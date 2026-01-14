<?php
// lupon/modules/dashboard.php
?>
<div class="space-y-6">
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                <p class="opacity-90"><?php echo $position_name; ?> • Barangay Justice System</p>
                <p class="text-sm opacity-80 mt-2">Your mediation expertise helps maintain peace and harmony in the community.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $stats['success_rate'] ?? 0; ?>%</div>
                        <div class="text-xs opacity-80">Success Rate</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $stats['assigned_cases'] ?? 0; ?></div>
                        <div class="text-xs opacity-80">Active Cases</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Assigned Cases</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['assigned_cases'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-gavel text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Require mediation</span>
                    <span class="font-medium text-blue-600">View All →</span>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Successful (30d)</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['successful_mediations'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="fas fa-handshake text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Amicable settlements</span>
                    <span class="font-medium text-green-600"><?php echo $stats['success_rate'] ?? 0; ?>% rate</span>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Upcoming Sessions</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['upcoming_sessions'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Scheduled hearings</span>
                    <a href="?module=mediation_scheduling" class="font-medium text-yellow-600">View Calendar →</a>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Avg. Resolution Time</p>
                    <p class="text-2xl font-bold text-gray-800">7 days</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-clock text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Faster than average</span>
                    <span class="font-medium text-purple-600">Good work!</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Cases & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Assigned Cases -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Recent Assigned Cases</h3>
                    <a href="?module=case_mediation" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All →</a>
                </div>
                
                <div class="space-y-4">
                    <?php if (!empty($recent_cases)): ?>
                        <?php foreach ($recent_cases as $case): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                        <i class="fas fa-scale-balanced text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">Case #<?php echo $case['report_number']; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $case['type_name']; ?></p>
                                        <p class="text-xs text-gray-400">Filed by <?php echo $case['complainant_fname'] . ' ' . $case['complainant_lname']; ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="status-badge status-pending">Pending</span>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo date('M d, Y', strtotime($case['case_date'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-3"></i>
                            <p>No cases assigned yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="space-y-6">
            <!-- Start New Mediation -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="?module=case_mediation" class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                        <i class="fas fa-handshake mr-3"></i>
                        <span>Start New Mediation</span>
                    </a>
                    <a href="?module=mediation_scheduling" class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100">
                        <i class="fas fa-calendar-plus mr-3"></i>
                        <span>Schedule Hearing</span>
                    </a>
                    <a href="?module=settlement_document" class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100">
                        <i class="fas fa-file-contract mr-3"></i>
                        <span>Generate Settlement</span>
                    </a>
                </div>
            </div>

            <!-- Performance Snapshot -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Performance Snapshot</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Mediation Success Rate</span>
                            <span class="font-medium"><?php echo $stats['success_rate'] ?? 0; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill bg-green-500" style="width: <?php echo $stats['success_rate'] ?? 0; ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Case Resolution Time</span>
                            <span class="font-medium">7 days avg</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill bg-blue-500" style="width: 70%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Party Satisfaction</span>
                            <span class="font-medium">92%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill bg-yellow-500" style="width: 92%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Barangay Announcements</h3>
            <span class="text-sm text-gray-500">Latest updates</span>
        </div>
        
        <?php
        $announce_query = "SELECT * FROM announcements 
                          WHERE (target_role = 'lupon' OR target_role = 'all')
                          AND is_active = 1
                          ORDER BY created_at DESC 
                          LIMIT 3";
        $announce_stmt = $conn->prepare($announce_query);
        $announce_stmt->execute();
        $announcements = $announce_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="space-y-4">
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $announce): ?>
                    <div class="flex items-start p-4 border border-gray-200 rounded-lg">
                        <div class="mr-4">
                            <div class="w-10 h-10 rounded-lg bg-<?php echo $announce['priority'] == 'high' ? 'red' : ($announce['priority'] == 'medium' ? 'yellow' : 'blue'); ?>-100 flex items-center justify-center">
                                <i class="fas fa-<?php echo $announce['is_emergency'] ? 'exclamation-triangle' : 'bullhorn'; ?> text-<?php echo $announce['priority'] == 'high' ? 'red' : ($announce['priority'] == 'medium' ? 'yellow' : 'blue'); ?>-600"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($announce['title']); ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars(substr($announce['content'], 0, 120)) . '...'; ?></p>
                            <div class="flex items-center mt-2 text-xs text-gray-500">
                                <span>Posted by <?php echo htmlspecialchars($announce['posted_by']); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo date('M d, Y', strtotime($announce['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-bullhorn text-3xl mb-3"></i>
                    <p>No announcements</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>