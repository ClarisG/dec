<?php
// admin/modules/report_management.php - WITNESS AND COMMUNITY REPORT MANAGEMENT MODULE

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Count total records for pagination
$count_query = "SELECT COUNT(*) FROM reports r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.needs_verification = 1 
                OR r.status IN ('pending', 'pending_field_verification')";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute();
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));

// Adjust page if out of bounds
if ($page > $total_pages && $total_records > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Get raw citizen reports pending verification (FIXED: removed urgency_level reference)
$reports_query = "SELECT r.*, u.first_name as reporter_first, u.last_name as reporter_last,
                         u.contact_number as reporter_contact,
                         rt.type_name as incident_type,
                         r.needs_verification
                  FROM reports r
                  LEFT JOIN users u ON r.user_id = u.id
                  LEFT JOIN report_types rt ON r.report_type_id = rt.id
                  WHERE r.needs_verification = 1 
                  OR r.status IN ('pending', 'pending_field_verification')
                  ORDER BY r.priority DESC, r.created_at DESC
                  LIMIT :limit OFFSET :offset";
$reports_stmt = $conn->prepare($reports_query);
$reports_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$reports_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$reports_stmt->execute();
$raw_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get routing log
try {
    $routing_log_query = "SELECT rl.*, u1.first_name as from_user, u2.first_name as to_user, 
                                 r.report_number
                          FROM report_routing_logs rl
                          LEFT JOIN users u1 ON rl.routed_by = u1.id
                          LEFT JOIN users u2 ON rl.routed_to = u2.id
                          LEFT JOIN reports r ON rl.report_id = r.id
                          ORDER BY rl.routed_at DESC 
                          LIMIT 20";
    $routing_log_stmt = $conn->prepare($routing_log_query);
    $routing_log_stmt->execute();
    $routing_logs = $routing_log_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if we got results
    if (empty($routing_logs)) {
        error_log("Routing logs query executed but returned empty results");
    }
} catch (PDOException $e) {
    // Log the detailed error
    error_log("Routing log query failed: " . $e->getMessage());
    
    // Try a simpler query to debug
    try {
        $simple_query = "SELECT COUNT(*) as count FROM report_routing_logs";
        $simple_stmt = $conn->prepare($simple_query);
        $simple_stmt->execute();
        $count = $simple_stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Table exists with " . $count['count'] . " records");
        
        // Try to get table structure
        $structure_query = "SHOW COLUMNS FROM report_routing_logs";
        $structure_stmt = $conn->prepare($structure_query);
        $structure_stmt->execute();
        $columns = $structure_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Table columns: " . print_r($columns, true));
    } catch (PDOException $e2) {
        error_log("Even simple query failed: " . $e2->getMessage());
    }
    
    // Set empty array to prevent fatal error
    $routing_logs = [];
}

// Get user roles for routing
$roles_query = "SELECT id, first_name, last_name, role FROM users 
                WHERE role IN ('tanod', 'secretary') AND is_active = 1
                ORDER BY role, first_name";
$roles_stmt = $conn->prepare($roles_query);
$roles_stmt->execute();
$available_users = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for charts
$chart_stats_query = "SELECT 
    DATE(r.created_at) as report_date,
    COUNT(*) as count,
    rp.type_name,
    rp.jurisdiction
    FROM reports r
    LEFT JOIN report_types rp ON r.report_type_id = rp.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(r.created_at), rp.type_name, rp.jurisdiction
    ORDER BY r.created_at DESC";
$chart_stats_stmt = $conn->prepare($chart_stats_query);
$chart_stats_stmt->execute();
$chart_data = $chart_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$daily_data = [];
$type_data = [];
$jurisdiction_data = [];

foreach ($chart_data as $row) {
    $date = $row['report_date'];
    $type = $row['type_name'] ?: 'Unknown';
    $jurisdiction = $row['jurisdiction'] ?: 'Unknown';
    
    // Daily data
    if (!isset($daily_data[$date])) {
        $daily_data[$date] = 0;
    }
    $daily_data[$date] += $row['count'];
    
    // Type data
    if (!isset($type_data[$type])) {
        $type_data[$type] = 0;
    }
    $type_data[$type] += $row['count'];
    
    // Jurisdiction data
    if (!isset($jurisdiction_data[$jurisdiction])) {
        $jurisdiction_data[$jurisdiction] = 0;
    }
    $jurisdiction_data[$jurisdiction] += $row['count'];
}

// Sort data
krsort($daily_data);
arsort($type_data);
arsort($jurisdiction_data);

// Take top 10 types for readability
$type_data = array_slice($type_data, 0, 10, true);
?>
<!-- Add CSS for responsive table -->
<style>
    @media (max-width: 1024px) {
        .responsive-table-container {
            position: relative;
        }
        .responsive-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        .responsive-table thead {
            display: table-header-group;
        }
        .responsive-table tbody {
            display: table-row-group;
        }
        .responsive-table tr {
            display: table-row;
        }
        .responsive-table th,
        .responsive-table td {
            display: table-cell;
            min-width: 120px;
        }
        /* Ensure actions column stays visible */
        .responsive-table td:last-child {
            position: sticky;
            right: 0;
            background: white;
            min-width: 150px;
        }
    }
</style>

<div class="space-y-6">
    <!-- Report Queue Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Verification</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($raw_reports, fn($r) => $r['needs_verification'] == 1)); ?>
                    </h3>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Awaiting initial review
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">High Priority</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($raw_reports, fn($r) => $r['priority'] === 'high' || $r['priority'] === 'critical')); ?>
                    </h3>
                </div>
                <div class="p-3 bg-red-100 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Urgent attention needed
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Reports</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count($raw_reports); ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                All pending reports
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today's Reports</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($raw_reports, fn($r) => date('Y-m-d', strtotime($r['created_at'])) == date('Y-m-d'))); ?>
                    </h3>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Submitted today
            </div>
        </div>
    </div>
    
    <!-- Raw Report Queue -->
    <div class="bg-white rounded-xl p-6 shadow-sm responsive-table-container">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Raw Citizen Report Queue</h2>
            <div class="text-sm text-gray-500">
                <?php echo count($raw_reports); ?> reports pending action
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 responsive-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Report #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">Reporter</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Incident Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($raw_reports as $report): ?>
                        <tr class="hover:bg-gray-50" id="reportRow-<?php echo $report['id']; ?>">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <span class="font-mono text-xs"><?php echo htmlspecialchars($report['report_number']); ?></span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                        <span class="text-gray-600 font-medium text-xs">
                                            <?php echo strtoupper(substr($report['reporter_first'] ?? 'C', 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-2">
                                        <div class="text-sm font-medium text-gray-900 truncate max-w-[120px]">
                                            <?php echo htmlspecialchars($report['reporter_first'] . ' ' . $report['reporter_last']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 truncate max-w-[120px]">
                                            <?php echo htmlspecialchars($report['reporter_contact'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 truncate max-w-[120px]">
                                <?php echo htmlspecialchars($report['incident_type'] ?? 'Unknown'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-full text-xs font-medium 
                                    <?php echo $report['priority'] === 'high' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($report['priority'] === 'critical' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo ucfirst($report['priority']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-full text-xs font-medium 
                                    <?php echo $report['needs_verification'] ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $report['needs_verification'] ? 'Pending Verify' : 'Verified'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <div class="text-xs">
                                    <?php echo date('M d', strtotime($report['created_at'])); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('H:i', strtotime($report['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex space-x-1">
                                    <button onclick="viewReportDetails(<?php echo $report['id']; ?>)" 
                                            class="text-purple-600 hover:text-purple-900 px-2 py-1 hover:bg-purple-50 rounded text-xs">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button onclick="routeReport(<?php echo $report['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 px-2 py-1 hover:bg-blue-50 rounded text-xs">
                                        <i class="fas fa-route"></i> Route
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls -->
        <div class="flex items-center justify-between mt-4">
            <div class="text-sm text-gray-600">
                Showing <?php echo min($per_page, count($raw_reports)); ?> of <?php echo $total_records; ?> entries
            </div>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="?module=report_management&page=1" class="px-2 py-1 border rounded hover:bg-gray-50 text-sm">First</a>
                    <a href="?module=report_management&page=<?php echo $page - 1; ?>" class="px-2 py-1 border rounded hover:bg-gray-50 text-sm">Prev</a>
                <?php else: ?>
                    <span class="px-2 py-1 border rounded opacity-50 cursor-not-allowed text-sm">First</span>
                    <span class="px-2 py-1 border rounded opacity-50 cursor-not-allowed text-sm">Prev</span>
                <?php endif; ?>
                
                <span class="px-3 py-1 border rounded bg-purple-50 text-purple-700 font-medium text-sm">
                    Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?module=report_management&page=<?php echo $page + 1; ?>" class="px-2 py-1 border rounded hover:bg-gray-50 text-sm">Next</a>
                    <a href="?module=report_management&page=<?php echo $total_pages; ?>" class="px-2 py-1 border rounded hover:bg-gray-50 text-sm">Last</a>
                <?php else: ?>
                    <span class="px-2 py-1 border rounded opacity-50 cursor-not-allowed text-sm">Next</span>
                    <span class="px-2 py-1 border rounded opacity-50 cursor-not-allowed text-sm">Last</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reports Analytics -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 space-y-4 md:space-y-0">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-chart-line text-purple-600 mr-2"></i> Reports Analytics
            </h3>
            <div class="flex flex-wrap gap-2">
                <select id="chartRange" class="p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm">
                    <option value="daily">Daily (Last 30 days)</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <button onclick="printReportCharts()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm shadow-md transition-all duration-200 flex items-center">
                    <i class="fas fa-print mr-2"></i>Generate Report
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 shadow-sm">
                <h4 class="font-bold text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-calendar-alt text-blue-500 mr-2"></i> Submission Trend
                </h4>
                <div class="chart-container relative h-64 w-full">
                    <canvas id="dailyReportsChart"></canvas>
                </div>
            </div>
            
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 shadow-sm">
                <h4 class="font-bold text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-pink-500 mr-2"></i> Report Categories
                </h4>
                <div class="chart-container relative h-64 w-full">
                    <canvas id="reportTypesChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="mt-8 bg-gray-50 rounded-xl p-4 border border-gray-100 shadow-sm">
            <h4 class="font-bold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-map-marked-alt text-green-500 mr-2"></i> Jurisdiction Distribution
            </h4>
            <div class="chart-container relative h-64 w-full max-w-2xl mx-auto">
                <canvas id="jurisdictionChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Routing Log -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Report Routing Log</h3>
        
        <div class="space-y-3">
            <?php if (!empty($routing_logs)): ?>
                <?php foreach($routing_logs as $log): ?>
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($log['report_number']); ?></span>
                                <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($log['routing_notes'] ?? 'No notes'); ?></p>
                            </div>
                            <span class="text-sm text-gray-500">
                                <?php echo date('H:i', strtotime($log['routed_at'])); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <div>
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($log['from_user'] ?? 'System'); ?>
                                <i class="fas fa-arrow-right mx-2"></i>
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($log['to_user'] ?? 'Unknown'); ?>
                            </div>
                            <div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $log['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-exchange-alt text-3xl mb-2"></i>
                    <p>No routing logs found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalReportTitle" class="text-xl font-bold text-gray-800">Report Details</h3>
            <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="reportDetails" class="space-y-4">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Routing Modal -->
<div id="routingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Route Report</h3>
            <button onclick="closeRoutingModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="routingForm" onsubmit="submitRouting(event)">
            <input type="hidden" id="routingReportId" name="report_id">
            <input type="hidden" name="route_report" value="1">
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Route To *</label>
                <select id="routeTo" name="routed_to" required 
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Select destination...</option>
                    <optgroup label="Tanod (Field Check)">
                        <?php foreach($available_users as $user): 
                            if ($user['role'] === 'tanod'): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    Tanod <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Secretary (Administrative Action)">
                        <?php foreach($available_users as $user): 
                            if ($user['role'] === 'secretary'): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    Secretary <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Routing Notes</label>
                <textarea name="routing_notes" rows="3" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="Add instructions or notes..."></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRoutingModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Route Report
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentReportId = null;
let dailyChart, typeChart, jurisdictionChart;

function printReportCharts() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Reports Analytics Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; }
                .chart-container { margin: 20px 0; page-break-inside: avoid; }
                .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
                .stat-box { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <h1>Reports Analytics Report</h1>
            <p>Generated on: ${new Date().toLocaleString()}</p>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Pending Reports</h3>
                    <p>${document.querySelector('.stat-card:nth-child(1) h3').textContent}</p>
                </div>
                <div class="stat-box">
                    <h3>High Priority Reports</h3>
                    <p>${document.querySelector('.stat-card:nth-child(2) h3').textContent}</p>
                </div>
            </div>
            <div class="chart-container">
                <h3>Daily Reports Trend</h3>
                <img src="${document.getElementById('dailyReportsChart').toDataURL()}" width="800" height="400">
            </div>
            <div class="chart-container">
                <h3>Report Types Distribution</h3>
                <img src="${document.getElementById('reportTypesChart').toDataURL()}" width="800" height="400">
            </div>
            <div class="chart-container">
                <h3>Jurisdiction Distribution</h3>
                <img src="${document.getElementById('jurisdictionChart').toDataURL()}" width="600" height="300">
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function initializeCharts() {
    const dailyCtx = document.getElementById('dailyReportsChart').getContext('2d');
    const typeCtx = document.getElementById('reportTypesChart').getContext('2d');
    const jurisdictionCtx = document.getElementById('jurisdictionChart').getContext('2d');
    
    // Destroy existing charts if they exist
    if (dailyChart) dailyChart.destroy();
    if (typeChart) typeChart.destroy();
    if (jurisdictionChart) jurisdictionChart.destroy();
    
    // Daily Reports Chart
    const dailyLabels = Object.keys(dailyData).slice(0, 15).reverse();
    const dailyValues = dailyLabels.map(date => dailyData[date]);
    
    dailyChart = new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Number of Reports',
                data: dailyValues,
                backgroundColor: '#7c3aed',
                borderColor: '#6d28d9',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Reports'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
    
    // Report Types Chart
    const typeLabels = Object.keys(typeData);
    const typeValues = Object.values(typeData);
    
    typeChart = new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: [
                    '#7c3aed', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe',
                    '#ef4444', '#f97316', '#f59e0b', '#10b981', '#06b6d4'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
    
    // Jurisdiction Chart - Made consistent with other charts
    const jurisdictionLabels = Object.keys(jurisdictionData);
    const jurisdictionValues = Object.values(jurisdictionData);
    
    jurisdictionChart = new Chart(jurisdictionCtx, {
        type: 'pie',
        data: {
            labels: jurisdictionLabels,
            datasets: [{
                data: jurisdictionValues,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// Chart data from PHP
const dailyData = <?php echo json_encode($daily_data); ?>;
const typeData = <?php echo json_encode($type_data); ?>;
const jurisdictionData = <?php echo json_encode($jurisdiction_data); ?>;

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    
    // Handle chart range change
    document.getElementById('chartRange').addEventListener('change', function(e) {
        // In a real application, you would fetch new data based on the selected range
        console.log('Selected range:', e.target.value);
        // For now, just reinitialize with current data
        initializeCharts();
    });
    
    // Add responsive behavior to table on window resize
    window.addEventListener('resize', function() {
        // Charts will automatically resize due to Chart.js responsive option
    });
});

function viewReportDetails(reportId) {
    fetch(`ajax/get_report_details.php?id=${reportId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(report => {
            document.getElementById('modalReportTitle').textContent = `Report: ${report.report_number}`;
            
            let detailsHtml = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-bold text-gray-800 mb-2">Reporter Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Name: <span class="font-medium">${report.reporter_first} ${report.reporter_last}</span></p>
                            <p class="text-sm text-gray-600">Contact: ${report.reporter_contact || 'N/A'}</p>
                            <p class="text-sm text-gray-600">Submitted: ${new Date(report.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 mb-2">Incident Details</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Type: <span class="font-medium">${report.incident_type}</span></p>
                            <p class="text-sm text-gray-600">Priority: <span class="font-medium ${report.priority === 'high' ? 'text-red-600' : report.priority === 'critical' ? 'text-red-800' : 'text-green-600'}">${report.priority}</span></p>
                            <p class="text-sm text-gray-600">Location: ${report.location_details || 'Not specified'}</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-gray-800 mb-2">Description</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-700">${report.description || 'No description provided'}</p>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-gray-800 mb-2">Current Status</h4>
                    <div class="flex space-x-4">
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${report.needs_verification ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                            ${report.needs_verification ? 'Needs Verification' : 'Verified'}
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${report.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                            ${report.status || 'Not Routed'}
                        </span>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="verifyReport(${reportId})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i>Mark as Verified
                    </button>
                </div>
            `;
            
            document.getElementById('reportDetails').innerHTML = detailsHtml;
            document.getElementById('reportModal').classList.remove('hidden');
            document.getElementById('reportModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error fetching report details:', error);
            alert('Error loading report details. Please try again.');
        });
}

function verifyReport(reportId) {
    if (confirm('Mark this report as verified?')) {
        const formData = new FormData();
        formData.append('report_id', reportId);
        formData.append('verify_report', '1');
        
        fetch('handlers/verify_report.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Report verified successfully!');
                closeReportModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error verifying report:', error);
            alert('Error verifying report. Please try again.');
        });
    }
}

function routeReport(reportId) {
    currentReportId = reportId;
    document.getElementById('routingReportId').value = reportId;
    document.getElementById('routingForm').reset();
    document.getElementById('routingModal').classList.remove('hidden');
    document.getElementById('routingModal').classList.add('flex');
}

function submitRouting(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('handlers/route_report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Report routed successfully!');
            closeRoutingModal();
            // Update the row in the table
            const row = document.getElementById(`reportRow-${currentReportId}`);
            if (row) {
                // You could update the status cell here if needed
            }
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error routing report:', error);
        alert('Error routing report. Please try again.');
    });
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.getElementById('reportModal').classList.remove('flex');
}

function closeRoutingModal() {
    document.getElementById('routingModal').classList.add('hidden');
    document.getElementById('routingModal').classList.remove('flex');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const reportModal = document.getElementById('reportModal');
    const routingModal = document.getElementById('routingModal');
    
    if (event.target == reportModal) closeReportModal();
    if (event.target == routingModal) closeRoutingModal();
}
</script>
