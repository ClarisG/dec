<?php
// admin/modules/classification.php - INCIDENT CLASSIFICATION MODULE

// Get classification rules
$classification_query = "SELECT * FROM report_types ORDER BY category, type_name";
$classification_stmt = $conn->prepare($classification_query);
$classification_stmt->execute();
$classification_rules = $classification_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Incident Type Rules -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Incident Type Classification Rules</h2>
            <button onclick="showAddRuleModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                <i class="fas fa-plus mr-2"></i>Add Rule
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Incident Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keywords</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jurisdiction</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="rulesTableBody" class="bg-white divide-y divide-gray-200">
                    <?php foreach($classification_rules as $rule): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($rule['type_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="capitalize"><?php echo htmlspecialchars($rule['category']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php if ($rule['keywords']): ?>
                                    <?php 
                                    $keywords = explode(',', $rule['keywords']);
                                    foreach(array_slice($keywords, 0, 3) as $keyword): ?>
                                        <span class="keyword-tag"><?php echo trim($keyword); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($keywords) > 3): ?>
                                        <span class="keyword-tag">+<?php echo count($keywords) - 3; ?> more</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">No keywords</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge <?php echo $rule['jurisdiction'] === 'police' ? 'status-warning' : 'status-success'; ?>">
                                    <?php echo htmlspecialchars($rule['jurisdiction']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-500 capitalize"><?php echo $rule['severity_level']; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="editRule(<?php echo $rule['id']; ?>)" class="text-purple-600 hover:text-purple-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteRule(<?php echo $rule['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- AI Classification Logs -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Recent AI Classifications</h3>
        
        <?php
        $ai_logs_query = "SELECT acl.*, r.report_number, r.title 
                         FROM ai_classification_logs acl 
                         LEFT JOIN reports r ON acl.report_id = r.id 
                         ORDER BY acl.created_at DESC 
                         LIMIT 5";
        $ai_logs_stmt = $conn->prepare($ai_logs_query);
        $ai_logs_stmt->execute();
        $ai_logs = $ai_logs_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="space-y-4">
            <?php if (!empty($ai_logs)): ?>
                <?php foreach($ai_logs as $log): ?>
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium text-gray-800">Report: <?php echo htmlspecialchars($log['report_number']); ?></span>
                                <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($log['title']); ?></p>
                            </div>
                            <span class="status-badge <?php echo $log['predicted_jurisdiction'] === 'police' ? 'status-warning' : 
                                                         ($log['predicted_jurisdiction'] === 'uncertain' ? 'status-pending' : 'status-success'); ?>">
                                <?php echo ucfirst($log['predicted_jurisdiction']); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <div>
                                Confidence: <span class="font-bold"><?php echo round($log['confidence_score'] * 100, 1); ?>%</span>
                            </div>
                            <div>
                                <?php echo date('M d, H:i', strtotime($log['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-robot text-3xl mb-2"></i>
                    <p>No AI classification logs found</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-6 text-center">
            <a href="?module=audit&filter=ai" class="text-purple-600 hover:text-purple-800 font-medium">
                View All Classification Logs →
            </a>
        </div>
    </div>
</div>

<!-- Add/Edit Rule Modal -->
<div id="ruleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-xl font-bold text-gray-800">Add Classification Rule</h3>
            <button onclick="closeRuleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="ruleForm" method="POST" action="handlers/save_classification_rule.php" onsubmit="return handleSaveRule(event)">
            <input type="hidden" id="ruleId" name="id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Incident Type Name</label>
                    <input type="text" name="type_name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" required 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="incident">Incident</option>
                        <option value="complaint">Complaint</option>
                        <option value="blotter">Blotter</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Keywords (comma-separated)</label>
                <textarea name="keywords" rows="3" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="theft, rob, nakaw, steal..."></textarea>
                <p class="text-sm text-gray-500 mt-1">These keywords will be used by the AI model for classification</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jurisdiction</label>
                    <select name="jurisdiction" required 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="barangay">Barangay</option>
                        <option value="police">Police</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Severity Level</label>
                    <select name="severity_level" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRuleModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Save Rule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleSaveRule(e){
    // Ensure required fields exist and submit via fetch to avoid full-page reload if desired
    const form = document.getElementById('ruleForm');
    const fd = new FormData(form);
    // Basic client validation
    if(!fd.get('type_name') || !fd.get('category') || !fd.get('jurisdiction')){
        alert('Please complete required fields');
        return false;
    }
    // Submit via fetch to the handler so "Save Rule" reliably works
    fetch('handlers/save_classification_rule.php', { method: 'POST', body: fd })
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            // Reload to reflect new rule
            window.location.reload();
        }else{
            alert('Error: ' + (data.message || 'Failed to save rule'));
        }
    }).catch(err=>{
        alert('Network error while saving rule');
        console.error(err);
    });
    e.preventDefault();
    return false;
}

function showAddRuleModal() {
    document.getElementById('modalTitle').textContent = 'Add Classification Rule';
    document.getElementById('ruleId').value = '';
    document.getElementById('ruleForm').reset();
    document.getElementById('ruleModal').classList.remove('hidden');
    document.getElementById('ruleModal').classList.add('flex');
}

function editRule(ruleId) {
    // Fetch rule data via AJAX
    fetch(`handlers/get_classification_rule.php?id=${ruleId}`)
        .then(response => response.json())
        .then(rule => {
            document.getElementById('modalTitle').textContent = 'Edit Classification Rule';
            document.getElementById('ruleId').value = rule.id;
            document.getElementById('ruleForm').type_name.value = rule.type_name;
            document.getElementById('ruleForm').category.value = rule.category;
            document.getElementById('ruleForm').keywords.value = rule.keywords;
            document.getElementById('ruleForm').jurisdiction.value = rule.jurisdiction;
            document.getElementById('ruleForm').severity_level.value = rule.severity_level;
            
            document.getElementById('ruleModal').classList.remove('hidden');
            document.getElementById('ruleModal').classList.add('flex');
        });
}

function deleteRule(ruleId) {
    if (confirm('Are you sure you want to delete this classification rule?')) {
        fetch('handlers/delete_classification_rule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: ruleId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function closeRuleModal() {
    document.getElementById('ruleModal').classList.add('hidden');
    document.getElementById('ruleModal').classList.remove('flex');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('ruleModal');
    if (event.target == modal) {
        closeRuleModal();
    }
}
</script>