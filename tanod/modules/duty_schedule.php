<?php
// tanod/modules/duty_schedule.php - Professional Duty Schedule
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// AJAX handlers for duty management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDbConnection();
        
        switch ($_POST['action']) {
            case 'clock_in':
                // Check if already on duty
                $check_stmt = $pdo->prepare("SELECT id FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
                $check_stmt->execute([$tanod_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already on duty']);
                    exit();
                }
                
                // Find current schedule
                $today = date('Y-m-d');
                $current_time = date('H:i:s');
                
                $schedule_stmt = $pdo->prepare("
                    SELECT id FROM tanod_schedules 
                    WHERE user_id = ? AND schedule_date = ? AND active = 1
                    AND shift_start <= ? AND shift_end >= ?
                ");
                $schedule_stmt->execute([$tanod_id, $today, $current_time, $current_time]);
                $schedule = $schedule_stmt->fetch();
                
                $schedule_id = $schedule ? $schedule['id'] : null;
                $latitude = $_POST['latitude'] ?? null;
                $longitude = $_POST['longitude'] ?? null;
                
                // Insert duty log
                $stmt = $pdo->prepare("
                    INSERT INTO tanod_duty_logs 
                    (user_id, schedule_id, clock_in, clock_in_lat, clock_in_long, status) 
                    VALUES (?, ?, NOW(), ?, ?, 'on_duty')
                ");
                $stmt->execute([$tanod_id, $schedule_id, $latitude, $longitude]);
                
                // Update user status
                $update_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $update_stmt->execute([$tanod_id]);
                
                echo json_encode(['success' => true, 'message' => 'Clocked in successfully']);
                break;
                
            case 'clock_out':
                // Get current duty
                $stmt = $pdo->prepare("
                    SELECT id, clock_in FROM tanod_duty_logs 
                    WHERE user_id = ? AND clock_out IS NULL 
                    ORDER BY clock_in DESC LIMIT 1
                ");
                $stmt->execute([$tanod_id]);
                $duty = $stmt->fetch();
                
                if (!$duty) {
                    echo json_encode(['success' => false, 'message' => 'No active duty found']);
                    exit();
                }
                
                $latitude = $_POST['latitude'] ?? null;
                $longitude = $_POST['longitude'] ?? null;
                
                // Update duty log
                $update_stmt = $pdo->prepare("
                    UPDATE tanod_duty_logs 
                    SET clock_out = NOW(), 
                        clock_out_lat = ?, 
                        clock_out_long = ?,
                        total_hours = TIMESTAMPDIFF(HOUR, clock_in, NOW()),
                        status = 'completed'
                    WHERE id = ?
                ");
                $update_stmt->execute([$latitude, $longitude, $duty['id']]);
                
                // Update user status
                $user_stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $user_stmt->execute([$tanod_id]);
                
                echo json_encode(['success' => true, 'message' => 'Clocked out successfully']);
                break;
                
            case 'update_location':
                $latitude = $_POST['latitude'] ?? null;
                $longitude = $_POST['longitude'] ?? null;
                
                if ($latitude && $longitude) {
                    $stmt = $pdo->prepare("
                        UPDATE tanod_duty_logs 
                        SET current_lat = ?, current_long = ?, last_location_update = NOW()
                        WHERE user_id = ? AND clock_out IS NULL
                    ");
                    $stmt->execute([$latitude, $longitude, $tanod_id]);
                }
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        error_log("Duty AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error']);
    }
    exit();
}

// Main page data fetching
try {
    $pdo = getDbConnection();
    
    // Get current duty status
    $stmt = $pdo->prepare("
        SELECT dl.*, ts.schedule_date, ts.shift_start, ts.shift_end, 
               ts.patrol_route, a.area_name, ts.shift_type
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        LEFT JOIN patrol_areas a ON ts.patrol_area_id = a.id
        WHERE dl.user_id = ? AND dl.clock_out IS NULL
        ORDER BY dl.clock_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$tanod_id]);
    $current_duty = $stmt->fetch();
    
    // Today's schedule
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT ts.*, a.area_name 
        FROM tanod_schedules ts
        LEFT JOIN patrol_areas a ON ts.patrol_area_id = a.id
        WHERE ts.user_id = ? AND ts.schedule_date = ? AND ts.active = 1
        ORDER BY ts.shift_start ASC
    ");
    $stmt->execute([$tanod_id, $today]);
    $today_schedule = $stmt->fetchAll();
    
    // Upcoming schedules (next 7 days)
    $stmt = $pdo->prepare("
        SELECT ts.*, a.area_name 
        FROM tanod_schedules ts
        LEFT JOIN patrol_areas a ON ts.patrol_area_id = a.id
        WHERE ts.user_id = ? AND ts.schedule_date > CURDATE() AND ts.active = 1
        ORDER BY ts.schedule_date ASC, ts.shift_start ASC
        LIMIT 10
    ");
    $stmt->execute([$tanod_id]);
    $upcoming_schedules = $stmt->fetchAll();
    
    // Monthly statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            COALESCE(SUM(total_hours), 0) as total_hours,
            AVG(total_hours) as avg_hours
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $stats = $stmt->fetch();
    
    // This week's hours
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_hours), 0) as week_hours
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_in >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $week_stats = $stmt->fetch();
    
    // Recent duty logs
    $stmt = $pdo->prepare("
        SELECT dl.*, ts.shift_type, a.area_name 
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        LEFT JOIN patrol_areas a ON ts.patrol_area_id = a.id
        WHERE dl.user_id = ? 
        ORDER BY dl.clock_in DESC 
        LIMIT 10
    ");
    $stmt->execute([$tanod_id]);
    $duty_history = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Duty Schedule Error: " . $e->getMessage());
    $current_duty = null;
    $today_schedule = [];
    $upcoming_schedules = [];
    $duty_history = [];
    $stats = ['total_shifts' => 0, 'total_hours' => 0, 'avg_hours' => 0];
    $week_stats = ['week_hours' => 0];
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-2">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Duty & Patrol Schedule</h2>
            <p class="text-gray-600 text-sm mt-1">Manage shifts and track duty hours</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($tanod_name); ?></p>
            <p class="text-xs text-gray-500">ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
        </div>
    </div>
    
    <!-- Current Status Card -->
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div class="mb-4 md:mb-0">
                <h3 class="text-lg font-bold text-gray-800 mb-3">Current Duty Status</h3>
                <div class="flex items-center">
                    <span class="w-3 h-3 rounded-full <?php echo $current_duty ? 'bg-green-500 pulse-dot' : 'bg-gray-400'; ?> mr-3"></span>
                    <span class="text-xl font-bold <?php echo $current_duty ? 'text-green-600' : 'text-gray-600'; ?>">
                        <?php echo $current_duty ? 'ON DUTY' : 'OFF DUTY'; ?>
                    </span>
                    <?php if($current_duty): ?>
                        <span class="ml-4 text-sm text-gray-600">
                            <i class="far fa-clock mr-1"></i>
                            Since <?php echo date('h:i A', strtotime($current_duty['clock_in'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <?php if($current_duty): ?>
                    <button type="button" onclick="clockOut()" 
                            class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-red-600 text-white font-medium rounded-lg hover:from-red-600 hover:to-red-700 transition shadow-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                    </button>
                <?php else: ?>
                    <button type="button" onclick="clockIn()" 
                            class="px-5 py-2.5 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-lg hover:from-green-600 hover:to-green-700 transition shadow-sm">
                        <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if($current_duty): ?>
        <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg border border-blue-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-600 mb-1">Current Shift</p>
                    <p class="text-sm font-medium text-gray-800">
                        <?php echo $current_duty['shift_start'] ? date('h:i A', strtotime($current_duty['shift_start'])) : 'Flexible'; ?>
                        - 
                        <?php echo $current_duty['shift_end'] ? date('h:i A', strtotime($current_duty['shift_end'])) : 'Flexible'; ?>
                    </p>
                </div>
                <?php if($current_duty['area_name']): ?>
                <div>
                    <p class="text-xs text-gray-600 mb-1">Patrol Area</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($current_duty['area_name']); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-xs text-gray-600 mb-1">Shift Type</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($current_duty['shift_type'] ?? 'Regular'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Stats and Schedule Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Statistics -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-5">Performance Stats</h3>
                <div class="space-y-5">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Monthly Shifts</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_shifts']; ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Monthly Hours</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_hours'], 1); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Weekly Hours</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($week_stats['week_hours'], 1); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Average per Shift</p>
                        <p class="text-2xl font-bold text-indigo-600">
                            <?php echo $stats['avg_hours'] ? number_format($stats['avg_hours'], 1) : '0.0'; ?>h
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Duty History -->
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Duty Logs</h3>
                <?php if (!empty($duty_history)): ?>
                <div class="space-y-3">
                    <?php foreach ($duty_history as $log): ?>
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    <?php echo date('M d', strtotime($log['clock_in'])); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('h:i A', strtotime($log['clock_in'])); ?> - 
                                    <?php echo $log['clock_out'] ? date('h:i A', strtotime($log['clock_out'])) : 'Present'; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-blue-600">
                                    <?php echo $log['total_hours'] ? number_format($log['total_hours'], 1) . 'h' : 'Active'; ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $log['shift_type'] ?? 'Shift'; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">No duty logs found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Today's Schedule -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-gray-800">Today's Schedule</h3>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                        <?php echo date('M j, Y'); ?>
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
                            } elseif ($current_time >= $shift_start && $current_time <= $shift_end) {
                                $status = 'active';
                                $border_color = 'border-green-300';
                                $bg_color = 'bg-green-50';
                            } else {
                                $status = 'completed';
                                $border_color = 'border-gray-300';
                                $bg_color = 'bg-gray-50';
                            }
                            ?>
                            
                            <div class="p-4 rounded-lg border <?php echo $border_color; ?> <?php echo $bg_color; ?>">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="flex items-center mb-2">
                                            <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($shift['shift_type']); ?></h4>
                                            <span class="ml-3 px-2 py-1 text-xs rounded-full font-medium 
                                                <?php echo $status === 'active' ? 'bg-green-100 text-green-800' : 
                                                       ($status === 'upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600 space-x-4">
                                            <span>
                                                <i class="far fa-clock mr-1.5"></i>
                                                <?php echo date('h:i A', $shift_start); ?> - <?php echo date('h:i A', $shift_end); ?>
                                            </span>
                                            <?php if($shift['area_name']): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt mr-1.5"></i>
                                                <?php echo htmlspecialchars($shift['area_name']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if($shift['patrol_route']): ?>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-route mr-1"></i>
                                            <?php echo htmlspecialchars($shift['patrol_route']); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-300 text-4xl mb-3">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <p class="text-gray-500 text-sm">No shifts scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Upcoming Schedule -->
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200 mt-6">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-gray-800">Upcoming Schedule</h3>
                    <span class="px-3 py-1 bg-gray-100 text-gray-700 text-sm font-medium rounded-full">
                        Next 10 Days
                    </span>
                </div>
                
                <?php if(count($upcoming_schedules) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                                    <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                                    <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($upcoming_schedules as $schedule): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 text-sm text-gray-700">
                                            <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                                        </td>
                                        <td class="py-3 text-sm font-medium text-gray-800">
                                            <?php echo htmlspecialchars($schedule['shift_type']); ?>
                                        </td>
                                        <td class="py-3 text-sm text-gray-600">
                                            <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . date('h:i A', strtotime($schedule['shift_end'])); ?>
                                        </td>
                                        <td class="py-3 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($schedule['area_name'] ?? 'TBA'); ?>
                                        </td>
                                        <td class="py-3 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($schedule['patrol_route'] ?? 'General'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500 text-sm">No upcoming shifts scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Data Protection Notice -->
    <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-shield-alt text-blue-500 mr-3"></i>
            <div>
                <p class="text-sm font-medium text-gray-800">Duty Data Protection</p>
                <p class="text-xs text-gray-600">All duty logs include GPS coordinates, timestamps, and shift details for audit compliance and real-time monitoring.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Clock In/Out functionality
async function clockIn() {
    const button = document.querySelector('[onclick="clockIn()"]');
    const originalText = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    
    try {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async function(position) {
                const lat = position.coords.latitude.toFixed(6);
                const long = position.coords.longitude.toFixed(6);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=clock_in&latitude=${lat}&longitude=${long}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Clocked in successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message, 'error');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            }, function(error) {
                performClockIn(button, originalText);
            });
        } else {
            performClockIn(button, originalText);
        }
    } catch (error) {
        showToast('Network error', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

async function performClockIn(button, originalText) {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=clock_in'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Clocked in successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    } catch (error) {
        showToast('Network error', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

async function clockOut() {
    if (!confirm('Are you sure you want to clock out?')) return;
    
    const button = document.querySelector('[onclick="clockOut()"]');
    const originalText = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=clock_out'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Clocked out successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    } catch (error) {
        showToast('Network error', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// Auto-update location every 30 seconds if on duty
<?php if($current_duty): ?>
setInterval(() => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude.toFixed(6);
            const long = position.coords.longitude.toFixed(6);
            
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_location&latitude=${lat}&longitude=${long}`
            });
        });
    }
}, 30000);
<?php endif; ?>

// Toast notification
function showToast(message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    
    let bgColor;
    switch(type) {
        case 'success': bgColor = 'bg-green-500'; break;
        case 'error': bgColor = 'bg-red-500'; break;
        case 'warning': bgColor = 'bg-yellow-500'; break;
        default: bgColor = 'bg-blue-500';
    }
    
    toast.id = toastId;
    toast.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
    toast.innerHTML = `
        <div class="flex items-center">
            <span class="font-medium">${message}</span>
            <button onclick="document.getElementById('${toastId}').remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.getElementById(toastId)) {
                document.getElementById(toastId).remove();
            }
        }, 300);
    }, 3000);
}

// Prevent accidental refresh when on duty
window.addEventListener('beforeunload', function (e) {
    <?php if($current_duty): ?>
    e.preventDefault();
    e.returnValue = 'You are currently on duty. Are you sure you want to leave?';
    return e.returnValue;
    <?php endif; ?>
});
</script>