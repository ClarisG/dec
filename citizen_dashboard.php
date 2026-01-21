<?php
// citizen_dashboard.php - UPDATED PROFESSIONAL DESIGN
// Start session only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a citizen
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: ../index.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';
require_once 'config/rate_limit.php';
require_once 'config/base_url.php';

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$barangay = $_SESSION['barangay'];

// Get the current module from URL
$current_module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';

// Database connection
try {
    $conn = getDbConnection();
    
    // Get complete user data including address
    $user_query = "SELECT u.*, 
                          IFNULL(u.barangay, 'Not specified') as barangay_display,
                          u.permanent_address as user_address
                   FROM users u 
                   WHERE u.id = :id";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':id', $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        $is_active = $user_data['is_active'] ?? 1;
        // Update session with current address
        $_SESSION['permanent_address'] = $user_data['user_address'];
        $_SESSION['barangay'] = $user_data['barangay_display'];
        $user_address = $user_data['user_address'];
        $profile_picture = $user_data['profile_picture'];
        
        // Store profile picture in session for immediate access
        $_SESSION['profile_picture'] = $profile_picture;
    } else {
        $error = "User not found.";
        $is_active = 1;
        $user_address = '';
        $profile_picture = '';
    }
    
    // Get reports count for badges
    $reports_query = "SELECT COUNT(*) as total,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                      SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                      SUM(CASE WHEN status = 'for_verification' THEN 1 ELSE 0 END) as verification,
                      SUM(CASE WHEN status = 'for_mediation' THEN 1 ELSE 0 END) as mediation,
                      SUM(CASE WHEN status = 'referred' THEN 1 ELSE 0 END) as referred,
                      SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                      FROM reports WHERE user_id = :user_id";
    $reports_stmt = $conn->prepare($reports_query);
    $reports_stmt->bindParam(':user_id', $user_id);
    $reports_stmt->execute();
    $report_counts = $reports_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get latest announcements
    $announce_query = "SELECT * FROM announcements 
                      WHERE (target_role = 'citizen' OR target_role = 'all')
                      AND barangay IN (:barangay, 'all')
                      AND is_active = 1
                      ORDER BY created_at DESC 
                      LIMIT 5";
    $announce_stmt = $conn->prepare($announce_query);
    $announce_stmt->bindParam(':barangay', $barangay);
    $announce_stmt->execute();
    $announcements = $announce_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent reports
    $recent_reports_query = "SELECT r.*, rt.type_name, rt.category, rt.jurisdiction
                            FROM reports r
                            LEFT JOIN report_types rt ON r.report_type_id = rt.id
                            WHERE r.user_id = :user_id
                            ORDER BY r.created_at DESC 
                            LIMIT 5";
    $recent_stmt = $conn->prepare($recent_reports_query);
    $recent_stmt->bindParam(':user_id', $user_id);
    $recent_stmt->execute();
    $recent_reports = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get rate limit info
    $rate_limit_info = getRateLimitInfo($user_id);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Citizen Dashboard Error: " . $e->getMessage());
}

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    try {
        $new_status = $is_active ? 0 : 1;
        $toggle_query = "UPDATE users SET is_active = :status WHERE id = :id";
        $toggle_stmt = $conn->prepare($toggle_query);
        $toggle_stmt->bindParam(':status', $new_status, PDO::PARAM_INT);
        $toggle_stmt->bindParam(':id', $user_id);
        
        if ($toggle_stmt->execute()) {
            $is_active = $new_status;
            $_SESSION['success'] = "Status updated successfully!";
            header("Location: citizen_dashboard.php");
            exit;
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update status: " . $e->getMessage();
    }
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Dashboard',
        'new-report' => 'New Report',
        'my-reports' => 'My Reports',
        'announcements' => 'Announcements',
        'profile' => 'Profile Settings'
    ];
    return $titles[$module] ?? 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR - <?php echo getModuleTitle($current_module); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/10213.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
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
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-verification { background: #fef3c7; color: #92400e; }
        .status-mediation { background: #fef3c7; color: #92400e; }
        .status-referred { background: #f3e8ff; color: #5b21b6; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-closed { background: #f1f5f9; color: #475569; }
        
        .module-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .report-card {
            border-left: 3px solid;
            transition: all 0.2s ease;
        }
        
        .report-card:hover {
            transform: translateX(3px);
        }
        
        .report-barangay { border-left-color: #3b82f6; }
        .report-police { border-left-color: #ef4444; }
        
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100px;
            height: 200px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.05), transparent);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        
        .stat-card:hover::after {
            right: 150%;
        }
        
        .notification-badge {
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .progress-bar {
            height: 0.375rem;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        }
        
        .active-status {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08);
            z-index: 100;
            padding: 0.5rem 0;
        }
        
        .nav-active {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
            border-left: 3px solid #60a5fa;
        }
        
        .nav-active i {
            color: white !important;
        }
        
        .mobile-nav-active {
            color: #3b82f6;
            position: relative;
        }
        
        .mobile-nav-active::after {
            content: '';
            position: absolute;
            bottom: -0.25rem;
            left: 50%;
            transform: translateX(-50%);
            width: 0.375rem;
            height: 0.375rem;
            background: #3b82f6;
            border-radius: 50%;
        }
        
        .sidebar-link {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            margin: 0.125rem 0.5rem;
            border-radius: 0.5rem;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.08);
            border-left-color: #60a5fa;
        }
        
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.12);
            border-left-color: #3b82f6;
        }
        
        .urgent {
            border-left: 3px solid #ef4444;
            background: #fef2f2;
        }
        
        .warning {
            border-left: 3px solid #f59e0b;
            background: #fffbeb;
        }
        
        .success {
            border-left: 3px solid #10b981;
            background: #ecfdf5;
        }
        
        /* Profile picture update animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .shadow-subtle {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Desktop Sidebar -->
    <div class="sidebar hidden md:flex md:flex-col md:w-64 h-screen fixed">
        <!-- Logo -->
        <div class="p-5 border-b border-blue-800/30">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 flex items-center justify-center">
                    <img src="images/10213.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white">LEIR System</h1>
                    <p class="text-xs text-blue-200/80">Citizen Portal</p>
                </div>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="p-5 border-b border-blue-800/30">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <?php 
                    $profile_pic_path = "uploads/profile_pictures/" . ($_SESSION['profile_picture'] ?? $profile_picture ?? '');
                    $full_path = __DIR__ . "/../" . $profile_pic_path;
                    $timestamp = file_exists($full_path) ? filemtime($full_path) : time();
                    if (!empty($_SESSION['profile_picture'] ?? $profile_picture) && file_exists($full_path)): 
                    ?>
                        <img id="sidebarProfileImage" src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                             alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md">
                    <?php else: ?>
                        <div id="sidebarProfileDefault" class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?>"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($full_name); ?></h3>
                    <p class="text-xs text-blue-200/80 truncate">Citizen</p>
                    <p class="text-xs text-blue-200/60 mt-1 truncate">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <?php echo htmlspecialchars($user_address ?? $barangay); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 p-4 overflow-y-auto">
            <ul class="space-y-1">
                <li>
                    <a href="?module=dashboard" class="sidebar-link flex items-center p-3 text-white hover:text-white <?php echo $current_module == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home mr-3 text-sm"></i>
                        <span class="text-sm font-medium">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="?module=new-report" class="sidebar-link flex items-center p-3 text-white hover:text-white <?php echo $current_module == 'new-report' ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle mr-3 text-sm"></i>
                        <span class="text-sm font-medium">New Report</span>
                        <?php if ($rate_limit_info && $rate_limit_info['count'] >= 5): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                                Limit
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=my-reports" class="sidebar-link flex items-center p-3 text-white hover:text-white <?php echo $current_module == 'my-reports' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt mr-3 text-sm"></i>
                        <span class="text-sm font-medium">My Reports</span>
                        <?php if ($report_counts['total'] > 0): ?>
                            <span class="ml-auto bg-blue-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                                <?php echo $report_counts['total']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=announcements" class="sidebar-link flex items-center p-3 text-white hover:text-white <?php echo $current_module == 'announcements' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn mr-3 text-sm"></i>
                        <span class="text-sm font-medium">Announcements</span>
                        <?php if (count($announcements) > 0): ?>
                            <span class="ml-auto bg-yellow-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                                <?php echo count($announcements); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=profile" class="sidebar-link flex items-center p-3 text-white hover:text-white <?php echo $current_module == 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user mr-3 text-sm"></i>
                        <span class="text-sm font-medium">Profile</span>
                    </a>
                </li>
                <li class="pt-4 mt-4 border-t border-blue-800/30">
                    <form method="POST" action="">
                        <button type="submit" name="toggle_status" class="w-full flex items-center p-3 rounded-lg <?php echo $is_active ? 'bg-green-500/20 text-green-300 hover:bg-green-500/30' : 'bg-gray-500/20 text-gray-300 hover:bg-gray-500/30'; ?> transition-colors">
                            <i class="fas fa-power-off mr-3 text-sm"></i>
                            <span class="text-sm font-medium flex-1 text-left">Status</span>
                            <span class="ml-auto">
                                <div class="relative">
                                    <div class="w-10 h-5 flex items-center <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?> rounded-full p-0.5 cursor-pointer transition-colors">
                                        <div class="bg-white w-4 h-4 rounded-full shadow transform <?php echo $is_active ? 'translate-x-5' : 'translate-x-0'; ?> transition-transform"></div>
                                    </div>
                                </div>
                            </span>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        
        <!-- Footer -->
        <div class="p-4 border-t border-blue-800/30 text-center">
            <p class="text-xs text-blue-300/70">
                <i class="fas fa-shield-alt mr-1"></i>
                Secure Reporting System
            </p>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="overlay md:hidden" id="mobileOverlay"></div>
    
    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-3">
                    <!-- Left: Mobile Menu Button -->
                    <div class="md:hidden">
                        <button id="mobileMenuButton" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Center: Page Title -->
                    <div class="flex-1 md:ml-0">
                        <h2 class="text-lg font-semibold text-gray-800" id="pageTitle"><?php echo getModuleTitle($current_module); ?></h2>
                        <p class="text-xs text-gray-600 hidden md:block mt-0.5">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</p>
                    </div>
                    
                    <!-- Right: User Dropdown -->
                    <div class="flex items-center space-x-3">
                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-bell text-lg"></i>
                            <?php if (count($announcements) > 0): ?>
                                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- User Menu -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none hover:bg-gray-100 p-1.5 rounded-lg transition-colors">
                                <?php 
                                $profile_pic_path = "uploads/profile_pictures/" . ($_SESSION['profile_picture'] ?? $profile_picture ?? '');
                                $full_path = __DIR__ . "/../" . $profile_pic_path;
                                $timestamp = file_exists($full_path) ? filemtime($full_path) : time();
                                if (!empty($_SESSION['profile_picture'] ?? $profile_picture) && file_exists($full_path)): 
                                ?>
                                    <img id="headerProfileImage" src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                                         alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 shadow-sm">
                                <?php else: ?>
                                    <div id="headerProfileDefault" class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold text-sm">
                                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="hidden md:inline text-gray-700 text-sm font-medium"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                            </button>
                            
                            <!-- User Dropdown -->
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 z-40">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <?php 
                                        if (!empty($_SESSION['profile_picture'] ?? $profile_picture) && file_exists($full_path)): 
                                        ?>
                                            <img id="dropdownProfileImage" src="<?php echo $profile_pic_path . '?t=' . $timestamp; ?>" 
                                                 alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-300">
                                        <?php else: ?>
                                            <div id="dropdownProfileDefault" class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-semibold">
                                                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($full_name); ?></p>
                                            <p class="text-xs text-gray-500">Citizen Account</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=dashboard" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-tachometer-alt mr-2 text-gray-500 text-sm"></i>
                                        Dashboard
                                    </a>
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user-cog mr-2 text-gray-500 text-sm"></i>
                                        Profile Settings
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="logout.php" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded">
                                        <i class="fas fa-sign-out-alt mr-2 text-sm"></i>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4 sm:p-5 lg:p-6">
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-4 animate-fade-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-sm"></i>
                        <div class="flex-1">
                            <p class="text-green-700 text-sm"><?php echo $_SESSION['success']; ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 animate-fade-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-sm"></i>
                        <div class="flex-1">
                            <p class="text-red-700 text-sm"><?php echo $_SESSION['error']; ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Load the appropriate module -->
            <?php
            $module_file = '';
            switch($current_module) {
                case 'dashboard':
                    ?>
                    <!-- Dashboard Module -->
                    <div id="dashboard-module">
                        <!-- Rate Limit Warning -->
                        <?php if ($rate_limit_info && $rate_limit_info['count'] >= 5): ?>
                            <div class="warning p-4 mb-5 rounded-lg border border-yellow-200 bg-yellow-50">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-lg mt-0.5 mr-3"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-yellow-800">Report Limit Reached</p>
                                        <p class="text-xs text-yellow-700 mt-1">You've submitted 5 reports in the last hour. Please wait until 
                                            <?php echo date('h:i A', strtotime($rate_limit_info['last_report'] . ' +1 hour')); ?> 
                                            to submit another report.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="card stat-card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wider">Total Reports</p>
                                        <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo $report_counts['total'] ?? 0; ?></h3>
                                    </div>
                                    <div class="module-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-xs text-gray-600">
                                        <span class="text-green-500 mr-1.5">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                        <span><?php echo $report_counts['resolved'] ?? 0; ?> resolved</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card stat-card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wider">Account Status</p>
                                        <h3 class="text-2xl font-bold <?php echo $is_active ? 'text-green-600' : 'text-gray-600'; ?> mt-1">
                                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                        </h3>
                                    </div>
                                    <div class="module-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-xs text-gray-600">
                                        <span class="<?php echo $is_active ? 'text-green-500' : 'text-gray-500'; ?> mr-1.5">
                                            <i class="fas fa-circle <?php echo $is_active ? 'active-status' : ''; ?>" style="font-size: 0.5rem;"></i>
                                        </span>
                                        <span><?php echo $is_active ? 'Visible to officials' : 'Hidden from officials'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card stat-card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wider">Pending Actions</p>
                                        <h3 class="text-2xl font-bold text-yellow-600 mt-1"><?php echo ($report_counts['pending'] ?? 0) + ($report_counts['verification'] ?? 0); ?></h3>
                                    </div>
                                    <div class="module-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-xs text-gray-600">
                                        <span class="text-yellow-500 mr-1.5">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </span>
                                        <span>Requires attention</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card stat-card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wider">Announcements</p>
                                        <h3 class="text-2xl font-bold text-blue-600 mt-1"><?php echo count($announcements); ?></h3>
                                    </div>
                                    <div class="module-icon">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-xs text-gray-600">
                                        <span class="text-blue-500 mr-1.5">
                                            <i class="fas fa-bell"></i>
                                        </span>
                                        <span>Community updates</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Reports & Announcements -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <!-- Recent Reports -->
                            <div class="card p-5">
                                <div class="flex items-center justify-between mb-5">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">Recent Reports</h3>
                                        <p class="text-xs text-gray-500 mt-0.5">Your latest incident reports</p>
                                    </div>
                                    <a href="?module=my-reports" class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center">
                                        View All
                                        <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                    </a>
                                </div>
                                
                                <?php if (count($recent_reports) > 0): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($recent_reports as $report): ?>
                                            <div class="report-card p-4 border border-gray-200 rounded-lg hover-lift report-<?php echo $report['jurisdiction'] == 'police' ? 'police' : 'barangay'; ?>">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($report['title']); ?></h4>
                                                        <div class="flex items-center mt-2 space-x-2">
                                                            <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600">
                                                                <?php echo htmlspecialchars($report['type_name']); ?>
                                                            </span>
                                                            <span class="status-badge status-<?php echo str_replace('_', '-', $report['status']); ?>">
                                                                <?php echo ucwords(str_replace('_', ' ', $report['status'])); ?>
                                                            </span>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-2">
                                                            <i class="far fa-calendar mr-1"></i>
                                                            <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                        </p>
                                                    </div>
                                                    <div class="ml-3 flex-shrink-0">
                                                        <?php if ($report['jurisdiction'] == 'police'): ?>
                                                            <span class="text-red-500 text-xs font-medium flex items-center">
                                                                <i class="fas fa-shield-alt mr-1"></i> Police
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-blue-500 text-xs font-medium flex items-center">
                                                                <i class="fas fa-home mr-1"></i> Barangay
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-6">
                                        <div class="w-12 h-12 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                            <i class="fas fa-file-alt text-gray-400 text-lg"></i>
                                        </div>
                                        <p class="text-sm text-gray-500 mb-3">No reports submitted yet</p>
                                        <a href="?module=new-report" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-plus mr-2 text-xs"></i>
                                            Create First Report
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Latest Announcements -->
                            <div class="card p-5">
                                <div class="flex items-center justify-between mb-5">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">Latest Announcements</h3>
                                        <p class="text-xs text-gray-500 mt-0.5">Important community updates</p>
                                    </div>
                                    <a href="?module=announcements" class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center">
                                        View All
                                        <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                    </a>
                                </div>
                                
                                <?php if (count($announcements) > 0): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="p-4 border border-gray-200 rounded-lg hover-lift">
                                                <div class="flex items-start">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-9 h-9 rounded-lg <?php echo $announcement['priority'] == 'high' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'; ?> flex items-center justify-center">
                                                            <i class="fas fa-bullhorn text-sm"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-3 flex-1 min-w-0">
                                                        <h4 class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                        <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 120)); ?>...</p>
                                                        <div class="flex items-center mt-2 text-xs text-gray-500">
                                                            <span class="flex items-center">
                                                                <i class="far fa-calendar mr-1"></i>
                                                                <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                            </span>
                                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                                <span class="ml-3 px-2 py-0.5 bg-red-100 text-red-600 rounded-full text-xs font-medium">
                                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Urgent
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-6">
                                        <div class="w-12 h-12 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                            <i class="fas fa-bullhorn text-gray-400 text-lg"></i>
                                        </div>
                                        <p class="text-sm text-gray-500">No announcements yet</p>
                                        <p class="text-xs text-gray-400 mt-1">Check back later for updates</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    break;
                    
                case 'new-report':
                    $module_file = 'modules/citizen_new_report.php';
                    break;
                    
                case 'my-reports':
                    $module_file = 'modules/citizen_my_reports.php';
                    break;
                    
                case 'announcements':
                    $module_file = 'modules/citizen_announcements.php';
                    break;
                    
                case 'profile':
                    $module_file = 'modules/citizen_profile.php';
                    break;
                                    
                default:
                    // Redirect to dashboard if module doesn't exist
                    header("Location: ?module=dashboard");
                    exit;
            }
            
            // Load module file if not dashboard
            if ($module_file && file_exists($module_file)) {
                include $module_file;
            }
            ?>
        </main>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav md:hidden">
        <div class="flex justify-around items-center">
            <a href="?module=dashboard" class="flex flex-col items-center py-2 px-3 text-gray-600 <?php echo $current_module == 'dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-home text-lg"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            
            <a href="?module=new-report" class="flex flex-col items-center py-2 px-3 text-gray-600 <?php echo $current_module == 'new-report' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-plus-circle text-lg"></i>
                <span class="text-xs mt-1">Report</span>
            </a>
            
            <a href="?module=my-reports" class="flex flex-col items-center py-2 px-3 text-gray-600 <?php echo $current_module == 'my-reports' ? 'mobile-nav-active' : ''; ?> relative">
                <i class="fas fa-file-alt text-lg"></i>
                <span class="text-xs mt-1">Reports</span>
                <?php if ($report_counts['total'] > 0): ?>
                    <span class="absolute top-1 right-3 bg-blue-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center" style="font-size: 0.6rem;">
                        <?php echo min($report_counts['total'], 9); ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="?module=announcements" class="flex flex-col items-center py-2 px-3 text-gray-600 <?php echo $current_module == 'announcements' ? 'mobile-nav-active' : ''; ?> relative">
                <i class="fas fa-bullhorn text-lg"></i>
                <span class="text-xs mt-1">News</span>
                <?php if (count($announcements) > 0): ?>
                    <span class="absolute top-1 right-3 bg-red-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center" style="font-size: 0.6rem;">
                        <?php echo min(count($announcements), 9); ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="?module=profile" class="flex flex-col items-center py-2 px-3 text-gray-600 <?php echo $current_module == 'profile' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-user text-lg"></i>
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
        document.addEventListener('click', function(e) {
            if (userDropdown && !userDropdown.contains(e.target) && !userMenuButton.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.3s, transform 0.3s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            });
        }, 5000);
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Function to update profile images across the dashboard
        function updateProfileImages(imageUrl) {
            console.log('Updating profile images with:', imageUrl);
            
            const timestamp = new Date().getTime();
            const imageUrlWithTimestamp = imageUrl + '?t=' + timestamp;
            
            // Update sidebar image
            const sidebarImg = document.getElementById('sidebarProfileImage');
            const sidebarDefault = document.getElementById('sidebarProfileDefault');
            if (sidebarImg) {
                sidebarImg.src = imageUrlWithTimestamp;
                sidebarImg.style.display = 'block';
                sidebarImg.classList.add('animate-fade-in');
            }
            if (sidebarDefault) {
                sidebarDefault.style.display = 'none';
            }
            
            // Update header image
            const headerImg = document.getElementById('headerProfileImage');
            const headerDefault = document.getElementById('headerProfileDefault');
            if (headerImg) {
                headerImg.src = imageUrlWithTimestamp;
                headerImg.style.display = 'block';
                headerImg.classList.add('animate-fade-in');
            }
            if (headerDefault) {
                headerDefault.style.display = 'none';
            }
            
            // Update dropdown image
            const dropdownImg = document.getElementById('dropdownProfileImage');
            const dropdownDefault = document.getElementById('dropdownProfileDefault');
            if (dropdownImg) {
                dropdownImg.src = imageUrlWithTimestamp;
                dropdownImg.style.display = 'block';
                dropdownImg.classList.add('animate-fade-in');
            }
            if (dropdownDefault) {
                dropdownDefault.style.display = 'none';
            }
            
            // Also update the profile module if it's loaded
            const profileModule = document.querySelector('#profile-module img[id*="ProfileImage"]');
            if (profileModule) {
                profileModule.src = imageUrlWithTimestamp;
                profileModule.classList.add('animate-fade-in');
            }
        }
        
        // Listen for profile picture updates from the profile module
        document.addEventListener('profilePictureUpdated', function(e) {
            console.log('Received profilePictureUpdated event:', e.detail);
            if (e.detail && e.detail.imageUrl) {
                updateProfileImages(e.detail.imageUrl);
            }
        });
        
        // Listen for profile picture updates from iframe/message
        window.addEventListener('message', function(event) {
            console.log('Received message event:', event.data);
            if (event.data && event.data.type === 'profilePictureUpdated' && event.data.imageUrl) {
                updateProfileImages(event.data.imageUrl);
            }
        });
        
        // Handle iframe content updates
        document.addEventListener('DOMContentLoaded', function() {
            // Set up MutationObserver to detect when profile module is loaded
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                        // Check if profile module was added
                        const profileElements = document.querySelectorAll('[id*="profile"]');
                        profileElements.forEach(el => {
                            if (el.id.includes('ProfileImage')) {
                                console.log('Profile image element detected:', el.id);
                            }
                        });
                    }
                });
            });
            
            // Start observing the main content area
            const mainContent = document.querySelector('main');
            if (mainContent) {
                observer.observe(mainContent, { childList: true, subtree: true });
            }
        });
        
        // Global event for profile picture updates
        window.updateUserProfilePicture = function(imageUrl) {
            updateProfileImages(imageUrl);
        };
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn = null;
}
?>