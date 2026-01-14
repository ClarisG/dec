<?php
// super_admin/modules/kpi_superview.php

// Get KPI data for all roles
$kpi_query = "SELECT 
    u.role,
    COUNT(DISTINCT u.id) as user_count,
    COUNT(DISTINCT r.id) as total_reports,
    SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
    AVG(TIMESTAMPDIFF(HOUR, r.created_at, r.resolution_date)) as avg_resolution_hours,
    MAX(r.created_at) as last_report_date
    FROM users u
    LEFT JOIN reports r ON u.id = r.user_id
    WHERE u.role IN ('captain', 'secretary', 'tanod', 'lupon', 'admin')
    GROUP BY u.role
    ORDER BY FIELD(u.role, 'captain', 'secretary', 'tanod', 'lupon', 'admin')";

$kpi_stmt = $conn->prepare($kpi_query);
$kpi_stmt->execute();
$kpi_data = $kpi_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get individual performance
$performance_query = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.role,
    u.barangay,
    COUNT(DISTINCT r.id) as report_count,
    SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
    AVG(CASE WHEN r.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, r.created_at, r.resolution_date) END) as avg_resolution_time,
    MAX(r.created_at) as last_activity
    FROM users u
    LEFT JOIN reports r ON u.id = r.user_id OR u.id = r.assigned_to OR u.id = r.assigned_lupon
    WHERE u.role IN ('captain', 'secretary', 'tanod', 'lupon', 'admin')
    AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.role, resolved_count DESC";

