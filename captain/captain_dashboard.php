<?php
// captain/captain_dashboard.php
session_start();

// Check if user is logged in and is captain
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    header("Location: ../login.php");
    exit;
}

// Include database configuration
require_once '../config/database.php';

// Get captain information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$barangay = $_SESSION['barangay'] ?? 'Barangay Office';

// Database connection
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle module switching
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$valid_modules = ['dashboard', 'review', 'hearing', 'profile'];
if (!in_array($module, $valid_modules)) {
    $module = 'dashboard';
}

// Handle hearing scheduling
if ($module == 'hearing' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['schedule_hearing'])) {
        $case_id = $_POST['case_id'];
        $hearing_date = $_POST['hearing_date'];
        $hearing_time = $_POST['hearing_time'];
        $location = $_POST['location'];
        $participants = $_POST['participants'];
        $notes = $_POST['notes'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO captain_hearings (report_id, hearing_date, hearing_time, location, participants, notes, scheduled_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
            $stmt->execute([$case_id, $hearing_date, $hearing_time, $location, $participants, $notes, $user_id]);
            
            // Update case status
            $update_stmt = $conn->prepare("UPDATE reports SET status = 'hearing_scheduled' WHERE id = ?");
            $update_stmt->execute([$case_id]);
            
            $_SESSION['success'] = "Hearing scheduled successfully! Reminders will be sent to all participants.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to schedule hearing: " . $e->getMessage();
        }
    }
}

// Handle case review/approval
if ($module == 'review' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_case'])) {
        $case_id = $_POST['case_id'];
        $resolution_type = $_POST['resolution_type'];
        $resolution_notes = $_POST['resolution_notes'];
        $digital_signature = $_POST['digital_signature'];
        
        try {
            $stmt = $conn->prepare("UPDATE reports SET status = 'approved', resolution = ?, resolution_date = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$resolution_notes, $user_id, $case_id]);
            
            // Log approval
            $log_stmt = $conn->prepare("INSERT INTO case_approvals (report_id, approved_by, resolution_type, digital_signature, approved_at) VALUES (?, ?, ?, ?, NOW())");
            $log_stmt->execute([$case_id, $user_id, $resolution_type, $digital_signature]);
            
            $_SESSION['success'] = "Case #$case_id approved successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to approve case: " . $e->getMessage();
        }
    } elseif (isset($_POST['reject_case'])) {
        $case_id = $_POST['case_id'];
        $rejection_reason = $_POST['rejection_reason'];
        
        try {
            $stmt = $conn->prepare("UPDATE reports SET status = 'rejected', verification_notes = ? WHERE id = ?");
            $stmt->execute([$rejection_reason, $user_id, $case_id]);
            
            $_SESSION['success'] = "Case #$case_id rejected.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Failed to reject case: " . $e->getMessage();
        }
    }
}

// Get user data
$user_query = "SELECT u.*, u.profile_picture, u.is_active, u.contact_number, u.email 
               FROM users u 
               WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    $is_active = $user_data['is_active'] ?? 1;
    $profile_picture = $user_data['profile_picture'] ?? '';
    $contact_number = $user_data['contact_number'] ?? '';
    $email = $user_data['email'] ?? '';
}

