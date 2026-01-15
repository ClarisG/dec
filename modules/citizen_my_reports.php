<?php
// modules/citizen_new_report.php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// citizen_new_report.php - COMPLETE UPDATED VERSION with AI for ALL Categories
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("User not logged in");
}

$error = '';
$success = '';

// Check rate limit before processing
if (!checkRateLimit($user_id)) {
    $error = "You have submitted too many reports recently. Please wait 1 hour before submitting another report.";
}

// Get report types for dropdown
$categorized_types = [
    'incident' => [],
    'complaint' => [],
    'blotter' => []
];

try {
    $conn = getDbConnection();
    
    // Get all report types with keywords
    $types_query = "SELECT *, COALESCE(keywords, '') as keywords FROM report_types ORDER BY category, type_name";
    $types_stmt = $conn->query($types_query);
    $all_report_types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by category
    foreach ($all_report_types as $type) {
        $type_keywords = !empty($type['keywords']) ? explode(',', $type['keywords']) : [];
        $type['all_keywords'] = array_map('trim', $type_keywords);
        $categorized_types[$type['category']][] = $type;
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    // Check rate limit again
    if (!checkRateLimit($user_id)) {
        $error = "Rate limit exceeded. Please wait before submitting another report.";
    } else {
        try {
            // Get form data
            $title = trim($_POST['title'] ?? '');
            $report_type_id = intval($_POST['report_type_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $incident_date = ($_POST['incident_date'] ?? '') . ' ' . ($_POST['incident_time'] ?? '00:00');
            $involved_persons = trim($_POST['involved_persons'] ?? '');
            $witnesses = trim($_POST['witnesses'] ?? '');
            
            $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0;
            $category = $_POST['category'] ?? 'incident';
            
            // Validate required fields
            if (empty($title) || empty($description) || empty($location) || empty($incident_date)) {
                $error = "Please fill all required fields.";
            } elseif (empty($report_type_id)) {
                $error = "Please select a report classification.";
            } else {
                // Generate report number
                $report_number = 'RPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                
                // Handle file uploads (max 10 files)
                $evidence_files = [];
                
                if (!empty($_FILES['evidence_files']['name'][0])) {
                    $upload_dir = __DIR__ . "/../uploads/reports/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Also create a subdirectory for this user if it doesn't exist
                    $user_upload_dir = $upload_dir . 'user_' . $user_id . '/';
                    if (!file_exists($user_upload_dir)) {
                        mkdir($user_upload_dir, 0777, true);
                    }
                    
                    // Limit to 10 files
                    $file_count = count($_FILES['evidence_files']['name']);
                    if ($file_count > 10) {
                        $error = "Maximum 10 files allowed. Please remove some files.";
                    } else {
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['evidence_files']['error'][$i] == UPLOAD_ERR_OK) {
                                $original_name = basename($_FILES['evidence_files']['name'][$i]);
                                $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                                
                                // Generate a unique filename
                                $unique_id = time() . '_' . uniqid();
                                $file_name = $unique_id . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $original_name);
                                $file_path = $user_upload_dir . $file_name;
                                
                                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp4', 'avi', 'mov', 'wav', 'mp3'];
                                
                                // Check file type and size (10MB max)
                                if (in_array($file_extension, $allowed_extensions) &&
                                    $_FILES['evidence_files']['size'][$i] <= 10 * 1024 * 1024) {
                                    
                                    if (move_uploaded_file($_FILES['evidence_files']['tmp_name'][$i], $file_path)) {
                                        // Store relative path for database
                                        $relative_path = 'uploads/reports/user_' . $user_id . '/' . $file_name;
                                        
                                        // Store file metadata (NO ENCRYPTION)
                                        $evidence_files[] = [
                                            'original_name' => $original_name,
                                            'stored_name' => $file_name,
                                            'path' => $relative_path,
                                            'file_type' => $file_extension,
                                            'file_size' => $_FILES['evidence_files']['size'][$i],
                                            'encrypted' => false
                                        ];
                                    }
                                } else {
                                    $error = "File '{$original_name}' is not allowed or too large (max 10MB).";
                                }
                            }
                        }
                    }
                }
                
                if (empty($error)) {
                    // Get jurisdiction and severity from report type
                    $type_query = "SELECT jurisdiction, severity_level FROM report_types WHERE id = :id";
                    $type_stmt = $conn->prepare($type_query);
                    $type_stmt->execute([':id' => $report_type_id]);
                    $type_info = $type_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $severity = $type_info['severity_level'] ?? 'medium';
                    
                    // Get user's barangay
                    $user_query = "SELECT barangay FROM users WHERE id = :user_id";
                    $user_stmt = $conn->prepare($user_query);
                    $user_stmt->execute([':user_id' => $user_id]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    $barangay = $user['barangay'] ?? '';
                    
                    // Insert report with all fields (PIN CODE REMOVED)
                    $insert_query = "INSERT INTO reports (
                        report_number, 
                        user_id, 
                        report_type_id, 
                        title, 
                        description, 
                        location,
                        incident_date, 
                        involved_persons, 
                        witnesses, 
                        evidence_files, 
                        status,
                        priority, 
                        is_anonymous, 
                        created_at, 
                        category,
                        barangay
                    ) VALUES (
                        :report_number, 
                        :user_id, 
                        :report_type_id, 
                        :title, 
                        :description, 
                        :location,
                        :incident_date, 
                        :involved_persons, 
                        :witnesses, 
                        :evidence_files, 
                        'pending',
                        :priority, 
                        :is_anonymous, 
                        NOW(), 
                        :category,
                        :barangay
                    )";
                    
                    $insert_stmt = $conn->prepare($insert_query);
                    $evidence_files_json = !empty($evidence_files) ? json_encode($evidence_files) : null;
                    
                    $result = $insert_stmt->execute([
                        ':report_number' => $report_number,
                        ':user_id' => $user_id,
                        ':report_type_id' => $report_type_id,
                        ':title' => $title,
                        ':description' => $description,
                        ':location' => $location,
                        ':incident_date' => $incident_date,
                        ':involved_persons' => $involved_persons,
                        ':witnesses' => $witnesses,
                        ':evidence_files' => $evidence_files_json,
                        ':priority' => $severity,
                        ':is_anonymous' => $is_anonymous,
                        ':category' => $category,
                        ':barangay' => $barangay
                    ]);
                    
                    if ($result) {
                        // Add to status history
                        $report_id = $conn->lastInsertId();
                        $history_query = "INSERT INTO report_status_history (report_id, status, notes, created_at) 
                                        VALUES (:report_id, 'pending', 'Report submitted by citizen', NOW())";
                        $history_stmt = $conn->prepare($history_query);
                        $history_stmt->execute([':report_id' => $report_id]);
                        
                        $success = "Report submitted successfully!<br>
                                    <strong>Report Number:</strong> $report_number<br>
                                    <a href='?module=my-reports' class='text-blue-600 hover:underline'>View in My Reports</a>";
                        
                        // Clear form
                        $_POST = array();
                    } else {
                        $error = "Failed to submit report. Please try again.";
                    }
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Report Submission Error: " . $e->getMessage());
        }
    }
}
?>

<div class="max-w-6xl mx-auto">
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded animate-fadeIn">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div class="flex-1">
                    <p class="text-red-700"><?php echo $error; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded animate-fadeIn">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <div class="flex-1">
                    <p class="text-green-700"><?php echo $success; ?></p>
                </div>
                <a href="?module=my-reports" class="ml-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                    View My Reports
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Category Tabs -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
            <!-- Incident Tab -->
            <button type="button" id="incidentTab" 
                    class="tab-button flex-1 flex items-center justify-center p-4 bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-200 rounded-xl hover:border-red-400 transition-all active-tab">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="text-left">
                        <h3 class="font-bold text-gray-800 text-lg">Incident Report</h3>
                        <p class="text-sm text-gray-600">Crimes, emergencies, accidents</p>
                    </div>
                </div>
            </button>
            
            <!-- Complaint Tab -->
            <button type="button" id="complaintTab" 
                    class="tab-button flex-1 flex items-center justify-center p-4 bg-gradient-to-r from-yellow-50 to-amber-50 border-2 border-yellow-200 rounded-xl hover:border-yellow-400 transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mr-4">
                        <i class="fas fa-comments text-yellow-600 text-xl"></i>
                    </div>
                    <div class="text-left">
                        <h3 class="font-bold text-gray-800 text-lg">Complaint</h3>
                        <p class="text-sm text-gray-600">Issues, nuisances, violations</p>
                    </div>
                </div>
            </button>
            
            <!-- Blotter Tab -->
            <button type="button" id="blotterTab" 
                    class="tab-button flex-1 flex items-center justify-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl hover:border-green-400 transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                    </div>
                    <div class="text-left">
                        <h3 class="font-bold text-gray-800 text-lg">Blotter</h3>
                        <p class="text-sm text-gray-600">Disputes, conflicts, documentation</p>
                    </div>
                </div>
            </button>
        </div>
    </div>
    
    <!-- Incident Report Form (Default Visible) -->
    <div id="incidentForm" class="report-form active-form">
        <form id="incidentReportForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6">
            <input type="hidden" name="category" value="incident">
            <input type="hidden" name="submit_report" value="1">
            
            <!-- AI Assistant Section -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-robot text-blue-500 text-2xl mr-3 mt-1"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 mb-2">AI Report Assistant</h4>
                            <p class="text-sm text-gray-600 mb-3">Describe what happened. AI will analyze and suggest the most appropriate classification.</p>
                            
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Describe what happened
                                </label>
                                <textarea name="description" id="incidentDescription" rows="4" required
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                          placeholder="Example: 'My wallet was stolen at the market yesterday. Someone took it from my bag.'"
                                          oninput="analyzeIncidentDescription(this.value)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                
                                <!-- Character counter -->
                                <div class="flex justify-end mt-1">
                                    <div class="text-xs text-gray-500">
                                        <span id="incidentCharCount">0</span>/5000 characters
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis Results -->
            <div id="incidentAiAnalysis" class="mb-6 hidden animate-fadeIn">
                <!-- AI results will be populated here -->
            </div>
            
            <!-- Report Type Selection (Hidden until AI suggests) -->
            <div id="incidentTypeSection" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Select Report Classification
                </label>
                <div id="incidentTypeSuggestions" class="space-y-3">
                    <!-- AI suggestions will be populated here -->
                </div>
                <input type="hidden" name="report_type_id" id="incidentReportTypeId" value="">
            </div>
            
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Report Title
                    </label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., Theft at Main Street"
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Incident Date & Time
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" name="incident_date" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_date']) ? $_POST['incident_date'] : date('Y-m-d'); ?>">
                        <input type="time" name="incident_time" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_time']) ? $_POST['incident_time'] : date('H:i'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Location -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Incident Location
                </label>
                <textarea name="location" rows="2" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Exact location, landmarks, or GPS coordinates"><?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?></textarea>
            </div>
            
            <!-- Involved Persons -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Involved Persons/Suspects (Optional)
                </label>
                <textarea name="involved_persons" rows="3"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Names, descriptions, roles of involved persons"><?php echo isset($_POST['involved_persons']) ? htmlspecialchars($_POST['involved_persons']) : ''; ?></textarea>
            </div>
            
            <!-- Witnesses -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Witnesses (Optional)
                </label>
                <textarea name="witnesses" rows="3"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Names and contact information of witnesses"><?php echo isset($_POST['witnesses']) ? htmlspecialchars($_POST['witnesses']) : ''; ?></textarea>
            </div>
            
            <!-- Evidence Upload -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Evidence Files <span class="text-sm text-gray-500">(Max 10 files, 10MB each)</span>
                </label>
                
                <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4 hover:border-blue-400 transition-colors cursor-pointer">
                    <input type="file" id="incident_evidence_files" name="evidence_files[]" 
                           class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.mp4,.avi,.mov,.wav,.mp3"
                           onchange="handleFileUpload(this.files, 'incident')">
                    
                    <div class="flex flex-col items-center">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600 font-medium">Drag & drop files here or click to browse</p>
                        <p class="text-sm text-gray-500 mt-1">Supports images, documents, videos, and audio files</p>
                        <button type="button" onclick="document.getElementById('incident_evidence_files').click()"
                                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i> Select Files
                        </button>
                    </div>
                </div>
                
                <!-- File list -->
                <div class="mb-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">
                            Selected Files: <span id="incidentFileCount" class="text-blue-600">0</span>/10
                        </span>
                        <button type="button" onclick="clearFiles('incident')"
                                class="text-sm text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt mr-1"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <div id="incidentFileList" class="space-y-2">
                    <!-- Files will be listed here -->
                </div>
            </div>
            
            <!-- Anonymous Option -->
            <div class="bg-gray-50 rounded-xl p-6 mb-6">
                <!-- Hidden input for unchecked state -->
                <input type="hidden" name="is_anonymous" value="0">
                <div class="flex items-center">
                    <input type="checkbox" id="incident_is_anonymous" name="is_anonymous" value="1" 
                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == 1) ? 'checked' : ''; ?>>
                    <label for="incident_is_anonymous" class="ml-3 block text-sm font-medium text-gray-700">
                        Submit anonymously
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-2 ml-8">
                    Your name will not be visible to the public.
                </p>
            </div>
            
            <!-- Terms and Submit -->
            <div class="border-t pt-6">
                <div class="mb-6">
                    <div class="flex items-start">
                        <input type="checkbox" id="incident_terms" name="terms" required
                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                        <label for="incident_terms" class="ml-3 text-sm text-gray-700">
                            I certify that the information provided is true and accurate to the best of my knowledge.
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="window.history.back()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 mr-4">
                        Cancel
                    </button>
                    <button type="submit" name="submit_report" value="1"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Report
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Complaint Form (Hidden by Default) -->
    <div id="complaintForm" class="report-form hidden">
        <form id="complaintReportForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6">
            <input type="hidden" name="category" value="complaint">
            <input type="hidden" name="submit_report" value="1">
            
            <!-- AI Assistant Section -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border-l-4 border-yellow-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-robot text-yellow-500 text-2xl mr-3 mt-1"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 mb-2">AI Complaint Assistant</h4>
                            <p class="text-sm text-gray-600 mb-3">Describe your complaint. AI will analyze and suggest the most appropriate classification.</p>
                            
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Describe your complaint
                                </label>
                                <textarea name="description" id="complaintDescription" rows="4" required
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                          placeholder="Example: 'My neighbor plays loud music every night until 2 AM.'"
                                          oninput="analyzeComplaintDescription(this.value)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                
                                <!-- Character counter -->
                                <div class="flex justify-end mt-1">
                                    <div class="text-xs text-gray-500">
                                        <span id="complaintCharCount">0</span>/5000 characters
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis Results -->
            <div id="complaintAiAnalysis" class="mb-6 hidden animate-fadeIn">
                <!-- AI results will be populated here -->
            </div>
            
            <!-- Report Type Selection (Hidden until AI suggests) -->
            <div id="complaintTypeSection" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Select Complaint Classification
                </label>
                <div id="complaintTypeSuggestions" class="space-y-3">
                    <!-- AI suggestions will be populated here -->
                </div>
                <input type="hidden" name="report_type_id" id="complaintReportTypeId" value="">
            </div>
            
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Complaint Title
                    </label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., Noise Complaint from Neighbor"
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Date & Time
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" name="incident_date" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_date']) ? $_POST['incident_date'] : date('Y-m-d'); ?>">
                        <input type="time" name="incident_time" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_time']) ? $_POST['incident_time'] : date('H:i'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Location -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Location
                </label>
                <textarea name="location" rows="2" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Where is the issue occurring?"><?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?></textarea>
            </div>
            
            <!-- Evidence Upload -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Evidence Files <span class="text-sm text-gray-500">(Max 10 files, 10MB each)</span>
                </label>
                
                <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4 hover:border-blue-400 transition-colors cursor-pointer">
                    <input type="file" id="complaint_evidence_files" name="evidence_files[]" 
                           class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.mp4,.avi,.mov,.wav,.mp3"
                           onchange="handleFileUpload(this.files, 'complaint')">
                    
                    <div class="flex flex-col items-center">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600 font-medium">Drag & drop files here or click to browse</p>
                        <p class="text-sm text-gray-500 mt-1">Supports images, documents, videos, and audio files</p>
                        <button type="button" onclick="document.getElementById('complaint_evidence_files').click()"
                                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i> Select Files
                        </button>
                    </div>
                </div>
                
                <!-- File list -->
                <div class="mb-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">
                            Selected Files: <span id="complaintFileCount" class="text-blue-600">0</span>/10
                        </span>
                        <button type="button" onclick="clearFiles('complaint')"
                                class="text-sm text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt mr-1"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <div id="complaintFileList" class="space-y-2">
                    <!-- Files will be listed here -->
                </div>
            </div>
            
            <!-- Anonymous Option -->
            <div class="bg-gray-50 rounded-xl p-6 mb-6">
                <!-- Hidden input for unchecked state -->
                <input type="hidden" name="is_anonymous" value="0">
                <div class="flex items-center">
                    <input type="checkbox" id="complaint_is_anonymous" name="is_anonymous" value="1" 
                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == 1) ? 'checked' : ''; ?>>
                    <label for="complaint_is_anonymous" class="ml-3 block text-sm font-medium text-gray-700">
                        Submit anonymously
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-2 ml-8">
                    Your name will not be visible to the public.
                </p>
            </div>
            
            <!-- Terms and Submit -->
            <div class="border-t pt-6">
                <div class="mb-6">
                    <div class="flex items-start">
                        <input type="checkbox" id="complaint_terms" name="terms" required
                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                        <label for="complaint_terms" class="ml-3 text-sm text-gray-700">
                            I certify that the information provided is true and accurate.
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="window.history.back()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 mr-4">
                        Cancel
                    </button>
                    <button type="submit" name="submit_report" value="1"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Complaint
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Blotter Form (Hidden by Default) -->
    <div id="blotterForm" class="report-form hidden">
        <form id="blotterReportForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6">
            <input type="hidden" name="category" value="blotter">
            <input type="hidden" name="submit_report" value="1">
            
            <!-- AI Assistant Section -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-robot text-green-500 text-2xl mr-3 mt-1"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 mb-2">AI Blotter Assistant</h4>
                            <p class="text-sm text-gray-600 mb-3">Describe the situation. AI will analyze and suggest the most appropriate classification.</p>
                            
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Describe the situation
                                </label>
                                <textarea name="description" id="blotterDescription" rows="4" required
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                          placeholder="Example: 'My neighbor and I have a dispute about our property boundary.'"
                                          oninput="analyzeBlotterDescription(this.value)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                
                                <!-- Character counter -->
                                <div class="flex justify-end mt-1">
                                    <div class="text-xs text-gray-500">
                                        <span id="blotterCharCount">0</span>/5000 characters
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis Results -->
            <div id="blotterAiAnalysis" class="mb-6 hidden animate-fadeIn">
                <!-- AI results will be populated here -->
            </div>
            
            <!-- Report Type Selection (Hidden until AI suggests) -->
            <div id="blotterTypeSection" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Select Blotter Classification
                </label>
                <div id="blotterTypeSuggestions" class="space-y-3">
                    <!-- AI suggestions will be populated here -->
                </div>
                <input type="hidden" name="report_type_id" id="blotterReportTypeId" value="">
            </div>
            
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Blotter Title
                    </label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., Neighbor Boundary Dispute"
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Date & Time
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" name="incident_date" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_date']) ? $_POST['incident_date'] : date('Y-m-d'); ?>">
                        <input type="time" name="incident_time" required
                               class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo isset($_POST['incident_time']) ? $_POST['incident_time'] : date('H:i'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Location -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Location
                </label>
                <textarea name="location" rows="2" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Where did this occur?"><?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?></textarea>
            </div>
            
            <!-- Evidence Upload -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Supporting Documents <span class="text-sm text-gray-500">(Max 10 files, 10MB each)</span>
                </label>
                
                <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4 hover:border-blue-400 transition-colors cursor-pointer">
                    <input type="file" id="blotter_evidence_files" name="evidence_files[]" 
                           class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.mp4,.avi,.mov,.wav,.mp3"
                           onchange="handleFileUpload(this.files, 'blotter')">
                    
                    <div class="flex flex-col items-center">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600 font-medium">Drag & drop files here or click to browse</p>
                        <p class="text-sm text-gray-500 mt-1">Supports images, documents, videos, and audio files</p>
                        <button type="button" onclick="document.getElementById('blotter_evidence_files').click()"
                                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i> Select Files
                        </button>
                    </div>
                </div>
                
                <!-- File list -->
                <div class="mb-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">
                            Selected Files: <span id="blotterFileCount" class="text-blue-600">0</span>/10
                        </span>
                        <button type="button" onclick="clearFiles('blotter')"
                                class="text-sm text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt mr-1"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <div id="blotterFileList" class="space-y-2">
                    <!-- Files will be listed here -->
                </div>
            </div>
            
            <!-- Anonymous Option -->
            <div class="bg-gray-50 rounded-xl p-6 mb-6">
                <!-- Hidden input for unchecked state -->
                <input type="hidden" name="is_anonymous" value="0">
                <div class="flex items-center">
                    <input type="checkbox" id="blotter_is_anonymous" name="is_anonymous" value="1" 
                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == 1) ? 'checked' : ''; ?>>
                    <label for="blotter_is_anonymous" class="ml-3 block text-sm font-medium text-gray-700">
                        Submit anonymously
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-2 ml-8">
                    Your name will not be visible to the public.
                </p>
            </div>
            
            <!-- Terms and Submit -->
            <div class="border-t pt-6">
                <div class="mb-6">
                    <div class="flex items-start">
                        <input type="checkbox" id="blotter_terms" name="terms" required
                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                        <label for="blotter_terms" class="ml-3 text-sm text-gray-700">
                            I certify that the information provided is true and accurate.
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="window.history.back()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 mr-4">
                        Cancel
                    </button>
                    <button type="submit" name="submit_report" value="1"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Blotter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// PHP data passed to JavaScript
