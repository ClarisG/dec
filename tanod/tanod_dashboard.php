<?php
// lupon/lupon_dashboard.php - LUPON DASHBOARD WITH LEIR LOGO
session_start();

// Check if user is logged in and is lupon
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lupon') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

// Get lupon information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if assigned_lupon column exists
$checkColumnStmt = $conn->query("SHOW COLUMNS FROM reports LIKE 'assigned_lupon'");
$columnExists = $checkColumnStmt->rowCount() > 0;

// Alternative: Check for other assignment columns
$checkAltColumnStmt = $conn->query("SHOW COLUMNS FROM reports");
$allColumns = $checkAltColumnStmt->fetchAll(PDO::FETCH_COLUMN);
$assignmentColumns = array_filter($allColumns, function($col) {
    return stripos($col, 'assign') !== false || stripos($col, 'lupon') !== false;
});

// Determine which column to use for assignment
$assignmentColumn = 'assigned_lupon'; // default
if (!$columnExists && !empty($assignmentColumns)) {
    $assignmentColumn = reset($assignmentColumns); // use first assignment-related column
} elseif (!$columnExists) {
    // Create a temporary assignment method using mediation_logs
    $assignmentColumn = null;
}

// Handle module switching
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = ['dashboard', 'case_mediation', 'mediation_scheduling', 'settlement_document', 'progress_tracker', 'profile'];
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
        header("Location: lupon_dashboard.php?module=case_mediation");
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
    $position_name = $user_data['position_name'] ?? 'Lupon Member';
    $_SESSION['permanent_address'] = $user_data['user_address'];
    $_SESSION['barangay'] = $user_data['barangay_display'];
    $user_address = $user_data['user_address'];
    $profile_picture = $user_data['profile_picture'];
} else {
    $is_active = 1;
    $position_name = 'Lupon Member';
    $user_address = '';
    $profile_picture = '';
}

