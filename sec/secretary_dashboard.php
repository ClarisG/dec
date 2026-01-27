<?php
// sec/secretary_dashboard.php - UPDATED VERSION WITH LEIR LOGO
session_start();

// Check if user is logged in and is secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

// Get secretary information
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
$valid_modules = ['dashboard', 'case', 'compliance', 'referral', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle actions based on module
if ($module == 'case' && isset($_POST['assign_case'])) {
    // Handle case assignment
    $case_id = $_POST['case_id'];
    $lupon_member = $_POST['lupon_member'];
    
    try {
        $stmt = $conn->prepare("UPDATE reports SET assigned_lupon = :lupon, status = 'processing', assigned_at = NOW() WHERE id = :id");
        $stmt->execute([':lupon' => $lupon_member, ':id' => $case_id]);
        
        $_SESSION['success'] = "Case #$case_id assigned to $lupon_member successfully!";
        header("Location: secretary_dashboard.php?module=case");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to assign case: " . $e->getMessage();
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
    // Pending cases
    $pending_query = "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->execute();
    $stats['pending_cases'] = $pending_stmt->fetchColumn();
    
    // Approaching deadline (cases filed > 12 days ago)
    $deadline_query = "SELECT COUNT(*) as count FROM reports WHERE status IN ('pending', 'assigned', 'investigating') 
                      AND DATEDIFF(NOW(), created_at) >= 12";
    $deadline_stmt = $conn->prepare($deadline_query);
    $deadline_stmt->execute();
    $stats['approaching_deadline'] = $deadline_stmt->fetchColumn();
    
    // Total reports
    $total_reports_query = "SELECT COUNT(*) as count FROM reports";
    $total_reports_stmt = $conn->prepare($total_reports_query);
    $total_reports_stmt->execute();
    $stats['total_reports'] = $total_reports_stmt->fetchColumn();
    
    // Recent announcements
    $announce_query = "SELECT * FROM announcements 
                      WHERE (target_role = 'secretary' OR target_role = 'all')
                      AND is_active = 1
                      ORDER BY created_at DESC 
                      LIMIT 5";
    $announce_stmt = $conn->prepare($announce_query);
    $announce_stmt->execute();
    $announcements = $announce_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent reports for secretary
    $recent_reports_query = "SELECT r.*, u.first_name, u.last_name 
                            FROM reports r 
                            LEFT JOIN users u ON r.user_id = u.id 
                            ORDER BY r.created_at DESC 
                            LIMIT 5";
    $recent_stmt = $conn->prepare($recent_reports_query);
    $recent_stmt->execute();
    $recent_stmt->execute();
    $recent_reports = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    $recent_stmt->closeCursor();
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Dashboard Overview',
        'case' => 'Case & Blotter Management',
        'compliance' => 'Document Generation',
        'referral' => 'External Referral Desk',
        'profile' => 'Profile Account'
    ];
    return $titles[$module] ?? 'Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'Overview of all secretary functions and quick actions',
        'case' => 'Manage cases, assign blotter numbers, and track case progress',
        'compliance' => 'Generate legal documents and forms for barangay proceedings',
        'referral' => 'Handle VAWC, minor cases, and external agency referrals',
        'profile' => 'Manage your account information and activity log'
    ];
    return $subtitles[$module] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - <?php echo getModuleTitle($module); ?></title>
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
        
        .badge-referred {
            background-color: #f3e8ff;
            color: #5b21b6;
        }
        
        .badge-vawc {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-minor {
            background-color: #fef3c7;
            color: #92400e;
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
        
        .status-pending { background: #fed7d7; color: #9b2c2c; }
        .status-submitted { background: #bee3f8; color: #2c5282; }
        .status-investigating { background: #fef3c7; color: #92400e; }
        .status-resolved { background: #c6f6d5; color: #065f46; }
        .status-referred { background: #c6f6d5; color: #065f46; }
        .status-closed { background: #e2e8f0; color: #4a5568; }
        
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
        
        .module-1 { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .module-2 { background: linear-gradient(135deg, #4299e1, #3182ce); color: white; }
        .module-3 { background: linear-gradient(135deg, #38a169, #2f855a); color: white; }
        .module-4 { background: linear-gradient(135deg, #d69e2e, #b7791f); color: white; }
        
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
                    <p class="text-blue-200 text-sm">Secretary System</p>
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
                        <p class="text-blue-200 text-sm">Secretary</p>
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
                <a href="?module=case" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'case' ? 'active' : ''; ?>">
                    <i class="fas fa-gavel mr-3"></i>
                    Case-Blotter Management
                    <?php if (isset($stats['pending_cases']) && $stats['pending_cases'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['pending_cases'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=compliance" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'compliance' ? 'active' : ''; ?>">
                    <i class="fas fa-file-pdf mr-3"></i>
                    Document Generation
                </a>
                <a href="?module=referral" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'referral' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    External Referral Desk
                </a>
            </nav>
            
            <!-- Status Toggle -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="mb-4">
                    <button class="w-full flex items-center p-3 rounded-lg <?php echo $is_active ? 'bg-green-500/20 text-green-300 hover:bg-green-500/30' : 'bg-red-500/20 text-red-300 hover:bg-red-500/30'; ?> transition-colors">
                        <i class="fas fa-power-off mr-3"></i>
                        <span class="font-medium flex-1 text-left">Status: <?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                        <div class="relative">
                            <div class="w-10 h-6 flex items-center <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?> rounded-full p-1 cursor-pointer transition-colors">
                                <div class="bg-white w-4 h-4 rounded-full shadow-md transform <?php echo $is_active ? 'translate-x-4' : 'translate-x-0'; ?> transition-transform"></div>
                            </div>
                        </div>
                    </button>
                </div>
                
                <a href="../logout.php" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
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
                                    <p class="text-xs text-gray-500">Secretary</p>
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
                                            <p class="text-xs text-gray-500">Secretary</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="?module=profile" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-user mr-2"></i>
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
        $module_file = "modules/{$module}.php";
        if (file_exists($module_file)) {
            include $module_file;
        } else {
            echo "<div class='bg-white rounded-xl p-6'>";
            echo "<h2 class='text-xl font-bold text-gray-800 mb-4'>Module Not Found</h2>";
            echo "<p class='text-gray-600'>The requested module is not available.</p>";
            echo "</div>";
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
            
            <a href="?module=case" class="flex flex-col items-center text-gray-600 <?php echo $module == 'case' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-gavel text-xl"></i>
                <span class="text-xs mt-1">Cases</span>
                <?php if (isset($stats['pending_cases']) && $stats['pending_cases'] > 0): ?>
                    <span class="mobile-nav-badge"><?php echo min($stats['pending_cases'], 9); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?module=compliance" class="flex flex-col items-center text-gray-600 <?php echo $module == 'compliance' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-file-pdf text-xl"></i>
                <span class="text-xs mt-1">Docs</span>
            </a>
            
            <a href="?module=referral" class="flex flex-col items-center text-gray-600 <?php echo $module == 'referral' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-exchange-alt text-xl"></i>
                <span class="text-xs mt-1">Referral</span>
            </a>
            
            <a href="?module=profile" class="flex flex-col items-center text-gray-600 <?php echo $module == 'profile' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-user text-xl"></i>
                <span class="text-xs mt-1">Profile</span>
            </a>
        </div>
    </div>

    <!-- Assignment Modal (for case module) - REMOVED (Handled by case.php) -->

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

        // Assignment Modal Functions - REMOVED (Handled by case.php)
        
        // Auto-refresh for compliance monitoring
        if (window.location.search.includes('module=compliance')) {
            setInterval(() => {
                // In real implementation, this would fetch updated data
                console.log('Refreshing compliance data...');
            }, 30000); // 30 seconds
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
