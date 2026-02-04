<?php
// admin/modules/tanod_tracker.php - TANOD ASSIGNMENT TRACKER MODULE

// Get active Tanods with their status
$tanods_query = "SELECT u.id, u.first_name, u.last_name, u.contact_number,
                        ts.status as duty_status, ts.last_updated,
                        td.clock_in, td.clock_out
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

// Get GPS data directly from database
$gps_units = [];
try {
    // Check if gps_units table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'gps_units'");
    if ($table_check->rowCount() > 0) {
        $gps_query = "SELECT * FROM gps_units WHERE is_active = 1";
        $gps_stmt = $conn->prepare($gps_query);
        $gps_stmt->execute();
        $gps_units = $gps_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If no GPS units, create them from tanods
    if (empty($gps_units)) {
        foreach ($tanods as $tanod) {
            $unit_id = 'TANOD_' . $tanod['id'];
            $callsign = 'Tanod ' . $tanod['first_name'] . ' ' . $tanod['last_name'];
            
            // Generate random position in Commonwealth
            $latitude = 14.697000 + (rand(-50, 50) / 10000);
            $longitude = 121.088000 + (rand(-50, 50) / 10000);
            
            $status_map = [
                'On-Duty' => 'On-Duty',
                'Available' => 'Available',
                'Off-Duty' => 'Stationary'
            ];
            $status = $status_map[$tanod['duty_status']] ?? 'Stationary';
            
            // Insert GPS unit
            $insert_query = "INSERT INTO gps_units (unit_id, callsign, status, latitude, longitude) 
                             VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE callsign = VALUES(callsign), 
                             status = VALUES(status)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->execute([$unit_id, $callsign, $status, $latitude, $longitude]);
        }
        
        // Get GPS units again
        $gps_stmt->execute();
        $gps_units = $gps_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("GPS database error: " . $e->getMessage());
    $gps_units = [];
}

// Prepare GPS data for JavaScript
$gps_data_json = json_encode($gps_units);
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
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <span class="text-sm text-gray-600">Responding</span>
                </div>
            </div>
        </div>
        
        <!-- INTERACTIVE GPS MAP -->
        <div id="gpsMap" style="height: 500px; width: 100%; border: 1px solid #e5e7eb;" 
             class="mb-6 rounded-lg"></div>
        
        <!-- Map loading indicator -->
        <div id="mapLoading" class="text-center py-4">
            <i class="fas fa-spinner fa-spin text-blue-500 mr-2"></i>
            <span class="text-gray-600">Loading Barangay Commonwealth map...</span>
        </div>
        
        <!-- Map error fallback -->
        <div id="mapError" class="hidden mb-6 rounded-lg border border-gray-300 p-8 bg-gray-50 text-center">
            <i class="fas fa-exclamation-triangle text-3xl text-yellow-500 mb-4"></i>
            <p class="text-gray-600 font-medium mb-2">Unable to load GPS map</p>
            <p class="text-gray-500 text-sm mb-4">Check if Leaflet.js is loaded properly</p>
            <button onclick="initMap()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                <i class="fas fa-redo mr-2"></i>Retry Loading Map
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">GPS Active</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count($gps_units); ?>
                </p>
            </div>
        </div>
        
        <!-- Refresh Controls -->
        <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Showing Barangay Commonwealth with Tanod positions
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshMapData()" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh Map
                </button>
                <button onclick="zoomToCommonwealth()" class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200">
                    <i class="fas fa-location-arrow mr-1"></i>View Commonwealth
                </button>
                <button onclick="simulateMovement()" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm hover:bg-purple-200">
                    <i class="fas fa-walking mr-1"></i>Simulate Movement
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tanod Personnel List -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Tanod Personnel Status</h3>
            
            <div class="space-y-3">
                <?php foreach($tanods as $tanod): 
                    // Find GPS data for this Tanod
                    $tanod_gps = null;
                    foreach ($gps_units as $gps) {
                        if (strpos($gps['unit_id'], (string)$tanod['id']) !== false || 
                            strpos($gps['callsign'], $tanod['first_name']) !== false) {
                            $tanod_gps = $gps;
                            break;
                        }
                    }
                ?>
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
                            <?php if ($tanod_gps): ?>
                                <p class="text-xs text-green-600">
                                    <i class="fas fa-satellite"></i> GPS Active
                                    <?php if ($tanod_gps['status'] === 'Moving' && $tanod_gps['speed'] > 0): ?>
                                        <span class="text-blue-600">(<?php echo number_format($tanod_gps['speed'], 1); ?> km/h)</span>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-yellow-600">
                                    <i class="fas fa-satellite"></i> GPS Inactive
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
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $assignment['priority'] === 'high' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($assignment['priority'] === 'critical' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'); ?>">
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
                <select id="quickIncident" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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
                <select id="quickTanod" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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
            <button onclick="assignQuickIncident()" class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                <i class="fas fa-paper-plane mr-2"></i>Assign Incident
            </button>
        </div>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Global map variables
let map;
let markers = {};
let markerLayer = L.layerGroup();
let barangayBoundary;
let refreshInterval;
const COMMONWEALTH_CENTER = [14.697000, 121.088000];
const GPS_DATA = <?php echo $gps_data_json ?: '[]'; ?>;

// Barangay Commonwealth boundary coordinates (approximate polygon)
const COMMONWEALTH_BOUNDARY = [
    [14.7045, 121.0820], // Northwest
    [14.7045, 121.0940], // Northeast
    [14.6895, 121.0940], // Southeast
    [14.6895, 121.0820], // Southwest
    [14.7045, 121.0820]  // Close polygon
];

// Initialize map
function initMap() {
    try {
        // Hide loading/error messages
        document.getElementById('mapLoading').style.display = 'none';
        document.getElementById('mapError').classList.add('hidden');
        
        // Initialize map centered on Commonwealth
        map = L.map('gpsMap').setView(COMMONWEALTH_CENTER, 14);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Add Barangay Commonwealth boundary
        barangayBoundary = L.polygon(COMMONWEALTH_BOUNDARY, {
            color: '#3b82f6',
            weight: 3,
            opacity: 0.7,
            fillColor: '#3b82f6',
            fillOpacity: 0.1
        }).addTo(map);
        
        // Add boundary label
        L.marker([14.697, 121.088], {
            icon: L.divIcon({
                className: 'barangay-label',
                html: '<div class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg">Barangay Commonwealth</div>',
                iconSize: [180, 30],
                iconAnchor: [90, 15]
            })
        }).addTo(map).bindPopup('<strong>Barangay Commonwealth</strong><br>Quezon City, Metro Manila');
        
        // Add marker layer
        markerLayer.addTo(map);
        
        // Load GPS data
        loadGpsData();
        
        // Start auto-refresh
        startAutoRefresh();
        
        console.log('Map initialized with', GPS_DATA.length, 'GPS units');
        
    } catch (error) {
        console.error('Map initialization error:', error);
        showMapError();
    }
}

// Load GPS data
function loadGpsData() {
    try {
        console.log('Loading GPS data...');
        
        if (GPS_DATA && GPS_DATA.length > 0) {
            updateMapMarkers(GPS_DATA);
            console.log('Loaded', GPS_DATA.length, 'GPS units');
        } else {
            // Create simulated data if none exists
            createSimulatedData();
        }
        
    } catch (error) {
        console.error('Error loading GPS data:', error);
        createSimulatedData();
    }
}

// Create simulated GPS data
function createSimulatedData() {
    console.log('Creating simulated GPS data...');
    
    const simulatedData = [
        {
            unit_id: 'TANOD_1009',
            callsign: 'Tanod Jeff',
            status: 'On-Duty',
            latitude: 14.697500,
            longitude: 121.088500,
            speed: 5.5
        },
        {
            unit_id: 'TANOD_1010', 
            callsign: 'Tanod MJ',
            status: 'Available',
            latitude: 14.696800,
            longitude: 121.087800,
            speed: 0
        },
        {
            unit_id: 'TANOD_1011',
            callsign: 'Tanod Isagani',
            status: 'Stationary',
            latitude: 14.697200,
            longitude: 121.088200,
            speed: 0
        },
        {
            unit_id: 'TANOD_1020',
            callsign: 'Tanod Carmelo',
            status: 'Off-Duty',
            latitude: 14.696500,
            longitude: 121.087500,
            speed: 0
        },
        {
            unit_id: 'TANOD_1029',
            callsign: 'Tanod Jeannalyn',
            status: 'Available',
            latitude: 14.697800,
            longitude: 121.088800,
            speed: 2.3
        }
    ];
    
    updateMapMarkers(simulatedData);
    console.log('Created simulated data for', simulatedData.length, 'Tanods');
}

// Update markers on map
function updateMapMarkers(units) {
    // Clear existing markers
    markerLayer.clearLayers();
    markers = {};
    
    // Add new markers for each unit
    units.forEach(unit => {
        if (unit.latitude && unit.longitude) {
            const marker = createMarker(unit);
            marker.addTo(markerLayer);
            markers[unit.unit_id] = marker;
        }
    });
    
    // Fit map to show all markers and boundary
    if (units.length > 0) {
        const bounds = L.latLngBounds(COMMONWEALTH_BOUNDARY);
        units.forEach(unit => {
            if (unit.latitude && unit.longitude) {
                bounds.extend([unit.latitude, unit.longitude]);
            }
        });
        map.fitBounds(bounds.pad(0.1));
    }
}

// Create a marker for a GPS unit
function createMarker(unit) {
    // Determine marker color based on status
    let markerColor, statusClass;
    if (unit.status === 'On-Duty' || unit.status === 'Moving') {
        markerColor = 'green';
        statusClass = 'on-duty-marker';
    } else if (unit.status === 'Available') {
        markerColor = 'blue';
        statusClass = 'available-marker';
    } else if (unit.status === 'Responding') {
        markerColor = 'red';
        statusClass = 'responding-marker';
    } else {
        markerColor = 'gray';
        statusClass = 'off-duty-marker';
    }
    
    // Create custom icon with pulsing effect for on-duty
    const iconHtml = `
        <div class="relative ${statusClass}">
            <div class="w-10 h-10 rounded-full bg-${markerColor}-500 border-2 border-white flex items-center justify-center text-white font-bold shadow-lg">
                ${unit.callsign ? unit.callsign.split(' ')[1]?.charAt(0) || 'T' : 'T'}
            </div>
            ${unit.status === 'On-Duty' ? '<div class="absolute inset-0 rounded-full bg-green-500 animate-ping opacity-75"></div>' : ''}
        </div>
    `;
    
    const icon = L.divIcon({
        className: 'custom-marker',
        html: iconHtml,
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
    
    // Create marker
    const marker = L.marker([unit.latitude, unit.longitude], { 
        icon: icon,
        title: unit.callsign || 'Tanod Unit'
    });
    
    // Add popup with unit info
    const popupContent = `
        <div class="p-3 min-w-52">
            <div class="font-bold text-gray-800 mb-2 flex items-center">
                <div class="w-3 h-3 rounded-full bg-${markerColor}-500 mr-2"></div>
                ${unit.callsign || 'Tanod Unit'}
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-medium text-${markerColor}-600">${unit.status}</span>
                </div>
                ${unit.assignment ? `<div class="flex justify-between">
                    <span class="text-gray-600">Assignment:</span>
                    <span class="font-medium">${unit.assignment}</span>
                </div>` : ''}
                <div class="flex justify-between">
                    <span class="text-gray-600">Location:</span>
                    <span class="font-medium">${unit.latitude.toFixed(6)}, ${unit.longitude.toFixed(6)}</span>
                </div>
                ${unit.speed > 0 ? `<div class="flex justify-between">
                    <span class="text-gray-600">Speed:</span>
                    <span class="font-medium">${unit.speed || 0} km/h</span>
                </div>` : ''}
                ${unit.last_ping ? `<div class="flex justify-between">
                    <span class="text-gray-600">Last Update:</span>
                    <span class="font-medium">${new Date(unit.last_ping).toLocaleTimeString()}</span>
                </div>` : ''}
            </div>
        </div>
    `;
    
    marker.bindPopup(popupContent);
    return marker;
}

// Refresh map data
function refreshMapData() {
    showNotification('Refreshing Tanod positions...', 'info');
    
    // Simulate some movement for demonstration
    Object.keys(markers).forEach(unitId => {
        const marker = markers[unitId];
        if (marker) {
            const currentPos = marker.getLatLng();
            // Add small random movement
            const newLat = currentPos.lat + (Math.random() - 0.5) * 0.0005;
            const newLng = currentPos.lng + (Math.random() - 0.5) * 0.0005;
            
            // Keep within Commonwealth boundary
            const boundedLat = Math.max(14.6895, Math.min(14.7045, newLat));
            const boundedLng = Math.max(121.0820, Math.min(121.0940, newLng));
            
            marker.setLatLng([boundedLat, boundedLng]);
        }
    });
    
    showNotification('Map refreshed with updated positions', 'success');
}

// Zoom to Commonwealth area
function zoomToCommonwealth() {
    map.setView(COMMONWEALTH_CENTER, 14);
    
    // Highlight boundary
    if (barangayBoundary) {
        map.fitBounds(barangayBoundary.getBounds());
        barangayBoundary.setStyle({ weight: 5, opacity: 1 });
        setTimeout(() => {
            barangayBoundary.setStyle({ weight: 3, opacity: 0.7 });
        }, 2000);
    }
    
    showNotification('Centered on Barangay Commonwealth', 'info');
}

// Simulate Tanod movement
function simulateMovement() {
    showNotification('Simulating Tanod patrol movement...', 'info');
    
    Object.keys(markers).forEach(unitId => {
        const marker = markers[unitId];
        if (marker) {
            // Move marker along a path within Commonwealth
            const path = [
                [14.6970, 121.0880],
                [14.6980, 121.0890],
                [14.6990, 121.0880],
                [14.6980, 121.0870],
                [14.6970, 121.0880]
            ];
            
            let currentPoint = 0;
            const interval = setInterval(() => {
                if (currentPoint < path.length) {
                    marker.setLatLng(path[currentPoint]);
                    currentPoint++;
                } else {
                    clearInterval(interval);
                }
            }, 1000);
        }
    });
}

// Show map error
function showMapError() {
    document.getElementById('mapLoading').style.display = 'none';
    document.getElementById('mapError').classList.remove('hidden');
}

// Start auto-refresh
function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(refreshMapData, 60000); // Refresh every 60 seconds
}

// Stop auto-refresh
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// Function to assign incident (unchanged)
function assignQuickIncident() {
    const reportId = document.getElementById('quickIncident').value;
    const tanodId = document.getElementById('quickTanod').value;
    
    if(!reportId || !tanodId){
        alert('Please select an incident and a tanod');
        return;
    }
    
    const formData = new FormData();
    formData.append('report_id', reportId);
    formData.append('tanod_id', tanodId);
    formData.append('assign_case', '1');
    
    fetch('handlers/assign_case.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Incident assigned successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to assign incident'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Network error assigning incident');
    });
}

// Function to show notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    } animate__animated animate__fadeIn`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('animate__fadeOut');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 3000);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map after a short delay to ensure DOM is ready
    setTimeout(() => {
        initMap();
    }, 500);
    
    // Set up auto-refresh controls
    const mapContainer = document.getElementById('gpsMap');
    if (mapContainer) {
        mapContainer.addEventListener('mouseenter', stopAutoRefresh);
        mapContainer.addEventListener('mouseleave', startAutoRefresh);
    }
});
</script>

<style>
/* Custom marker styles */
.custom-marker {
    transition: transform 0.3s ease;
    z-index: 1000;
}

.custom-marker:hover {
    transform: scale(1.2);
    z-index: 1001;
}

/* Animation for on-duty markers */
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.on-duty-marker {
    animation: pulse 2s infinite;
}

.responding-marker {
    animation: pulse 1s infinite;
}

/* Barangay boundary style */
.barangay-boundary {
    stroke-dasharray: 10, 10;
    animation: dash 20s linear infinite;
}

@keyframes dash {
    to {
        stroke-dashoffset: 1000;
    }
}

/* Map controls */
.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
    border-radius: 8px !important;
}

.leaflet-control-zoom a {
    border-radius: 4px !important;
    margin: 2px !important;
    background-color: white !important;
    color: #4b5563 !important;
    transition: all 0.2s ease !important;
}

.leaflet-control-zoom a:hover {
    background-color: #f3f4f6 !important;
}

/* Custom popup */
.leaflet-popup-content-wrapper {
    border-radius: 12px !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    border: 1px solid #e5e7eb !important;
}

.leaflet-popup-content {
    margin: 0 !important;
    padding: 0 !important;
    font-family: 'Segoe UI', system-ui, sans-serif !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #gpsMap {
        height: 400px;
    }
    
    .leaflet-control-zoom {
        transform: scale(0.9);
    }
}

/* Loading animation */
#mapLoading {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Barangay label */
.barangay-label {
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}
</style>