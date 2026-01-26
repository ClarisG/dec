<?php
// Enhanced error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure correct paths for dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php'; // Assuming a functions file for helpers

// Validate and sanitize input
$case_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$case_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid Case ID']));
}

try {
    // Corrected SQL query to use `permanent_address`
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name, 
               u.last_name,
               u.contact_number,
               u.email,
               u.permanent_address as address, -- Corrected column
               r.created_at as report_date
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.id = :case_id
    ");
    $stmt->execute([':case_id' => $case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        die('<div class="text-center py-8 text-red-600">Case not found. Please check the ID and try again.</div>');
    }
    
    // Fetch attachments in a separate query
    $attachments_stmt = $conn->prepare("
        SELECT * FROM report_attachments 
        WHERE report_id = :case_id 
        ORDER BY created_at
    ");
    $attachments_stmt->execute([':case_id' => $case_id]);
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates for display
    $report_date = !empty($case['report_date']) ? date('F d, Y h:i A', strtotime($case['report_date'])) : 'N/A';
    $incident_date = !empty($case['incident_date']) ? date('F d, Y', strtotime($case['incident_date'])) : 'N/A';
    
    // Helper function for status badge (can be moved to functions.php)
    function getStatusBadgeClass($status) {
        $map = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'assigned' => 'bg-blue-100 text-blue-800',
            'investigating' => 'bg-purple-100 text-purple-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-100 text-gray-800',
            'referred' => 'bg-indigo-100 text-indigo-800'
        ];
        return $map[$status] ?? 'bg-gray-200 text-gray-800';
    }

?>
    
<div class="space-y-6">
    <!-- Case Header -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <h4 class="font-bold text-lg text-gray-800">Case #<?php echo htmlspecialchars($case['id']); ?></h4>
                <p class="text-gray-600"><?php echo htmlspecialchars($case['title']); ?></p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo getStatusBadgeClass($case['status']); ?>">
                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($case['status']))); ?>
            </span>
        </div>
    </div>
    
    <!-- Grid Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h5 class="font-bold text-gray-700 mb-3 flex items-center"><i class="fas fa-user-circle mr-2 text-blue-600"></i>Complainant Information</h5>
                <dl class="space-y-2">
                    <div class="flex"><dt class="w-1/3 text-gray-600">Name:</dt><dd class="w-2/3 font-medium"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></dd></div>
                    <div class="flex"><dt class="w-1/3 text-gray-600">Contact:</dt><dd class="w-2/3"><?php echo htmlspecialchars($case['contact_number'] ?? 'N/A'); ?></dd></div>
                    <div class="flex"><dt class="w-1/3 text-gray-600">Email:</dt><dd class="w-2/3"><?php echo htmlspecialchars($case['email'] ?? 'N/A'); ?></dd></div>
                    <div class="flex"><dt class="w-1/3 text-gray-600">Address:</dt><dd class="w-2/3"><?php echo htmlspecialchars($case['address'] ?? 'N/A'); ?></dd></div>
                </dl>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h5 class="font-bold text-gray-700 mb-3 flex items-center"><i class="fas fa-calendar-alt mr-2 text-green-600"></i>Case Timeline</h5>
                <dl class="space-y-2">
                    <div class="flex"><dt class="w-1/2 text-gray-600">Report Filed:</dt><dd class="w-1/2"><?php echo $report_date; ?></dd></div>
                    <div class="flex"><dt class="w-1/2 text-gray-600">Incident Date:</dt><dd class="w-1/2"><?php echo $incident_date; ?></dd></div>
                    <div class="flex"><dt class="w-1/2 text-gray-600">Priority:</dt><dd class="w-1/2"><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $case['priority'] == 'high' ? 'bg-red-100 text-red-800' : ($case['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>"><?php echo ucfirst(htmlspecialchars($case['priority'])); ?></span></dd></div>
                </dl>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h5 class="font-bold text-gray-700 mb-3 flex items-center"><i class="fas fa-file-alt mr-2 text-purple-600"></i>Incident Details</h5>
                <div class="space-y-3">
                    <div><p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p></div>
                    <?php if (!empty($case['location'])): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Incident Location:</p>
                        <p class="font-medium"><?php echo htmlspecialchars($case['location']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($attachments) > 0): ?>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h5 class="font-bold text-gray-700 mb-3 flex items-center"><i class="fas fa-paperclip mr-2 text-blue-600"></i>Attachments (<?php echo count($attachments); ?>)</h5>
                <div class="space-y-2">
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <div class="flex items-center truncate">
                            <i class="fas fa-file mr-2 text-gray-500"></i>
                            <span class="text-sm truncate" title="<?php echo htmlspecialchars($attachment['filename']); ?>"><?php echo htmlspecialchars($attachment['filename']); ?></span>
                        </div>
                        <a href="../uploads/reports/<?php echo htmlspecialchars($attachment['filepath']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm ml-2"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($case['actions_taken'])): ?>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h5 class="font-bold text-gray-700 mb-3 flex items-center"><i class="fas fa-tasks mr-2 text-green-600"></i>Initial Actions Taken</h5>
        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($case['actions_taken'])); ?></p>
    </div>
    <?php endif; ?>
</div>

<?php
    
} catch (PDOException $e) {
    http_response_code(500);
    // Provide a more user-friendly error and log the detailed one
    error_log("Case Details Error: " . $e->getMessage());
    echo '<div class="text-center py-8 text-red-600">Error loading case details. Please contact support.</div>';
    // For debugging, you might want to show the error
    // echo '<div class="text-center py-8 text-red-600">Error loading case details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>