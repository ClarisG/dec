<?php
// tanod/modules/report_vetting.php

// Start session and include configurations
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Check if user is a Tanod
if ($_SESSION['role'] !== 'tanod') {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'citizen':
            header('Location: ' . BASE_URL . 'citizen_dashboard.php');
            exit();
        case 'secretary':
            header('Location: ' . BASE_URL . 'sec/secretary_dashboard.php');
            exit();
        case 'captain':
            header('Location: ' . BASE_URL . 'captain/dashboard.php');
            exit();
        default:
            header('Location: ' . BASE_URL . 'login.php');
            exit();
    }
}

// Create mysqli connection from PDO config if $conn doesn't exist
if (!isset($conn)) {
    $host = 'localhost';
    $dbname = 'leir_db';
    $username = 'root';
    $password = '';
    $port = '3307';
    
    $conn = new mysqli($host, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}

// Now we can safely use $conn
$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Tanod';
$current_date = date('Y-m-d H:i:s');

// Initialize error variable
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_report'])) {
        $report_id = intval($_POST['report_id']);
        
        $sql = "UPDATE reports SET assigned_tanod = ?, status = 'assigned', needs_verification = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ii", $tanod_id, $report_id);
            if ($stmt->execute()) {
                // Log the assignment
                logActivity($tanod_id, 'assigned_report', "Assigned report #$report_id for vetting", $conn);
                
                $_SESSION['success'] = "Report assigned successfully for vetting.";
            } else {
                $_SESSION['error'] = "Error assigning report: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
    }
    
    if (isset($_POST['submit_vetting'])) {
        $report_id = intval($_POST['report_id']);
        $location_verified = $_POST['location_verified'] ?? 'No';
        $facts_verified = $_POST['facts_verified'] ?? 'Unconfirmed';
        $verification_notes = $_POST['verification_notes'] ?? '';
        $recommendation = $_POST['recommendation'] ?? 'Needs More Info';
        
        // Sanitize inputs
        $location_verified = sanitize($location_verified);
        $facts_verified = sanitize($facts_verified);
        $verification_notes = sanitize($verification_notes);
        $recommendation = sanitize($recommendation);
        
        // Check if vetting already exists
        $check_sql = "SELECT vetting_id FROM report_vetting WHERE report_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt) {
            $check_stmt->bind_param("i", $report_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_stmt->close();
            
            if ($check_result->num_rows > 0) {
                // Update existing vetting
                $sql = "UPDATE report_vetting SET 
                        location_verified = ?, 
                        facts_verified = ?, 
                        verification_notes = ?, 
                        recommendation = ?, 
                        status = 'Completed', 
                        updated_at = ? 
                        WHERE report_id = ?";
            } else {
                // Insert new vetting
                $sql = "INSERT INTO report_vetting 
                        (report_id, tanod_id, location_verified, facts_verified, verification_notes, recommendation, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Completed')";
            }
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                if ($check_result->num_rows > 0) {
                    // Update
                    $stmt->bind_param("sssssi", $location_verified, $facts_verified, $verification_notes, $recommendation, $current_date, $report_id);
                } else {
                    // Insert
                    $stmt->bind_param("iissss", $report_id, $tanod_id, $location_verified, $facts_verified, $verification_notes, $recommendation);
                }
                
                if ($stmt->execute()) {
                    // Update report status based on recommendation
                    $report_status = 'assigned';
                    if ($recommendation == 'Approved') {
                        $report_status = 'investigating';
                    } elseif ($recommendation == 'Rejected') {
                        $report_status = 'closed';
                    }
                    
                    $update_sql = "UPDATE reports SET status = ?, verification_notes = ?, verification_date = NOW(), verified_by = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    
                    if ($update_stmt) {
                        $update_stmt->bind_param("ssii", $report_status, $verification_notes, $tanod_id, $report_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    // Log activity
                    logActivity($tanod_id, 'submitted_vetting', "Submitted vetting for report #$report_id with recommendation: $recommendation", $conn);
                    
                    $_SESSION['success'] = "Vetting report submitted successfully.";
                    
                    // Redirect to clear POST data
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                    exit();
                } else {
                    $_SESSION['error'] = "Error submitting vetting report: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get assigned reports for this tanod (reports that need verification and are assigned to this tanod)
$assigned_reports = [];
$assigned_sql = "
    SELECT r.*, 
           CONCAT(u.first_name, ' ', u.last_name) as reporter_name,
           v.vetting_id, v.verification_date, v.recommendation as vetting_recommendation
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN report_vetting v ON r.id = v.report_id
    WHERE (r.assigned_tanod = ? OR r.assigned_to = ?)
    AND r.status IN ('pending_field_verification', 'assigned', 'investigating')
    ORDER BY r.created_at DESC
";

$assigned_stmt = $conn->prepare($assigned_sql);
if ($assigned_stmt) {
    $assigned_stmt->bind_param("ii", $tanod_id, $tanod_id);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    
    while ($row = $assigned_result->fetch_assoc()) {
        $assigned_reports[] = $row;
    }
    $assigned_stmt->close();
}

// Get pending reports available for assignment (reports needing verification, not assigned yet)
$pending_reports = [];
$pending_sql = "
    SELECT r.*, 
           CONCAT(u.first_name, ' ', u.last_name) as reporter_name 
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'pending_field_verification'
    AND r.needs_verification = 1
    AND (r.assigned_tanod IS NULL OR r.assigned_tanod = 0)
    ORDER BY r.created_at DESC
    LIMIT 10
";

$pending_stmt = $conn->prepare($pending_sql);
if ($pending_stmt) {
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    
    while ($row = $pending_result->fetch_assoc()) {
        $pending_reports[] = $row;
    }
    $pending_stmt->close();
}

// Get completed vettings
$completed_vettings = [];
$completed_sql = "
    SELECT v.*, r.title as report_title, r.location,
           CONCAT(u.first_name, ' ', u.last_name) as reporter_name
    FROM report_vetting v
    JOIN reports r ON v.report_id = r.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE v.tanod_id = ?
    AND v.status = 'Completed'
    ORDER BY v.verification_date DESC
    LIMIT 10
";

$completed_stmt = $conn->prepare($completed_sql);
if ($completed_stmt) {
    $completed_stmt->bind_param("i", $tanod_id);
    $completed_stmt->execute();
    $completed_result = $completed_stmt->get_result();
    
    while ($row = $completed_result->fetch_assoc()) {
        $completed_vettings[] = $row;
    }
    $completed_stmt->close();
}

// Get specific report details if requested
$report_details = null;
if (isset($_GET['view_report'])) {
    $report_id = intval($_GET['view_report']);
    $report_sql = "
        SELECT r.*, 
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name, 
               u.contact_number,
               v.*
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN report_vetting v ON r.id = v.report_id
        WHERE r.id = ?
    ";
    
    $report_stmt = $conn->prepare($report_sql);
    if ($report_stmt) {
        $report_stmt->bind_param("i", $report_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        
        if ($report_result->num_rows > 0) {
            $report_details = $report_result->fetch_assoc();
        }
        $report_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Vetting - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .badge {
            @apply px-2 py-1 text-xs font-semibold rounded-full;
        }
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-pending_field_verification { @apply bg-orange-100 text-orange-800; }
        .status-assigned { @apply bg-blue-100 text-blue-800; }
        .status-investigating { @apply bg-purple-100 text-purple-800; }
        .status-completed { @apply bg-green-100 text-green-800; }
        .status-approved { @apply bg-emerald-100 text-emerald-800; }
        .status-rejected { @apply bg-red-100 text-red-800; }
        .status-needs-info { @apply bg-orange-100 text-orange-800; }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .grid-cols-1 {
                grid-template-columns: 1fr;
            }
            .lg\\:col-span-2 {
                grid-column: span 1;
            }
            .lg\\:col-span-1 {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-800 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="../tanod_dashboard.php" class="flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    <span>Back to Dashboard</span>
                </a>
                <h1 class="text-xl font-bold">Report Vetting System</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm">Logged in as: <strong><?php echo htmlspecialchars($tanod_name); ?></strong></span>
                <a href="../../logout.php" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 fade-in">
                <div class="flex justify-between items-center">
                    <span><?php echo $_SESSION['success']; ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 fade-in">
                <div class="flex justify-between items-center">
                    <span><?php echo $_SESSION['error']; ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Pending Reports -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Pending Reports for Assignment -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-700">Pending Reports for Verification</h2>
                        <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">
                            <?php echo count($pending_reports); ?> pending
                        </span>
                    </div>
                    
                    <?php if (!empty($pending_reports)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporter</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pending_reports as $report): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3">
                                                <span class="text-sm font-medium text-gray-900">#<?php echo $report['id']; ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($report['title'] ?? 'Untitled Report'); ?></div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($report['created_at'] ?? $current_date)); ?>
                                                    <?php if (!empty($report['location'])): ?>
                                                        • <?php echo htmlspecialchars(substr($report['location'], 0, 30)); ?>...
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($report['reporter_name'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <button type="submit" name="assign_report" 
                                                            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                                        <i class="fas fa-user-check mr-1"></i> Assign
                                                    </button>
                                                </form>
                                                <a href="?view_report=<?php echo $report['id']; ?>" 
                                                   class="ml-2 text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-clipboard-check text-4xl mb-4"></i>
                            <p>No pending reports for verification</p>
                            <p class="text-sm mt-2">All reports have been assigned or verified</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Reports -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-700">My Assigned Reports</h2>
                        <span class="bg-yellow-100 text-yellow-800 text-sm font-semibold px-3 py-1 rounded-full">
                            <?php echo count($assigned_reports); ?> assigned
                        </span>
                    </div>
                    
                    <?php if (!empty($assigned_reports)): ?>
                        <div class="space-y-4">
                            <?php foreach ($assigned_reports as $report): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <span class="text-sm font-medium text-gray-900">#<?php echo $report['id']; ?></span>
                                                <?php 
                                                $status = $report['status'] ?? 'pending';
                                                $status_class = 'status-' . strtolower($status);
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                </span>
                                                <?php if (!empty($report['vetting_recommendation'])): ?>
                                                    <?php 
                                                    $rec = $report['vetting_recommendation'];
                                                    $rec_class = 'status-' . strtolower(str_replace(' ', '-', $rec));
                                                    ?>
                                                    <span class="badge <?php echo $rec_class; ?>">
                                                        <?php echo $rec; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($report['title'] ?? 'Untitled Report'); ?></h3>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($report['reporter_name'] ?? 'Unknown'); ?>
                                                <?php if (!empty($report['location'])): ?>
                                                    • <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($report['location']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($report['verification_date'])): ?>
                                                <p class="text-xs text-gray-500 mt-2">
                                                    Last verified: <?php echo date('M d, Y', strtotime($report['verification_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="?view_report=<?php echo $report['id']; ?>" 
                                               class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                                <i class="fas fa-edit mr-1"></i> Review
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p>No reports assigned to you</p>
                            <p class="text-sm mt-2">Assign pending reports to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Report Details / Vetting Form -->
            <div class="lg:col-span-1">
                <?php if ($report_details): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-700">Report Details</h2>
                            <a href="?" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        
                        <!-- Report Information -->
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Report Title</label>
                                <p class="mt-1 text-gray-900 p-2 bg-gray-50 rounded"><?php echo htmlspecialchars($report_details['title'] ?? 'No title'); ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Reporter</label>
                                <p class="mt-1 text-gray-900"><?php echo htmlspecialchars($report_details['reporter_name'] ?? 'Unknown'); ?></p>
                                <?php if (!empty($report_details['contact_number'])): ?>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($report_details['contact_number']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Incident Location</label>
                                <p class="mt-1 text-gray-900">
                                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>
                                    <?php echo htmlspecialchars($report_details['location'] ?? 'Location not specified'); ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Incident Details</label>
                                <div class="mt-1 p-3 bg-gray-50 rounded text-gray-700 max-h-48 overflow-y-auto">
                                    <?php 
                                    $details = $report_details['description'] ?? 'No details provided';
                                    echo nl2br(htmlspecialchars($details)); 
                                    ?>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Report Date</label>
                                <p class="mt-1 text-gray-900">
                                    <i class="far fa-calendar mr-1"></i>
                                    <?php echo date('F d, Y h:i A', strtotime($report_details['created_at'] ?? $current_date)); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Vetting Form -->
                        <form method="POST" id="vettingForm" class="space-y-4">
                            <input type="hidden" name="report_id" value="<?php echo $report_details['id']; ?>">
                            
                            <h3 class="text-lg font-semibold text-gray-700 border-t pt-4">Field Verification</h3>
                            
                            <!-- Location Verification -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-map-pin mr-1"></i>Location Verified?
                                </label>
                                <div class="space-y-2">
                                    <?php 
                                    $location_options = [
                                        'Yes' => ['icon' => 'fa-check-circle', 'color' => 'text-green-500'],
                                        'Partial' => ['icon' => 'fa-exclamation-circle', 'color' => 'text-yellow-500'],
                                        'No' => ['icon' => 'fa-times-circle', 'color' => 'text-red-500']
                                    ];
                                    foreach ($location_options as $value => $info): 
                                        $checked = ($report_details['location_verified'] ?? '') == $value ? 'checked' : '';
                                    ?>
                                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                            <input type="radio" name="location_verified" value="<?php echo $value; ?>" 
                                                   class="form-radio h-4 w-4 text-blue-600" 
                                                   <?php echo $checked; ?>
                                                   required>
                                            <i class="<?php echo $info['icon']; ?> <?php echo $info['color']; ?> ml-2 mr-2"></i>
                                            <span class="text-sm text-gray-700"><?php echo $value; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Facts Verification -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-clipboard-check mr-1"></i>Facts Verified?
                                </label>
                                <div class="space-y-2">
                                    <?php 
                                    $facts_options = [
                                        'Confirmed' => ['icon' => 'fa-check-circle', 'color' => 'text-green-500'],
                                        'Partially Confirmed' => ['icon' => 'fa-exclamation-circle', 'color' => 'text-yellow-500'],
                                        'Unconfirmed' => ['icon' => 'fa-times-circle', 'color' => 'text-red-500']
                                    ];
                                    foreach ($facts_options as $value => $info): 
                                        $checked = ($report_details['facts_verified'] ?? '') == $value ? 'checked' : '';
                                    ?>
                                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                            <input type="radio" name="facts_verified" value="<?php echo $value; ?>" 
                                                   class="form-radio h-4 w-4 text-blue-600"
                                                   <?php echo $checked; ?>
                                                   required>
                                            <i class="<?php echo $info['icon']; ?> <?php echo $info['color']; ?> ml-2 mr-2"></i>
                                            <span class="text-sm text-gray-700"><?php echo $value; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                            </div>
                            
                            <!-- Verification Notes -->
                            <div>
                                <label for="verification_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-notes-medical mr-1"></i>Verification Notes
                                </label>
                                <textarea id="verification_notes" name="verification_notes" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                          placeholder="Enter your field verification notes, observations, and findings..."
                                          required><?php echo htmlspecialchars($report_details['verification_notes'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">Describe what you found during verification</p>
                            </div>
                            
                            <!-- Recommendation -->
                            <div>
                                <label for="recommendation" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-file-signature mr-1"></i>Recommendation
                                </label>
                                <select id="recommendation" name="recommendation" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required>
                                    <option value="">Select recommendation</option>
                                    <option value="Approved" <?php echo ($report_details['recommendation'] ?? '') == 'Approved' ? 'selected' : ''; ?>>
                                        ✓ Approve Report (Verified and Accurate)
                                    </option>
                                    <option value="Needs More Info" <?php echo ($report_details['recommendation'] ?? '') == 'Needs More Info' ? 'selected' : ''; ?>>
                                        ⚠ Needs More Information
                                    </option>
                                    <option value="Rejected" <?php echo ($report_details['recommendation'] ?? '') == 'Rejected' ? 'selected' : ''; ?>>
                                        ✗ Reject Report (Inaccurate or False)
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-2">
                                <button type="submit" name="submit_vetting" 
                                        class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                    <i class="fas fa-paper-plane mr-2"></i>Submit Vetting Report
                                </button>
                                <p class="text-xs text-gray-500 mt-2 text-center">
                                    This will update the report status based on your recommendation
                                </p>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Summary Stats -->
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                        <h2 class="text-xl font-semibold text-gray-700 mb-6">Vetting Summary</h2>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <div>
                                    <p class="text-sm text-gray-600">Pending Assignment</p>
                                    <p class="text-2xl font-bold text-blue-600"><?php echo count($pending_reports); ?></p>
                                </div>
                                <i class="fas fa-clock text-blue-500 text-2xl"></i>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                                <div>
                                    <p class="text-sm text-gray-600">Assigned to Me</p>
                                    <p class="text-2xl font-bold text-yellow-600"><?php echo count($assigned_reports); ?></p>
                                </div>
                                <i class="fas fa-user-check text-yellow-500 text-2xl"></i>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <div>
                                    <p class="text-sm text-gray-600">Completed Vettings</p>
                                    <p class="text-2xl font-bold text-green-600"><?php echo count($completed_vettings); ?></p>
                                </div>
                                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <a href="../tanod_dashboard.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-tachometer-alt mr-3 text-gray-600"></i>
                                    <span>Return to Dashboard</span>
                                </a>
                                <a href="?refresh=1" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-sync-alt mr-3 text-gray-600"></i>
                                    <span>Refresh List</span>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Recent Vettings -->
                        <?php if (!empty($completed_vettings)): ?>
                            <div class="mt-8">
                                <h3 class="text-lg font-semibold text-gray-700 mb-4">Recent Vettings</h3>
                                <div class="space-y-3">
                                    <?php foreach ($completed_vettings as $vetting): ?>
                                        <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($vetting['report_title'] ?? 'Report'); ?></p>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        <i class="far fa-calendar mr-1"></i>
                                                        <?php echo date('M d, Y', strtotime($vetting['verification_date'] ?? $current_date)); ?>
                                                    </p>
                                                </div>
                                                <?php 
                                                $rec = $vetting['recommendation'] ?? 'Needs More Info';
                                                $rec_class = 'status-' . strtolower(str_replace(' ', '-', $rec));
                                                ?>
                                                <span class="badge <?php echo $rec_class; ?> text-xs">
                                                    <?php echo $rec; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript for enhanced functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('vettingForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const recommendation = document.getElementById('recommendation');
                    const notes = document.getElementById('verification_notes');
                    const locationVerified = document.querySelector('input[name="location_verified"]:checked');
                    const factsVerified = document.querySelector('input[name="facts_verified"]:checked');
                    
                    // Validation
                    let errors = [];
                    
                    if (!locationVerified) {
                        errors.push('Please select location verification status');
                    }
                    
                    if (!factsVerified) {
                        errors.push('Please select facts verification status');
                    }
                    
                    if (!notes || !notes.value.trim()) {
                        errors.push('Please provide verification notes');
                    }
                    
                    if (!recommendation || !recommendation.value) {
                        errors.push('Please select a recommendation');
                    }
                    
                    if (errors.length > 0) {
                        e.preventDefault();
                        alert('Please fix the following errors:\n\n' + errors.join('\n'));
                        return;
                    }
                    
                    // Confirm submission
                    const confirmation = confirm(
                        'Are you sure you want to submit this vetting report?\n\n' +
                        'This action will:\n' +
                        '1. Update the report status\n' +
                        '2. Record your verification findings\n' +
                        '3. Cannot be undone\n\n' +
                        'Click OK to proceed.'
                    );
                    
                    if (!confirmation) {
                        e.preventDefault();
                    }
                });
            }
            
            // Auto-expand textarea
            const textarea = document.getElementById('verification_notes');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                // Trigger initial resize
                setTimeout(() => {
                    textarea.style.height = 'auto';
                    textarea.style.height = (textarea.scrollHeight) + 'px';
                }, 100);
            }
            
            // Add visual feedback for radio buttons
            const radioButtons = document.querySelectorAll('input[type="radio"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Remove all highlights
                    document.querySelectorAll('label').forEach(label => {
                        label.classList.remove('bg-blue-50', 'border', 'border-blue-200');
                    });
                    
                    // Highlight selected option's label
                    if (this.checked) {
                        const label = this.closest('label');
                        if (label) {
                            label.classList.add('bg-blue-50', 'border', 'border-blue-200');
                        }
                    }
                });
                
                // Initialize highlights
                if (radio.checked) {
                    const label = radio.closest('label');
                    if (label) {
                        label.classList.add('bg-blue-50', 'border', 'border-blue-200');
                    }
                }
            });
            
            // Refresh page when clicking refresh button
            const refreshBtn = document.querySelector('a[href*="refresh=1"]');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.reload();
                });
            }
            
            // Add loading state to form submission
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>