const reportTypes = {
    incident: <?php echo json_encode($categorized_types['incident']); ?>,
    complaint: <?php echo json_encode($categorized_types['complaint']); ?>,
    blotter: <?php echo json_encode($categorized_types['blotter']); ?>
};

// Comprehensive Keywords Database for Local Analysis
const COMPREHENSIVE_KEYWORDS = {
    incident: [
        'incident', 'crime', 'criminal', 'emergency', 'urgent', 'help', 'saklolo',
        'danger', 'delikado', 'life threatening', 'critical', 'responde',
        '911', 'pulis', 'police', 'tanod', 'stolen', 'ninakaw', 'nakaw', 'holdap', 'robbery',
        'missing', 'nawala', 'lost', 'snatch', 'snatching', 'carnap', 'carnapping',
        'attack', 'inatake', 'assault', 'sinaktan', 'binugbog', 'violence', 'karahasan',
        'saksak', 'baril', 'shooting', 'injured', 'nasugatan', 'dugo', 'patay', 'dead',
        'murder', 'killing', 'homicide', 'rape', 'ginahasa', 'sexual assault', 'molest',
        'kidnap', 'kidnapping', 'dinukot', 'abduction', 'accident', 'aksidente', 'banggaan',
        'car accident', 'fire', 'sunog', 'nasusunog', 'smoke', 'flood', 'baha', 'landslide',
        'bagyo', 'disaster', 'sakuna', 'earthquake', 'lindol'
    ],
    complaint: [
        'complaint', 'reklamo', 'issue', 'problem', 'problema', 'concern', 'hinaing', 'report',
        'ireport', 'noise', 'noisy', 'ingay', 'maingay', 'loud', 'videoke', 'karaoke', 'party',
        'inuman', 'disturbance', 'istorbo', 'gulo', 'garbage', 'basura', 'trash', 'kalat',
        'dirty', 'marumi', 'mabaho', 'sanitation', 'sewer', 'imburnal', 'baradong kanal',
        'animal', 'hayop', 'aso', 'pusa', 'stray', 'gala', 'askal', 'pusakal', 'kagat', 'bite',
        'rabies', 'parking', 'illegal parking', 'double parking', 'violation', 'labag', 'bawal',
        'obstruction', 'traffic', 'trapik', 'road block', 'water', 'tubig', 'walang tubig',
        'electricity', 'kuryente', 'brownout', 'power outage', 'meralco', 'road', 'kalsada',
        'daan', 'street', 'construction', 'hukay', 'sira ang daan', 'bukas na kanal'
    ],
    blotter: [
        'blotter', 'ipablotter', 'record', 'irecord', 'documentation', 'dokumento',
        'dispute', 'alitan', 'away', 'nag-away', 'argument', 'pagtatalo', 'conflict',
        'misunderstanding', 'di pagkakaunawaan', 'neighbor', 'neighbour', 'kapitbahay',
        'family', 'pamilya', 'kamag-anak', 'asawa', 'mag-asawa', 'partner', 'property',
        'ari-arian', 'lupa', 'land', 'boundary', 'hangganan', 'bakod', 'encroachment',
        'trespassing', 'debt', 'utang', 'loan', 'pautang', 'money', 'pera', 'hindi nagbayad',
        'paniningil', 'threat', 'banta', 'pananakot', 'harassment', 'pangha-harass',
        'intimidation', 'pang-iintimidate', 'verbal', 'salita', 'mura', 'panlalait',
        'insult', 'defamation', 'paninira', 'clearance', 'barangay clearance',
        'certificate', 'certification', 'rekord', 'kasulatan', 'affidavit'
    ]
};

