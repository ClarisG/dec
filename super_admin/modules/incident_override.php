<?php
// super_admin/modules/incident_override.php

// Get AI classified reports that might need override
$override_query = "
    SELECT r.*, ai.predicted_jurisdiction, ai.confidence_score, ai.reasoning,
           rt.type_name, rt.jurisdiction as original_jurisdiction,
           u.first_name, u.last_name, u.barangay
    FROM reports r
    LEFT JOIN ai_classification_logs ai ON r.id = ai.report_id
    LEFT JOIN report_types rt ON r.report_type_id = rt.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE ai.predicted_jurisdiction IS NOT NULL
    AND (r.status = 'pending' OR r.status = 'pending_field_verification')
    ORDER BY ai.created_at DESC
    LIMIT 20
";
$override_stmt = $conn->prepare($override_query);
$override_stmt->execute();
$override_reports = $override_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get override history
$history_query = "
    SELECT ao.*, r.report_number, u.first_name, u.last_name,
           ao.original_jurisdiction, ao.new_jurisdiction
    FROM ai_override_logs ao
    LEFT JOIN reports r ON ao.report_id = r.id
    LEFT JOIN users u ON ao.overridden_by = u.id
    ORDER BY ao.overridden_at DESC
    LIMIT 10
";
?>
<div class="space-y-6">
    <!-- Incident Override Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Incident Classification Override</h3>
            <p class="text-sm text-gray-600">Manually reclassify any incident and override AI suggestions</p>
        </div>
        <div class="text-sm text-gray-600">
            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full">
                <i class="fas fa-robot mr-1"></i>AI Confidence Threshold: 0.7
            </span>
        </div>
    </div>

    <!-- AI Classification Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">AI Classifications</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($override_reports); ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-robot text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Total classified by AI
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">High Confidence</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?php 
                        $high_conf = array_filter($override_reports, function($r) {
                            return $r['confidence_score'] >= 0.8;
                        });
                        echo count($high_conf);
                        ?>
                    </p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                >80% confidence
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Low Confidence</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?php 
                        $low_conf = array_filter($override_reports, function($r) {
                            return $r['confidence_score'] < 0.7;
                        });
                        echo count($low_conf);
                        ?>
                    </p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <70% confidence
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Manual Overrides</p>
                    <p class="text-2xl font-bold text-gray-800">15</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                This month
            </div>
        </div>
    </div>

    <!-- AI Classified Reports -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-md font-medium text-gray-800">Recent AI Classifications</h4>
                <div class="flex space-x-2">
                    <select class="p-2 border border-gray-300 rounded-lg text-sm">
                        <option>All Confidence Levels</option>
                        <option>High Confidence (>0.8)</option>
                        <option>Medium Confidence (0.7-0.8)</option>
                        <option>Low Confidence (<0.7)</option>
                    </select>
                    <select class="p-2 border border-gray-300 rounded-lg text-sm">
                        <option>All Jurisdictions</option>
                        <option>Barangay</option>
                        <option>Police</option>
                        <option>Uncertain</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AI Prediction</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AI Reasoning</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($override_reports as $report): 
                            $confidence = floatval($report['confidence_score']);
                            $confidence_class = $confidence >= 0.8 ? 'bg-green-100 text-green-800' : 
                                              ($confidence >= 0.7 ? 'bg-yellow-100 text-yellow-800' : 
                                              'bg-red-100 text-red-800');
                            
                            $prediction_class = $report['predicted_jurisdiction'] == 'police' ? 
                                               'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
                            $current_class = $report['original_jurisdiction'] == 'police' ? 
                                            'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($report['report_number']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($report['type_name']); ?>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $prediction_class; ?>">
                                    <?php echo htmlspecialchars($report['predicted_jurisdiction']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div class="w-16 h-2 bg-gray-200 rounded-full mr-2 overflow-hidden">
                                        <div class="h-full <?php echo str_replace('text-', 'bg-', $confidence_class); ?>" 
                                             style="width: <?php echo ($confidence * 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium <?php echo str_replace('100', '800', $confidence_class); ?>">
                                        <?php echo number_format($confidence * 100, 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars(substr($report['reasoning'], 0, 80)); ?>...
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $current_class; ?>">
                                    <?php echo htmlspecialchars($report['original_jurisdiction']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex space-x-2">
                                    <?php if ($report['predicted_jurisdiction'] != $report['original_jurisdiction']): ?>
                                    <button onclick="overrideClassification(<?php echo $report['id']; ?>, '<?php echo $report['predicted_jurisdiction']; ?>')" 
                                            class="px-3 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                                        Accept AI
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="showOverrideModal(<?php echo $report['id']; ?>)" 
                                            class="px-3 py-1 border border-gray-300 text-gray-700 text-xs rounded hover:bg-gray-50">
                                        Manual Override
                                    </button>
                                    
                                    <button onclick="viewReportDetails(<?php echo $report['id']; ?>)" 
                                            class="px-3 py-1 border border-gray-300 text-gray-700 text-xs rounded hover:bg-gray-50">
                                        Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Override History -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-medium text-gray-800 mb-4">Recent Override History</h4>
        <div class="space-y-3">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <div class="p-2 bg-purple-100 rounded-lg mr-3">
                    <i class="fas fa-exchange-alt text-purple-600"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-700">
                        Report <span class="font-medium">RPT-20260115-ABC123</span> jurisdiction changed from 
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">Barangay</span> to 
                        <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded">Police</span>
                    </p>
                    <p class="text-xs text-gray-500">
                        Overridden by Admin User • Today at 14:30
                    </p>
                </div>
                <button class="text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-undo"></i> Revert
                </button>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Override Modal -->
<div id="overrideModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Manual Jurisdiction Override</h3>
                <button onclick="closeOverrideModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="overrideForm">
                <input type="hidden" id="overrideReportId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Details</label>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-700" id="modalReportNumber"></p>
                        <p class="text-xs text-gray-500" id="modalReportTitle"></p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Classification</label>
                    <div class="flex space-x-4">
                        <div class="flex-1 p-3 border border-gray-300 rounded-lg">
                            <div class="text-sm font-medium text-gray-700">AI Prediction</div>
                            <div class="text-sm text-gray-500" id="modalAIPrediction"></div>
                            <div class="text-xs text-gray-400" id="modalAIConfidence"></div>
                        </div>
                        <div class="flex-1 p-3 border border-gray-300 rounded-lg">
                            <div class="text-sm font-medium text-gray-700">Current</div>
                            <div class="text-sm text-gray-500" id="modalCurrentJurisdiction"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select New Jurisdiction *</label>
                    <select id="newJurisdiction" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select jurisdiction</option>
                        <option value="barangay">Barangay</option>
                        <option value="police">Police</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Override Reason *</label>
                    <textarea id="overrideReason" required rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg" 
                              placeholder="Explain why you are overriding the AI classification..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeOverrideModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Apply Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showOverrideModal(reportId) {
    // In real implementation, fetch report details via AJAX
    document.getElementById('overrideReportId').value = reportId;
    document.getElementById('modalReportNumber').textContent = 'Report #RPT-' + reportId;
    document.getElementById('modalReportTitle').textContent = 'Sample Report Title';
    document.getElementById('modalAIPrediction').textContent = 'Police (predicted)';
    document.getElementById('modalAIConfidence').textContent = 'Confidence: 72%';
    document.getElementById('modalCurrentJurisdiction').textContent = 'Barangay (current)';
    
    document.getElementById('overrideModal').classList.remove('hidden');
    document.getElementById('overrideModal').classList.add('flex');
}

function closeOverrideModal() {
    document.getElementById('overrideModal').classList.add('hidden');
    document.getElementById('overrideModal').classList.remove('flex');
}

function overrideClassification(reportId, jurisdiction) {
    if (confirm('Accept AI prediction and change jurisdiction to ' + jurisdiction + '?')) {
        // Implement override functionality
        alert('Jurisdiction updated for report #' + reportId);
    }
}

function viewReportDetails(reportId) {
    window.open('../ajax/get_report_details.php?id=' + reportId, '_blank');
}
</script>