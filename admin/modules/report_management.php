<?php
// admin/modules/report_management.php - WITNESS AND COMMUNITY REPORT MANAGEMENT MODULE

// Get raw citizen reports pending verification
$reports_query = "SELECT r.*, u.first_name as reporter_first, u.last_name as reporter_last,
                         u.contact_number as reporter_contact,
                         rt.type_name as incident_type,
                         r.needs_verification, r.urgency_level, r.routing_status
                  FROM reports r
                  LEFT JOIN users u ON r.user_id = u.id
                  LEFT JOIN report_types rt ON r.report_type_id = rt.id
                  WHERE r.needs_verification = 1 OR r.routing_status IN ('pending', 'assigned', 'needs_verification')
                  ORDER BY r.priority DESC, r.created_at DESC";
$reports_stmt = $conn->prepare($reports_query);
$reports_stmt->execute();
$raw_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get routing log
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

// Get user roles for routing
$roles_query = "SELECT id, first_name, last_name, role FROM users 
                WHERE role IN ('tanod', 'secretary') AND is_active = 1
                ORDER BY role, first_name";
$roles_stmt = $conn->prepare($roles_query);
$roles_stmt->execute();
$available_users = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
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
                    <p class="text-sm font-medium text-gray-500">Routed to Tanod</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($raw_reports, fn($r) => $r['routing_status'] === 'routed_tanod')); ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-user-shield text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Field check required
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Routed to Secretary</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($raw_reports, fn($r) => $r['routing_status'] === 'routed_secretary')); ?>
                    </h3>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-user-tie text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                For administrative action
            </div>
        </div>
    </div>
    
    <!-- Raw Report Queue -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Raw Citizen Report Queue</h2>
            <div class="text-sm text-gray-500">
                <?php echo count($raw_reports); ?> reports pending action
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Incident Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urgency</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($raw_reports as $report): ?>
                        <tr class="hover:bg-gray-50" id="reportRow-<?php echo $report['id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($report['report_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                        <span class="text-gray-600 font-medium text-xs">
                                            <?php echo strtoupper(substr($report['reporter_first'] ?? 'C', 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($report['reporter_first'] . ' ' . $report['reporter_last']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($report['reporter_contact'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($report['incident_type'] ?? 'Unknown'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge <?php echo $report['priority'] === 'high' ? 'status-warning' : 
                                                                 ($report['priority'] === 'critical' ? 'status-error' : 'status-success'); ?>">
                                    <?php echo ucfirst($report['priority']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($report['urgency_level'] ?? 'Normal'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge <?php echo $report['needs_verification'] ? 'status-pending' : 'status-success'; ?>">
                                    <?php echo $report['needs_verification'] ? 'Needs Verify' : 'Verified'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, H:i', strtotime($report['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewReportDetails(<?php echo $report['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="routeReport(<?php echo $report['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-route"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                                <span class="status-badge <?php echo $log['status'] === 'completed' ? 'status-success' : 'status-pending'; ?>">
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
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Route To</label>
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
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Urgency Level</label>
                <select name="urgency_level" 
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgent</option>
                    <option value="critical">Critical</option>
                </select>
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

<script>
let currentReportId = null;

function viewReportDetails(reportId) {
    fetch(`handlers/get_report_details.php?id=${reportId}`)
        .then(response => response.json())
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
                        <span class="status-badge ${report.needs_verification ? 'status-pending' : 'status-success'}">
                            ${report.needs_verification ? 'Needs Verification' : 'Verified'}
                        </span>
                        <span class="status-badge ${report.routing_status === 'pending' ? 'status-warning' : 'status-success'}">
                            ${report.routing_status || 'Not Routed'}
                        </span>
                    </div>
                </div>
            `;
            
            document.getElementById('reportDetails').innerHTML = detailsHtml;
            document.getElementById('reportModal').classList.remove('hidden');
            document.getElementById('reportModal').classList.add('flex');
        });
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Report routed successfully!');
            closeRoutingModal();
            // Update the row in the table
            const row = document.getElementById(`reportRow-${currentReportId}`);
            if (row) {
                row.querySelector('.routing-status').textContent = data.new_status;
            }
        } else {
            alert('Error: ' + data.message);
        }
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