// Emergency Keywords
const EMERGENCY_KEYWORDS = [
    'emergency', 'urgent', 'help', 'saklolo', 'tulong', '911', 'patay', 'dead', 'dying',
    'namamatay', 'biktima', 'victim', 'sunog', 'fire', 'burning', 'flames', 'smoke',
    'nasusunog', 'aksidente', 'accident', 'hospital', 'ambulance', 'nasugatan', 'injured',
    'holdap', 'robbery', 'baril', 'gun', 'shoot', 'shot', 'pumuputok', 'ginahasa', 'rape',
    'assault', 'sexual assault', 'panggagahasa', 'missing', 'nawawala', 'lost', 'kidnap',
    'abduct', 'dinukot', 'suicide', 'magpapakamatay', 'jumping', 'overdose', 'self-harm',
    'heart attack', 'atake sa puso', 'stroke', 'natumba', 'unconscious', 'life threatening',
    'delikado', 'critical', 'responde', 'dugo', 'blood', 'nasugatan', 'wounded', 'baha',
    'flood', 'landslide', 'bagyo', 'typhoon', 'lindol', 'earthquake', 'quake', 'gas leak',
    'chemical spill', 'nakuryente', 'electric shock', 'poison', 'lason', 'food poisoning',
    'intoxicated'
];

