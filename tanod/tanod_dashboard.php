<?php
// tanod/tanod_dashboard.php - TANOD DASHBOARD WITH LEIR LOGO
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Get tanod information
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
$valid_modules = ['dashboard', 'patrol_scheduling', 'incident_reporting', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Get user data including profile picture
$user_query = "SELECT u.*, 
                      IFNULL(u.barangay, 'Not specified') as barangay_display,
                      u.permanent_address as user_address,
                      u.profile_picture,
                      u.is_active,
                      u.is_on_duty,
                      bp.position_name
               FROM users u 
               LEFT JOIN barangay_positions bp ON u.position_id = bp.id
               WHERE u.id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([':id' => $user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    $is_active = $user_data['is_active'] ?? 1;
    $is_on_duty = $user_data['is_on_duty'] ?? 0;
    $position_name = $user_data['position_name'] ?? 'Tanod Member';
    $_SESSION['permanent_address'] = $user_data['user_address'];
    $_SESSION['barangay'] = $user_data['barangay_display'];
    $user_address = $user_data['user_address'];
    $profile_picture = $user_data['profile_picture'];
} else {
    $is_active = 1;
    $is_on_duty = 0;
    $position_name = 'Tanod Member';
    $user_address = '';
    $profile_picture = '';
}

// Get statistics for dashboard
$stats = [];
if ($module == 'dashboard') {
    // Get total patrol logs for this tanod
    $patrol_query = "SELECT COUNT(*) as count FROM patrol_logs WHERE tanod_id = :tanod_id";
    $patrol_stmt = $conn->prepare($patrol_query);
    $patrol_stmt->execute([':tanod_id' => $user_id]);
    $stats['total_patrols'] = $patrol_stmt->fetchColumn();
    
    // Get incidents reported by this tanod
    $incidents_query = "SELECT COUNT(*) as count FROM reports WHERE reported_by_tanod = :tanod_id";
    $incidents_stmt = $conn->prepare($incidents_query);
    $incidents_stmt->execute([':tanod_id' => $user_id]);
    $stats['incidents_reported'] = $incidents_stmt->fetchColumn();
    
    // Get today's patrols
    $today_patrol_query = "SELECT COUNT(*) as count FROM patrol_logs 
                          WHERE tanod_id = :tanod_id 
                          AND DATE(patrol_start) = CURDATE()";
    $today_patrol_stmt = $conn->prepare($today_patrol_query);
    $today_patrol_stmt->execute([':tanod_id' => $user_id]);
    $stats['today_patrols'] = $today_patrol_stmt->fetchColumn();
    
    // Get current duty status
    $stats['on_duty'] = $is_on_duty;
    
    // Get recent patrols
    $recent_patrols_query = "SELECT pl.*, a.area_name 
                            FROM patrol_logs pl
                            LEFT JOIN patrol_areas a ON pl.area_id = a.id
                            WHERE pl.tanod_id = :tanod_id
                            ORDER BY pl.patrol_start DESC 
                            LIMIT 5";
    $recent_patrols_stmt = $conn->prepare($recent_patrols_query);
    $recent_patrols_stmt->execute([':tanod_id' => $user_id]);
    $recent_patrols = $recent_patrols_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Tanod Dashboard',
        'patrol_scheduling' => 'Patrol Scheduling',
        'incident_reporting' => 'Incident Reporting',
        'profile' => 'Profile & Performance'
    ];
    return $titles[$module] ?? 'Tanod Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'Overview of patrol activities and incident reports',
        'patrol_scheduling' => 'View patrol schedules and log patrol activities',
        'incident_reporting' => 'Report incidents and security concerns',
        'profile' => 'View patrol statistics and manage your account'
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
            --dark-blue: #1976d2;
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
        
        .sidebar {
            background: linear-gradient(135deg, #1976d2 0%, #2196f3 100%);
            box-shadow: 0 4px 20px rgba(33, 150, 243, 0.2);
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
            font-weight: 600;
        }
        
        .mobile-bottom-nav {
            background: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        
        .mobile-nav-active {
            color: #2196f3;
        }
        
        .mobile-nav-badge {
            position: absolute;
            top: -5px;
            right: 10px;
            background: #f44336;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 45;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 280px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 50;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding-bottom: 70px;
            }
        }
        
        .duty-status-on {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
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
                    <p class="text-blue-200 text-sm">Tanod Patrol System</p>
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
                        <p class="text-blue-200 text-sm"><?php echo htmlspecialchars($position_name); ?></p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-blue-200 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-xs"></i>
                        <span class="truncate"><?php echo htmlspecialchars($user_address ?? 'Barangay Office'); ?></span>
                    </p>
                    <div class="mt-2">
                        <span class="text-xs px-2 py-1 rounded-full <?php echo $is_on_duty ? 'bg-green-500/30 text-green-300' : 'bg-gray-500/30 text-gray-300'; ?>">
                            <i class="fas fa-shield-alt mr-1"></i>
                            <?php echo $is_on_duty ? 'ON DUTY' : 'OFF DUTY'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <nav class="space-y-2">
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Patrol Dashboard
                </a>
                <a href="?module=patrol_scheduling" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'patrol_scheduling' ? 'active' : ''; ?>">
                    <i class="fas fa-walking mr-3"></i>
                    Patrol Scheduling
                </a>
                <a href="?module=incident_reporting" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'incident_reporting' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                    Incident Reporting
                </a>
            </nav>
            
            <!-- Status & Stats -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Total Patrols</span>
                        <span class="text-white font-bold"><?php echo $stats['total_patrols'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Incidents Reported</span>
                        <span class="text-white font-bold"><?php echo $stats['incidents_reported'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Today's Patrols</span>
                        <span class="text-white font-bold"><?php echo $stats['today_patrols'] ?? 0; ?></span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="?module=profile" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition mb-2">
                        <i class="fas fa-user mr-3"></i>
                        Profile & Performance
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
                        <!-- Duty Status Toggle -->
                        <div class="relative">
                            <form id="dutyToggleForm" method="POST" action="../ajax/toggle_duty.php">
                                <button type="button" onclick="toggleDutyStatus()" 
                                        class="flex items-center space-x-2 px-4 py-2 rounded-lg <?php echo $is_on_duty ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-gray-100 text-gray-800 border border-gray-300'; ?> hover:opacity-90 transition">
                                    <div class="w-3 h-3 rounded-full <?php echo $is_on_duty ? 'bg-green-500 duty-status-on' : 'bg-gray-500'; ?>"></div>
                                    <span class="text-sm font-medium"><?php echo $is_on_duty ? 'ON DUTY' : 'OFF DUTY'; ?></span>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button onclick="showNotifications()" class="relative">
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
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($position_name); ?></p>
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
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($position_name); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Status: <span class="ml-1 font-medium <?php echo $is_on_duty ? 'text-green-600' : 'text-gray-600'; ?>">
                                            <?php echo $is_on_duty ? 'ON DUTY' : 'OFF DUTY'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user mr-2"></i>
                                        Profile & Performance
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
        $module_file = "modules/{$module}.php";
        if (file_exists($module_file)) {
            include $module_file;
        } else {
            // Default dashboard content
            echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">';
            echo '  <div class="bg-white rounded-xl shadow p-6">';
            echo '    <div class="flex items-center">';
            echo '      <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-walking text-blue-600 text-xl"></i>';
            echo '      </div>';
            echo '      <div>';
            echo '        <p class="text-gray-500 text-sm">Total Patrols</p>';
            echo '        <p class="text-2xl font-bold text-gray-800">' . ($stats['total_patrols'] ?? 0) . '</p>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';
            
            echo '  <div class="bg-white rounded-xl shadow p-6">';
            echo '    <div class="flex items-center">';
            echo '      <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-exclamation-triangle text-green-600 text-xl"></i>';
            echo '      </div>';
            echo '      <div>';
            echo '        <p class="text-gray-500 text-sm">Incidents Reported</p>';
            echo '        <p class="text-2xl font-bold text-gray-800">' . ($stats['incidents_reported'] ?? 0) . '</p>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';
            
            echo '  <div class="bg-white rounded-xl shadow p-6">';
            echo '    <div class="flex items-center">';
            echo '      <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-calendar-day text-purple-600 text-xl"></i>';
            echo '      </div>';
            echo '      <div>';
            echo '        <p class="text-gray-500 text-sm">Today\'s Patrols</p>';
            echo '        <p class="text-2xl font-bold text-gray-800">' . ($stats['today_patrols'] ?? 0) . '</p>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';
            
            echo '  <div class="bg-white rounded-xl shadow p-6">';
            echo '    <div class="flex items-center">';
            echo '      <div class="w-12 h-12 rounded-lg ' . ($is_on_duty ? 'bg-green-100' : 'bg-gray-100') . ' flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-shield-alt ' . ($is_on_duty ? 'text-green-600' : 'text-gray-600') . ' text-xl"></i>';
            echo '      </div>';
            echo '      <div>';
            echo '        <p class="text-gray-500 text-sm">Duty Status</p>';
            echo '        <p class="text-2xl font-bold ' . ($is_on_duty ? 'text-green-600' : 'text-gray-600') . '">' . ($is_on_duty ? 'ON DUTY' : 'OFF DUTY') . '</p>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
            
            // Recent Patrols
            if (!empty($recent_patrols)) {
                echo '<div class="bg-white rounded-xl shadow mb-8">';
                echo '  <div class="p-6 border-b">';
                echo '    <h2 class="text-xl font-bold text-gray-800">Recent Patrols</h2>';
                echo '    <p class="text-gray-600 text-sm">Your latest patrol activities</p>';
                echo '  </div>';
                echo '  <div class="p-6">';
                echo '    <div class="overflow-x-auto">';
                echo '      <table class="w-full">';
                echo '        <thead>';
                echo '          <tr class="text-left text-gray-500 text-sm border-b">';
                echo '            <th class="pb-3">Date & Time</th>';
                echo '            <th class="pb-3">Area</th>';
                echo '            <th class="pb-3">Status</th>';
                echo '            <th class="pb-3">Duration</th>';
                echo '          </tr>';
                echo '        </thead>';
                echo '        <tbody>';
                foreach ($recent_patrols as $patrol) {
                    $start_time = date('M d, h:i A', strtotime($patrol['patrol_start']));
                    $end_time = $patrol['patrol_end'] ? date('h:i A', strtotime($patrol['patrol_end'])) : 'Ongoing';
                    $duration = $patrol['patrol_end'] ? 
                        round((strtotime($patrol['patrol_end']) - strtotime($patrol['patrol_start'])) / 3600, 1) . ' hrs' : 
                        'In Progress';
                    
                    echo '<tr class="border-b hover:bg-gray-50">';
                    echo '  <td class="py-4">' . $start_time . '</td>';
                    echo '  <td class="py-4">' . ($patrol['area_name'] ?? 'Unspecified') . '</td>';
                    echo '  <td class="py-4">';
                    echo '    <span class="px-3 py-1 rounded-full text-xs ' . 
                         ($patrol['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') . '">';
                    echo '      ' . ucfirst($patrol['status']);
                    echo '    </span>';
                    echo '  </td>';
                    echo '  <td class="py-4">' . $duration . '</td>';
                    echo '</tr>';
                }
                echo '        </tbody>';
                echo '      </table>';
                echo '    </div>';
                echo '  </div>';
                echo '</div>';
            }
            
            // Quick Actions
            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';
            echo '  <a href="?module=patrol_scheduling" class="module-card bg-white rounded-xl shadow p-6 hover:shadow-lg transition">';
            echo '    <div class="flex items-center mb-4">';
            echo '      <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-calendar-plus text-blue-600 text-xl"></i>';
            echo '      </div>';
            echo '      <h3 class="text-lg font-semibold text-gray-800">Start New Patrol</h3>';
            echo '    </div>';
            echo '    <p class="text-gray-600 mb-4">Log a new patrol activity with details and location</p>';
            echo '    <span class="text-blue-600 font-medium">Start Patrol →</span>';
            echo '  </a>';
            
            echo '  <a href="?module=incident_reporting" class="module-card bg-white rounded-xl shadow p-6 hover:shadow-lg transition">';
            echo '    <div class="flex items-center mb-4">';
            echo '      <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>';
            echo '      </div>';
            echo '      <h3 class="text-lg font-semibold text-gray-800">Report Incident</h3>';
            echo '    </div>';
            echo '    <p class="text-gray-600 mb-4">Report security incidents or community concerns</p>';
            echo '    <span class="text-red-600 font-medium">Report Now →</span>';
            echo '  </a>';
            
            echo '  <a href="?module=profile" class="module-card bg-white rounded-xl shadow p-6 hover:shadow-lg transition">';
            echo '    <div class="flex items-center mb-4">';
            echo '      <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center mr-4">';
            echo '        <i class="fas fa-chart-line text-green-600 text-xl"></i>';
            echo '      </div>';
            echo '      <h3 class="text-lg font-semibold text-gray-800">View Performance</h3>';
            echo '    </div>';
            echo '    <p class="text-gray-600 mb-4">Check your patrol statistics and performance metrics</p>';
            echo '    <span class="text-green-600 font-medium">View Stats →</span>';
            echo '  </a>';
            echo '</div>';
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
            
            <a href="?module=patrol_scheduling" class="flex flex-col items-center text-gray-600 <?php echo $module == 'patrol_scheduling' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-walking text-xl"></i>
                <span class="text-xs mt-1">Patrol</span>
            </a>
            
            <a href="?module=incident_reporting" class="flex flex-col items-center text-gray-600 <?php echo $module == 'incident_reporting' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-exclamation-triangle text-xl"></i>
                <span class="text-xs mt-1">Report</span>
            </a>
            
            <a href="?module=profile" class="flex flex-col items-center text-gray-600 <?php echo $module == 'profile' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-user text-xl"></i>
                <span class="text-xs mt-1">Profile</span>
            </a>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[80vh] overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Notifications</h3>
                <button onclick="closeNotifications()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4 overflow-y-auto max-h-[60vh]">
                <div class="space-y-4">
                    <?php
                    // Fetch notifications
                    $notif_query = "SELECT * FROM notifications 
                                   WHERE user_id = :user_id 
                                   ORDER BY created_at DESC 
                                   LIMIT 10";
                    $notif_stmt = $conn->prepare($notif_query);
                    $notif_stmt->execute([':user_id' => $user_id]);
                    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($notifications)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-bell-slash text-3xl mb-3"></i>
                            <p>No notifications</p>
                        </div>
                    <?php else: 
                        foreach ($notifications as $notif): ?>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                <span class="text-xs text-gray-500"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <?php if (!empty($notif['related_type'])): ?>
                                <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded"><?php echo htmlspecialchars($notif['related_type']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
            <div class="p-4 border-t">
                <button onclick="markAllAsRead()" class="w-full py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg">
                    Mark all as read
                </button>
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

        // Toggle duty status
        function toggleDutyStatus() {
            fetch('../ajax/toggle_duty.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=<?php echo $user_id; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update duty status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating duty status');
            });
        }

        // Notifications modal
        function showNotifications() {
            document.getElementById('notificationsModal').classList.remove('hidden');
            document.getElementById('notificationsModal').classList.add('flex');
        }
        
        function closeNotifications() {
            document.getElementById('notificationsModal').classList.add('hidden');
            document.getElementById('notificationsModal').classList.remove('flex');
        }
        
        function markAllAsRead() {
            fetch('../ajax/mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=<?php echo $user_id; ?>'
            }).then(() => {
                location.reload();
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const notifModal = document.getElementById('notificationsModal');
            if (event.target == notifModal) {
                closeNotifications();
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
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
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