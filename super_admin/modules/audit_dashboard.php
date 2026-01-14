<?php
// super_admin/modules/audit_dashboard.php

// Get audit statistics
$audit_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM activity_logs) as total_logs,
        (SELECT COUNT(*) FROM activity_logs WHERE created_at >= CURDATE()) as today_logs,
        (SELECT COUNT(DISTINCT user_id) FROM activity_logs) as active_users,
        (SELECT COUNT(*) FROM file_encryption_logs) as encrypted_files,
        (SELECT COUNT(*) FROM login_history WHERE success = 0 AND login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as failed_logins
    FROM DUAL
";
$audit_stats_stmt = $conn->prepare($audit_stats_query);
$audit_stats_stmt->execute();
$audit_stats = $audit_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Audit Dashboard Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Master Audit & Compliance Dashboard</h3>
            <p class="text-sm text-gray-600">View all cases, evidence logs, user actions, and system events</p>
        </div>
        <div class="flex space-x-3">
            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                <i class="fas fa-download mr-2"></i>Export All
            </button>
            <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                <i class="fas fa-filter mr-2"></i>Advanced Filters
            </button>
        </div>
    </div>

    <!-- Audit Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Audit Logs</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($audit_stats['total_logs']); ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-history text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                Today: <?php echo $audit_stats['today_logs']; ?> logs
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Audited Users</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $audit_stats['active_users']; ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Encrypted Files</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $audit_stats['encrypted_files']; ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-key text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Failed Logins (7d)</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $audit_stats['failed_logins']; ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Data Retention</p>
                    <p class="text-2xl font-bold text-gray-800">90 days</p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-database text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Logs Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b">
            <nav class="flex space-x-8 px-6" aria-label="Audit Tabs">
                <button class="py-4 px-1 border-b-2 border-purple-600 text-sm font-medium text-purple-600">
                    Activity Logs
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    Evidence Access
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    Login History
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    System Changes
                </button>
            </nav>
        </div>

        <!-- Activity Logs Table -->
        <div class="p-6">
            <div class="mb-4 flex justify-between items-center">
                <h4 class="text-md font-medium text-gray-800">Recent Activity Logs</h4>
                <div class="flex space-x-2">
                    <input type="date" class="p-2 border border-gray-300 rounded-lg text-sm">
                    <input type="text" placeholder="Search actions..." class="p-2 border border-gray-300 rounded-lg text-sm w-64">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Affected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        // Get recent activity logs
                        $activity_query = "SELECT al.*, u.first_name, u.last_name, u.username 
                                          FROM activity_logs al 
                                          LEFT JOIN users u ON al.user_id = u.id 
                                          ORDER BY al.created_at DESC 
                                          LIMIT 20";
                        $activity_stmt = $conn->prepare($activity_query);
                        $activity_stmt->execute();
                        $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($activities as $log):
                            $action_class = [
                                'create' => 'bg-green-100 text-green-800',
                                'update' => 'bg-blue-100 text-blue-800',
                                'delete' => 'bg-red-100 text-red-800',
                                'login' => 'bg-purple-100 text-purple-800',
                                'access' => 'bg-yellow-100 text-yellow-800'
                            ][strtolower($log['action'])] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    @<?php echo htmlspecialchars($log['username']); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $action_class; ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars(substr($log['description'], 0, 100)); ?>...
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php if ($log['affected_id']): ?>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded">
                                    ID: <?php echo $log['affected_id']; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Compliance Reports -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-medium text-gray-800 mb-4">Compliance Reports</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="p-2 bg-blue-100 rounded-lg mr-3">
                        <i class="fas fa-file-pdf text-blue-600"></i>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-800">Monthly Activity Report</h5>
                        <p class="text-sm text-gray-500">Last generated: Jan 15, 2026</p>
                    </div>
                </div>
                <button class="w-full mt-2 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    Generate New Report
                </button>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="p-2 bg-green-100 rounded-lg mr-3">
                        <i class="fas fa-shield-alt text-green-600"></i>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-800">Security Audit Report</h5>
                        <p class="text-sm text-gray-500">Last audit: Jan 10, 2026</p>
                    </div>
                </div>
                <button class="w-full mt-2 px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                    Run Security Audit
                </button>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <div class="p-2 bg-purple-100 rounded-lg mr-3">
                        <i class="fas fa-user-shield text-purple-600"></i>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-800">User Access Review</h5>
                        <p class="text-sm text-gray-500">Due: Feb 1, 2026</p>
                    </div>
                </div>
                <button class="w-full mt-2 px-3 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700">
                    Start Review
                </button>
            </div>
        </div>
    </div>
</div>