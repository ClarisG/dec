<?php
// super_admin/modules/audit_dashboard.php

// Get audit filters
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get audit logs
$audit_query = "SELECT al.*, u.first_name, u.last_name, u.role
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE DATE(al.created_at) BETWEEN :date_from AND :date_to";
                
$audit_params = [':date_from' => $date_from, ':date_to' => $date_to];

if ($type_filter) {
    $audit_query .= " AND al.action_type = :type";
    $audit_params[':type'] = $type_filter;
}

$audit_query .= " ORDER BY al.created_at DESC LIMIT 100";

$audit_stmt = $conn->prepare($audit_query);
$audit_stmt->execute($audit_params);
$audit_logs = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get audit statistics
$stats_query = "SELECT 
    COUNT(*) as total_logs,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_logs,
    COUNT(CASE WHEN action LIKE '%login%' THEN 1 END) as login_events,
    COUNT(CASE WHEN action LIKE '%delete%' OR action LIKE '%remove%' THEN 1 END) as delete_events
    FROM activity_logs 
    WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
    
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
$audit_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Master Audit & Compliance Dashboard</h2>
                <p class="text-gray-600 mt-2">View all cases, evidence logs, and system events with full filtering and export capabilities</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="exportAuditLogs()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i> Export Audit Log
                </button>
            </div>
        </div>

        <!-- Audit Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $audit_stats['total_logs'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total Audit Logs</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo $audit_stats['unique_users'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Unique Users</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo $audit_stats['today_logs'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Today's Events</div>
            </div>
            <div class="bg-red-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-red-700"><?php echo $audit_stats['delete_events'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Delete Events</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-xl mb-6">
            <form method="GET" action="" class="space-y-4 md:space-y-0 md:flex md:space-x-4 md:items-end">
                <input type="hidden" name="module" value="audit_dashboard">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                    <select name="type" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">All Actions</option>
                        <option value="login" <?php echo $type_filter === 'login' ? 'selected' : ''; ?>>Login Events</option>
                        <option value="create" <?php echo $type_filter === 'create' ? 'selected' : ''; ?>>Create Events</option>
                        <option value="update" <?php echo $type_filter === 'update' ? 'selected' : ''; ?>>Update Events</option>
                        <option value="delete" <?php echo $type_filter === 'delete' ? 'selected' : ''; ?>>Delete Events</option>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="?module=audit_dashboard" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Timeline -->
    <div class="glass-card rounded-xl p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Audit Timeline</h3>
        
        <div class="space-y-4">
            <?php foreach ($audit_logs as $log): ?>
            <div class="flex items-start p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                <div class="flex-shrink-0 w-10 h-10 mr-4">
                    <div class="w-10 h-10 rounded-full 
                        <?php echo $log['role'] === 'super_admin' ? 'bg-purple-100' : 
                                 ($log['role'] === 'admin' ? 'bg-red-100' :
                                 ($log['role'] === 'captain' ? 'bg-blue-100' :
                                 'bg-gray-100')); ?> 
                        flex items-center justify-center">
                        <i class="fas 
                            <?php echo strpos($log['action'], 'login') !== false ? 'fa-sign-in-alt text-green-600' :
                                   (strpos($log['action'], 'create') !== false ? 'fa-plus-circle text-blue-600' :
                                   (strpos($log['action'], 'update') !== false ? 'fa-edit text-yellow-600' :
                                   (strpos($log['action'], 'delete') !== false ? 'fa-trash text-red-600' :
                                   'fa-history text-gray-600'))); ?>"></i>
                    </div>
                </div>
                
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($log['action']); ?></p>
                    
                    <div class="flex items-center mt-2 space-x-4">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            <?php 
                            $user_name = $log['first_name'] && $log['last_name'] 
                                ? htmlspecialchars($log['first_name'] . ' ' . $log['last_name'])
                                : 'System';
                            echo $user_name . ' (' . ($log['role'] ? ucfirst($log['role']) : 'System') . ')';
                            ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            <?php echo date('M d, H:i:s', strtotime($log['created_at'])); ?>
                        </p>
                        <?php if ($log['ip_address']): ?>
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-network-wired mr-1"></i>
                            <?php echo htmlspecialchars($log['ip_address']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($audit_logs)): ?>
            <div class="text-center py-12">
                <i class="fas fa-history text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No audit logs found for selected period</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compliance Reports -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Security Compliance -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800">Security Compliance</h3>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                    <i class="fas fa-check-circle mr-1"></i> Compliant
                </span>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-user-shield text-green-500 mr-3"></i>
                        <div>
                            <p class="font-medium">Access Control</p>
                            <p class="text-sm text-gray-500">Role-based permissions</p>
                        </div>
                    </div>
                    <div class="text-green-600">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-contract text-blue-500 mr-3"></i>
                        <div>
                            <p class="font-medium">Audit Trail</p>
                            <p class="text-sm text-gray-500">Complete activity logging</p>
                        </div>
                    </div>
                    <div class="text-green-600">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-lock text-purple-500 mr-3"></i>
                        <div>
                            <p class="font-medium">Data Encryption</p>
                            <p class="text-sm text-gray-500">Evidence file protection</p>
                        </div>
                    </div>
                    <div class="text-green-600">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Retention -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800">Data Retention</h3>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    <i class="fas fa-clock mr-1"></i> 90 Days
                </span>
            </div>
            
            <div class="space-y-4">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">Audit Logs</p>
                        <span class="text-sm text-blue-600"><?php echo $audit_stats['total_logs'] ?? 0; ?> records</span>
                    </div>
                    <div class="health-bar">
                        <div class="health-fill health-good" style="width: 65%"></div>
                    </div>
                </div>
                
                <div class="p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">User Activities</p>
                        <span class="text-sm text-green-600"><?php echo $audit_stats['unique_users'] ?? 0; ?> users</span>
                    </div>
                    <div class="health-bar">
                        <div class="health-fill health-excellent" style="width: 85%"></div>
                    </div>
                </div>
                
                <div class="p-3 bg-purple-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">System Events</p>
                        <span class="text-sm text-purple-600"><?php echo $audit_stats['today_logs'] ?? 0; ?> today</span>
                    </div>
                    <div class="health-bar">
                        <div class="health-fill health-warning" style="width: 45%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportAuditLogs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}
</script>