<?php
// tanod/modules/tanod_account_tracker.php
session_start();
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get Tanod's current status and duty info
$tanod_query = "SELECT u.id, u.first_name, u.last_name, 
                       ts.status as duty_status, ts.last_updated,
                       td.id as duty_id, td.clock_in, td.clock_out,
                       tr.route_data
                FROM users u
                LEFT JOIN tanod_status ts ON u.id = ts.user_id
                LEFT JOIN tanod_duty_logs td ON u.id = td.user_id 
                    AND DATE(td.clock_in) = CURDATE() 
                    AND td.clock_out IS NULL
                LEFT JOIN tanod_routes tr ON td.id = tr.duty_id
                WHERE u.id = :user_id";
$tanod_stmt = $conn->prepare($tanod_query);
$tanod_stmt->execute(['user_id' => $user_id]);
$tanod = $tanod_stmt->fetch(PDO::FETCH_ASSOC);

// Get today's patrol route points
$route_points = [];
if ($tanod['route_data']) {
    $route_points = json_decode($tanod['route_data'], true);
} else {
    $route_query = "SELECT latitude, longitude, created_at 
                    FROM tanod_location_logs 
                    WHERE user_id = :user_id 
                    AND DATE(created_at) = CURDATE()
                    ORDER BY created_at ASC";
    $route_stmt = $conn->prepare($route_query);
    $route_stmt->execute(['user_id' => $user_id]);
    $route_points = $route_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get assigned incidents
$assigned_query = "SELECT r.*, rt.type_name, p.barangay_name
                  FROM reports r
                  LEFT JOIN report_types rt ON r.type_id = rt.id
                  LEFT JOIN puroks p ON r.purok_id = p.id
                  WHERE r.assigned_tanod = :user_id
                  AND r.status IN ('assigned', 'investigating')
                  ORDER BY r.priority DESC, r.created_at DESC";
$assigned_stmt = $conn->prepare($assigned_query);
$assigned_stmt->execute(['user_id' => $user_id]);
$assigned_incidents = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Personal Tracker Header -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">My Patrol Tracker</h2>
                <p class="text-gray-600">Real-time location tracking and patrol history</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full <?php echo $tanod['duty_status'] == 'On-Duty' ? 'bg-green-500' : 'bg-gray-400'; ?> mr-2"></div>
                    <span class="text-sm text-gray-600"><?php echo $tanod['duty_status'] ?? 'Off-Duty'; ?></span>
                </div>
                <?php if ($tanod['clock_in']): ?>
                    <div class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-sm">
                        <i class="fas fa-clock mr-1"></i>
                        On duty for <?php echo time_elapsed($tanod['clock_in']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patrol Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Today's Distance</p>
                <p class="text-2xl font-bold text-gray-800" id="totalDistance">0 km</p>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Patrol Time</p>
                <p class="text-2xl font-bold text-gray-800" id="patrolTime">0h 0m</p>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Locations Logged</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo count($route_points); ?></p>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Assigned Incidents</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo count($assigned_incidents); ?></p>
            </div>
        </div>

        <!-- Main Map -->
        <div class="mb-6">
            <div id="personalTrackerMap" style="height: 400px; border-radius: 10px;"></div>
        </div>

        <!-- Location Controls -->
        <div class="flex justify-center space-x-4">
            <button id="startTracking" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                <i class="fas fa-play mr-2"></i>Start Patrol
            </button>
            <button id="pauseTracking" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 flex items-center" disabled>
                <i class="fas fa-pause mr-2"></i>Pause Patrol
            </button>
            <button id="stopTracking" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center" disabled>
                <i class="fas fa-stop mr-2"></i>End Patrol
            </button>
            <button id="updateLocation" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                <i class="fas fa-location-arrow mr-2"></i>Update Location
            </button>
        </div>
    </div>

    <!-- Assigned Incidents -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-800 mb-4">My Assigned Incidents</h3>
        
        <div class="space-y-3">
            <?php if (!empty($assigned_incidents)): ?>
                <?php foreach($assigned_incidents as $incident): ?>
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($incident['report_number']); ?></span>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($incident['title']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo htmlspecialchars($incident['barangay_name']); ?>
                                </p>
                            </div>
                            <span class="status-badge <?php echo $incident['priority'] === 'high' ? 'status-warning' : 
                                                         ($incident['priority'] === 'critical' ? 'status-error' : 'status-success'); ?>">
                                <?php echo ucfirst($incident['priority']); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-4">
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-tag mr-1"></i>
                                <?php echo htmlspecialchars($incident['type_name']); ?>
                            </div>
                            <button class="text-sm text-blue-600 hover:text-blue-800" 
                                    onclick="viewIncidentDetails(<?php echo $incident['id']; ?>)">
                                <i class="fas fa-external-link-alt mr-1"></i>View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>No assigned incidents</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Route History -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Today's Patrol Route</h3>
            <button id="exportRoute" class="text-sm text-blue-600 hover:text-blue-800">
                <i class="fas fa-download mr-1"></i>Export Route
            </button>
        </div>
        
        <div id="routeTimeline" class="space-y-3">
            <?php if (!empty($route_points)): ?>
                <?php foreach($route_points as $index => $point): ?>
                    <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">
                                Location Point <?php echo $index + 1; ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo date('H:i:s', strtotime($point['created_at'])); ?> • 
                                Lat: <?php echo number_format($point['latitude'], 6); ?>, 
                                Lng: <?php echo number_format($point['longitude'], 6); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-route text-3xl mb-2"></i>
                    <p>No route data recorded yet</p>
                    <p class="text-sm mt-2">Start patrol to begin tracking your route</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Global variables
let personalMap;
let currentRoute = [];
let isTracking = false;
let trackingInterval;
let watchId;
let totalDistance = 0;
let startTime;

// Initialize personal tracker map
function initPersonalMap() {
    personalMap = L.map('personalTrackerMap').setView([14.5995, 120.9842], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(personalMap);
    
    // Add route polyline if exists
    <?php if (!empty($route_points)): ?>
        const routeCoords = <?php echo json_encode(array_map(function($point) {
            return [$point['latitude'], $point['longitude']];
        }, $route_points)); ?>;
        
        // Draw route
        const routePolyline = L.polyline(routeCoords, {
            color: '#3B82F6',
            weight: 4,
            opacity: 0.7
        }).addTo(personalMap);
        
        // Add start marker
        if (routeCoords.length > 0) {
            L.marker(routeCoords[0], {
                icon: L.divIcon({
                    html: '<div class="w-6 h-6 rounded-full bg-green-500 border-2 border-white shadow-lg flex items-center justify-center"><i class="fas fa-play text-white text-xs"></i></div>',
                    className: 'custom-div-icon',
                    iconSize: [24, 24]
                })
            }).addTo(personalMap)
            .bindPopup('Start Point<br>' + formatTime('<?php echo $route_points[0]['created_at']; ?>'));
        }
        
        // Add end marker if route is complete
        if (routeCoords.length > 1) {
            L.marker(routeCoords[routeCoords.length - 1], {
                icon: L.divIcon({
                    html: '<div class="w-6 h-6 rounded-full bg-red-500 border-2 border-white shadow-lg flex items-center justify-center"><i class="fas fa-flag text-white text-xs"></i></div>',
                    className: 'custom-div-icon',
                    iconSize: [24, 24]
                })
            }).addTo(personalMap)
            .bindPopup('Current Location<br>' + formatTime('<?php echo end($route_points)['created_at']; ?>'));
            
            // Calculate and display distance
            calculateRouteDistance(routeCoords);
        }
        
        // Fit bounds to show entire route
        personalMap.fitBounds(routePolyline.getBounds());
    <?php else: ?>
        // Center on user's location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                personalMap.setView([position.coords.latitude, position.coords.longitude], 16);
            });
        }
    <?php endif; ?>
}

// Start tracking location
function startLocationTracking() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    isTracking = true;
    startTime = new Date();
    
    // Update UI
    document.getElementById('startTracking').disabled = true;
    document.getElementById('pauseTracking').disabled = false;
    document.getElementById('stopTracking').disabled = false;
    document.getElementById('startTracking').innerHTML = '<i class="fas fa-sync-alt fa-spin mr-2"></i>Tracking...';
    
    // Watch position continuously
    watchId = navigator.geolocation.watchPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            // Add to current route
            currentRoute.push({
                lat: lat,
                lng: lng,
                timestamp: new Date().toISOString(),
                accuracy: position.coords.accuracy
            });
            
            // Update map
            updateMapWithNewLocation(lat, lng);
            
            // Save to server every 30 seconds
            if (currentRoute.length % 2 === 0) {
                saveLocationToServer(lat, lng);
            }
            
            // Update patrol time
            updatePatrolTime();
        },
        function(error) {
            console.error('Geolocation error:', error);
            alert('Error getting location: ' + error.message);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 5000
        }
    );
    
    // Send start notification to server
    fetch('handlers/start_patrol.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'start' })
    });
}

