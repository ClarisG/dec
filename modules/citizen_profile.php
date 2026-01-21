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
            
            if (!empty($_FILES['profile_picture']['name']) && $_FILES['profile_picture']['error'] == 0) {
                $upload_dir = __DIR__ . "/../uploads/profile_pictures/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
                $file_name = preg_replace('/[^a-zA-Z0-9\.\_\-]/', '', $file_name);
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
                                @unlink($upload_dir . $profile_picture);
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

<div class="max-w-6xl mx-auto px-4">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Profile Settings</h1>
        <p class="text-gray-600">Manage your account information and security settings</p>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 animate-fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?php echo $error; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 animate-fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?php echo $success; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Profile Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <!-- Profile Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center">
                        <!-- Profile Picture -->
                        <div class="relative">
                            <div class="w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-md bg-gradient-to-br from-blue-50 to-indigo-50">
                                <?php 
                                $current_profile_pic = $_SESSION['profile_picture'] ?? $user['profile_picture'] ?? '';
                                $profile_pic_path = "uploads/profile_pictures/" . $current_profile_pic;
                                $full_path = __DIR__ . "/../" . $profile_pic_path;
                                
                                if (!empty($current_profile_pic) && file_exists($full_path)): 
                                    $timestamp = filemtime($full_path);
                                ?>
                                    <img src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                                         alt="Profile Picture" 
                                         class="w-full h-full object-cover"
                                         id="currentProfileImage">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-2xl font-bold"
                                         id="defaultProfileImage">
                                        <?php 
                                        $firstName = $_SESSION['first_name'] ?? $user['first_name'] ?? 'U';
                                        echo strtoupper(substr($firstName, 0, 1)); 
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label for="profile_picture" class="absolute -bottom-1 -right-1 w-8 h-8 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full flex items-center justify-center cursor-pointer hover:from-blue-700 hover:to-indigo-700 shadow-lg transition-all duration-200">
                                <i class="fas fa-camera text-white text-sm"></i>
                                <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/*" onchange="previewProfileImage(this)">
                            </label>
                        </div>
                        
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-user-circle mr-1 text-xs"></i>
                                    Citizen
                                </span>
                                <span class="ml-2 text-xs text-gray-500">
                                    <i class="far fa-calendar mr-1"></i>
                                    Joined <?php echo date('M Y', strtotime($user['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Details -->
                <div class="p-4">
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Email</p>
                                <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                                <i class="fas fa-phone text-green-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Phone</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-orange-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Address</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1 text-gray-400"></i>
                            Your full name cannot be changed for security purposes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Forms -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Update Profile Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center mr-3">
                            <i class="fas fa-user-edit text-white text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Personal Information</h3>
                            <p class="text-sm text-gray-500">Update your contact details</p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="profileForm" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span class="flex items-center">
                                    <i class="fas fa-envelope mr-2 text-blue-500 text-xs"></i>
                                    Email Address
                                </span>
                            </label>
                            <input type="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors"
                                   value="<?php echo htmlspecialchars($_SESSION['email'] ?? $user['email']); ?>"
                                   placeholder="your.email@example.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span class="flex items-center">
                                    <i class="fas fa-phone mr-2 text-green-500 text-xs"></i>
                                    Contact Number
                                </span>
                            </label>
                            <input type="tel" name="contact_number" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm transition-colors"
                                   value="<?php echo htmlspecialchars($_SESSION['contact_number'] ?? $user['contact_number']); ?>"
                                   placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span class="flex items-center">
                                <i class="fas fa-home mr-2 text-orange-500 text-xs"></i>
                                Residential Address
                            </span>
                        </label>
                        <textarea name="permanent_address" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm transition-colors resize-none"
                                  placeholder="House No., Street, Barangay, City, Province"><?php echo htmlspecialchars($_SESSION['permanent_address'] ?? $user['permanent_address']); ?></textarea>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                        <button type="submit" name="update_profile" id="saveProfileBtn"
                                class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 text-sm font-medium transition-all duration-200 flex items-center">
                            <i class="fas fa-save mr-2 text-xs"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-green-500 to-emerald-500 flex items-center justify-center mr-3">
                            <i class="fas fa-key text-white text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Security Settings</h3>
                            <p class="text-sm text-gray-500">Change your password for enhanced security</p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span class="flex items-center">
                                    <i class="fas fa-lock mr-2 text-gray-500 text-xs"></i>
                                    Current Password
                                </span>
                            </label>
                            <div class="relative">
                                <input type="password" name="current_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 text-sm transition-colors pr-10"
                                       placeholder="Enter your current password"
                                       id="currentPassword">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                                        onclick="togglePassword('currentPassword', this)">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span class="flex items-center">
                                    <i class="fas fa-lock mr-2 text-blue-500 text-xs"></i>
                                    New Password
                                </span>
                            </label>
                            <div class="relative">
                                <input type="password" name="new_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors pr-10"
                                       placeholder="Enter new password (min. 8 characters)"
                                       id="newPassword">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                                        onclick="togglePassword('newPassword', this)">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                            <div class="flex items-center mt-2 space-x-1">
                                <div class="h-1.5 flex-1 rounded-full bg-gray-200" id="passwordStrength1"></div>
                                <div class="h-1.5 flex-1 rounded-full bg-gray-200" id="passwordStrength2"></div>
                                <div class="h-1.5 flex-1 rounded-full bg-gray-200" id="passwordStrength3"></div>
                                <div class="h-1.5 flex-1 rounded-full bg-gray-200" id="passwordStrength4"></div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span class="flex items-center">
                                    <i class="fas fa-lock mr-2 text-green-500 text-xs"></i>
                                    Confirm New Password
                                </span>
                            </label>
                            <div class="relative">
                                <input type="password" name="confirm_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm transition-colors pr-10"
                                       placeholder="Re-enter new password"
                                       id="confirmPassword">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                                        onclick="togglePassword('confirmPassword', this)">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                            <p class="text-xs mt-1 text-gray-500" id="passwordMatch">
                                <i class="fas fa-info-circle mr-1"></i>
                                Passwords must match
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                        <button type="submit" name="change_password" id="changePasswordBtn"
                                class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-1 text-sm font-medium transition-all duration-200 flex items-center">
                            <i class="fas fa-key mr-2 text-xs"></i>
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

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>

<script>
// Function to preview profile image
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('currentProfileImage');
        const defaultImage = document.getElementById('defaultProfileImage');
        const button = document.getElementById('saveProfileBtn');
        
        reader.onload = function(e) {
            // Update preview immediately
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (defaultImage) defaultImage.style.display = 'none';
            } else if (defaultImage) {
                // Create new image element
                const newImage = document.createElement('img');
                newImage.src = e.target.result;
                newImage.alt = "Profile Preview";
                newImage.className = "w-full h-full object-cover";
                newImage.id = "currentProfileImage";
                
                defaultImage.parentNode.appendChild(newImage);
                defaultImage.style.display = 'none';
            }
            
            // Change button to indicate pending changes
            if (button) {
                button.innerHTML = '<i class="fas fa-camera mr-2 text-xs"></i>Upload & Save';
                button.classList.add('from-purple-600', 'to-pink-600');
            }
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
    
    if (!newPassword || !confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Passwords must match';
        matchText.className = 'text-xs mt-1 text-gray-500';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-check-circle mr-1 text-green-500"></i>Passwords match';
        matchText.className = 'text-xs mt-1 text-green-600';
    } else {
        matchText.innerHTML = '<i class="fas fa-times-circle mr-1 text-red-500"></i>Passwords do not match';
        matchText.className = 'text-xs mt-1 text-red-600';
    }
}

// Initialize when page loads
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
        newPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Form submission handlers
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            // Check if profile picture is being uploaded
            const hasFile = profilePictureInput && profilePictureInput.files.length > 0;
            
            if (hasFile || this.checkValidity()) {
                // Show loading state
                if (saveProfileBtn) {
                    const originalText = saveProfileBtn.innerHTML;
                    saveProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    saveProfileBtn.disabled = true;
                    
                    // Reset button after 5 seconds if still disabled (fallback)
                    setTimeout(() => {
                        if (saveProfileBtn.disabled) {
                            saveProfileBtn.innerHTML = originalText;
                            saveProfileBtn.disabled = false;
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
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
                this.disabled = true;
                
                // Reset button after 5 seconds if still disabled (fallback)
                setTimeout(() => {
                    if (this.disabled) {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }
                }, 5000);
            }
        });
    }
    
    // Listen for profile picture update events from PHP
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
                const parent = defaultImage.parentNode;
                const newImg = document.createElement('img');
                newImg.src = imageUrl;
                newImg.alt = "Profile Picture";
                newImg.className = "w-full h-full object-cover";
                newImg.id = "currentProfileImage";
                parent.appendChild(newImg);
                defaultImage.style.display = 'none';
            }
            
            // Dispatch event to parent (dashboard) to update all images
            if (window.parent && window.parent !== window) {
                window.parent.document.dispatchEvent(new CustomEvent('profilePictureUpdated', {
                    detail: { imageUrl: e.detail.imageUrl }
                }));
            }
            
            // Also send message via postMessage for cross-domain compatibility
            if (window.parent) {
                window.parent.postMessage({
                    type: 'profilePictureUpdated',
                    imageUrl: e.detail.imageUrl
                }, '*');
            }
        }
    });
    
    // Listen to events from parent
    window.addEventListener('message', function(event) {
        if (event.data.type === 'profilePictureUpdated' && event.data.imageUrl) {
            document.dispatchEvent(new CustomEvent('profilePictureUpdated', {
                detail: { imageUrl: event.data.imageUrl }
            }));
        }
    });
});
</script>