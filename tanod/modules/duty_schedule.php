<?php
require_once '../../config/database.php';

$tanod_id = $_SESSION['user_id'];

// Get current schedule
$stmt = $pdo->prepare("
    SELECT * FROM tanod_schedules 
    WHERE user_id = ? AND schedule_date >= CURDATE()
    ORDER BY schedule_date ASC, shift_start ASC
    LIMIT 7
");
$stmt->execute([$tanod_id]);
$schedules = $stmt->fetchAll();

// Get current duty status
$stmt = $pdo->prepare("SELECT * FROM tanod_duty_logs WHERE user_id = ? ORDER BY clock_in DESC LIMIT 1");
$stmt->execute([$tanod_id]);
$current_duty = $stmt->fetch();
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">My Duty & Patrol Schedule</h2>
    <p class="text-gray-600">View assigned shifts and designated routes</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Duty Status Card -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Current Duty Status</h3>
            <button onclick="toggleDuty()" class="px-4 py-2 <?php echo ($current_duty && $current_duty['clock_out'] === null) ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white rounded-lg font-medium">
                <?php echo ($current_duty && $current_duty['clock_out'] === null) ? 'Clock Out' : 'Clock In'; ?>
            </button>
        </div>
        
        <div class="space-y-4">
            <?php if($current_duty && $current_duty['clock_out'] === null): ?>
                <div class="p-4 bg-green-50 rounded-lg">
                    <p class="text-green-700 font-medium">Currently On Duty</p>
                    <p class="text-sm text-green-600">Clocked in at: <?php echo date('h:i A', strtotime($current_duty['clock_in'])); ?></p>
                </div>
            <?php else: ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-gray-700 font-medium">Currently Off Duty</p>
                    <p class="text-sm text-gray-600">Ready to clock in for your next shift</p>
                </div>
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Real-time Status Tracker</label>
                <div class="relative">
                    <div class="flex items-center">
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full <?php echo ($current_duty && $current_duty['clock_out'] === null) ? 'bg-green-500 w-full' : 'bg-gray-500 w-1/4'; ?>"></div>
                        </div>
                        <span class="ml-4 text-sm font-medium <?php echo ($current_duty && $current_duty['clock_out'] === null) ? 'text-green-600' : 'text-gray-600'; ?>">
                            <?php echo ($current_duty && $current_duty['clock_out'] === null) ? 'On Patrol' : 'Standing By'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Shifts -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Shifts</h3>
        
        <?php if(count($schedules) > 0): ?>
            <div class="space-y-4">
                <?php foreach($schedules as $schedule): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium text-gray-800">
                                    <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . date('h:i A', strtotime($schedule['shift_end'])); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                <?php echo htmlspecialchars($schedule['shift_type']); ?>
                            </span>
                        </div>
                        <?php if($schedule['patrol_route']): ?>
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-route text-blue-500 mr-2"></i>
                                    Route: <?php echo htmlspecialchars($schedule['patrol_route']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-calendar-times text-gray-400 text-4xl mb-3"></i>
                <p class="text-gray-500">No upcoming shifts scheduled</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assigned Routes -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Assigned Patrol Routes</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-route text-blue-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">Main Barangay Road</p>
                        <p class="text-xs text-gray-500">Primary Route</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600">Covers all major intersections and commercial areas</p>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-home text-green-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">Residential Area</p>
                        <p class="text-xs text-gray-500">Secondary Route</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600">Residential zones and community centers</p>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-school text-purple-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">School Zone</p>
                        <p class="text-xs text-gray-500">Special Route</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600">School perimeter and adjacent streets</p>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 p-4 bg-blue-50 rounded-lg">
    <div class="flex items-center">
        <i class="fas fa-info-circle text-blue-600 mr-3"></i>
        <div>
            <p class="text-sm text-blue-800 font-medium">Critical Data Handled</p>
            <p class="text-xs text-blue-700">Shift times, Assigned patrol routes, Real-time status (On-Duty/Off-Duty)</p>
        </div>
    </div>
</div>