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

// Get system health
$health_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM reports WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_reports,
    (SELECT COUNT(*) FROM api_integrations WHERE status = 'active') as active_apis,
    (SELECT COUNT(*) FROM file_encryption_logs WHERE last_decrypted IS NOT NULL) as decrypted_files,
    (SELECT COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as hourly_activity,
    (SELECT MAX(created_at) FROM activity_logs) as last_activity";
$health_stmt = $conn->prepare($health_query);
$health_stmt->execute();
$health_data = $health_stmt->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - <?php echo getModuleTitle($module); ?> LEIR | Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../images/10213.png">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-purple: #7e22ce;
            --secondary-purple: #9333ea;
            --accent-purple: #a855f7;
            --dark-purple: #581c87;
            --light-purple: #faf5ff;
        }
        
        body {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-purple);
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(126, 34, 206, 0.15);
        }
        
        .super-stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f0ff 100%);
            border: 1px solid #e9d5ff;
            position: relative;
            overflow: hidden;
        }
        
        .super-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #7e22ce, #9333ea);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e1b4b 0%, #3730a3 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #a855f7;
        }
        
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #8b5cf6;
            font-weight: 600;
        }
        
        .sidebar-link.active::after {
            content: '';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: #8b5cf6;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-super {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
                color: #7e22ce;
            }
            
            .mobile-nav-item.active::after {
                content: '';
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                width: 6px;
                height: 6px;
                background: #7e22ce;
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
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #7e22ce, #9333ea);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #6b21a8, #7c3aed);
        }
        
        /* Loading Animation */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #7e22ce;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Mobile Overlay -->
    <div class="overlay md:hidden" id="mobileOverlay"></div>
    
    <!-- Desktop Sidebar -->
    <div class="sidebar w-64 min-h-screen fixed left-0 top-0 z-40 hidden md:block">
        <div class="p-6">
            <!-- LEIR Logo -->
            <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-purple-400/30">
                <div class="w-10 h-10 flex items-center justify-center">
                    <img src="../images/10213.png" alt="LEIR Logo" class="w-19 h-22 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">LEIR</h1>
                    <p class="text-purple-200 text-sm">Super Admin System</p>
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
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 flex items-center justify-center text-white font-bold text-lg">
                                SA
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white bg-green-500"></div>
                    </div>
                    <div>
                        <p class="text-white font-medium truncate"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="text-purple-200 text-sm">Super Administrator</p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-purple-200 flex items-center">
                        <i class="fas fa-globe mr-2 text-xs"></i>
                        <span class="truncate">System-wide Access</span>
                    </p>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="space-y-1">
                <h3 class="text-xs uppercase tracking-wider text-purple-300 mb-2">System Modules</h3>
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
                
                <h3 class="text-xs uppercase tracking-wider text-purple-300 mt-4 mb-2">Control Modules</h3>
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
                
                <h3 class="text-xs uppercase tracking-wider text-purple-300 mt-4 mb-2">Data Access</h3>
                <a href="?module=reports_all" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'reports_all' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt mr-3"></i>
                    All Reports
                    <span class="float-right bg-purple-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                        <?php echo $active_cases; ?>
                    </span>
                </a>
                <a href="?module=users_all" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'users_all' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends mr-3"></i>
                    All Users
                    <span class="float-right bg-purple-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                        <?php echo $total_users; ?>
                    </span>
                </a>
                <a href="?module=activity_logs" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'activity_logs' ? 'active' : ''; ?>">
                    <i class="fas fa-history mr-3"></i>
                    Activity Logs
                </a>
                <a href="?module=system_health" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'system_health' ? 'active' : ''; ?>">
                    <i class="fas fa-heartbeat mr-3"></i>
                    System Health
                </a>
            </nav>
            
            <!-- Status & Logout -->
            <div class="mt-8 pt-8 border-t border-purple-400/30">
                <div class="flex items-center justify-between p-3 bg-white/10 rounded-lg mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-server text-green-400 mr-2"></i>
                        <span class="text-white text-sm">System Status</span>
                    </div>
                    <span class="px-2 py-1 bg-green-500/30 text-green-300 text-xs rounded-full">Operational</span>
                </div>
                
                <a href="../logout.php" class="flex items-center p-3 text-purple-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
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
                        <button id="mobileMenuButton" class="md:hidden text-gray-600 hover:text-purple-600 focus:outline-none">
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
                                <p class="font-semibold text-purple-600"><?php echo $health_data['active_users'] ?? 0; ?></p>
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
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-purple-300 shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 flex items-center justify-center text-white font-semibold">
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
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 flex items-center justify-center text-white font-bold">
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

        <!-- Module Content -->
        <?php
        $module_file = "super_admin/modules/{$module}.php";
        if (file_exists($module_file)) {
            include $module_file;
        } else {
            // Fallback to dashboard if module file doesn't exist
            include "super_admin/modules/dashboard.php";
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

        // Auto-refresh system stats every 30 seconds
        if (window.location.search.includes('module=dashboard') || window.location.search.includes('module=system_health')) {
            setInterval(() => {
                // You can implement AJAX refresh here
                console.log('Refreshing system stats...');
            }, 30000);
        }

        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Initialize tooltips (if using any tooltip library)
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any JavaScript plugins here
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>