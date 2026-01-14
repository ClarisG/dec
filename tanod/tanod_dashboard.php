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

// Get assigned reports
$assigned_query = "SELECT COUNT(*) as count FROM reports 
                  WHERE assigned_tanod = :tanod_id 
                  AND status IN ('pending', 'assigned', 'investigating')";
$assigned_stmt = $conn->prepare($assigned_query);
$assigned_stmt->execute([':tanod_id' => $user_id]);
$stats['assigned_cases'] = $assigned_stmt->fetchColumn();

// Get resolved cases (last 30 days)
$resolved_query = "SELECT COUNT(*) as count FROM reports 
                  WHERE assigned_tanod = :tanod_id 
                  AND status = 'resolved'
                  AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$resolved_stmt = $conn->prepare($resolved_query);
$resolved_stmt->execute([':tanod_id' => $user_id]);
$stats['resolved_cases'] = $resolved_stmt->fetchColumn();

// Get patrol duty status
$duty_query = "SELECT status FROM tanod_status WHERE user_id = :user_id";
$duty_stmt = $conn->prepare($duty_query);
$duty_stmt->execute([':user_id' => $user_id]);
$duty_status = $duty_stmt->fetch(PDO::FETCH_ASSOC);
$stats['duty_status'] = $duty_status['status'] ?? 'Off-Duty';

// Get recent assigned cases
$recent_cases_query = "SELECT r.*, u.first_name as complainant_fname, u.last_name as complainant_lname,
                              rt.type_name, r.created_at as case_date
                       FROM reports r
                       JOIN users u ON r.user_id = u.id
                       JOIN report_types rt ON r.report_type_id = rt.id
                       WHERE r.assigned_tanod = :tanod_id
                       ORDER BY r.created_at DESC 
                       LIMIT 5";
$recent_cases_stmt = $conn->prepare($recent_cases_query);
$recent_cases_stmt->execute([':tanod_id' => $user_id]);
$recent_cases = $recent_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanod Dashboard - LEIR System</title>
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
        
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Main Content -->
    <div class="flex">
        <!-- Sidebar -->
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
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="tanod_dashboard.php" class="block p-3 text-white rounded-lg bg-white/10">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                    <a href="assigned_cases.php" class="block p-3 text-white rounded-lg hover:bg-white/10">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        Assigned Cases
                        <?php if ($stats['assigned_cases'] > 0): ?>
                            <span class="float-right bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                                <?php echo $stats['assigned_cases']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="patrol_duty.php" class="block p-3 text-white rounded-lg hover:bg-white/10">
                        <i class="fas fa-walking mr-3"></i>
                        Patrol Duty
                        <span class="float-right text-xs px-2 py-1 rounded-full <?php echo $stats['duty_status'] == 'On-Duty' ? 'bg-green-500' : 'bg-gray-500'; ?>">
                            <?php echo $stats['duty_status']; ?>
                        </span>
                    </a>
                    <a href="incident_reports.php" class="block p-3 text-white rounded-lg hover:bg-white/10">
                        <i class="fas fa-file-alt mr-3"></i>
                        Incident Reports
                    </a>
                </nav>
                
                <!-- Stats -->
                <div class="mt-8 pt-8 border-t border-blue-400/30">
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">Assigned Cases</span>
                            <span class="text-white font-bold"><?php echo $stats['assigned_cases']; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">Resolved (30d)</span>
                            <span class="text-white font-bold"><?php echo $stats['resolved_cases']; ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <a href="../logout.php" class="flex items-center p-3 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content md:ml-64 flex-1 p-6">
            <!-- Header -->
            <header class="bg-white shadow-sm rounded-lg mb-6 p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Tanod Dashboard</h1>
                        <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <i class="fas fa-bell text-gray-600 text-xl"></i>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2">
                                <?php if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                    <img src="<?php echo $profile_pic_path; ?>" 
                                         alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold">
                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Assigned Cases</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['assigned_cases']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Resolved (30 days)</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['resolved_cases']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg <?php echo $stats['duty_status'] == 'On-Duty' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-600'; ?> mr-4">
                            <i class="fas fa-walking text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Duty Status</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['duty_status']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Cases -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Recent Assigned Cases</h2>
                    <a href="assigned_cases.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complainant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (!empty($recent_cases)): ?>
                                <?php foreach ($recent_cases as $case): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($case['report_number']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($case['type_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($case['complainant_fname'] . ' ' . $case['complainant_lname']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($case['case_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            <?php 
                                            switch($case['status']) {
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'assigned': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'investigating': echo 'bg-orange-100 text-orange-800'; break;
                                                case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($case['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        No assigned cases yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.createElement('button');
            mobileMenuButton.className = 'md:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg';
            mobileMenuButton.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(mobileMenuButton);
            
            mobileMenuButton.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('hidden');
            });
        });
    </script>
</body>
</html>