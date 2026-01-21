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
            $image_url = '';
            
            if (!empty($_FILES['profile_picture']['name'])) {
                $upload_dir = __DIR__ . "/../uploads/profile_pictures/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\_\-]/', '', basename($_FILES['profile_picture']['name']));
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
                            $image_url = 'uploads/profile_pictures/' . $profile_picture;
                            
                            // Update session immediately
                            $_SESSION['profile_picture'] = $profile_picture;
                        } else {
                            $error = "Failed to upload image. Please try again.";
                        }
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            
            if (empty($error)) {
                // Update user in database
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
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update the current $user array
                $user['email'] = $email;
                $user['contact_number'] = $contact_number;
                $user['permanent_address'] = $permanent_address;
                $user['profile_picture'] = $profile_picture;
                
                // If new image was uploaded, output JavaScript to update images immediately
                if ($new_image_uploaded && !empty($image_url)) {
                    echo '<script>';
                    echo 'document.dispatchEvent(new CustomEvent("profilePictureUpdated", { detail: { imageUrl: "' . $image_url . '" } }));';
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
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg animate-fade-in">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg animate-fade-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?php echo $success; ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Profile Info -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform transition-all duration-300 hover:shadow-xl">
                <div class="text-center mb-6">
                    <!-- Profile Picture -->
                    <div class="relative w-40 h-40 mx-auto mb-4 group">
                        <div id="profileImagePreview" class="w-full h-full rounded-full overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-50 border-4 border-white shadow-xl">
                            <?php 
                            $current_profile_pic = $_SESSION['profile_picture'] ?? $user['profile_picture'] ?? '';
                            $profile_pic_path = "uploads/profile_pictures/" . $current_profile_pic;
                            $full_path = __DIR__ . "/../" . $profile_pic_path;
                            
                            if (!empty($current_profile_pic) && file_exists($full_path)): 
                                $timestamp = filemtime($full_path);
                            ?>
                                <img src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                                     alt="Profile Picture" 
                                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                     id="currentProfileImage">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-5xl font-bold transition-all duration-300 group-hover:from-blue-700 group-hover:to-indigo-700"
                                     id="defaultProfileImage">
                                    <?php 
                                    $firstName = $_SESSION['first_name'] ?? $user['first_name'] ?? 'U';
                                    echo strtoupper(substr($firstName, 0, 1)); 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label for="profile_picture" class="absolute bottom-4 right-4 w-12 h-12 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full flex items-center justify-center cursor-pointer hover:from-blue-700 hover:to-indigo-700 shadow-xl transition-all duration-300 transform hover:scale-110 group">
                            <i class="fas fa-camera text-white text-lg"></i>
                            <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/*" onchange="previewProfileImage(this)">
                        </label>
                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 bg-white px-3 py-1 rounded-full shadow-md text-xs text-gray-600 font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            Click to change
                        </div>
                    </div>
                    
                    <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <div class="inline-flex items-center px-3 py-1 rounded-full bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-600 text-sm font-medium mb-2">
                        <i class="fas fa-user-circle mr-2"></i> Citizen
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        <i class="far fa-calendar-alt mr-1"></i>
                        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100">
                        <div class="flex items-start mb-2">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center mr-3">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Full Name</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 pl-13 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Name cannot be changed for security reasons
                        </p>
                    </div>
                    
                    <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center mr-3 flex-shrink-0">
                            <i class="fas fa-envelope text-white"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-500">Email Address</p>
                            <p class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-500 to-emerald-500 flex items-center justify-center mr-3 flex-shrink-0">
                            <i class="fas fa-phone text-white"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-500">Phone Number</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center mr-3 flex-shrink-0">
                            <i class="fas fa-map-marker-alt text-white"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-500">Residential Address</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Forms -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Update Profile Form -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform transition-all duration-300 hover:shadow-xl">
                <div class="flex items-center mb-6 pb-4 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center mr-4">
                        <i class="fas fa-user-edit text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Update Contact Information</h3>
                        <p class="text-sm text-gray-500">Manage your account details and contact information</p>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="profileForm" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-envelope mr-2 text-blue-500"></i>
                                Email Address
                            </span>
                        </label>
                        <input type="email" name="email" required
                               class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50/50"
                               value="<?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?>"
                               placeholder="your.email@example.com">
                        <p class="text-xs text-gray-500 flex items-center mt-1">
                            <i class="fas fa-info-circle mr-1 text-blue-400"></i>
                            This email will be used for account notifications and recovery
                        </p>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-phone mr-2 text-green-500"></i>
                                Contact Number
                            </span>
                        </label>
                        <input type="tel" name="contact_number" required
                               class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50/50"
                               value="<?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?>"
                               placeholder="+63 XXX XXX XXXX">
                        <p class="text-xs text-gray-500 flex items-center mt-1">
                            <i class="fas fa-info-circle mr-1 text-green-400"></i>
                            Your mobile number for emergency contact and verification
                        </p>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-home mr-2 text-orange-500"></i>
                                Residential Address
                            </span>
                        </label>
                        <textarea name="permanent_address" rows="4"
                                  class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200 bg-gray-50/50 resize-none"
                                  placeholder="House No., Street, Barangay, City, Province"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></textarea>
                        <p class="text-xs text-gray-500 flex items-center mt-1">
                            <i class="fas fa-info-circle mr-1 text-orange-400"></i>
                            Complete address including barangay for accurate service delivery
                        </p>
                    </div>
                    
                    <div class="pt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit" name="update_profile" id="saveProfileBtn"
                                class="px-8 py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform transition-all duration-300 hover:shadow-xl">
                <div class="flex items-center mb-6 pb-4 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-green-500 to-emerald-500 flex items-center justify-center mr-4">
                        <i class="fas fa-key text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Change Password</h3>
                        <p class="text-sm text-gray-500">Update your password for enhanced security</p>
                    </div>
                </div>
                
                <form method="POST" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-lock mr-2 text-gray-500"></i>
                                Current Password
                            </span>
                        </label>
                        <div class="relative">
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition-all duration-200 bg-gray-50/50 pr-12"
                                   placeholder="Enter your current password"
                                   id="currentPassword">
                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    onclick="togglePassword('currentPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-lock mr-2 text-blue-500"></i>
                                New Password
                            </span>
                        </label>
                        <div class="relative">
                            <input type="password" name="new_password" required
                                   class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50/50 pr-12"
                                   placeholder="Enter new password (min. 8 characters)"
                                   id="newPassword">
                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    onclick="togglePassword('newPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 gap-2 mt-2">
                            <div class="h-1 rounded bg-gray-200" id="passwordStrength1"></div>
                            <div class="h-1 rounded bg-gray-200" id="passwordStrength2"></div>
                            <div class="h-1 rounded bg-gray-200" id="passwordStrength3"></div>
                            <div class="h-1 rounded bg-gray-200" id="passwordStrength4"></div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-lock mr-2 text-green-500"></i>
                                Confirm New Password
                            </span>
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50/50 pr-12"
                                   placeholder="Re-enter new password"
                                   id="confirmPassword">
                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    onclick="togglePassword('confirmPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 flex items-center mt-1" id="passwordMatch">
                            <i class="fas fa-info-circle mr-1 text-gray-400"></i>
                            Passwords must match
                        </p>
                    </div>
                    
                    <div class="pt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit" name="change_password" id="changePasswordBtn"
                                class="px-8 py-3.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center">
                            <i class="fas fa-key mr-2"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
// Function to preview profile image
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('profileImagePreview');
        const button = document.getElementById('saveProfileBtn');
        
        reader.onload = function(e) {
            // Update the preview immediately with animation
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105 animate-fade-in" id="currentProfileImage">`;
            
            // Show the uploaded image and hide default
            const defaultImage = document.getElementById('defaultProfileImage');
            if (defaultImage) {
                defaultImage.style.display = 'none';
            }
            
            // Change button color to indicate pending changes
            button.innerHTML = '<i class="fas fa-camera mr-2"></i>Upload & Save Changes';
            button.classList.remove('from-blue-600', 'to-indigo-600');
            button.classList.add('from-purple-600', 'to-pink-600', 'hover:from-purple-700', 'hover:to-pink-700');
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle password visibility
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength indicator
function checkPasswordStrength() {
    const password = document.getElementById('newPassword').value;
    const strengthBars = [
        document.getElementById('passwordStrength1'),
        document.getElementById('passwordStrength2'),
        document.getElementById('passwordStrength3'),
        document.getElementById('passwordStrength4')
    ];
    
    let strength = 0;
    
    // Reset bars
    strengthBars.forEach(bar => {
        bar.classList.remove('bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500');
        bar.classList.add('bg-gray-200');
    });
    
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Update bars
    for (let i = 0; i < strength; i++) {
        let colorClass;
        if (strength === 1) colorClass = 'bg-red-500';
        else if (strength === 2) colorClass = 'bg-orange-500';
        else if (strength === 3) colorClass = 'bg-yellow-500';
        else if (strength === 4) colorClass = 'bg-green-500';
        
        strengthBars[i].classList.remove('bg-gray-200');
        strengthBars[i].classList.add(colorClass);
    }
}

// Check if passwords match
function checkPasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchText = document.getElementById('passwordMatch');
    const icon = matchText.querySelector('i');
    
    if (!newPassword || !confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-info-circle mr-1 text-gray-400"></i>Passwords must match';
        matchText.className = 'text-xs text-gray-500 flex items-center mt-1';
        icon.className = 'fas fa-info-circle mr-1 text-gray-400';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-check-circle mr-1 text-green-500"></i>Passwords match';
        matchText.className = 'text-xs text-green-600 flex items-center mt-1';
        icon.className = 'fas fa-check-circle mr-1 text-green-500';
    } else {
        matchText.innerHTML = '<i class="fas fa-times-circle mr-1 text-red-500"></i>Passwords do not match';
        matchText.className = 'text-xs text-red-600 flex items-center mt-1';
        icon.className = 'fas fa-times-circle mr-1 text-red-500';
    }
}

// When form is submitted, handle immediate profile picture update
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const profilePictureInput = document.getElementById('profile_picture');
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    // Password strength and match listeners
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', checkPasswordStrength);
    }
    
    if (newPasswordInput && confirmPasswordInput) {
        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            // Check if profile picture is being uploaded
            const hasFile = profilePictureInput && profilePictureInput.files.length > 0;
            
            if (hasFile || this.checkValidity()) {
                // Show loading state
                if (saveProfileBtn) {
                    const originalText = saveProfileBtn.innerHTML;
                    saveProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving Changes...';
                    saveProfileBtn.disabled = true;
                    saveProfileBtn.classList.remove('hover:-translate-y-0.5', 'hover:shadow-xl');
                    
                    // Reset button after 5 seconds if still disabled (fallback)
                    setTimeout(() => {
                        if (saveProfileBtn.disabled) {
                            saveProfileBtn.innerHTML = originalText;
                            saveProfileBtn.disabled = false;
                            saveProfileBtn.classList.add('hover:-translate-y-0.5', 'hover:shadow-xl');
                        }
                    }, 5000);
                }
            }
        });
    }
    
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function(e) {
            const form = this.closest('form');
            if (form && form.checkValidity()) {
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Changing Password...';
                this.disabled = true;
                this.classList.remove('hover:-translate-y-0.5', 'hover:shadow-xl');
                
                // Reset button after 5 seconds if still disabled (fallback)
                setTimeout(() => {
                    if (this.disabled) {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        this.classList.add('hover:-translate-y-0.5', 'hover:shadow-xl');
                    }
                }, 5000);
            }
        });
    }
    
    // Listen for profile picture update events
    document.addEventListener('profilePictureUpdated', function(e) {
        if (e.detail && e.detail.imageUrl) {
            // Update timestamp to prevent caching
            const timestamp = new Date().getTime();
            const imageUrl = e.detail.imageUrl + '?t=' + timestamp;
            
            // Update profile picture in this module
            const preview = document.getElementById('currentProfileImage');
            const defaultImage = document.getElementById('defaultProfileImage');
            
            if (preview) {
                preview.src = imageUrl;
                preview.style.display = 'block';
            } else if (defaultImage) {
                // Replace default image with uploaded image
                const previewDiv = document.getElementById('profileImagePreview');
                if (previewDiv) {
                    previewDiv.innerHTML = `<img src="${imageUrl}" alt="Profile Picture" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105 animate-fade-in" id="currentProfileImage">`;
                }
            }
            
            // Dispatch event to parent (dashboard) to update all images
            if (window.parent && window.parent !== window) {
                window.parent.document.dispatchEvent(new CustomEvent('profilePictureUpdated', {
                    detail: { imageUrl: e.detail.imageUrl }
                }));
            }
        }
    });
    
    // Also listen to events from parent
    window.addEventListener('message', function(event) {
        if (event.data.type === 'profilePictureUpdated' && event.data.imageUrl) {
            document.dispatchEvent(new CustomEvent('profilePictureUpdated', {
                detail: { imageUrl: event.data.imageUrl }
            }));
        }
    });
});
</script>