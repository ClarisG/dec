<?php
// super_admin/super_admin_dashboard.php
session_start();

// Check if user is logged in and is super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

// Get super admin information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle module switching
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = [
    'dashboard', 'global_config', 'user_management', 'audit_dashboard',
    'incident_override', 'evidence_log', 'patrol_override', 'kpi_superview',
    'api_control', 'mediation_oversight', 'super_notifications', 'system_health',
    'reports_all', 'users_all', 'announcements_all', 'activity_logs', 'profile'
];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle form submissions based on module
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch($module) {
        case 'global_config':
            if (isset($_POST['save_config'])) {
                handleGlobalConfig($conn, $_POST);
            }
            break;
        case 'user_management':
            if (isset($_POST['update_user'])) {
                handleUserUpdate($conn, $_POST);
            } elseif (isset($_POST['create_user'])) {
                handleUserCreate($conn, $_POST);
            }
            break;
        case 'super_notifications':
            if (isset($_POST['send_notification'])) {
                handleSendNotification($conn, $_POST);
            }
            break;
    }
}

// Get user data including profile picture
$user_query = "SELECT u.*, 
                      IFNULL(u.barangay, 'System-wide') as barangay_display,
                      u.permanent_address as user_address,
                      u.profile_picture,
                      u.is_active,
                      (SELECT COUNT(*) FROM users) as total_users,
                      (SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'assigned', 'investigating')) as active_cases
               FROM users u 
               WHERE u.id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([':id' => $user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    $is_active = $user_data['is_active'] ?? 1;
    $profile_picture = $user_data['profile_picture'] ?? '';
    $total_users = $user_data['total_users'] ?? 0;
    $active_cases = $user_data['active_cases'] ?? 0;
} else {
    $is_active = 1;
    $profile_picture = '';
    $total_users = 0;
    $active_cases = 0;
}

// Get system health statistics with graceful error handling
$health_data = [
    'active_users' => 0,
    'weekly_reports' => 0,
    'active_apis' => 0,
    'decrypted_files' => 0,
    'hourly_activity' => 0,
    'last_activity' => null,
    'pending_reports' => 0,
    'today_patrols' => 0
];

try {
    // Tables that definitely exist (core tables)
    $core_queries = [
        "active_users" => "SELECT COUNT(*) as count FROM users WHERE is_active = 1",
        "weekly_reports" => "SELECT COUNT(*) as count FROM reports WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
        "pending_reports" => "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'",
        "hourly_activity" => "SELECT COUNT(*) as count FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        "last_activity" => "SELECT MAX(created_at) as timestamp FROM activity_logs"
    ];
    
    foreach ($core_queries as $key => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $health_data[$key] = $result['count'] ?? $result['timestamp'] ?? 0;
        } catch (PDOException $e) {
            // Log but continue
            error_log("Health query failed for $key: " . $e->getMessage());
        }
    }
    
    // Optional tables - try but don't break if missing
    $optional_tables = [
        "active_apis" => "SELECT COUNT(*) as count FROM api_integrations WHERE status = 'active'",
        "decrypted_files" => "SELECT COUNT(*) as count FROM file_encryption_logs WHERE last_decrypted IS NOT NULL",
        "today_patrols" => "SELECT COUNT(*) as count FROM patrol_logs WHERE DATE(start_time) = CURDATE()"
    ];
    
    foreach ($optional_tables as $key => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $health_data[$key] = $result['count'] ?? 0;
        } catch (PDOException $e) {
            // Set to 0 if table doesn't exist - no error logging needed
            $health_data[$key] = 0;
        }
    }
    
} catch (Exception $e) {
    // Fallback to ensure dashboard loads even with DB issues
    error_log("Health stats failed: " . $e->getMessage());
}

// Get recent system activities
$activities_query = "SELECT al.*, u.first_name, u.last_name 
                     FROM activity_logs al 
                     LEFT JOIN users u ON al.user_id = u.id 
                     ORDER BY al.created_at DESC 
                     LIMIT 10";
