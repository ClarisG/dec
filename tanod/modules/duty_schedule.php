<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get database connection
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle clock in/out via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'clock_in') {
        $result = clockIn($pdo, $tanod_id);
        echo json_encode($result);
        exit();
    } elseif ($_POST['action'] === 'clock_out') {
        $result = clockOut($pdo, $tanod_id);
        echo json_encode($result);
        exit();
    } elseif ($_POST['action'] === 'update_location') {
        $latitude = $_POST['latitude'] ?? 0;
        $longitude = $_POST['longitude'] ?? 0;
        
        try {
            // Update or insert location
            $stmt = $pdo->prepare("
                INSERT INTO tanod_locations (tanod_id, latitude, longitude, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                updated_at = NOW()
            ");
            $stmt->execute([$tanod_id, $latitude, $longitude]);
            
            // Update current duty log if exists
            $stmt2 = $pdo->prepare("
                UPDATE tanod_duty_logs 
                SET current_latitude = ?, current_longitude = ?
                WHERE user_id = ? AND clock_out IS NULL
            ");
            $stmt2->execute([$latitude, $longitude, $tanod_id]);
            
            echo json_encode(['success' => true, 'message' => 'Location updated']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating location']);
        }
        exit();
    }
}

function clockIn($pdo, $tanod_id) {
    try {
        // Check if already clocked in
        $stmt = $pdo->prepare("SELECT * FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
        $stmt->execute([$tanod_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Already clocked in'];
        }
        
        // Get current schedule
        $current_time = date('H:i:s');
        $current_date = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT * FROM tanod_schedules 
            WHERE user_id = ? AND schedule_date = ? 
            AND shift_start <= ? AND shift_end >= ?
            AND active = 1
        ");
        $stmt->execute([$tanod_id, $current_date, $current_time, $current_time]);
        
        $schedule_id = null;
        if ($stmt->rowCount() > 0) {
            $schedule = $stmt->fetch();
            $schedule_id = $schedule['id'];
        }
        
        // Insert duty log
        $stmt = $pdo->prepare("
            INSERT INTO tanod_duty_logs 
            (user_id, schedule_id, clock_in, status, current_latitude, current_longitude) 
            VALUES (?, ?, NOW(), 'on_duty', 0, 0)
        ");
        $stmt->execute([$tanod_id, $schedule_id]);
        
        $duty_id = $pdo->lastInsertId();
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET status = 'on_duty' WHERE id = ?");
        $stmt->execute([$tanod_id]);
        
        // Add activity log
        addActivityLog($pdo, $tanod_id, 'clock_in', 'Clocked in for duty');
        
        // Create notification for Admin/Secretary
        $notif_stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'duty_clock', ?, 0, NOW())
        ");
        
        // Get admins and secretaries
        $admin_stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role IN ('admin', 'secretary', 'captain') 
            AND status = 'active'
        ");
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $notif_stmt->execute([
                $admin['id'],
                '👮 Tanod On Duty',
                "Tanod $tanod_name has clocked in for duty (Duty #$duty_id)",
                $duty_id
            ]);
        }
        
        return ['success' => true, 'message' => 'Successfully clocked in', 'duty_id' => $duty_id];
    } catch (PDOException $e) {
        error_log("Clock In Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again.'];
    }
}

function clockOut($pdo, $tanod_id) {
    try {
        // Check if clocked in
        $stmt = $pdo->prepare("SELECT * FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
        $stmt->execute([$tanod_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Not clocked in'];
        }
        
        $duty_log = $stmt->fetch();
        
        // Update duty log
        $stmt = $pdo->prepare("
            UPDATE tanod_duty_logs 
            SET clock_out = NOW(), 
                total_hours = TIMESTAMPDIFF(SECOND, clock_in, NOW())/3600.0,
                status = 'off_duty',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$duty_log['id']]);
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET status = 'off_duty' WHERE id = ?");
        $stmt->execute([$tanod_id]);
        
        // Clear location
        $stmt = $pdo->prepare("DELETE FROM tanod_locations WHERE tanod_id = ?");
        $stmt->execute([$tanod_id]);
        
        // Add activity log
        addActivityLog($pdo, $tanod_id, 'clock_out', 'Clocked out from duty');
        
        // Create notification for Admin/Secretary
        $notif_stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'duty_clock', ?, 0, NOW())
        ");
        
        // Get admins and secretaries
        $admin_stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role IN ('admin', 'secretary', 'captain') 
            AND status = 'active'
        ");
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $notif_stmt->execute([
                $admin['id'],
                '👮 Tanod Off Duty',
                "Tanod $tanod_name has clocked out from duty (Duty #{$duty_log['id']})",
                $duty_log['id']
            ]);
        }
        
        return ['success' => true, 'message' => 'Successfully clocked out'];
    } catch (PDOException $e) {
        error_log("Clock Out Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again.'];
    }
}

function addActivityLog($pdo, $user_id, $action, $description) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

try {
    // Get current duty status
    $stmt = $pdo->prepare("
        SELECT dl.*, 
               ts.schedule_date, ts.shift_start, ts.shift_end, 
               ts.patrol_route, ts.shift_type, ts.notes as schedule_notes,
               u.first_name, u.last_name, u.contact_number
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        LEFT JOIN users u ON dl.user_id = u.id
        WHERE dl.user_id = ? AND dl.clock_out IS NULL
        ORDER BY dl.clock_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$tanod_id]);
    $current_duty = $stmt->fetch();

    // Get today's schedule
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date = ? AND active = 1
        ORDER BY shift_start ASC
    ");
    $stmt->execute([$tanod_id, $today]);
    $today_schedule = $stmt->fetchAll();

    // Get upcoming schedules (next 7 days)
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date >= CURDATE() AND active = 1
        ORDER BY schedule_date ASC, shift_start ASC
        LIMIT 14
    ");
    $stmt->execute([$tanod_id]);
    $schedules = $stmt->fetchAll();

    // Get assigned patrol routes
    $stmt = $pdo->prepare("
        SELECT DISTINCT patrol_route, zone_name, area_description
        FROM tanod_schedules 
        WHERE user_id = ? AND patrol_route IS NOT NULL AND active = 1
        ORDER BY patrol_route
    ");
    $stmt->execute([$tanod_id]);
    $patrol_routes = $stmt->fetchAll();

    // Get current location if on duty
    $current_location = null;
    if ($current_duty) {
        $stmt = $pdo->prepare("
            SELECT * FROM tanod_locations 
            WHERE tanod_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$tanod_id]);
        $current_location = $stmt->fetch();
    }

    // Get duty statistics (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            COALESCE(SUM(total_hours), 0) as total_hours,
            COALESCE(AVG(total_hours), 0) as avg_hours,
            COALESCE(MAX(total_hours), 0) as max_hours,
            COALESCE(MIN(total_hours), 0) as min_hours
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_out IS NOT NULL
        AND clock_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $stats = $stmt->fetch();

    // Get recent duty logs
    $stmt = $pdo->prepare("
        SELECT dl.*, 
               ts.schedule_date, ts.shift_type, ts.patrol_route,
               DATE_FORMAT(dl.clock_in, '%Y-%m-%d') as duty_date
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        WHERE dl.user_id = ? 
        ORDER BY dl.clock_in DESC 
        LIMIT 10
    ");
    $stmt->execute([$tanod_id]);
    $recent_duties = $stmt->fetchAll();

    // Get today's incidents (if any)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as incident_count
        FROM tanod_incidents 
        WHERE user_id = ? AND DATE(reported_at) = CURDATE()
    ");
    $stmt->execute([$tanod_id]);
    $today_incidents = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    // Initialize variables to prevent errors
    $current_duty = null;
    $schedules = [];
    $patrol_routes = [];
    $today_schedule = [];
    $stats = ['total_shifts' => 0, 'total_hours' => 0, 'avg_hours' => 0, 'max_hours' => 0, 'min_hours' => 0];
    $recent_duties = [];
    $today_incidents = 0;
    $current_location = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Duty & Patrol Schedule - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-on-duty { 
            background-color: #10B981; 
            box-shadow: 0 0 10px #10B981;
        }
        .status-off-duty { 
            background-color: #EF4444; 
        }
        
        .shift-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .shift-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background-color: #E5E7EB;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.5s ease-in-out;
        }
        
        .blink {
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            transform: translateX(400px);
            transition: transform 0.3s ease-in-out;
            opacity: 0;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        /* Map container */
        #mapContainer {
            height: 300px;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .location-updating {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .mobile-stack {
                flex-direction: column;
            }
            
            .mobile-full {
                width: 100% !important;
            }
            
            #mapContainer {
                height: 200px;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .glass-card {
                background: rgba(30, 30, 30, 0.95);
                border-color: rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen p-4">
    <!-- Toast Notification -->
    <div id="toast" class="toast hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 border-l-4 border-blue-500">
            <div class="flex items-start">
                <div id="toastIcon" class="mr-3 mt-1">
                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                </div>
                <div class="flex-1">
                    <p id="toastMessage" class="text-gray-800 font-medium"></p>
                    <p id="toastTime" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <button onclick="hideToast()" class="ml-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            My Duty & Patrol Schedule
                        </h1>
                        <p class="text-blue-100 mt-2">View assigned shifts and designated routes. Use real-time status tracker.</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-white text-sm">Logged in as:</p>
                                <p class="text-white font-bold">
                                    <?php echo htmlspecialchars($tanod_name); ?>
                                    <span class="bg-white/20 px-2 py-1 rounded-full text-xs ml-2">Tanod</span>
                                </p>
                                <p class="text-white text-xs mt-1">
                                    ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Data Handled Info -->
        <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-blue-800">Critical Data Handled</p>
                    <p class="text-xs text-blue-700">Shift times, Assigned patrol routes, Real-time status (On-Duty/Off-Duty), GPS locations</p>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Current Duty Status Card -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Current Duty Status</h2>
                            <div class="flex items-center mt-2">
                                <span class="status-indicator <?php echo ($current_duty) ? 'status-on-duty blink' : 'status-off-duty'; ?>"></span>
                                <span class="text-xl font-bold <?php echo ($current_duty) ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo ($current_duty) ? '🟢 ON DUTY' : '🔴 OFF DUTY'; ?>
                                </span>
                                <?php if($current_duty): ?>
                                    <span class="ml-4 text-sm text-gray-600">
                                        <i class="far fa-clock mr-1"></i>
                                        Since <?php echo date('h:i A', strtotime($current_duty['clock_in'])); ?>
                                    </span>
                                    <?php 
                                    $hours = 0;
                                    if ($current_duty['clock_in']) {
                                        $start = new DateTime($current_duty['clock_in']);
                                        $now = new DateTime();
                                        $interval = $start->diff($now);
                                        $hours = $interval->h + ($interval->i / 60);
                                    }
                                    ?>
                                    <span class="ml-4 text-sm font-bold text-gray-700">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        <?php echo number_format($hours, 1); ?> hours
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4 md:mt-0">
                            <?php if($current_duty): ?>
                                <button type="button" onclick="clockOut()" 
                                        class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white font-bold rounded-lg shadow-lg hover:from-red-700 hover:to-red-800 transition duration-300 flex items-center">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="clockIn()" 
                                        class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold rounded-lg shadow-lg hover:from-green-700 hover:to-green-800 transition duration-300 flex items-center">
                                    <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Real-time Status Tracker -->
                    <div class="mt-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">
                                <i class="fas fa-satellite mr-2"></i>
                                Duty Status Tracker
                            </span>
                            <span class="text-sm font-bold <?php echo ($current_duty) ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo ($current_duty) ? '🟢 Active Patrol' : '🔴 Standing By'; ?>
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo ($current_duty) ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-red-500 to-red-600'; ?>" 
                                 style="width: <?php echo ($current_duty) ? '100%' : '25%'; ?>"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-2">
                            <span>Start</span>
                            <span>Active</span>
                            <span>On Patrol</span>
                            <span>Complete</span>
                        </div>
                    </div>

                    <!-- Current Location (if on duty) -->
                    <?php if($current_duty && $current_location): ?>
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-blue-800">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                Current Location
                            </h4>
                            <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full location-updating">
                                <i class="fas fa-sync-alt mr-1"></i> Live
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600">Latitude</p>
                                <p class="text-sm font-mono font-bold"><?php echo number_format($current_location['latitude'], 6); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Longitude</p>
                                <p class="text-sm font-mono font-bold"><?php echo number_format($current_location['longitude'], 6); ?></p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="far fa-clock mr-1"></i>
                            Updated: <?php echo date('h:i A', strtotime($current_location['updated_at'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Quick Stats -->
            <div class="lg:col-span-1">
                <div class="glass-card rounded-2xl shadow-lg p-6 h-full">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Today's Overview</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                            <div>
                                <p class="text-sm text-blue-700">Today's Shifts</p>
                                <p class="text-2xl font-bold text-blue-800"><?php echo count($today_schedule); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                            <div>
                                <p class="text-sm text-green-700">Hours Logged (30 days)</p>
                                <p class="text-2xl font-bold text-green-800"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-green-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg">
                            <div>
                                <p class="text-sm text-orange-700">Today's Incidents</p>
                                <p class="text-2xl font-bold text-orange-800"><?php echo $today_incidents; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                            <div>
                                <p class="text-sm text-purple-700">Avg. Shift Hours</p>
                                <p class="text-2xl font-bold text-purple-800"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule and Routes Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Today's Schedule -->
            <div class="glass-card rounded-2xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Today's Schedule</h2>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-bold rounded-full">
                        <i class="far fa-calendar mr-1"></i>
                        <?php echo date('F j, Y'); ?>
                    </span>
                </div>
                
                <?php if(count($today_schedule) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($today_schedule as $shift): ?>
                            <?php
                            $current_time = time();
                            $shift_start = strtotime($shift['schedule_date'] . ' ' . $shift['shift_start']);
                            $shift_end = strtotime($shift['schedule_date'] . ' ' . $shift['shift_end']);
                            
                            $status = '';
                            if ($current_time < $shift_start) {
                                $status = 'upcoming';
                                $border_color = 'border-blue-300';
                                $bg_color = 'bg-blue-50';
                                $text_color = 'text-blue-700';
                            } elseif ($current_time >= $shift_start && $current_time <= $shift_end) {
                                $status = 'active';
                                $border_color = 'border-green-300';
                                $bg_color = 'bg-green-50';
                                $text_color = 'text-green-700';
                            } else {
                                $status = 'completed';
                                $border_color = 'border-gray-300';
                                $bg_color = 'bg-gray-50';
                                $text_color = 'text-gray-700';
                            }
                            ?>
                            
                            <div class="shift-card border-l-4 <?php echo $border_color; ?> <?php echo $bg_color; ?> p-4 rounded-r-lg">
                                <div class="flex justify-between items-center">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($shift['shift_type']); ?> Shift</h3>
                                            <span class="ml-3 px-3 py-1 text-xs rounded-full font-bold 
                                                <?php echo $status === 'active' ? 'bg-green-100 text-green-800' : 
                                                       ($status === 'upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm <?php echo $text_color; ?>">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('h:i A', $shift_start); ?> - <?php echo date('h:i A', $shift_end); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <?php if($status === 'active' && !$current_duty): ?>
                                            <button onclick="clockIn()" 
                                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                                <i class="fas fa-play mr-1"></i> Start
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if(!empty($shift['patrol_route'])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <p class="text-sm text-gray-700">
                                            <i class="fas fa-route text-blue-500 mr-2"></i>
                                            Route: <span class="font-bold"><?php echo htmlspecialchars($shift['patrol_route']); ?></span>
                                        </p>
                                        <?php if(!empty($shift['zone_name'])): ?>
                                            <p class="text-xs text-gray-600 mt-1">
                                                <i class="fas fa-map-pin mr-2"></i>
                                                Zone: <?php echo htmlspecialchars($shift['zone_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($shift['schedule_notes'])): ?>
                                    <div class="mt-3 p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-600 mb-1 font-medium">Notes:</p>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($shift['schedule_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-5xl mb-3">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No shifts scheduled for today</p>
                        <p class="text-sm text-gray-400 mt-1">Check upcoming schedule for future assignments</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Shifts -->
            <div class="glass-card rounded-2xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Upcoming Shifts</h2>
                    <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm font-bold rounded-full">
                        Next 14 Days
                    </span>
                </div>
                
                <?php if(count($schedules) > 0): ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        <?php 
                        $displayed = 0;
                        foreach($schedules as $schedule): 
                            if($schedule['schedule_date'] != $today):
                                $displayed++;
                        ?>
                            <div class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50 transition duration-200">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <p class="font-bold text-gray-800">
                                                <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                                            </p>
                                            <?php 
                                            $days_diff = floor((strtotime($schedule['schedule_date']) - time()) / (60 * 60 * 24));
                                            if ($days_diff == 1): ?>
                                                <span class="ml-3 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-bold">
                                                    Tomorrow
                                                </span>
                                            <?php elseif ($days_diff <= 7): ?>
                                                <span class="ml-3 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-bold">
                                                    In <?php echo $days_diff; ?> days
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . date('h:i A', strtotime($schedule['shift_end'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-bold">
                                        <?php echo htmlspecialchars($schedule['shift_type']); ?>
                                    </span>
                                </div>
                                
                                <?php if(!empty($schedule['patrol_route'])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <p class="text-sm text-gray-700">
                                            <i class="fas fa-route text-blue-500 mr-2"></i>
                                            <?php echo htmlspecialchars($schedule['patrol_route']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        
                        if ($displayed === 0): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-calendar-alt text-3xl mb-3"></i>
                                <p>No upcoming shifts scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-5xl mb-3">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No upcoming shifts scheduled</p>
                        <p class="text-sm text-gray-400 mt-1">Contact administrator for schedule assignments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Patrol Routes -->
        <div class="glass-card rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Assigned Patrol Routes</h2>
                <span class="px-3 py-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white text-sm font-bold rounded-full">
                    <?php echo count($patrol_routes); ?> Routes Assigned
                </span>
            </div>
            
            <?php if(count($patrol_routes) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    $route_colors = ['blue', 'green', 'purple', 'orange', 'indigo', 'pink', 'teal', 'red'];
                    $color_index = 0;
                    
                    foreach($patrol_routes as $route): 
                        $color_class = $route_colors[$color_index % count($route_colors)];
                        $color_index++;
                    ?>
                        <div class="border border-gray-200 rounded-xl p-6 hover:shadow-lg transition duration-300 transform hover:-translate-y-1">
                            <div class="flex items-center mb-4">
                                <div class="h-14 w-14 bg-gradient-to-br from-<?php echo $color_class; ?>-500 to-<?php echo $color_class; ?>-700 rounded-xl flex items-center justify-center mr-4 shadow-md">
                                    <i class="fas fa-route text-white text-xl"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($route['patrol_route']); ?></p>
                                    <?php if(!empty($route['zone_name'])): ?>
                                        <p class="text-sm text-gray-500">Zone: <?php echo htmlspecialchars($route['zone_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if(!empty($route['area_description'])): ?>
                                <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars(substr($route['area_description'], 0, 100)); ?>...</p>
                            <?php endif; ?>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-check-circle text-<?php echo $color_class; ?>-500 mr-2"></i>
                                    <span>Regular patrol assignment</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-shield-alt text-<?php echo $color_class; ?>-500 mr-2"></i>
                                    <span>Standard security protocol</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-clock text-<?php echo $color_class; ?>-500 mr-2"></i>
                                    <span>Scheduled rotations</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 text-5xl mb-3">
                        <i class="fas fa-route"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No patrol routes assigned</p>
                    <p class="text-sm text-gray-400 mt-1">Contact administrator for route assignments</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Duty Statistics and Recent Logs -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Duty Statistics -->
            <div class="lg:col-span-1">
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Duty Statistics (30 Days)</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200">
                            <div>
                                <p class="text-sm text-gray-600">Total Shifts</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_shifts'] ?? 0; ?></p>
                            </div>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-history text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200">
                            <div>
                                <p class="text-sm text-gray-600">Total Hours</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></p>
                            </div>
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-green-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200">
                            <div>
                                <p class="text-sm text-gray-600">Average Hours/Shift</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></p>
                            </div>
                            <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200">
                            <div>
                                <p class="text-sm text-gray-600">Longest Shift</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['max_hours'] ?? 0, 1); ?>h</p>
                            </div>
                            <div class="h-12 w-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-hourglass-end text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Duty Logs -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Recent Duty Logs</h2>
                    
                    <?php if(count($recent_duties) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Shift</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Clock In</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Clock Out</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Hours</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach($recent_duties as $log): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($log['clock_in'])); ?>
                                                </div>
                                                <?php if($log['duty_date'] === $today): ?>
                                                    <span class="text-xs text-green-600 font-bold">Today</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($log['shift_type'] ?? 'N/A'); ?>
                                                </div>
                                                <?php if(!empty($log['patrol_route'])): ?>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['patrol_route']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600">
                                                    <?php echo date('h:i A', strtotime($log['clock_in'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600">
                                                    <?php echo $log['clock_out'] ? date('h:i A', strtotime($log['clock_out'])) : '--:--'; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-gray-800">
                                                    <?php echo number_format($log['total_hours'] ?? 0, 1); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 text-xs rounded-full font-bold 
                                                    <?php echo $log['clock_out'] ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo $log['clock_out'] ? '✅ Completed' : '🟢 Active'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="text-gray-400 text-5xl mb-3">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <p class="text-gray-500 font-medium">No duty logs found</p>
                            <p class="text-sm text-gray-400 mt-1">Start by clocking in for your first duty</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Footer -->
        <div class="glass-card rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <a href="incident_logging.php" 
                   class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4 text-center hover:from-blue-100 hover:to-blue-200 transition">
                    <div class="text-blue-600 text-2xl mb-2">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p class="font-bold text-blue-800">Log Incident</p>
                    <p class="text-xs text-blue-600 mt-1">Field incident logging</p>
                </a>
                
                <a href="report_vetting.php" 
                   class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl p-4 text-center hover:from-green-100 hover:to-green-200 transition">
                    <div class="text-green-600 text-2xl mb-2">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <p class="font-bold text-green-800">Verify Reports</p>
                    <p class="text-xs text-green-600 mt-1">Citizen report vetting</p>
                </a>
                
                <a href="evidence_handover.php" 
                   class="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-4 text-center hover:from-purple-100 hover:to-purple-200 transition">
                    <div class="text-purple-600 text-2xl mb-2">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <p class="font-bold text-purple-800">Evidence Log</p>
                    <p class="text-xs text-purple-600 mt-1">Chain of custody</p>
                </a>
                
                <a href="profile.php" 
                   class="bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-xl p-4 text-center hover:from-gray-100 hover:to-gray-200 transition">
                    <div class="text-gray-600 text-2xl mb-2">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <p class="font-bold text-gray-800">My Profile</p>
                    <p class="text-xs text-gray-600 mt-1">Update contact info</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Toast notification functions
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            const toastTime = document.getElementById('toastTime');
            
            toastMessage.textContent = message;
            toastTime.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            // Set icon based on type
            let iconClass, borderColor;
            switch(type) {
                case 'success':
                    iconClass = 'fas fa-check-circle text-green-500';
                    borderColor = 'border-green-500';
                    break;
                case 'error':
                    iconClass = 'fas fa-exclamation-circle text-red-500';
                    borderColor = 'border-red-500';
                    break;
                case 'warning':
                    iconClass = 'fas fa-exclamation-triangle text-yellow-500';
                    borderColor = 'border-yellow-500';
                    break;
                default:
                    iconClass = 'fas fa-info-circle text-blue-500';
                    borderColor = 'border-blue-500';
            }
            
            toastIcon.innerHTML = `<i class="${iconClass} text-xl"></i>`;
            toast.querySelector('.border-l-4').className = `bg-white rounded-lg shadow-lg p-4 border-l-4 ${borderColor}`;
            
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto hide after 5 seconds
            setTimeout(hideToast, 5000);
        }
        
        function hideToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('show');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 300);
        }

        // Clock In/Out functionality
        async function clockIn() {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            
            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            try {
                // Get current location first
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(async function(position) {
                        const lat = position.coords.latitude.toFixed(6);
                        const long = position.coords.longitude.toFixed(6);
                        
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=clock_in`
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update location after clocking in
                            await fetch('', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=update_location&latitude=${lat}&longitude=${long}`
                            });
                            
                            showToast(result.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast(result.message, 'error');
                            button.disabled = false;
                            button.innerHTML = originalText;
                        }
                    }, function(error) {
                        // Continue with clock in even if location fails
                        performClockIn(button, originalText);
                    });
                } else {
                    performClockIn(button, originalText);
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Clock in error:', error);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
        
        async function performClockIn(button, originalText) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clock_in'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.message, 'error');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        async function clockOut() {
            if (!confirm('Are you sure you want to clock out?')) {
                return;
            }
            
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            
            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clock_out'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.message, 'error');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Clock out error:', error);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        // Auto-refresh duty status every 30 seconds
        setInterval(() => {
            const statusIndicator = document.querySelector('.status-indicator');
            if (statusIndicator && statusIndicator.classList.contains('blink')) {
                statusIndicator.classList.remove('blink');
                setTimeout(() => statusIndicator.classList.add('blink'), 100);
            }
            
            // Update location if on duty
            if (<?php echo $current_duty ? 'true' : 'false'; ?>) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude.toFixed(6);
                        const long = position.coords.longitude.toFixed(6);
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=update_location&latitude=${lat}&longitude=${long}`
                        });
                    });
                }
            }
        }, 30000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show welcome message
            const todaySchedule = <?php echo json_encode($today_schedule); ?>;
            const currentDuty = <?php echo json_encode($current_duty); ?>;
            
            if (todaySchedule && todaySchedule.length > 0 && !currentDuty) {
                const now = new Date();
                const hasUpcomingShift = todaySchedule.some(shift => {
                    const start = new Date(`${shift.schedule_date}T${shift.shift_start}`);
                    const end = new Date(`${shift.schedule_date}T${shift.shift_end}`);
                    return now >= start && now <= end;
                });
                
                if (hasUpcomingShift) {
                    showToast('You have an active shift. Remember to clock in!', 'info');
                }
            }
            
            // Auto-update current time
            function updateCurrentTime() {
                const now = new Date();
                document.querySelectorAll('.current-time').forEach(el => {
                    el.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                });
            }
            
            setInterval(updateCurrentTime, 60000);
            updateCurrentTime();
        });
        
        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function (e) {
            if (<?php echo $current_duty ? 'true' : 'false'; ?>) {
                e.preventDefault();
                e.returnValue = 'You are currently on duty. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>