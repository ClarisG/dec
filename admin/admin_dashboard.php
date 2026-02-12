<?php
// admin/admin_dashboard.php - ADMIN DASHBOARD WITH SYSTEM CONTROL MODULES
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

// Get admin information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle module switching - ONLY 6 MODULES + Dashboard
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = [
    'dashboard', 'classification', 'case_dashboard', 'tanod_tracker', 
    'patrol_scheduling', 'report_management', 'evidence_tracking', 'profile'
];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle form submissions for each module
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch($module) {
        case 'classification':
            if (isset($_POST['update_keywords'])) {
                $type_id = $_POST['report_type_id'];
                $keywords = $_POST['keywords'];
                $jurisdiction = $_POST['jurisdiction'];
                $threshold = $_POST['threshold'];
                
                $stmt = $conn->prepare("UPDATE report_types SET keywords = :keywords, jurisdiction = :jurisdiction WHERE id = :id");
                $stmt->execute([':keywords' => $keywords, ':jurisdiction' => $jurisdiction, ':id' => $type_id]);
                
                // Update threshold in configuration
                $stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value, updated_by) 
                                        VALUES (:key, :value, :user_id) 
                                        ON DUPLICATE KEY UPDATE config_value = :value, updated_by = :user_id, updated_at = NOW()");
                $stmt->execute([':key' => 'classification_threshold', ':value' => $threshold, ':user_id' => $user_id]);
                
                $_SESSION['success'] = "Classification settings updated successfully!";
                header("Location: admin_dashboard.php?module=classification");
                exit;
            }
            break;
            
        case 'patrol_scheduling':
            if (isset($_POST['assign_patrol'])) {
                $tanod_id = $_POST['tanod_id'];
                $schedule_date = $_POST['schedule_date'];
                $shift_start = $_POST['shift_start'];
                $shift_end = $_POST['shift_end'];
                $patrol_route = $_POST['patrol_route'];
                
                $stmt = $conn->prepare("INSERT INTO tanod_schedules (user_id, schedule_date, shift_start, shift_end, patrol_route, assigned_by) 
                                        VALUES (:user_id, :schedule_date, :shift_start, :shift_end, :patrol_route, :assigned_by)");
                $stmt->execute([
                    ':user_id' => $tanod_id,
                    ':schedule_date' => $schedule_date,
                    ':shift_start' => $shift_start,
                    ':shift_end' => $shift_end,
                    ':patrol_route' => $patrol_route,
                    ':assigned_by' => $user_id
                ]);
                
                $_SESSION['success'] = "Patrol schedule assigned successfully!";
                header("Location: admin_dashboard.php?module=patrol_scheduling");
                exit;
            }
            break;
    }
}