// Get dashboard statistics
if ($module == 'dashboard') {
    // Total open cases
    $open_cases_query = "SELECT COUNT(*) as count FROM reports WHERE status NOT IN ('closed', 'resolved', 'approved')";
    $open_cases_stmt = $conn->prepare($open_cases_query);
    $open_cases_stmt->execute();
    $stats['open_cases'] = $open_cases_stmt->fetchColumn();
    
    // Cases pending approval (3/15 day rule)
    $pending_approval_query = "SELECT COUNT(*) as count FROM reports 
                              WHERE status IN ('investigating', 'hearing_completed') 
                              AND (DATEDIFF(NOW(), created_at) >= 3 OR DATEDIFF(NOW(), last_status_change) >= 15)";
    $pending_approval_stmt = $conn->prepare($pending_approval_query);
    $pending_approval_stmt->execute();
    $stats['pending_approval'] = $pending_approval_stmt->fetchColumn();
    
    // Referred cases
    $referred_cases_query = "SELECT COUNT(*) as count FROM reports WHERE status = 'referred'";
    $referred_cases_stmt = $conn->prepare($referred_cases_query);
    $referred_cases_stmt->execute();
    $stats['referred_cases'] = $referred_cases_stmt->fetchColumn();
    
    // Today's hearings
    $todays_hearings_query = "SELECT COUNT(*) as count FROM captain_hearings WHERE DATE(hearing_date) = CURDATE() AND status = 'scheduled'";
    $todays_hearings_stmt = $conn->prepare($todays_hearings_query);
    $todays_hearings_stmt->execute();
    $stats['todays_hearings'] = $todays_hearings_stmt->fetchColumn();
    
    // Cases needing attention (priority)
    $attention_cases_query = "SELECT r.*, u.first_name, u.last_name 
                             FROM reports r 
                             LEFT JOIN users u ON r.user_id = u.id 
                             WHERE r.status IN ('pending', 'assigned', 'investigating') 
                             AND r.priority IN ('high', 'critical')
                             ORDER BY r.created_at DESC 
                             LIMIT 5";
    $attention_cases_stmt = $conn->prepare($attention_cases_query);
    $attention_cases_stmt->execute();
    $attention_cases = $attention_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Upcoming hearings
    $upcoming_hearings_query = "SELECT ch.*, r.report_number, r.title 
                               FROM captain_hearings ch 
                               JOIN reports r ON ch.report_id = r.id 
                               WHERE ch.hearing_date >= CURDATE() 
                               AND ch.status = 'scheduled'
                               ORDER BY ch.hearing_date ASC 
                               LIMIT 5";
    $upcoming_hearings_stmt = $conn->prepare($upcoming_hearings_query);
    $upcoming_hearings_stmt->execute();
    $upcoming_hearings = $upcoming_hearings_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get cases for review
if ($module == 'review') {
$review_cases_query = "SELECT r.*, u.first_name, u.last_name, u.contact_number,
                              TIMESTAMPDIFF(DAY, r.created_at, NOW()) as days_pending,
                              CONCAT(l.first_name, ' ', IFNULL(l.middle_name, ''), ' ', l.last_name, ' ', IFNULL(l.suffix, '')) as lupon_name
                       FROM reports r 
                       LEFT JOIN users u ON r.user_id = u.id 
                       LEFT JOIN users l ON r.assigned_to = l.id 
                       WHERE r.status IN ('investigating', 'hearing_completed') 
                       AND (r.assigned_to IS NOT NULL OR r.assigned_tanod IS NOT NULL)
                       ORDER BY r.priority DESC, r.created_at ASC";

                           
    $review_cases_stmt = $conn->prepare($review_cases_query);
    $review_cases_stmt->execute();
    $review_cases = $review_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get hearings for scheduling
if ($module == 'hearing') {
    $hearing_cases_query = "SELECT r.*, u.first_name, u.last_name, u.contact_number,
                                   TIMESTAMPDIFF(DAY, r.created_at, NOW()) as days_pending
                            FROM reports r 
                            LEFT JOIN users u ON r.user_id = u.id 
                            WHERE r.status IN ('assigned', 'investigating') 
                            AND r.category = 'blotter'
                            AND r.id NOT IN (SELECT report_id FROM captain_hearings WHERE status = 'scheduled')
                            ORDER BY r.created_at ASC";
    $hearing_cases_stmt = $conn->prepare($hearing_cases_query);
    $hearing_cases_stmt->execute();
    $hearing_cases = $hearing_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get scheduled hearings
    $scheduled_hearings_query = "SELECT ch.*, r.report_number, r.title, 
                                        u.first_name as complainant_fname, u.last_name as complainant_lname,
                                        u.contact_number as complainant_contact
                                 FROM captain_hearings ch 
                                 JOIN reports r ON ch.report_id = r.id 
                                 LEFT JOIN users u ON r.user_id = u.id 
                                 WHERE ch.status = 'scheduled'
                                 ORDER BY ch.hearing_date ASC, ch.hearing_time ASC";
    $scheduled_hearings_stmt = $conn->prepare($scheduled_hearings_query);
    $scheduled_hearings_stmt->execute();
    $scheduled_hearings = $scheduled_hearings_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get module title
function getModuleTitle($module) {
    $titles = [
        'dashboard' => 'Executive Dashboard',
        'review' => 'Final Case Review & Approval',
        'hearing' => 'Mediation & Hearing Scheduler',
        'profile' => 'Profile Account & Status'
    ];
    return $titles[$module] ?? 'Executive Dashboard';
}

// Function to get module subtitle
function getModuleSubtitle($module) {
    $subtitles = [
        'dashboard' => 'High-level KPIs reflecting overall efficiency and compliance',
        'review' => 'Final review and digital sign-off of case resolutions before closure',
        'hearing' => 'Manage calendar for conciliation hearings and send automated reminders',
        'profile' => 'Manage contact information and account status'
    ];
    return $subtitles[$module] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captain Dashboard - <?php echo getModuleTitle($module); ?></title>
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
            --primary-blue: #1e3a8a;
            --secondary-blue: #1e40af;
            --accent-blue: #3b82f6;
            --dark-blue: #0d47a1;
            --light-blue: #f0f9ff;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #0d47a1 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
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
        
        .kpi-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }
        
        .urgent {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
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
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .badge-high { background: #fef3c7; color: #92400e; }
        .badge-medium { background: #dbeafe; color: #1e40af; }
        .badge-low { background: #dcfce7; color: #166534; }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fed7d7; color: #9b2c2c; }
        .status-assigned { background: #bee3f8; color: #2c5282; }
        .status-investigating { background: #fef3c7; color: #92400e; }
        .status-hearing { background: #e9d5ff; color: #6b21a8; }
        .status-approved { background: #c6f6d5; color: #065f46; }
        .status-rejected { background: #fed7d7; color: #9b2c2c; }
        .status-referred { background: #c6f6d5; color: #065f46; }
        .status-closed { background: #e2e8f0; color: #4a5568; }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring__circle {
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s;
        }
        
        .calendar-day {
            transition: all 0.2s ease;
        }
        
        .calendar-day:hover {
            background: #3b82f6;
            color: white;
            transform: scale(1.05);
        }
        
        .calendar-day.today {
            background: #3b82f6;
            color: white;
        }
        
        .calendar-day.has-hearing {
            background: #10b981;
            color: white;
        }
        
        .signature-pad {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: crosshair;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
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
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                z-index: 100;
            }
            
            .mobile-nav-active {
                color: #3b82f6;
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
                background: #3b82f6;
                border-radius: 50%;
            }
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
                    <img src="../images/10213.png" alt="Logo" class="w-10 h-10 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">LEIR</h1>
                    <p class="text-blue-200 text-sm">Captain System</p>
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
                        <p class="text-blue-200 text-sm">Barangay Captain</p>
                    </div>
                </div>
                <div class="mt-3 ml-3">
                    <p class="text-sm text-blue-200 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-xs"></i>
                        <span class="truncate"><?php echo htmlspecialchars($barangay); ?></span>
                    </p>
                </div>
            </div>
            
            <nav class="space-y-2">
                <a href="?module=dashboard" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i>
                    Executive Dashboard
                </a>
                <a href="?module=review" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'review' ? 'active' : ''; ?>">
                    <i class="fas fa-check-double mr-3"></i>
                    Final Case Review
                    <?php if (isset($stats['pending_approval']) && $stats['pending_approval'] > 0): ?>
                        <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['pending_approval'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=hearing" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'hearing' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Hearing Scheduler
                    <?php if (isset($stats['todays_hearings']) && $stats['todays_hearings'] > 0): ?>
                        <span class="float-right bg-green-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                            <?php echo min($stats['todays_hearings'], 9); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="?module=profile" class="sidebar-link block p-3 text-white rounded-lg <?php echo $module == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog mr-3"></i>
                    Profile Account
                </a>
            </nav>
            
            <!-- Status & Logout -->
            <div class="mt-8 pt-8 border-t border-blue-400/30">
                <div class="mb-4">
                    <div class="w-full flex items-center p-3 rounded-lg <?php echo $is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?>">
                        <i class="fas fa-user-shield mr-3"></i>
                        <span class="font-medium flex-1 text-left">Status: <?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                        <div class="w-10 h-6 flex items-center <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?> rounded-full p-1 cursor-pointer transition-colors">
                            <div class="bg-white w-4 h-4 rounded-full shadow-md transform <?php echo $is_active ? 'translate-x-4' : 'translate-x-0'; ?>"></div>
                        </div>
                    </div>
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
        <header class="glass-card rounded-xl shadow-sm sticky top-0 z-30 mb-6">
            <div class="px-6 py-4">
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
                        <!-- Notifications Component -->
                        <?php include '../components/notification_button.php'; ?>
                        
                        <!-- Quick Stats -->
                        <div class="hidden lg:flex items-center space-x-4">
                            <div class="text-center px-3 py-2 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Open Cases</p>
                                <p class="text-lg font-bold text-blue-600"><?php echo $stats['open_cases'] ?? 0; ?></p>
                            </div>
                            <div class="text-center px-3 py-2 bg-yellow-50 rounded-lg">
                                <p class="text-sm text-gray-600">Pending Review</p>
                                <p class="text-lg font-bold text-yellow-600"><?php echo $stats['pending_approval'] ?? 0; ?></p>
                            </div>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                                <?php 
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
                                    <p class="text-xs text-gray-500">Captain</p>
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
                                            <p class="text-xs text-gray-500">Barangay Captain</p>
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
        <div class="mb-6 animate-slide-in">
            <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-6 animate-slide-in">
            <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Load Module Content -->
        <?php
        // Include module content
        if ($module == 'dashboard') {
            include 'modules/dashboard.php';
        } elseif ($module == 'review') {
            include 'modules/review.php';
        } elseif ($module == 'hearing') {
            include 'modules/hearing.php';
        } elseif ($module == 'profile') {
            include 'modules/profile.php';
        }
        ?>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav md:hidden">
        <div class="flex justify-around items-center py-3">
            <a href="?module=dashboard" class="flex flex-col items-center text-gray-600 <?php echo $module == 'dashboard' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-chart-line text-xl"></i>
                <span class="text-xs mt-1">Dashboard</span>
            </a>
            
            <a href="?module=review" class="flex flex-col items-center text-gray-600 <?php echo $module == 'review' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-check-double text-xl"></i>
                <span class="text-xs mt-1">Review</span>
                <?php if (isset($stats['pending_approval']) && $stats['pending_approval'] > 0): ?>
                    <span class="absolute -top-1 right-3 w-5 h-5 bg-red-500 rounded-full text-white text-xs flex items-center justify-center">
                        <?php echo min($stats['pending_approval'], 9); ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="?module=hearing" class="flex flex-col items-center text-gray-600 <?php echo $module == 'hearing' ? 'mobile-nav-active' : ''; ?>">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Hearings</span>
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

        // Toggle notifications
        function toggleNotifications() {
            // In a real implementation, this would show a notifications panel
            alert('Notifications feature would show here with pending reviews and upcoming hearings.');
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

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[title]');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function(e) {
                    const title = this.getAttribute('title');
                    if (title) {
                        const tooltipEl = document.createElement('div');
                        tooltipEl.className = 'fixed z-50 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg';
                        tooltipEl.textContent = title;
                        document.body.appendChild(tooltipEl);
                        
                        const rect = this.getBoundingClientRect();
                        tooltipEl.style.left = rect.left + 'px';
                        tooltipEl.style.top = (rect.top - tooltipEl.offsetHeight - 10) + 'px';
                        
                        this.setAttribute('data-tooltip', tooltipEl);
                        this.removeAttribute('title');
                    }
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    const tooltipEl = this.getAttribute('data-tooltip');
                    if (tooltipEl) {
                        document.body.removeChild(tooltipEl);
                        this.setAttribute('title', tooltipEl.textContent);
                    }
                });
            });
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