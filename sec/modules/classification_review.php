<?php
// sec/modules/classification_review.php

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
        
        // Get current report details for logging
        $stmt = $conn->prepare("SELECT ai_classification, classification_override FROM reports WHERE id = :id");
        $stmt->execute([':id' => $report_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $original = $current['classification_override'] ?? $current['ai_classification'] ?? 'uncertain';
        
        // Update report
        $update_stmt = $conn->prepare("UPDATE reports SET 
            classification_override = :classification,
            override_notes = :notes,
            overridden_by = :user_id,
            overridden_at = NOW(),
            last_status_change = NOW()
            WHERE id = :id");
            
        $update_stmt->execute([
            ':classification' => $new_classification,
            ':notes' => $notes,
            ':user_id' => $_SESSION['user_id'],
            ':id' => $report_id
        ]);
        
        // Log to classification_logs
        $log_stmt = $conn->prepare("INSERT INTO classification_logs 
            (report_id, original_classification, new_classification, changed_by, notes) 
            VALUES (:report_id, :original, :new, :user_id, :notes)");
            
        $log_stmt->execute([
            ':report_id' => $report_id,
            ':original' => $original,
            ':new' => $new_classification,
            ':user_id' => $_SESSION['user_id'],
            ':notes' => $notes
        ]);
        
        $conn->commit();
        $success_message = "Report classification updated successfully.";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Error updating classification: " . $e->getMessage();
    }
}

// Fetch reports needing review or all reports
// We'll focus on Pending and Field Verification, maybe Assigned too.
// And we want to see AI classification vs Actual.

$query = "SELECT r.*, 
          u.first_name, u.last_name,
          rt.type_name as report_type,
          COALESCE(r.classification_override, r.ai_classification, 'uncertain') as current_jurisdiction
          FROM reports r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN report_types rt ON r.report_type_id = rt.id
          WHERE r.status IN ('pending', 'pending_field_verification', 'assigned', 'investigating')
          ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- UI Implementation -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Report Classification Review</h2>
            <p class="text-gray-500 text-sm">Review and correct AI-predicted jurisdiction</p>
        </div>
        
        <!-- Stats -->
        <div class="flex space-x-4">
            <div class="bg-blue-50 px-4 py-2 rounded-lg">
                <span class="block text-xs text-blue-600 font-semibold uppercase">Total Reports</span>
                <span class="text-lg font-bold text-blue-800"><?php echo count($reports); ?></span>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                    <th class="p-4 font-semibold">Report Info</th>
                    <th class="p-4 font-semibold">Description</th>
                    <th class="p-4 font-semibold">AI Prediction</th>
                    <th class="p-4 font-semibold">Current Status</th>
                    <th class="p-4 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($reports as $report): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="p-4 align-top">
                            <span class="block font-medium text-gray-900">#<?php echo htmlspecialchars($report['report_number']); ?></span>
                            <span class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></span>
                            <div class="mt-1">
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full border border-gray-200">
                                    <?php echo htmlspecialchars($report['report_type'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                        </td>
                        <td class="p-4 align-top max-w-xs">
                            <p class="text-sm text-gray-800 font-medium truncate"><?php echo htmlspecialchars($report['title']); ?></p>
                            <p class="text-xs text-gray-500 line-clamp-2 mt-1"><?php echo htmlspecialchars($report['description']); ?></p>
                        </td>
                        <td class="p-4 align-top">
                            <?php 
                                $ai_class = $report['ai_classification'] ?? 'Uncertain';
                                $ai_conf = $report['ai_confidence'] ?? 0;
                                $badge_color = 'bg-gray-100 text-gray-600';
                                if (strtolower($ai_class) == 'police') $badge_color = 'bg-red-100 text-red-700';
                                if (strtolower($ai_class) == 'barangay') $badge_color = 'bg-green-100 text-green-700';
                            ?>
                            <div class="flex flex-col space-y-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                    <i class="fas fa-robot mr-1"></i> <?php echo ucfirst($ai_class); ?>
                                </span>
                                <span class="text-xs text-gray-400">Confidence: <?php echo $ai_conf; ?>%</span>
                            </div>
                        </td>
                        <td class="p-4 align-top">
                            <?php 
                                $current = $report['current_jurisdiction'];
                                $is_overridden = !empty($report['classification_override']);
                            ?>
                            <div class="flex flex-col space-y-1">
                                <span class="font-medium text-sm <?php echo strtolower($current) == 'police' ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo ucfirst($current); ?> Matter
                                </span>
                                <?php if ($is_overridden): ?>
                                    <span class="text-xs text-amber-600"><i class="fas fa-edit mr-1"></i>Manually Edited</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4 align-top">
                            <button onclick="openClassificationModal(<?php echo htmlspecialchars(json_encode($report)); ?>)" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium hover:underline">
                                Review & Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-gray-500">
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

<!-- Modal -->
<div id="classificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden transform transition-all">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Review Classification</h3>
            <button onclick="closeClassificationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="report_id" id="modalReportId">
            <input type="hidden" name="update_classification" value="1">
            
            <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-bold text-blue-600 uppercase tracking-wide">AI Analysis</span>
                    <span class="text-xs text-blue-600" id="modalAiConfidence"></span>
                </div>
                <p class="text-lg font-bold text-gray-800 mb-1" id="modalAiPrediction"></p>
                <p class="text-sm text-gray-600" id="modalAiReasoning"></p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Jurisdiction Classification</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer relative">
                        <input type="radio" name="classification" value="barangay" class="peer sr-only" id="radioBarangay">
                        <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 transition-all text-center h-full">
                            <i class="fas fa-home text-2xl mb-2 text-gray-400 peer-checked:text-green-600"></i>
                            <div class="font-bold text-gray-700 peer-checked:text-green-800">Barangay Matter</div>
                            <div class="text-xs text-gray-500 mt-1">Local resolution</div>
                        </div>
                    </label>
                    
                    <label class="cursor-pointer relative">
                        <input type="radio" name="classification" value="police" class="peer sr-only" id="radioPolice">
                        <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 transition-all text-center h-full">
                            <i class="fas fa-shield-alt text-2xl mb-2 text-gray-400 peer-checked:text-red-600"></i>
                            <div class="font-bold text-gray-700 peer-checked:text-red-800">Police Matter</div>
                            <div class="text-xs text-gray-500 mt-1">Requires PNP turnover</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Change Notes / Reason</label>
                <textarea name="notes" id="modalNotes" rows="3" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="Explain why you are changing the classification..."></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeClassificationModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openClassificationModal(report) {
    document.getElementById('modalReportId').value = report.id;
    
    // Set AI info
    const aiClass = report.ai_classification || 'Uncertain';
    document.getElementById('modalAiPrediction').textContent = aiClass.charAt(0).toUpperCase() + aiClass.slice(1) + ' Matter';
    document.getElementById('modalAiConfidence').textContent = (report.ai_confidence || 0) + '% Confidence';
    
    // Select current classification
    const current = report.current_jurisdiction.toLowerCase();
    if (current === 'barangay') {
        document.getElementById('radioBarangay').checked = true;
    } else if (current === 'police') {
        document.getElementById('radioPolice').checked = true;
    }
    
    document.getElementById('modalNotes').value = report.override_notes || '';
    
    document.getElementById('classificationModal').classList.remove('hidden');
    document.getElementById('classificationModal').classList.add('flex');
}

function closeClassificationModal() {
    document.getElementById('classificationModal').classList.add('hidden');
    document.getElementById('classificationModal').classList.remove('flex');
}

// Close on click outside
document.getElementById('classificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeClassificationModal();
    }
});
</script>
