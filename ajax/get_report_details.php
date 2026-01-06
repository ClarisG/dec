<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access';
    exit;
}

$report_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($report_id == 0) {
    http_response_code(400);
    echo 'Invalid report ID';
    exit;
}

try {
    $conn = getDbConnection();
    
    // Get report details
    $query = "SELECT 
        r.*,
        rt.type_name,
        rt.jurisdiction,
        u.first_name as assigned_first_name,
        u.last_name as assigned_last_name,
        u.email as assigned_email,
        u.contact_number as assigned_contact,
        uc.first_name as creator_first_name,
        uc.last_name as creator_last_name
    FROM reports r
    LEFT JOIN report_types rt ON r.report_type_id = rt.id
    LEFT JOIN users u ON r.assigned_to = u.id
    LEFT JOIN users uc ON r.user_id = uc.id
    WHERE r.id = :report_id AND r.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':report_id' => $report_id, ':user_id' => $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        echo 'Report not found or access denied';
        exit;
    }
    
    // Get status history (TIMELINE)
    $history_query = "SELECT 
        h.*,
        u.first_name,
        u.last_name,
        u.role
    FROM report_status_history h
    LEFT JOIN users u ON h.updated_by = u.id
    WHERE h.report_id = :report_id
    ORDER BY h.created_at DESC";
    
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->execute([':report_id' => $report_id]);
    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get evidence files
    $evidence_files = [];
    if (!empty($report['evidence_files'])) {
        $evidence_files = json_decode($report['evidence_files'], true);
    }
    
    // Status colors
    $status_colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'investigating' => 'bg-purple-100 text-purple-800',
        'resolved' => 'bg-green-100 text-green-800',
        'referred' => 'bg-orange-100 text-orange-800',
        'closed' => 'bg-gray-100 text-gray-800'
    ];
    
    $status_color = $status_colors[$report['status']] ?? 'bg-gray-100 text-gray-800';
    
?>
<div class="space-y-6">
    <!-- Report Header -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <h4 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($report['title']); ?></h4>
                <div class="flex items-center mt-2 space-x-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_color; ?>">
                        <?php echo ucfirst($report['status']); ?>
                    </span>
                    <span class="text-gray-600">
                        <i class="fas fa-hashtag mr-1"></i>
                        <?php echo htmlspecialchars($report['report_number']); ?>
                    </span>
                    <span class="text-gray-600">
                        <i class="fas fa-calendar mr-1"></i>
                        <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                    </span>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Report Type</p>
                <p class="font-medium"><?php echo htmlspecialchars($report['type_name']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- Description -->
            <div class="bg-white p-4 rounded-lg border">
                <h5 class="font-semibold text-gray-800 mb-2">Description</h5>
                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
            </div>
            
            <!-- Location & Incident Details -->
            <div class="bg-white p-4 rounded-lg border">
                <h5 class="font-semibold text-gray-800 mb-3">Incident Details</h5>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500">Location</p>
                        <p class="font-medium"><?php echo htmlspecialchars($report['location']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Incident Date & Time</p>
                        <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($report['incident_date'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Reported On</p>
                        <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Assignment Info -->
            <?php if (!empty($report['assigned_first_name'])): ?>
            <div class="bg-white p-4 rounded-lg border">
                <h5 class="font-semibold text-gray-800 mb-3">Assigned To</h5>
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                        <i class="fas fa-user text-blue-600"></i>
                    </div>
                    <div>
                        <p class="font-medium"><?php echo htmlspecialchars($report['assigned_first_name'] . ' ' . $report['assigned_last_name']); ?></p>
                        <?php if (!empty($report['assigned_email'])): ?>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($report['assigned_email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($report['assigned_contact'])): ?>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($report['assigned_contact']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Evidence Files -->
            <?php if (!empty($evidence_files)): ?>
            <div class="bg-white p-4 rounded-lg border">
                <h5 class="font-semibold text-gray-800 mb-3">Attached Files</h5>
                <div class="space-y-2">
                    <?php foreach ($evidence_files as $file): ?>
                    <?php 
                    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                    $is_video = in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']);
                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                    $is_document = in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx']);
                    $file_path = isset($file['path']) ? '../uploads/' . $file['path'] : '';
                    $file_exists = isset($file['path']) && file_exists($file_path);
                    ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center">
                            <?php if ($is_video): ?>
                                <i class="fas fa-video text-red-500 mr-3"></i>
                            <?php elseif ($is_image): ?>
                                <i class="fas fa-image text-green-500 mr-3"></i>
                            <?php elseif ($is_document): ?>
                                <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                            <?php else: ?>
                                <i class="fas fa-file text-gray-500 mr-3"></i>
                            <?php endif; ?>
                            <div>
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($file['name'] ?? 'file'); ?></span>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php 
                                    if ($is_video) {
                                        echo 'Video File';
                                    } elseif ($is_image) {
                                        echo 'Image File';
                                    } elseif ($is_document) {
                                        echo 'Document';
                                    } else {
                                        echo 'File';
                                    }
                                    if (isset($file['size'])) {
                                        echo ' • ' . formatBytes($file['size']);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($file_exists): ?>
                        <div class="flex space-x-2">
                            <?php if ($is_image): ?>
                            <a href="<?php echo $file_path; ?>" 
                               class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs hover:bg-blue-100 flex items-center transition-colors"
                               target="_blank">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo $file_path; ?>" 
                               class="px-3 py-1.5 bg-green-50 text-green-700 rounded text-xs hover:bg-green-100 flex items-center transition-colors"
                               target="_blank" 
                               download>
                                <i class="fas fa-download mr-1"></i> Download
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400 px-2 py-1 bg-gray-100 rounded">File not available</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="bg-white p-4 rounded-lg border">
                <h5 class="font-semibold text-gray-800 mb-3">Quick Actions</h5>
                <div class="flex flex-wrap gap-2">
                    <button onclick="printReport(<?php echo $report_id; ?>); return false;"
                            class="px-3 py-2 bg-blue-50 text-blue-700 rounded text-sm hover:bg-blue-100 transition-colors">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                    <button onclick="viewReportTimeline(<?php echo $report_id; ?>); return false;"
                            class="px-3 py-2 bg-gray-50 text-gray-700 rounded text-sm hover:bg-gray-100 transition-colors">
                        <i class="fas fa-history mr-1"></i> Timeline
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status History (TIMELINE) -->
    <?php if (!empty($history)): ?>
    <div class="bg-white p-4 rounded-lg border">
        <h5 class="font-semibold text-gray-800 mb-3">Status History</h5>
        <div class="space-y-3">
            <?php foreach ($history as $item): ?>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3 mt-1">
                    <i class="fas fa-history text-blue-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between">
                        <p class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?></p>
                        <p class="text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?></p>
                    </div>
                    <?php if (!empty($item['notes'])): ?>
                    <p class="text-gray-600 mt-1 text-sm"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['first_name'])): ?>
                    <p class="text-xs text-gray-500 mt-1">
                        Updated by: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                        (<?php echo ucfirst($item['role']); ?>)
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
} catch(PDOException $e) {
    http_response_code(500);
    echo '<div class="text-center p-8 text-red-600">Error loading report details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>