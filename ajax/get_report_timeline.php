<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Report ID required');
}

$report_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Verify report ownership
    $check_query = "SELECT id FROM reports WHERE id = :id AND user_id = :user_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':id', $report_id);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        exit('Report not found');
    }
    
    // Get timeline
    $query = "SELECT h.*, u.first_name, u.last_name, u.role 
             FROM report_status_history h
             LEFT JOIN users u ON h.updated_by = u.id
             WHERE h.report_id = :report_id
             ORDER BY h.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':report_id', $report_id);
    $stmt->execute();
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get report info
    $report_query = "SELECT r.title, r.report_number, r.status 
                    FROM reports r WHERE r.id = :id";
    $report_stmt = $conn->prepare($report_query);
    $report_stmt->bindParam(':id', $report_id);
    $report_stmt->execute();
    $report = $report_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    http_response_code(500);
    exit('Database error');
}

// Function to get status icon
function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'submitted' => 'fa-paper-plane',
        'for_verification' => 'fa-search',
        'for_mediation' => 'fa-handshake',
        'referred' => 'fa-external-link-alt',
        'resolved' => 'fa-check-circle',
        'closed' => 'fa-archive'
    ];
    return $icons[$status] ?? 'fa-circle';
}
?>

<div class="pb-4">
    <!-- Header -->
    <div class="mb-6 text-center">
        <h4 class="text-lg font-bold text-gray-800 mb-1">Report Timeline</h4>
        <p class="text-gray-600"><?php echo htmlspecialchars($report['title']); ?></p>
        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($report['report_number']); ?></p>
    </div>
    
    <?php if (count($timeline) > 0): ?>
        <div class="relative">
            <!-- Timeline Line -->
            <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-blue-200"></div>
            
            <!-- Timeline Items -->
            <div class="space-y-8">
                <?php foreach ($timeline as $index => $item): ?>
                    <?php
                    $is_last = $index === count($timeline) - 1;
                    $icon_color = $is_last ? 'bg-blue-500' : 'bg-blue-200';
                    ?>
                    
                    <div class="relative flex items-start">
                        <!-- Icon -->
                        <div class="relative z-10 flex-shrink-0">
                            <div class="w-12 h-12 rounded-full <?php echo $icon_color; ?> flex items-center justify-center text-white">
                                <i class="fas <?php echo getStatusIcon($item['status']); ?>"></i>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="ml-6 flex-1">
                            <div class="bg-white border rounded-lg p-4 shadow-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-800">
                                        <?php echo ucwords(str_replace('_', ' ', $item['status'])); ?>
                                    </h5>
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($item['notes'])): ?>
                                    <p class="text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['first_name'])): ?>
                                    <div class="flex items-center text-sm text-gray-500">
                                        <i class="fas fa-user mr-2"></i>
                                        <span>
                                            <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                            (<?php echo ucfirst($item['role']); ?>)
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-robot mr-2"></i>
                                        System Update
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Current Status -->
                <div class="relative flex items-start">
                    <div class="relative z-10 flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center text-white animate-pulse">
                            <i class="fas <?php echo getStatusIcon($report['status']); ?>"></i>
                        </div>
                    </div>
                    
                    <div class="ml-6 flex-1">
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="font-bold text-green-800">
                                    Current Status: <?php echo ucwords(str_replace('_', ' ', $report['status'])); ?>
                                </h5>
                                <span class="text-sm text-green-600 font-medium">
                                    <i class="fas fa-circle animate-pulse"></i> Active
                                </span>
                            </div>
                            <p class="text-green-700">
                                Your report is currently being processed. You will be notified of any updates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-history text-gray-400 text-2xl"></i>
            </div>
            <h4 class="text-lg font-medium text-gray-900 mb-2">No timeline available</h4>
            <p class="text-gray-500">Timeline will appear as your report is processed.</p>
        </div>
    <?php endif; ?>
    
    <!-- Status Legend -->
    <div class="mt-8 pt-6 border-t">
        <h5 class="font-semibold text-gray-700 mb-3">Status Legend</h5>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                <span class="text-sm text-gray-600">Pending/Submitted</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full bg-orange-500 mr-2"></div>
                <span class="text-sm text-gray-600">Verification</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full bg-purple-500 mr-2"></div>
                <span class="text-sm text-gray-600">Mediation</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                <span class="text-sm text-gray-600">Resolved/Closed</span>
            </div>
        </div>
    </div>
</div>