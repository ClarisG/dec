<?php
// lupon/modules/progress_tracker.php

// Fetch mediation progress data
$progress_query = "SELECT 
    COUNT(CASE WHEN r.status IN ('pending', 'assigned') THEN 1 END) as pending,
    COUNT(CASE WHEN r.status = 'in_mediation' THEN 1 END) as active,
    COUNT(CASE WHEN r.status = 'mediation_complete' THEN 1 END) as completed,
    COUNT(CASE WHEN r.status IN ('referred', 'closed') THEN 1 END) as closed,
    AVG(DATEDIFF(IFNULL(r.resolution_date, NOW()), r.created_at)) as avg_days
    FROM reports r
    WHERE r.assigned_lupon = :lupon_id";
$progress_stmt = $conn->prepare($progress_query);
$progress_stmt->execute([':lupon_id' => $user_id]);
$progress_data = $progress_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch recent mediation logs
$logs_query = "SELECT ml.*, r.report_number, r.title
               FROM mediation_logs ml
               JOIN reports r ON ml.report_id = r.id
               WHERE ml.lupon_id = :lupon_id
               ORDER BY ml.mediation_date DESC
               LIMIT 10";
$logs_stmt = $conn->prepare($logs_query);
$logs_stmt->execute([':lupon_id' => $user_id]);
$mediation_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Mediation Progress Tracker</h2>
            <p class="text-gray-600">Monitor mediation progress and track session outcomes</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="exportProgressReport()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-file-export mr-2"></i> Export Report
            </button>
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $progress_data['pending'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Pending Cases</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-handshake text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $progress_data['active'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Active Mediations</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $progress_data['completed'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Completed</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo round($progress_data['avg_days'] ?? 0, 1); ?></p>
                    <p class="text-sm text-gray-600">Avg. Days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Chart -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Mediation Progress Chart</h3>
        <div class="h-64 flex items-end space-x-4">
            <?php
            $statuses = ['Pending', 'Active', 'Completed', 'Closed'];
            $counts = [
                $progress_data['pending'] ?? 0,
                $progress_data['active'] ?? 0,
                $progress_data['completed'] ?? 0,
                $progress_data['closed'] ?? 0
            ];
            $max_count = max($counts) ?: 1;
            $colors = ['bg-yellow-500', 'bg-blue-500', 'bg-green-500', 'bg-purple-500'];
            
            for ($i = 0; $i < count($statuses); $i++):
                $height = ($counts[$i] / $max_count) * 80;
            ?>
                <div class="flex-1 text-center">
                    <div class="mb-2">
                        <div class="text-lg font-bold text-gray-800"><?php echo $counts[$i]; ?></div>
                        <div class="text-xs text-gray-500"><?php echo $statuses[$i]; ?></div>
                    </div>
                    <div class="w-full mx-auto">
                        <div class="<?php echo $colors[$i]; ?> rounded-t-lg transition-all duration-500" 
                             style="height: <?php echo $height; ?>%; max-height: 80%;"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Recent Mediation Logs -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800">Recent Mediation Sessions</h3>
            <p class="text-sm text-gray-600">Log of recent mediation activities and outcomes</p>
        </div>
        
        <div class="divide-y divide-gray-200">
            <?php if (!empty($mediation_logs)): ?>
                <?php foreach ($mediation_logs as $log): ?>
                    <div class="p-6 hover:bg-gray-50">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-start">
                                    <div class="mr-4">
                                        <div class="w-10 h-10 rounded-lg <?php 
                                            echo $log['status'] == 'completed' ? 'bg-green-100 text-green-600' :
                                            ($log['status'] == 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600'); 
                                        ?> flex items-center justify-center">
                                            <i class="fas fa-<?php 
                                                echo $log['status'] == 'completed' ? 'check' :
                                                ($log['status'] == 'cancelled' ? 'times' : 'clock'); 
                                            ?>"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800"><?php echo $log['title']; ?></h4>
                                        <div class="flex items-center mt-2 text-sm text-gray-600">
                                            <i class="fas fa-hashtag mr-2 text-xs"></i>
                                            <span class="mr-4"><?php echo $log['report_number']; ?></span>
                                            <i class="fas fa-calendar-alt mr-2 text-xs"></i>
                                            <span><?php echo date('M d, Y h:i A', strtotime($log['mediation_date'])); ?></span>
                                        </div>
                                        <?php if (!empty($log['notes'])): ?>
                                            <p class="text-sm text-gray-600 mt-2"><?php echo substr($log['notes'], 0, 150); ?>...</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <span class="mediation-status <?php 
                                    echo $log['status'] == 'completed' ? 'status-completed' :
                                    ($log['status'] == 'cancelled' ? 'status-cancelled' :
                                    ($log['status'] == 'scheduled' ? 'status-scheduled' : 'status-ongoing')); 
                                ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($log['outcome'])): ?>
                            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-flag-checkered text-blue-500 mr-2"></i>
                                    <span class="font-medium text-blue-700">Outcome:</span>
                                    <span class="ml-2 text-blue-600"><?php echo $log['outcome']; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-3xl mb-3"></i>
                    <p>No mediation logs available</p>
                    <p class="text-sm mt-2">Mediation logs will appear here after sessions</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Log Entry -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Add Mediation Log Entry</h3>
        <form method="POST" action="../handlers/add_mediation_log.php">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Case Number</label>
                    <select name="case_id" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select Case</option>
                        <?php
                        $cases_query = "SELECT id, report_number, title FROM reports WHERE assigned_lupon = :lupon_id";
                        $cases_stmt = $conn->prepare($cases_query);
                        $cases_stmt->execute([':lupon_id' => $user_id]);
                        $cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($cases as $case): ?>
                            <option value="<?php echo $case['id']; ?>">
                                <?php echo $case['report_number'] . ' - ' . $case['title']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Session Date</label>
                    <input type="datetime-local" name="session_date" required 
                           class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" required 
                              class="w-full p-3 border border-gray-300 rounded-lg"
                              placeholder="Enter mediation session notes..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Outcome</label>
                    <select name="outcome" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select Outcome</option>
                        <option value="settled">Settled</option>
                        <option value="partial_settlement">Partial Settlement</option>
                        <option value="referred">Referred to Captain</option>
                        <option value="continued">Continued</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Next Steps</label>
                    <input type="text" name="next_steps" 
                           class="w-full p-3 border border-gray-300 rounded-lg"
                           placeholder="Enter follow-up actions...">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i> Save Log Entry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function exportProgressReport() {
    const startDate = prompt('Enter start date (YYYY-MM-DD):', '<?php echo date('Y-m-01'); ?>');
    const endDate = prompt('Enter end date (YYYY-MM-DD):', '<?php echo date('Y-m-d'); ?>');
    
    if (startDate && endDate) {
        window.open('../ajax/export_progress_report.php?start=' + startDate + '&end=' + endDate, '_blank');
    }
}
</script>