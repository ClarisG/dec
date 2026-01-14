<?php
// tanod/tanod_dashboard.php - TANOD DASHBOARD WITH LEIR LOGO
session_start();

// Check if user is logged in and is tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

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
$valid_modules = ['dashboard', 'duty_schedule', 'evidence_handover', 'incident_logging', 'report_vetting', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle actions based on module
if ($module == 'case_mediation' && isset($_POST['start_mediation'])) {
    $case_id = $_POST['case_id'];
    $mediation_date = $_POST['mediation_date'];
    $notes = $_POST['mediation_notes'];
    
    try {
        // Update report status
        $updateSql = "UPDATE reports SET status = 'in_mediation', mediation_started = NOW()";
        if ($assignmentColumn) {
            $updateSql .= ", $assignmentColumn = :lupon_id";
        }
        $updateSql .= " WHERE id = :id";
        
        $stmt = $conn->prepare($updateSql);
        $params = [':id' => $case_id];
        if ($assignmentColumn) {
            $params[':lupon_id'] = $user_id;
        }
        $stmt->execute($params);
        
        // Insert mediation log
        $log_stmt = $conn->prepare("INSERT INTO mediation_logs (report_id, lupon_id, mediation_date, notes, status) VALUES (:report_id, :lupon_id, :mediation_date, :notes, 'scheduled')");
        $log_stmt->execute([
            ':report_id' => $case_id,
            ':lupon_id' => $user_id,
            ':mediation_date' => $mediation_date,
            ':notes' => $notes
        ]);
        
        $_SESSION['success'] = "Mediation session scheduled successfully!";
        header("Location: tanod_dashboard.php?module=case_mediation");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to schedule mediation: " . $e->getMessage();
    }
}

// Get user data including profile picture
$user_query = "SELECT u.*, 
                      IFNULL(u.barangay, 'Not specified') as barangay_display,
                      u.permanent_address as user_address,
                      u.profile_picture,
                      u.is_active,
                      bp.position_name
               FROM users u 
               LEFT JOIN barangay_positions bp ON u.position_id = bp.id
               WHERE u.id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([':id' => $user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    $is_active = $user_data['is_active'] ?? 1;
    $position_name = $user_data['position_name'] ?? 'Tanod Member';
    $_SESSION['permanent_address'] = $user_data['user_address'];
    $_SESSION['barangay'] = $user_data['barangay_display'];
    $user_address = $user_data['user_address'];
    $profile_picture = $user_data['profile_picture'];
} else {
    $is_active = 1;
    $position_name = 'Tanod Member';
    $user_address = '';
    $profile_picture = '';
}

// Get statistics for dashboard
$stats = [];
if ($module == 'dashboard') {
    // Get today's duty status
    $duty_query = "SELECT COUNT(*) as count FROM tanod_duty_logs 
                   WHERE user_id = :tanod_id 
                   AND clock_out IS NULL 
                   AND DATE(clock_in) = CURDATE()";
    $duty_stmt = $conn->prepare($duty_query);
    $duty_stmt->execute([':tanod_id' => $user_id]);
    $stats['on_duty'] = $duty_stmt->fetchColumn();
    
    // Pending reports for vetting
    $pending_query = "SELECT COUNT(*) as count FROM reports 
                     WHERE (assigned_tanod = :tanod_id OR assigned_to = :tanod_id2)
                     AND status IN ('pending_field_verification', 'assigned')
                     AND needs_verification = 1";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->execute([':tanod_id' => $user_id, ':tanod_id2' => $user_id]);
    $stats['pending_reports'] = $pending_stmt->fetchColumn();
    
    // Today's incidents
    $incidents_query = "SELECT COUNT(*) as count FROM tanod_incidents 
                       WHERE user_id = :tanod_id 
                       AND DATE(reported_at) = CURDATE()";
    $incidents_stmt = $conn->prepare($incidents_query);
    $incidents_stmt->execute([':tanod_id' => $user_id]);
    $stats['today_incidents'] = $incidents_stmt->fetchColumn();
    
    // Pending handovers
    $handovers_query = "SELECT COUNT(*) as count FROM evidence_handovers 
                       WHERE tanod_id = :tanod_id 
                       AND recipient_acknowledged = 0";
    $handovers_stmt = $conn->prepare($handovers_query);
    $handovers_stmt->execute([':tanod_id' => $user_id]);
    $stats['pending_handovers'] = $handovers_stmt->fetchColumn();
    
    // Get recent incidents
    $recent_incidents_query = "SELECT * FROM tanod_incidents 
                              WHERE user_id = :tanod_id 
                              ORDER BY reported_at DESC 
                              LIMIT 5";
    $recent_incidents_stmt = $conn->prepare($recent_incidents_query);
    $recent_incidents_stmt->execute([':tanod_id' => $user_id]);
    $recent_incidents = $recent_incidents_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Tanod Dashboard',
        'duty_schedule' => 'Duty & Patrol Schedule',
        'evidence_handover' => 'Evidence Handover',
        'incident_logging' => 'Incident Logging',
        'report_vetting' => 'Report Vetting',
        'profile' => 'Profile & Settings'
    ];
    return $titles[$module] ?? 'Tanod Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'Overview of tanod activities and statistics',
        'duty_schedule' => 'Manage duty shifts and patrol routes',
        'evidence_handover' => 'Log and track evidence handovers',
        'incident_logging' => 'Report and log field incidents',
        'report_vetting' => 'Verify and vet citizen reports',
        'profile' => 'Manage your account and settings'
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
        /* ... (keep all existing CSS styles as they are) ... */
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-blue: #e8f4f8;
            --secondary-blue: #c8e3ed;
            --accent-blue: #3498db;
            --dark-blue: #2c3e50;
            --light-blue: #f9fcff;
        }
        
        body {
            background: linear-gradient(135deg, #f9fcff 0%, #e8f4f8 100%);
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
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.1);
            border-left-color: var(--dark-blue);
        }
        
        /* ... (rest of the CSS remains the same) ... */
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
                    <p class="text-blue-200 text-sm">Tanod Duty System</p>
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
                </div>
            </div>
            
            <nav class="space-y-2">
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Tanod Dashboard
                </a>
                <a href="?module=duty_schedule" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'duty_schedule' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Duty Schedule
                </a>
                <a href="?module=evidence_handover" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'evidence_handover' ? 'active' : ''; ?>">
                    <i class="fas fa-box-open mr-3"></i>
                    Evidence Handover
                </a>
                <a href="?module=incident_logging" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'incident_logging' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                    Incident Logging
                </a>
                <a href="?module=report_vetting" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'report_vetting' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check mr-3"></i>
                    Report Vetting
                    <?php if (isset($stats['pending_reports']) && $stats['pending_reports'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['pending_reports'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
            </nav>
            
            <!-- Status & Stats -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="mb-4">
                    <div class="flex items-center p-3 rounded-lg bg-blue-500/20 text-blue-300">
                        <i class="fas fa-shield-alt mr-3"></i>
                        <div class="flex-1">
                            <div class="text-xs">Duty Status</div>
                            <div class="font-bold text-lg">
                                <?php echo isset($stats['on_duty']) && $stats['on_duty'] > 0 ? 'ON DUTY' : 'OFF DUTY'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Pending Reports</span>
                        <span class="text-white font-bold"><?php echo $stats['pending_reports'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Today's Incidents</span>
                        <span class="text-white font-bold"><?php echo $stats['today_incidents'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">Pending Handovers</span>
                        <span class="text-white font-bold"><?php echo $stats['pending_handovers'] ?? 0; ?></span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="?module=profile" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition mb-2">
                        <i class="fas fa-user mr-3"></i>
                        Profile & Settings
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
                                    <div class="text-xs text-gray-500">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Tanod ID: T-<?php echo str_pad($user_id, 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user mr-2"></i>
                                        Profile & Settings
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
        // Load Module Content - Fixed path for Tanod modules
        $module_file = __DIR__ . "/modules/{$module}.php";
        if (file_exists($module_file)) {
            include $module_file;
        } else {
            // Default dashboard content
            include __DIR__ . "/modules/dashboard.php";
        }
        ?>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav md:hidden">
        <div class="flex justify-around items-center py-3">
            <a href="?module=dashboard" class="flex flex-col items-center text-gray-600 <?php echo $module == 'dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Dashboard</span>
            </a>
            
            <a href="?module=duty_schedule" class="flex flex-col items-center text-gray-600 <?php echo $module == 'duty_schedule' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Duty</span>
            </a>
            
            <a href="?module=incident_logging" class="flex flex-col items-center text-gray-600 <?php echo $module == 'incident_logging' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-exclamation-triangle text-xl"></i>
                <span class="text-xs mt-1">Incident</span>
            </a>
            
            <a href="?module=evidence_handover" class="flex flex-col items-center text-gray-600 <?php echo $module == 'evidence_handover' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-box-open text-xl"></i>
                <span class="text-xs mt-1">Evidence</span>
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
        
        // Refresh dashboard stats every 30 seconds
        if (window.location.search.includes('module=dashboard')) {
            setInterval(() => {
                fetch('../ajax/get_tanod_stats.php?user_id=<?php echo $user_id; ?>')
                    .then(response => response.json())
                    .then(data => {
                        // Update stats on the page if needed
                        console.log('Stats updated:', data);
                    });
            }, 30000);
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