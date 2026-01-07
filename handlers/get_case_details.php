<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    die("Unauthorized access");
}

$case_id = $_GET['id'] ?? 0;

try {
    $conn = getDbConnection();
    
    // Get case details
    $query = "SELECT r.*, 
                     u.first_name, 
                     u.last_name,
                     u.contact_number,
                     u.email,
                     u.permanent_address,
                     u.barangay,
                     (SELECT COUNT(*) FROM report_attachments WHERE report_id = r.id) as attachment_count
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $case_id);
    $stmt->execute();
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        echo '<div class="text-center py-8 text-red-600">Case not found</div>';
        exit;
    }
    
    // Get attachments
    $attachments_query = "SELECT * FROM report_attachments WHERE report_id = :report_id ORDER BY created_at";
    $attachments_stmt = $conn->prepare($attachments_query);
    $attachments_stmt->bindParam(':report_id', $case_id);
    $attachments_stmt->execute();
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ?>
    <div class="space-y-6">
        <!-- Case Header -->
        <div class="bg-blue-50 p-4 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="font-bold text-gray-800 text-lg">Case #<?php echo $case['id']; ?></h4>
                    <p class="text-gray-600">Filed on <?php echo date('F d, Y', strtotime($case['created_at'])); ?></p>
                </div>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    <?php echo ucwords($case['status']); ?>
                </span>
            </div>
        </div>
        
        <!-- Complainant Information -->
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h5 class="font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-user mr-2 text-blue-600"></i>
                Complainant Information
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Full Name</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Contact Number</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['contact_number'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Email Address</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['email'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Address</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['permanent_address'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Barangay</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['barangay'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Case Details -->
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h5 class="font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-file-alt mr-2 text-blue-600"></i>
                Case Details
            </h5>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-600">Category</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['title']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Description</p>
                    <p class="font-medium text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($case['description']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Location of Incident</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['incident_location'] ?? 'Not specified'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Date/Time of Incident</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($case['incident_date'] ? date('F d, Y', strtotime($case['incident_date'])) : 'Not specified'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Attachments -->
        <?php if (count($attachments) > 0): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h5 class="font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-paperclip mr-2 text-blue-600"></i>
                Attachments (<?php echo count($attachments); ?>)
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($attachments as $attachment): 
                    $file_ext = strtolower(pathinfo($attachment['file_path'], PATHINFO_EXTENSION));
                    $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                    $is_video = in_array($file_ext, ['mp4', 'avi', 'mov', 'mkv', 'wmv']);
                    $is_pdf = $file_ext === 'pdf';
                    $file_url = '../uploads/report/' . $attachment['file_path'];
                ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <?php if ($is_image): ?>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-image text-green-600"></i>
                            </div>
                        <?php elseif ($is_pdf): ?>
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file-pdf text-red-600"></i>
                            </div>
                        <?php elseif ($is_video): ?>
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-video text-purple-600"></i>
                            </div>
                        <?php else: ?>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file text-blue-600"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800 text-sm truncate"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo strtoupper($file_ext); ?> • <?php echo round($attachment['file_size'] / 1024, 1); ?> KB</p>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <a href="<?php echo $file_url; ?>" 
                           target="_blank" 
                           class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i> Open
                        </a>
                        <a href="<?php echo $file_url; ?>" 
                           download 
                           class="text-gray-600 hover:text-gray-800 text-sm">
                            <i class="fas fa-download mr-1"></i> Download
                        </a>
                    </div>
                    <?php if ($is_image): ?>
                        <div class="mt-3">
                            <img src="<?php echo $file_url; ?>" 
                                 alt="Attachment" 
                                 class="attachment-preview cursor-pointer" 
                                 onclick="window.open('<?php echo $file_url; ?>', '_blank')">
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions Taken -->
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h5 class="font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-tasks mr-2 text-blue-600"></i>
                Actions Taken
            </h5>
            <?php if (!empty($case['action_taken'])): ?>
                <p class="font-medium text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($case['action_taken']); ?></p>
            <?php else: ?>
                <p class="text-gray-500 italic">No actions recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
} catch(PDOException $e) {
    echo '<div class="text-center py-8 text-red-600">Error: ' . $e->getMessage() . '</div>';
}
?>