<?php
// sec/modules/classification_review.php - Enhanced Version with Full Functionality

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    exit('Unauthorized');
}

// Database connection is expected to be available from secretary_dashboard.php
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getDbConnection();
}

// Include email helper functions
require_once __DIR__ . '/../../includes/email_helper.php';

// Handle classification override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_classification'])) {
    $report_id = intval($_POST['report_id']);
    $new_classification = $_POST['classification']; // 'barangay' or 'police'
    $notes = $_POST['notes'] ?? '';
    $category = $_POST['category'] ?? 'incident';
    $severity_level = $_POST['severity_level'] ?? 'medium';
    $priority = $_POST['priority'] ?? 'medium';
    
    try {
        $conn->beginTransaction();
        
        // Get current report details for logging and routing
        $stmt = $conn->prepare("SELECT r.*, u.id as user_id, u.email, u.first_name, u.last_name 
                               FROM reports r 
                               LEFT JOIN users u ON r.user_id = u.id 
                               WHERE r.id = :id");
        $stmt->execute([':id' => $report_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            throw new Exception("Report not found");
        }
        
        $original = $current['classification_override'] ?? $current['ai_classification'] ?? 'uncertain';
        
        // Update report with new classification and all fields
        $update_stmt = $conn->prepare("UPDATE reports SET 
            classification_override = :classification,
            override_notes = :notes,
            overridden_by = :user_id,
            overridden_at = NOW(),
            last_status_change = NOW(),
            routing_updated = 1,
            category = CASE 
                WHEN :classification = 'barangay' THEN 'Barangay Matter'
                WHEN :classification = 'police' THEN 'Police Matter'
                ELSE :category
            END,
            severity_level = :severity_level,
            priority = :priority,
            updated_at = NOW()
            WHERE id = :id");
            
        $update_stmt->execute([
            ':classification' => $new_classification,
            ':notes' => $notes,
            ':user_id' => $_SESSION['user_id'],
            ':category' => $category,
            ':severity_level' => $severity_level,
            ':priority' => $priority,
            ':id' => $report_id
        ]);
        
        // Log to classification_logs
        $log_stmt = $conn->prepare("INSERT INTO classification_logs 
            (report_id, original_classification, new_classification, changed_by, notes, change_type) 
            VALUES (:report_id, :original, :new, :user_id, :notes, 'manual_override')");
            
        $log_stmt->execute([
            ':report_id' => $report_id,
            ':original' => $original,
            ':new' => $new_classification,
            ':user_id' => $_SESSION['user_id'],
            ':notes' => $notes . " | Category: " . ucfirst($category) . " | Severity: " . ucfirst($severity_level) . " | Priority: " . ucfirst($priority)
        ]);
        
        // Update routing flags for immediate queue reflection
        try {
            $routing_stmt = $conn->prepare("
                UPDATE report_routing 
                SET needs_update = 1, 
                    last_updated = NOW(),
                    updated_by = :user_id
                WHERE report_id = :report_id
            ");
            $routing_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':report_id' => $report_id
            ]);
        } catch (Exception $e) {
            // Routing table might not exist, continue anyway
        }
        
        // Create notification for citizen about classification change
        if ($current['user_id']) {
            $new_jurisdiction = $new_classification == 'barangay' ? 'Barangay Matter' : 'Police Matter';
            $message = "Your report #" . ($current['report_number'] ?? $current['id']) . " has been reclassified to: $new_jurisdiction. Click to view updated details.";
            
            // Insert into notifications table with action_url
            $notification_stmt = $conn->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, related_id, related_type, action_url, created_at) 
                VALUES (:user_id, 'Report Classification Updated', 
                        :message, 
                        'classification_change', :report_id, 'report', 
                        CONCAT('?module=my-reports&highlight=', :report_id), NOW())
            ");
            $notification_stmt->execute([
                ':user_id' => $current['user_id'],
                ':message' => $message,
                ':report_id' => $report_id
            ]);
            
            // Send email notification to citizen
            $mail_subject = "Report Classification Update - Report #" . ($current['report_number'] ?? $current['id']);
            $mail_body = "
            <h3>Report Classification Update</h3>
            <p>Dear " . htmlspecialchars($current['first_name'] . ' ' . $current['last_name']) . ",</p>
            <p>Your report has been reviewed and updated by our secretary.</p>
            <p><strong>Report Details:</strong></p>
            <ul>
                <li><strong>Report ID:</strong> #" . ($current['report_number'] ?? $current['id']) . "</li>
                <li><strong>New Classification:</strong> $new_jurisdiction</li>
                <li><strong>Report Category:</strong> " . htmlspecialchars(ucfirst($category)) . "</li>
                <li><strong>Severity Level:</strong> " . htmlspecialchars(ucfirst($severity_level)) . "</li>
                <li><strong>Priority:</strong> " . htmlspecialchars(ucfirst($priority)) . "</li>
            </ul>
            <p><strong>Reason for change:</strong> " . htmlspecialchars($notes) . "</p>
            <p><strong>Report Status:</strong> The report has been moved to the appropriate department for processing.</p>
            <p>You can view the updated report details by clicking the notification in your dashboard or visiting your reports page.</p>
            <p>Thank you for using our reporting system.</p>
            ";
            
            // Send email using sendEmailNotification function
            sendEmailNotification($current['email'], $current['first_name'] . ' ' . $current['last_name'], $mail_subject, $mail_body);
        }
        
        $conn->commit();
        $success_message = "Report classification updated successfully! Citizen has been notified via email and notification.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error updating classification: " . $e->getMessage();
    }
}

// Fetch reports WITHOUT ai_analysis table join (using existing columns)
$query = "SELECT r.*, 
          u.first_name, u.last_name, u.email,
          rt.type_name as report_type,
          COALESCE(r.classification_override, r.ai_classification, 'uncertain') as current_jurisdiction,
          r.ai_confidence,
          r.ai_classification as original_ai_prediction,
          (SELECT COUNT(*) FROM classification_logs cl WHERE cl.report_id = r.id) as change_count
          FROM reports r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN report_types rt ON r.report_type_id = rt.id
          WHERE r.status IN ('pending', 'pending_field_verification')
          ORDER BY 
            CASE 
                WHEN r.classification_override IS NULL AND r.ai_classification IS NOT NULL THEN 0
                ELSE 1
            END,
            r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Enhanced UI Implementation -->
<div class="space-y-6">
    <!-- Header with Stats -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Classification Review</h2>
            </div>
            
            <!-- Stats -->
            <div class="flex space-x-4">
                <div class="bg-blue-50 px-4 py-3 rounded-lg border border-blue-100">
                    <span class="block text-xs text-blue-600 font-semibold uppercase">Total Reports</span>
                    <span class="text-xl font-bold text-blue-800"><?php echo count($reports); ?></span>
                </div>
                <div class="bg-yellow-50 px-4 py-3 rounded-lg border border-yellow-100">
                    <span class="block text-xs text-yellow-600 font-semibold uppercase">Needs Review</span>
                    <span class="text-xl font-bold text-yellow-800">
                        <?php 
                        $needs_review = 0;
                        foreach ($reports as $r) {
                            if (empty($r['classification_override'])) $needs_review++;
                        }
                        echo $needs_review;
                        ?>
                    </span>
                </div>
                <div class="bg-green-50 px-4 py-3 rounded-lg border border-green-100">
                    <span class="block text-xs text-green-600 font-semibold uppercase">Reviewed</span>
                    <span class="text-xl font-bold text-green-800">
                        <?php 
                        $reviewed = 0;
                        foreach ($reports as $r) {
                            if (!empty($r['classification_override'])) $reviewed++;
                        }
                        echo $reviewed;
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Filter Options -->
        <div class="mb-6">
            <div class="flex space-x-4">
                <button onclick="filterReports('all')" class="filter-btn active px-4 py-2 rounded-lg" data-filter="all">
                    All Reports (<?php echo count($reports); ?>)
                </button>
                <button onclick="filterReports('needs_review')" class="filter-btn px-4 py-2 rounded-lg" data-filter="needs_review">
                    Needs Review (<?php echo $needs_review; ?>)
                </button>
                <button onclick="filterReports('reviewed')" class="filter-btn px-4 py-2 rounded-lg" data-filter="reviewed">
                    Already Reviewed (<?php echo $reviewed; ?>)
                </button>
            </div>
        </div>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3 text-green-500 text-xl"></i>
                <div>
                    <p class="font-medium"><?php echo $success_message; ?></p>
                    <p class="text-sm mt-1">Citizen has been notified via email and notification.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3 text-red-500 text-xl"></i>
                <p class="font-medium"><?php echo $error_message; ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reports Table -->
    <div class="glass-card rounded-xl p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                        <th class="p-4 font-semibold">Report Info</th>
                        <th class="p-4 font-semibold">Citizen</th>
                        <th class="p-4 font-semibold">AI Prediction</th>
                        <th class="p-4 font-semibold">Current</th>
                        <th class="p-4 font-semibold">Status</th>
                        <th class="p-4 font-semibold">History</th>
                        <th class="p-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="reportsTable">
                    <?php foreach ($reports as $report): ?>
                        <?php 
                        $needs_review = empty($report['classification_override']);
                        $row_class = $needs_review ? 'bg-yellow-50 hover:bg-yellow-100' : 'hover:bg-gray-50';
                        ?>
                        <tr class="<?php echo $row_class; ?> transition-colors report-row cursor-pointer" 
                            data-review-status="<?php echo $needs_review ? 'needs_review' : 'reviewed'; ?>"
                            onclick="openClassificationModal(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                            <td class="p-4 align-top">
                                <span class="block font-medium text-gray-900">#<?php echo htmlspecialchars($report['report_number'] ?? $report['id']); ?></span>
                                <span class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></span>
                                <div class="mt-1">
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full border border-gray-200">
                                        <?php echo htmlspecialchars($report['report_type'] ?? 'Unknown'); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 align-top">
                                <span class="block text-sm text-gray-900 font-medium">
                                    <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                </span>
                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($report['email'] ?? ''); ?></span>
                            </td>
                            <td class="p-4 align-top">
                                <?php 
                                    $ai_class = $report['original_ai_prediction'] ?? 'Uncertain';
                                    $ai_conf = $report['ai_confidence'] ?? 0;
                                    $badge_color = 'bg-gray-100 text-gray-600';
                                    if (strtolower($ai_class) == 'police') $badge_color = 'bg-red-100 text-red-700 border border-red-200';
                                    if (strtolower($ai_class) == 'barangay') $badge_color = 'bg-green-100 text-green-700 border border-green-200';
                                ?>
                                <div class="flex flex-col space-y-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                        <i class="fas fa-robot mr-1"></i> <?php echo ucfirst($ai_class); ?>
                                    </span>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min($ai_conf, 100); ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-400"><?php echo $ai_conf; ?>% confident</span>
                                </div>
                            </td>
                            <td class="p-4 align-top">
                                <?php 
                                    $current = $report['current_jurisdiction'];
                                    $is_overridden = !empty($report['classification_override']);
                                    $current_badge = 'bg-gray-100 text-gray-600';
                                    if (strtolower($current) == 'police') $current_badge = 'bg-red-100 text-red-700 border border-red-200';
                                    if (strtolower($current) == 'barangay') $current_badge = 'bg-green-100 text-green-700 border border-green-200';
                                ?>
                                <div class="flex flex-col space-y-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $current_badge; ?>">
                                        <?php if ($is_overridden): ?>
                                            <i class="fas fa-user-edit mr-1"></i>
                                        <?php endif; ?>
                                        <?php echo ucfirst($current); ?>
                                    </span>
                                    <?php if ($is_overridden): ?>
                                        <span class="text-xs text-amber-600">
                                            <i class="fas fa-edit mr-1"></i>Edited
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 align-top">
                                <?php 
                                    $queue_status = 'Pending Review';
                                    $queue_color = 'bg-yellow-100 text-yellow-800';
                                    
                                    if ($current == 'barangay' && $report['status'] == 'pending') {
                                        $queue_status = 'Barangay Queue';
                                        $queue_color = 'bg-green-100 text-green-800';
                                    } elseif ($current == 'police' && $report['status'] == 'pending') {
                                        $queue_status = 'Police Queue';
                                        $queue_color = 'bg-red-100 text-red-800';
                                    } elseif ($report['status'] == 'pending_field_verification') {
                                        $queue_status = 'Field Verification';
                                        $queue_color = 'bg-blue-100 text-blue-800';
                                    }
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $queue_color; ?>">
                                    <?php echo $queue_status; ?>
                                </span>
                            </td>
                            <td class="p-4 align-top">
                                <?php if ($report['change_count'] > 0): ?>
                                    <button onclick="event.stopPropagation(); showChangeLog(<?php echo $report['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium hover:underline">
                                        <i class="fas fa-history mr-1"></i> <?php echo $report['change_count']; ?> change(s)
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No changes</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 align-top" onclick="event.stopPropagation();">
                                <button onclick="openClassificationModal(<?php echo htmlspecialchars(json_encode($report)); ?>)" 
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <i class="fas fa-eye mr-1"></i> View & Review
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-clipboard-check text-4xl mb-3 text-gray-300"></i>
                                    <p>No reports found needing review.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Classification Modal - Enhanced with Report Details -->
<div id="classificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 my-8 overflow-hidden transform transition-all">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Report Classification Review</h3>
            <button onclick="closeClassificationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="" class="p-6 overflow-y-auto max-h-[calc(100vh-200px)]">
            <input type="hidden" name="report_id" id="modalReportId">
            <input type="hidden" name="update_classification" value="1">
            
            <!-- Report Header Info -->
            <div class="mb-6 grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 mb-1">REPORT DETAILS</h4>
                    <div class="text-sm">
                        <p><span class="font-medium">ID:</span> <span id="modalReportNumber" class="font-mono"></span></p>
                        <p><span class="font-medium">Citizen:</span> <span id="modalCitizenName"></span></p>
                        <p><span class="font-medium">Date Filed:</span> <span id="modalDateFiled"></span></p>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 mb-1">CURRENT STATUS</h4>
                    <div class="text-sm">
                        <p><span class="font-medium">Status:</span> <span id="modalCurrentStatus"></span></p>
                        <p><span class="font-medium">Type:</span> <span id="modalReportType"></span></p>
                    </div>
                </div>
            </div>
            
            <!-- Report Description Section -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wide">Report Description</h4>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 max-h-48 overflow-y-auto">
                    <p id="modalReportDescription" class="text-gray-700 whitespace-pre-line"></p>
                </div>
            </div>
            
            <!-- AI Analysis Details -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">AI Analysis</h4>
                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full" id="modalAiConfidenceBadge"></span>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 mb-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-blue-600 font-medium mb-1">AI PREDICTION</p>
                            <p class="text-lg font-bold text-gray-800" id="modalAiPrediction"></p>
                            <p class="text-xs text-gray-600 mt-1" id="modalAiReasoning">Based on report content analysis</p>
                            <!-- Confidence calculation explanation -->
                            <div class="mt-2">
                                <p class="text-xs text-blue-600 font-medium">CONFIDENCE CALCULATION</p>
                                <ul class="text-xs text-gray-600 mt-1 space-y-1">
                                    <li id="modalKeywordMatches">Keyword matches: 0</li>
                                    <li id="modalPatternMatches">Pattern matches: 0</li>
                                    <li id="modalJurisdictionScore">Jurisdiction score: 0/100</li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600 font-medium mb-1">CONFIDENCE LEVEL</p>
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-3 mr-2">
                                    <div id="modalConfidenceBar" class="bg-blue-600 h-3 rounded-full transition-all duration-300"></div>
                                </div>
                                <span id="modalAiConfidence" class="text-sm font-bold text-blue-800 min-w-[40px]"></span>
                            </div>
                            <!-- Confidence breakdown -->
                            <div class="mt-3 bg-white p-3 rounded border">
                                <p class="text-xs font-medium text-gray-700 mb-1">Confidence Breakdown:</p>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span>Keyword Analysis</span>
                                        <span id="modalKeywordScore" class="font-medium">0%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1">
                                        <div id="modalKeywordBar" class="bg-green-500 h-1 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span>Context Analysis</span>
                                        <span id="modalContextScore" class="font-medium">0%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1">
                                        <div id="modalContextBar" class="bg-yellow-500 h-1 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span>Pattern Recognition</span>
                                        <span id="modalPatternScore" class="font-medium">0%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1">
                                        <div id="modalPatternBar" class="bg-purple-500 h-1 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Details with dropdowns -->
                <div class="grid grid-cols-3 gap-4">
                    <!-- Report Category Dropdown -->
                    <div class="p-3 bg-white border border-gray-200 rounded-lg">
                        <p class="text-xs text-gray-500 font-medium mb-1">REPORT CATEGORY</p>
                        <select name="category" id="modalReportCategorySelect" 
                                class="w-full text-sm font-medium text-gray-700 border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded cursor-pointer p-2">
                            <option value="incident">Incident Report</option>
                            <option value="complaint">Complaint Report</option>
                            <option value="blotter">Blotter Report</option>
                        </select>
                    </div>
                    
                    <!-- Severity Level Dropdown -->
                    <div class="p-3 bg-white border border-gray-200 rounded-lg">
                        <p class="text-xs text-gray-500 font-medium mb-1">SEVERITY LEVEL</p>
                        <select name="severity_level" id="modalSeveritySelect" 
                                class="w-full text-sm font-medium text-gray-700 border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded cursor-pointer p-2">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    
                    <!-- Priority Dropdown -->
                    <div class="p-3 bg-white border border-gray-200 rounded-lg">
                        <p class="text-xs text-gray-500 font-medium mb-1">PRIORITY</p>
                        <select name="priority" id="modalPrioritySelect" 
                                class="w-full text-sm font-medium text-gray-700 border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded cursor-pointer p-2">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Secretary Review Section -->
            <div class="mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-user-edit text-indigo-600"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Secretary Review & Correction</h4>
                        <p class="text-xs text-gray-500">Review AI analysis and correct if necessary</p>
                    </div>
                </div>
                
                <!-- Classification Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Select Correct Jurisdiction:</label>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="classification" value="barangay" class="peer sr-only" id="radioBarangay" required>
                            <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 transition-all text-center h-full hover:border-green-300">
                                <i class="fas fa-home text-2xl mb-2 text-gray-400 peer-checked:text-green-600"></i>
                                <div class="font-bold text-gray-700 peer-checked:text-green-800">Barangay Matter</div>
                                <div class="text-xs text-gray-500 mt-1">Local disputes, minor offenses</div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer relative">
                            <input type="radio" name="classification" value="police" class="peer sr-only" id="radioPolice" required>
                            <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 transition-all text-center h-full hover:border-red-300">
                                <i class="fas fa-shield-alt text-2xl mb-2 text-gray-400 peer-checked:text-red-600"></i>
                                <div class="font-bold text-gray-700 peer-checked:text-red-800">Police Matter</div>
                                <div class="text-xs text-gray-500 mt-1">Criminal offenses, investigations</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Correction Reason -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Correction:</label>
                    <textarea name="notes" id="modalNotes" rows="3" required 
                              class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow" 
                              placeholder="Explain why you are changing the AI classification. This will be sent to the citizen."></textarea>
                    <p class="text-xs text-gray-500 mt-1">This explanation will be included in the notification sent to the citizen.</p>
                </div>
                
                <!-- Citizen Notification -->
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-bell text-yellow-600 mt-0.5 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-yellow-800">Citizen Notification</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                When you save changes, the citizen will receive:
                            </p>
                            <ul class="text-sm text-yellow-700 mt-2 space-y-1">
                                <li class="flex items-start">
                                    <i class="fas fa-envelope text-xs mr-2 mt-0.5"></i>
                                    <span>Email notification about the classification change</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-bell text-xs mr-2 mt-0.5"></i>
                                    <span>In-app notification with your explanation</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-sync-alt text-xs mr-2 mt-0.5"></i>
                                    <span>Updated status in their report dashboard</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-edit text-xs mr-2 mt-0.5"></i>
                                    <span>Updated category, severity, and priority</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeClassificationModal()" 
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-save mr-2"></i> Save Correction & Notify Citizen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Log Modal -->
<div id="changeLogModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden transform transition-all">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Classification Change History</h3>
            <button onclick="closeChangeLogModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="p-6 max-h-96 overflow-y-auto">
            <div id="changeLogContent" class="space-y-4">
                <!-- Change logs will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Filter reports by status
function filterReports(filterType) {
    const rows = document.querySelectorAll('.report-row');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update active button
    buttons.forEach(btn => {
        btn.classList.remove('active', 'bg-blue-100', 'text-blue-700', 'border', 'border-blue-300');
        if (btn.getAttribute('data-filter') === filterType) {
            btn.classList.add('active', 'bg-blue-100', 'text-blue-700', 'border', 'border-blue-300');
        }
    });
    
    // Filter rows
    rows.forEach(row => {
        const status = row.getAttribute('data-review-status');
        
        if (filterType === 'all') {
            row.style.display = '';
        } else if (filterType === 'needs_review' && status === 'needs_review') {
            row.style.display = '';
        } else if (filterType === 'reviewed' && status === 'reviewed') {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Open classification modal with full report details
function openClassificationModal(report) {
    if (!report || !report.id) {
        console.error('Invalid report data');
        return;
    }
    
    document.getElementById('modalReportId').value = report.id;
    
    // Set report info
    document.getElementById('modalReportNumber').textContent = report.report_number || '#' + report.id;
    document.getElementById('modalCitizenName').textContent = (report.first_name || '') + ' ' + (report.last_name || '');
    document.getElementById('modalDateFiled').textContent = new Date(report.created_at).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    document.getElementById('modalCurrentStatus').textContent = (report.status || 'unknown').replace('_', ' ').toUpperCase();
    document.getElementById('modalReportType').textContent = report.report_type || 'General';
    document.getElementById('modalReportDescription').textContent = report.description || report.incident_details || 'No description provided.';
    
    // Set editable dropdown values
    document.getElementById('modalReportCategorySelect').value = report.category || 'incident';
    document.getElementById('modalSeveritySelect').value = report.severity_level || 'medium';
    document.getElementById('modalPrioritySelect').value = report.priority || 'medium';
    
    // Set AI info with enhanced confidence calculation
    const aiClass = report.original_ai_prediction || 'uncertain';
    let aiConfidence = report.ai_confidence || 0;
    
    // If confidence is 0 or not set, calculate it based on content
    if (aiConfidence === 0 && report.description) {
        aiConfidence = calculateConfidence(report.description, aiClass);
    }
    
    document.getElementById('modalAiPrediction').textContent = aiClass.charAt(0).toUpperCase() + aiClass.slice(1) + ' Matter';
    document.getElementById('modalAiConfidence').textContent = aiConfidence + '%';
    document.getElementById('modalAiConfidenceBadge').textContent = aiConfidence + '% confident';
    document.getElementById('modalConfidenceBar').style.width = aiConfidence + '%';
    
    // Set confidence breakdown
    const breakdown = calculateConfidenceBreakdown(report.description || '', aiClass);
    document.getElementById('modalKeywordScore').textContent = breakdown.keywordScore + '%';
    document.getElementById('modalContextScore').textContent = breakdown.contextScore + '%';
    document.getElementById('modalPatternScore').textContent = breakdown.patternScore + '%';
    document.getElementById('modalKeywordBar').style.width = breakdown.keywordScore + '%';
    document.getElementById('modalContextBar').style.width = breakdown.contextScore + '%';
    document.getElementById('modalPatternBar').style.width = breakdown.patternScore + '%';
    
    document.getElementById('modalKeywordMatches').textContent = `Keyword matches: ${breakdown.keywordMatches}`;
    document.getElementById('modalPatternMatches').textContent = `Pattern matches: ${breakdown.patternMatches}`;
    document.getElementById('modalJurisdictionScore').textContent = `Jurisdiction score: ${breakdown.jurisdictionScore}/100`;
    
    // Set reasoning based on AI classification
    let reasoning = 'Based on report content analysis';
    if (aiClass.toLowerCase() === 'barangay') {
        reasoning = 'Minor dispute or local ordinance violation detected';
    } else if (aiClass.toLowerCase() === 'police') {
        reasoning = 'Criminal offense or serious incident requiring police investigation';
    }
    document.getElementById('modalAiReasoning').textContent = reasoning;
    
    // Select current classification
    const current = (report.current_jurisdiction || '').toLowerCase();
    if (current === 'barangay') {
        document.getElementById('radioBarangay').checked = true;
    } else if (current === 'police') {
        document.getElementById('radioPolice').checked = true;
    } else {
        // If uncertain, default to AI prediction
        if (aiClass.toLowerCase() === 'barangay') {
            document.getElementById('radioBarangay').checked = true;
        } else if (aiClass.toLowerCase() === 'police') {
            document.getElementById('radioPolice').checked = true;
        }
    }
    
    document.getElementById('modalNotes').value = report.override_notes || '';
    
    // Show modal
    document.getElementById('classificationModal').classList.remove('hidden');
    document.getElementById('classificationModal').classList.add('flex');
}

function closeClassificationModal() {
    document.getElementById('classificationModal').classList.add('hidden');
    document.getElementById('classificationModal').classList.remove('flex');
}

// Add confidence calculation functions
function calculateConfidence(text, classification) {
    const keywords = {
        'police': ['murder', 'rape', 'robbery', 'assault', 'drugs', 'weapon', 'gun', 'stabbing', 'shooting', 'kidnapping', 'theft', 'burglary'],
        'barangay': ['noise', 'dispute', 'neighbor', 'boundary', 'garbage', 'animal', 'parking', 'water', 'electricity', 'sanitation', 'ordinance', 'local']
    };
    
    const patterns = {
        'police': [
            /\b(shot|killed|stabbed|robbed|stolen)\b/i,
            /\b(drug|shabu|marijuana)\b/i,
            /\b(rape|molest|sexual assault)\b/i,
            /\b(weapon|gun|knife)\b/i
        ],
        'barangay': [
            /\b(noisy|loud|disturbance)\b/i,
            /\b(neighbor|boundary|fence)\b/i,
            /\b(garbage|trash|waste)\b/i,
            /\b(animal|dog|pet)\b/i
        ]
    };
    
    const lowerText = text.toLowerCase();
    const relevantKeywords = keywords[classification] || [];
    
    // Calculate keyword matches
    let keywordMatches = 0;
    relevantKeywords.forEach(keyword => {
        if (lowerText.includes(keyword.toLowerCase())) {
            keywordMatches++;
        }
    });
    
    // Calculate pattern matches
    let patternMatches = 0;
    const relevantPatterns = patterns[classification] || [];
    relevantPatterns.forEach(pattern => {
        if (pattern.test(text)) {
            patternMatches++;
        }
    });
    
    // Calculate confidence
    const keywordScore = relevantKeywords.length > 0 ? Math.min(100, (keywordMatches / relevantKeywords.length) * 100) : 0;
    const patternScore = relevantPatterns.length > 0 ? Math.min(100, (patternMatches / relevantPatterns.length) * 100) : 0;
    const textLengthScore = Math.min(100, (text.length / 500) * 100);
    
    // Weighted average
    const confidence = Math.round((keywordScore * 0.4) + (patternScore * 0.4) + (textLengthScore * 0.2));
    
    return Math.min(100, Math.max(0, confidence));
}

function calculateConfidenceBreakdown(text, classification) {
    const keywords = {
        'police': ['murder', 'rape', 'robbery', 'assault', 'drugs', 'weapon', 'gun', 'stabbing', 'shooting', 'kidnapping', 'theft', 'burglary'],
        'barangay': ['noise', 'dispute', 'neighbor', 'boundary', 'garbage', 'animal', 'parking', 'water', 'electricity', 'sanitation', 'ordinance', 'local']
    };
    
    const patterns = {
        'police': [
            /\b(shot|killed|stabbed|robbed|stolen)\b/i,
            /\b(drug|shabu|marijuana)\b/i,
            /\b(rape|molest|sexual assault)\b/i,
            /\b(weapon|gun|knife)\b/i
        ],
        'barangay': [
            /\b(noisy|loud|disturbance)\b/i,
            /\b(neighbor|boundary|fence)\b/i,
            /\b(garbage|trash|waste)\b/i,
            /\b(animal|dog|pet)\b/i
        ]
    };
    
    const lowerText = text.toLowerCase();
    const relevantKeywords = keywords[classification] || [];
    const relevantPatterns = patterns[classification] || [];
    
    // Calculate keyword matches
    let keywordMatches = 0;
    relevantKeywords.forEach(keyword => {
        if (lowerText.includes(keyword.toLowerCase())) {
            keywordMatches++;
        }
    });
    
    // Calculate pattern matches
    let patternMatches = 0;
    relevantPatterns.forEach(pattern => {
        if (pattern.test(text)) {
            patternMatches++;
        }
    });
    
    // Calculate scores
    const keywordScore = relevantKeywords.length > 0 ? Math.min(100, (keywordMatches / relevantKeywords.length) * 100) : 0;
    const patternScore = relevantPatterns.length > 0 ? Math.min(100, (patternMatches / relevantPatterns.length) * 100) : 0;
    const textLength = text.length;
    const contextScore = Math.min(100, (textLength / 500) * 100);
    
    // Jurisdiction score (combined)
    const jurisdictionScore = Math.round((keywordScore + patternScore + contextScore) / 3);
    
    return {
        keywordMatches: keywordMatches,
        patternMatches: patternMatches,
        keywordScore: Math.round(keywordScore),
        contextScore: Math.round(contextScore),
        patternScore: Math.round(patternScore),
        jurisdictionScore: jurisdictionScore
    };
}

// Show change log
async function showChangeLog(reportId) {
    try {
        const response = await fetch(`../../ajax/get_classification_logs.php?report_id=${reportId}`);
        const data = await response.json();
        
        let html = '';
        if (data && data.length > 0) {
            data.forEach(log => {
                const date = new Date(log.created_at);
                html += `
                    <div class="border-l-4 border-blue-500 pl-4 py-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-medium">${log.original_classification} → ${log.new_classification}</span>
                                <p class="text-sm text-gray-600 mt-1">${log.notes || 'No explanation provided'}</p>
                            </div>
                            <span class="text-xs text-gray-500">${date.toLocaleDateString()}</span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            Changed by: ${log.changed_by_name || 'System'}
                        </div>
                    </div>
                `;
            });
        } else {
            html = '<p class="text-gray-500 text-center py-4">No change history found for this report.</p>';
        }
        
        document.getElementById('changeLogContent').innerHTML = html;
        document.getElementById('changeLogModal').classList.remove('hidden');
        document.getElementById('changeLogModal').classList.add('flex');
        
    } catch (error) {
        console.error('Error loading change log:', error);
        document.getElementById('changeLogContent').innerHTML = '<p class="text-red-500 text-center py-4">Error loading change history.</p>';
        document.getElementById('changeLogModal').classList.remove('hidden');
        document.getElementById('changeLogModal').classList.add('flex');
    }
}

function closeChangeLogModal() {
    document.getElementById('changeLogModal').classList.add('hidden');
    document.getElementById('changeLogModal').classList.remove('flex');
}

// Close modals on click outside
document.getElementById('classificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeClassificationModal();
    }
});

document.getElementById('changeLogModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChangeLogModal();
    }
});

// Initialize filter buttons
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        if (btn.classList.contains('active')) {
            btn.classList.add('bg-blue-100', 'text-blue-700', 'border', 'border-blue-300');
        }
    });
});
</script>

<style>
.filter-btn {
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background-color: #f3f4f6;
}

.filter-btn.active {
    background-color: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

/* Dropdown styling */
select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1em;
    padding-right: 2.5rem;
}

select:focus {
    outline: 2px solid #4f46e5;
    outline-offset: 2px;
}

/* Modal scrolling */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