// Tab Switching
const tabs = document.querySelectorAll('.tab-button');
const forms = document.querySelectorAll('.report-form');

tabs.forEach(tab => {
    tab.addEventListener('click', function() {
        const category = this.id.replace('Tab', '').toLowerCase();
        
        // Update active tab
        tabs.forEach(t => {
            t.classList.remove('active-tab', 'border-blue-500', 'bg-blue-50');
            t.classList.add('border-gray-200');
        });
        this.classList.add('active-tab', 'border-blue-500', 'bg-blue-50');
        this.classList.remove('border-gray-200');
        
        // Show active form
        forms.forEach(form => {
            form.classList.add('hidden');
            form.classList.remove('active-form');
        });
        const activeForm = document.getElementById(category + 'Form');
        if (activeForm) {
            activeForm.classList.remove('hidden');
            activeForm.classList.add('active-form');
            
            // Clear previous AI analysis
            hideAiAnalysis(category);
        }
    });
});

// AI Analysis Functions
let analysisTimeouts = {};

function analyzeIncidentDescription(text) {
    updateCharCount('incident', text.length);
    if (text.length < 10) {
        hideAiAnalysis('incident');
        return;
    }
    
    clearTimeout(analysisTimeouts['incident']);
    analysisTimeouts['incident'] = setTimeout(() => {
        performAIAnalysis(text, 'incident');
    }, 800);
}

