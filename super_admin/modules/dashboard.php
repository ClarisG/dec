<?php
// super_admin/modules/dashboard.php
?>
<div class="space-y-6">
    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Users</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $total_users; ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                <span>12% increase this month</span>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Cases</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $active_cases; ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">Pending: 8</span>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full ml-1">Investigating: 4</span>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">System Uptime</p>
                    <p class="text-2xl font-bold text-gray-800">99.8%</p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-server text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <div class="health-bar">
                    <div class="health-fill health-excellent" style="width: 99.8%"></div>
                </div>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Security Status</p>
                    <p class="text-2xl font-bold text-gray-800">Secure</p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-shield-alt text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-green-600">
                <i class="fas fa-check-circle mr-1"></i>
                All systems protected
            </div>
        </div>
    </div>

    <!-- System Health Overview -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">System Health Overview</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Resource Usage</h4>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>CPU Usage</span>
                            <span class="font-medium">45%</span>
                        </div>
                        <div class="health-bar">
                            <div class="health-fill health-good" style="width: 45%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Memory Usage</span>
                            <span class="font-medium">68%</span>
                        </div>
                        <div class="health-bar">
                            <div class="health-fill health-warning" style="width: 68%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Database Load</span>
                            <span class="font-medium">32%</span>
                        </div>
                        <div class="health-bar">
                            <div class="health-fill health-good" style="width: 32%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Recent Activities</h4>
                <div class="space-y-3">
                    <?php
                    // Get recent activities
                    $activity_query = "SELECT al.*, u.first_name, u.last_name 
                                      FROM activity_logs al 
                                      LEFT JOIN users u ON al.user_id = u.id 
                                      ORDER BY al.created_at DESC 
                                      LIMIT 5";
                    $activity_stmt = $conn->prepare($activity_query);
                    $activity_stmt->execute();
                    $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($activities as $activity):
                    ?>
                    <div class="flex items-center p-2 hover:bg-gray-50 rounded">
                        <div class="w-2 h-2 bg-purple-500 rounded-full mr-3"></div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($activity['action']); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> • 
                                <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Super Admin Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="?module=global_config" class="module-card bg-gradient-to-r from-purple-50 to-white p-4 rounded-lg border border-purple-100 hover:border-purple-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg mr-4">
                        <i class="fas fa-cogs text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-800">System Config</h4>
                        <p class="text-sm text-gray-500">Modify global settings</p>
                    </div>
                </div>
            </a>
            
            <a href="?module=user_management" class="module-card bg-gradient-to-r from-blue-50 to-white p-4 rounded-lg border border-blue-100 hover:border-blue-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg mr-4">
                        <i class="fas fa-users-cog text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-800">User Management</h4>
                        <p class="text-sm text-gray-500">Manage all user accounts</p>
                    </div>
                </div>
            </a>
            
            <a href="?module=evidence_log" class="module-card bg-gradient-to-r from-red-50 to-white p-4 rounded-lg border border-red-100 hover:border-red-300">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg mr-4">
                        <i class="fas fa-key text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-800">Evidence Log</h4>
                        <p class="text-sm text-gray-500">Access encrypted files</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Critical Reports -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Recent Critical Reports</h3>
            <a href="?module=reports_all" class="text-sm text-purple-600 hover:text-purple-700 font-medium">
                View All →
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    // Get critical reports
                    $reports_query = "SELECT r.*, rt.type_name, u.barangay 
                                     FROM reports r 
                                     JOIN report_types rt ON r.report_type_id = rt.id 
                                     JOIN users u ON r.user_id = u.id 
                                     WHERE r.priority IN ('high', 'critical')
                                     ORDER BY r.created_at DESC 
                                     LIMIT 5";
                    $reports_stmt = $conn->prepare($reports_query);
                    $reports_stmt->execute();
                    $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($reports as $report):
                        $status_class = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'assigned' => 'bg-blue-100 text-blue-800',
                            'investigating' => 'bg-purple-100 text-purple-800',
                            'resolved' => 'bg-green-100 text-green-800'
                        ][$report['status']] ?? 'bg-gray-100 text-gray-800';
                        
                        $priority_class = [
                            'critical' => 'badge-critical',
                            'high' => 'badge-high',
                            'medium' => 'badge-medium',
                            'low' => 'badge-low'
                        ][$report['priority']] ?? 'badge-low';
                    ?>
                    <tr class="table-row">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?php echo $report['report_number']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($report['type_name']); ?></div>
                            <span class="<?php echo $priority_class; ?>"><?php echo $report['priority']; ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $status_class; ?>">
                                <?php echo $report['status']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <?php echo htmlspecialchars($report['barangay'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="../ajax/get_report_details.php?id=<?php echo $report['id']; ?>" 
                               class="text-purple-600 hover:text-purple-900 text-sm font-medium">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>