// Get user data including profile picture
$user_query = "SELECT u.*, 
                      IFNULL(u.barangay, 'Not specified') as barangay_display,
                      u.permanent_address as user_address,
                      u.profile_picture,
                      u.is_active
               FROM users u 
               WHERE u.id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([':id' => $user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    $is_active = $user_data['is_active'] ?? 1;
    $_SESSION['permanent_address'] = $user_data['user_address'];
    $_SESSION['barangay'] = $user_data['barangay_display'];
    $user_address = $user_data['user_address'];
    $profile_picture = $user_data['profile_picture'];
} else {
    $is_active = 1;
    $user_address = '';
    $profile_picture = '';
}

// Get statistics for dashboard
$stats = [];
if ($module == 'dashboard') {
    // Total reports
    $total_reports_query = "SELECT COUNT(*) as count FROM reports";
    $total_reports_stmt = $conn->prepare($total_reports_query);
    $total_reports_stmt->execute();
    $stats['total_reports'] = $total_reports_stmt->fetchColumn();
    
    // Pending verification
    $pending_verification_query = "SELECT COUNT(*) as count FROM reports WHERE needs_verification = 1";
    $pending_verification_stmt = $conn->prepare($pending_verification_query);
    $pending_verification_stmt->execute();
    $stats['pending_verification'] = $pending_verification_stmt->fetchColumn();
    
    // Active Tanods
    $active_tanods_query = "SELECT COUNT(*) as count FROM users WHERE role = 'tanod' AND is_active = 1";
    $active_tanods_stmt = $conn->prepare($active_tanods_query);
    $active_tanods_stmt->execute();
    $stats['active_tanods'] = $active_tanods_stmt->fetchColumn();
    
    // System health
    $system_health_query = "SELECT 
        (SELECT COUNT(*) FROM reports WHERE DATE(created_at) = CURDATE()) as today_reports,
        (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as today_logs,
        (SELECT COUNT(*) FROM file_encryption_logs WHERE decryption_count > 0) as total_decryptions";
    $system_health_stmt = $conn->prepare($system_health_query);
    $system_health_stmt->execute();
    $system_health = $system_health_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity logs
    $activity_query = "SELECT al.*, u.first_name, u.last_name 
                      FROM activity_logs al 
                      LEFT JOIN users u ON al.user_id = u.id 
                      ORDER BY al.created_at DESC 
                      LIMIT 10";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->execute();
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Dashboard Overview',
        'classification' => 'Incident Classification',
        'case_dashboard' => 'Case Status Dashboard',
        'tanod_tracker' => 'Tanod Assignment Tracker',
        'patrol_scheduling' => 'Patrol Scheduling',
        'report_management' => 'Report Management',
        'evidence_tracking' => 'Evidence Tracking',
        'profile' => 'Profile Settings'

    ];
    return $titles[$module] ?? 'Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'System Controller and Master Auditor Dashboard',
        'classification' => 'Configure incident types and keywords',
        'case_dashboard' => 'Unified case view with advanced audit filters',
        'tanod_tracker' => 'Real-time GPS tracking and assignment management',
        'patrol_scheduling' => 'Schedule and deploy Tanod patrol routes',
        'report_management' => 'Central hub for citizen report routing and verification',
        'evidence_tracking' => 'Master inventory and chain of custody management',
         'profile' => 'Manage your administrative account information and security'

    ];
    return $subtitles[$module] ?? '';
}