// Get statistics for dashboard
$stats = [];
if ($module == 'dashboard') {
    // Assigned mediation cases - dynamic query based on column existence
    if ($assignmentColumn) {
        $assigned_query = "SELECT COUNT(*) as count FROM reports 
                          WHERE $assignmentColumn = :lupon_id 
                          AND status IN ('pending', 'assigned', 'in_mediation')";
    } else {
        // Alternative: Get cases from mediation_logs where lupon is assigned
        $assigned_query = "SELECT COUNT(DISTINCT ml.report_id) as count 
                          FROM mediation_logs ml
                          JOIN reports r ON ml.report_id = r.id
                          WHERE ml.lupon_id = :lupon_id 
                          AND r.status IN ('pending', 'assigned', 'in_mediation')";
    }
    $assigned_stmt = $conn->prepare($assigned_query);
    $assigned_stmt->execute([':lupon_id' => $user_id]);
    $stats['assigned_cases'] = $assigned_stmt->fetchColumn();
    
    // Successful mediations (last 30 days)
    $success_query = "SELECT COUNT(DISTINCT ml.report_id) as count 
                     FROM mediation_logs ml
                     JOIN reports r ON ml.report_id = r.id
                     WHERE ml.lupon_id = :lupon_id 
                     AND ml.status = 'successful'
                     AND ml.mediation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $success_stmt = $conn->prepare($success_query);
    $success_stmt->execute([':lupon_id' => $user_id]);
    $stats['successful_mediations'] = $success_stmt->fetchColumn();
    
    // Upcoming mediation sessions
    $upcoming_query = "SELECT COUNT(*) as count FROM mediation_logs 
                      WHERE lupon_id = :lupon_id 
                      AND mediation_date >= CURDATE() 
                      AND status = 'scheduled'";
    $upcoming_stmt = $conn->prepare($upcoming_query);
    $upcoming_stmt->execute([':lupon_id' => $user_id]);
    $stats['upcoming_sessions'] = $upcoming_stmt->fetchColumn();
    
    // Mediation success rate
    $total_mediated_query = "SELECT COUNT(*) as total FROM mediation_logs WHERE lupon_id = :lupon_id";
    $total_mediated_stmt = $conn->prepare($total_mediated_query);
    $total_mediated_stmt->execute([':lupon_id' => $user_id]);
    $total_mediated = $total_mediated_stmt->fetchColumn();
    
    $successful_query = "SELECT COUNT(*) as successful FROM mediation_logs WHERE lupon_id = :lupon_id AND status = 'successful'";
    $successful_stmt = $conn->prepare($successful_query);
    $successful_stmt->execute([':lupon_id' => $user_id]);
    $successful = $successful_stmt->fetchColumn();
    
    $stats['success_rate'] = $total_mediated > 0 ? round(($successful / $total_mediated) * 100, 1) : 0;
    
    // Get recent mediation cases
    if ($assignmentColumn) {
        $recent_cases_query = "SELECT r.*, u.first_name as complainant_fname, u.last_name as complainant_lname,
                                      rt.type_name, r.created_at as case_date
                               FROM reports r
                               JOIN users u ON r.user_id = u.id
                               JOIN report_types rt ON r.report_type_id = rt.id
                               WHERE r.$assignmentColumn = :lupon_id
                               ORDER BY r.created_at DESC 
                               LIMIT 5";
    } else {
        $recent_cases_query = "SELECT DISTINCT r.*, u.first_name as complainant_fname, u.last_name as complainant_lname,
                                      rt.type_name, r.created_at as case_date
                               FROM reports r
                               JOIN users u ON r.user_id = u.id
                               JOIN report_types rt ON r.report_type_id = rt.id
                               JOIN mediation_logs ml ON r.id = ml.report_id
                               WHERE ml.lupon_id = :lupon_id
                               ORDER BY r.created_at DESC 
                               LIMIT 5";
    }
    $recent_cases_stmt = $conn->prepare($recent_cases_query);
    $recent_cases_stmt->execute([':lupon_id' => $user_id]);
    $recent_cases = $recent_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Mediation Dashboard',
        'case_mediation' => 'Case Mediation Desk',
        'mediation_scheduling' => 'Mediation Scheduling',
        'settlement_document' => 'Settlement Documents',
        'progress_tracker' => 'Mediation Progress',
        'profile' => 'Profile & Performance'
    ];
    return $titles[$module] ?? 'Mediation Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'Overview of mediation activities and performance metrics',
        'case_mediation' => 'View and mediate assigned barangay cases with full access to details',
        'mediation_scheduling' => 'Coordinate hearing schedules and send reminders to parties',
        'settlement_document' => 'Generate and sign amicable settlement agreements and reports',
        'progress_tracker' => 'Monitor mediation progress and log session outcomes',
        'profile' => 'View mediation statistics and manage your account'
    ];
    return $subtitles[$module] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupon Dashboard - <?php echo getModuleTitle($module); ?></title>
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
            --primary-green: #e8f5e9;
            --secondary-green: #c8e6c9;
            --accent-green: #4caf50;
            --dark-green: #2e7d32;
            --light-green: #f9fff9;
        }
        
        body {
            background: linear-gradient(135deg, #f9fff9 0%, #e8f5e9 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-green);
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.1);
            border-left-color: var(--dark-green);
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
            <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-green-400/30">
                <div class="w-10 h-10 flex items-center justify-center">
                    <img src="../images/10213.png" alt="Logo" class="w-19 h-22 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">LEIR</h1>
                    <p class="text-green-200 text-sm">Lupon Mediation System</p>
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
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-600 to-green-500 flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                    </div>
                    <div>
                        <p class="text-white font-medium truncate"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="text-green-200 text-sm"><?php echo htmlspecialchars($position_name); ?></p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-green-200 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-xs"></i>
                        <span class="truncate"><?php echo htmlspecialchars($user_address ?? 'Barangay Office'); ?></span>
                    </p>
                </div>
            </div>
            
            <nav class="space-y-2">
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Mediation Dashboard
                </a>
                <a href="?module=case_mediation" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'case_mediation' ? 'active' : ''; ?>">
                    <i class="fas fa-handshake mr-3"></i>
                    Case Mediation Desk
                    <?php if (isset($stats['assigned_cases']) && $stats['assigned_cases'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['assigned_cases'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=mediation_scheduling" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'mediation_scheduling' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Mediation Scheduling
                </a>
                <a href="?module=settlement_document" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'settlement_document' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature mr-3"></i>
                    Settlement Documents
                </a>
                <a href="?module=progress_tracker" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'progress_tracker' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i>
                    Mediation Progress
                </a>
            </nav>
            
            <!-- Status & Stats -->
            <div class="mt-8 pt-8 border-t border-green-400/30">
                <div class="mb-4">
                    <div class="flex items-center p-3 rounded-lg bg-green-500/20 text-green-300">
                        <i class="fas fa-chart-pie mr-3"></i>
                        <div class="flex-1">
                            <div class="text-xs">Success Rate</div>
                            <div class="font-bold text-lg"><?php echo $stats['success_rate'] ?? 0; ?>%</div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-green-200">Active Cases</span>
                        <span class="text-white font-bold"><?php echo $stats['assigned_cases'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-200">Successful (30d)</span>
                        <span class="text-white font-bold"><?php echo $stats['successful_mediations'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-200">Upcoming</span>
                        <span class="text-white font-bold"><?php echo $stats['upcoming_sessions'] ?? 0; ?></span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="?module=profile" class="flex items-center p-3 text-green-200 hover:text-white hover:bg-white/10 rounded-lg transition mb-2">
                        <i class="fas fa-user mr-3"></i>
                        Profile & Performance
                    </a>
                    <a href="../logout.php" class="flex items-center p-3 text-green-200 hover:text-white hover:bg-white/10 rounded-lg transition">
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
                                <i class="fas fa-bell text-gray-600 text-xl cursor-pointer hover:text-green-600"></i>
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
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-600 to-green-500 flex items-center justify-center text-white font-semibold">
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
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-600 to-green-500 flex items-center justify-center text-white font-bold">
                                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user_name); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($position_name); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <i class="fas fa-chart-line mr-1"></i>
                                        Success Rate: <?php echo $stats['success_rate'] ?? 0; ?>%
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
            include "modules/dashboard.php";
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
            
            <a href="?module=case_mediation" class="flex flex-col items-center text-gray-600 <?php echo $module == 'case_mediation' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-handshake text-xl"></i>
                <span class="text-xs mt-1">Mediate</span>
                <?php if (isset($stats['assigned_cases']) && $stats['assigned_cases'] > 0): ?>
                    <span class="mobile-nav-badge"><?php echo min($stats['assigned_cases'], 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=mediation_scheduling" class="flex flex-col items-center text-gray-600 <?php echo $module == 'mediation_scheduling' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Schedule</span>
            </a>
            
            <a href="?module=settlement_document" class="flex flex-col items-center text-gray-600 <?php echo $module == 'settlement_document' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-file-signature text-xl"></i>
                <span class="text-xs mt-1">Documents</span>
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
                fetch('../ajax/get_lupon_stats.php?user_id=<?php echo $user_id; ?>')
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