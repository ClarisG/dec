<?php
// admin/modules/tanod_tracker.php - TANOD ASSIGNMENT TRACKER MODULE

// Get active Tanods with their status - REMOVED current_address as it doesn't exist in the table
$tanods_query = "SELECT u.id, u.first_name, u.last_name, u.contact_number,
                        ts.status as duty_status, ts.last_updated,
                        td.clock_in, td.clock_out, td.location_lat, td.location_lng
                 FROM users u
                 LEFT JOIN tanod_status ts ON u.id = ts.user_id
                 LEFT JOIN tanod_duty_logs td ON u.id = td.user_id AND DATE(td.clock_in) = CURDATE()
                 WHERE u.role = 'tanod' AND u.is_active = 1
                 ORDER BY ts.status DESC, u.first_name";
$tanods_stmt = $conn->prepare($tanods_query);
$tanods_stmt->execute();
$tanods = $tanods_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active incident assignments
$assignments_query = "SELECT r.*, u.first_name, u.last_name 
                     FROM reports r
                     LEFT JOIN users u ON r.assigned_tanod = u.id
                     WHERE r.assigned_tanod IS NOT NULL 
                     AND r.status IN ('pending_field_verification', 'assigned', 'investigating')
                     ORDER BY r.priority DESC, r.created_at DESC";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->execute();
$active_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Barangay Commonwealth boundary coordinates (approximate polygon)
$barangay_boundary = [
    [14.6990, 121.0750], // Northwest corner
    [14.6995, 121.0790], 
    [14.6970, 121.0830],
    [14.6935, 121.0860], // Northeast corner
    [14.6900, 121.0850],
    [14.6870, 121.0820],
    [14.6860, 121.0780], // Southeast corner
    [14.6880, 121.0740],
    [14.6920, 121.0720], // Southwest corner
    [14.6960, 121.0730],
    [14.6990, 121.0750]  // Closing point
];

