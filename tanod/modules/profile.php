<?php
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$pdo = getDbConnection();

// Get tanod details
$stmt = $pdo->prepare("
    SELECT u.*, b.name as barangay_name,
           (SELECT COUNT(*) FROM tanod_duty_logs WHERE user_id = u.id AND MONTH(clock_in) = MONTH(CURDATE())) as monthly_shifts,
           (SELECT AVG(TIMESTAMPDIFF(HOUR, clock_in, COALESCE(clock_out, NOW()))) FROM tanod_duty_logs WHERE user_id = u.id) as avg_duty_hours
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$tanod_id]);
$tanod = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$message_type = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        
        // Validate
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $message = "❌ Please fill in all required fields";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "❌ Invalid email format";
            $message_type = 'error';
        } else {
            try {
                // Check if email exists for another user
                $email_check = $pdo->prepare("
                    SELECT id FROM users WHERE email = ? AND id != ?
                ");
                $email_check->execute([$email, $tanod_id]);
                
                if ($email_check->rowCount() > 0) {
                    $message = "❌ Email already in use by another user";
                    $message_type = 'error';
                } else {
                    // Update profile
                    $update_stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?,
                            address = ?, emergency_contact = ?, emergency_phone = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    $update_stmt->execute([
                        $first_name, $last_name, $email, $phone,
                        $address, $emergency_contact, $emergency_phone,
                        $tanod_id
                    ]);
                    
                    // Update session
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    
                    // Refresh tanod data
                    $stmt->execute([$tanod_id]);
                    $tanod = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log activity
                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs 
                        (user_id, action, description, ip_address, created_at) 
                        VALUES (?, 'profile_update', 'Updated profile information', ?, NOW())
                    ");
                    $log_stmt->execute([$tanod_id, $_SERVER['REMOTE_ADDR']]);
                    
                    $message = "✅ Profile updated successfully";
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                error_log("Profile Update Error: " . $e->getMessage());
                $message = "❌ Error updating profile: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "❌ Please fill in all password fields";
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = "❌ New passwords do not match";
            $message_type = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = "❌ New password must be at least 8 characters";
            $message_type = 'error';
        } else {
            try {
                // Verify current password
                $verify_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $verify_stmt->execute([$tanod_id]);
                $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("
                        UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?
                    ");
                    $update_stmt->execute([$hashed_password, $tanod_id]);
                    
                    // Log activity
                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs 
                        (user_id, action, description, ip_address, created_at) 
                        VALUES (?, 'password_change', 'Changed account password', ?, NOW())
                    ");
                    $log_stmt->execute([$tanod_id, $_SERVER['REMOTE_ADDR']]);
                    
                    $message = "✅ Password changed successfully";
                    $message_type = 'success';
                } else {
                    $message = "❌ Current password is incorrect";
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                error_log("Password Change Error: " . $e->getMessage());
                $message = "❌ Error changing password: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get performance statistics
try {
    // Monthly performance
    $perf_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as reports_vetted,
            COUNT(DISTINCT ti.id) as incidents_logged,
            COUNT(DISTINCT eh.id) as evidence_handovers,
            AVG(rv.accuracy_rating) as avg_accuracy
        FROM users u
        LEFT JOIN reports r ON r.assigned_tanod = u.id AND MONTH(r.verification_date) = MONTH(CURDATE())
        LEFT JOIN tanod_incidents ti ON ti.user_id = u.id AND MONTH(ti.reported_at) = MONTH(CURDATE())
        LEFT JOIN evidence_handovers eh ON eh.tanod_id = u.id AND MONTH(eh.handover_date) = MONTH(CURDATE())
        LEFT JOIN tanod_ratings rv ON rv.tanod_id = u.id
        WHERE u.id = ?
    ");
    $perf_stmt->execute([$tanod_id]);
    $performance = $perf_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Performance Stats Error: " . $e->getMessage());
    $performance = ['reports_vetted' => 0, 'incidents_logged' => 0, 'evidence_handovers' => 0, 'avg_accuracy' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & Account Settings - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .profile-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-reports { border-left-color: #3b82f6; }
        .stat-incidents { border-left-color: #10b981; }
        .stat-evidence { border-left-color: #8b5cf6; }
        .stat-accuracy { border-left-color: #f59e0b; }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring__circle {
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 0.5s ease;
        }
        
        .badge-verified {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .form-section {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="profile-header p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div class="flex items-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-white to-blue-100 rounded-full flex items-center justify-center text-blue-600 text-2xl font-bold border-4 border-white shadow-lg">
                            <?php echo strtoupper(substr($tanod['first_name'], 0, 1) . substr($tanod['last_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-6">
                            <h1 class="text-2xl md:text-3xl font-bold text-white">Profile Account & Tanod Status</h1>
                            <p class="text-blue-100 mt-2">Manage contact information and view performance metrics</p>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="text-right">
                            <p class="text-white text-sm">Tanod ID</p>
                            <p class="text-white font-bold text-lg">TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                    <?php echo $tanod['status'] === 'active' ? 'badge-verified' : 'badge-pending'; ?>">
                                    <i class="fas fa-<?php echo $tanod['status'] === 'active' ? 'check-circle' : 'clock'; ?> mr-2"></i>
                                    <?php echo ucfirst($tanod['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Critical Data Handled -->
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-bold text-blue-800">Critical Data Handled: Contact details, Duty status (set by patrol schedule or Admin/Super Admin)</p>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg border-l-4 
            <?php echo $message_type === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 
                   ($message_type === 'warning' ? 'bg-yellow-50 border-yellow-500 text-yellow-800' : 'bg-red-50 border-red-500 text-red-800'); ?>">
            <div class="flex items-center">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 
                                      ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> 
                    text-xl mr-3"></i>
                <p class="font-bold"><?php echo $message; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Performance Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card stat-reports bg-white rounded-xl shadow-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Reports Vetted</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $performance['reports_vetted']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-blue-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">This month</p>
            </div>
            
            <div class="stat-card stat-incidents bg-white rounded-xl shadow-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Incidents Logged</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $performance['incidents_logged']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-green-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">This month</p>
            </div>
            
            <div class="stat-card stat-evidence bg-white rounded-xl shadow-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Evidence Handovers</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $performance['evidence_handovers']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-purple-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">This month</p>
            </div>
            
            <div class="stat-card stat-accuracy bg-white rounded-xl shadow-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Avg. Accuracy</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo $performance['avg_accuracy'] ? round($performance['avg_accuracy'], 1) . '/5' : 'N/A'; ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Based on ratings</p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Personal Information -->
            <div>
                <!-- Personal Information Form -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-800">Personal Information</h2>
                            <p class="text-sm text-gray-600">Update your contact details</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    First Name *
                                </label>
                                <input type="text" name="first_name" required 
                                       value="<?php echo htmlspecialchars($tanod['first_name']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Name *
                                </label>
                                <input type="text" name="last_name" required 
                                       value="<?php echo htmlspecialchars($tanod['last_name']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address *
                            </label>
                            <input type="email" name="email" required 
                                   value="<?php echo htmlspecialchars($tanod['email']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number
                            </label>
                            <input type="tel" name="phone" 
                                   value="<?php echo htmlspecialchars($tanod['phone'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Residential Address
                            </label>
                            <textarea name="address" rows="2"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"><?php echo htmlspecialchars($tanod['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Emergency Contact
                                </label>
                                <input type="text" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($tanod['emergency_contact'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Emergency Phone
                                </label>
                                <input type="tel" name="emergency_phone" 
                                       value="<?php echo htmlspecialchars($tanod['emergency_phone'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="update_profile" 
                                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold py-3 px-4 rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                                <i class="fas fa-save mr-2"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Information -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-gray-600 to-gray-700 rounded-lg flex items-center justify-center">
                            <i class="fas fa-id-card text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-800">Account Information</h2>
                            <p class="text-sm text-gray-600">System account details</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Username</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($tanod['username']); ?></p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">User ID</p>
                                <p class="font-medium text-gray-800">TAN-<?php echo str_pad($tanod['id'], 4, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Barangay</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($tanod['barangay_name'] ?? 'Not assigned'); ?></p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Role</p>
                                <p class="font-medium text-gray-800"><?php echo ucfirst($tanod['role']); ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Account Created</p>
                                <p class="font-medium text-gray-800"><?php echo date('M d, Y', strtotime($tanod['created_at'])); ?></p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Last Login</p>
                                <p class="font-medium text-gray-800">
                                    <?php echo isset($tanod['last_login']) ? date('M d, h:i A', strtotime($tanod['last_login'])) : 'Never'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Monthly Shifts</p>
                                <p class="font-medium text-gray-800"><?php echo $tanod['monthly_shifts']; ?></p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Avg. Duty Hours</p>
                                <p class="font-medium text-gray-800"><?php echo round($tanod['avg_duty_hours'] ?? 0, 1); ?>h</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Password Change -->
            <div>
                <!-- Change Password -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-lock text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
                            <p class="text-sm text-gray-600">Update your account password</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="" class="space-y-4" id="passwordForm">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Current Password *
                            </label>
                            <div class="relative">
                                <input type="password" name="current_password" required id="currentPassword"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <button type="button" onclick="togglePassword('currentPassword')" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Password *
                            </label>
                            <div class="relative">
                                <input type="password" name="new_password" required id="newPassword"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <button type="button" onclick="togglePassword('newPassword')" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="flex items-center mb-1">
                                    <div id="strengthIndicator" class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="strengthBar" class="h-full transition-all duration-300"></div>
                                    </div>
                                    <span id="strengthText" class="ml-3 text-xs text-gray-500">Weak</span>
                                </div>
                                <p class="text-xs text-gray-500">Password must be at least 8 characters</p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm New Password *
                            </label>
                            <div class="relative">
                                <input type="password" name="confirm_password" required id="confirmPassword"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <button type="button" onclick="togglePassword('confirmPassword')" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-2 text-xs hidden"></div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="change_password" 
                                    class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white font-bold py-3 px-4 rounded-lg hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition">
                                <i class="fas fa-key mr-2"></i>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Status Information -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-check text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-800">Tanod Status</h2>
                            <p class="text-sm text-gray-600">Your current duty status and settings</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-green-800">Current Status</p>
                                    <p class="text-lg font-bold text-green-900">
                                        <?php echo ucfirst($tanod['status']); ?>
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-<?php echo $tanod['status'] === 'active' ? 'check-circle' : 'clock'; ?> text-green-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-green-700 mt-2">
                                Status is automatically set by patrol schedule or can be toggled by Admin/Super Admin
                            </p>
                        </div>
                        
                        <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                            <h4 class="font-bold text-blue-800 mb-2">Status Legend</h4>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-700">Active - Can receive assignments</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-700">Inactive - Not available for duty</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-700">Suspended - Account temporarily disabled</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg">
                            <h4 class="font-bold text-gray-800 mb-2">Need Help?</h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <i class="fas fa-question-circle text-blue-500 mr-2 mt-1"></i>
                                    <span>Contact barangay admin for status changes</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2 mt-1"></i>
                                    <span>Report any account issues immediately</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-shield-alt text-green-500 mr-2 mt-1"></i>
                                    <span>Keep your password secure and confidential</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Barangay LEIR Profile Management System v2.0 &copy; <?php echo date('Y'); ?></p>
            <p class="mt-1">Contact details and duty status management</p>
        </div>
    </div>
    
    <script>
    // Password strength checker
    function checkPasswordStrength(password) {
        let strength = 0;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        let color, width, text;
        switch(strength) {
            case 0:
            case 1:
                color = '#ef4444';
                width = '20%';
                text = 'Very Weak';
                break;
            case 2:
                color = '#f59e0b';
                width = '40%';
                text = 'Weak';
                break;
            case 3:
                color = '#3b82f6';
                width = '60%';
                text = 'Good';
                break;
            case 4:
                color = '#10b981';
                width = '80%';
                text = 'Strong';
                break;
            case 5:
                color = '#059669';
                width = '100%';
                text = 'Very Strong';
                break;
        }
        
        strengthBar.style.backgroundColor = color;
        strengthBar.style.width = width;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
    
    // Password match checker
    function checkPasswordMatch() {
        const newPass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;
        const matchDiv = document.getElementById('passwordMatch');
        
        if (!newPass || !confirmPass) {
            matchDiv.classList.add('hidden');
            return;
        }
        
        if (newPass === confirmPass) {
            matchDiv.textContent = '✓ Passwords match';
            matchDiv.className = 'mt-2 text-xs text-green-600';
            matchDiv.classList.remove('hidden');
        } else {
            matchDiv.textContent = '✗ Passwords do not match';
            matchDiv.className = 'mt-2 text-xs text-red-600';
            matchDiv.classList.remove('hidden');
        }
    }
    
    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Password strength
        const newPassInput = document.getElementById('newPassword');
        if (newPassInput) {
            newPassInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
        }
        
        // Password match
        const confirmPassInput = document.getElementById('confirmPassword');
        if (confirmPassInput) {
            confirmPassInput.addEventListener('input', checkPasswordMatch);
        }
        
        // Form submission
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPass = document.getElementById('newPassword').value;
                const confirmPass = document.getElementById('confirmPassword').value;
                
                if (newPass.length < 8) {
                    e.preventDefault();
                    showToast('Password must be at least 8 characters', 'error');
                    return;
                }
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    showToast('Passwords do not match', 'error');
                    return;
                }
            });
        }
        
        // Show initial strength
        checkPasswordStrength('');
    });
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const toastId = 'toast-' + Date.now();
        
        let bgColor, textColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'bg-gradient-to-r from-green-500 to-green-600';
                textColor = 'text-white';
                icon = 'fa-check-circle';
                break;
            case 'error':
                bgColor = 'bg-gradient-to-r from-red-500 to-red-600';
                textColor = 'text-white';
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                bgColor = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
                textColor = 'text-white';
                icon = 'fa-exclamation-triangle';
                break;
            default:
                bgColor = 'bg-gradient-to-r from-blue-500 to-blue-600';
                textColor = 'text-white';
                icon = 'fa-info-circle';
        }
        
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${bgColor} ${textColor} px-6 py-4 rounded-lg shadow-xl z-50 transform translate-x-full transition-transform duration-300`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-3"></i>
                <span class="font-medium">${message}</span>
                <button onclick="document.getElementById('${toastId}').remove()" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (document.getElementById(toastId)) {
                    document.getElementById(toastId).remove();
                }
            }, 300);
        }, 5000);
    }
    </script>
</body>
</html>