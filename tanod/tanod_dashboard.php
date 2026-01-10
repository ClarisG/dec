<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../login.php');
    exit();
}

// Get tanod data
$tanod_id = $_SESSION['user_id'];

// Check if database connection is available
if (!isset($pdo)) {
    // Try to get connection using function
    try {
        $pdo = getDbConnection();
    } catch (Exception $e) {
        die('Database connection not established. Please check your database configuration.');
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$tanod_id]);
$tanod = $stmt->fetch();

// Get duty status
$stmt = $pdo->prepare("SELECT status FROM tanod_status WHERE user_id = ?");
$stmt->execute([$tanod_id]);
$duty_status = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user data including profile picture
$user_query = "SELECT u.*, 
                      IFNULL(u.barangay, 'Not specified') as barangay_display,
                      u.permanent_address as user_address,
                      u.profile_picture,
                      u.is_active
               FROM users u 
               WHERE u.id = :id";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([':id' => $tanod_id]);
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

// Handle module switching
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = ['dashboard', 'duty_schedule', 'incident_logging', 'report_vetting', 'evidence_handover', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Get statistics for dashboard
$stats = [];
if ($module == 'dashboard') {
    // Pending incidents - FIXED: Changed table from 'incidents' to 'tanod_incidents'
    $pending_query = "SELECT COUNT(*) as count FROM tanod_incidents WHERE status = 'pending' AND user_id = ?";
    $pending_stmt = $pdo->prepare($pending_query);
    $pending_stmt->execute([$tanod_id]);
    $stats['pending_incidents'] = $pending_stmt->fetchColumn();
    
    // Reports for vetting
    $vetting_query = "SELECT COUNT(*) as count FROM reports WHERE status = 'pending_vetting'";
    $vetting_stmt = $pdo->prepare($vetting_query);
    $vetting_stmt->execute();
    $stats['pending_vetting'] = $vetting_stmt->fetchColumn();
    
    // Total incidents logged - FIXED: Changed table from 'incidents' to 'tanod_incidents'
    $total_query = "SELECT COUNT(*) as count FROM tanod_incidents WHERE user_id = ?";
    $total_stmt = $pdo->prepare($total_query);
    $total_stmt->execute([$tanod_id]);
    $stats['total_incidents'] = $total_stmt->fetchColumn();
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Dashboard Overview',
        'duty_schedule' => 'Duty & Patrol Schedule',
        'incident_logging' => 'Incident Logging',
        'report_vetting' => 'Report Vetting',
        'evidence_handover' => 'Evidence Handover',
        'profile' => 'Profile Account'
    ];
    return $titles[$module] ?? 'Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'Overview of all tanod functions and quick actions',
        'duty_schedule' => 'View assigned shifts, patrol routes, and clock in/out',
        'incident_logging' => 'Log incidents with GPS location and evidence upload',
        'report_vetting' => 'Review citizen reports for field verification',
        'evidence_handover' => 'Track evidence transfer and chain of custody',
        'profile' => 'Manage your account information and settings'
    ];
    return $subtitles[$module] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanod Dashboard - <?php echo getModuleTitle($module); ?></title>
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
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-processing {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-resolved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-on-duty {
            background-color: #10b981;
            color: white;
        }
        
        .badge-off-duty {
            background-color: #6b7280;
            color: white;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
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
            
            .mobile-nav-badge {
                position: absolute;
                top: -5px;
                right: 5px;
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
        
        /* Card animations */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Active status animation */
        .active-status {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
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
            <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-blue-400/30">
                <div class="w-10 h-10 flex items-center justify-center">
                    <img src="../images/10213.png" alt="Logo" class="w-19 h-22 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">LEIR</h1>
                    <p class="text-blue-200 text-sm">Tanod System</p>
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="mb-8">
                <div class="flex items-center space-x-3 p-3 bg-white/10 rounded-lg">
                    <div class="relative">
                        <?php 
                        // FIXED: Changed path from ../uploads to ../../uploads
                        $profile_pic_path = "../../uploads/profile_pictures/" . ($profile_picture ?? '');
                        if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                        ?>
                            <img src="<?php echo $profile_pic_path; ?>" 
                                 alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                    </div>
                    <div>
                        <p class="text-white font-medium truncate"><?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?></p>
                        <p class="text-blue-200 text-sm">Barangay Tanod</p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-blue-200 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-xs"></i>
                        <span class="truncate"><?php echo htmlspecialchars($user_address ?? 'Barangay Office'); ?></span>
                    </p>
                </div>
            </div>
            
            <nav class="space-y-2">
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard Overview
                </a>
                <a href="?module=duty_schedule" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'duty_schedule' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Duty & Patrol Schedule
                </a>
                <a href="?module=incident_logging" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'incident_logging' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    Incident Logging
                    <?php if (isset($stats['pending_incidents']) && $stats['pending_incidents'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['pending_incidents'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=report_vetting" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'report_vetting' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle mr-3"></i>
                    Report Vetting
                    <?php if (isset($stats['pending_vetting']) && $stats['pending_vetting'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['pending_vetting'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=evidence_handover" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'evidence_handover' ? 'active' : ''; ?>">
                    <i class="fas fa-box mr-3"></i>
                    Evidence Handover
                </a>
            </nav>
            
            <!-- Duty Status -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-blue-200 mb-3">CURRENT DUTY STATUS</h3>
                    <div class="<?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'bg-green-500/20 border-green-500' : 'bg-gray-500/20 border-gray-500'; ?> border rounded-xl p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-white font-medium">Status</span>
                            <span class="<?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'text-green-300' : 'text-gray-300'; ?>">
                                <i class="fas fa-circle text-xs mr-1"></i>
                                <?php echo ($duty_status && $duty_status['status']) ? htmlspecialchars($duty_status['status']) : 'Off-Duty'; ?>
                            </span>
                        </div>
                        <button onclick="toggleDuty()" 
                                class="w-full mt-3 py-2 rounded-lg <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white font-medium transition-colors">
                            <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'Clock Out' : 'Clock In'; ?>
                        </button>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <a href="?module=profile" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                        <i class="fas fa-user-cog mr-3"></i>
                        Profile Settings
                    </a>
                    <a href="../logout.php" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
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
                        <!-- Notifications -->
                        <div class="relative">
                            <i class="fas fa-bell text-gray-600 text-xl cursor-pointer hover:text-blue-600"></i>
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                                <?php 
                                // FIXED: Changed path from ../uploads to ../../uploads
                                $profile_pic_path = "../../uploads/profile_pictures/" . ($profile_picture ?? '');
                                if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                                ?>
                                    <img src="<?php echo $profile_pic_path; ?>" 
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="hidden md:block text-left">
                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($tanod['first_name']); ?></p>
                                    <p class="text-xs text-gray-500">Barangay Tanod</p>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 hidden md:block"></i>
                            </button>
                            
                            <!-- User Dropdown Menu -->
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border z-40">
                                <div class="p-4 border-b">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <?php 
                                        $profile_pic_path = "../../uploads/profile_pictures/" . ($profile_picture ?? '');
                                        if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                            <img src="<?php echo $profile_pic_path; ?>" 
                                                 alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                                <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?></p>
                                            <p class="text-xs text-gray-500">Barangay Tanod</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? '<span class="text-green-600 font-medium">On Duty</span>' : '<span class="text-gray-600">Off Duty</span>'; ?>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user-cog mr-2"></i>
                                        Profile Settings
                                    </a>
                                    <button onclick="toggleDuty()" class="w-full text-left flex items-center px-3 py-2 text-sm <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'text-red-600' : 'text-green-600'; ?> hover:bg-gray-100 rounded">
                                        <i class="fas fa-power-off mr-2"></i>
                                        <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'Clock Out' : 'Clock In'; ?>
                                    </button>
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
        if ($module == 'dashboard') {
            // Dashboard content
            ?>
            <div class="mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Welcome, Tanod <?php echo htmlspecialchars($tanod['first_name']); ?>!</h1>
                        <p class="text-gray-600 mt-1"><?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'You are currently on duty. Stay vigilant!' : 'You are currently off duty.'; ?></p>
                    </div>
                    <button onclick="toggleDuty()" 
                            class="px-6 py-3 rounded-lg font-medium <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white transition-colors">
                        <i class="fas fa-power-off mr-2"></i>
                        <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'Clock Out' : 'Clock In'; ?>
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?php echo $stats['total_incidents'] ?? 0; ?></span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Incidents Logged</h3>
                        <p class="text-gray-600 text-sm">Total incidents you have documented</p>
                    </div>

                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="h-12 w-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_incidents'] ?? 0; ?></span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Pending Incidents</h3>
                        <p class="text-gray-600 text-sm">Incidents requiring follow-up</p>
                    </div>

                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_vetting'] ?? 0; ?></span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Reports for Vetting</h3>
                        <p class="text-gray-600 text-sm">Citizen reports to review</p>
                    </div>

                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <span class="text-2xl font-bold text-gray-800">
                                    <?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'ON' : 'OFF'; ?>
                                </span>
                                <span class="text-sm text-gray-500 ml-1">DUTY</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Current Status</h3>
                        <p class="text-gray-600 text-sm"><?php echo ($duty_status && $duty_status['status'] == 'On-Duty') ? 'You are on active duty' : 'You are currently off duty'; ?></p>
                    </div>
                </div>

                <!-- Quick Action Cards -->
                <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <a href="?module=incident_logging" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-plus-circle text-blue-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Log New Incident</h3>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Document a new incident with location and evidence</p>
                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-lg">Quick Action</span>
                    </a>

                    <a href="?module=report_vetting" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-search text-yellow-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Review Reports</h3>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Vet citizen-submitted reports for verification</p>
                        <?php if (isset($stats['pending_vetting']) && $stats['pending_vetting'] > 0): ?>
                            <span class="inline-block bg-red-100 text-red-800 text-xs px-3 py-1 rounded-lg"><?php echo $stats['pending_vetting']; ?> Pending</span>
                        <?php else: ?>
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-3 py-1 rounded-lg">Up to date</span>
                        <?php endif; ?>
                    </a>

                    <a href="?module=duty_schedule" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">View Schedule</h3>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Check your patrol schedule and assigned routes</p>
                        <span class="inline-block bg-green-100 text-green-800 text-xs px-3 py-1 rounded-lg">View Schedule</span>
                    </a>
                </div>

                <!-- Module Cards -->
                <h2 class="text-xl font-bold text-gray-800 mb-4">Modules</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="?module=duty_schedule" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-alt text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Duty & Patrol Schedule</h3>
                        </div>
                        <p class="text-gray-600 text-sm">View assigned shifts and designated routes. Clock in/out using real-time tracker.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Critical: Shift times, Patrol routes</span>
                        </div>
                    </a>

                    <a href="?module=incident_logging" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-clipboard-list text-purple-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Incident Logging</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Quick field form for incidents. GPS location recording and evidence upload.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Critical: Incident details, GPS, Evidence</span>
                        </div>
                    </a>

                    <a href="?module=report_vetting" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-check-circle text-yellow-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Report Vetting</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Review citizen reports for field verification. Submit vetting recommendations.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Critical: Report details, Verification notes</span>
                        </div>
                    </a>

                    <a href="?module=evidence_handover" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-box text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Evidence Handover</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Formal log for transferring physical evidence. Maintain chain of custody.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Critical: Item description, Handover details</span>
                        </div>
                    </a>

                    <a href="?module=profile" class="module-card bg-white rounded-xl p-6 cursor-pointer block">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-cog text-indigo-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Profile & Settings</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Manage contact information, profile details, and account settings.</p>
                        <div class="mt-4">
                            <span class="inline-block bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">Important: Contact details, Account settings</span>
                        </div>
                    </a>
                </div>
            </div>
            <?php
        } else {
            // Load other modules
            $module_file = "modules/{$module}.php";
            if (file_exists($module_file)) {
                include $module_file;
            } else {
                echo "<div class='bg-white rounded-xl p-6'>";
                echo "<h2 class='text-xl font-bold text-gray-800 mb-4'>Module Not Found</h2>";
                echo "<p class='text-gray-600'>The requested module is not available.</p>";
                echo "<a href='?module=dashboard' class='mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>Go to Dashboard</a>";
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
            
            <a href="?module=incident_logging" class="flex flex-col items-center text-gray-600 <?php echo $module == 'incident_logging' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-clipboard-list text-xl"></i>
                <span class="text-xs mt-1">Incidents</span>
                <?php if (isset($stats['pending_incidents']) && $stats['pending_incidents'] > 0): ?>
                    <span class="mobile-nav-badge"><?php echo min($stats['pending_incidents'], 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=report_vetting" class="flex flex-col items-center text-gray-600 <?php echo $module == 'report_vetting' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-check-circle text-xl"></i>
                <span class="text-xs mt-1">Vetting</span>
                <?php if (isset($stats['pending_vetting']) && $stats['pending_vetting'] > 0): ?>
                    <span class="mobile-nav-badge"><?php echo min($stats['pending_vetting'], 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=duty_schedule" class="flex flex-col items-center text-gray-600 <?php echo $module == 'duty_schedule' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Schedule</span>
            </a>
            
            <a href="?module=profile" class="flex flex-col items-center text-gray-600 <?php echo $module == 'profile' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-user text-xl"></i>
                <span class="text-xs mt-1">Profile</span>
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

        // Handle clock in/out
        function toggleDuty() {
            fetch('../ajax/toggle_duty.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to update all status indicators
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error toggling duty status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating duty status');
                });
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
    </script>
</body>
</html>
