<?php
// sec/modules/classification_review.php - Enhanced Version

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    exit('Unauthorized');
}

// Database connection is expected to be available from secretary_dashboard.php
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getDbConnection();
}

// Handle classification override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_classification'])) {
    $report_id = $_POST['report_id'];
    $new_classification = $_POST['classification']; // 'barangay' or 'police'
    $notes = $_POST['notes'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Get current report details for logging and routing
        $stmt = $conn->prepare("SELECT r.*, u.id as user_id, u.email, u.phone 
                               FROM reports r 
                               LEFT JOIN users u ON r.user_id = u.id 
                               WHERE r.id = :id");
        $stmt->execute([':id' => $report_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $original = $current['classification_override'] ?? $current['ai_classification'] ?? 'uncertain';
        
        // Update report with new classification
        $update_stmt = $conn->prepare("UPDATE reports SET 
            classification_override = :classification,
            override_notes = :notes,
            overridden_by = :user_id,
            overridden_at = NOW(),
            last_status_change = NOW(),
            routing_updated = 1, -- Flag for routing system
            category = CASE 
                WHEN :classification = 'barangay' THEN 'Barangay Matter'
                WHEN :classification = 'police' THEN 'Police Matter'
                ELSE category
            END
            WHERE id = :id");
            
        $update_stmt->execute([
            ':classification' => $new_classification,
            ':notes' => $notes,
            ':user_id' => $_SESSION['user_id'],
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
            ':notes' => $notes
        ]);
        
        // Update routing flags for immediate queue reflection
        // This would trigger notifications or queue updates
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
        
        // Create notification for citizen about classification change
        if ($current['user_id']) {
            $notification_stmt = $conn->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, related_id, created_at) 
                VALUES (:user_id, 'Report Classification Updated', 
                        'Your report has been reclassified. New jurisdiction: ' || :classification, 
                        'classification_change', :report_id, NOW())
            ");
            $notification_stmt->execute([
                ':user_id' => $current['user_id'],
                ':classification' => $new_classification == 'barangay' ? 'Barangay Matter' : 'Police Matter',
                ':report_id' => $report_id
            ]);
        }
        
        $conn->commit();
        $success_message = "Report classification updated successfully! Changes are reflected immediately.";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Error updating classification: " . $e->getMessage();
    }
}

// Fetch reports needing review or all reports
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
                <h2 class="text-2xl font-bold text-gray-800">Report Classification Review</h2>
                <p class="text-gray-500 text-sm">Review and correct AI-predicted jurisdiction. Changes are immediately reflected in Citizen status and relevant user queues.</p>
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
                    <span class="block text-xs text-green-600 font-semibold uppercase">Already Reviewed</span>
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
                    <p class="text-sm mt-1">The classification has been updated and all relevant queues have been notified.</p>
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
                        <th class="p-4 font-semibold">Current Classification</th>
                        <th class="p-4 font-semibold">Queue Status</th>
                        <th class="p-4 font-semibold">Change Log</th>
                        <th class="p-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="reportsTable">
                    <?php foreach ($reports as $report): ?>
                        <?php 
                        $needs_review = empty($report['classification_override']);
                        $row_class = $needs_review ? 'bg-yellow-50 hover:bg-yellow-100' : 'hover:bg-gray-50';
                        ?>
                        <tr class="<?php echo $row_class; ?> transition-colors report-row" 
                            data-review-status="<?php echo $needs_review ? 'needs_review' : 'reviewed'; ?>">
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
                                        <i class="fas fa-robot mr-1"></i> <?php echo ucfirst($ai_class); ?> Matter
                                    </span>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min($ai_conf, 100); ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-400">Confidence: <?php echo $ai_conf; ?>%</span>
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
                                        <?php echo ucfirst($current); ?> Matter
                                    </span>
                                    <?php if ($is_overridden): ?>
                                        <span class="text-xs text-amber-600">
                                            <i class="fas fa-edit mr-1"></i>Manually Edited
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
                                    <button onclick="showChangeLog(<?php echo $report['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium hover:underline">
                                        <i class="fas fa-history mr-1"></i> <?php echo $report['change_count']; ?> change(s)
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No changes</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 align-top">
                                <button onclick="openClassificationModal(<?php echo htmlspecialchars(json_encode($report)); ?>)" 
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <i class="fas fa-edit mr-1"></i> Review & Edit
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
                                    <p class="text-sm mt-1">All reports have been classified.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Classification Modal -->
<div id="classificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden transform transition-all">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Review & Correct Classification</h3>
            <button onclick="closeClassificationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="report_id" id="modalReportId">
            <input type="hidden" name="update_classification" value="1">
            
            <!-- Report Info -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-semibold text-gray-700 mb-2">Report Information</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Report ID:</span>
                        <span class="font-medium ml-2" id="modalReportNumber"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Citizen:</span>
                        <span class="font-medium ml-2" id="modalCitizenName"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Date Filed:</span>
                        <span class="font-medium ml-2" id="modalDateFiled"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Current Status:</span>
                        <span class="font-medium ml-2" id="modalCurrentStatus"></span>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis -->
            <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-bold text-blue-600 uppercase tracking-wide">AI Analysis (Transformer Model)</span>
                    <span class="text-xs text-blue-600" id="modalAiConfidence"></span>
                </div>
                <p class="text-lg font-bold text-gray-800 mb-1" id="modalAiPrediction"></p>
                <p class="text-sm text-gray-600 mb-2">This prediction was generated by the AI model based on report content analysis.</p>
            </div>
            
            <!-- Classification Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Correct Jurisdiction Classification *</label>
                <p class="text-sm text-gray-600 mb-4">Changing this will immediately update the citizen's status and move the report to the appropriate queue.</p>
                
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer relative">
                        <input type="radio" name="classification" value="barangay" class="peer sr-only" id="radioBarangay">
                        <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 transition-all text-center h-full">
                            <i class="fas fa-home text-2xl mb-2 text-gray-400 peer-checked:text-green-600"></i>
                            <div class="font-bold text-gray-700 peer-checked:text-green-800">Barangay Matter</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <ul class="text-left mt-2 space-y-1">
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 text-xs mr-1 mt-0.5"></i>
                                        <span>Local dispute resolution</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 text-xs mr-1 mt-0.5"></i>
                                        <span>Lupon/Tanod assignment</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 text-xs mr-1 mt-0.5"></i>
                                        <span>Barangay hearing required</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </label>
                    
                    <label class="cursor-pointer relative">
                        <input type="radio" name="classification" value="police" class="peer sr-only" id="radioPolice">
                        <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 transition-all text-center h-full">
                            <i class="fas fa-shield-alt text-2xl mb-2 text-gray-400 peer-checked:text-red-600"></i>
                            <div class="font-bold text-gray-700 peer-checked:text-red-800">Police Matter</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <ul class="text-left mt-2 space-y-1">
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-red-500 text-xs mr-1 mt-0.5"></i>
                                        <span>Criminal offense</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-red-500 text-xs mr-1 mt-0.5"></i>
                                        <span>Requires police investigation</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-red-500 text-xs mr-1 mt-0.5"></i>
                                        <span>PNP turnover required</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Change Reason -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Correction *</label>
                <textarea name="notes" id="modalNotes" rows="3" required 
                          class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" 
                          placeholder="Explain why you are changing the classification. This will be logged and visible to supervisors."></textarea>
                <p class="text-xs text-gray-500 mt-1">This explanation will be saved in the change log and may be reviewed by supervisors.</p>
            </div>
            
            <!-- Immediate Effects Notice -->
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-yellow-800">Immediate Effects</h4>
                        <ul class="text-sm text-yellow-700 mt-1 space-y-1">
                            <li class="flex items-start">
                                <i class="fas fa-arrow-right text-xs mr-2 mt-0.5"></i>
                                <span>Citizen's status will be updated immediately</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-arrow-right text-xs mr-2 mt-0.5"></i>
                                <span>Report will be moved to appropriate officer queue</span>
                            </li>
                            <li class="fas fa-arrow-right text-xs mr-2 mt-0.5">
                                <span>All related users will be notified of the change</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeClassificationModal()" 
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-save mr-2"></i> Save & Update Classification
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
        
        <div class="p-6">
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

// Open classification modal
function openClassificationModal(report) {
    document.getElementById('modalReportId').value = report.id;
    
    // Set report info
    document.getElementById('modalReportNumber').textContent = report.report_number || '#' + report.id;
    document.getElementById('modalCitizenName').textContent = report.first_name + ' ' + report.last_name;
    document.getElementById('modalDateFiled').textContent = new Date(report.created_at).toLocaleDateString();
    document.getElementById('modalCurrentStatus').textContent = report.status.replace('_', ' ').toUpperCase();
    
    // Set AI info
    const aiClass = report.original_ai_prediction || 'Uncertain';
    document.getElementById('modalAiPrediction').textContent = aiClass.charAt(0).toUpperCase() + aiClass.slice(1) + ' Matter';
    document.getElementById('modalAiConfidence').textContent = (report.ai_confidence || 0) + '% Confidence';
    
    // Select current classification
    const current = report.current_jurisdiction.toLowerCase();
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
    
    document.getElementById('classificationModal').classList.remove('hidden');
    document.getElementById('classificationModal').classList.add('flex');
}

function closeClassificationModal() {
    document.getElementById('classificationModal').classList.add('hidden');
    document.getElementById('classificationModal').classList.remove('flex');
}

// Show change log
async function showChangeLog(reportId) {
    try {
        const response = await fetch(`../../ajax/get_classification_logs.php?report_id=${reportId}`);
        const data = await response.json();
        
        let html = '';
        if (data.length > 0) {
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
</style>