function analyzeComplaintDescription(text) {
    updateCharCount('complaint', text.length);
    if (text.length < 10) {
        hideAiAnalysis('complaint');
        return;
    }
    
    clearTimeout(analysisTimeouts['complaint']);
    analysisTimeouts['complaint'] = setTimeout(() => {
        performAIAnalysis(text, 'complaint');
    }, 800);
}

function analyzeBlotterDescription(text) {
    updateCharCount('blotter', text.length);
    if (text.length < 10) {
        hideAiAnalysis('blotter');
        return;
    }
    
    clearTimeout(analysisTimeouts['blotter']);
    analysisTimeouts['blotter'] = setTimeout(() => {
        performAIAnalysis(text, 'blotter');
    }, 800);
}

async function performAIAnalysis(text, category) {
    try {
        const formData = new FormData();
        formData.append('description', text);
        formData.append('category', category);
        
        const response = await fetch('../ajax/get_ai_suggestions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.suggestions && data.suggestions.length > 0) {
            displayAISuggestions(data.suggestions, category, data.analysis);
            
            // Auto-select if only one high-confidence suggestion
            if (data.suggestions.length === 1 && data.suggestions[0].confidence > 85) {
                setTimeout(() => {
                    selectSuggestedType(data.suggestions[0].id, category, data.suggestions[0].type_name);
                }, 1000);
            }
        } else if (data.fallback_suggestions && data.fallback_suggestions.length > 0) {
            displayFallbackSuggestions(data.fallback_suggestions, category);
        } else {
            showNoSuggestions(category, text);
        }
    } catch (error) {
        console.error('AI analysis error:', error);
        performLocalAnalysis(text, category);
    }
}

