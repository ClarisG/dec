<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Don't call session_start() here - it's already started in the main file
// session_start(); // Remove this line

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];

// Handle clock in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clock_in') {
            $result = clockIn($tanod_id);
            echo json_encode($result);
            exit();
        } elseif ($_POST['action'] === 'clock_out') {
            $result = clockOut($tanod_id);
            echo json_encode($result);
            exit();
        }
    }
}

function clockIn($tanod_id) {
    global $pdo;
    
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
        ");
        $stmt->execute([$tanod_id, $current_date, $current_time, $current_time]);
        
        $schedule_id = null;
        if ($stmt->rowCount() > 0) {
            $schedule = $stmt->fetch();
            $schedule_id = $schedule['id'];
        }
        
        // Insert duty log
        $stmt = $pdo->prepare("
            INSERT INTO tanod_duty_logs (user_id, schedule_id, clock_in, status) 
            VALUES (?, ?, NOW(), 'on_duty')
        ");
        $stmt->execute([$tanod_id, $schedule_id]);
        
        // Add activity log
        addActivityLog($tanod_id, 'clock_in', 'Clocked in for duty');
        
        return ['success' => true, 'message' => 'Successfully clocked in'];
    } catch (PDOException $e) {
        error_log("Clock In Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again.'];
    }
}

function clockOut($tanod_id) {
    global $pdo;
    
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
                status = 'off_duty'
            WHERE id = ?
        ");
        $stmt->execute([$duty_log['id']]);
        
        // Add activity log
        addActivityLog($tanod_id, 'clock_out', 'Clocked out from duty');
        
        return ['success' => true, 'message' => 'Successfully clocked out'];
    } catch (PDOException $e) {
        error_log("Clock Out Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again.'];
    }
}

function addActivityLog($user_id, $action, $description) {
    global $pdo;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

try {
    // Get database connection
    $pdo = getDbConnection();
    
    // Get current duty status
    $stmt = $pdo->prepare("
        SELECT dl.*, ts.schedule_date, ts.shift_start, ts.shift_end, ts.patrol_route, 
               ts.shift_type
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        WHERE dl.user_id = ? AND dl.clock_out IS NULL
        ORDER BY dl.clock_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$tanod_id]);
    $current_duty = $stmt->fetch();

    // Get upcoming schedules (next 7 days)
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date >= CURDATE()
        ORDER BY schedule_date ASC, shift_start ASC
        LIMIT 7
    ");
    $stmt->execute([$tanod_id]);
    $schedules = $stmt->fetchAll();

    // Get assigned patrol routes
    $stmt = $pdo->prepare("
        SELECT DISTINCT patrol_route 
        FROM tanod_schedules 
        WHERE user_id = ? AND patrol_route IS NOT NULL
        ORDER BY patrol_route
    ");
    $stmt->execute([$tanod_id]);
    $patrol_routes = $stmt->fetchAll();

    // Get today's schedule
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date = ?
        ORDER BY shift_start ASC
    ");
    $stmt->execute([$tanod_id, $today]);
    $today_schedule = $stmt->fetchAll();

    // Get duty statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            COALESCE(SUM(total_hours), 0) as total_hours,
            COALESCE(AVG(total_hours), 0) as avg_hours
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_out IS NOT NULL
        AND clock_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $stats = $stmt->fetch();

    // Get recent duty logs
    $stmt = $pdo->prepare("
        SELECT dl.*, ts.schedule_date, ts.shift_type
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        WHERE dl.user_id = ? 
        ORDER BY dl.clock_in DESC 
        LIMIT 5
    ");
    $stmt->execute([$tanod_id]);
    $recent_duties = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    // Initialize variables to prevent errors
    $current_duty = null;
    $schedules = [];
    $patrol_routes = [];
    $today_schedule = [];
    $stats = ['total_shifts' => 0, 'total_hours' => 0, 'avg_hours' => 0];
    $recent_duties = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty & Patrol Schedule</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-on-duty { background-color: #10B981; }
        .status-off-duty { background-color: #EF4444; }
        
        .shift-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .shift-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
        }
        
        .toast.show {
            transform: translateX(0);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Toast Notification -->
    <div id="toast" class="toast hidden">
        <div class="bg-white rounded-lg shadow-lg p-4 border-l-4">
            <div class="flex items-start">
                <div id="toastIcon" class="mr-3 mt-1">
                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                </div>
                <div class="flex-1">
                    <p id="toastMessage" class="text-gray-800 font-medium"></p>
                </div>
                <button onclick="hideToast()" class="ml-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

        <!-- Current Duty Status -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Current Duty Status</h2>
                    <div class="flex items-center mt-2">
                        <span class="status-indicator <?php echo ($current_duty) ? 'status-on-duty blink' : 'status-off-duty'; ?>"></span>
                        <span class="text-lg font-semibold <?php echo ($current_duty) ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo ($current_duty) ? 'ON DUTY' : 'OFF DUTY'; ?>
                        </span>
                        <?php if($current_duty): ?>
                            <span class="ml-4 text-sm text-gray-600">
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
                            <span class="ml-4 text-sm font-semibold text-gray-700">
                                <?php echo number_format($hours, 1); ?> hours
                            </span>
                        <?php endif; ?>
                </div>
                
                <div class="mt-4 md:mt-0">
                    <?php if($current_duty): ?>
                        <button type="button" onclick="clockOut()" 
                                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-md transition duration-300 flex items-center">
                            <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="clockIn()" 
                                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition duration-300 flex items-center">
                            <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Real-time Status Tracker -->
            <div class="mt-6">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Duty Status Tracker</span>
                    <span class="text-sm font-semibold <?php echo ($current_duty) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo ($current_duty) ? 'Active Patrol' : 'Standing By'; ?>
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo ($current_duty) ? 'bg-green-500' : 'bg-red-500'; ?>" 
                         style="width: <?php echo ($current_duty) ? '100%' : '25%'; ?>"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-2">
                    <span>Start</span>
                    <span>Active</span>
                    <span>On Patrol</span>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Today's Schedule -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Today's Schedule</h2>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
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
                                $border_color = 'border-blue-200';
                                $bg_color = 'bg-blue-50';
                            } elseif ($current_time >= $shift_start && $current_time <= $shift_end) {
                                $status = 'active';
                                $border_color = 'border-green-200';
                                $bg_color = 'bg-green-50';
                            } else {
                                $status = 'completed';
                                $border_color = 'border-gray-200';
                                $bg_color = 'bg-gray-50';
                            }
                            ?>
                            
                            <div class="shift-card border-l-4 <?php echo $border_color; ?> <?php echo $bg_color; ?> p-4 rounded-r-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($shift['shift_type']); ?> Shift</h3>
                                        <p class="text-sm text-gray-600">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('h:i A', $shift_start); ?> - <?php echo date('h:i A', $shift_end); ?>
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 text-xs rounded-full 
                                                <?php echo $status === 'active' ? 'bg-green-100 text-green-800' : 
                                                       ($status === 'upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                                
                                <?php if(!empty($shift['patrol_route'])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <p class="text-sm text-gray-700">
                                            <i class="fas fa-route text-blue-500 mr-2"></i>
                                            Route: <span class="font-medium"><?php echo htmlspecialchars($shift['patrol_route']); ?></span>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-500">No shifts scheduled for today</p>
                        <p class="text-sm text-gray-400 mt-1">Check upcoming schedule for future assignments</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Shifts -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Upcoming Shifts</h2>
                
                <?php if(count($schedules) > 0): ?>
                    <div class="space-y-4">
                        <?php 
                        $displayed = 0;
                        foreach($schedules as $schedule): 
                            if($schedule['schedule_date'] != $today && $displayed < 4):
                                $displayed++;
                        ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-200">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . date('h:i A', strtotime($schedule['shift_end'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                        <?php echo htmlspecialchars($schedule['shift_type']); ?>
                                    </span>
                                </div>
                                
                                <?php if(!empty($schedule['patrol_route'])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <p class="text-sm text-gray-700">
                                            <i class="fas fa-route text-blue-500 mr-2"></i>
                                            Route: <?php echo htmlspecialchars($schedule['patrol_route']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 text-xs text-gray-500">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?php 
                                    $days_diff = floor((strtotime($schedule['schedule_date']) - time()) / (60 * 60 * 24));
                                    if ($days_diff == 0) {
                                        echo "Today";
                                    } elseif ($days_diff == 1) {
                                        echo "Tomorrow";
                                    } elseif ($days_diff > 1) {
                                        echo "In " . $days_diff . " days";
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-500">No upcoming shifts scheduled</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assigned Patrol Routes -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Assigned Patrol Routes</h2>
                    <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">
                        <?php echo count($patrol_routes); ?> Routes
                    </span>
                </div>
                
                <?php if(count($patrol_routes) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $route_colors = ['blue', 'green', 'purple', 'orange', 'indigo', 'pink'];
                        $color_index = 0;
                        
                        foreach($patrol_routes as $route): 
                            $color_class = $route_colors[$color_index % count($route_colors)];
                            $color_index++;
                        ?>
                            <div class="border border-gray-200 rounded-lg p-5 hover:shadow-md transition duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="h-12 w-12 bg-<?php echo $color_class; ?>-100 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-route text-<?php echo $color_class; ?>-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($route['patrol_route']); ?></p>
                                        <p class="text-sm text-gray-500">Regular patrol route</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 text-sm text-gray-600">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle text-<?php echo $color_class; ?>-500 mr-2"></i>
                                        <span>Active route</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-shield-alt text-<?php echo $color_class; ?>-500 mr-2"></i>
                                        <span>Standard patrol</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock text-<?php echo $color_class; ?>-500 mr-2"></i>
                                        <span>Regular schedule</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-route text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-500">No patrol routes assigned</p>
                        <p class="text-sm text-gray-400 mt-1">Contact administrator for route assignments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Duty Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-history text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Shifts (30 days)</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_shifts'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Hours (30 days)</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Avg. Hours/Shift</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Duty Logs -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Recent Duty Logs</h2>
            
            <?php if(count($recent_duties) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock In</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock Out</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($recent_duties as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                        <?php echo date('M d, Y', strtotime($log['clock_in'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                        <?php echo htmlspecialchars($log['shift_type'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('h:i A', strtotime($log['clock_in'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $log['clock_out'] ? date('h:i A', strtotime($log['clock_out'])) : '--:--'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                        <?php echo number_format($log['total_hours'] ?? 0, 1); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-xs rounded-full 
                                            <?php echo $log['clock_out'] ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $log['clock_out'] ? 'Completed' : 'Active'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-clipboard-list text-gray-400 text-4xl mb-3"></i>
                    <p class="text-gray-500">No duty logs found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toast notification functions
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            toastMessage.textContent = message;
            
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
                console.error('Clock in error:', error);
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
        }, 30000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show welcome message if there's a schedule today
            const todaySchedule = <?php echo json_encode($today_schedule); ?>;
            if (todaySchedule && todaySchedule.length > 0) {
                const hasActiveShift = todaySchedule.some(shift => {
                    const now = new Date();
                    const start = new Date(shift.schedule_date + ' ' + shift.shift_start);
                    const end = new Date(shift.schedule_date + ' ' + shift.shift_end);
                    return now >= start && now <= end;
                });
                
                if (hasActiveShift) {
                    showToast('You have an active shift today. Remember to clock in if you haven\'t!', 'info');
                }
            }
        });
    </script>
</body>
</html>