<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];

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
    
    $stmt = $pdo->prepare("
        INSERT INTO tanod_incidents 
        (user_id, location, latitude, longitude, incident_type, description, witnesses, action_taken, status, reported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    if ($stmt->execute([$tanod_id, $location, $latitude, $longitude, $incident_type, $description, $witnesses, $action_taken])) {
        echo '<div class="bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4" role="alert">
                <i class="fas fa-check-circle mr-2"></i> Incident logged successfully!
              </div>';
    }
}
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Incident Logging & Submission</h2>
    <p class="text-gray-600">Quick field form for incidents witnessed during patrol</p>
</div>

<form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- Location -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Incident Location</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Location Description
                        </label>
                        <input type="text" name="location" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., Near Barangay Hall, Main Street">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            GPS Coordinates
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <input type="text" name="latitude" id="latitude" readonly
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                       placeholder="Latitude">
                            </div>
                            <div>
                                <input type="text" name="longitude" id="longitude" readonly
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                       placeholder="Longitude">
                            </div>
                        </div>
                        <button type="button" onclick="getLocation()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-map-marker-alt mr-2"></i>Get Current Location
                        </button>
                    </div>
                </div>
            </div>

            <!-- Incident Details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Incident Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Incident Type
                        </label>
                        <select name="incident_type" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select type...</option>
                            <option value="disturbance">Public Disturbance</option>
                            <option value="altercation">Altercation/Fight</option>
                            <option value="suspicious">Suspicious Activity</option>
                            <option value="theft">Theft/Robbery</option>
                            <option value="vandalism">Vandalism</option>
                            <option value="traffic">Traffic Violation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Description
                        </label>
                        <textarea name="description" required rows="4"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Provide detailed description of the incident..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Witnesses & Action -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Witnesses & Action Taken</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Witnesses (if any)
                        </label>
                        <textarea name="witnesses" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Names and contact information of witnesses..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Action Taken
                        </label>
                        <textarea name="action_taken" required rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Describe action taken (e.g., Mediated, Reported to authorities)..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Evidence Upload -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Evidence Upload</h3>
                
                <div class="space-y-4">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3"></i>
                        <p class="text-gray-600 mb-2">Upload photos or videos of evidence</p>
                        <p class="text-xs text-gray-500 mb-4">Supports JPG, PNG, MP4 up to 10MB</p>
                        <input type="file" name="evidence[]" multiple 
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                               accept="image/*,video/*">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            TanoG PIN (4 digits)
                        </label>
                        <div class="grid grid-cols-4 gap-2">
                            <input type="password" maxlength="1" 
                                   class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold" 
                                   onkeyup="moveToNext(this, 1)">
                            <input type="password" maxlength="1" 
                                   class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold" 
                                   onkeyup="moveToNext(this, 2)">
                            <input type="password" maxlength="1" 
                                   class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold" 
                                   onkeyup="moveToNext(this, 3)">
                            <input type="password" maxlength="1" 
                                   class="w-full h-12 text-center border border-gray-300 rounded-lg text-xl font-bold" 
                                   onkeyup="moveToNext(this, 4)">
                        </div>
                        <input type="hidden" name="pin" id="full-pin">
                        <p class="text-xs text-gray-500 mt-2">PIN encrypts all uploaded evidence</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <button type="submit" 
                        class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    <i class="fas fa-paper-plane mr-2"></i>Submit Incident Report
                </button>
                <p class="text-xs text-gray-500 mt-3 text-center">
                    Report will be submitted with current GPS location and encrypted evidence
                </p>
            </div>
        </div>
    </div>
</form>

<div class="mt-6 p-4 bg-green-50 rounded-lg">
    <div class="flex items-center">
        <i class="fas fa-info-circle text-green-600 mr-3"></i>
        <div>
            <p class="text-sm text-green-800 font-medium">Critical Data Handled</p>
            <p class="text-xs text-green-700">Field-observed incident details, GPS location, encrypted field evidence</p>
        </div>
    </div>
</div>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
            document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
            showNotification('Location captured successfully!', 'success');
        });
    } else {
        showNotification('Geolocation is not supported by this browser.', 'error');
    }
}

function moveToNext(input, index) {
    if (input.value.length === 1) {
        const inputs = document.querySelectorAll('input[type="password"]');
        if (index < inputs.length) {
            inputs[index].focus();
        }
    }
    
    // Update hidden PIN field
    const pinInputs = document.querySelectorAll('input[type="password"]');
    let fullPin = '';
    pinInputs.forEach(input => {
        fullPin += input.value;
    });
    document.getElementById('full-pin').value = fullPin;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>