function displayAISuggestions(suggestions, category, analysis) {
    const analysisDiv = document.getElementById(category + 'AiAnalysis');
    const typeSection = document.getElementById(category + 'TypeSection');
    const typeSuggestions = document.getElementById(category + 'TypeSuggestions');
    
    if (!analysisDiv || !typeSection || !typeSuggestions) return;
    
    // Show analysis section
    analysisDiv.classList.remove('hidden');
    typeSection.classList.remove('hidden');
    
    // Create analysis message
    let categoryTitle = '';
    let categoryColor = '';
    
    switch(category) {
        case 'incident':
            categoryTitle = 'Incident Analysis';
            categoryColor = 'red';
            break;
        case 'complaint':
            categoryTitle = 'Complaint Analysis';
            categoryColor = 'yellow';
            break;
        case 'blotter':
            categoryTitle = 'Blotter Analysis';
            categoryColor = 'green';
            break;
    }
    
    // Display keyword analysis
    let analysisHtml = `
        <div class="bg-gradient-to-r from-${categoryColor}-50 to-${categoryColor}-100 border border-${categoryColor}-200 rounded-lg p-4 animate-fadeIn">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <i class="fas fa-robot text-${categoryColor}-500 text-lg mr-2"></i>
                    <span class="font-semibold text-gray-800">${categoryTitle}</span>
                </div>
                <span class="text-sm text-${categoryColor}-600 font-medium">
                    ${suggestions.length} classification${suggestions.length > 1 ? 's' : ''} found
                </span>
            </div>
            <p class="text-sm text-gray-600 mb-3">
                Based on your description, here are the most relevant classifications:
            </p>
    `;
    
    // Display analysis details
    if (analysis) {
        analysisHtml += `
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="text-xs">
                    <span class="text-gray-500">Keywords found:</span>
                    <span class="ml-1 font-medium">${analysis.total_keywords_found || 0}</span>
                </div>
                <div class="text-xs">
                    <span class="text-gray-500">Language:</span>
                    <span class="ml-1 px-1 bg-blue-100 text-blue-600 rounded">${analysis.detected_language || 'Mixed'}</span>
                </div>
            </div>
        `;
    }
    
    // Check for emergency (for incidents only)
    if (analysis && analysis.is_emergency && category === 'incident') {
        analysisHtml += `
            <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg emergency-alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="font-medium text-red-700">⚠️ EMERGENCY DETECTED</span>
                </div>
                <p class="text-sm text-red-600 mt-1">This appears to be urgent. Please proceed immediately.</p>
            </div>
        `;
    }
    
    analysisDiv.innerHTML = analysisHtml + '</div>';
    
    // Create type suggestions
    let suggestionsHtml = '';
    
    suggestions.forEach((suggestion, index) => {
        const isTopMatch = index === 0 && suggestion.confidence > 75;
        const jurisdictionColor = suggestion.jurisdiction === 'police' ? 'red' : 'blue';
        const severityColor = suggestion.severity_level === 'critical' ? 'red' : 
                             suggestion.severity_level === 'high' ? 'orange' : 
                             suggestion.severity_level === 'medium' ? 'yellow' : 'green';
        
        suggestionsHtml += `
            <div class="p-4 border rounded-lg cursor-pointer hover:border-blue-300 transition-all ${isTopMatch ? 'border-blue-300 bg-blue-50' : 'border-gray-200'}" 
                  onclick="selectSuggestedType(${suggestion.id}, '${category}', '${suggestion.type_name.replace(/'/g, "\\'")}')">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <div class="font-medium text-gray-800">${suggestion.type_name}</div>
                            <div class="ml-2 text-xs px-2 py-1 rounded-full bg-${jurisdictionColor}-100 text-${jurisdictionColor}-600">
                                <i class="fas ${suggestion.jurisdiction === 'police' ? 'fa-shield-alt' : 'fa-home'} mr-1"></i>
                                ${suggestion.jurisdiction}
                            </div>
                            <div class="ml-2 text-xs px-2 py-1 rounded-full bg-${severityColor}-100 text-${severityColor}-600">
                                ${suggestion.severity_level}
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mb-3">${suggestion.description || 'No description available'}</p>
                        
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-400">
                                ${suggestion.matched_keywords && suggestion.matched_keywords.length > 0 ? 
                                    `<i class="fas fa-key mr-1"></i> Keywords: ${suggestion.matched_keywords.slice(0, 3).join(', ')}${suggestion.matched_keywords.length > 3 ? '...' : ''}` : 
                                    'AI matched based on context'}
                            </div>
                            <div class="flex items-center">
                                <div class="confidence-meter mr-3">
                                    <div class="confidence-bar">
                                        <div class="confidence-fill" style="width: ${suggestion.confidence}%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">${suggestion.confidence}% match</div>
                                </div>
                                <button type="button" onclick="event.stopPropagation(); selectSuggestedType(${suggestion.id}, '${category}', '${suggestion.type_name.replace(/'/g, "\\'")}')" 
                                        class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    Select
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    typeSuggestions.innerHTML = suggestionsHtml;
    
    // Scroll to suggestions
    setTimeout(() => {
        typeSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 300);
}

function displayFallbackSuggestions(fallbacks, category) {
    const analysisDiv = document.getElementById(category + 'AiAnalysis');
    const typeSection = document.getElementById(category + 'TypeSection');
    const typeSuggestions = document.getElementById(category + 'TypeSuggestions');
    
    if (!analysisDiv || !typeSection || !typeSuggestions) return;
    
    analysisDiv.classList.remove('hidden');
    typeSection.classList.remove('hidden');
    
    let categoryColor = '';
    switch(category) {
        case 'complaint': categoryColor = 'yellow'; break;
        case 'blotter': categoryColor = 'green'; break;
        default: categoryColor = 'blue';
    }
    
    analysisDiv.innerHTML = `
        <div class="bg-gradient-to-r from-${categoryColor}-50 to-${categoryColor}-100 border border-${categoryColor}-200 rounded-lg p-4 animate-fadeIn">
            <div class="flex items-center mb-3">
                <i class="fas fa-lightbulb text-${categoryColor}-500 text-lg mr-2"></i>
                <span class="font-semibold text-gray-800">General Analysis</span>
            </div>
            <p class="text-sm text-gray-600">
                Based on general patterns, here are suggested classifications:
            </p>
        </div>
    `;
    
    let suggestionsHtml = '';
    
    fallbacks.forEach((fallback) => {
        suggestionsHtml += `
            <div class="p-4 border rounded-lg cursor-pointer hover:border-blue-300 transition-all border-gray-200" 
                  onclick="selectSuggestedTypeByName('${fallback.type_name.replace(/'/g, "\\'")}', '${category}')">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="font-medium text-gray-800 mb-2">${fallback.type_name}</div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-400">
                                <i class="fas fa-lightbulb mr-1"></i> Suggested based on general pattern
                            </div>
                            <button type="button" onclick="event.stopPropagation(); selectSuggestedTypeByName('${fallback.type_name.replace(/'/g, "\\'")}', '${category}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                Select
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    typeSuggestions.innerHTML = suggestionsHtml;
}

function showNoSuggestions(category, description) {
    const analysisDiv = document.getElementById(category + 'AiAnalysis');
    const typeSection = document.getElementById(category + 'TypeSection');
    
    if (!analysisDiv || !typeSection) return;
    
    analysisDiv.classList.remove('hidden');
    typeSection.classList.add('hidden');
    
    let categoryColor = '';
    let specificKeywords = '';
    
    switch(category) {
        case 'incident':
            categoryColor = 'red';
            specificKeywords = 'Try including specific keywords like "stolen", "missing", "attack", "fire", "accident", etc.';
            break;
        case 'complaint':
            categoryColor = 'yellow';
            specificKeywords = 'Try including specific keywords like "noise", "garbage", "animal", "water issue", "electricity problem", etc.';
            break;
        case 'blotter':
            categoryColor = 'green';
            specificKeywords = 'Try including specific keywords like "neighbor dispute", "family argument", "debt", "threat", "property boundary", etc.';
            break;
    }
    
    analysisDiv.innerHTML = `
        <div class="bg-gradient-to-r from-${categoryColor}-50 to-${categoryColor}-100 border border-${categoryColor}-200 rounded-lg p-4 animate-fadeIn">
            <div class="flex items-center mb-3">
                <i class="fas fa-info-circle text-${categoryColor}-500 text-lg mr-2"></i>
                <span class="font-semibold text-gray-800">Need More Details</span>
            </div>
            <p class="text-sm text-gray-600 mb-2">
                Please provide more specific details about what happened so AI can suggest the best classification.
            </p>
            <div class="mt-2 text-xs text-gray-500">
                <i class="fas fa-lightbulb mr-1"></i>
                ${specificKeywords}
            </div>
        </div>
    `;
}

function performLocalAnalysis(text, category) {
    const textLower = text.toLowerCase();
    const types = reportTypes[category];
    const suggestions = [];
    
    // Check for emergency keywords
    let isEmergency = false;
    EMERGENCY_KEYWORDS.forEach(keyword => {
        if (textLower.includes(keyword.toLowerCase())) {
            isEmergency = true;
        }
    });
    
    // Get comprehensive keywords for this category
    const categoryKeywords = COMPREHENSIVE_KEYWORDS[category] || [];
    
    types.forEach(type => {
        let score = 0;
        const matchedKeywords = [];
        
        // Check type name
        if (type.type_name) {
            const typeNameLower = type.type_name.toLowerCase();
            const typeNameWords = typeNameLower.split(/\/|\(|\)|\s+/);
            
            typeNameWords.forEach(word => {
                word = word.trim();
                if (word.length > 3 && textLower.includes(word)) {
                    score += 15;
                    matchedKeywords.push(word);
                }
            });
        }
        
        // Check keywords from database
        if (type.all_keywords) {
            type.all_keywords.forEach(keyword => {
                if (keyword && keyword.trim() && textLower.includes(keyword.toLowerCase().trim())) {
                    score += 10;
                    matchedKeywords.push(keyword.trim());
                }
            });
        }
        
        // Check comprehensive category keywords
        categoryKeywords.forEach(keyword => {
            if (keyword && textLower.includes(keyword.toLowerCase())) {
                // If this keyword is related to the report type name, give higher score
                if (type.type_name && type.type_name.toLowerCase().includes(keyword.toLowerCase())) {
                    score += 12;
                } else {
                    score += 5;
                }
                if (!matchedKeywords.includes(keyword)) {
                    matchedKeywords.push(keyword);
                }
            }
        });
        
        // Emergency boost
        if (isEmergency && category === 'incident') {
            score += 20;
        }
        
        if (score > 0) {
            const confidence = Math.min(score, 90);
            suggestions.push({
                ...type,
                score: score,
                confidence: confidence,
                matched_keywords: matchedKeywords.slice(0, 5),
                jurisdiction: type.jurisdiction || 'barangay',
                severity_level: type.severity_level || 'medium'
            });
        }
    });
    
    // Sort by score
    suggestions.sort((a, b) => b.score - a.score);
    
    if (suggestions.length > 0) {
        const localSuggestions = suggestions.slice(0, 3).map(s => ({
            ...s,
            description: s.description || 'No description available'
        }));
        displayAISuggestions(localSuggestions, category, { 
            is_emergency: isEmergency,
            total_keywords_found: suggestions.reduce((sum, s) => sum + s.matched_keywords.length, 0),
            detected_language: detectLanguage(text)
        });
    } else {
        showNoSuggestions(category, text);
    }
}

function detectLanguage(text) {
    const tagalogWords = ['ang', 'ng', 'sa', 'na', 'ako', 'ko', 'mo', 'siya', 'namin', 'ninyo', 'sila',
                         'ito', 'iyan', 'iyon', 'dito', 'doon', 'kung', 'pero', 'at', 'o', 'tayo',
                         'kayo', 'sila', 'akin', 'amin', 'inyo', 'kanila', 'bakit', 'paano', 'kailan',
                         'saan', 'sino', 'ano', 'alin', 'gaano', 'ilan', 'magkano'];
    
    const englishWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with',
                         'by', 'from', 'of', 'about', 'into', 'through', 'during', 'before', 'after',
                         'above', 'below', 'between', 'under', 'over', 'again', 'further', 'then', 'once'];
    
    let tagalogCount = 0;
    let englishCount = 0;
    
    const words = text.toLowerCase().split(/\s+/);
    
    words.forEach(word => {
        if (tagalogWords.includes(word)) {
            tagalogCount++;
        }
        if (englishWords.includes(word)) {
            englishCount++;
        }
    });
    
    if (tagalogCount > englishCount) {
        return 'tagalog';
    } else if (englishCount > tagalogCount) {
        return 'english';
    } else {
        return 'mixed';
    }
}

