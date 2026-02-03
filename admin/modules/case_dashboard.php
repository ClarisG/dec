<?php
// admin/modules/case_dashboard.php - BARANGAY CASE STATUS DASHBOARD MODULE

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$filter_date_from = $_GET['date_from'] ?? date('Y-m-01');
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query with filters (removed report_referrals references)
$cases_query = "SELECT r.*, rt.type_name as incident_type,
                       CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
                       CONCAT(ut.first_name, ' ', ut.last_name) as tanod_name,
                       NULL as hearing_date, NULL as hearing_status,
                       c.complainant_contact
                FROM reports r
                LEFT JOIN report_types rt ON r.report_type_id = rt.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users ut ON r.assigned_tanod = ut.id
                /* hearing_cases removed to avoid missing table error */
                LEFT JOIN complainants c ON r.complainant_id = c.id
                WHERE r.status != 'draft' AND r.status != 'pending_field_verification'";

$params = [];

if ($filter_status !== 'all') {
    $cases_query .= " AND r.status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_type !== 'all') {
    $cases_query .= " AND rt.type_name = :type";
    $params[':type'] = $filter_type;
}

$cases_query .= " AND DATE(r.created_at) BETWEEN :date_from AND :date_to";
$params[':date_from'] = $filter_date_from;
$params[':date_to'] = $filter_date_to;

$cases_query .= " ORDER BY r.priority DESC, r.created_at DESC 
                  LIMIT 50";

$cases_stmt = $conn->prepare($cases_query);
$cases_stmt->execute($params);
$cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get case statistics
$stats_query = "SELECT 
    COUNT(*) as total_cases,
    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_cases,
    COUNT(CASE WHEN status = 'referred' THEN 1 END) as referred_cases,
    COUNT(CASE WHEN status = 'investigating' THEN 1 END) as investigating_cases,
    COUNT(CASE WHEN priority IN ('high', 'critical') THEN 1 END) as high_priority_cases,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_cases
    FROM reports 
    WHERE status NOT IN ('draft', 'pending_field_verification')";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$case_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get report types for filter
