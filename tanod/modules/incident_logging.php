<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get database connection
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = $_POST['location'] ?? '';
    $incident_type = $_POST['incident_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $witnesses = $_POST['witnesses'] ?? '';
    $action_taken = $_POST['action_taken'] ?? '';
    
    // Generate GPS coordinates (in real app, get from device)
    $latitude = $_POST['latitude'] ?? '0';
    $longitude = $_POST['longitude'] ?? '0';
    
    // Get current duty status
    $stmt = $pdo->prepare("SELECT * FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
    $stmt->execute([$tanod_id]);
    $duty_status = $stmt->fetch();
    
    $duty_id = $duty_status ? $duty_status['id'] : null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tanod_incidents 
            (user_id, duty_log_id, location, latitude, longitude, incident_type, description, witnesses, action_taken, status, reported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        if ($stmt->execute([$tanod_id, $duty_id, $location, $latitude, $longitude, $incident_type, $description, $witnesses, $action_taken])) {
            $incident_id = $pdo->lastInsertId();
            
            // Log activity
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $tanod_id, 
                'incident_logged', 
                "Logged incident #$incident_id: $incident_type at $location", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['success_message'] = 'Incident logged successfully!';
            
            // Handle evidence upload if any
            if (!empty($_FILES['evidence']['name'][0])) {
                // Create upload directory if it doesn't exist
                $upload_dir = __DIR__ . '/../../uploads/tanod_incidents/' . $incident_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Process each file
                foreach ($_FILES['evidence']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['evidence']['name'][$key];
                    $file_size = $_FILES['evidence']['size'][$key];
                    $file_error = $_FILES['evidence']['error'][$key];
                    
                    if ($file_error === UPLOAD_ERR_OK) {
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $new_file_name = uniqid('evidence_', true) . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            // Log file in database
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
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error logging incident: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Logging - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gps-active {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-white">
                            <i class="fas fa-exclamation-triangle mr-3"></i>
                            Incident Logging & Submission
                        </h1>
                        <p class="text-blue-100 mt-2">Quick field form for incidents witnessed during patrol</p>
                    </div>
                    <div class="text-white">
                        <p class="text-sm">Logged in as: <span class="font-bold"><?php echo htmlspecialchars($tanod_name); ?></span></p>
                        <p class="text-sm">Tanod ID: <span class="font-bold">TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                <div>
                    <p class="text-green-700 font-medium"><?php echo $_SESSION['success_message']; ?></p>
                    <p class="text-green-600 text-sm mt-1"><?php echo date('F j, Y - h:i A'); ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                <p class="text-red-700 font-medium"><?php echo $_SESSION['error_message']; ?></p>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Location Card -->
                    <div class="glass-card rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            Incident Location
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Location Description *
                                </label>
                                <input type="text" name="location" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                       placeholder="e.g., Near Barangay Hall, Main Street, Purok 3">
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-sm font-medium text-blue-700">
                                        <i class="fas fa-satellite mr-2"></i>
                                        GPS Coordinates
                                    </label>
                                    <button type="button" onclick="getLocation()" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm flex items-center">
                                        <i class="fas fa-crosshairs mr-2"></i>
                                        Get Current Location
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Latitude</label>
                                        <input type="text" name="latitude" id="latitude" readonly
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Longitude</label>
                                        <input type="text" name="longitude" id="longitude" readonly
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                    </div>
                                </div>
                                
                                <div id="gpsStatus" class="mt-3 text-sm text-gray-600">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Click button to capture GPS coordinates
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Incident Details Card -->
                    <div class="glass-card rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-clipboard-list text-green-500 mr-2"></i>
                            Incident Details
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Incident Type *
                                </label>
                                <select name="incident_type" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    <option value="">Select incident type...</option>
                                    <option value="public_disturbance">🔊 Public Disturbance</option>
                                    <option value="altercation">👊 Altercation/Fight</option>
                                    <option value="suspicious_activity">👁️ Suspicious Activity</option>
                                    <option value="theft">💰 Theft/Robbery</option>
                                    <option value="vandalism">🎨 Vandalism/Property Damage</option>
                                    <option value="traffic_violation">🚗 Traffic Violation</option>
                                    <option value="assault">🤕 Physical Assault</option>
                                    <option value="trespassing">🚷 Trespassing</option>
                                    <option value="public_intoxication">🍻 Public Intoxication</option>
                                    <option value="noise_complaint">🔊 Noise Complaint</option>
                                    <option value="environmental">🌳 Environmental Concern</option>
                                    <option value="other">📝 Other Incident</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Detailed Description *
                                </label>
                                <textarea name="description" required rows="4"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                          placeholder="Provide detailed description including:
• What happened
• People involved
• Time of incident
• Any immediate danger
• Your observations"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Witnesses & Action Card -->
                    <div class="glass-card rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-users text-orange-500 mr-2"></i>
                            Witnesses & Action Taken
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Witnesses (if any)
                                </label>
                                <textarea name="witnesses" rows="3"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                          placeholder="Names and contact information of witnesses...
Example:
• Juan Dela Cruz - 09123456789
• Maria Santos - 09234567890"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Action Taken *
                                </label>
                                <textarea name="action_taken" required rows="3"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                          placeholder="Describe action taken during incident...
Example:
• Separated parties involved
• Provided first aid to injured person
• Called for backup
• Collected statements
• Secured the area"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Evidence Upload Card -->
                    <div class="glass-card rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-camera text-purple-500 mr-2"></i>
                            Evidence Collection
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 transition-colors">
                                <div class="text-gray-400 text-4xl mb-4">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <p class="text-gray-600 mb-2">Upload photos or videos of evidence</p>
                                <p class="text-sm text-gray-500 mb-4">Supports JPG, PNG, MP4, MOV up to 20MB each</p>
                                <input type="file" name="evidence[]" multiple 
                                       class="block w-full text-sm text-gray-500 
                                              file:mr-4 file:py-3 file:px-6 
                                              file:rounded-lg file:border-0 
                                              file:text-sm file:font-semibold 
                                              file:bg-blue-50 file:text-blue-700 
                                              hover:file:bg-blue-100 cursor-pointer"
                                       accept="image/*,video/*,.pdf,.doc,.docx">
                                <p class="text-xs text-gray-400 mt-3">Maximum 5 files allowed</p>
                            </div>
                            
                            <!-- TanoG PIN (Optional) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-lock text-gray-500 mr-2"></i>
                                    TanoG PIN (Optional for Encryption)
                                </label>
                                <div class="grid grid-cols-4 gap-2">
                                    <input type="password" maxlength="1" 
                                           class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" 
                                           onkeyup="moveToNext(this, 1)">
                                    <input type="password" maxlength="1" 
                                           class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" 
                                           onkeyup="moveToNext(this, 2)">
                                    <input type="password" maxlength="1" 
                                           class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" 
                                           onkeyup="moveToNext(this, 3)">
                                    <input type="password" maxlength="1" 
                                           class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500" 
                                           onkeyup="moveToNext(this, 4)">
                                </div>
                                <input type="hidden" name="pin" id="full-pin">
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    4-digit PIN encrypts uploaded evidence files (Optional)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Card -->
                    <div class="glass-card rounded-xl shadow-lg p-6">
                        <div class="flex flex-col md:flex-row justify-between items-center">
                            <div class="mb-4 md:mb-0">
                                <h3 class="text-lg font-bold text-gray-800">Submit Incident Report</h3>
                                <p class="text-sm text-gray-600">Review all information before submission</p>
                            </div>
                            
                            <div class="flex space-x-3">
                                <button type="reset" 
                                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-redo mr-2"></i>
                                    Clear Form
                                </button>
                                <button type="submit" 
                                        class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold rounded-lg hover:from-green-700 hover:to-green-800 transition shadow-lg">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Incident Report
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-green-800">Critical Data Handled</p>
                                    <p class="text-xs text-green-700">Field-observed incident details, GPS location at time of logging, encrypted multimedia evidence</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Recent Incidents (Optional) -->
        <div class="mt-8 glass-card rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-history text-blue-500 mr-2"></i>
                Recently Logged Incidents
            </h2>
            
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
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_incidents as $incident): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            INC-<?php echo str_pad($incident['id'], 5, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?php echo htmlspecialchars(substr($incident['location'], 0, 30)); ?>...
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            <?php echo date('M d, h:i A', strtotime($incident['reported_at'])); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-3 py-1 text-xs rounded-full 
                                                <?php echo $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                       ($incident['status'] === 'processed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                        <p>No incidents logged yet</p>
                        <p class="text-sm mt-2">Your logged incidents will appear here</p>
                    </div>
                <?php endif;
            } catch (PDOException $e) {
                echo '<div class="text-center py-8 text-red-500">Error loading recent incidents</div>';
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
        statusDiv.className = 'mt-3 text-sm text-yellow-600';
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude.toFixed(6);
                    const long = position.coords.longitude.toFixed(6);
                    
                    latInput.value = lat;
                    longInput.value = long;
                    
                    statusDiv.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-2"></i>Location captured: ${lat}, ${long}`;
                    statusDiv.className = 'mt-3 text-sm text-green-600';
                    
                    // Start updating location every 10 seconds
                    if (gpsInterval) clearInterval(gpsInterval);
                    gpsInterval = setInterval(updateLocation, 10000);
                },
                function(error) {
                    let errorMessage = 'Unable to retrieve location. ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Permission denied.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Location unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Request timeout.';
                            break;
                        default:
                            errorMessage += 'Unknown error.';
                    }
                    
                    statusDiv.innerHTML = `<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>${errorMessage}`;
                    statusDiv.className = 'mt-3 text-sm text-red-600';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Geolocation not supported by browser';
            statusDiv.className = 'mt-3 text-sm text-red-600';
        }
    }
    
    function updateLocation() {
        if (!navigator.geolocation) return;
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude.toFixed(6);
                const long = position.coords.longitude.toFixed(6);
                
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = long;
                
                const statusDiv = document.getElementById('gpsStatus');
                statusDiv.innerHTML = `<i class="fas fa-sync-alt text-blue-500 mr-2 gps-active"></i>Location updated: ${lat}, ${long}`;
            },
            null,
            { enableHighAccuracy: true, timeout: 5000 }
        );
    }
    
    function moveToNext(input, index) {
        if (input.value.length === 1) {
            const inputs = document.querySelectorAll('input[type="password"]');
            if (index < inputs.length) {
                inputs[index].focus();
            } else {
                input.blur();
            }
        }
        
        // Update hidden PIN field
        const pinInputs = document.querySelectorAll('input[type="password"]');
        let fullPin = '';
        pinInputs.forEach(pinInput => {
            fullPin += pinInput.value;
        });
        document.getElementById('full-pin').value = fullPin;
    }
    
    // Auto-size textareas
    document.addEventListener('input', function(e) {
        if (e.target.tagName === 'TEXTAREA') {
            e.target.style.height = 'auto';
            e.target.style.height = (e.target.scrollHeight) + 'px';
        }
    });
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const location = document.querySelector('input[name="location"]');
            const incidentType = document.querySelector('select[name="incident_type"]');
            const description = document.querySelector('textarea[name="description"]');
            const actionTaken = document.querySelector('textarea[name="action_taken"]');
            
            let errors = [];
            
            if (!location.value.trim()) {
                errors.push('Location description is required');
                location.classList.add('border-red-500');
            }
            
            if (!incidentType.value) {
                errors.push('Incident type is required');
                incidentType.classList.add('border-red-500');
            }
            
            if (!description.value.trim()) {
                errors.push('Incident description is required');
                description.classList.add('border-red-500');
            }
            
            if (!actionTaken.value.trim()) {
                errors.push('Action taken is required');
                actionTaken.classList.add('border-red-500');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });
        
        // Clear error styles on input
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
        });
        
        // Initialize textarea heights
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        });
    });
    
    // Auto-get location on page load (with permission)
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(getLocation, 1000);
    });
    
    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (gpsInterval) clearInterval(gpsInterval);
    });
    </script>
</body>
</html>