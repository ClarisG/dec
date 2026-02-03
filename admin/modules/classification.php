<?php
// admin/modules/classification.php - INCIDENT CLASSIFICATION MODULE

// Get classification rules
$classification_query = "SELECT * FROM report_types ORDER BY category, type_name";
$classification_stmt = $conn->prepare($classification_query);
$classification_stmt->execute();
$classification_rules = $classification_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for saving rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rule'])) {
    try {
        $type_name = $_POST['type_name'];
        $category = $_POST['category'];
        $keywords = $_POST['keywords'];
        $jurisdiction = $_POST['jurisdiction'];
        $severity_level = $_POST['severity_level'];
        $rule_id = $_POST['rule_id'] ?? null;
        
        if ($rule_id) {
            // Update existing rule
            $update_query = "UPDATE report_types SET 
                            type_name = :type_name,
                            category = :category,
                            keywords = :keywords,
                            jurisdiction = :jurisdiction,
                            severity_level = :severity_level
                            WHERE id = :id";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([
                ':type_name' => $type_name,
                ':category' => $category,
                ':keywords' => $keywords,
                ':jurisdiction' => $jurisdiction,
                ':severity_level' => $severity_level,
                ':id' => $rule_id
            ]);
            
            $_SESSION['success'] = "Rule updated successfully!";
        } else {
            // Insert new rule
            $insert_query = "INSERT INTO report_types 
                            (type_name, category, keywords, jurisdiction, severity_level)
                            VALUES (:type_name, :category, :keywords, :jurisdiction, :severity_level)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([
                ':type_name' => $type_name,
                ':category' => $category,
                ':keywords' => $keywords,
                ':jurisdiction' => $jurisdiction,
                ':severity_level' => $severity_level
            ]);
            
            $_SESSION['success'] = "Rule added successfully!";
        }
        
        header("Location: ?module=classification");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error saving rule: " . $e->getMessage();
    }
}

// Handle delete rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rule'])) {
    try {
        $rule_id = $_POST['rule_id'];
        $delete_query = "DELETE FROM report_types WHERE id = :id";
        $stmt = $conn->prepare($delete_query);
        $stmt->execute([':id' => $rule_id]);
        
        $_SESSION['success'] = "Rule deleted successfully!";
        header("Location: ?module=classification");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting rule: " . $e->getMessage();
    }
}

// Get threshold configuration
$threshold_query = "SELECT config_value FROM system_config WHERE config_key = 'classification_threshold'";
$threshold_stmt = $conn->prepare($threshold_query);
$threshold_stmt->execute();
$threshold = $threshold_stmt->fetchColumn() ?: 0.7;
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
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
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
                                        <span class="inline-block bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs mr-1 mb-1">
                                            <?php echo trim($keyword); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($keywords) > 3): ?>
                                        <span class="inline-block bg-gray-200 text-gray-600 px-2 py-1 rounded text-xs">
                                            +<?php echo count($keywords) - 3; ?> more
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">No keywords</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $rule['jurisdiction'] === 'police' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($rule['jurisdiction'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-500 capitalize"><?php echo $rule['severity_level']; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="editRule(<?php echo $rule['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900 mr-3 px-2 py-1 hover:bg-purple-50 rounded">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteRule(<?php echo $rule['id']; ?>)" 
                                        class="text-red-600 hover:text-red-900 px-2 py-1 hover:bg-red-50 rounded">
                                    <i class="fas fa-trash"></i> Delete
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
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $log['predicted_jurisdiction'] === 'police' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($log['predicted_jurisdiction'] === 'uncertain' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'); ?>">
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
        
        <form id="ruleForm" method="POST" action="">
            <input type="hidden" id="ruleId" name="rule_id" value="">
            <input type="hidden" name="save_rule" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Incident Type Name *</label>
                    <input type="text" name="type_name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                           placeholder="e.g., Theft, Assault, etc.">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select name="category" required 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Select Category</option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jurisdiction *</label>
                    <select name="jurisdiction" required 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Select Jurisdiction</option>
                        <option value="barangay">Barangay</option>
                        <option value="police">Police</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Severity Level *</label>
                    <select name="severity_level" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Select Severity</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
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
function showAddRuleModal() {
    document.getElementById('modalTitle').textContent = 'Add Classification Rule';
    document.getElementById('ruleId').value = '';
    document.getElementById('ruleForm').reset();
    document.getElementById('ruleForm').type_name.value = '';
    document.getElementById('ruleForm').category.value = '';
    document.getElementById('ruleForm').keywords.value = '';
    document.getElementById('ruleForm').jurisdiction.value = '';
    document.getElementById('ruleForm').severity_level.value = '';
    document.getElementById('ruleModal').classList.remove('hidden');
    document.getElementById('ruleModal').classList.add('flex');
}

function editRule(ruleId) {
    fetch(`ajax/get_classification_rule.php?id=${ruleId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(rule => {
            document.getElementById('modalTitle').textContent = 'Edit Classification Rule';
            document.getElementById('ruleId').value = rule.id;
            document.querySelector('[name="type_name"]').value = rule.type_name || '';
            document.querySelector('[name="category"]').value = rule.category || '';
            document.querySelector('[name="keywords"]').value = rule.keywords || '';
            document.querySelector('[name="jurisdiction"]').value = rule.jurisdiction || '';
            document.querySelector('[name="severity_level"]').value = rule.severity_level || '';
            
            document.getElementById('ruleModal').classList.remove('hidden');
            document.getElementById('ruleModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error fetching rule:', error);
            alert('Error loading rule details. Please try again.');
        });
}

function deleteRule(ruleId) {
    if (confirm('Are you sure you want to delete this classification rule?')) {
        const formData = new FormData();
        formData.append('delete_rule', '1');
        formData.append('rule_id', ruleId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                location.reload();
            } else {
                throw new Error('Network response was not ok');
            }
        })
        .catch(error => {
            console.error('Error deleting rule:', error);
            alert('Error deleting rule. Please try again.');
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

// Handle form submission
document.getElementById('ruleForm').addEventListener('submit', function(e) {
    // Validation is already handled by required attributes
});
</script>