function selectSuggestedType(typeId, category, typeName) {
    const typeInput = document.getElementById(category + 'ReportTypeId');
    const analysisDiv = document.getElementById(category + 'AiAnalysis');
    
    if (typeInput) {
        typeInput.value = typeId;
        
        // Update analysis to show selected
        if (analysisDiv) {
            analysisDiv.innerHTML = `
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4 animate-fadeIn">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-lg mr-2"></i>
                            <span class="font-semibold text-gray-800">Classification Selected</span>
                        </div>
                        <button type="button" onclick="showAiSuggestionsAgain('${category}')" 
                                class="text-xs text-blue-600 hover:text-blue-800">
                            <i class="fas fa-redo mr-1"></i> Change
                        </button>
                    </div>
                    <p class="text-sm text-gray-700">
                        <strong>${typeName}</strong> has been selected as your report classification.
                    </p>
                </div>
            `;
        }
        
        // Auto-fill title if empty
        const titleInput = document.querySelector(`#${category}Form input[name="title"]`);
        if (titleInput && !titleInput.value.trim()) {
            const titleBase = typeName.split('/')[0].trim();
            titleInput.value = `${titleBase} Report`;
            titleInput.focus();
        }
        
        showToast(`✓ Selected: "${typeName}"`, 'success');
    }
}

function selectSuggestedTypeByName(typeName, category) {
    // Find the report type with matching name
    const reportTypes = window.reportTypes[category];
    if (!reportTypes) return;
    
    const matchedType = reportTypes.find(type => type.type_name === typeName);
    if (matchedType) {
        selectSuggestedType(matchedType.id, category, matchedType.type_name);
    } else {
        // Try to find similar type
        const similarType = reportTypes.find(type => 
            type.type_name.toLowerCase().includes(typeName.toLowerCase()) ||
            typeName.toLowerCase().includes(type.type_name.toLowerCase())
        );
        if (similarType) {
            selectSuggestedType(similarType.id, category, similarType.type_name);
        }
    }
}

function showAiSuggestionsAgain(category) {
    const textarea = document.getElementById(category + 'Description');
    if (textarea && textarea.value.length >= 10) {
        if (category === 'incident') {
            analyzeIncidentDescription(textarea.value);
        } else if (category === 'complaint') {
            analyzeComplaintDescription(textarea.value);
        } else if (category === 'blotter') {
            analyzeBlotterDescription(textarea.value);
        }
    }
}

function hideAiAnalysis(category) {
    const analysisDiv = document.getElementById(category + 'AiAnalysis');
    const typeSection = document.getElementById(category + 'TypeSection');
    
    if (analysisDiv) {
        analysisDiv.classList.add('hidden');
    }
    if (typeSection) {
        typeSection.classList.add('hidden');
    }
}

function updateCharCount(category, count) {
    const charCount = document.getElementById(category + 'CharCount');
    if (charCount) {
        charCount.textContent = count;
    }
}

// File Upload Functions - FIXED VERSION
const uploadedFiles = {
    incident: [],
    complaint: [],
    blotter: []
};

