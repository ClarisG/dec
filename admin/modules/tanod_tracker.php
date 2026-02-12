<?php
// admin/modules/tanod_tracker.php - PATROL VEHICLE TRACKER MODULE

// Get active Tanods with their status (for status display only)
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

// Get Patrol Vehicles for Tracking
$vehicles = [];
try {
    // Check if patrol_vehicles table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'patrol_vehicles'");
    if ($table_check->rowCount() > 0) {
        $vehicle_query = "SELECT * FROM patrol_vehicles WHERE status = 'Active'";
        $vehicle_stmt = $conn->prepare($vehicle_query);
        $vehicle_stmt->execute();
        $vehicles = $vehicle_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Fallback if table doesn't exist yet
    $vehicles = [];
}

// Generate GPS data for vehicles (simulated or real)
$gps_units = [];
foreach ($vehicles as $vehicle) {
    $unit_id = 'VEHICLE_' . $vehicle['id'];
    $callsign = $vehicle['vehicle_name'];
    
    // Simulate GPS if not in DB
    // In a real app, you'd fetch from vehicle_gps_logs
    $latitude = 14.697000 + (rand(-50, 50) / 10000);
    $longitude = 121.088000 + (rand(-50, 50) / 10000);
    $status = 'Patrol'; // Default status
    
    $gps_units[] = [
        'unit_id' => $unit_id,
        'callsign' => $callsign,
        'status' => $status,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'type' => $vehicle['type'] ?? 'car',
        'plate_number' => $vehicle['plate_number']
    ];
}

// Prepare GPS data for JavaScript
$gps_data_json = json_encode($gps_units);
?>
<div class="space-y-6">
    <!-- Real-time Map View -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Real-time Patrol Vehicle Tracker - Barangay Commonwealth</h2>
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                    <span class="text-sm text-gray-600">On Patrol</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                    <span class="text-sm text-gray-600">Stationary</span>
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
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Active Vehicles</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count($vehicles); ?>
                </p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">On-Duty Tanods</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo count(array_filter($tanods, fn($t) => $t['duty_status'] === 'On-Duty')); ?>
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
                Showing Barangay Commonwealth with Patrol Vehicle positions
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshMapData()" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh Map
                </button>
                <button onclick="zoomToCommonwealth()" class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200">
                    <i class="fas fa-location-arrow mr-1"></i>View Commonwealth
                </button>
                <button onclick="simulateMovement()" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm hover:bg-purple-200">
                    <i class="fas fa-car mr-1"></i>Simulate Patrol
                </button>
            </div>
        </div>
    </div>
    
    <!-- Status Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tanod Personnel Status -->
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

// Barangay Commonwealth boundary coordinates
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
        
    } catch (error) {
        console.error('Map initialization error:', error);
        showMapError();
    }
}

// Load GPS data
function loadGpsData() {
    try {
        if (GPS_DATA && GPS_DATA.length > 0) {
            updateMapMarkers(GPS_DATA);
        } else {
            console.log('No GPS data available');
        }
    } catch (error) {
        console.error('Error loading GPS data:', error);
    }
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
    const isMoving = unit.status === 'Patrol' || unit.status === 'Moving';
    const markerColor = isMoving ? 'green' : 'blue';
    const iconClass = unit.type === 'motorcycle' ? 'motorcycle' : 'car';
    
    // Create custom icon
    const iconHtml = `
        <div class="relative">
            <div class="w-10 h-10 rounded-full bg-${markerColor}-500 border-2 border-white flex items-center justify-center text-white font-bold shadow-lg">
                <i class="fas fa-${iconClass}"></i>
            </div>
            ${isMoving ? '<div class="absolute inset-0 rounded-full bg-green-500 animate-ping opacity-75"></div>' : ''}
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
        title: unit.callsign || 'Patrol Vehicle'
    });
    
    // Add popup with unit info
    const popupContent = `
        <div class="p-3 min-w-52">
            <div class="font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-${iconClass} mr-2 text-${markerColor}-600"></i>
                ${unit.callsign || 'Patrol Vehicle'}
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-medium text-${markerColor}-600">${unit.status}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Plate:</span>
                    <span class="font-medium">${unit.plate_number || 'N/A'}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Location:</span>
                    <span class="font-medium">${unit.latitude.toFixed(6)}, ${unit.longitude.toFixed(6)}</span>
                </div>
            </div>
        </div>
    `;
    
    marker.bindPopup(popupContent);
    return marker;
}

// Refresh map data
function refreshMapData() {
    // Simulate movement update
    Object.keys(markers).forEach(unitId => {
        const marker = markers[unitId];
        if (marker) {
            const currentPos = marker.getLatLng();
            const newLat = currentPos.lat + (Math.random() - 0.5) * 0.0005;
            const newLng = currentPos.lng + (Math.random() - 0.5) * 0.0005;
            marker.setLatLng([newLat, newLng]);
        }
    });
}

// Zoom to Commonwealth area
function zoomToCommonwealth() {
    map.setView(COMMONWEALTH_CENTER, 14);
    if (barangayBoundary) {
        map.fitBounds(barangayBoundary.getBounds());
    }
}

// Simulate Movement
function simulateMovement() {
    showNotification('Simulating patrol movement...', 'info');
    Object.keys(markers).forEach(unitId => {
        const marker = markers[unitId];
        if (marker) {
            const path = [
                [14.6970, 121.0880], [14.6980, 121.0890],
                [14.6990, 121.0880], [14.6980, 121.0870]
            ];
            let i = 0;
            const interval = setInterval(() => {
                if (i < path.length) marker.setLatLng(path[i++]);
                else clearInterval(interval);
            }, 1000);
        }
    });
}

function showNotification(message, type = 'info') {
    // Implementation of notification
    alert(message);
}

// Show map error
function showMapError() {
    document.getElementById('mapLoading').style.display = 'none';
    document.getElementById('mapError').classList.remove('hidden');
}

// Start auto-refresh
function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(refreshMapData, 60000);
}

// Stop auto-refresh
function stopAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initMap, 500);
});
</script>

<style>
.custom-marker { transition: transform 0.3s ease; z-index: 1000; }
.custom-marker:hover { transform: scale(1.2); z-index: 1001; }
.barangay-boundary { stroke-dasharray: 10, 10; animation: dash 20s linear infinite; }
@keyframes dash { to { stroke-dashoffset: 1000; } }
.barangay-label { text-shadow: 1px 1px 2px rgba(0,0,0,0.5); }
</style>