// Save location to server
function saveLocationToServer(lat, lng) {
    fetch('handlers/save_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            latitude: lat,
            longitude: lng,
            accuracy: 10 // Default accuracy
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update route timeline
            addRoutePointToTimeline(lat, lng);
        }
    });
}

// Update map with new location
function updateMapWithNewLocation(lat, lng) {
    // Clear previous position marker
    personalMap.eachLayer(function(layer) {
        if (layer.options && layer.options.className === 'current-position') {
            personalMap.removeLayer(layer);
        }
    });
    
    // Add new position marker
    L.marker([lat, lng], {
        icon: L.divIcon({
            html: '<div class="w-8 h-8 rounded-full bg-blue-600 border-2 border-white shadow-lg flex items-center justify-center"><i class="fas fa-user text-white text-xs"></i></div>',
            className: 'current-position',
            iconSize: [32, 32]
        })
    }).addTo(personalMap)
    .bindPopup('My Current Location<br>' + new Date().toLocaleTimeString());
    
    // Add to polyline
    if (currentRoute.length > 1) {
        const polyline = L.polyline(currentRoute.map(p => [p.lat, p.lng]), {
            color: '#3B82F6',
            weight: 3,
            opacity: 0.8
        }).addTo(personalMap);
    }
    
    // Center map on new location
    personalMap.setView([lat, lng], personalMap.getZoom());
}

