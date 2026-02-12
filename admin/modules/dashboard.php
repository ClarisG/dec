<?php
// admin/modules/dashboard.php - ADMIN DASHBOARD MODULE
?>
<div class="space-y-6">
    <!-- System Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Reports</p>
                    <h3 class="text-3xl font-bold text-gray-800 stat-value" data-stat="total_reports">
                        <?php echo number_format($stats['total_reports'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <span class="text-green-600 font-medium">+12%</span> from last month
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Verification</p>
                    <h3 class="text-3xl font-bold text-gray-800 stat-value" data-stat="pending_verification">
                        <?php echo number_format($stats['pending_verification'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <span class="text-red-600 font-medium">-3%</span> from yesterday
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Tanods</p>
                    <h3 class="text-3xl font-bold text-gray-800 stat-value" data-stat="active_tanods">
                        <?php echo number_format($stats['active_tanods'] ?? 0); ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <span class="text-green-600 font-medium">5/5</span> on duty
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">System Health</p>
                    <h3 class="text-3xl font-bold text-gray-800">98%</h3>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-heartbeat text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <span class="text-green-600 font-medium">All systems normal</span>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Recent System Activity</h2>
            <a href="?module=audit" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                View Full Audit Log →
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach($recent_activities as $activity): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-purple-100 rounded-full flex items-center justify-center">
                                            <span class="text-purple-800 font-medium text-sm">
                                                <?php echo strtoupper(substr($activity['first_name'] ?? 'S', 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(($activity['first_name'] ?? 'System') . ' ' . ($activity['last_name'] ?? '')); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo strpos($activity['action'], 'update') !== false ? 'bg-green-100 text-green-800' : 
                                               (strpos($activity['action'], 'delete') !== false ? 'bg-red-100 text-red-800' : 
                                               'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars(substr($activity['description'], 0, 50) . (strlen($activity['description']) > 50 ? '...' : '')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                No recent activity found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>