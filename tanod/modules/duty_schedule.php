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

try {
    $pdo = getDbConnection();
    
    // AJAX handlers (same as before - keep functionality)
    // ... [Keep all your existing AJAX handlers and functions]
    
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
    
    // Get upcoming schedules
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_schedules 
        WHERE user_id = ? AND schedule_date >= CURDATE() AND active = 1
        ORDER BY schedule_date ASC, shift_start ASC
        LIMIT 7
    ");
    $stmt->execute([$tanod_id]);
    $schedules = $stmt->fetchAll();
    
    // Get duty statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            COALESCE(SUM(total_hours), 0) as total_hours
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_out IS NOT NULL
        AND clock_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Duty Schedule Error: " . $e->getMessage());
    $current_duty = null;
    $schedules = [];
    $today_schedule = [];
    $stats = ['total_shifts' => 0, 'total_hours' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Schedule - Barangay LEIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-on-duty { 
            background-color: #10B981; 
            animation: pulse 2s infinite;
        }
        .status-off-duty { 
            background-color: #6B7280; 
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .shift-card {
            transition: all 0.2s ease;
            border-left: 3px solid;
        }
        
        .shift-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Duty & Patrol Schedule</h1>
                    <p class="text-blue-100 text-sm mt-1">Manage your shifts and track duty hours</p>
                </div>
                <div class="text-right text-white">
                    <p class="text-sm"><?php echo htmlspecialchars($tanod_name); ?></p>
                    <p class="text-xs opacity-80">ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Current Status -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Duty Status Card -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 mb-2">Current Duty Status</h2>
                        <div class="flex items-center">
                            <span class="status-indicator <?php echo $current_duty ? 'status-on-duty' : 'status-off-duty'; ?>"></span>
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
                    
                    <div class="mt-4 md:mt-0">
                        <?php if($current_duty): ?>
                            <button type="button" onclick="clockOut()" 
                                    class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white font-bold rounded-lg hover:from-red-700 hover:to-red-800 transition duration-200 flex items-center">
                                <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="clockIn()" 
                                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold rounded-lg hover:from-green-700 hover:to-green-800 transition duration-200 flex items-center">
                                <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if($current_duty): ?>
                <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Current Shift</p>
                            <p class="font-medium text-gray-800">
                                <?php echo $current_duty['shift_start'] ? date('h:i A', strtotime($current_duty['shift_start'])) : 'Not Scheduled'; ?>
                                - 
                                <?php echo $current_duty['shift_end'] ? date('h:i A', strtotime($current_duty['shift_end'])) : 'N/A'; ?>
                            </p>
                        </div>
                        <?php if($current_duty['patrol_route']): ?>
                        <div>
                            <p class="text-sm text-gray-600">Assigned Route</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($current_duty['patrol_route']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Monthly Summary</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600">Shifts Completed</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_shifts']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Hours</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_hours'], 1); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Average per Shift</p>
                        <p class="text-2xl font-bold text-purple-600">
                            <?php echo $stats['total_shifts'] > 0 ? number_format($stats['total_hours'] / $stats['total_shifts'], 1) : '0.0'; ?>h
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Schedule -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-800">Today's Schedule</h2>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
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
                        
                        <div class="shift-card border-l-3 <?php echo $border_color; ?> <?php echo $bg_color; ?> p-4 rounded-r-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($shift['shift_type']); ?> Shift</h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="far fa-clock mr-2"></i>
                                        <?php echo date('h:i A', $shift_start); ?> - <?php echo date('h:i A', $shift_end); ?>
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="px-3 py-1 text-xs rounded-full font-medium 
                                        <?php echo $status === 'active' ? 'bg-green-100 text-green-800' : 
                                               ($status === 'upcoming' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                    <?php if($status === 'active' && !$current_duty): ?>
                                        <button onclick="clockIn()" 
                                                class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                            <i class="fas fa-play mr-1"></i> Start
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if(!empty($shift['patrol_route'])): ?>
                                <div class="mt-3 pt-3 border-t border-gray-200">
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
                    <div class="text-gray-300 text-4xl mb-3">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No shifts scheduled for today</p>
                    <p class="text-sm text-gray-400 mt-1">Contact administrator for assignments</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Upcoming Schedule -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-800">Upcoming Schedule</h2>
                <span class="px-3 py-1 bg-gray-100 text-gray-700 text-sm font-medium rounded-full">
                    Next 7 Days
                </span>
            </div>
            
            <?php if(count($schedules) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            $displayed = 0;
                            foreach($schedules as $schedule): 
                                if($schedule['schedule_date'] != $today):
                                    $displayed++;
                                    $days_diff = floor((strtotime($schedule['schedule_date']) - time()) / (60 * 60 * 24));
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                                        <?php if ($days_diff == 1): ?>
                                            <span class="ml-2 text-xs text-blue-600 font-medium">Tomorrow</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($schedule['shift_type']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . date('h:i A', strtotime($schedule['shift_end'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($schedule['patrol_route'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-3 py-1 text-xs rounded-full font-medium bg-blue-100 text-blue-800">
                                            Scheduled
                                        </span>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if ($displayed === 0): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-calendar-alt text-3xl mb-3 block"></i>
                                        <p>No upcoming shifts scheduled</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-300 text-4xl mb-3">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No upcoming shifts scheduled</p>
                    <p class="text-sm text-gray-400 mt-1">Contact administrator for schedule assignments</p>
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