$types_query = "SELECT DISTINCT type_name FROM report_types ORDER BY type_name";
$types_stmt = $conn->prepare($types_query);
$types_stmt->execute();
$report_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="space-y-6">
    <!-- Case Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Cases</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo number_format($case_stats['total_cases'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-gray-100 rounded-lg">
                    <i class="fas fa-folder-open text-gray-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <?php echo $case_stats['today_cases'] ?? 0; ?> new today
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Closed Cases</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo number_format($case_stats['closed_cases'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Resolved successfully
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Referred Cases</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo number_format($case_stats['referred_cases'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-exchange-alt text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Escalated to higher authorities
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Under Investigation</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo number_format($case_stats['investigating_cases'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-search text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Active investigations
            </div>
        </div>
    </div>
    
    <!-- Case Dashboard -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 space-y-4 md:space-y-0">
            <h2 class="text-xl font-bold text-gray-800">Barangay Case Dashboard</h2>
            
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <form method="GET" action="" class="flex flex-wrap gap-3">
                    <input type="hidden" name="module" value="case_dashboard">
                    
                    <div>
                        <select name="status" onchange="this.form.submit()" 
                                class="p-2 border border-gray-300 rounded-lg text-sm">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="investigating" <?php echo $filter_status === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                            <option value="hearing" <?php echo $filter_status === 'hearing' ? 'selected' : ''; ?>>Hearing</option>
                            <option value="referred" <?php echo $filter_status === 'referred' ? 'selected' : ''; ?>>Referred</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="type" onchange="this.form.submit()" 
                                class="p-2 border border-gray-300 rounded-lg text-sm">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach($report_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex space-x-2">
                        <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>" 
                               onchange="this.form.submit()"
                               class="p-2 border border-gray-300 rounded-lg text-sm">
                        <span class="self-center text-gray-500">to</span>
                        <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>" 
                               onchange="this.form.submit()"
                               class="p-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    
                    <button type="button" onclick="exportCases()" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                </form>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complainant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Tanod</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Update</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audit Trail</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($cases as $case): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($case['report_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                        <span class="text-gray-600 font-medium text-xs">
                                            <?php echo strtoupper(substr($case['complainant_name'] ?? 'C', 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($case['complainant_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($case['complainant_contact'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($case['incident_type'] ?? 'Unknown'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($case['tanod_name'] ?? 'Not assigned'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge 
                                    <?php echo $case['status'] === 'closed' ? 'status-success' : 
                                           ($case['status'] === 'referred' ? 'status-warning' : 
                                           ($case['status'] === 'investigating' ? 'status-active' : 'status-pending')); ?>">
                                    <?php echo ucfirst($case['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="font-medium <?php echo $case['priority'] === 'high' ? 'text-red-600' : 
                                                                 ($case['priority'] === 'critical' ? 'text-red-800' : 'text-green-600'); ?>">
                                    <?php echo ucfirst($case['priority']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, H:i', strtotime($case['updated_at'] ?? $case['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewAuditTrail(<?php echo $case['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900">
                                    <i class="fas fa-history"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($cases)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-3xl mb-2"></i>
                <p>No cases found with the selected filters</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Case Status Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Case Status Distribution</h3>
            
            <div class="space-y-4">
                <?php
                $status_dist_query = "SELECT 
                    status, COUNT(*) as count 
                    FROM reports 
                    WHERE status NOT IN ('draft', 'pending_field_verification')
                    GROUP BY status 
                    ORDER BY count DESC";
                $status_dist_stmt = $conn->prepare($status_dist_query);
                $status_dist_stmt->execute();
                $status_dist = $status_dist_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $total_cases = array_sum(array_column($status_dist, 'count'));
                ?>
                
                <?php foreach($status_dist as $status): 
                    $percentage = $total_cases > 0 ? ($status['count'] / $total_cases) * 100 : 0;
                ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 capitalize">
                                <?php echo htmlspecialchars($status['status']); ?>
                            </span>
                            <span class="text-sm font-medium text-gray-700">
                                <?php echo $status['count']; ?> (<?php echo round($percentage, 1); ?>%)
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill bg-purple-500" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Case Updates</h3>
            
            <div class="space-y-3">
                <?php
                $recent_updates_query = "SELECT al.*, u.first_name, u.last_name, r.report_number 
                                        FROM activity_logs al
                                        LEFT JOIN users u ON al.user_id = u.id
                                        LEFT JOIN reports r ON al.report_id = r.id
                                        WHERE al.report_id IS NOT NULL
                                        ORDER BY al.created_at DESC 
                                        LIMIT 5";
                $recent_updates_stmt = $conn->prepare($recent_updates_query);
                $recent_updates_stmt->execute();
                $recent_updates = $recent_updates_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (!empty($recent_updates)): ?>
                    <?php foreach($recent_updates as $update): ?>
                        <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($update['report_number']); ?></span>
                                    <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($update['description']); ?></p>
                                </div>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('H:i', strtotime($update['created_at'])); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <div>
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($update['first_name'] . ' ' . $update['last_name']); ?>
                                </div>
                                <span class="status-badge <?php echo strpos($update['action'], 'update') !== false ? 'status-success' : 'status-info'; ?>">
                                    <?php echo htmlspecialchars($update['action']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-3xl mb-2"></i>
                        <p>No recent case updates</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Audit Trail Modal -->
<div id="auditTrailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalAuditTitle" class="text-xl font-bold text-gray-800">Case Audit Trail</h3>
            <button onclick="closeAuditTrailModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="auditTrailContent" class="space-y-4">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
function viewAuditTrail(caseId) {
    fetch(`handlers/get_case_audit_trail.php?id=${caseId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalAuditTitle').textContent = `Audit Trail: ${data.report_number}`;
            
            let auditHtml = `
                <div class="mb-6">
                    <h4 class="font-bold text-gray-800 mb-2">Case Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Case Number: <span class="font-medium">${data.report_number}</span></p>
                            <p class="text-sm text-gray-600">Type: <span class="font-medium">${data.incident_type}</span></p>
                            <p class="text-sm text-gray-600">Complainant: <span class="font-medium">${data.complainant_name}</span></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Current Status: <span class="status-badge ${getStatusClass(data.status)}">${data.status}</span></p>
                            <p class="text-sm text-gray-600">Assigned Tanod: <span class="font-medium">${data.tanod_name || 'Not assigned'}</span></p>
                            <p class="text-sm text-gray-600">Created: ${new Date(data.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
                
                <h4 class="font-bold text-gray-800 mb-2">Activity Timeline</h4>
            `;
            
            if (data.activities && data.activities.length > 0) {
                auditHtml += `
                    <div class="relative">
                        <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200"></div>
                        
                        ${data.activities.map((activity, index) => `
                            <div class="relative mb-6 ml-12">
                                <div class="absolute -left-8 top-0 w-4 h-4 rounded-full bg-${getActivityColor(activity.action)}-500 border-2 border-white"></div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="font-medium text-gray-800">${activity.action}</span>
                                            <p class="text-sm text-gray-600">${activity.description}</p>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            ${new Date(activity.created_at).toLocaleString()}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs text-gray-500">
                                        <div>
                                            <i class="fas fa-user mr-1"></i>
                                            ${activity.user_name || 'System'}
                                        </div>
                                        <span class="font-mono text-xs">
                                            ${activity.ip_address || 'N/A'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                auditHtml += `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-3xl mb-2"></i>
                        <p>No audit trail available for this case</p>
                    </div>
                `;
            }
            
            document.getElementById('auditTrailContent').innerHTML = auditHtml;
            document.getElementById('auditTrailModal').classList.remove('hidden');
            document.getElementById('auditTrailModal').classList.add('flex');
        });
}

function getStatusClass(status) {
    const classes = {
        'closed': 'status-success',
        'referred': 'status-warning',
        'investigating': 'status-active',
        'open': 'status-pending',
        'hearing': 'status-info'
    };
    return classes[status] || 'status-pending';
}

function getActivityColor(action) {
    if (action.includes('create') || action.includes('add')) return 'green';
    if (action.includes('update') || action.includes('change')) return 'blue';
    if (action.includes('delete') || action.includes('remove')) return 'red';
    if (action.includes('assign') || action.includes('route')) return 'purple';
    return 'gray';
}

function closeAuditTrailModal() {
    document.getElementById('auditTrailModal').classList.add('hidden');
    document.getElementById('auditTrailModal').classList.remove('flex');
}

function exportCases() {
    const params = new URLSearchParams({
        module: 'case_dashboard',
        status: '<?php echo $filter_status; ?>',
        type: '<?php echo $filter_type; ?>',
        date_from: '<?php echo $filter_date_from; ?>',
        date_to: '<?php echo $filter_date_to; ?>'
    });
    
    window.location.href = `handlers/export_cases.php?${params.toString()}`;
}

window.onclick = function(event) {
    const auditModal = document.getElementById('auditTrailModal');
    if (event.target == auditModal) {
        closeAuditTrailModal();
    }
}
</script>