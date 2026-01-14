<?php
// super_admin/modules/patrol_override.php

// Get all tanods
$tanods_query = "SELECT u.*, 
                        ts.status as duty_status,
                        ts.last_updated,
                        (SELECT COUNT(*) FROM tanod_duty_logs WHERE user_id = u.id AND DATE(clock_in) = CURDATE()) as today_shifts
                 FROM users u
                 LEFT JOIN tanod_status ts ON u.id = ts.user_id
                 WHERE u.role = 'tanod'
                 ORDER BY u.is_active DESC, u.first_name";
$tanods_stmt = $conn->prepare($tanods_query);
$tanods_stmt->execute();
$tanods = $tanods_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patrol schedules
$schedules_query = "SELECT ts.*, 
                           u.first_name, u.last_name,
                           a.first_name as assigned_by_first, a.last_name as assigned_by_last
                    FROM tanod_schedules ts
                    JOIN users u ON ts.user_id = u.id
                    LEFT JOIN users a ON ts.assigned_by = a.id
                    WHERE ts.schedule_date >= CURDATE()
                    ORDER BY ts.schedule_date, ts.shift_start";
$schedules_stmt = $conn->prepare($schedules_query);
$schedules_stmt->execute();
$schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patrol routes
$routes_query = "SELECT * FROM patrol_routes WHERE is_active = 1 ORDER BY priority_level DESC, route_name";
$routes_stmt = $conn->prepare($routes_query);
$routes_stmt->execute();
$routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get duty logs for today
$today_logs_query = "SELECT tdl.*, 
                            u.first_name, u.last_name
                     FROM tanod_duty_logs tdl
                     JOIN users u ON tdl.user_id = u.id
                     WHERE DATE(tdl.created_at) = CURDATE()
                     ORDER BY tdl.clock_in DESC";
