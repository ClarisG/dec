<?php
// sec/modules/classification_review.php - Report Classification Review & Correction
session_start();
require_once '../../config/database.php';

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Location: ../../index.php');
    exit();
}

// Get AI classification suggestions
try {
    // Fetch recent reports with AI classifications for review
    $query = "SELECT r.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
                     r.ai_classification,
                     r.ai_confidence,
                     r.classification_override,
                     (SELECT filename FROM report_attachments WHERE report_id = r.id LIMIT 1) as sample_attachment
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.status = 'pending'
              AND r.ai_classification IS NOT NULL
              AND r.classification_override IS NULL
              ORDER BY r.created_at DESC
              LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!-- Report Classification Review & Correction Module -->
<div class="space-y-8">
    <div class="glass-card rounded-xl p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-brain mr-3 text-purple-600"></i>
            AI Classification Review & Correction
        </h2>
        
        <?php if (isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- AI Confidence Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-robot text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">AI Pending Review</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo count($reports ?? []); ?> Reports
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">High Confidence AI</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $high_confidence = 0;
                            foreach ($reports as $report) {
                                if ($report['ai_confidence'] > 80) $high_confidence++;
                            }
                            echo $high_confidence;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Low Confidence AI</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $low_confidence = 0;
                            foreach ($reports as $report) {
                                if ($report['ai_confidence'] < 60) $low_confidence++;
                            }
                            echo $low_confidence;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Classification Rules -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-balance-scale mr-2 text-blue-600"></i>
                Classification Guidelines
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 border-l-4 border-green-500 bg-green-50">
                    <h4 class="font-bold text-gray-800 mb-2">Barangay Matters</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Small claims & disputes</li>
                        <li>• Neighborhood conflicts</li>
                        <li>• Noise complaints</li>
                        <li>• Property boundary issues</li>
                        <li>• Minor altercations</li>
                    </ul>
                </div>
                <div class="p-4 border-l-4 border-red-500 bg-red-50">
                    <h4 class="font-bold text-gray-800 mb-2">Police Matters</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Criminal offenses (theft, assault)</li>
                        <li>• Drug-related incidents</li>
                        <li>• VAWC cases (RA 9262)</li>
                        <li>• Cases with weapons involved</li>
                        <li>• Felonies under RPC</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Reports for Review -->
        <div class="space-y-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Reports Requiring Classification Review</h3>
            
            <?php if (empty($reports)): ?>
            <div class="text-center py-12">
                <i class="fas fa-check-circle text-green-400 text-5xl mb-4"></i>
                <h4 class="text-xl font-bold text-gray-700 mb-2">All Caught Up!</h4>
                <p class="text-gray-600">No reports require classification review at this time.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Case #</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Title</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">AI Classification</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Confidence</th>
                            <th class="py-3 px-4 text-left text-gray-600 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr class="hover:bg-gray-50 border-b">
                            <td class="py-3 px-4">
                                <span class="font-medium">#<?php echo $report['id']; ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($report['title']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($report['complainant_name']); ?></p>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <?php
                                $ai_class = $report['ai_classification'];
                                $badge_color = ($ai_class === 'Barangay Matter') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                    <?php echo htmlspecialchars($ai_class); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" 
                                             style="width: <?php echo $report['ai_confidence']; ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium"><?php echo $report['ai_confidence']; ?>%</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-2">
                                    <button onclick="reviewClassification(<?php echo $report['id']; ?>, '<?php echo $ai_class; ?>')"
                                            class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                        <i class="fas fa-edit mr-1"></i> Review
                                    </button>
                                    <button onclick="viewReportDetails(<?php echo $report['id']; ?>)"
                                            class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Classification Review Modal -->
<div id="classificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Classification Review</h3>
            <button onclick="closeClassificationModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="classificationContent">
            <!-- Content loaded via AJAX -->
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeClassificationModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="saveClassification()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i> Save Classification
            </button>
        </div>
    </div>
</div>

<script>
let currentReportId = null;

function reviewClassification(reportId, currentClassification) {
    currentReportId = reportId;
    
    const modal = document.getElementById('classificationModal');
    const content = document.getElementById('classificationContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading report details...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    fetch(`../../handlers/get_report_for_review.php?id=${reportId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading report</p>
                </div>
            `;
        });
}

function closeClassificationModal() {
    document.getElementById('classificationModal').classList.add('hidden');
    document.getElementById('classificationModal').classList.remove('flex');
    currentReportId = null;
}

function saveClassification() {
    const classification = document.querySelector('input[name="classification"]:checked')?.value;
    const notes = document.getElementById('classificationNotes')?.value || '';
    
    if (!classification) {
        alert('Please select a classification');
        return;
    }
    
    if (!confirm('Are you sure you want to update the classification?')) return;
    
    const formData = new FormData();
    formData.append('report_id', currentReportId);
    formData.append('classification', classification);
    formData.append('notes', notes);
    
    fetch('../../handlers/update_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Classification updated successfully!');
            closeClassificationModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function viewReportDetails(reportId) {
    window.location.href = `?module=case&action=view&id=${reportId}`;
}
</script>
<?php $conn = null; ?>