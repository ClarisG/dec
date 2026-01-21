<?php
// citizen_profile.php - Profile Management Module
// Start session only once (already started in dashboard, but just in case)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $conn = getDbConnection();
    
    // Get user data
    $user_query = "SELECT * FROM users WHERE id = :id";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':id', $user_id);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error = "User not found.";
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            $email = trim($_POST['email']);
            $contact_number = trim($_POST['contact_number']);
            $permanent_address = trim($_POST['permanent_address']);
            
            // Handle profile picture upload
            $profile_picture = $user['profile_picture'];
            $new_image_uploaded = false;
            
            if (!empty($_FILES['profile_picture']['name'])) {
                $upload_dir = "../uploads/profile_pictures/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
                $file_path = $upload_dir . $file_name;
                
                // Check if image file
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_types)) {
                    // Check file size (max 2MB)
                    if ($_FILES['profile_picture']['size'] > 2097152) {
                        $error = "File size must be less than 2MB.";
                    } else {
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                            // Delete old profile picture if exists
                            if (!empty($profile_picture) && file_exists($upload_dir . $profile_picture)) {
                                unlink($upload_dir . $profile_picture);
                            }
                            
                            $profile_picture = $file_name;
                            $new_image_uploaded = true;
                            
                            // Update session immediately
                            $_SESSION['profile_picture'] = $profile_picture;
                        }
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            
            if (empty($error)) {
                // Update user
                $update_query = "UPDATE users SET 
                                email = :email,
                                contact_number = :contact_number,
                                permanent_address = :permanent_address,
                                profile_picture = :profile_picture,
                                updated_at = NOW()
                                WHERE id = :id";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([
                    ':email' => $email,
                    ':contact_number' => $contact_number,
                    ':permanent_address' => $permanent_address,
                    ':profile_picture' => $profile_picture,
                    ':id' => $user_id
                ]);
                
                // Update all session variables
                $_SESSION['email'] = $email;
                $_SESSION['profile_picture'] = $profile_picture;
                $_SESSION['permanent_address'] = $permanent_address;
                $_SESSION['contact_number'] = $contact_number;
                
                // Also update first_name and last_name in session if they exist in POST
                if (isset($_POST['first_name'])) {
                    $_SESSION['first_name'] = trim($_POST['first_name']);
                }
                if (isset($_POST['last_name'])) {
                    $_SESSION['last_name'] = trim($_POST['last_name']);
                }
                
                $success = "Profile updated successfully!";
                
                // Force refresh user data
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update the current $user array
                $user['email'] = $email;
                $user['contact_number'] = $contact_number;
                $user['permanent_address'] = $permanent_address;
                $user['profile_picture'] = $profile_picture;
                
                // If new image was uploaded, output JavaScript to update images immediately
                if ($new_image_uploaded) {
                    $image_url = '../uploads/profile_pictures/' . $profile_picture;
                    echo '<script>';
                    echo 'const event = new CustomEvent("profilePictureUpdated", { detail: { imageUrl: "' . $image_url . '" } });';
                    echo 'document.dispatchEvent(event);';
                    echo 'window.parent.document.dispatchEvent(event);';
                    echo '</script>';
                }
            }
            
        } catch(PDOException $e) {
            $error = "Update error: " . $e->getMessage();
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_query = "UPDATE users SET password = :password WHERE id = :id";
            $password_stmt = $conn->prepare($password_query);
            $password_stmt->execute([
                ':password' => $hashed_password,
                ':id' => $user_id
            ]);
            
            $success = "Password changed successfully!";
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?php echo $success; ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Profile Info -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="text-center mb-6">
                    <!-- Profile Picture -->
                    <div class="relative w-32 h-32 mx-auto mb-4">
                        <div id="profileImagePreview" class="w-full h-full rounded-full overflow-hidden bg-gray-200 border-4 border-white shadow-lg">
                            <?php 
                            // Use session variable first, then database
                            $current_profile_pic = $_SESSION['profile_picture'] ?? $user['profile_picture'] ?? '';
                            $profile_pic_path = "../uploads/profile_pictures/" . $current_profile_pic;
                            
                            if (!empty($current_profile_pic) && file_exists($profile_pic_path)): 
                                // Add cache-busting timestamp
                                $timestamp = filemtime($profile_pic_path);
                            ?>
                                <img src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                                     alt="Profile Picture" 
                                     class="w-full h-full object-cover"
                                     id="currentProfileImage">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-purple-600 to-blue-500 text-white text-4xl font-bold"
                                     id="defaultProfileImage">
                                    <?php 
                                    $firstName = $_SESSION['first_name'] ?? $user['first_name'] ?? '';
                                    echo strtoupper(substr($firstName, 0, 1)); 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label for="profile_picture" class="absolute bottom-0 right-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer hover:bg-blue-700 shadow-lg transition-all">
                            <i class="fas fa-camera text-white"></i>
                            <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/*" onchange="previewProfileImage(this)">
                        </label>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p class="text-gray-600">Citizen</p>
                    <p class="text-sm text-gray-500 mt-2">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-start mb-3">
                            <i class="fas fa-user text-gray-400 mt-1 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-500">Full Name</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 ml-8">Name cannot be changed for security reasons</p>
                    </div>
                    
                    <div class="flex items-start text-gray-600">
                        <i class="fas fa-envelope text-gray-400 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start text-gray-600">
                        <i class="fas fa-phone text-gray-400 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">Phone Number</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start text-gray-600">
                        <i class="fas fa-map-marker-alt text-gray-400 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">Address</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Forms -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Update Profile Form -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-3 border-b">Update Contact Information</h3>
                
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                               value="<?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?>">
                        <p class="text-xs text-gray-500 mt-1">This email will be used for account notifications</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" name="contact_number" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                               value="<?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?>">
                        <p class="text-xs text-gray-500 mt-1">Your mobile number for emergency contact</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Residential Address</label>
                        <textarea name="permanent_address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Complete address including barangay</p>
                    </div>
                    
                    <div class="flex justify-end pt-4 border-t">
                        <button type="submit" name="update_profile"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all font-medium">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-3 border-b">Change Password</h3>
                
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                   placeholder="Enter your current password">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                   placeholder="Enter new password (min. 8 characters)">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                   placeholder="Re-enter new password">
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t flex justify-end">
                        <button type="submit" name="change_password"
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all font-medium">
                            <i class="fas fa-key mr-2"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Function to preview profile image
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('profileImagePreview');
        
        reader.onload = function(e) {
            // Update the preview immediately
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="w-full h-full object-cover" id="currentProfileImage">`;
            
            // Show the uploaded image and hide default
            const defaultImage = document.getElementById('defaultProfileImage');
            if (defaultImage) {
                defaultImage.style.display = 'none';
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// When form is submitted, handle immediate profile picture update
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const profilePictureInput = document.getElementById('profile_picture');
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            // Check if profile picture is being uploaded
            const hasFile = profilePictureInput && profilePictureInput.files.length > 0;
            
            if (hasFile) {
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    submitBtn.disabled = true;
                    
                    // Reset button after 3 seconds if still disabled (fallback)
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 3000);
                }
            }
        });
    }
    
    // Listen for profile picture change events
    document.addEventListener('profilePictureUpdated', function(e) {
        if (e.detail && e.detail.imageUrl) {
            const timestamp = new Date().getTime();
            const preview = document.getElementById('currentProfileImage');
            const defaultImage = document.getElementById('defaultProfileImage');
            
            if (preview) {
                preview.src = e.detail.imageUrl + '?t=' + timestamp;
                preview.style.display = 'block';
            }
            
            if (defaultImage) {
                defaultImage.style.display = 'none';
            }
            
            // Also update the image in the profile module
            const profilePreview = document.getElementById('profileImagePreview');
            if (profilePreview && !preview) {
                profilePreview.innerHTML = `<img src="${e.detail.imageUrl}?t=${timestamp}" alt="Profile Picture" class="w-full h-full object-cover" id="currentProfileImage">`;
            }
        }
    });
});
</script>