// Calculate route distance
function calculateRouteDistance(coords) {
    let distance = 0;
    for (let i = 1; i < coords.length; i++) {
        distance += calculateDistance(
            coords[i-1][0], coords[i-1][1],
            coords[i][0], coords[i][1]
        );
    }
    totalDistance = distance;
    document.getElementById('totalDistance').textContent = distance.toFixed(2) + ' km';
}

// Haversine formula for distance calculation
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Update patrol time display
function updatePatrolTime() {
    if (!startTime) return;
    
    const now = new Date();
    const diff = now - startTime;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    document.getElementById('patrolTime').textContent = 
        `${hours}h ${minutes}m`;
}

// Add route point to timeline
function addRoutePointToTimeline(lat, lng) {
    const timeline = document.getElementById('routeTimeline');
    const time = new Date().toLocaleTimeString();
    
    const pointElement = document.createElement('div');
    pointElement.className = 'flex items-center p-3 border border-gray-200 rounded-lg';
    pointElement.innerHTML = `
        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
            ${currentRoute.length}
        </div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-800">
                Location Point ${currentRoute.length}
            </p>
            <p class="text-xs text-gray-500">
                ${time} • Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}
            </p>
        </div>
    `;
    
    timeline.appendChild(pointElement);
}

// Stop tracking
function stopLocationTracking() {
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
    }
    
    isTracking = false;
    
    // Update UI
    document.getElementById('startTracking').disabled = false;
    document.getElementById('pauseTracking').disabled = true;
    document.getElementById('stopTracking').disabled = true;
    document.getElementById('startTracking').innerHTML = '<i class="fas fa-play mr-2"></i>Start Patrol';
    
    // Send stop notification to server
    fetch('handlers/stop_patrol.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'stop',
            route_data: currentRoute,
            total_distance: totalDistance
        })
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    initPersonalMap();
    
    // Start tracking button
    document.getElementById('startTracking').addEventListener('click', startLocationTracking);
    
    // Pause tracking button
    document.getElementById('pauseTracking').addEventListener('click', function() {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            this.innerHTML = '<i class="fas fa-play mr-2"></i>Resume';
            this.onclick = resumeTracking;
        }
    });
    
    // Stop tracking button
    document.getElementById('stopTracking').addEventListener('click', stopLocationTracking);
    
    // Manual location update
    document.getElementById('updateLocation').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                saveLocationToServer(position.coords.latitude, position.coords.longitude);
                updateMapWithNewLocation(position.coords.latitude, position.coords.longitude);
                showNotification('Location updated successfully', 'success');
            });
        }
    });
    
    // Export route button
    document.getElementById('exportRoute').addEventListener('click', function() {
        exportRouteData();
    });
});

// Resume tracking
function resumeTracking() {
    startLocationTracking();
    this.innerHTML = '<i class="fas fa-pause mr-2"></i>Pause';
    this.onclick = function() {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            this.innerHTML = '<i class="fas fa-play mr-2"></i>Resume';
            this.onclick = resumeTracking;
        }
    };
}

// Export route data
function exportRouteData() {
    const data = {
        date: new Date().toISOString().split('T')[0],
        points: currentRoute.length > 0 ? currentRoute : <?php echo json_encode($route_points); ?>,
        distance: totalDistance,
        duration: document.getElementById('patrolTime').textContent
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `patrol_route_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
}

// Helper functions
function formatTime(dateTime) {
    const date = new Date(dateTime);
    return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

<style>
.leaflet-div-icon {
    background: transparent !important;
    border: none !important;
}

.custom-div-icon {
    background: transparent !important;
    border: none !important;
}

.leaflet-tooltip {
    font-size: 12px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 4px 8px;
}

.status-badge {
    @apply px-2 py-1 rounded-full text-xs font-medium;
}
.status-warning { @apply bg-yellow-100 text-yellow-800; }
.status-error { @apply bg-red-100 text-red-800; }
.status-success { @apply bg-green-100 text-green-800; }
</style>