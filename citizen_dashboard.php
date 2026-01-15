<?php
// citizen_dashboard.php - UPDATED HEADER
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
        'announcements' => 'Barangay Announcements',
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/10213.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #38a169;
            --warning: #d69e2e;
            --danger: #e53e3e;
            --info: #4299e1;
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
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
        }
        
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fed7d7; color: #9b2c2c; }
        .status-submitted { background: #bee3f8; color: #2c5282; }
        .status-verification { background: #fed7d7; color: #9b2c2c; }
        .status-mediation { background: #fefcbf; color: #744210; }
        .status-referred { background: #c6f6d5; color: #276749; }
        .status-resolved { background: #c6f6d5; color: #276749; }
        .status-closed { background: #e2e8f0; color: #4a5568; }
        
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
        
        .module-1 { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .module-2 { background: linear-gradient(135deg, #4299e1, #3182ce); color: white; }
        .module-3 { background: linear-gradient(135deg, #38a169, #2f855a); color: white; }
        .module-4 { background: linear-gradient(135deg, #d69e2e, #b7791f); color: white; }
        
        .report-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateX(5px);
        }
        
        .report-barangay { border-left-color: #667eea; }
        .report-police { border-left-color: #e53e3e; }
        
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
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        
        .stat-card:hover::after {
            right: 150%;
        }
        
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
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        
        .nav-active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white !important;
        }
        
        .nav-active i {
            color: white !important;
        }
        
        .mobile-nav-active {
            color: #667eea;
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
            background: #667eea;
            border-radius: 50%;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Desktop Sidebar -->
    <div class="sidebar hidden md:flex md:flex-col md:w-64 bg-white h-screen shadow-lg fixed">
        <!-- Logo -->
        <div class="p-6 border-b">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 flex items-center justify-center">
                    <img src="images/10213.png" alt="Logo" class="w-19 h-22 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">LEIR</h1>
                    <p class="text-xs text-gray-500">Citizen Dashboard</p>
                </div>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="p-6 border-b">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <?php 
                    $profile_pic_path = "../uploads/profile_pictures/" . ($profile_picture ?? '');
                    if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                    ?>
                        <img src="<?php echo $profile_pic_path; ?>" 
                             alt="Profile" class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-center text-white font-bold text-lg">
                            <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 w-4 h-4 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($full_name); ?></h3>
                    <p class="text-sm text-gray-500">Citizen</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-600 flex items-center">
                    <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                    <?php echo htmlspecialchars($user_address ?? $barangay); ?>
                </p>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 p-4 overflow-y-auto">
            <ul class="space-y-2">
                <li>
                    <a href="?module=dashboard" class="nav-link flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors <?php echo $current_module == 'dashboard' ? 'nav-active' : ''; ?>">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="?module=new-report" class="nav-link flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors <?php echo $current_module == 'new-report' ? 'nav-active' : ''; ?>">
                        <i class="fas fa-plus-circle mr-3 text-lg"></i>
                        <span class="font-medium">New Report</span>
                        <?php if ($rate_limit_info && $rate_limit_info['count'] >= 5): ?>
                            <span class="ml-auto bg-red-100 text-red-600 text-xs font-semibold px-2 py-1 rounded-full">
                                <i class="fas fa-clock mr-1"></i> Limit
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=my-reports" class="nav-link flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors <?php echo $current_module == 'my-reports' ? 'nav-active' : ''; ?>">
                        <i class="fas fa-file-alt mr-3 text-lg"></i>
                        <span class="font-medium">My Reports</span>
                        <?php if ($report_counts['total'] > 0): ?>
                            <span class="ml-auto bg-purple-100 text-purple-600 text-xs font-semibold px-2 py-1 rounded-full">
                                <?php echo $report_counts['total']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=announcements" class="nav-link flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors <?php echo $current_module == 'announcements' ? 'nav-active' : ''; ?>">
                        <i class="fas fa-bullhorn mr-3 text-lg"></i>
                        <span class="font-medium">Announcements</span>
                        <?php if (count($announcements) > 0): ?>
                            <span class="ml-auto bg-red-100 text-red-600 text-xs font-semibold px-2 py-1 rounded-full">
                                <?php echo count($announcements); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="?module=profile" class="nav-link flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors <?php echo $current_module == 'profile' ? 'nav-active' : ''; ?>">
                        <i class="fas fa-user mr-3 text-lg"></i>
                        <span class="font-medium">Profile</span>
                    </a>
                </li>
                <li class="pt-4 border-t">
                    <form method="POST" action="">
                        <button type="submit" name="toggle_status" class="w-full flex items-center p-3 rounded-lg <?php echo $is_active ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-red-50 text-red-700 hover:bg-red-100'; ?> transition-colors">
                            <i class="fas fa-power-off mr-3 text-lg"></i>
                            <span class="font-medium">Status: <?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                            <span class="ml-auto">
                                <div class="relative">
                                    <div class="w-10 h-6 flex items-center <?php echo $is_active ? 'bg-green-500' : 'bg-gray-300'; ?> rounded-full p-1 cursor-pointer transition-colors">
                                        <div class="bg-white w-4 h-4 rounded-full shadow-md transform <?php echo $is_active ? 'translate-x-4' : 'translate-x-0'; ?> transition-transform"></div>
                                    </div>
                                </div>
                            </span>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        
        <!-- Footer -->
        <div class="p-4 border-t text-center">
            <p class="text-xs text-gray-500">
                <i class="fas fa-shield-alt mr-1"></i>
                Secure Incident Reporting
            </p>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="overlay md:hidden" id="mobileOverlay"></div>
    
    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-30">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <!-- Left: Mobile Menu Button -->
                    <div class="md:hidden">
                        <button id="mobileMenuButton" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                            <i class="fas fa-bars text-2xl"></i>
                        </button>
                    </div>
                    
                    <!-- Center: Page Title -->
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-xl font-bold text-gray-800" id="pageTitle"><?php echo getModuleTitle($current_module); ?></h2>
                        <p class="text-sm text-gray-600 hidden md:block">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</p>
                    </div>
                    
                    <!-- Right: User Dropdown -->
                    <div class="flex items-center space-x-4">
                        <!-- User Menu -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                                <?php 
                                $profile_pic_path = "../uploads/profile_pictures/" . ($profile_picture ?? '');
                                if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                                ?>
                                    <img src="<?php echo $profile_pic_path; ?>" 
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="hidden md:inline text-gray-700 font-medium"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </button>
                            
                            <!-- User Dropdown -->
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border z-40">
                                <div class="p-4 border-b">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <?php if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                            <img src="<?php echo $profile_pic_path; ?>" 
                                                 alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($full_name); ?></p>
                                            <p class="text-xs text-gray-500">Citizen</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user mr-2"></i>
                                        Profile Settings
                                    </a>
                                    <a href="logout.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
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
        
        <!-- Main Content Area -->
        <main class="p-4 sm:p-6 lg:p-8">
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <div class="flex-1">
                            <p class="text-green-700"><?php echo $_SESSION['success']; ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <div class="flex-1">
                            <p class="text-red-700"><?php echo $_SESSION['error']; ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
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
                    // Show dashboard content directly
                    ?>
                    <!-- Dashboard Module -->
                    <div id="dashboard-module">
    
                        
                        <!-- Rate Limit Warning -->
                        <?php if ($rate_limit_info && $rate_limit_info['count'] >= 5): ?>
                            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-r-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
                                    <div class="flex-1">
                                        <p class="text-yellow-700 font-medium">Report Limit Reached</p>
                                        <p class="text-sm text-yellow-600">You've submitted 5 reports in the last hour. Please wait until 
                                            <?php echo date('h:i A', strtotime($rate_limit_info['last_report'] . ' +1 hour')); ?> 
                                            to submit another report.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">Total Reports</p>
                                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $report_counts['total'] ?? 0; ?></h3>
                                    </div>
                                    <div class="module-icon module-1">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm">
                                        <span class="text-green-500 mr-2">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                        <span class="text-gray-600"><?php echo $report_counts['resolved'] ?? 0; ?> resolved</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">Active Status</p>
                                        <h3 class="text-2xl font-bold <?php echo $is_active ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                        </h3>
                                    </div>
                                    <div class="module-icon module-4">
                                        <i class="fas fa-power-off"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm">
                                        <span class="<?php echo $is_active ? 'text-green-500' : 'text-red-500'; ?> mr-2">
                                            <i class="fas fa-circle <?php echo $is_active ? 'active-status' : ''; ?>"></i>
                                        </span>
                                        <span class="text-gray-600"><?php echo $is_active ? 'You are visible to officials' : 'You are hidden from officials'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">Pending Actions</p>
                                        <h3 class="text-2xl font-bold text-yellow-600"><?php echo ($report_counts['pending'] ?? 0) + ($report_counts['verification'] ?? 0); ?></h3>
                                    </div>
                                    <div class="module-icon module-2">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm">
                                        <span class="text-yellow-500 mr-2">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </span>
                                        <span class="text-gray-600">Requires your attention</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">New Announcements</p>
                                        <h3 class="text-2xl font-bold text-purple-600"><?php echo count($announcements); ?></h3>
                                    </div>
                                    <div class="module-icon module-3">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm">
                                        <span class="text-purple-500 mr-2">
                                            <i class="fas fa-bell"></i>
                                        </span>
                                        <span class="text-gray-600">Latest community updates</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Reports & Announcements -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Recent Reports -->
                            <div class="bg-white rounded-xl shadow-sm border p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-semibold text-gray-800">Recent Reports</h3>
                                    <a href="?module=my-reports" class="nav-link text-sm text-purple-600 hover:text-purple-700 font-medium">
                                        View All <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                                
                                <?php if (count($recent_reports) > 0): ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recent_reports as $report): ?>
                                            <div class="report-card p-4 border rounded-lg hover:shadow-md transition-shadow report-<?php echo $report['jurisdiction'] == 'police' ? 'police' : 'barangay'; ?>">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($report['title']); ?></h4>
                                                        <div class="flex items-center mt-2 space-x-4">
                                                            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                                                                <?php echo htmlspecialchars($report['type_name']); ?>
                                                            </span>
                                                            <span class="status-badge status-<?php echo str_replace('_', '-', $report['status']); ?>">
                                                                <?php echo ucwords(str_replace('_', ' ', $report['status'])); ?>
                                                            </span>
                                                        </div>
                                                        <p class="text-sm text-gray-500 mt-2">
                                                            <i class="far fa-calendar mr-1"></i>
                                                            <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                        </p>
                                                    </div>
                                                    <div class="ml-4">
                                                        <?php if ($report['jurisdiction'] == 'police'): ?>
                                                            <span class="text-red-500 text-sm font-medium">
                                                                <i class="fas fa-shield-alt"></i> Police
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-blue-500 text-sm font-medium">
                                                                <i class="fas fa-home"></i> Barangay
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                                        </div>
                                        <p class="text-gray-500 mb-3">No reports submitted yet</p>
                                        <a href="?module=new-report" class="nav-link inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                            <i class="fas fa-plus mr-2"></i>
                                            Create Your First Report
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Latest Announcements -->
                            <div class="bg-white rounded-xl shadow-sm border p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-semibold text-gray-800">Latest Announcements</h3>
                                    <a href="?module=announcements" class="nav-link text-sm text-purple-600 hover:text-purple-700 font-medium">
                                        View All <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                                
                                <?php if (count($announcements) > 0): ?>
                                    <div class="space-y-4">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                                                <div class="flex items-start">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                                            <i class="fas fa-bullhorn text-purple-600"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4 flex-1">
                                                        <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>...</p>
                                                        <div class="flex items-center mt-2 text-xs text-gray-500">
                                                            <span class="flex items-center">
                                                                <i class="far fa-calendar mr-1"></i>
                                                                <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                            </span>
                                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                                <span class="ml-4 px-2 py-1 bg-red-100 text-red-600 rounded-full text-xs font-medium">
                                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Important
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-bullhorn text-gray-400 text-2xl"></i>
                                        </div>
                                        <p class="text-gray-500">No announcements yet</p>
                                        <p class="text-sm text-gray-400 mt-1">Check back later for updates</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <a href="?module=new-report" class="nav-link group">
                                <div class="bg-white rounded-xl p-6 border hover:border-purple-500 hover:shadow-lg transition-all">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-center text-white mr-4">
                                            <i class="fas fa-plus text-xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 group-hover:text-purple-600">New Report</h4>
                                            <p class="text-sm text-gray-500">File a new incident report</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?module=my-reports" class="nav-link group">
                                <div class="bg-white rounded-xl p-6 border hover:border-blue-500 hover:shadow-lg transition-all">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-600 to-cyan-500 flex items-center justify-center text-white mr-4">
                                            <i class="fas fa-file-alt text-xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 group-hover:text-blue-600">Track Reports</h4>
                                            <p class="text-sm text-gray-500">Monitor your submissions</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?module=profile" class="nav-link group">
                                <div class="bg-white rounded-xl p-6 border hover:border-green-500 hover:shadow-lg transition-all">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-green-600 to-teal-500 flex items-center justify-center text-white mr-4">
                                            <i class="fas fa-user-cog text-xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 group-hover:text-green-600">Update Profile</h4>
                                            <p class="text-sm text-gray-500">Manage your information</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
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
        <div class="flex justify-around items-center py-3">
            <a href="?module=dashboard" class="flex flex-col items-center text-gray-600 <?php echo $current_module == 'dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            
            <a href="?module=new-report" class="flex flex-col items-center text-gray-600 <?php echo $current_module == 'new-report' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-plus-circle text-xl"></i>
                <span class="text-xs mt-1">New</span>
            </a>
            
            <a href="?module=my-reports" class="flex flex-col items-center text-gray-600 <?php echo $current_module == 'my-reports' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-file-alt text-xl"></i>
                <span class="text-xs mt-1">Reports</span>
                <?php if ($report_counts['total'] > 0): ?>
                    <span class="absolute mt-[-5px] ml-6 bg-purple-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo min($report_counts['total'], 9); ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="?module=announcements" class="flex flex-col items-center text-gray-600 <?php echo $current_module == 'announcements' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-bullhorn text-xl"></i>
                <span class="text-xs mt-1">News</span>
                <?php if (count($announcements) > 0): ?>
                    <span class="absolute mt-[-5px] ml-6 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo min(count($announcements), 9); ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="?module=profile" class="flex flex-col items-center text-gray-600 <?php echo $current_module == 'profile' ? 'mobile-nav-active' : ''; ?>">
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
$conn = null;
?>