// Define the 6 main modules for navigation in the requested order
$main_modules = [
    ['id' => 'classification', 'name' => 'Incident Classification', 'icon' => 'fa-robot', 'color' => 'module-1'],
    ['id' => 'case_dashboard', 'name' => 'Case Status Dashboard', 'icon' => 'fa-gavel', 'color' => 'module-2'],
    ['id' => 'tanod_tracker', 'name' => 'Tanod Tracker', 'icon' => 'fa-map-marker-alt', 'color' => 'module-3'],
    ['id' => 'patrol_scheduling', 'name' => 'Patrol Scheduling', 'icon' => 'fa-route', 'color' => 'module-4'],
    ['id' => 'report_management', 'name' => 'Report Management', 'icon' => 'fa-inbox', 'color' => 'module-5'],
    ['id' => 'evidence_tracking', 'name' => 'Evidence Tracking', 'icon' => 'fa-boxes', 'color' => 'module-6'],
    ['id' => 'profile', 'name' => 'Profile Settings', 'icon' => 'fa-user-cog', 'color' => 'module-blue'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo getModuleTitle($module); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../images/10213.png">
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
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
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
        .module-blue { 
            background: linear-gradient(135deg, #2196f3, #0d47a1); 
            color: white; 
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
        
        .info {
            border-left: 4px solid #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #0d47a1 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
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
        
        .case-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .case-table tr:hover {
            background-color: #f8fafc;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-admin {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-system {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-security {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-data {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-integration {
            background-color: #e0f2fe;
            color: #0c4a6e;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f3f4f6; color: #6b7280; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-success { background: #dcfce7; color: #166534; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
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
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                z-index: 100;
            }
            
            .mobile-nav-active {
                color: #2196f3;
                position: relative;
            }
            
            .mobile-nav-active::after {
                content: '';
                position: absolute;
                bottom: -5px;
                left: 50%;
                transform: translateX(-50%);
                width: 6px;
                height: 6px;
                background: #2196f3;
                border-radius: 50%;
            }
        }
        
        /* Card animations */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Module icons */
        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .module-1 { background: linear-gradient(135deg, #2196f3, #0d47a1); color: white; }
        .module-2 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        .module-3 { background: linear-gradient(135deg, #10b981, #047857); color: white; }
        .module-4 { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .module-5 { background: linear-gradient(135deg, #2196f3, #0d47a1); color: white; }
        .module-6 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        
        /* Active status animation */
        .active-status {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Progress bar */
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #2196f3, #0d47a1);
        }
        
        /* Map container */
        #tanodMap {
            height: 500px;
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* Configuration cards */
        .config-card {
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            border-color: #2196f3;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.1);
        }
        
        /* System health indicators */
        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .health-green { background-color: #10b981; }
        .health-yellow { background-color: #f59e0b; }
        .health-red { background-color: #ef4444; }
        
        /* Keyword tags */
        .keyword-tag {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin: 2px;
        }
        
        /* Scrollable sidebar - FIXED VERSION (keeping the same as before) */
        .sidebar-scroll {
            height: calc(100vh - 180px);
            overflow-y: auto;
            flex: 1;
        }
        
        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        /* Improved sidebar layout */
        .sidebar-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .sidebar-top {
            flex-shrink: 0;
        }
        
        .sidebar-bottom {
            flex-shrink: 0;
        }
        
        /* Ensure all 6 modules are visible */
        .module-navigation {
            min-height: 300px;
        }
    </style>
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body class="min-h-screen">
    <!-- Mobile Overlay -->
    <div class="overlay md:hidden" id="mobileOverlay"></div>
    
    <!-- Desktop Sidebar -->
    <div class="sidebar w-64 min-h-screen fixed left-0 top-0 z-40 hidden md:block">
        <div class="sidebar-container">
            <div class="sidebar-top p-6">
                <!-- LEIR Logo with white shadow -->
                <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-blue-400/30">
                    <div class="w-10 h-10 flex items-center justify-center">
                        <img src="../images/10213.png" alt="Logo" class="w-19 h-22 object-contain drop-shadow-[0_0_15px_rgba(255,255,255,0.9)] drop-shadow-[0_0_30px_rgba(255,255,255,0.7)] drop-shadow-[0_0_60px_rgba(255,255,255,0.5)] transition-filter duration-300">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">LEIR</h1>
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
                                     alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                        </div>
                        <div>
                            <p class="text-white font-medium truncate"><?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-blue-200 text-sm">System Administrator</p>
                        </div>
                    </div>
                    <div class="mt-3 ml-3">
                        <p class="text-sm text-blue-200 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-xs"></i>
                            <span class="truncate">Master Auditor Access</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Scrollable Navigation Area - FIXED -->
            <div class="sidebar-scroll px-6 pb-4 module-navigation">
                <!-- Main Navigation -->
                <div class="mb-6">
                    <p class="text-xs text-blue-300 uppercase tracking-wider mb-3">MAIN</p>
                    <nav class="space-y-2 mb-8">
                        <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard Overview
                        </a>
                        
                    </nav>
                    
                    <p class="text-xs text-blue-300 uppercase tracking-wider mb-3">SYSTEM MODULES</p>
                    <nav class="space-y-2">
                        <!-- Reordered modules as requested -->
                        <a href="?module=classification" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'classification' ? 'active' : ''; ?>">
                            <i class="fas fa-robot mr-3"></i>
                            Incident Classification
                        </a>
                        
                        <a href="?module=case_dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'case_dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-gavel mr-3"></i>
                            Case Status Dashboard
                        </a>
                        
                        <a href="?module=tanod_tracker" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'tanod_tracker' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt mr-3"></i>
                            Tanod Assignment Tracker
                        </a>
                        
                        <a href="?module=patrol_scheduling" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'patrol_scheduling' ? 'active' : ''; ?>">
                            <i class="fas fa-route mr-3"></i>
                            Patrol Scheduling
                        </a>
                        
                        <a href="?module=report_management" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'report_management' ? 'active' : ''; ?>">
                            <i class="fas fa-inbox mr-3"></i>
                            Report Management
                        </a>
                        
                        <a href="?module=evidence_tracking" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'evidence_tracking' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes mr-3"></i>
                            Evidence Tracking
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content md:ml-64 flex-1 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="bg-white shadow-sm sticky top-0 z-30 mb-6">
            <div class="px-4 py-4">
                <div class="flex justify-between items-center">
                    <!-- Left: Mobile Menu Button and Title -->
                    <div class="flex items-center space-x-4">
                        <button id="mobileMenuButton" class="md:hidden text-gray-600 hover:text-gray-900 focus:outline-none">
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
                    
                    <!-- Right: Notifications and User -->
                    <div class="flex items-center space-x-4">
                        <!-- System Status -->
                        <div class="hidden md:flex items-center space-x-2 px-3 py-1 bg-green-50 text-green-700 rounded-full">
                            <i class="fas fa-server text-sm"></i>
                            <span class="text-sm font-medium">System Healthy</span>
                        </div>
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button onclick="toggleNotifications()" class="relative">
                                <i class="fas fa-bell text-gray-600 text-xl cursor-pointer hover:text-blue-600"></i>
                                <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                            </button>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                                <?php 
                                $profile_pic_path = "../uploads/profile_pictures/" . ($profile_picture ?? '');
                                if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                                ?>
                                    <img src="<?php echo $profile_pic_path; ?>" 
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden md:block text-left">
                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
                                    <p class="text-xs text-gray-500">System Admin</p>
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
                                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user_name); ?></p>
                                            <p class="text-xs text-gray-500">System Administrator</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="modules/profile.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas  fa-user-edit mr-2"></i>
                                        Profile Settings
                                    </a>
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



        <!-- Load Module Content -->
        <?php
        // Define module file path
        $module_file = "modules/{$module}.php";
        
        // Check if module file exists and include it
        if (file_exists($module_file)) {
            include $module_file;
        } else {
            // Fallback to module content based on module name
            switch($module) {
                case 'dashboard':
                    include 'modules/dashboard.php';
                    break;
                case 'classification':
                    include 'modules/classification.php';
                    break;
                case 'case_dashboard':
                    include 'modules/case_dashboard.php';
                    break;
                case 'tanod_tracker':
                    include 'modules/tanod_tracker.php';
                    break;
                case 'patrol_scheduling':
                    include 'modules/patrol_scheduling.php';
                    break;
                case 'report_management':
                    include 'modules/report_management.php';
                    break;
                case 'evidence_tracking':
                    include 'modules/evidence_tracking.php';
                    break;
                default:
                    echo "<div class='bg-white rounded-xl p-6'>";
                    echo "<h2 class='text-xl font-bold text-gray-800 mb-4'>Module Under Development</h2>";
                    echo "<p class='text-gray-600'>This module is currently being developed.</p>";
                    echo "</div>";
            }
        }
        ?>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav md:hidden">
        <div class="flex justify-around items-center py-3">
            <a href="?module=dashboard" class="flex flex-col items-center text-gray-600 <?php echo $module == 'dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            
            <a href="?module=tanod_tracker" class="flex flex-col items-center text-gray-600 <?php echo $module == 'tanod_tracker' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-map-marker-alt text-xl"></i>
                <span class="text-xs mt-1">Tracker</span>
            </a>
            
            <a href="?module=classification" class="flex flex-col items-center text-gray-600 <?php echo $module == 'classification' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-robot text-xl"></i>
                <span class="text-xs mt-1">AI Config</span>
            </a>
            
            <a href="?module=case_dashboard" class="flex flex-col items-center text-gray-600 <?php echo $module == 'case_dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-gavel text-xl"></i>
                <span class="text-xs mt-1">Cases</span>
            </a>
            
            <a href="?module=report_management" class="flex flex-col items-center text-gray-600 <?php echo $module == 'report_management' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-inbox text-xl"></i>
                <span class="text-xs mt-1">Reports</span>
            </a>
        </div>
    </div>

    <!-- Notifications Panel -->
    <div id="notificationsPanel" class="hidden fixed right-4 top-20 w-80 bg-white rounded-lg shadow-xl border z-50">
        <div class="p-4 border-b">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-gray-800">System Notifications</h3>
                <button onclick="toggleNotifications()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <div class="p-4 border-b hover:bg-gray-50 cursor-pointer">
                <div class="flex items-start">
                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-database text-blue-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">Database Backup Completed</p>
                        <p class="text-xs text-gray-600">Nightly backup completed successfully</p>
                        <p class="text-xs text-gray-500 mt-1">5 minutes ago</p>
                    </div>
                </div>
            </div>
            <div class="p-4 border-b hover:bg-gray-50 cursor-pointer">
                <div class="flex items-start">
                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-shield-alt text-green-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">Security Scan Clean</p>
                        <p class="text-xs text-gray-600">No security vulnerabilities detected</p>
                        <p class="text-xs text-gray-500 mt-1">1 hour ago</p>
                    </div>
                </div>
            </div>
            <div class="p-4 border-b hover:bg-gray-50 cursor-pointer">
                <div class="flex items-start">
                    <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">High Server Load</p>
                        <p class="text-xs text-gray-600">CPU usage at 85% - Monitor required</p>
                        <p class="text-xs text-gray-500 mt-1">2 hours ago</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 text-center border-t">
            <a href="?module=report_management" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                View All Reports
            </a>
        </div>
    </div>

    <!-- Leaflet JS for maps -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            if (document.getElementById('notificationsPanel')) {
                document.getElementById('notificationsPanel').classList.add('hidden');
            }
        });

        // Notifications panel
        function toggleNotifications() {
            const panel = document.getElementById('notificationsPanel');
            panel.classList.toggle('hidden');
        }

        // Initialize map for Tanod Tracker
        function initTanodMap() {
            const mapElement = document.getElementById('tanodMap');
            if (mapElement) {
                const map = L.map('tanodMap').setView([14.5995, 120.9842], 13); // Manila coordinates
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Add sample markers for Tanods (in real system, fetch from database)
                const tanodLocations = [
                    { name: "Tanod Jeff", lat: 14.5995, lng: 120.9842, status: "active" },
                    { name: "Tanod Abo", lat: 14.6000, lng: 120.9900, status: "on-patrol" },
                    { name: "Tanod Isagani", lat: 14.5980, lng: 120.9800, status: "available" }
                ];
                
                tanodLocations.forEach(tanod => {
                    const iconColor = tanod.status === 'active' ? 'blue' : 
                                     tanod.status === 'on-patrol' ? 'green' : 'gray';
                    
                    const icon = L.divIcon({
                        html: `<div class="bg-${iconColor}-500 w-6 h-6 rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                                 <i class="fas fa-user text-white text-xs"></i>
                               </div>`,
                        className: 'custom-div-icon',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    
                    L.marker([tanod.lat, tanod.lng], { icon: icon })
                        .addTo(map)
                        .bindPopup(`<strong>${tanod.name}</strong><br>Status: ${tanod.status}`);
                });
                
                // Add patrol routes
                const patrolRoute = [
                    [14.5995, 120.9842],
                    [14.6000, 120.9900],
                    [14.5980, 120.9800],
                    [14.5995, 120.9842]
                ];
                
                L.polyline(patrolRoute, {
                    color: '#2196f3',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '5, 10'
                }).addTo(map);
            }
        }

        // Auto-refresh for tracker module
        if (window.location.search.includes('module=tanod_tracker')) {
            // Initialize map when module loads
            setTimeout(initTanodMap, 100);
            
            // Refresh every 30 seconds
            setInterval(() => {
                // In real implementation, this would fetch updated GPS data
                console.log('Refreshing Tanod locations...');
            }, 30000);
        }

        // Auto-refresh for dashboard
        if (window.location.search.includes('module=dashboard')) {
            setInterval(() => {
                // Refresh dashboard stats
                fetch('handlers/get_system_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update stats on the page
                        document.querySelectorAll('.stat-value').forEach(el => {
                            const statName = el.dataset.stat;
                            if (data[statName]) {
                                el.textContent = data[statName];
                            }
                        });
                    });
            }, 60000); // 60 seconds
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
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Real-time updates for classification module
        if (window.location.search.includes('module=classification')) {
            // Load classification rules
            loadClassificationRules();
            
            function loadClassificationRules() {
                fetch('handlers/get_classification_rules.php')
                    .then(response => response.json())
                    .then(rules => {
                        // Populate the rules table
                        const tableBody = document.getElementById('rulesTableBody');
                        if (tableBody && rules.length > 0) {
                            tableBody.innerHTML = rules.map(rule => `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        ${rule.type_name}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${rule.category}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        ${rule.keywords ? rule.keywords.split(',').map(k => `<span class="keyword-tag">${k.trim()}</span>`).join('') : 'None'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="status-badge status-${rule.jurisdiction === 'police' ? 'warning' : 'success'}">
                                            ${rule.jurisdiction}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="editRule(${rule.id})" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteRule(${rule.id})" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `).join('');
                        }
                    });
            }
            
            window.editRule = function(id) {
                // Open edit modal
                console.log('Edit rule:', id);
            };
            
            window.deleteRule = function(id) {
                if (confirm('Are you sure you want to delete this classification rule?')) {
                    fetch('handlers/delete_classification_rule.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadClassificationRules();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                }
            };
        }
        
        // Real-time updates for evidence tracking
        if (window.location.search.includes('module=evidence_tracking')) {
            setInterval(() => {
                fetch('handlers/get_evidence_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update evidence counters
                        if (data.total_items) {
                            document.getElementById('totalEvidenceItems').textContent = data.total_items;
                        }
                        if (data.pending_handovers) {
                            document.getElementById('pendingHandovers').textContent = data.pending_handovers;
                        }
                    });
            }, 30000); // 30 seconds
        }
        
        // System health monitoring
        setInterval(() => {
            fetch('handlers/get_system_health.php')
                .then(response => response.json())
                .then(health => {
                    const statusElement = document.querySelector('.health-indicator');
                    if (statusElement && health.status) {
                        statusElement.className = `health-indicator health-${health.status}`;
                        if (health.status === 'red') {
                            // Show alert for critical system status
                            showSystemAlert('System Warning', health.message || 'System experiencing issues');
                        }
                    }
                });
        }, 120000); // 2 minutes
        
        function showSystemAlert(title, message) {
            // Create alert if not already shown
            if (!document.getElementById('systemAlert')) {
                const alertDiv = document.createElement('div');
                alertDiv.id = 'systemAlert';
                alertDiv.className = 'fixed top-4 right-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg z-50 max-w-md';
                alertDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">${title}</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p>${message}</p>
                            </div>
                        </div>
                        <button onclick="this.parentElement.remove()" class="ml-auto pl-3">
                            <i class="fas fa-times text-red-400 hover:text-red-600"></i>
                        </button>
                    </div>
                `;
                document.body.appendChild(alertDiv);
                
                // Auto-remove after 10 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 10000);
            }
        }
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>