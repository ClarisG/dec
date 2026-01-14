<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page or show error
    header('Location: ../login.php');
    exit();
}

// Database connection - adjust the path based on your project structure
// Since this is in /tanod/modules/, we need to go up 2 levels to reach config
require_once '../../config/database.php';

$tanod_id = $_SESSION['user_id'];

// Get tanod details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$tanod_id]);
$tanod = $stmt->fetch();

$message = '';
$message_type = '';

// Update profile if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $email, $phone, $tanod_id])) {
            $message = 'Profile updated successfully!';
            $message_type = 'success';
            // Update session
            $_SESSION['username'] = $first_name . ' ' . $last_name;
            // Refresh tanod data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$tanod_id]);
            $tanod = $stmt->fetch();
        } else {
            $message = 'Failed to update profile. Please try again.';
            $message_type = 'error';
        }
    }
}
?>
<div class="p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Profile & Settings</h2>
    
    <?php if($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Personal Information</h3>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($tanod['first_name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($tanod['last_name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($tanod['email']); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($tanod['phone'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <button type="submit" name="update_profile" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Account Info -->
        <div>
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Account Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500">Username</p>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($tanod['username']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">User ID</p>
                        <p class="font-medium text-gray-800">TANOD-<?php echo str_pad($tanod['id'], 4, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Role</p>
                        <p class="font-medium text-gray-800"><?php echo ucfirst($tanod['role']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Account Created</p>
                        <p class="font-medium text-gray-800"><?php echo date('M d, Y', strtotime($tanod['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Last Login</p>
                        <p class="font-medium text-gray-800"><?php echo isset($tanod['last_login']) ? date('M d, Y h:i A', strtotime($tanod['last_login'])) : 'Never'; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h3>
                <form id="changePasswordForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" id="current_password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" id="new_password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required minlength="6">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required minlength="6">
                    </div>
                    <button type="button" onclick="changePassword()"
                            class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function changePassword() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all password fields');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        return;
    }
    
    if (newPassword.length < 6) {
        alert('New password must be at least 6 characters long');
        return;
    }
    
    const formData = new FormData();
    formData.append('current_password', currentPassword);
    formData.append('new_password', newPassword);
    
    fetch('../ajax/change_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password changed successfully!');
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        } else {
            alert(data.message || 'Failed to change password');
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
        console.error(error);
    });
}
</script>