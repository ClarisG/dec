<?php
// super_admin/modules/dashboard.php
?>
<div class="space-y-6">
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-2xl p-6 text-white shadow-xl">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h2 class="text-2xl font-bold">System-wide Oversight Dashboard</h2>
                <p class="text-purple-200 mt-2">Unrestricted access to all system modules and data</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $total_users; ?></div>
                        <div class="text-sm text-purple-200">Total Users</div>
                    </div>
                    <div class="h-10 w-px bg-purple-400"></div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $active_cases; ?></div>
                        <div class="text-sm text-purple-200">Active Cases</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="super-stat-card rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Active Users</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $health_data['active_users'] ?? 0; ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-users text-purple-600 text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="health-bar">
                    <div class="health-fill health-excellent" style="width: 95%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">95% active rate</p>
            </div>
        </div>

        <div class="super-stat-card rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Weekly Reports</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $health_data['weekly_reports'] ?? 0; ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-file-alt text-blue-600 text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="health-bar">
                    <div class="health-fill health-good" style="width: 75%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">25% increase from last week</p>
            </div>
        </div>

        <div class="super-stat-card rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Active APIs</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $health_data['active_apis'] ?? 0; ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-plug text-green-600 text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="health-bar">
                    <div class="health-fill health-excellent" style="width: 100%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">All systems operational</p>
            </div>
        </div>

        <div class="super-stat-card rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Hourly Activity</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $health_data['hourly_activity'] ?? 0; ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-lg">
                    <i class="fas fa-bolt text-red-600 text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="health-bar">
                    <div class="health-fill health-warning" style="width: 60%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">Moderate activity level</p>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- System Health Chart -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">System Health Overview</h3>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                    <i class="fas fa-check-circle mr-1"></i> Healthy
                </span>
            </div>
            <div class="chart-container">
                <canvas id="healthChart"></canvas>
            </div>
        </div>

        <!-- Activity Distribution -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Recent System Activities</h3>
                <a href="?module=activity_logs" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-4">
                <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-history text-purple-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['action']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php 
                            $user_name = ($activity['first_name'] && $activity['last_name']) 
                                ? htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name'])
                                : 'System';
                            echo "By $user_name • " . date('M d, H:i', strtotime($activity['created_at']));
                            ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="glass-card rounded-xl p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <button onclick="quickCreateUser()" 
                    class="p-4 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition text-left">
                <i class="fas fa-user-plus text-purple-600 text-xl mb-2"></i>
                <p class="font-medium text-gray-800">Create User</p>
                <p class="text-sm text-gray-500 mt-1">Add new system user</p>
            </button>
            
            <button onclick="quickSendNotification()"
                    class="p-4 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition text-left">
                <i class="fas fa-bullhorn text-blue-600 text-xl mb-2"></i>
                <p class="font-medium text-gray-800">Send Alert</p>
                <p class="text-sm text-gray-500 mt-1">System-wide notification</p>
            </button>
            
            <a href="?module=global_config"
               class="p-4 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition text-left">
                <i class="fas fa-cogs text-green-600 text-xl mb-2"></i>
                <p class="font-medium text-gray-800">System Config</p>
                <p class="text-sm text-gray-500 mt-1">Update settings</p>
            </a>
            
            <a href="?module=system_health"
               class="p-4 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition text-left">
                <i class="fas fa-heartbeat text-red-600 text-xl mb-2"></i>
                <p class="font-medium text-gray-800">System Health</p>
                <p class="text-sm text-gray-500 mt-1">Monitor performance</p>
            </a>
        </div>
    </div>

    <!-- Critical Alerts -->
    <div class="glass-card rounded-xl p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                <h3 class="text-lg font-bold text-gray-800">Critical System Alerts</h3>
            </div>
            <span class="badge badge-critical">Priority</span>
        </div>
        
        <div class="space-y-3">
            <?php if (($health_data['pending_reports'] ?? 0) > 10): ?>
            <div class="flex items-center p-3 bg-red-50 rounded-lg">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">High Pending Reports</p>
                    <p class="text-sm text-gray-600"><?php echo $health_data['pending_reports'] ?? 0; ?> reports pending assignment</p>
                </div>
                <a href="?module=reports_all" class="text-red-600 hover:text-red-800 text-sm font-medium">
                    Review
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (($health_data['last_activity'] ?? null) && strtotime($health_data['last_activity']) < strtotime('-1 hour')): ?>
            <div class="flex items-center p-3 bg-yellow-50 rounded-lg">
                <i class="fas fa-clock text-yellow-500 mr-3"></i>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">Low System Activity</p>
                    <p class="text-sm text-gray-600">Last system activity was <?php echo date('H:i', strtotime($health_data['last_activity'])); ?></p>
                </div>
                <a href="?module=activity_logs" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                    Check
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (($health_data['today_patrols'] ?? 0) < 5): ?>
            <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                <i class="fas fa-walking text-blue-500 mr-3"></i>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">Low Patrol Activity</p>
                    <p class="text-sm text-gray-600">Only <?php echo $health_data['today_patrols'] ?? 0; ?> patrols logged today</p>
                </div>
                <a href="?module=patrol_override" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Manage
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    const healthCtx = document.getElementById('healthChart')?.getContext('2d');
    if (healthCtx) {
        new Chart(healthCtx, {
            type: 'doughnut',
            data: {
                labels: ['Operational', 'Warning', 'Critical', 'Maintenance'],
                datasets: [{
                    data: [85, 10, 3, 2],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#6b7280'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
});
</script>