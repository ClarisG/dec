<?php
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$pdo = getDbConnection();

// Initialize variables with default values
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$current_duty = null;
$weekly_schedule = [];
$duty_stats = ['total_shifts' => 0, 'total_hours' => 0, 'avg_duration' => 0];
$message = '';
$message_type = '';

// Handle clock in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clock_in'])) {
        try {
            // Check if already clocked in today
            $check_stmt = $pdo->prepare("
                SELECT id FROM tanod_duty_logs 
                WHERE user_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL
            ");
            $check_stmt->execute([$tanod_id]);
            
            if ($check_stmt->rowCount() === 0) {
                // Get assigned patrol route for today
                $route_stmt = $pdo->prepare("
                    SELECT pr.id, pr.route_name, pr.checkpoints 
                    FROM patrol_routes pr
                    JOIN patrol_assignments pa ON pr.id = pa.route_id
                    WHERE pa.user_id = ? AND pa.assignment_date = CURDATE()
                    AND pa.status = 'active'
                    LIMIT 1
                ");
                $route_stmt->execute([$tanod_id]);
                $route = $route_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Clock in
                $stmt = $pdo->prepare("
                    INSERT INTO tanod_duty_logs 
                    (user_id, barangay_id, clock_in, status, patrol_route_id, created_at) 
                    VALUES (?, ?, NOW(), 'on_duty', ?, NOW())
                ");
                
                $route_id = $route ? $route['id'] : null;
                $stmt->execute([$tanod_id, $barangay_id, $route_id]);
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, 'clock_in', 'Tanod clocked in for duty', ?, NOW())
                ");
                $log_stmt->execute([$tanod_id, $_SERVER['REMOTE_ADDR']]);
                
                // Notify admin
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, priority, created_at) 
                    VALUES (?, ?, ?, 'duty_status', ?, 'low', NOW())
                ");
                
                $admin_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND barangay_id = ?");
                $admin_stmt->execute([$barangay_id]);
                $admins = $admin_stmt->fetchAll();
                
                foreach ($admins as $admin) {
                    $notif_stmt->execute([
                        $admin['id'],
                        '🟢 Tanod Clocked In',
                        "Tanod {$tanod_name} has clocked in for duty",
                        $tanod_id
                    ]);
                }
                
                $message = "✅ Successfully clocked in for duty";
                $message_type = 'success';
            } else {
                $message = "⚠️ You are already clocked in";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Clock In Error: " . $e->getMessage());
            $message = "❌ Error clocking in: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    if (isset($_POST['clock_out'])) {
        try {
            // Find active duty log
            $find_stmt = $pdo->prepare("
                SELECT id FROM tanod_duty_logs 
                WHERE user_id = ? AND clock_out IS NULL 
                ORDER BY clock_in DESC LIMIT 1
            ");
            $find_stmt->execute([$tanod_id]);
            $duty_log = $find_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($duty_log) {
                // Clock out
                $stmt = $pdo->prepare("
                    UPDATE tanod_duty_logs 
                    SET clock_out = NOW(), status = 'off_duty', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$duty_log['id']]);
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, 'clock_out', 'Tanod clocked out from duty', ?, NOW())
                ");
                $log_stmt->execute([$tanod_id, $_SERVER['REMOTE_ADDR']]);
                
                $message = "✅ Successfully clocked out from duty";
                $message_type = 'success';
            } else {
                $message = "⚠️ No active duty session found";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Clock Out Error: " . $e->getMessage());
            $message = "❌ Error clocking out: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current duty status
try {
    $status_stmt = $pdo->prepare("
        SELECT dl.*, pr.route_name, pr.checkpoints
        FROM tanod_duty_logs dl
        LEFT JOIN patrol_routes pr ON dl.patrol_route_id = pr.id
        WHERE dl.user_id = ? AND DATE(dl.clock_in) = CURDATE()
        ORDER BY dl.clock_in DESC 
        LIMIT 1
    ");
    $status_stmt->execute([$tanod_id]);
    $current_duty = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get this week's schedule
    $schedule_stmt = $pdo->prepare("
        SELECT pa.*, pr.route_name, pr.checkpoints,
               s.shift_start, s.shift_end, s.shift_name
        FROM patrol_assignments pa
        JOIN patrol_routes pr ON pa.route_id = pr.id
        JOIN patrol_shifts s ON pa.shift_id = s.id
        WHERE pa.user_id = ? 
        AND pa.assignment_date BETWEEN ? AND ?
        AND pa.status = 'active'
        ORDER BY pa.assignment_date, s.shift_start
    ");
    $schedule_stmt->execute([$tanod_id, $week_start, $week_end]);
    $weekly_schedule = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get duty statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            SUM(TIMESTAMPDIFF(HOUR, clock_in, COALESCE(clock_out, NOW()))) as total_hours,
            AVG(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))) as avg_duration
        FROM tanod_duty_logs 
        WHERE user_id = ? AND MONTH(clock_in) = MONTH(CURDATE())
    ");
    $stats_stmt->execute([$tanod_id]);
    $duty_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Duty Schedule Error: " . $e->getMessage());
    // Variables already have default values from initialization above
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
        
        .schedule-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.15);
        }
        
        .schedule-today {
            border-left-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }
        
        .schedule-upcoming {
            border-left-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .schedule-past {
            border-left-color: #6b7280;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
        
        .duty-active {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring__circle {
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 0.5s ease;
        }
        
        .checkpoint-done {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .checkpoint-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            My Duty & Patrol Schedule
                        </h1>
                        <p class="text-blue-100 mt-2">View assigned shifts and designated patrol routes</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-white text-sm">Tanod Officer</p>
                                <p class="text-white font-bold"><?php echo htmlspecialchars($tanod_name); ?></p>
                                <p class="text-white text-xs mt-1">ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg border-l-4 
            <?php echo $message_type === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 
                   ($message_type === 'warning' ? 'bg-yellow-50 border-yellow-500 text-yellow-800' : 'bg-red-50 border-red-500 text-red-800'); ?>">
            <div class="flex items-center">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 
                                      ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> 
                    text-xl mr-3"></i>
                <p class="font-bold"><?php echo $message; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Critical Data Handled -->
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-blue-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-bold text-blue-800">Critical Data Handled: Shift times, Assigned patrol routes, Real-time status (On-Duty/Off-Duty)</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Current Duty & Stats -->
            <div class="lg:col-span-1">
                <!-- Current Duty Status -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Current Duty Status</h2>
                        <?php if ($current_duty && !$current_duty['clock_out']): ?>
                            <span class="px-3 py-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-bold rounded-full duty-active">
                                <i class="fas fa-clock mr-2"></i>ON DUTY
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-gradient-to-r from-gray-500 to-gray-600 text-white text-sm font-bold rounded-full">
                                OFF DUTY
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($current_duty && !$current_duty['clock_out']): ?>
                        <div class="space-y-4">
                            <!-- Clock In Time -->
                            <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-green-800">Clocked In</p>
                                        <p class="text-2xl font-bold text-green-900 mt-1">
                                            <?php echo date('h:i A', strtotime($current_duty['clock_in'])); ?>
                                        </p>
                                        <p class="text-xs text-green-700 mt-1">
                                            <?php echo date('M d, Y', strtotime($current_duty['clock_in'])); ?>
                                        </p>
                                    </div>
                                    <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-sign-in-alt text-green-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Duration -->
                            <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-800">Duration</p>
                                        <p class="text-2xl font-bold text-blue-900 mt-1">
                                            <?php 
                                            $start = new DateTime($current_duty['clock_in']);
                                            $now = new DateTime();
                                            $interval = $start->diff($now);
                                            echo $interval->format('%hh %im');
                                            ?>
                                        </p>
                                        <p class="text-xs text-blue-700 mt-1">Time on duty</p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-hourglass-half text-blue-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patrol Route -->
                            <?php if ($current_duty['route_name']): ?>
                                <div class="p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-purple-800">Assigned Route</p>
                                            <p class="text-lg font-bold text-purple-900 mt-1">
                                                <?php echo htmlspecialchars($current_duty['route_name']); ?>
                                            </p>
                                        </div>
                                        <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-route text-purple-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Clock Out Button -->
                            <form method="POST" class="mt-4">
                                <button type="submit" name="clock_out" 
                                        class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white font-bold py-3 px-4 rounded-lg hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition flex items-center justify-center">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Clock Out
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Off Duty State -->
                        <div class="text-center py-8">
                            <div class="text-gray-300 text-5xl mb-4">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">Currently Off Duty</h3>
                            <p class="text-gray-500 mb-6">Clock in to start your shift</p>
                            
                            <form method="POST">
                                <button type="submit" name="clock_in" 
                                        class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-3 px-4 rounded-lg hover:from-green-600 hover:to-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition flex items-center justify-center">
                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                    Clock In for Duty
                                </button>
                            </form>
                            
                            <p class="text-xs text-gray-400 mt-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                Clocking in will notify admin and update your status
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Duty Statistics -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Monthly Statistics</h2>
                    
                    <div class="space-y-4">
                        <!-- Total Shifts -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-calendar-check text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Total Shifts</p>
                                    <p class="text-lg font-bold text-gray-900"><?php echo $duty_stats['total_shifts']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Hours -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-clock text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Total Hours</p>
                                    <p class="text-lg font-bold text-gray-900"><?php echo round($duty_stats['total_hours'], 1); ?>h</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Average Duration -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-chart-line text-yellow-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Avg. Duration</p>
                                    <p class="text-lg font-bold text-gray-900">
                                        <?php echo round($duty_stats['avg_duration'] / 60, 1); ?>h
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Schedule & Routes -->
            <div class="lg:col-span-2">
                <!-- Weekly Schedule -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Weekly Schedule</h2>
                        <span class="text-sm text-gray-500">
                            <?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d, Y', strtotime($week_end)); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($weekly_schedule)): ?>
                        <div class="space-y-4">
                            <?php foreach ($weekly_schedule as $day): 
                                $is_today = date('Y-m-d') === $day['assignment_date'];
                                $is_past = date('Y-m-d') > $day['assignment_date'];
                                $card_class = $is_today ? 'schedule-today' : ($is_past ? 'schedule-past' : 'schedule-upcoming');
                            ?>
                                <div class="schedule-card p-4 rounded-lg <?php echo $card_class; ?>">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div class="mb-3 md:mb-0 md:mr-4">
                                            <div class="flex items-center">
                                                <span class="text-lg font-bold text-gray-800">
                                                    <?php echo date('D, M d', strtotime($day['assignment_date'])); ?>
                                                </span>
                                                <?php if ($is_today): ?>
                                                    <span class="ml-3 px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                                        TODAY
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-2 space-y-2">
                                                <!-- Shift -->
                                                <div class="flex items-center text-sm">
                                                    <i class="fas fa-clock text-blue-500 mr-2 w-4"></i>
                                                    <span class="font-medium">
                                                        <?php echo date('h:i A', strtotime($day['shift_start'])); ?> - 
                                                        <?php echo date('h:i A', strtotime($day['shift_end'])); ?>
                                                    </span>
                                                    <span class="ml-3 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                        <?php echo htmlspecialchars($day['shift_name']); ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Route -->
                                                <?php if ($day['route_name']): ?>
                                                    <div class="flex items-center text-sm">
                                                        <i class="fas fa-route text-purple-500 mr-2 w-4"></i>
                                                        <span class="font-medium"><?php echo htmlspecialchars($day['route_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <?php if ($is_today && !$is_past): ?>
                                                <?php if ($current_duty && !$current_duty['clock_out']): ?>
                                                    <span class="px-3 py-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-bold rounded-full">
                                                        <i class="fas fa-check-circle mr-1"></i> On Duty
                                                    </span>
                                                <?php else: ?>
                                                    <form method="POST" class="inline">
                                                        <button type="submit" name="clock_in" 
                                                                class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition text-sm font-medium">
                                                            <i class="fas fa-play mr-1"></i> Start Shift
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($day['checkpoints']): ?>
                                                <button onclick="showRouteDetails(<?php echo $day['id']; ?>)" 
                                                        class="px-4 py-2 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-lg hover:from-gray-200 hover:to-gray-300 transition text-sm">
                                                    <i class="fas fa-map-marked-alt mr-1"></i> View Route
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="text-gray-300 text-5xl mb-4">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">No Schedule Assigned</h3>
                            <p class="text-gray-500">Your schedule for this week hasn't been set yet</p>
                            <p class="text-sm text-gray-400 mt-2">Contact your barangay admin for assignment</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Current Patrol Route Details -->
                <?php if ($current_duty && $current_duty['route_name'] && $current_duty['checkpoints']): 
                    $checkpoints = json_decode($current_duty['checkpoints'], true);
                ?>
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Current Patrol Route</h2>
                            <span class="px-3 py-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white text-sm font-bold rounded-full">
                                <?php echo htmlspecialchars($current_duty['route_name']); ?>
                            </span>
                        </div>
                        
                        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                            <h3 class="font-bold text-blue-800 mb-2">Route Checkpoints</h3>
                            <p class="text-sm text-blue-700">Follow this sequence during your patrol</p>
                        </div>
                        
                        <?php if (is_array($checkpoints) && !empty($checkpoints)): ?>
                            <div class="space-y-3">
                                <?php foreach ($checkpoints as $index => $checkpoint): ?>
                                    <div class="flex items-start p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="flex-shrink-0 w-8 h-8 <?php echo $index < 2 ? 'checkpoint-done' : 'checkpoint-pending'; ?> rounded-full flex items-center justify-center font-bold mr-4">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($checkpoint['name'] ?? 'Checkpoint ' . ($index + 1)); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($checkpoint['description'] ?? 'No description'); ?></p>
                                            <?php if (isset($checkpoint['coordinates'])): ?>
                                                <p class="text-xs text-gray-500 mt-2">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    Coordinates: <?php echo htmlspecialchars($checkpoint['coordinates']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($index < 2): ?>
                                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                                    <i class="fas fa-check mr-1"></i> Visited
                                                </span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                                    <i class="fas fa-clock mr-1"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                            <?php if (isset($checkpoint['eta'])): ?>
                                                <p class="text-xs text-gray-500 mt-2">ETA: <?php echo htmlspecialchars($checkpoint['eta']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Map Placeholder -->
                        <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg text-center">
                            <i class="fas fa-map text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-600">Interactive patrol map would appear here</p>
                            <p class="text-sm text-gray-400 mt-1">Real-time GPS tracking enabled while on duty</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Barangay LEIR Duty Management System v2.0 &copy; <?php echo date('Y'); ?></p>
            <p class="mt-1">Real-time status tracking and patrol coordination</p>
        </div>
    </div>
    
    <!-- Route Details Modal -->
    <div id="routeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800" id="routeModalTitle"></h3>
                    <button onclick="closeRouteModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]" id="routeModalContent"></div>
        </div>
    </div>
    
    <script>
    // Route Details Modal
    function showRouteDetails(routeId) {
        const modal = document.getElementById('routeModal');
        const title = document.getElementById('routeModalTitle');
        const content = document.getElementById('routeModalContent');
        
        // In a real implementation, this would fetch route details via AJAX
        title.textContent = 'Route Details';
        content.innerHTML = `
            <div class="space-y-4">
                <div class="flex justify-center items-center h-40">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-500"></i>
                    <span class="ml-3 text-gray-600">Loading route details...</span>
                </div>
            </div>
        `;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Simulate loading
        setTimeout(() => {
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                        <h4 class="font-bold text-blue-800 mb-2">Route Information</h4>
                        <p class="text-sm text-blue-700">This route has been assigned by the barangay admin.</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Checkpoints</p>
                            <p class="text-lg font-bold text-gray-800">8</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Estimated Time</p>
                            <p class="text-lg font-bold text-gray-800">3.5 hours</p>
                        </div>
                    </div>
                    
                    <div class="text-center py-8">
                        <p class="text-gray-500">Detailed route information is loaded when you clock in</p>
                    </div>
                </div>
            `;
        }, 1000);
    }
    
    function closeRouteModal() {
        document.getElementById('routeModal').classList.add('hidden');
        document.getElementById('routeModal').classList.remove('flex');
    }
    
    // Auto-refresh duty status
    let dutyRefreshTimer;
    
    function refreshDutyStatus() {
        const isOnDuty = "<?php echo $current_duty && !$current_duty['clock_out'] ? 'true' : 'false'; ?>";
        if (isOnDuty === 'true') {
            // Update duration counter
            const durationElement = document.querySelector('.duration-counter');
            if (durationElement) {
                // This would be updated via AJAX in a real implementation
            }
        }
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Start auto-refresh for on-duty users
        const isOnDuty = "<?php echo $current_duty && !$current_duty['clock_out'] ? 'true' : 'false'; ?>";
        if (isOnDuty === 'true') {
            dutyRefreshTimer = setInterval(refreshDutyStatus, 30000); // Every 30 seconds
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + I to clock in
            if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                const clockInBtn = document.querySelector('[name="clock_in"]');
                if (clockInBtn) clockInBtn.click();
            }
            
            // Ctrl/Cmd + O to clock out
            if ((e.ctrlKey || e.metaKey) && e.key === 'o') {
                e.preventDefault();
                const clockOutBtn = document.querySelector('[name="clock_out"]');
                if (clockOutBtn) clockOutBtn.click();
            }
        });
    });
    
    // Cleanup
    window.addEventListener('beforeunload', () => {
        clearInterval(dutyRefreshTimer);
    });
    </script>
</body>
</html>