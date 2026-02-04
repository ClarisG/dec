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

// Fetch GPS data from API (for stats only, since map is embedded)
$gps_data = [];
try {
    $api_url = 'https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123';
    $api_response = file_get_contents($api_url);
    
    if ($api_response !== false) {
        $gps_data = json_decode($api_response, true);
        
        // If the API returns an error or empty data
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($gps_data)) {
            $gps_data = [];
            error_log("GPS API returned invalid JSON or empty data");
        }
    } else {
        error_log("Failed to fetch GPS data from API");
    }
} catch (Exception $e) {
    error_log("GPS API error: " . $e->getMessage());
    $gps_data = [];
}

// Merge GPS data with tanod information for stats display
foreach ($tanods as &$tanod) {
    $tanod['location_lat'] = null;
    $tanod['location_lng'] = null;
    $tanod['last_gps_update'] = null;
    
    // Find matching GPS data for this tanod
    foreach ($gps_data as $gps) {
        if (isset($gps['user_id']) && $gps['user_id'] == $tanod['id']) {
            $tanod['location_lat'] = $gps['latitude'] ?? null;
            $tanod['location_lng'] = $gps['longitude'] ?? null;
            $tanod['last_gps_update'] = $gps['timestamp'] ?? null;
            break;
        }
    }
}
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
                    <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                    <span class="text-sm text-gray-600">GPS Active</span>
                </div>
            </div>
        </div>
        
        <!-- EMBED DIRECT GPS DATA API IN IFRAME -->
        <iframe 
            id="gpsMapFrame"
            src="https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123" 
            style="height: 500px; width: 100%; border: 1px solid #e5e7eb;" 
            class="mb-6 rounded-lg"
            title="Tanod GPS Tracker Map"
            frameborder="0"
            scrolling="no"
            onload="resizeIframe(this)"
        ></iframe>
        
        <!-- Alternative if the API doesn't load properly -->
        <div id="mapFallback" class="hidden mb-6 rounded-lg border border-gray-300" style="height: 500px; width: 100%;">
            <div class="h-full w-full flex flex-col items-center justify-center bg-gray-100">
                <i class="fas fa-map-marked-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600 font-medium mb-2">Unable to load GPS map</p>
                <p class="text-gray-500 text-sm mb-4">The GPS data API is not displaying properly</p>
                <a href="https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123" 
                   target="_blank" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                   <i class="fas fa-external-link-alt mr-2"></i>Open GPS Data in New Tab
                </a>
            </div>
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
                    <?php echo count(array_filter($tanods, fn($t) => $t['location_lat'] && $t['location_lng'])); ?>
                </p>
            </div>
        </div>
        
        <!-- Refresh Controls -->
        <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Map data loaded from: https://cpas.jampzdev.com/admin/api/gps_data.php
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshMap()" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh Map
                </button>
                <button onclick="openMapInNewTab()" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                    <i class="fas fa-external-link-alt mr-1"></i>Open Full Screen
                </button>
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
                                    <i class="fas fa-satellite"></i> Live GPS
                                    <?php if ($tanod['last_gps_update']): ?>
                                        <span class="text-gray-500">(<?php echo date('H:i', strtotime($tanod['last_gps_update'])); ?>)</span>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-yellow-600">
                                    <i class="fas fa-satellite"></i> No GPS Signal
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

<script>
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

// Function to resize iframe if needed
function resizeIframe(iframe) {
    // Check if iframe loaded successfully
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (iframeDoc && iframeDoc.body) {
            // If iframe has content, hide fallback
            document.getElementById('mapFallback').classList.add('hidden');
        }
    } catch (e) {
        // Cross-origin error - can't access iframe content
        console.log('Iframe loaded (cross-origin):', iframe.src);
        // Show fallback if iframe appears empty
        setTimeout(() => {
            checkIframeContent();
        }, 2000);
    }
}

// Check if iframe has content
function checkIframeContent() {
    const iframe = document.getElementById('gpsMapFrame');
    const fallback = document.getElementById('mapFallback');
    
    // Try to detect if iframe is showing JSON (API response) instead of a map
    fetch('https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123')
        .then(response => response.text())
        .then(text => {
            // If response is JSON (starts with { or [), it's not a map
            const trimmed = text.trim();
            if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
                console.log('API returns JSON, not HTML map');
                iframe.classList.add('hidden');
                fallback.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error checking API response:', err);
        });
}

// Refresh the embedded map
function refreshMap() {
    const iframe = document.getElementById('gpsMapFrame');
    const currentSrc = iframe.src;
    
    // Add timestamp to prevent caching
    const separator = currentSrc.includes('?') ? '&' : '?';
    iframe.src = currentSrc + separator + '_t=' + new Date().getTime();
    
    // Show refresh notification
    showNotification('Refreshing GPS map...', 'info');
}

// Open map in new tab
function openMapInNewTab() {
    window.open('https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123', '_blank');
}

// Function to show notifications
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Auto-refresh map every 60 seconds
let mapRefreshInterval;
function startMapAutoRefresh() {
    // Clear existing interval
    if (mapRefreshInterval) {
        clearInterval(mapRefreshInterval);
    }
    
    // Start new interval
    mapRefreshInterval = setInterval(() => {
        refreshMap();
    }, 60000); // Refresh every 60 seconds
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Start auto-refresh
    startMapAutoRefresh();
    
    // Check iframe content after page loads
    setTimeout(() => {
        checkIframeContent();
    }, 3000);
});
</script>

<style>
/* Remove old Leaflet styles - keeping only minimal custom styles */

.animate-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Custom iframe styling */
#iframe {
    transition: all 0.3s ease;
}

#iframe:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
</style>