$activities_stmt = $conn->prepare($activities_query);
$activities_stmt->execute();
$recent_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles for user management
$roles_query = "SELECT DISTINCT role FROM users WHERE role IS NOT NULL";
$roles_stmt = $conn->prepare($roles_query);
$roles_stmt->execute();
$all_roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all barangays for filtering
$barangays_query = "SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay != ''";
$barangays_stmt = $conn->prepare($barangays_query);
$barangays_stmt->execute();
$all_barangays = $barangays_stmt->fetchAll(PDO::FETCH_COLUMN);

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Super Admin Dashboard',
        'global_config' => 'Global System Configuration',
        'user_management' => 'Universal User Management',
        'audit_dashboard' => 'Master Audit & Compliance',
        'incident_override' => 'Incident Classification Override',
        'evidence_log' => 'Evidence & Encryption Master Log',
        'patrol_override' => 'Patrol & Duty Control',
        'kpi_superview' => 'Executive KPI Superview',
        'api_control' => 'API Integration Master Control',
        'mediation_oversight' => 'Mediation & Hearing Oversight',
        'super_notifications' => 'Super Notification System',
        'system_health' => 'System Health & Monitoring',
        'reports_all' => 'All Reports',
        'users_all' => 'All Users',
        'announcements_all' => 'All Announcements',
        'activity_logs' => 'Activity Logs',
        'profile' => 'Profile Account'
    ];
    return $titles[$module] ?? 'Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'System-wide oversight with unrestricted access',
        'global_config' => 'Configure all system rules, models, and security policies',
        'user_management' => 'Create, modify, suspend, or delete any user account',
        'audit_dashboard' => 'View all cases, evidence logs, and system events',
        'incident_override' => 'Manually reclassify incidents and override AI suggestions',
        'evidence_log' => 'Access all encrypted files and manage evidence lifecycle',
        'patrol_override' => 'Assign/override Tanod schedules and patrol routes',
        'kpi_superview' => 'View and modify KPIs for all Barangay officials',
        'api_control' => 'Manage external integrations and monitor data transfers',
        'mediation_oversight' => 'View and intervene in all scheduled hearings',
        'super_notifications' => 'Send system-wide announcements and emergency alerts',
        'system_health' => 'Monitor system performance and resource usage',
        'reports_all' => 'All reports across all barangays and categories',
        'users_all' => 'All users across all roles and barangays',
        'announcements_all' => 'All system announcements and broadcasts',
        'activity_logs' => 'Complete audit trail of all user actions',
        'profile' => 'Manage your super admin account'
    ];
    return $subtitles[$module] ?? '';
}

// Handler functions
function handleGlobalConfig($conn, $data) {
    try {
        // Update configuration settings
        foreach($data as $key => $value) {
            if (strpos($key, 'config_') === 0) {
                $config_key = substr($key, 7);
                $stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value, updated_by, updated_at) 
                                       VALUES (:key, :value, :user_id, NOW()) 
                                       ON DUPLICATE KEY UPDATE config_value = :value, updated_by = :user_id, updated_at = NOW()");
                $stmt->execute([
                    ':key' => $config_key,
                    ':value' => $value,
                    ':user_id' => $_SESSION['user_id']
                ]);
            }
        }
        $_SESSION['success'] = "Configuration updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update configuration: " . $e->getMessage();
    }
}

function handleUserUpdate($conn, $data) {
    try {
        $user_id = $data['user_id'];
        $role = $data['role'];
        $status = $data['status'];
        
        $stmt = $conn->prepare("UPDATE users SET role = :role, is_active = :status WHERE id = :id");
        $stmt->execute([
            ':role' => $role,
            ':status' => $status,
            ':id' => $user_id
        ]);
        
        // Log the action
        logActivity($conn, $_SESSION['user_id'], "Updated user #$user_id (Role: $role, Status: $status)");
        
        $_SESSION['success'] = "User updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update user: " . $e->getMessage();
    }
}

