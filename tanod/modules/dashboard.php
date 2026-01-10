<?php
// tanod/modules/dashboard.php

// Start session and include configurations
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Tanod';

// Initialize variables
$duty_status = null;
$today_schedule = [];
$pending_reports = [];
$recent_incidents = [];
$assigned_handovers = [];
$quick_stats = [];

try {
    // Get current duty status
    $stmt = $pdo->prepare("
        SELECT dl.*, ts.schedule_date, ts.shift_start, ts.shift_end, ts.patrol_route 
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        WHERE dl.user_id = ? AND dl.clock_out IS NULL
        ORDER BY dl.clock_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$tanod_id]);
    $duty_status = $stmt->fetch();

    // Get today's schedule
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date = ?
        ORDER BY shift_start ASC
        LIMIT 3
    ");
    $stmt->execute([$tanod_id, $today]);
    $today_schedule = $stmt->fetchAll();

    // Get pending reports for vetting
    $stmt = $pdo->prepare("
        SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as reporter_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE (r.assigned_tanod = ? OR r.assigned_to = ?)
        AND r.status IN ('pending_field_verification', 'assigned')
        AND r.needs_verification = 1
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$tanod_id, $tanod_id]);
    $pending_reports = $stmt->fetchAll();

    // Get recent incidents (last 5)
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_incidents 
        WHERE user_id = ? 
        ORDER BY reported_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$tanod_id]);
    $recent_incidents = $stmt->fetchAll();

    // Get pending evidence handovers
    $stmt = $pdo->prepare("
        SELECT eh.*, CONCAT(u.first_name, ' ', u.last_name) as recipient_name
        FROM evidence_handovers eh
        JOIN users u ON eh.handover_to = u.id
        WHERE eh.tanod_id = ? AND eh.recipient_acknowledged = 0
        ORDER BY eh.handover_date DESC
        LIMIT 5
    ");
    $stmt->execute([$tanod_id]);
    $assigned_handovers = $stmt->fetchAll();

    // Get quick stats
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NOT NULL AND DATE(clock_in) = CURDATE()) as today_shifts,
            (SELECT COUNT(*) FROM reports WHERE (assigned_tanod = ? OR assigned_to = ?) AND status IN ('pending_field_verification', 'assigned')) as pending_verifications,
            (SELECT COUNT(*) FROM tanod_incidents WHERE user_id = ? AND DATE(reported_at) = CURDATE()) as today_incidents,
            (SELECT COUNT(*) FROM evidence_handovers WHERE tanod_id = ? AND recipient_acknowledged = 0) as pending_acknowledgements
    ");
    $stmt->execute([$tanod_id, $tanod_id, $tanod_id, $tanod_id, $tanod_id]);
    $quick_stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanod Dashboard - Barangay LEIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .blink {
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
            transition: transform 0.5s ease;
        }
        .stat-card:hover::after {
            transform: rotate(30deg) translateX(100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="gradient-bg text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-2xl mr-3"></i>
                        <div>
                            <h1 class="text-xl font-bold">Tanod Dashboard</h1>
                            <p class="text-sm text-white/80">Barangay Law Enforcement & Incident Response</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden md:block">
                        <p class="font-medium"><?php echo htmlspecialchars($tanod_name); ?></p>
                        <p class="text-sm text-white/80">Tanod ID: T-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div class="relative">
                        <button id="userMenu" class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center hover:bg-white/30 transition">
                            <i class="fas fa-user"></i>
                        </button>
                        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-lg shadow-lg py-2 z-50">
                            <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-user-cog mr-2"></i>Profile Settings</a>
                            <a href="../../logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl shadow p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Today's Shifts</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $quick_stats['today_shifts'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="duty_schedule.php" class="text-blue-600 text-sm hover:text-blue-800 flex items-center">
                        <span>View Schedule</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Verifications</p>
                        <p class="text-3xl font-bold text-orange-600"><?php echo $quick_stats['pending_verifications'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-orange-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="report_vetting.php" class="text-orange-600 text-sm hover:text-orange-800 flex items-center">
                        <span>Review Reports</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Today's Incidents</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $quick_stats['today_incidents'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#" onclick="loadModule('incident_logging')" class="text-red-600 text-sm hover:text-red-800 flex items-center">
                        <span>Log Incident</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Handovers</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $quick_stats['pending_acknowledgements'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-box-open text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="evidence_handover.php" class="text-purple-600 text-sm hover:text-purple-800 flex items-center">
                        <span>Manage Evidence</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Duty Status & Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Duty Status Panel -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Current Duty Status</h2>
                    <div class="flex items-center">
                        <span class="status-indicator <?php echo ($duty_status) ? 'bg-green-500 blink' : 'bg-red-500'; ?> w-3 h-3 rounded-full mr-2"></span>
                        <span class="font-semibold <?php echo ($duty_status) ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo ($duty_status) ? 'ON DUTY' : 'OFF DUTY'; ?>
                        </span>
                    </div>
                </div>

                <?php if($duty_status): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-5 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-green-800">Clocked In Since</p>
                                <p class="text-2xl font-bold text-green-900">
                                    <?php echo date('h:i A', strtotime($duty_status['clock_in'])); ?>
                                </p>
                                <p class="text-sm text-green-700 mt-1">
                                    <?php 
                                    if ($duty_status['clock_in']) {
                                        $start = new DateTime($duty_status['clock_in']);
                                        $now = new DateTime();
                                        $interval = $start->diff($now);
                                        echo $interval->format('%hh %im on duty');
                                    }
                                    ?>
                                </p>
                            </div>
                            <button onclick="clockOut()" 
                                    class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow transition flex items-center">
                                <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                            </button>
                        </div>
                        <?php if($duty_status['patrol_route']): ?>
                            <div class="mt-4 pt-4 border-t border-green-200">
                                <p class="text-sm text-green-700">
                                    <i class="fas fa-route mr-2"></i>
                                    Current Route: <span class="font-semibold"><?php echo htmlspecialchars($duty_status['patrol_route']); ?></span>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-5 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-red-800">Currently Off Duty</p>
                                <p class="text-sm text-red-700">You are not clocked in for any shift</p>
                            </div>
                            <button onclick="clockIn()" 
                                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition flex items-center">
                                <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Today's Schedule -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Today's Schedule</h3>
                    <?php if(count($today_schedule) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach($today_schedule as $shift): ?>
                                <?php
                                $current_time = time();
                                $shift_start = strtotime($shift['schedule_date'] . ' ' . $shift['shift_start']);
                                $shift_end = strtotime($shift['schedule_date'] . ' ' . $shift['shift_end']);
                                
                                $status_class = '';
                                if ($current_time < $shift_start) {
                                    $status_class = 'border-blue-200 bg-blue-50';
                                } elseif ($current_time >= $shift_start && $current_time <= $shift_end) {
                                    $status_class = 'border-green-200 bg-green-50';
                                } else {
                                    $status_class = 'border-gray-200 bg-gray-50';
                                }
                                ?>
                                <div class="border-l-4 <?php echo $status_class; ?> p-4 rounded-r-lg">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($shift['shift_type']); ?> Shift</p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('h:i A', $shift_start); ?> - <?php echo date('h:i A', $shift_end); ?>
                                            </p>
                                        </div>
                                        <?php if(!empty($shift['patrol_route'])): ?>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                <?php echo htmlspecialchars($shift['patrol_route']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fas fa-calendar-times text-3xl mb-3"></i>
                            <p>No shifts scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Panel -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
                <div class="space-y-4">
                    <a href="duty_schedule.php" 
                       class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-alt text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Duty Schedule</p>
                            <p class="text-sm text-gray-600">View and manage shifts</p>
                        </div>
                    </a>

                    <a href="incident_logging.php" 
                       class="flex items-center p-4 bg-red-50 hover:bg-red-100 rounded-lg transition">
                        <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Log Incident</p>
                            <p class="text-sm text-gray-600">Report field incidents</p>
                        </div>
                    </a>

                    <a href="report_vetting.php" 
                       class="flex items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition">
                        <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clipboard-check text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Verify Reports</p>
                            <p class="text-sm text-gray-600">Review citizen reports</p>
                        </div>
                    </a>

                    <a href="evidence_handover.php" 
                       class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition">
                        <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-box-open text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Evidence Handover</p>
                            <p class="text-sm text-gray-600">Manage evidence chain</p>
                        </div>
                    </a>

                    <a href="profile.php" 
                       class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <div class="w-10 h-10 bg-gray-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-user-cog text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Profile Settings</p>
                            <p class="text-sm text-gray-600">Update your account</p>
                        </div>
                    </a>
                </div>

                <!-- Emergency Contact -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-3">Emergency Contact</h3>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-phone-alt text-red-600 mr-3"></i>
                            <div>
                                <p class="font-semibold text-red-800">Barangay Hotline</p>
                                <p class="text-red-700">(02) 8888-9999</p>
                            </div>
                        </div>
                        <button onclick="callEmergency()" 
                                class="mt-3 w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">
                            <i class="fas fa-phone mr-2"></i> Call Emergency
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Pending Reports -->
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Pending Verification</h3>
                    <span class="px-3 py-1 bg-orange-100 text-orange-800 text-sm rounded-full">
                        <?php echo count($pending_reports); ?> reports
                    </span>
                </div>
                
                <?php if(count($pending_reports) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($pending_reports as $report): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">#<?php echo $report['id']; ?>: <?php echo htmlspecialchars(substr($report['title'], 0, 30)); ?>...</p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($report['reporter_name']); ?>
                                        </p>
                                    </div>
                                    <a href="report_vetting.php?view_report=<?php echo $report['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="report_vetting.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            View All Reports <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-clipboard-check text-3xl mb-3"></i>
                        <p>No pending reports</p>
                        <p class="text-sm mt-1">All reports are verified</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Incidents -->
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Recent Incidents</h3>
                    <span class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded-full">
                        <?php echo count($recent_incidents); ?> logged
                    </span>
                </div>
                
                <?php if(count($recent_incidents) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($recent_incidents as $incident): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($incident['incident_type']); ?></p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars(substr($incident['location'], 0, 25)); ?>...
                                        </p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <?php echo date('h:i A', strtotime($incident['reported_at'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded">
                                        <?php echo ucfirst($incident['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="#" onclick="loadModule('incident_logging')" class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
                            Log New Incident <i class="fas fa-plus-circle ml-2"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                        <p>No incidents logged today</p>
                        <button onclick="loadModule('incident_logging')" 
                                class="mt-3 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">
                            Log First Incident
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Evidence Handovers -->
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Evidence Handovers</h3>
                    <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">
                        <?php echo count($assigned_handovers); ?> pending
                    </span>
                </div>
                
                <?php if(count($assigned_handovers) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($assigned_handovers as $handover): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars(ucfirst($handover['item_type'])); ?></p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-user-tag mr-1"></i>To: <?php echo htmlspecialchars($handover['recipient_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <?php echo date('M d, h:i A', strtotime($handover['handover_date'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">
                                        Pending
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="evidence_handover.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center">
                            Manage Evidence <i class="fas fa-box-open ml-2"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-box-open text-3xl mb-3"></i>
                        <p>No pending handovers</p>
                        <p class="text-sm mt-1">All evidence acknowledged</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200 text-center text-gray-500 text-sm">
            <p>Barangay LEIR System • Last sync: <?php echo date('h:i A'); ?></p>
            <p class="mt-1">© <?php echo date('Y'); ?> Barangay Law Enforcement & Incident Response</p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // User menu toggle
        document.getElementById('userMenu').addEventListener('click', function() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!userMenu.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Clock In/Out functionality
        async function clockIn() {
            try {
                const response = await fetch('duty_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clock_in'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Successfully clocked in!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
            }
        }

        async function clockOut() {
            if (!confirm('Are you sure you want to clock out?')) {
                return;
            }
            
            try {
                const response = await fetch('duty_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clock_out'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Successfully clocked out!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
            }
        }

        // Emergency call function
        function callEmergency() {
            if (confirm('Call Barangay Emergency Hotline?\n\n(02) 8888-9999')) {
                window.location.href = 'tel:0288889999';
            }
        }

        // Load module in modal (for quick actions)
        function loadModule(module) {
            if (module === 'incident_logging') {
                window.location.href = 'incident_logging.php';
            }
        }

        // Notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
                'bg-blue-100 text-blue-800 border border-blue-200'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Auto-refresh duty status every 60 seconds
        setInterval(() => {
            const statusIndicator = document.querySelector('.status-indicator');
            if (statusIndicator && statusIndicator.classList.contains('blink')) {
                statusIndicator.classList.remove('blink');
                setTimeout(() => statusIndicator.classList.add('blink'), 100);
            }
        }, 60000);

        // Add active state to current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('a[href]');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath.split('/').pop()) {
                    link.classList.add('active');
                }
            });
            
            // Check if there's a pending schedule soon
            const todaySchedule = <?php echo json_encode($today_schedule); ?>;
            if (todaySchedule && todaySchedule.length > 0) {
                const now = new Date();
                const upcomingShifts = todaySchedule.filter(shift => {
                    const shiftTime = new Date(shift.schedule_date + ' ' + shift.shift_start);
                    const diffHours = (shiftTime - now) / (1000 * 60 * 60);
                    return diffHours > 0 && diffHours < 2; // Within next 2 hours
                });
                
                if (upcomingShifts.length > 0 && !<?php echo $duty_status ? 'true' : 'false'; ?>) {
                    showNotification('Upcoming shift in less than 2 hours. Remember to clock in!', 'warning');
                }
            }
        });
    </script>
</body>
</html>