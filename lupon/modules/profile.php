<?php
// lupon/modules/profile.php

// Fetch performance metrics
$metrics_query = "SELECT 
    COUNT(DISTINCT r.id) as total_cases,
    COUNT(CASE WHEN r.status IN ('closed', 'resolved') THEN 1 END) as resolved_cases,
    COUNT(CASE WHEN ml.status = 'completed' THEN 1 END) as completed_sessions,
    AVG(TIMESTAMPDIFF(DAY, r.created_at, IFNULL(r.resolution_date, NOW()))) as avg_resolution_days
    FROM reports r
    LEFT JOIN mediation_logs ml ON r.id = ml.report_id AND ml.lupon_id = :lupon_id
    WHERE r.assigned_lupon = :lupon_id2";
$metrics_stmt = $conn->prepare($metrics_query);
$metrics_stmt->execute([':lupon_id' => $user_id, ':lupon_id2' => $user_id]);
$performance_metrics = $metrics_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate success rate
$success_rate = $performance_metrics['total_cases'] > 0 ? 
    round(($performance_metrics['resolved_cases'] / $performance_metrics['total_cases']) * 100, 1) : 0;
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Profile & Performance Dashboard</h2>
            <p class="text-gray-600">View your mediation statistics and manage account details</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="printPerformanceReport()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-green-500 to-green-600 p-6">
            <div class="flex flex-col md:flex-row md:items-center">
                <?php if (!empty($profile_picture)): ?>
                    <img src="<?php echo '../uploads/profile_pictures/' . $profile_picture; ?>" 
                         alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg">
                <?php else: ?>
                    <div class="w-24 h-24 rounded-full bg-gradient-to-r from-green-700 to-green-800 flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-lg">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 md:mt-0 md:ml-6 flex-1">
                    <h3 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($user_name); ?></h3>
                    <p class="text-green-100"><?php echo htmlspecialchars($position_name); ?></p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="px-3 py-1 bg-white/20 text-white text-sm rounded-full">
                            <i class="fas fa-user-shield mr-1"></i> Certified Lupon Member
                        </span>
                        <span class="px-3 py-1 bg-white/20 text-white text-sm rounded-full">
                            <i class="fas fa-calendar-alt mr-1"></i> Member Since <?php echo date('Y', strtotime($user_data['created_at'])); ?>
                        </span>
                        <?php if ($is_active): ?>
                            <span class="px-3 py-1 bg-white/20 text-white text-sm rounded-full">
                                <i class="fas fa-circle mr-1"></i> Active Status
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 md:mt-0 text-right">
                    <div class="text-4xl font-bold text-white"><?php echo $success_rate; ?>%</div>
                    <div class="text-green-100">Success Rate</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Information -->
                <div>
                    <h4 class="text-lg font-bold text-gray-800 mb-4">Personal Information</h4>
                    <div class="space-y-3">
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Full Name:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($user_name); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Position:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($position_name); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Email:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($user_data['email']); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Contact:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($user_data['contact_number']); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Address:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($user_address); ?></span>
                        </div>
                        <div class="flex">
                            <span class="w-32 text-sm text-gray-500">Barangay:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($user_data['barangay_display']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div>
                    <h4 class="text-lg font-bold text-gray-800 mb-4">Performance Metrics</h4>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Mediation Success Rate</span>
                                <span class="font-medium"><?php echo $success_rate; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-green-500" style="width: <?php echo $success_rate; ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Average Resolution Time</span>
                                <span class="font-medium"><?php echo round($performance_metrics['avg_resolution_days'] ?? 0, 1); ?> days</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-blue-500" style="width: <?php echo min(($performance_metrics['avg_resolution_days'] ?? 0) * 2, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Case Completion Rate</span>
                                <span class="font-medium">
                                    <?php echo $performance_metrics['resolved_cases'] ?? 0; ?> / <?php echo $performance_metrics['total_cases'] ?? 0; ?>
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-purple-500" style="width: <?php 
                                    echo $performance_metrics['total_cases'] > 0 ? 
                                    ($performance_metrics['resolved_cases'] / $performance_metrics['total_cases']) * 100 : 0; 
                                ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?php echo $performance_metrics['total_cases'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total Cases Handled</div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo $performance_metrics['completed_sessions'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Mediation Sessions</div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600"><?php echo $performance_metrics['resolved_cases'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Cases Resolved</div>
            </div>
        </div>
    </div>

    <!-- Activity History -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-4">Recent Activity</h4>
        <div class="space-y-4">
            <?php
            $activity_query = "SELECT * FROM activity_logs 
                              WHERE user_id = :user_id
                              ORDER BY created_at DESC 
                              LIMIT 5";
            $activity_stmt = $conn->prepare($activity_query);
            $activity_stmt->execute([':user_id' => $user_id]);
            $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="flex items-start p-3 border border-gray-200 rounded-lg">
                        <div class="mr-3">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-<?php 
                                    echo strpos($activity['action'], 'mediation') !== false ? 'handshake' :
                                    (strpos($activity['action'], 'document') !== false ? 'file-contract' :
                                    (strpos($activity['action'], 'schedule') !== false ? 'calendar-alt' : 'clipboard-check')); 
                                ?> text-green-600"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-800"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-history text-3xl mb-3"></i>
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Account Settings -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-6">Account Settings</h4>
        <form method="POST" action="../handlers/update_profile.php">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                    <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number']); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" 
                           class="w-full p-3 border border-gray-300 rounded-lg"
                           placeholder="Enter current password to make changes">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" 
                           class="w-full p-3 border border-gray-300 rounded-lg"
                           placeholder="Leave blank to keep current">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" 
                           class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function printPerformanceReport() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Performance Report - <?php echo $user_name; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                .stat-box { text-align: center; padding: 15px; }
                .chart { margin: 30px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Lupon Member Performance Report</h1>
                <h2><?php echo $user_name; ?></h2>
                <p>Generated on <?php echo date('F d, Y'); ?></p>
            </div>
            
            <div class="stats">
                <div class="stat-box">
                    <h3>Success Rate</h3>
                    <h2><?php echo $success_rate; ?>%</h2>
                </div>
                <div class="stat-box">
                    <h3>Total Cases</h3>
                    <h2><?php echo $performance_metrics['total_cases'] ?? 0; ?></h2>
                </div>
                <div class="stat-box">
                    <h3>Resolved</h3>
                    <h2><?php echo $performance_metrics['resolved_cases'] ?? 0; ?></h2>
                </div>
            </div>
            
            <h3>Recent Activities</h3>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Activity</th>
                    <th>Description</th>
                </tr>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>