$today_logs_stmt = $conn->prepare($today_logs_query);
$today_logs_stmt->execute();
$today_logs = $today_logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Patrol & Duty Control</h2>
                <p class="text-gray-600 mt-2">Assign/override Tanod schedules, patrol routes, and duty status in real-time</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="createSchedule()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-plus mr-2"></i> New Schedule
                </button>
                <button onclick="bulkAssign()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-users mr-2"></i> Bulk Assign
                </button>
            </div>
        </div>

        <!-- Tanod Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php
            $on_duty = array_filter($tanods, function($t) { return ($t['duty_status'] ?? '') === 'On-Duty'; });
            $off_duty = array_filter($tanods, function($t) { return ($t['duty_status'] ?? '') === 'Off-Duty'; });
            $active = array_filter($tanods, function($t) { return $t['is_active'] == 1; });
            ?>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo count($on_duty); ?></div>
                <div class="text-sm text-gray-600">On Duty</div>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-gray-700"><?php echo count($off_duty); ?></div>
                <div class="text-sm text-gray-600">Off Duty</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo count($active); ?></div>
                <div class="text-sm text-gray-600">Active Tanods</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo count($schedules); ?></div>
                <div class="text-sm text-gray-600">Upcoming Shifts</div>
            </div>
        </div>
    </div>

    <!-- Tanod Grid -->
    <div class="glass-card rounded-xl p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Tanod Force Management</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($tanods as $tanod): ?>
            <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 mr-3">
                            <?php
                            $profile_pic = "../uploads/profile_pictures/" . ($tanod['profile_picture'] ?? '');
                            if (!empty($tanod['profile_picture']) && file_exists($profile_pic)):
                            ?>
                                <img src="<?php echo $profile_pic; ?>" 
                                     alt="Profile" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($tanod['barangay'] ?? 'No barangay'); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center justify-end mb-1">
                            <div class="status-indicator <?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'status-active' : 'status-inactive'; ?>"></div>
                            <span class="text-xs <?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'text-green-600' : 'text-gray-500'; ?> ml-1">
                                <?php echo $tanod['duty_status'] ?? 'Off-Duty'; ?>
                            </span>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full <?php echo $tanod['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $tanod['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Today's Shifts:</span>
                        <span class="font-medium"><?php echo $tanod['today_shifts'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Last Updated:</span>
                        <span class="font-medium">
                            <?php echo $tanod['last_updated'] ? date('H:i', strtotime($tanod['last_updated'])) : 'N/A'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="flex space-x-2 mt-4">
                    <button onclick="toggleTanodDuty(<?php echo $tanod['id']; ?>, '<?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'off' : 'on'; ?>')"
                            class="flex-1 px-3 py-2 text-sm rounded-lg <?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                        <i class="fas <?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?> mr-1"></i>
                        <?php echo ($tanod['duty_status'] ?? '') === 'On-Duty' ? 'Clock Out' : 'Clock In'; ?>
                    </button>
                    <button onclick="assignSchedule(<?php echo $tanod['id']; ?>)"
                            class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm">
                        <i class="fas fa-calendar-plus"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Patrol Schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Schedules -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Upcoming Patrol Schedules</h3>
                <button onclick="createSchedule()"
                        class="px-3 py-1 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add
                </button>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($schedules as $schedule): ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?></p>
                            <p class="text-sm text-gray-500">
                                <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?> • 
                                <?php echo date('g:i A', strtotime($schedule['shift_start'])) . ' - ' . date('g:i A', strtotime($schedule['shift_end'])); ?>
                            </p>
                        </div>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                            <?php echo ucfirst($schedule['shift_type'] ?? 'Regular'); ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center text-sm">
                        <div>
                            <p class="text-gray-500">Route: <span class="font-medium"><?php echo htmlspecialchars($schedule['patrol_route'] ?? 'Not assigned'); ?></span></p>
                            <?php if ($schedule['assigned_by_first']): ?>
                            <p class="text-gray-500 text-xs">Assigned by: <?php echo htmlspecialchars($schedule['assigned_by_first'] . ' ' . $schedule['assigned_by_last']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="editSchedule(<?php echo $schedule['id']; ?>)"
                                    class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>)"
                                    class="p-1 text-red-600 hover:bg-red-50 rounded">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($schedules)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar text-gray-300 text-3xl mb-3"></i>
                    <p class="text-gray-500">No upcoming schedules</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Duty Log -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Today's Duty Log</h3>
                <span class="text-sm text-gray-500"><?php echo date('F d, Y'); ?></span>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($today_logs as $log): ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-user text-blue-600 text-sm"></i>
                            </div>
                            <p class="font-medium text-gray-800 text-sm">
                                <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-800">
                                <?php echo date('g:i A', strtotime($log['clock_in'])); ?>
                                <?php if ($log['clock_out']): ?>
                                - <?php echo date('g:i A', strtotime($log['clock_out'])); ?>
                                <?php else: ?>
                                <span class="text-green-600">(On Duty)</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($log['clock_out']): ?>
                            <?php
                            $duration = strtotime($log['clock_out']) - strtotime($log['clock_in']);
                            $hours = floor($duration / 3600);
                            $minutes = floor(($duration % 3600) / 60);
                            ?>
                            <p class="text-xs text-gray-500"><?php echo $hours ?>h <?php echo $minutes ?>m</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($log['location_lat'] && $log['location_lng']): ?>
                    <p class="text-xs text-gray-500">Location: <?php echo $log['location_lat'] ?>, <?php echo $log['location_lng'] ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($today_logs)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-history text-gray-300 text-3xl mb-3"></i>
                    <p class="text-gray-500">No duty logs for today</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Patrol Routes -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Patrol Routes</h3>
            <button onclick="createRoute()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                <i class="fas fa-plus mr-2"></i> New Route
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($routes as $route): ?>
            <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($route['route_name']); ?></h4>
                        <p class="text-sm text-gray-500">Zone: <?php echo htmlspecialchars($route['zone_assigned']); ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                        <?php echo $route['priority_level'] === 'high' ? 'bg-red-100 text-red-800' :
                               ($route['priority_level'] === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                               'bg-green-100 text-green-800'); ?>">
                        <?php echo ucfirst($route['priority_level']); ?>
                    </span>
                </div>
                
                <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($route['description']); ?></p>
                
                <div class="flex justify-between items-center text-sm">
                    <div>
                        <p class="text-gray-500">Est. Time:</p>
                        <p class="font-medium"><?php echo $route['estimated_time']; ?> hours</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="assignRoute(<?php echo $route['id']; ?>)"
                                class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm">
                            Assign
                        </button>
                        <button onclick="editRoute(<?php echo $route['id']; ?>)"
                                class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm">
                            Edit
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function toggleTanodDuty(tanodId, action) {
    fetch('../ajax/toggle_duty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: tanodId,
            action: action,
            override: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to update duty status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update duty status');
    });
}

function createSchedule() {
    const content = `
        <form method="POST" action="../handlers/create_schedule.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Tanod</label>
                    <select name="user_id" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Choose Tanod...</option>
                        <?php foreach ($tanods as $tanod): ?>
                        <option value="<?php echo $tanod['id']; ?>">
                            <?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Date</label>
                        <input type="date" name="schedule_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shift Type</label>
                        <select name="shift_type" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="regular">Regular</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" name="shift_start" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="time" name="shift_end" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patrol Route</label>
                    <select name="patrol_route" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select Route...</option>
                        <?php foreach ($routes as $route): ?>
                        <option value="<?php echo htmlspecialchars($route['route_name']); ?>">
                            <?php echo htmlspecialchars($route['route_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Create Schedule
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function assignSchedule(tanodId) {
    const content = `
        <form method="POST" action="../handlers/assign_schedule.php">
            <input type="hidden" name="user_id" value="${tanodId}">
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Date</label>
                        <input type="date" name="schedule_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shift Type</label>
                        <select name="shift_type" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="regular">Regular</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" name="shift_start" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="time" name="shift_end" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patrol Route</label>
                    <select name="patrol_route" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select Route...</option>
                        <?php foreach ($routes as $route): ?>
                        <option value="<?php echo htmlspecialchars($route['route_name']); ?>">
                            <?php echo htmlspecialchars($route['route_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Assign Schedule
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function createRoute() {
    const content = `
        <form method="POST" action="../handlers/create_route.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Route Name</label>
                    <input type="text" name="route_name" required class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zone Assigned</label>
                        <input type="text" name="zone" required class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority Level</label>
                        <select name="priority" required class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Time (hours)</label>
                    <input type="number" step="0.5" name="estimated_time" 
                           class="w-full p-3 border border-gray-300 rounded-lg" placeholder="2.5">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Create Route
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}
</script>