$performance_stmt = $conn->prepare($performance_query);
$performance_stmt->execute();
$performance_data = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Executive KPI Superview</h2>
                <p class="text-gray-600 mt-2">View and modify KPIs for all Barangay officials</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="exportKPI()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i> Export KPI Report
                </button>
                <button onclick="setKPITargets()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-bullseye mr-2"></i> Set Targets
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Dashboard -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <?php
        $roles_data = [];
        foreach ($kpi_data as $data) {
            $roles_data[$data['role']] = $data;
        }
        
        $role_configs = [
            'captain' => ['color' => 'blue', 'icon' => 'fa-user-tie', 'title' => 'Captain'],
            'secretary' => ['color' => 'green', 'icon' => 'fa-file-alt', 'title' => 'Secretary'],
            'tanod' => ['color' => 'indigo', 'icon' => 'fa-shield-alt', 'title' => 'Tanod'],
            'lupon' => ['color' => 'yellow', 'icon' => 'fa-gavel', 'title' => 'Lupon'],
            'admin' => ['color' => 'red', 'icon' => 'fa-cog', 'title' => 'Admin']
        ];
        
        foreach ($role_configs as $role => $config):
            $data = $roles_data[$role] ?? null;
            $color = $config['color'];
        ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 rounded-xl bg-<?php echo $color; ?>-100 flex items-center justify-center mr-4">
                    <i class="fas <?php echo $config['icon']; ?> text-<?php echo $color; ?>-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800"><?php echo $config['title']; ?> Performance</h3>
                    <p class="text-sm text-gray-500"><?php echo $data['user_count'] ?? 0; ?> active users</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Case Resolution Rate</span>
                        <span class="font-medium text-<?php echo $color; ?>-600">
                            <?php 
                            $rate = $data ? ($data['resolved_reports'] / max(1, $data['total_reports'])) * 100 : 0;
                            echo round($rate, 1); 
                            ?>%
                        </span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-<?php echo $color; ?>-500 rounded-full" 
                             style="width: <?php echo min($rate, 100); ?>%"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Total Cases</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo $data['total_reports'] ?? 0; ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Resolved</p>
                        <p class="text-lg font-bold text-green-600"><?php echo $data['resolved_reports'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Pending</p>
                        <p class="text-lg font-bold text-yellow-600"><?php echo $data['pending_reports'] ?? 0; ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Avg. Resolution</p>
                        <p class="text-lg font-bold text-gray-800">
                            <?php echo $data && $data['avg_resolution_hours'] ? round($data['avg_resolution_hours']) . 'h' : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <button onclick="viewRoleDetails('<?php echo $role; ?>')"
                        class="w-full px-4 py-2 border border-<?php echo $color; ?>-300 text-<?php echo $color; ?>-700 rounded-lg hover:bg-<?php echo $color; ?>-50 transition">
                    View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Individual Performance -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800">Individual Performance Metrics</h3>
            <p class="text-gray-600 mt-1">Performance breakdown by user</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">User</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Role</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Barangay</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Cases Handled</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Resolved</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Resolution Rate</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Avg. Time</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Performance</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($performance_data as $user): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 mr-3">
                                    <?php
                                    $role_color = [
                                        'captain' => 'blue',
                                        'secretary' => 'green', 
                                        'tanod' => 'indigo',
                                        'lupon' => 'yellow',
                                        'admin' => 'red'
                                    ][$user['role']] ?? 'gray';
                                    ?>
                                    <div class="w-10 h-10 rounded-full bg-<?php echo $role_color; ?>-100 flex items-center justify-center">
                                        <i class="fas 
                                            <?php echo $user['role'] === 'captain' ? 'fa-user-tie' :
                                                   ($user['role'] === 'secretary' ? 'fa-file-alt' :
                                                   ($user['role'] === 'tanod' ? 'fa-shield-alt' :
                                                   ($user['role'] === 'lupon' ? 'fa-gavel' :
                                                   'fa-cog'))); ?> 
                                            text-<?php echo $role_color; ?>-600"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">ID: <?php echo $user['id']; ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $user['role'] === 'captain' ? 'bg-blue-100 text-blue-800' :
                                       ($user['role'] === 'secretary' ? 'bg-green-100 text-green-800' :
                                       ($user['role'] === 'tanod' ? 'bg-indigo-100 text-indigo-800' :
                                       ($user['role'] === 'lupon' ? 'bg-yellow-100 text-yellow-800' :
                                       'bg-red-100 text-red-800'))); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($user['barangay'] ?? 'N/A'); ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center font-medium"><?php echo $user['report_count'] ?? 0; ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center font-medium text-green-600"><?php echo $user['resolved_count'] ?? 0; ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center justify-center">
                                <?php
                                $rate = $user['report_count'] > 0 ? ($user['resolved_count'] / $user['report_count']) * 100 : 0;
                                $rate_color = $rate >= 80 ? 'green' : ($rate >= 60 ? 'yellow' : 'red');
                                ?>
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="h-full bg-<?php echo $rate_color; ?>-500 rounded-full" 
                                         style="width: <?php echo min($rate, 100); ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-<?php echo $rate_color; ?>-600">
                                    <?php echo round($rate, 1); ?>%
                                </span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center text-sm">
                                <?php echo $user['avg_resolution_time'] ? round($user['avg_resolution_time']) . 'h' : 'N/A'; ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <?php
                            $performance_score = min(100, max(0, 
                                ($user['resolved_count'] * 30) + 
                                ($rate >= 80 ? 30 : ($rate >= 60 ? 20 : 10)) +
                                (($user['avg_resolution_time'] && $user['avg_resolution_time'] < 24 ? 20 : 10)) +
                                ($user['last_activity'] && strtotime($user['last_activity']) > strtotime('-7 days') ? 20 : 10)
                            ));
                            $performance_color = $performance_score >= 80 ? 'green' : ($performance_score >= 60 ? 'yellow' : 'red');
                            ?>
                            <div class="flex items-center">
                                <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="h-full bg-<?php echo $performance_color; ?>-500 rounded-full" 
                                         style="width: <?php echo $performance_score; ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-<?php echo $performance_color; ?>-600">
                                    <?php echo round($performance_score); ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="viewUserPerformance(<?php echo $user['id']; ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <button onclick="setUserTargets(<?php echo $user['id']; ?>)"
                                        class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg">
                                    <i class="fas fa-bullseye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Resolution Time Trends -->
        <div class="glass-card rounded-xl p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6">Resolution Time Trends</h3>
            <div class="chart-container">
                <canvas id="resolutionChart"></canvas>
            </div>
        </div>

        <!-- Case Volume by Role -->
        <div class="glass-card rounded-xl p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6">Case Volume Distribution</h3>
            <div class="chart-container">
                <canvas id="volumeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
function viewRoleDetails(role) {
    fetch(`../ajax/get_role_kpi.php?role=${role}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-xl bg-${role === 'captain' ? 'blue' : role === 'secretary' ? 'green' : role === 'tanod' ? 'indigo' : role === 'lupon' ? 'yellow' : 'red'}-100 flex items-center justify-center">
                            <i class="fas ${role === 'captain' ? 'fa-user-tie' : role === 'secretary' ? 'fa-file-alt' : role === 'tanod' ? 'fa-shield-alt' : role === 'lupon' ? 'fa-gavel' : 'fa-cog'} text-${role === 'captain' ? 'blue' : role === 'secretary' ? 'green' : role === 'tanod' ? 'indigo' : role === 'lupon' ? 'yellow' : 'red'}-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">${role.charAt(0).toUpperCase() + role.slice(1)} Performance</h3>
                            <p class="text-gray-600">${data.user_count} active users</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-500">Total Cases</p>
                            <p class="text-2xl font-bold text-gray-800">${data.total_reports || 0}</p>
                        </div>
                        <div class="p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-500">Resolved</p>
                            <p class="text-2xl font-bold text-green-600">${data.resolved_reports || 0}</p>
                        </div>
                        <div class="p-3 bg-yellow-50 rounded-lg">
                            <p class="text-sm text-gray-500">Pending</p>
                            <p class="text-2xl font-bold text-yellow-600">${data.pending_reports || 0}</p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-gray-500">Avg Resolution</p>
                            <p class="text-2xl font-bold text-blue-600">${data.avg_resolution_hours ? Math.round(data.avg_resolution_hours) + 'h' : 'N/A'}</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h4 class="font-medium text-gray-800 mb-2">Performance Metrics</h4>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Resolution Rate</span>
                                    <span class="font-medium">${data.resolution_rate || 0}%</span>
                                </div>
                                <div class="h-2 bg-gray-200 rounded-full">
                                    <div class="h-full bg-green-500 rounded-full" style="width: ${Math.min(data.resolution_rate || 0, 100)}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Efficiency Score</span>
                                    <span class="font-medium">${data.efficiency_score || 0}/100</span>
                                </div>
                                <div class="h-2 bg-gray-200 rounded-full">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: ${data.efficiency_score || 0}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load role details');
        });
}

function exportKPI() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'kpi');
    window.location.href = '?' + params.toString();
}

function setKPITargets() {
    const content = `
        <form method="POST" action="../handlers/set_kpi_targets.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Role</label>
                    <select name="target_role" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="all">All Roles</option>
                        <option value="captain">Captains</option>
                        <option value="secretary">Secretaries</option>
                        <option value="tanod">Tanods</option>
                        <option value="lupon">Lupon Members</option>
                        <option value="admin">Admins</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target Resolution Rate (%)</label>
                        <input type="number" min="0" max="100" name="target_rate" 
                               class="w-full p-3 border border-gray-300 rounded-lg" placeholder="80">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target Cases/Month</label>
                        <input type="number" name="target_cases" 
                               class="w-full p-3 border border-gray-300 rounded-lg" placeholder="20">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Resolution Time (hours)</label>
                        <input type="number" name="max_resolution_time" 
                               class="w-full p-3 border border-gray-300 rounded-lg" placeholder="48">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Period (days)</label>
                        <select name="evaluation_period" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Set Targets
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Resolution Time Chart
    const resolutionCtx = document.getElementById('resolutionChart')?.getContext('2d');
    if (resolutionCtx) {
        new Chart(resolutionCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Captains',
                        data: [48, 42, 36, 32, 30, 28],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Tanods',
                        data: [72, 68, 64, 58, 52, 48],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Lupon',
                        data: [96, 88, 82, 76, 72, 68],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
    }
    
    // Volume Chart
    const volumeCtx = document.getElementById('volumeChart')?.getContext('2d');
    if (volumeCtx) {
        new Chart(volumeCtx, {
            type: 'bar',
            data: {
                labels: ['Captains', 'Secretaries', 'Tanods', 'Lupon', 'Admins'],
                datasets: [{
                    label: 'Cases Handled',
                    data: [120, 85, 200, 75, 40],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    }
                }
            }
        });
    }
});
</script>