// Key locations within Barangay Commonwealth
$key_locations = [
    [
        'name' => 'Barangay Hall',
        'lat' => 14.6955,
        'lng' => 121.0795,
        'type' => 'government',
        'address' => 'Commonwealth Ave, Brgy. Commonwealth, Quezon City'
    ],
    [
        'name' => 'Commonwealth Market',
        'lat' => 14.6930,
        'lng' => 121.0805,
        'type' => 'commercial',
        'address' => 'Market Drive, Brgy. Commonwealth'
    ],
    [
        'name' => 'Commonwealth Elementary School',
        'lat' => 14.6970,
        'lng' => 121.0765,
        'type' => 'school',
        'address' => 'Don Mariano Marcos Ave, Commonwealth'
    ],
    [
        'name' => 'QC Police Station 6',
        'lat' => 14.6915,
        'lng' => 121.0775,
        'type' => 'police',
        'address' => 'Commonwealth Ave cor. Luzon Ave'
    ],
    [
        'name' => 'Commonwealth Health Center',
        'lat' => 14.6940,
        'lng' => 121.0820,
        'type' => 'hospital',
        'address' => 'Health Center Road, Commonwealth'
    ]
];
?>
<div class="space-y-6">
    <!-- Real-time Map View -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Real-time Tanod Tracker - Barangay Commonwealth</h2>
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                    <span class="text-sm text-gray-600">On Patrol</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                    <span class="text-sm text-gray-600">Available</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-gray-400 mr-2"></div>
                    <span class="text-sm text-gray-600">Off-duty</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-purple-500 mr-2"></div>
                    <span class="text-sm text-gray-600">Key Locations</span>
                </div>
            </div>
        </div>
        
        <div id="tanodMap" style="height: 500px; width: 100%;" class="mb-6 rounded-lg border border-gray-300"></div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">On Patrol</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count(array_filter($tanods, fn($t) => $t['duty_status'] === 'On-Duty')); ?>
                </p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Available</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count(array_filter($tanods, fn($t) => $t['duty_status'] === 'Available')); ?>
                </p>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Off-duty</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count(array_filter($tanods, fn($t) => $t['duty_status'] === 'Off-Duty')); ?>
                </p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Assigned Incidents</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count($active_assignments); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Tanod Personnel List -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Tanod Personnel Status</h3>
            
            <div class="space-y-3">
                <?php foreach($tanods as $tanod): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center space-x-3">
                            <div class="relative">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                                </div>
                                <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white 
                                    <?php echo $tanod['duty_status'] === 'On-Duty' ? 'bg-green-500' : 
                                           ($tanod['duty_status'] === 'Available' ? 'bg-blue-500' : 'bg-gray-400'); ?>"></div>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">
                                    <?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?>
                                </p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($tanod['contact_number']); ?></p>
                                <!-- Removed current_address section since column doesn't exist -->
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-medium <?php echo $tanod['duty_status'] === 'On-Duty' ? 'text-green-600' : 
                                                                  ($tanod['duty_status'] === 'Available' ? 'text-blue-600' : 'text-gray-500'); ?>">
                                <?php echo $tanod['duty_status']; ?>
                            </span>
                            <?php if ($tanod['clock_in']): ?>
                                <p class="text-xs text-gray-500">
                                    Since <?php echo date('H:i', strtotime($tanod['clock_in'])); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($tanod['location_lat'] && $tanod['location_lng']): ?>
                                <p class="text-xs text-green-600">
                                    <i class="fas fa-satellite"></i> Live Location
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Active Assignments -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Active Incident Assignments</h3>
                <span class="text-sm font-medium text-gray-600">
                    <?php echo count($active_assignments); ?> active
                </span>
            </div>
            
            <div class="space-y-3">
                <?php if (!empty($active_assignments)): ?>
                    <?php foreach($active_assignments as $assignment): ?>
                        <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($assignment['report_number']); ?></span>
                                    <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($assignment['title']); ?></p>
                                </div>
                                <span class="status-badge <?php echo $assignment['priority'] === 'high' ? 'status-warning' : 
                                                                 ($assignment['priority'] === 'critical' ? 'status-error' : 'status-success'); ?>">
                                    <?php echo ucfirst($assignment['priority']); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <div>
                                    <i class="fas fa-user-shield mr-1"></i>
                                    <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                </div>
                                <div>
                                    <?php echo date('H:i', strtotime($assignment['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-check-circle text-3xl mb-2"></i>
                        <p>No active assignments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assignment Management -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Assignment</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Incident</label>
                <select class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Choose incident to assign...</option>
                    <?php
                    $pending_incidents_query = "SELECT id, report_number, title FROM reports 
                                               WHERE assigned_tanod IS NULL 
                                               AND status IN ('pending_field_verification')
                                               ORDER BY created_at DESC";
                    $pending_incidents_stmt = $conn->prepare($pending_incidents_query);
                    $pending_incidents_stmt->execute();
                    $pending_incidents = $pending_incidents_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach($pending_incidents as $incident): ?>
                        <option value="<?php echo $incident['id']; ?>">
                            <?php echo htmlspecialchars($incident['report_number'] . ' - ' . $incident['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Tanod</label>
                <select class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Select available Tanod...</option>
                    <?php foreach($tanods as $tanod): 
                        if ($tanod['duty_status'] === 'Available' || $tanod['duty_status'] === 'On-Duty'): ?>
                            <option value="<?php echo $tanod['id']; ?>">
                                <?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name'] . ' - ' . $tanod['duty_status']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="mt-6">
            <button class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                <i class="fas fa-paper-plane mr-2"></i>Assign Incident
            </button>
        </div>
    </div>
</div>

<script>
// Initialize map with Philippines and Barangay Commonwealth focus
function initTanodMap() {
    // Center on Barangay Commonwealth, Quezon City
    const map = L.map('tanodMap').setView([14.6945, 121.0790], 14);
    
    // Use OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors | Barangay Commonwealth, Quezon City'
    }).addTo(map);
    
    // Add Philippines boundary context (optional)
    L.rectangle([[4.5, 114], [21.5, 127]], {
        color: "#0044ff",
        weight: 2,
        fillOpacity: 0.05,
        dashArray: '5, 5'
    }).addTo(map).bindPopup("Philippines Territory");
    
    // Draw Barangay Commonwealth boundary
    const barangayBoundary = L.polygon(<?php echo json_encode($barangay_boundary); ?>, {
        color: '#3b82f6',
        weight: 3,
        fillColor: '#1d4ed8',
        fillOpacity: 0.1,
        dashArray: '10, 5'
    }).addTo(map);
    
    // Add label for Barangay Commonwealth
    barangayBoundary.bindPopup(`
        <div class="p-2">
            <h3 class="font-bold text-lg text-blue-700">Barangay Commonwealth</h3>
            <p class="text-sm">Quezon City, Metro Manila</p>
            <p class="text-xs text-gray-600">Area: ~5.3 sq km | Population: ~200,000</p>
            <div class="mt-2">
                <div class="flex items-center text-sm">
                    <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                    <span>Barangay Boundary</span>
                </div>
                <div class="flex items-center text-sm">
                    <div class="w-3 h-3 rounded-full bg-purple-500 mr-2"></div>
                    <span>Key Locations</span>
                </div>
            </div>
        </div>
    `);
    
    // Add key locations markers
    <?php foreach($key_locations as $location): ?>
        const locationIcon<?php echo str_replace(' ', '', $location['name']); ?> = L.divIcon({
            html: `<div class="bg-purple-600 w-6 h-6 rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                     <i class="fas fa-<?php echo $location['type'] === 'government' ? 'landmark' : 
                                          ($location['type'] === 'school' ? 'graduation-cap' : 
                                           ($location['type'] === 'police' ? 'shield-alt' : 
                                            ($location['type'] === 'hospital' ? 'hospital' : 'store'))); ?> 
                          text-white text-xs"></i>
                   </div>`,
            className: 'custom-div-icon',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        
        L.marker([<?php echo $location['lat']; ?>, <?php echo $location['lng']; ?>], { 
            icon: locationIcon<?php echo str_replace(' ', '', $location['name']); ?>
        }).addTo(map)
          .bindPopup(`
            <div class="p-2">
                <h4 class="font-bold text-purple-700"><?php echo $location['name']; ?></h4>
                <p class="text-sm text-gray-600"><?php echo $location['address']; ?></p>
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fas fa-tag mr-1"></i><?php echo ucfirst($location['type']); ?> Location
                </p>
            </div>
          `);
    <?php endforeach; ?>
    
    // Add Tanod markers from database
    <?php foreach($tanods as $tanod): ?>
        <?php if ($tanod['location_lat'] && $tanod['location_lng']): ?>
            const iconColor<?php echo $tanod['id']; ?> = '<?php echo $tanod['duty_status'] === "On-Duty" ? "green" : 
                                                              ($tanod['duty_status'] === "Available" ? "blue" : "gray"); ?>';
            
            const icon<?php echo $tanod['id']; ?> = L.divIcon({
                html: `<div class="bg-${iconColor<?php echo $tanod['id']; ?>}-500 w-8 h-8 rounded-full border-2 border-white shadow-lg flex items-center justify-center animate-pulse">
                         <i class="fas fa-shield-alt text-white text-xs"></i>
                       </div>`,
                className: 'custom-div-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
            
            L.marker([<?php echo $tanod['location_lat']; ?>, <?php echo $tanod['location_lng']; ?>], { 
                icon: icon<?php echo $tanod['id']; ?>,
                zIndexOffset: 1000 // Make tanod markers appear above other markers
            }).addTo(map)
              .bindPopup(`
                <div class="p-3">
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($tanod['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800"><?php echo addslashes($tanod['first_name'] . ' ' . $tanod['last_name']); ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $tanod['contact_number']; ?></p>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm">
                            <span class="font-medium">Status:</span> 
                            <span class="<?php echo $tanod['duty_status'] === 'On-Duty' ? 'text-green-600' : 
                                            ($tanod['duty_status'] === 'Available' ? 'text-blue-600' : 'text-gray-500'); ?>">
                                <?php echo $tanod['duty_status']; ?>
                            </span>
                        </p>
                        <?php if ($tanod['clock_in']): ?>
                            <p class="text-sm">
                                <span class="font-medium">On Duty Since:</span> 
                                <span class="text-gray-600"><?php echo date('H:i', strtotime($tanod['clock_in'])); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
              `);
        <?php endif; ?>
    <?php endforeach; ?>
    
    // Add scale control
    L.control.scale({imperial: false}).addTo(map);
    
    // Add legend
    const legend = L.control({position: 'bottomright'});
    legend.onAdd = function() {
        const div = L.DomUtil.create('div', 'bg-white p-4 rounded-lg shadow-lg border border-gray-300');
        div.innerHTML = `
            <h4 class="font-bold text-gray-800 mb-2">Map Legend</h4>
            <div class="space-y-2">
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full bg-green-500 mr-2"></div>
                    <span class="text-sm">Tanod On Patrol</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full bg-blue-500 mr-2"></div>
                    <span class="text-sm">Tanod Available</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full bg-gray-400 mr-2"></div>
                    <span class="text-sm">Tanod Off-duty</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full bg-purple-500 mr-2"></div>
                    <span class="text-sm">Key Locations</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 border-2 border-blue-500 bg-blue-100 mr-2"></div>
                    <span class="text-sm">Barangay Boundary</span>
                </div>
            </div>
        `;
        return div;
    };
    legend.addTo(map);
}

// Initialize map when module loads
setTimeout(initTanodMap, 100);

// Auto-refresh Tanod locations every 30 seconds
setInterval(() => {
    fetch('handlers/get_tanod_locations.php')
        .then(response => response.json())
        .then(data => {
            console.log('Updated Tanod locations:', data);
            // Implementation for updating markers would go here
        });
}, 30000);
</script>

<style>
.animate-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.leaflet-popup-content {
    min-width: 200px;
}
</style>