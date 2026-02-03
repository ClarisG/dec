<?php
// admin/modules/patrol_scheduling.php - NEIGHBORHOOD PATROL SCHEDULING MODULE

// Handle new patrol schedule submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_patrol'])) {
    try {
        $insert = $conn->prepare("INSERT INTO tanod_schedules (user_id, schedule_date, shift_start, shift_end, patrol_route, notes)
            VALUES (:user_id, :schedule_date, :shift_start, :shift_end, :patrol_route, :notes)");
        $insert->execute([
            ':user_id' => $_POST['tanod_id'],
            ':schedule_date' => $_POST['schedule_date'],
            ':shift_start' => $_POST['shift_start'],
            ':shift_end' => $_POST['shift_end'],
            ':patrol_route' => $_POST['patrol_route'] ?? '',
            ':notes' => $_POST['schedule_notes'] ?? '',
        ]);
        $_SESSION['success'] = "Patrol scheduled successfully";
        header("Location: ?module=patrol_scheduling");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error scheduling patrol: " . $e->getMessage();
    }
}

// Handle save patrol route
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_route'])) {
    try {
        $route_name = $_POST['route_name'];
        $description = $_POST['route_description'];
        $estimated_duration = $_POST['estimated_duration'];
        $checkpoint_count = $_POST['checkpoint_count'];
        $route_coordinates = $_POST['route_coordinates'];
        $route_id = $_POST['route_id'] ?? null;
        
        if ($route_id) {
            // Update existing route
            $update_query = "UPDATE patrol_routes SET 
                            route_name = :route_name,
                            route_description = :description,
                            estimated_duration = :duration,
                            checkpoint_count = :checkpoints,
                            route_coordinates = :coordinates,
                            updated_at = NOW()
                            WHERE id = :id";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([
                ':route_name' => $route_name,
                ':description' => $description,
                ':duration' => $estimated_duration,
                ':checkpoints' => $checkpoint_count,
                ':coordinates' => $route_coordinates,
                ':id' => $route_id
            ]);
            
            $_SESSION['success'] = "Route updated successfully!";
        } else {
            // Insert new route
            $insert_query = "INSERT INTO patrol_routes 
                            (route_name, route_description, estimated_duration, checkpoint_count, route_coordinates)
                            VALUES (:route_name, :description, :duration, :checkpoints, :coordinates)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([
                ':route_name' => $route_name,
                ':description' => $description,
                ':duration' => $estimated_duration,
                ':checkpoints' => $checkpoint_count,
                ':coordinates' => $route_coordinates
            ]);
            
            $_SESSION['success'] = "Route added successfully!";
        }
        
        header("Location: ?module=patrol_scheduling");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error saving route: " . $e->getMessage();
    }
}

// Handle delete route
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
    try {
        $route_id = $_POST['route_id'];
        $delete_query = "DELETE FROM patrol_routes WHERE id = :id";
        $stmt = $conn->prepare($delete_query);
        $stmt->execute([':id' => $route_id]);
        
        $_SESSION['success'] = "Route deleted successfully!";
        header("Location: ?module=patrol_scheduling");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting route: " . $e->getMessage();
    }
}

// Get all Tanods
$tanods_query = "SELECT id, first_name, last_name, contact_number, is_active 
                 FROM users 
                 WHERE role = 'tanod' 
                 ORDER BY is_active DESC, first_name";
$tanods_stmt = $conn->prepare($tanods_query);
$tanods_stmt->execute();
$all_tanods = $tanods_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patrol schedules for the week (support navigation)
$baseDate = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
$start_of_week = date('Y-m-d', strtotime('monday this week', strtotime($baseDate)));
$end_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($baseDate)));

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
$routes_query = "SELECT * FROM patrol_routes ORDER BY route_name";
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
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
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
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo strtotime($schedule['schedule_date']) < time() ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo strtotime($schedule['schedule_date']) < time() ? 'Past' : 'Upcoming'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                    <input type="hidden" name="delete_schedule" value="1">
                                    <button type="submit" onclick="return confirm('Delete this schedule?')" 
                                            class="text-red-600 hover:text-red-900 px-2 py-1 hover:bg-red-50 rounded">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
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
                <input type="hidden" name="assign_patrol" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Tanod *</label>
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Date *</label>
                        <input type="date" name="schedule_date" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shift Start *</label>
                        <input type="time" name="shift_start" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shift End *</label>
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
                    <button type="submit" 
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
                                            class="text-blue-600 hover:text-blue-800 px-2 py-1 hover:bg-blue-50 rounded">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                                        <input type="hidden" name="delete_route" value="1">
                                        <button type="submit" onclick="return confirm('Delete this route?')" 
                                                class="text-red-600 hover:text-red-800 px-2 py-1 hover:bg-red-50 rounded">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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
        
        <form id="routeForm" method="POST" action="">
            <input type="hidden" id="routeId" name="route_id" value="">
            <input type="hidden" name="save_route" value="1">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Route Name *</label>
                    <input type="text" name="route_name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                           placeholder="e.g., Main Street Patrol">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="route_description" rows="2" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                              placeholder="Brief description of the route..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Duration *</label>
                        <input type="number" name="estimated_duration" min="15" max="480" step="15" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Minutes">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Checkpoint Count *</label>
                        <input type="number" name="checkpoint_count" min="1" max="20" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Number of stops">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Route Coordinates (JSON)</label>
                    <textarea name="route_coordinates" rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                              placeholder='[{"lat": 14.5995, "lng": 120.9842}, ...]'></textarea>
                    <p class="text-xs text-gray-500 mt-1">Optional: JSON array of coordinates</p>
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
function prevWeek(){
    const d = new Date('<?php echo $start_of_week; ?>');
    d.setDate(d.getDate() - 7);
    const ymd = d.toISOString().slice(0,10);
    const url = new URL(window.location.href);
    url.searchParams.set('module','patrol_scheduling');
    url.searchParams.set('week', ymd);
    window.location.href = url.toString();
}

function nextWeek(){
    const d = new Date('<?php echo $start_of_week; ?>');
    d.setDate(d.getDate() + 7);
    const ymd = d.toISOString().slice(0,10);
    const url = new URL(window.location.href);
    url.searchParams.set('module','patrol_scheduling');
    url.searchParams.set('week', ymd);
    window.location.href = url.toString();
}

function showRouteModal() {
    document.getElementById('modalRouteTitle').textContent = 'Add Patrol Route';
    document.getElementById('routeId').value = '';
    document.getElementById('routeForm').reset();
    document.getElementById('routeModal').classList.remove('hidden');
    document.getElementById('routeModal').classList.add('flex');
}

function editRoute(routeId) {
    fetch(`ajax/get_route_details.php?id=${routeId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(route => {
            document.getElementById('modalRouteTitle').textContent = 'Edit Patrol Route';
            document.getElementById('routeId').value = route.id;
            document.querySelector('[name="route_name"]').value = route.route_name || '';
            document.querySelector('[name="route_description"]').value = route.route_description || '';
            document.querySelector('[name="estimated_duration"]').value = route.estimated_duration || '';
            document.querySelector('[name="checkpoint_count"]').value = route.checkpoint_count || '';
            document.querySelector('[name="route_coordinates"]').value = route.route_coordinates || '';
            
            document.getElementById('routeModal').classList.remove('hidden');
            document.getElementById('routeModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error fetching route:', error);
            alert('Error loading route details. Please try again.');
        });
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

// Handle form submission for delete schedule
document.querySelectorAll('form[action=""]').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (this.querySelector('[name="delete_schedule"]') || this.querySelector('[name="delete_route"]')) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        }
    });
});
</script>