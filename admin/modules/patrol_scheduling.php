<?php
// admin/modules/patrol_scheduling.php - NEIGHBORHOOD PATROL SCHEDULING MODULE

// Get all Tanods
$tanods_query = "SELECT id, first_name, last_name, contact_number, is_active 
                 FROM users 
                 WHERE role = 'tanod' 
                 ORDER BY is_active DESC, first_name";
$tanods_stmt = $conn->prepare($tanods_query);
$tanods_stmt->execute();
$all_tanods = $tanods_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patrol schedules for the week
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$end_of_week = date('Y-m-d', strtotime('sunday this week'));

$schedules_query = "SELECT ts.*, u.first_name, u.last_name,
                           CONCAT(u.first_name, ' ', u.last_name) as tanod_name
                    FROM tanod_schedules ts
                    LEFT JOIN users u ON ts.user_id = u.id
                    WHERE ts.schedule_date BETWEEN :start_date AND :end_date
                    ORDER BY ts.schedule_date, ts.shift_start";
$schedules_stmt = $conn->prepare($schedules_query);
$schedules_stmt->execute([
    ':start_date' => $start_of_week,
    ':end_date' => $end_of_week
]);
$schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get predefined patrol routes
$routes_query = "SELECT * FROM patrol_routes WHERE is_active = 1 ORDER BY route_name";
$routes_stmt = $conn->prepare($routes_query);
$routes_stmt->execute();
$patrol_routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Schedule Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Tanods</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($all_tanods, fn($t) => $t['is_active'])); ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-user-shield text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Available for scheduling
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">This Week's Shifts</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count($schedules); ?>
                    </h3>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Scheduled patrols
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Patrol Routes</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count($patrol_routes); ?>
                    </h3>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-route text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Predefined routes
            </div>
        </div>
    </div>
    
    <!-- Weekly Schedule Calendar -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Weekly Patrol Schedule</h2>
            <div class="flex space-x-2">
                <button onclick="prevWeek()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="px-4 py-2 font-medium text-gray-700">
                    <?php echo date('F d', strtotime($start_of_week)) . ' - ' . date('F d, Y', strtotime($end_of_week)); ?>
                </span>
                <button onclick="nextWeek()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanod</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patrol Route</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($schedules as $schedule): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-800 font-medium text-xs">
                                            <?php echo strtoupper(substr($schedule['first_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($schedule['tanod_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('h:i A', strtotime($schedule['shift_start'])) . ' - ' . 
                                       date('h:i A', strtotime($schedule['shift_end'])); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($schedule['patrol_route']); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="status-badge <?php echo $schedule['is_completed'] ? 'status-success' : 
                                                                 (strtotime($schedule['schedule_date']) < time() ? 'status-warning' : 'status-active'); ?>">
                                    <?php echo $schedule['is_completed'] ? 'Completed' : 
                                           (strtotime($schedule['schedule_date']) < time() ? 'Missed' : 'Scheduled'); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="editSchedule(<?php echo $schedule['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Quick Schedule Assignment -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Schedule New Patrol</h3>
            
            <form method="POST" action="" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Tanod</label>
                        <select name="tanod_id" required 
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Choose Tanod...</option>
                            <?php foreach($all_tanods as $tanod): 
                                if ($tanod['is_active']): ?>
                                    <option value="<?php echo $tanod['id']; ?>">
                                        <?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Date</label>
                        <input type="date" name="schedule_date" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shift Start</label>
                        <input type="time" name="shift_start" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shift End</label>
                        <input type="time" name="shift_end" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Patrol Route</label>
                    <select name="patrol_route" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Select patrol route...</option>
                        <?php foreach($patrol_routes as $route): ?>
                            <option value="<?php echo htmlspecialchars($route['route_name']); ?>">
                                <?php echo htmlspecialchars($route['route_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                    <textarea name="schedule_notes" rows="2" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                              placeholder="Any special instructions..."></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="assign_patrol" 
                            class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Schedule Patrol
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Patrol Routes Management -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Patrol Routes</h3>
                <button onclick="showRouteModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                    <i class="fas fa-plus mr-2"></i>Add Route
                </button>
            </div>
            
            <div class="space-y-3">
                <?php if (!empty($patrol_routes)): ?>
                    <?php foreach($patrol_routes as $route): ?>
                        <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($route['route_name']); ?></span>
                                    <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($route['route_description']); ?></p>
                                </div>
                                <span class="text-sm text-gray-500">
                                    <?php echo $route['estimated_duration']; ?> mins
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <div>
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo $route['checkpoint_count']; ?> checkpoints
                                </div>
                                <div class="space-x-2">
                                    <button onclick="editRoute(<?php echo $route['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteRoute(<?php echo $route['id']; ?>)" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-route text-3xl mb-2"></i>
                        <p>No patrol routes defined</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Route Modal -->
<div id="routeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalRouteTitle" class="text-xl font-bold text-gray-800">Add Patrol Route</h3>
            <button onclick="closeRouteModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="routeForm" onsubmit="saveRoute(event)">
            <input type="hidden" id="routeId" name="id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Route Name</label>
                    <input type="text" name="route_name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="route_description" rows="2" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Duration</label>
                        <input type="number" name="estimated_duration" min="15" max="480" step="15" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Minutes">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Checkpoint Count</label>
                        <input type="number" name="checkpoint_count" min="1" max="20" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Number of stops">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Route Coordinates</label>
                    <textarea name="route_coordinates" rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                              placeholder="JSON coordinates or instructions..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Format: {"lat": 14.5995, "lng": 120.9842}, ...</p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeRouteModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Save Route
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRouteModal() {
    document.getElementById('modalRouteTitle').textContent = 'Add Patrol Route';
    document.getElementById('routeId').value = '';
    document.getElementById('routeForm').reset();
    document.getElementById('routeModal').classList.remove('hidden');
    document.getElementById('routeModal').classList.add('flex');
}

function editRoute(routeId) {
    fetch(`handlers/get_route_details.php?id=${routeId}`)
        .then(response => response.json())
        .then(route => {
            document.getElementById('modalRouteTitle').textContent = 'Edit Patrol Route';
            document.getElementById('routeId').value = route.id;
            document.getElementById('routeForm').route_name.value = route.route_name;
            document.getElementById('routeForm').route_description.value = route.route_description;
            document.getElementById('routeForm').estimated_duration.value = route.estimated_duration;
            document.getElementById('routeForm').checkpoint_count.value = route.checkpoint_count;
            document.getElementById('routeForm').route_coordinates.value = route.route_coordinates;
            
            document.getElementById('routeModal').classList.remove('hidden');
            document.getElementById('routeModal').classList.add('flex');
        });
}

function saveRoute(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('handlers/save_patrol_route.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Route saved successfully!');
            closeRouteModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteRoute(routeId) {
    if (confirm('Are you sure you want to delete this patrol route?')) {
        fetch('handlers/delete_patrol_route.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: routeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function editSchedule(scheduleId) {
    // Load and populate edit form
    console.log('Edit schedule:', scheduleId);
    // Similar implementation as editRoute
}

function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this patrol schedule?')) {
        fetch('handlers/delete_patrol_schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: scheduleId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function closeRouteModal() {
    document.getElementById('routeModal').classList.add('hidden');
    document.getElementById('routeModal').classList.remove('flex');
}

window.onclick = function(event) {
    const routeModal = document.getElementById('routeModal');
    if (event.target == routeModal) {
        closeRouteModal();
    }
}
</script>