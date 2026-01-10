<?php
// captain/modules/dashboard.php
?>
<div class="space-y-6">
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="kpi-card rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-gray-500 text-sm">Total Open Cases</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['open_cases'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-folder-open text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">This Month</span>
                    <span class="font-medium text-blue-600">+12%</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 65%"></div>
                </div>
            </div>
        </div>
        
        <div class="kpi-card rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-gray-500 text-sm">Compliance Rate</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2">89%</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">On 3/15 Day Rule</span>
                    <span class="font-medium text-green-600">+5%</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 89%"></div>
                </div>
            </div>
        </div>
        
        <div class="kpi-card rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-gray-500 text-sm">Referred Cases Out</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['referred_cases'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">External Agencies</span>
                    <span class="font-medium text-purple-600">+8%</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-purple-600 h-2 rounded-full" style="width: 42%"></div>
                </div>
            </div>
        </div>
        
        <div class="kpi-card rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-gray-500 text-sm">Avg Resolution Time</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2">7.2d</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">vs Target: 5 days</span>
                    <span class="font-medium text-yellow-600">+2.2d</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 72%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cases Needing Attention -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Cases Needing Attention</h3>
                    <p class="text-gray-600 text-sm">Priority cases requiring immediate action</p>
                </div>
                <a href="?module=review" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    View All
                    <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <div class="space-y-4">
                <?php if (!empty($attention_cases)): ?>
                    <?php foreach ($attention_cases as $case): ?>
                        <div class="p-4 rounded-lg border <?php echo $case['priority'] == 'critical' ? 'urgent' : ($case['priority'] == 'high' ? 'warning' : ''); ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($case['report_number']); ?></span>
                                        <span class="badge badge-<?php echo $case['priority']; ?>"><?php echo ucfirst($case['priority']); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($case['created_at'])); ?></span>
                                    </div>
                                    <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($case['title']); ?></p>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-user mr-2"></i>
                                        <span><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></span>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-clock mr-2"></i>
                                        <span><?php echo $case['days_pending'] ?? 0; ?> days pending</span>
                                    </div>
                                </div>
                                <a href="?module=review&case_id=<?php echo $case['id']; ?>" 
                                   class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition">
                                    Review
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                        <p class="text-gray-600">No cases requiring immediate attention</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Hearings -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Upcoming Hearings</h3>
                    <p class="text-gray-600 text-sm">Scheduled conciliation hearings</p>
                </div>
                <a href="?module=hearing" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    Schedule New
                    <i class="fas fa-plus ml-1"></i>
                </a>
            </div>
            
            <div class="space-y-4">
                <?php if (!empty($upcoming_hearings)): ?>
                    <?php foreach ($upcoming_hearings as $hearing): ?>
                        <div class="p-4 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($hearing['report_number']); ?></span>
                                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">Hearing</span>
                                    </div>
                                    <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($hearing['title']); ?></p>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-calendar-day mr-2"></i>
                                        <span><?php echo date('F j, Y', strtotime($hearing['hearing_date'])); ?></span>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-clock mr-2"></i>
                                        <span><?php echo date('g:i A', strtotime($hearing['hearing_time'])); ?></span>
                                    </div>
                                </div>
                                <?php 
                                $today = date('Y-m-d');
                                $hearing_date = date('Y-m-d', strtotime($hearing['hearing_date']));
                                $is_today = $hearing_date == $today;
                                ?>
                                <span class="px-3 py-1 <?php echo $is_today ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> rounded-lg text-sm">
                                    <?php echo $is_today ? 'Today' : 'Upcoming'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-alt text-gray-400 text-3xl mb-3"></i>
                        <p class="text-gray-600">No upcoming hearings scheduled</p>
                        <a href="?module=hearing" class="mt-3 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Schedule First Hearing
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Monthly Performance Chart -->
    <div class="glass-card rounded-xl p-6 mt-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Monthly Performance Overview</h3>
                <p class="text-gray-600 text-sm">Case resolution trends and compliance metrics</p>
            </div>
            <select class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option>Last 30 Days</option>
                <option>Last 90 Days</option>
                <option selected>Last 12 Months</option>
            </select>
        </div>
        
        <div class="h-64">
            <!-- Chart placeholder - In production, use Chart.js or similar -->
            <div class="w-full h-full flex items-end justify-between px-4 pb-4 border-b border-l border-gray-200">
                <?php 
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ($months as $index => $month): 
                    $height = rand(40, 80);
                    $color = $index == date('n') - 1 ? 'bg-blue-600' : 'bg-blue-400';
                ?>
                    <div class="flex flex-col items-center">
                        <div class="w-8 <?php echo $color; ?> rounded-t-lg mb-2" style="height: <?php echo $height; ?>%"></div>
                        <span class="text-xs text-gray-600"><?php echo $month; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex justify-center space-x-8">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-blue-600 rounded mr-2"></div>
                    <span class="text-sm text-gray-600">Cases Resolved</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-600">Compliance Rate</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-purple-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-600">Referred Cases</span>
                </div>
            </div>
        </div>
    </div>
</div>