function handleFileUpload(files, formType) {
    const fileList = document.getElementById(formType + 'FileList');
    const fileCount = document.getElementById(formType + 'FileCount');
    const maxFiles = 10;
    
    // Convert FileList to array
    const newFiles = Array.from(files);
    
    // Check if adding new files would exceed limit
    if (uploadedFiles[formType].length + newFiles.length > maxFiles) {
        showToast(`Maximum ${maxFiles} files allowed. You already have ${uploadedFiles[formType].length} files.`, 'error');
        return;
    }
    
    newFiles.forEach(file => {
        // Validate file type and size
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'video/mp4', 'video/avi', 'video/quicktime', 'audio/wav', 'audio/mp3'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!allowedTypes.includes(file.type) && !file.type.startsWith('image/')) {
            showToast(`File ${file.name} is not a supported file type.`, 'error');
            return;
        }
        
        if (file.size > maxSize) {
            showToast(`File ${file.name} is too large. Maximum size is 10MB.`, 'error');
            return;
        }
        
        // Add to uploaded files
        uploadedFiles[formType].push(file);
        
        // Create file preview
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item flex items-center justify-between p-3 bg-gray-50 rounded-lg border animate-fadeIn';
        fileItem.dataset.fileName = file.name;
        fileItem.dataset.fileSize = file.size;
        fileItem.innerHTML = `
            <div class="flex items-center flex-1">
                <div class="flex-shrink-0">
                    <i class="fas ${getFileIcon(file.type)} text-blue-500 mr-3 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${file.name}</p>
                    <div class="flex items-center text-xs text-gray-500 mt-1">
                        <span>${formatFileSize(file.size)}</span>
                        <span class="mx-2">•</span>
                        <span>${getFileType(file.type)}</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="removeFile('${formType}', this)" 
                    class="ml-3 p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full transition-colors">
                <i class="fas fa-times"></i>
            </button>
        `;
        fileList.appendChild(fileItem);
    });
    
    // Update count
    fileCount.textContent = uploadedFiles[formType].length;
    updateFileInput(formType);
    
    if (newFiles.length > 0) {
        showToast(`Added ${newFiles.length} file(s)`, 'success');
    }
}

function removeFile(formType, buttonElement) {
    const fileItem = buttonElement.closest('.file-item');
    const fileName = fileItem.dataset.fileName;
    const fileSize = parseInt(fileItem.dataset.fileSize);
    
    // Remove from uploadedFiles array
    uploadedFiles[formType] = uploadedFiles[formType].filter(file => 
        !(file.name === fileName && file.size === fileSize)
    );
    
    // Remove from DOM with animation
    fileItem.classList.add('animate-fadeOut');
    setTimeout(() => {
        fileItem.remove();
    }, 300);
    
    // Update count
    document.getElementById(formType + 'FileCount').textContent = uploadedFiles[formType].length;
    updateFileInput(formType);
    
    showToast('File removed', 'info');
}

function clearFiles(formType) {
    if (uploadedFiles[formType].length === 0) return;
    
    if (confirm(`Are you sure you want to remove all ${uploadedFiles[formType].length} files?`)) {
        // Clear the array
        uploadedFiles[formType] = [];
        
        // Remove all file items from DOM with animation
        const fileList = document.getElementById(formType + 'FileList');
        const fileItems = fileList.querySelectorAll('.file-item');
        
        fileItems.forEach(item => {
            item.classList.add('animate-fadeOut');
        });
        
        setTimeout(() => {
            fileList.innerHTML = '';
        }, 300);
        
        // Update count
        document.getElementById(formType + 'FileCount').textContent = '0';
        updateFileInput(formType);
        
        showToast('All files cleared', 'info');
    }
}

function updateFileInput(formType) {
    const fileInput = document.getElementById(formType + '_evidence_files');
    
    // Create a new DataTransfer object
    const dataTransfer = new DataTransfer();
    
    // Add all files from the uploadedFiles array
    uploadedFiles[formType].forEach(file => {
        dataTransfer.items.add(file);
    });
    
    // Replace the files in the input
    fileInput.files = dataTransfer.files;
    
    // Force a change event to ensure the input is updated
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
}

// Helper functions
function getFileIcon(mimeType) {
    if (mimeType.startsWith('image/')) return 'fa-image';
    if (mimeType.startsWith('video/')) return 'fa-video';
    if (mimeType === 'application/pdf') return 'fa-file-pdf';
    if (mimeType.startsWith('audio/')) return 'fa-file-audio';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'fa-file-word';
    return 'fa-file';
}

function getFileType(mimeType) {
    if (mimeType.startsWith('image/')) return 'Image';
    if (mimeType.startsWith('video/')) return 'Video';
    if (mimeType === 'application/pdf') return 'PDF';
    if (mimeType.startsWith('audio/')) return 'Audio';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'Document';
    return 'File';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Drag and drop - FIXED VERSION
document.querySelectorAll('.drop-zone').forEach(dropZone => {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('border-blue-400', 'bg-blue-50');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        
        const files = e.dataTransfer.files;
        const formType = dropZone.closest('.report-form').id.replace('Form', '').toLowerCase();
        
        if (files.length > 0) {
            handleFileUpload(files, formType);
        }
    });
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        // Validate report type is selected
        const reportTypeId = this.querySelector('input[name="report_type_id"]');
        if (!reportTypeId || !reportTypeId.value) {
            e.preventDefault();
            showToast('Please select a report classification from AI suggestions.', 'error');
            
            // Scroll to AI analysis section
            const formId = this.id.replace('ReportForm', '').toLowerCase();
            const analysisDiv = document.getElementById(formId + 'AiAnalysis');
            if (analysisDiv) {
                analysisDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            submitBtn.disabled = true;
            
            // Re-enable after 10 seconds if still stuck
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    showToast('Submission taking longer than expected. Please try again.', 'warning');
                }
            }, 10000);
        }
    });
});

// Toast notification
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transform transition-all duration-300 animate-fadeIn ${type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : type === 'warning' ? 'bg-yellow-600' : 'bg-blue-600'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('animate-fadeOut');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize character counters
    document.querySelectorAll('textarea[name="description"]').forEach(textarea => {
        const formType = textarea.id.replace('Description', '').toLowerCase();
        const charCount = document.getElementById(formType + 'CharCount');
        if (charCount) {
            charCount.textContent = textarea.value.length;
            textarea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
    });
});
</script>

<style>
.tab-button.active-tab {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}

.tab-button.active-tab .text-gray-800 {
    color: white;
}

.tab-button.active-tab .text-gray-600 {
    color: #dbeafe;
}

.file-item {
    transition: all 0.2s ease;
}

.file-item:hover {
    background-color: #f9fafb;
    transform: translateX(2px);
}

.drop-zone:hover {
    background-color: #f9fafb;
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-in-out;
}

.animate-fadeOut {
    animation: fadeOut 0.3s ease-in-out;
    opacity: 0;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes emergencyPulse {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.animate-pulse-once {
    animation: pulse 0.5s ease;
}

.emergency-alert {
    animation: emergencyPulse 2s infinite;
}

.confidence-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
    width: 100px;
}

.confidence-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #3b82f6);
    border-radius: 3px;
    transition: width 0.5s ease;
}

.confidence-meter {
    display: inline-block;
}

.file-preview img {
    max-width: 100%;
    max-height: 160px;
    object-fit: contain;
}
</style>