function handleUserCreate($conn, $data) {
    try {
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? 'citizen';
        $barangay = $data['barangay'] ?? '';
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $check_email->execute([':email' => $email]);
        if ($check_email->fetch()) {
            $_SESSION['error'] = "Email already exists!";
            return;
        }
        
        // Generate a temporary password
        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, role, barangay, password, is_active, created_at) 
                               VALUES (:first_name, :last_name, :email, :role, :barangay, :password, 1, NOW())");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':role' => $role,
            ':barangay' => $barangay,
            ':password' => $hashed_password
        ]);
        
        $new_user_id = $conn->lastInsertId();
        
        // Log the action
        logActivity($conn, $_SESSION['user_id'], "Created user #$new_user_id ($first_name $last_name) with role $role");
        
        $_SESSION['success'] = "User created successfully! Temporary password: $temp_password";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to create user: " . $e->getMessage();
    }
}

function handleSendNotification($conn, $data) {
    try {
        $title = $data['title'];
        $message = $data['message'];
        $target_role = $data['target_role'];
        $priority = $data['priority'];
        
        $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role, priority, created_by, created_at) 
                               VALUES (:title, :message, :target_role, :priority, :user_id, NOW())");
        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':target_role' => $target_role,
            ':priority' => $priority,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        // Log the action
        logActivity($conn, $_SESSION['user_id'], "Sent notification to $target_role: $title");
        
        $_SESSION['success'] = "Notification sent successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to send notification: " . $e->getMessage();
    }
}

