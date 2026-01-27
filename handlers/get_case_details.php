<?php
// handlers/get_case_details.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$case_id = $_GET['id'] ?? null;

if (!$case_id) {
    header('HTTP/1.1 400 Bad Request');
    echo "Case ID is required";
    exit();
}

try {
    // Database connection
    $dsn = "mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4";
    $conn = new PDO($dsn, 'u514031374_leir', 'leirP@55w0rd');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get case details with user information
    // FIXED: Changed 'u.phone' to 'u.mobile_number' based on your error
    $query = "SELECT r.*, 
              u.first_name, u.last_name, 
              u.permanent_address as address,
              u.email, 
              u.mobile_number as phone,  // Changed from u.phone
              u.barangay as user_barangay,
              (SELECT COUNT(*) FROM report_attachments ra WHERE ra.report_id = r.id) as attachment_count
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        echo "<div class='text-center py-8 text-red-600'>Case not found.</div>";
        exit();
    }
    
    // Get attachments
    $attachments_query = "SELECT * FROM report_attachments WHERE report_id = :id";
    $attachments_stmt = $conn->prepare($attachments_query);
    $attachments_stmt->execute([':id' => $case_id]);
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned officer if any
    $officer = null;
    if ($case['assigned_officer_id']) {
        $officer_query = "SELECT first_name, last_name, role FROM users WHERE id = :id";
        $officer_stmt = $conn->prepare($officer_query);
        $officer_stmt->execute([':id' => $case['assigned_officer_id']]);
        $officer = $officer_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    echo "<div class='text-center py-8 text-red-600'>Error loading case details: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit();
}
?>

<!-- HTML for displaying case details -->
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-xl border">
            <h4 class="font-bold text-gray-800 mb-4">Case Information</h4>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Case ID:</span>
                    <span class="font-medium">#<?php echo $case['id']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Blotter Number:</span>
                    <span class="font-medium"><?php echo $case['blotter_number'] ?: 'Not assigned'; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-medium"><?php echo ucwords(str_replace('_', ' ', $case['status'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Category:</span>
                    <span class="font-medium"><?php echo $case['category']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Date Filed:</span>
                    <span class="font-medium"><?php echo date('F d, Y h:i A', strtotime($case['created_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl border">
            <h4 class="font-bold text-gray-800 mb-4">Complainant Information</h4>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Name:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Address:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($case['address'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Barangay:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($case['user_barangay'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Contact:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($case['phone'] ?? $case['email'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Case Details -->
    <div class="bg-white p-6 rounded-xl border">
        <h4 class="font-bold text-gray-800 mb-4">Case Details</h4>
        <div class="space-y-4">
            <div>
                <h5 class="font-medium text-gray-700 mb-2">Incident Title</h5>
                <p class="text-gray-800"><?php echo htmlspecialchars($case['title']); ?></p>
            </div>
            <div>
                <h5 class="font-medium text-gray-700 mb-2">Description</h5>
                <p class="text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($case['description']); ?></p>
            </div>
            <div>
                <h5 class="font-medium text-gray-700 mb-2">Location</h5>
                <p class="text-gray-800"><?php echo htmlspecialchars($case['location']); ?></p>
            </div>
            <div>
                <h5 class="font-medium text-gray-700 mb-2">Date & Time of Incident</h5>
                <p class="text-gray-800"><?php echo date('F d, Y h:i A', strtotime($case['incident_date'])); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Attachments -->
    <?php if (count($attachments) > 0): ?>
    <div class="bg-white p-6 rounded-xl border">
        <h4 class="font-bold text-gray-800 mb-4">Attachments (<?php echo count($attachments); ?>)</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($attachments as $attachment): ?>
            <div class="border rounded-lg p-4">
                <div class="flex items-center mb-2">
                    <i class="fas fa-file text-blue-600 mr-2"></i>
                    <span class="font-medium truncate"><?php echo htmlspecialchars($attachment['filename']); ?></span>
                </div>
                <div class="text-sm text-gray-600 mb-3">
                    <?php echo round($attachment['filesize'] / 1024, 2); ?> KB
                </div>
                <a href="../uploads/reports/<?php echo $attachment['filepath']; ?>" 
                   target="_blank" 
                   class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-download mr-1"></i> Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Assignment Information -->
    <?php if ($officer): ?>
    <div class="bg-white p-6 rounded-xl border">
        <h4 class="font-bold text-gray-800 mb-4">Assignment Information</h4>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-600">Assigned Officer:</span>
                <span class="font-medium"><?php echo htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Officer Role:</span>
                <span class="font-medium"><?php echo ucwords(str_replace('_', ' ', $officer['role'])); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Assigned Date:</span>
                <span class="font-medium"><?php echo date('F d, Y h:i A', strtotime($case['assigned_at'])); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>