<?php
// citizen_profile.php - Profile Management Module
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
                
                // Update session
                $_SESSION['email'] = $email;
                $_SESSION['profile_picture'] = $profile_picture;
                $_SESSION['permanent_address'] = $permanent_address;
                $_SESSION['contact_number'] = $contact_number;
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
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
    <!-- Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Profile Settings</h2>
        <p class="text-gray-600">Manage your contact information and account security</p>
    </div>
    
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
                            $profile_pic_path = "../uploads/profile_pictures/" . ($user['profile_picture'] ?? '');
                            if (!empty($user['profile_picture']) && file_exists($profile_pic_path)): 
                            ?>
                                <img src="<?php echo $profile_pic_path; ?>" 
                                     alt="Profile Picture" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-purple-600 to-blue-500 text-white text-4xl font-bold">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
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
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start text-gray-600">
                        <i class="fas fa-phone text-gray-400 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">Phone Number</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['contact_number']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start text-gray-600">
                        <i class="fas fa-map-marker-alt text-gray-400 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">Address</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['permanent_address']); ?></p>
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
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                        <p class="text-xs text-gray-500 mt-1">This email will be used for account notifications</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" name="contact_number" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                               value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                        <p class="text-xs text-gray-500 mt-1">Your mobile number for emergency contact</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Residential Address</label>
                        <textarea name="permanent_address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"><?php echo htmlspecialchars($user['permanent_address']); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Complete address including barangay</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                        <div class="flex items-center space-x-4">
                            <div class="flex-1">
                                <p class="text-sm text-gray-600 mb-2">Upload a new profile picture (max 2MB)</p>
                                <input type="file" name="profile_picture" 
                                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                       accept="image/*" onchange="previewProfileImage(this)">
                            </div>
                        </div>
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
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('profileImagePreview');
        
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="w-full h-full object-cover">`;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>