function logActivity($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                           VALUES (:user_id, :action, :ip, :agent, NOW())");
    $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':ip' => $_SERVER['REMOTE_ADDR'],
        ':agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - <?php echo getModuleTitle($module); ?> | LEIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../images/10213.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-blue: #e3f2fd;
            --secondary-blue: #bbdefb;
            --accent-blue: #2196f3;
            --dark-blue: #0d47a1;
            --light-blue: #f5fbff;
        }
        
        body {
            background: linear-gradient(135deg, #f5fbff 0%, #e3f2fd 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-blue);
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(33, 150, 243, 0.1);
            border-left-color: var(--dark-blue);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            border: 1px solid #e0f2fe;
        }
        
        .urgent {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
        }
        
        .warning {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }
        
        .success {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #0d47a1 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-content {
            padding-bottom: 2rem;
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #60a5fa;
        }
        
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #3b82f6;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-super {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .badge-critical {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-high {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .badge-medium {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .badge-low {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        .status-active { background-color: #10b981; }
        .status-inactive { background-color: #ef4444; }
        .status-pending { background-color: #f59e0b; }
        
        .health-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .health-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .health-excellent { background: linear-gradient(90deg, #10b981, #34d399); }
        .health-good { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .health-warning { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .health-critical { background: linear-gradient(90deg, #ef4444, #f87171); }
        
        .table-row {
            transition: background-color 0.2s ease;
        }
        
        .table-row:hover {
            background-color: #f8fafc;
        }
        
        /* Custom Scrollbar for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            
            .overlay.active {
                display: block;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 1rem !important;
            }
            
            .mobile-bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
                z-index: 100;
                padding: 8px 0;
            }
            
            .mobile-nav-item {
                flex: 1;
                text-align: center;
                padding: 8px 4px;
                color: #6b7280;
                position: relative;
            }
            
            .mobile-nav-item.active {
                color: #2196f3;
            }
            
            .mobile-nav-item.active::after {
                content: '';
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                width: 6px;
                height: 6px;
                background: #2196f3;
                border-radius: 50%;
            }
            
            .mobile-badge {
                position: absolute;
                top: 2px;
                right: 8px;
                background: #ef4444;
                color: white;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
                font-weight: 600;
            }
        }
        
        /* General Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #2196f3, #0d47a1);
            border-radius: 4px;
        }
        
        /* Loading Animation */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2196f3;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Mobile Overlay -->
    <div class="overlay md:hidden" id="mobileOverlay"></div>
    
    <!-- Desktop Sidebar -->
    <div class="sidebar w-64 fixed left-0 top-0 z-40 hidden md:block">
        <div class="sidebar-content p-6">
            <!-- LEIR Logo -->
            <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-blue-400/30">
                <div class="w-10 h-10 flex items-center justify-center">
                    <img src="../images/10213.png" alt="LEIR Logo" class="w-19 h-22 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">LEIR</h1>
                    <p class="text-blue-200 text-sm">Super Admin System</p>
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="mb-8">
                <div class="flex items-center space-x-3 p-3 bg-white/10 rounded-lg">
                    <div class="relative">
                        <?php 
                        $profile_pic_path = "../uploads/profile_pictures/" . ($profile_picture ?? '');
                        if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                        ?>
                            <img src="<?php echo $profile_pic_path; ?>" 
                                 alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-lg">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold text-lg">
                                SA
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white bg-green-500"></div>
                    </div>
                    <div>
                        <p class="text-white font-medium truncate"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="text-blue-200 text-sm">Super Administrator</p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-blue-200 flex items-center">
                        <i class="fas fa-globe mr-2 text-xs"></i>
                        <span class="truncate">System-wide Access</span>
                    </p>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="space-y-1">
                <h3 class="text-xs uppercase tracking-wider text-blue-300 mb-2">System Modules</h3>
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard Overview
                </a>
                <a href="?module=global_config" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'global_config' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs mr-3"></i>
                    System Configuration
                </a>
                <a href="?module=user_management" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'user_management' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog mr-3"></i>
                    User Management
                </a>
                <a href="?module=audit_dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'audit_dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt mr-3"></i>
                    Audit & Compliance
                </a>
                <a href="?module=incident_override" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'incident_override' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    Classification Override
                </a>
                <a href="?module=evidence_log" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'evidence_log' ? 'active' : ''; ?>">
                    <i class="fas fa-key mr-3"></i>
                    Evidence Master Log
                </a>
                
                <h3 class="text-xs uppercase tracking-wider text-blue-300 mt-4 mb-2">Control Modules</h3>
                <a href="?module=patrol_override" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'patrol_override' ? 'active' : ''; ?>">
                    <i class="fas fa-walking mr-3"></i>
                    Patrol Control
                </a>
                <a href="?module=kpi_superview" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'kpi_superview' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i>
                    KPI Superview
                </a>
                <a href="?module=api_control" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'api_control' ? 'active' : ''; ?>">
                    <i class="fas fa-plug mr-3"></i>
                    API Integration
                </a>
                <a href="?module=mediation_oversight" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'mediation_oversight' ? 'active' : ''; ?>">
                    <i class="fas fa-gavel mr-3"></i>
                    Mediation Oversight
                </a>
                
                <h3 class="text-xs uppercase tracking-wider text-blue-300 mt-4 mb-2">Communication</h3>
                <a href="?module=super_notifications" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'super_notifications' ? 'active' : ''; ?>">
                    <i class="fas fa-bell mr-3"></i>
                    Super Notifications
                </a>
                <a href="?module=announcements_all" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'announcements_all' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn mr-3"></i>
                    All Announcements
                </a>
                
                <h3 class="text-xs uppercase tracking-wider text-blue-300 mt-4 mb-2">Data Access</h3>
                <a href="?module=reports_all" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'reports_all' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt mr-3"></i>
                    All Reports
                    <span class="float-right bg-blue-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                        <?php echo $active_cases; ?>
                    </span>
                </a>
                <a href="?module=users_all" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'users_all' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends mr-3"></i>
                    All Users
                    <span class="float-right bg-blue-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                        <?php echo $total_users; ?>
                    </span>
                </a>
                <a href="?module=activity_logs" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'activity_logs' ? 'active' : ''; ?>">
                    <i class="fas fa-history mr-3"></i>
                    Activity Logs
                </a>
                
                <h3 class="text-xs uppercase tracking-wider text-blue-300 mt-4 mb-2">System</h3>
                <a href="?module=system_health" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'system_health' ? 'active' : ''; ?>">
                    <i class="fas fa-heartbeat mr-3"></i>
                    System Health
                </a>
                <a href="?module=profile" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog mr-3"></i>
                    Profile Account
                </a>
            </nav>
            
            <!-- Status & Logout -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="flex items-center justify-between p-3 bg-white/10 rounded-lg mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-server text-green-400 mr-2"></i>
                        <span class="text-white text-sm">System Status</span>
                    </div>
                    <span class="px-2 py-1 bg-green-500/30 text-green-300 text-xs rounded-full">Operational</span>
                </div>
                
                <a href="../logout.php" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
            
            <!-- Scroll indicator for long sidebars -->
            <div class="text-center mt-4 pt-4 border-t border-blue-400/20">
                <p class="text-xs text-blue-300/70">
                    <i class="fas fa-arrow-up mr-1"></i>
                    Scroll to see all modules
                    <i class="fas fa-arrow-up ml-1"></i>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content md:ml-64 flex-1 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="bg-white shadow-sm sticky top-0 z-30 mb-6 rounded-xl border border-gray-200">
            <div class="px-4 py-4">
                <div class="flex justify-between items-center">
                    <!-- Left: Mobile Menu Button and Title -->
                    <div class="flex items-center space-x-4">
                        <button id="mobileMenuButton" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                            <i class="fas fa-bars text-2xl"></i>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">
                                <?php echo getModuleTitle($module); ?>
                            </h1>
                            <p class="text-gray-600 text-sm">
                                <?php echo getModuleSubtitle($module); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Right: Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Quick Stats -->
                        <div class="hidden md:flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Active Users</p>
                                <p class="font-semibold text-blue-600"><?php echo $health_data['active_users'] ?? 0; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Weekly Reports</p>
                                <p class="font-semibold text-blue-600"><?php echo $health_data['weekly_reports'] ?? 0; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">System Health</p>
                                <div class="flex items-center">
                                    <div class="health-bar w-16 mr-2">
                                        <div class="health-fill health-excellent" style="width: 95%"></div>
                                    </div>
                                    <span class="text-sm font-semibold text-green-600">95%</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                                <?php if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                    <img src="<?php echo $profile_pic_path; ?>" 
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-blue-300 shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold">
                                        SA
                                    </div>
                                <?php endif; ?>
                                <div class="hidden md:block text-left">
                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
                                    <p class="text-xs text-gray-500">Super Admin</p>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 hidden md:block"></i>
                            </button>
                            
                            <!-- User Dropdown Menu -->
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border z-40">
                                <div class="p-4 border-b">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <?php if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                            <img src="<?php echo $profile_pic_path; ?>" 
                                                 alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                                SA
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user_name); ?></p>
                                            <p class="text-xs text-gray-500">Super Administrator</p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-id-badge mr-1"></i>
                                        ID: <?php echo $user_id; ?>
                                    </p>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user-cog mr-2"></i>
                                        Super Admin Profile
                                    </a>
                                    <a href="?module=system_health" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-cogs mr-2"></i>
                                        System Settings
                                    </a>
                                    <div class="border-t my-2"></div>
                                    <a href="../logout.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-sign-out-alt mr-2"></i>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Module Content -->
        <?php
        // Load module content based on selected module
        switch($module) {
            case 'dashboard':
                include 'modules/dashboard.php';
                break;
            case 'global_config':
                include 'modules/global_config.php';
                break;
            case 'user_management':
                include 'modules/user_management.php';
                break;
            case 'audit_dashboard':
                include 'modules/audit_dashboard.php';
                break;
            case 'incident_override':
                include 'modules/incident_override.php';
                break;
            case 'evidence_log':
                include 'modules/evidence_log.php';
                break;
            case 'patrol_override':
                include 'modules/patrol_override.php';
                break;
            case 'kpi_superview':
                include 'modules/kpi_superview.php';
                break;
            case 'api_control':
                include 'modules/api_control.php';
                break;
            case 'mediation_oversight':
                include 'modules/mediation_oversight.php';
                break;
            case 'super_notifications':
                include 'modules/super_notifications.php';
                break;
            case 'system_health':
                include 'modules/system_health.php';
                break;
            case 'reports_all':
                include 'modules/reports_all.php';
                break;
            case 'users_all':
                include 'modules/users_all.php';
                break;
            case 'activity_logs':
                include 'modules/activity_logs.php';
                break;
            case 'profile':
                include 'modules/profile.php';
                break;
            default:
                include 'modules/dashboard.php';
        }
        ?>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav md:hidden">
        <div class="flex justify-around items-center">
            <a href="?module=dashboard" class="mobile-nav-item <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt text-lg"></i>
                <span class="text-xs mt-1 block">Dashboard</span>
            </a>
            
            <a href="?module=user_management" class="mobile-nav-item <?php echo $module == 'user_management' ? 'active' : ''; ?>">
                <i class="fas fa-users text-lg"></i>
                <span class="text-xs mt-1 block">Users</span>
                <?php if ($total_users > 0): ?>
                    <span class="mobile-badge"><?php echo min($total_users, 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=reports_all" class="mobile-nav-item <?php echo $module == 'reports_all' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt text-lg"></i>
                <span class="text-xs mt-1 block">Reports</span>
                <?php if ($active_cases > 0): ?>
                    <span class="mobile-badge"><?php echo min($active_cases, 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=system_health" class="mobile-nav-item <?php echo $module == 'system_health' ? 'active' : ''; ?>">
                <i class="fas fa-heartbeat text-lg"></i>
                <span class="text-xs mt-1 block">Health</span>
            </a>
            
            <a href="?module=profile" class="mobile-nav-item <?php echo $module == 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user text-lg"></i>
                <span class="text-xs mt-1 block">Profile</span>
            </a>
        </div>
    </div>

    <!-- Quick Action Modal -->
    <div id="quickActionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Quick Action</h3>
                <button onclick="closeModal('quickActionModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        // Close mobile menu when clicking overlay
        document.getElementById('mobileOverlay').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            this.classList.remove('active');
            sidebar.classList.remove('active');
        });

        // User dropdown
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('hidden');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (userDropdown) userDropdown.classList.add('hidden');
        });

        // Modal functions
        function openModal(modalId, content = '') {
            const modal = document.getElementById(modalId);
            if (content) {
                document.getElementById('modalContent').innerHTML = content;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }
        
        // Quick action functions
        function quickCreateUser() {
            const content = `
                <form method="POST" action="">
                    <input type="hidden" name="create_user" value="1">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" required class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="">Select Role</option>
                                <option value="citizen">Citizen</option>
                                <option value="tanod">Tanod</option>
                                <option value="secretary">Secretary</option>
                                <option value="captain">Captain</option>
                                <option value="lupon">Lupon</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Barangay (Optional)</label>
                            <input type="text" name="barangay" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Leave empty for system-wide access">
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Create User
                            </button>
                        </div>
                    </div>
                </form>
            `;
            openModal('quickActionModal', content);
        }
        
        function quickSendNotification() {
            const content = `
                <form method="POST" action="">
                    <input type="hidden" name="send_notification" value="1">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea name="message" required rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target Role</label>
                            <select name="target_role" class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="all">All Users</option>
                                <option value="citizen">Citizens</option>
                                <option value="tanod">Tanods</option>
                                <option value="secretary">Secretaries</option>
                                <option value="captain">Captains</option>
                                <option value="lupon">Lupon Members</option>
                                <option value="admin">Admins</option>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Send Notification
                            </button>
                        </div>
                    </div>
                </form>
            `;
            openModal('quickActionModal', content);
        }

        // Auto-refresh system stats every 30 seconds
        if (window.location.search.includes('module=dashboard') || window.location.search.includes('module=system_health')) {
            setInterval(() => {
                // In a real implementation, you would fetch updated data via AJAX
                console.log('Refreshing system stats...');
            }, 30000);
        }

        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Initialize charts for dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any JavaScript plugins here
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('quickActionModal');
            if (event.target == modal) {
                closeModal('quickActionModal');
            }
        }
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>