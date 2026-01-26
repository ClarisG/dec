<?php
require_once '../config/database.php';
require_once '../config/session.php';

$case_id = $_GET['id'] ?? 0;

try {
    // Fetch case details
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name, 
               u.last_name,
               u.contact_number,
               u.email,
               u.permanent_address as address,
               r.created_at as report_date
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        die('<div class="text-center py-8 text-red-600">Case not found</div>');
    }
    
    // Fetch attachments
    $attachments_stmt = $conn->prepare("
        SELECT * FROM report_attachments 
        WHERE report_id = ? 
        ORDER BY created_at
    ");
    $attachments_stmt->execute([$case_id]);
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates
    $report_date = date('F d, Y h:i A', strtotime($case['report_date']));
    $incident_date = date('F d, Y', strtotime($case['incident_date']));
    
    ?>
    
    <div class="space-y-6">
        <!-- Case Header -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-start">
                <div>
                    <h4 class="font-bold text-lg text-gray-800">Case #<?php echo $case['id']; ?></h4>
                    <p class="text-gray-600"><?php echo htmlspecialchars($case['title']); ?></p>
                </div>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                    <?php echo ucfirst($case['status']); ?>
                </span>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column - Complainant Info -->
            <div class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                        Complainant Information
                    </h5>
                    <div class="space-y-2">
                        <div class="flex">
                            <span class="w-1/3 text-gray-600">Name:</span>
                            <span class="w-2/3 font-medium"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-1/3 text-gray-600">Contact:</span>
                            <span class="w-2/3"><?php echo htmlspecialchars($case['contact_number']); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-1/3 text-gray-600">Email:</span>
                            <span class="w-2/3"><?php echo htmlspecialchars($case['email']); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-1/3 text-gray-600">Address:</span>
                            <span class="w-2/3"><?php echo htmlspecialchars($case['address']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-green-600"></i>
                        Case Timeline
                    </h5>
                    <div class="space-y-2">
                        <div class="flex">
                            <span class="w-1/2 text-gray-600">Report Filed:</span>
                            <span class="w-1/2"><?php echo $report_date; ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-1/2 text-gray-600">Incident Date:</span>
                            <span class="w-1/2"><?php echo $incident_date; ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-1/2 text-gray-600">Priority:</span>
                            <span class="w-1/2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $case['priority'] == 'high' ? 'bg-red-100 text-red-800' : ($case['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo ucfirst($case['priority']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Case Details -->
            <div class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                        Incident Details
                    </h5>
                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                        </div>
                        <?php if (!empty($case['location'])): ?>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Incident Location:</p>
                            <p class="font-medium"><?php echo htmlspecialchars($case['location']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attachments -->
                <?php if (count($attachments) > 0): ?>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-paperclip mr-2 text-blue-600"></i>
                        Attachments (<?php echo count($attachments); ?>)
                    </h5>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-file mr-2 text-gray-500"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($attachment['filename']); ?></span>
                            </div>
                            <a href="../uploads/reports/<?php echo $attachment['filepath']; ?>" 
                               target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions Taken -->
        <?php if (!empty($case['actions_taken'])): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                <i class="fas fa-tasks mr-2 text-green-600"></i>
                Initial Actions Taken
            </h5>
            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($case['actions_taken'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-red-600">Error loading case details: ' . $e->getMessage() . '</div>';
}
?>