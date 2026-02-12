<?php
// admin/modules/classification.php - INCIDENT CLASSIFICATION MODULE

// Pagination and search
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$q = trim($_GET['q'] ?? '');

$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE type_name LIKE :q OR category LIKE :q2";
    $params[':q'] = "%$q%";
    $params[':q2'] = "%$q%";
}

// Count total
$count_sql = "SELECT COUNT(*) FROM report_types $where";
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$count_stmt->execute();
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

// Get paginated rules
$limit = (int)$per_page;
$off = (int)$offset;
$classification_sql = "SELECT * FROM report_types $where ORDER BY category, type_name LIMIT $limit OFFSET $off";
$classification_stmt = $conn->prepare($classification_sql);
foreach ($params as $key => $value) {
    $classification_stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$classification_stmt->execute();
$classification_rules = $classification_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for saving rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rule'])) {
    try {
        $type_name = $_POST['type_name'];
        $category = $_POST['category'];
        $keywords = $_POST['keywords'];
        $jurisdiction = $_POST['jurisdiction'];
        $rule_id = $_POST['rule_id'] ?? null;
        
        if ($rule_id) {
            // Update existing rule
            $update_query = "UPDATE report_types SET 
                            type_name = :type_name,
                            category = :category,
                            keywords = :keywords,
                            jurisdiction = :jurisdiction
                            WHERE id = :id";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([
                ':type_name' => $type_name,
                ':category' => $category,
                ':keywords' => $keywords,
                ':jurisdiction' => $jurisdiction,
                ':id' => $rule_id
            ]);
            
            $_SESSION['success'] = "Rule updated successfully!";
        } else {
            // Insert new rule
            $insert_query = "INSERT INTO report_types 
                            (type_name, category, keywords, jurisdiction)
                            VALUES (:type_name, :category, :keywords, :jurisdiction)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([
                ':type_name' => $type_name,
                ':category' => $category,
                ':keywords' => $keywords,
                ':jurisdiction' => $jurisdiction
            ]);
            
            $_SESSION['success'] = "Rule added successfully!";
        }
        
        // Preserve search query in redirect
        $redirect_url = "?module=classification";
        if (!empty($q)) {
            $redirect_url .= "&q=" . urlencode($q);
        }
        if ($page > 1) {
            $redirect_url .= "&page=" . $page;
        }
        
        header("Location: $redirect_url");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error saving rule: " . $e->getMessage();
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
        <div class="flex items-center justify-between mb-4 gap-3">
            <form method="GET" action="" class="flex items-center flex-1 max-w-lg">
                <input type="hidden" name="module" value="classification">
                <div class="relative flex-1">
                    <input type="text" name="q" id="searchInput" value="<?php echo htmlspecialchars($q ?? ''); ?>" placeholder="Search type or category..." class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"/>
                    <span class="absolute left-2 top-2.5 text-gray-400"><i class="fas fa-search"></i></span>
                </div>
                <button type="submit" class="ml-2 px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">Search</button>
                <?php if (!empty($q)): ?>
                    <a href="?module=classification" class="ml-2 px-3 py-2 border border-gray-300 rounded-lg text-sm text-red-600 hover:bg-red-50">Clear</a>
                <?php endif; ?>
            </form>
            <button onclick="showAddRuleModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm whitespace-nowrap">
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
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Incident Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keywords</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Jurisdiction</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Actions</th>
                    </tr>
                </thead>
                <tbody id="rulesTableBody" class="bg-white divide-y divide-gray-200">
                    <?php if (empty($classification_rules)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                <p>No classification rules found<?php echo !empty($q) ? ' for "' . htmlspecialchars($q) . '"' : ''; ?></p>
                                <?php if (!empty($q)): ?>
                                    <a href="?module=classification" class="mt-2 inline-block text-purple-600 hover:text-purple-800">Clear search</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($classification_rules as $rule): ?>
                            <tr class="hover:bg-gray-50" data-rule-id="<?php echo $rule['id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($rule['type_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="capitalize"><?php echo htmlspecialchars($rule['category']); ?></span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500">
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
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                                        <?php echo $rule['jurisdiction'] === 'police' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($rule['jurisdiction'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="editRule(<?php echo $rule['id']; ?>)" 
                                            class="text-purple-600 hover:text-purple-900 px-2 py-1 hover:bg-purple-50 rounded edit-rule-btn"
                                            data-rule-id="<?php echo $rule['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between mt-4">
            <div class="text-sm text-gray-600">
                Showing <?php echo $total_records ? (min($per_page, $total_records - $offset)) : 0; ?> of <?php echo $total_records; ?> entries
            </div>
            <div class="flex items-center gap-1">
                <?php 
                $base = '?module=classification'.(($q??'')!==''?'&q='.urlencode($q):'');
                $mk = function($p) use ($base){ return $base.'&page='.$p; };
                ?>
                <a class="px-2 py-1 border rounded <?php echo $page<=1?'opacity-50 pointer-events-none':''; ?>" href="<?php echo $mk(1); ?>">First</a>
                <a class="px-2 py-1 border rounded <?php echo $page<=1?'opacity-50 pointer-events-none':''; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
                <?php 
                    $start = max(1, $page-2); $end = min($total_pages, $page+2);
                    for($p=$start;$p<=$end;$p++):
                ?>
                    <a class="px-3 py-1 border rounded <?php echo $p==$page?'bg-purple-600 text-white border-purple-600':'bg-white'; ?>" href="<?php echo $mk($p); ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
                <a class="px-2 py-1 border rounded <?php echo $page>=$total_pages?'opacity-50 pointer-events-none':''; ?>" href="<?php echo $mk(min($total_pages,$page+1)); ?>">Next</a>
                <a class="px-2 py-1 border rounded <?php echo $page>=$total_pages?'opacity-50 pointer-events-none':''; ?>" href="<?php echo $mk($total_pages); ?>">Last</a>
            </div>
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
            
            <!-- Preserve search and pagination in form -->
            <?php if (!empty($q)): ?>
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
            <?php endif; ?>
            <?php if ($page > 1): ?>
                <input type="hidden" name="page" value="<?php echo $page; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Incident Type Name *</label>
                    <input type="text" name="type_name" id="type_name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                           placeholder="e.g., Theft, Assault, etc.">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select name="category" id="category" required 
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
                <textarea name="keywords" id="keywords" rows="3" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="theft, rob, nakaw, steal..."></textarea>
                <p class="text-sm text-gray-500 mt-1">These keywords will be used by the AI model for classification</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jurisdiction *</label>
                    <select name="jurisdiction" id="jurisdiction" required 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Select Jurisdiction</option>
                        <option value="barangay">Barangay</option>
                        <option value="police">Police</option>
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
document.addEventListener('DOMContentLoaded', function() {
    // Store rule data in JavaScript object for quick access
    const ruleData = {};
    <?php foreach($classification_rules as $rule): ?>
    ruleData[<?php echo $rule['id']; ?>] = {
        type_name: <?php echo json_encode($rule['type_name']); ?>,
        category: <?php echo json_encode($rule['category']); ?>,
        keywords: <?php echo json_encode($rule['keywords']); ?>,
        jurisdiction: <?php echo json_encode($rule['jurisdiction']); ?>
    };
    <?php endforeach; ?>

    // Store current page and search query for form preservation
    const currentPage = <?php echo $page; ?>;
    const currentQuery = <?php echo json_encode($q); ?>;

    window.showAddRuleModal = function() {
        document.getElementById('modalTitle').textContent = 'Add Classification Rule';
        document.getElementById('ruleForm').reset();
        document.getElementById('ruleId').value = '';
        
        // Reset selects to default state
        const categorySelect = document.getElementById('category');
        const jurisdictionSelect = document.getElementById('jurisdiction');
        if (categorySelect) categorySelect.selectedIndex = 0;
        if (jurisdictionSelect) jurisdictionSelect.selectedIndex = 0;
        
        document.getElementById('ruleModal').classList.remove('hidden');
        document.getElementById('ruleModal').classList.add('flex');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }

    window.editRule = function(ruleId) {
        if (ruleData[ruleId]) {
            const rule = ruleData[ruleId];
            document.getElementById('modalTitle').textContent = 'Edit Classification Rule';
            document.getElementById('ruleId').value = ruleId;
            document.getElementById('type_name').value = rule.type_name || '';
            document.getElementById('keywords').value = rule.keywords || '';
            
            // Set category select
            const categorySelect = document.getElementById('category');
            if (categorySelect) {
                for (let i = 0; i < categorySelect.options.length; i++) {
                    if (categorySelect.options[i].value === rule.category) {
                        categorySelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Set jurisdiction select
            const jurisdictionSelect = document.getElementById('jurisdiction');
            if (jurisdictionSelect) {
                for (let i = 0; i < jurisdictionSelect.options.length; i++) {
                    if (jurisdictionSelect.options[i].value === rule.jurisdiction) {
                        jurisdictionSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            document.getElementById('ruleModal').classList.remove('hidden');
            document.getElementById('ruleModal').classList.add('flex');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        } else {
            console.warn('Rule data not found for ID:', ruleId);
            alert('Error loading rule data. Please refresh the page and try again.');
        }
    }

    window.closeRuleModal = function() {
        document.getElementById('ruleModal').classList.add('hidden');
        document.getElementById('ruleModal').classList.remove('flex');
        document.body.style.overflow = 'auto'; // Re-enable scrolling
    }

    // Close modal when clicking outside
    document.getElementById('ruleModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeRuleModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !document.getElementById('ruleModal').classList.contains('hidden')) {
            closeRuleModal();
        }
    });

    // Fix form submission to preserve search parameters
    const ruleForm = document.getElementById('ruleForm');
    if (ruleForm) {
        // The hidden inputs already handle preserving q and page
        // Let the form submit normally
    }
});
</script>