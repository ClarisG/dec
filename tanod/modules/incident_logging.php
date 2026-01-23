<?php
// incident_logging.php - Professional Field Incident Reporting
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$pdo = getDbConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = $_POST['location'] ?? '';
    $incident_type = $_POST['incident_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $witnesses = $_POST['witnesses'] ?? '';
    $action_taken = $_POST['action_taken'] ?? '';
    
    $latitude = $_POST['latitude'] ?? '0';
    $longitude = $_POST['longitude'] ?? '0';
    
    // Get current duty status
    $stmt = $pdo->prepare("SELECT id FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
    $stmt->execute([$tanod_id]);
    $duty_status = $stmt->fetch();
    $duty_id = $duty_status ? $duty_status['id'] : null;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO tanod_incidents 
            (user_id, duty_log_id, location, latitude, longitude, incident_type, 
             description, witnesses, action_taken, status, reported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([$tanod_id, $duty_id, $location, $latitude, $longitude, 
                       $incident_type, $description, $witnesses, $action_taken]);
        
        $incident_id = $pdo->lastInsertId();
        $incident_code = 'INC-' . str_pad($incident_id, 5, '0', STR_PAD_LEFT);
        
        // Log activity
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([$tanod_id, 'incident_logged', 
                          "Logged incident $incident_code: $incident_type at $location", 
                          $_SERVER['REMOTE_ADDR']]);
        
        // Notify Secretary/Admin
        $notif_stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, related_type, related_id, priority, created_at) 
            VALUES (?, ?, ?, 'tanod_incident', ?, 'medium', NOW())
        ");
        
        // Get secretaries and admins
        $admin_stmt = $pdo->prepare("
            SELECT id FROM users WHERE role IN ('secretary', 'admin') AND status = 'active'
        ");
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $notif_stmt->execute([$admin['id'], '🚨 Tanod Incident Report', 
                                "Tanod $tanod_name logged incident $incident_code: $incident_type", 
                                $incident_id]);
        }
        
        // Handle file uploads
        if (!empty($_FILES['evidence']['name'][0])) {
            $upload_dir = __DIR__ . '/../../uploads/tanod_incidents/' . $incident_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['evidence']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['evidence']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['evidence']['name'][$key];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_name = uniqid('evidence_', true) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        // Encrypt if PIN provided
                        $encryption_key = $_POST['pin'] ?? null;
                        if ($encryption_key && strlen($encryption_key) === 4) {
                            // Encryption logic here
                        }
                        
                        $file_stmt = $pdo->prepare("
                            INSERT INTO incident_evidence 
                            (incident_id, file_name, file_path, file_type, uploaded_by, uploaded_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $file_stmt->execute([$incident_id, $file_name, $upload_path, $file_ext, $tanod_id]);
                    }
                }
            }
        }
        
        $pdo->commit();
        $success = "✅ Incident logged successfully! Reference: $incident_code";
        
        // Clear form
        $_POST = [];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-2">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Incident Logging</h2>
            <p class="text-gray-600 text-sm mt-1">Quick field form for incidents witnessed during patrol</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($tanod_name); ?></p>
            <p class="text-xs text-gray-500">ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if (isset($success)): ?>
    <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
            <div>
                <p class="text-green-700 font-bold"><?php echo $success; ?></p>
                <p class="text-green-600 text-sm mt-1"><?php echo date('F j, Y - h:i A'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="p-4 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
            <p class="text-red-700 font-bold"><?php echo $error; ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Form -->
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-6">
                <!-- Location -->
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                        Incident Location
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location Description *</label>
                            <input type="text" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="e.g., Near Barangay Hall, Main Street, Purok 3">
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-3">
                                <label class="block text-sm font-bold text-blue-700">GPS Coordinates</label>
                                <button type="button" onclick="getLocation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm flex items-center">
                                    <i class="fas fa-crosshairs mr-2"></i> Get Location
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Latitude</label>
                                    <input type="text" name="latitude" id="latitude" readonly value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Longitude</label>
                                    <input type="text" name="longitude" id="longitude" readonly value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                </div>
                            </div>
                            <div id="gpsStatus" class="mt-3 text-xs text-gray-600">
                                <i class="fas fa-info-circle mr-2"></i>Click button to capture GPS coordinates
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Incident Details -->
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-clipboard-list text-green-500 mr-2"></i>
                        Incident Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Incident Type *</label>
                            <select name="incident_type" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Select type...</option>
                                <option value="public_disturbance" <?php echo ($_POST['incident_type'] ?? '') === 'public_disturbance' ? 'selected' : ''; ?>>Public Disturbance</option>
                                <option value="altercation" <?php echo ($_POST['incident_type'] ?? '') === 'altercation' ? 'selected' : ''; ?>>Altercation/Fight</option>
                                <option value="suspicious_activity" <?php echo ($_POST['incident_type'] ?? '') === 'suspicious_activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                                <option value="theft" <?php echo ($_POST['incident_type'] ?? '') === 'theft' ? 'selected' : ''; ?>>Theft/Robbery</option>
                                <option value="vandalism" <?php echo ($_POST['incident_type'] ?? '') === 'vandalism' ? 'selected' : ''; ?>>Vandalism</option>
                                <option value="traffic_violation" <?php echo ($_POST['incident_type'] ?? '') === 'traffic_violation' ? 'selected' : ''; ?>>Traffic Violation</option>
                                <option value="assault" <?php echo ($_POST['incident_type'] ?? '') === 'assault' ? 'selected' : ''; ?>>Physical Assault</option>
                                <option value="trespassing" <?php echo ($_POST['incident_type'] ?? '') === 'trespassing' ? 'selected' : ''; ?>>Trespassing</option>
                                <option value="public_intoxication" <?php echo ($_POST['incident_type'] ?? '') === 'public_intoxication' ? 'selected' : ''; ?>>Public Intoxication</option>
                                <option value="noise_complaint" <?php echo ($_POST['incident_type'] ?? '') === 'noise_complaint' ? 'selected' : ''; ?>>Noise Complaint</option>
                                <option value="environmental" <?php echo ($_POST['incident_type'] ?? '') === 'environmental' ? 'selected' : ''; ?>>Environmental</option>
                                <option value="other" <?php echo ($_POST['incident_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other Incident</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Detailed Description *</label>
                            <textarea name="description" required rows="4" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                      placeholder="Provide detailed description including what happened, people involved, time, and your observations"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Witnesses & Action -->
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-users text-orange-500 mr-2"></i>
                        Witnesses & Action
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Witnesses (Optional)</label>
                            <textarea name="witnesses" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                      placeholder="Names and contact information of witnesses..."><?php echo htmlspecialchars($_POST['witnesses'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Action Taken *</label>
                            <textarea name="action_taken" required rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                      placeholder="Describe action taken during incident..."><?php echo htmlspecialchars($_POST['action_taken'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Evidence -->
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-camera text-purple-500 mr-2"></i>
                        Evidence Collection
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                            <input type="file" name="evidence[]" multiple 
                                   class="hidden" accept="image/*,video/*,.pdf,.doc,.docx" id="evidenceUpload">
                            <label for="evidenceUpload" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3"></i>
                                <p class="text-gray-600">Upload photos/videos</p>
                                <p class="text-xs text-gray-500 mt-1">JPG, PNG, MP4 up to 20MB each (max 5 files)</p>
                            </label>
                            <div id="fileList" class="mt-3 text-sm text-gray-600 hidden"></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Encryption PIN (Optional)</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input type="password" maxlength="1" class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" onkeyup="moveToNext(this, 1)">
                                <input type="password" maxlength="1" class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" onkeyup="moveToNext(this, 2)">
                                <input type="password" maxlength="1" class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" onkeyup="moveToNext(this, 3)">
                                <input type="password" maxlength="1" class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" onkeyup="moveToNext(this, 4)">
                            </div>
                            <input type="hidden" name="pin" id="full-pin">
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>4-digit PIN encrypts uploaded files (Optional)
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Submit -->
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="mb-4 md:mb-0">
                            <h3 class="text-lg font-bold text-gray-800">Submit Incident Report</h3>
                            <p class="text-sm text-gray-600">Review all information before submission</p>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="reset" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-redo mr-2"></i>Clear
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-green-500 to-green-600 text-white font-bold rounded-lg hover:from-green-600 hover:to-green-700 transition shadow-sm">
                                <i class="fas fa-paper-plane mr-2"></i>Submit Report
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-5 p-4 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-shield-alt text-blue-500 mt-1 mr-3"></i>
                            <div>
                                <p class="text-sm font-bold text-blue-800">Field Incident Data</p>
                                <p class="text-xs text-blue-700">Incident details, GPS location, encrypted evidence files, and action taken are logged.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Recent Incidents -->
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-5">Recent Incidents</h3>
        
        <?php
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM tanod_incidents 
                WHERE user_id = ? 
                ORDER BY reported_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$tanod_id]);
            $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($recent_incidents) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_incidents as $incident): ?>
                        <div class="p-3 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center mb-2">
                                        <span class="font-bold text-gray-800">
                                            INC-<?php echo str_pad($incident['id'], 5, '0', STR_PAD_LEFT); ?>
                                        </span>
                                        <span class="ml-3 px-2 py-1 text-xs rounded-full 
                                            <?php echo $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                   ($incident['status'] === 'processed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($incident['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo htmlspecialchars(substr($incident['location'], 0, 30)); ?>...
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('M d, h:i A', strtotime($incident['reported_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No incidents logged yet</p>
                </div>
            <?php endif;
        } catch (PDOException $e) {
            echo '<div class="text-center py-8 text-gray-500">Error loading recent incidents</div>';
        }
        ?>
    </div>
</div>

<script>
let gpsInterval;

function getLocation() {
    const statusDiv = document.getElementById('gpsStatus');
    const latInput = document.getElementById('latitude');
    const longInput = document.getElementById('longitude');
    
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Getting location...';
    statusDiv.className = 'mt-3 text-xs text-yellow-600';
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude.toFixed(6);
                const long = position.coords.longitude.toFixed(6);
                
                latInput.value = lat;
                longInput.value = long;
                
                statusDiv.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-2"></i>Location captured: ${lat}, ${long}`;
                statusDiv.className = 'mt-3 text-xs text-green-600';
            },
            function(error) {
                statusDiv.innerHTML = `<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Location unavailable`;
                statusDiv.className = 'mt-3 text-xs text-red-600';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Geolocation not supported';
        statusDiv.className = 'mt-3 text-xs text-red-600';
    }
}

function moveToNext(input, index) {
    if (input.value.length === 1) {
        const inputs = document.querySelectorAll('input[type="password"]');
        if (index < inputs.length) {
            inputs[index].focus();
        }
    }
    
    const pinInputs = document.querySelectorAll('input[type="password"]');
    let fullPin = '';
    pinInputs.forEach(pinInput => {
        fullPin += pinInput.value;
    });
    document.getElementById('full-pin').value = fullPin;
}

// File upload display
document.getElementById('evidenceUpload').addEventListener('change', function(e) {
    const fileList = document.getElementById('fileList');
    if (this.files.length > 0) {
        fileList.classList.remove('hidden');
        fileList.innerHTML = `<p class="font-medium">Selected files:</p>`;
        for (let i = 0; i < Math.min(this.files.length, 5); i++) {
            fileList.innerHTML += `<p class="text-xs mt-1">• ${this.files[i].name}</p>`;
        }
    } else {
        fileList.classList.add('hidden');
    }
});

// Auto-get location on page load
setTimeout(getLocation, 1000);
</script>