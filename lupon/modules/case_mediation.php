<?php
// lupon/modules/case_mediation.php

// Fetch assigned cases
$assigned_cases_query = "SELECT r.*, 
                                u.first_name as complainant_fname, 
                                u.last_name as complainant_lname,
                                u.contact_number,
                                rt.type_name,
                                r.created_at as case_date,
                                r.status as case_status
                         FROM reports r
                         JOIN users u ON r.user_id = u.id
                         JOIN report_types rt ON r.report_type_id = rt.id
                         WHERE r.assigned_lupon = :lupon_id
                         AND r.status IN ('pending', 'assigned', 'in_mediation', 'investigating')
                         ORDER BY r.priority DESC, r.created_at ASC";
$assigned_cases_stmt = $conn->prepare($assigned_cases_query);
$assigned_cases_stmt->execute([':lupon_id' => $user_id]);
$assigned_cases = $assigned_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Case Mediation Desk</h2>
            <p class="text-gray-600">Manage assigned cases for mediation and conciliation</p>
        </div>
        <div class="mt-4 md:mt-0">
            <div class="flex space-x-3">
                <button onclick="showAllCases()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-eye mr-2"></i> View All Cases
                </button>
                <button onclick="showMediationGuide()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-book mr-2"></i> Mediation Guide
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo count($assigned_cases); ?></div>
                <div class="text-sm text-gray-600">Total Assigned</div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo count(array_filter($assigned_cases, fn($c) => $c['case_status'] == 'in_mediation')); ?>
                </div>
                <div class="text-sm text-gray-600">In Mediation</div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border">
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600">
                    <?php echo count(array_filter($assigned_cases, fn($c) => $c['priority'] == 'high' || $c['priority'] == 'critical')); ?>
                </div>
                <div class="text-sm text-gray-600">High Priority</div>
            </div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border">
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">
                    <?php echo count(array_filter($assigned_cases, fn($c) => strtotime($c['case_date']) < strtotime('-7 days'))); ?>
                </div>
                <div class="text-sm text-gray-600">Overdue</div>
            </div>
        </div>
    </div>

    <!-- Cases Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800">Assigned Cases for Mediation</h3>
            <p class="text-sm text-gray-600">Click on any case to view details and start mediation</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parties</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($assigned_cases)): ?>
                        <?php foreach ($assigned_cases as $case): ?>
                            <?php
                            // Get involved persons count
                            $involved_count = !empty($case['involved_persons']) ? 
                                count(json_decode($case['involved_persons'], true) ?? []) : 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <a href="#" onclick="viewCaseDetails(<?php echo $case['id']; ?>)" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <?php echo $case['report_number']; ?>
                                            </a>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo $case['type_name']; ?></div>
                                        <div class="text-xs text-gray-400">
                                            Filed: <?php echo date('M d, Y', strtotime($case['case_date'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $case['complainant_fname'] . ' ' . $case['complainant_lname']; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo $involved_count; ?> involved parties
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        📞 <?php echo $case['contact_number']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($case['case_status']) {
                                        case 'in_mediation': $status_class = 'status-in-mediation'; $status_text = 'In Mediation'; break;
                                        case 'assigned': $status_class = 'status-pending'; $status_text = 'Assigned'; break;
                                        case 'pending': $status_class = 'status-pending'; $status_text = 'Pending'; break;
                                        default: $status_class = 'status-pending'; $status_text = $case['case_status'];
                                    }
                                    ?>
                                    <span class="mediation-status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $priority_class = '';
                                    switch($case['priority']) {
                                        case 'critical': $priority_class = 'bg-red-100 text-red-800'; break;
                                        case 'high': $priority_class = 'bg-orange-100 text-orange-800'; break;
                                        case 'medium': $priority_class = 'bg-yellow-100 text-yellow-800'; break;
                                        default: $priority_class = 'bg-blue-100 text-blue-800';
                                    }
                                    ?>
                                    <span class="px-3 py-1 text-xs rounded-full <?php echo $priority_class; ?>">
                                        <?php echo ucfirst($case['priority']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="startMediation(<?php echo $case['id']; ?>)" 
                                                class="px-3 py-1 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">
                                            <i class="fas fa-handshake mr-1"></i> Mediate
                                        </button>
                                        <button onclick="viewCaseDetails(<?php echo $case['id']; ?>)" 
                                                class="px-3 py-1 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        <button onclick="downloadCase(<?php echo $case['id']; ?>)" 
                                                class="px-3 py-1 bg-gray-600 text-white text-xs rounded-lg hover:bg-gray-700">
                                            <i class="fas fa-download mr-1"></i> Docs
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-3"></i>
                                <p>No cases assigned for mediation</p>
                                <p class="text-sm mt-2">Cases will appear here when assigned by the Secretary</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo min(count($assigned_cases), 10); ?></span> of <span class="font-medium"><?php echo count($assigned_cases); ?></span> cases
                </div>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Previous</button>
                    <button class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">1</button>
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Case Details Modal -->
    <div id="caseDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-bold text-gray-800">Case Details</h3>
                <button onclick="closeCaseDetails()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[70vh]" id="caseDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="p-6 border-t">
                <div class="flex justify-end space-x-3">
                    <button onclick="closeCaseDetails()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Close
                    </button>
                    <button onclick="startMediationFromModal()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-handshake mr-2"></i> Start Mediation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Start Mediation Modal -->
    <div id="startMediationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Start Mediation Session</h3>
                <button onclick="closeStartMediation()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="case_id" id="mediationCaseId">
                <input type="hidden" name="start_mediation" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mediation Date & Time</label>
                        <input type="datetime-local" name="mediation_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                        <select name="location" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="barangay_hall">Barangay Hall - Mediation Room</option>
                            <option value="community_center">Community Center</option>
                            <option value="virtual">Virtual Meeting (Zoom)</option>
                            <option value="neutral_location">Neutral Location (Specify in notes)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mediation Notes</label>
                        <textarea name="mediation_notes" rows="4" 
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                  placeholder="Enter initial observations, concerns, or special considerations..."></textarea>
                    </div>
                    
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Remember:</strong> As Lupon member, maintain neutrality and confidentiality throughout the mediation process.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeStartMediation()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Schedule Mediation Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentCaseId = null;

function viewCaseDetails(caseId) {
    currentCaseId = caseId;
    fetch('../ajax/get_case_details.php?case_id=' + caseId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('caseDetailsContent').innerHTML = html;
            document.getElementById('caseDetailsModal').classList.remove('hidden');
            document.getElementById('caseDetailsModal').classList.add('flex');
        });
}

function closeCaseDetails() {
    document.getElementById('caseDetailsModal').classList.add('hidden');
    document.getElementById('caseDetailsModal').classList.remove('flex');
}

function startMediation(caseId) {
    currentCaseId = caseId;
    document.getElementById('mediationCaseId').value = caseId;
    
    // Set default mediation date to tomorrow 9 AM
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);
    
    const formattedDate = tomorrow.toISOString().slice(0, 16);
    document.querySelector('input[name="mediation_date"]').value = formattedDate;
    
    document.getElementById('startMediationModal').classList.remove('hidden');
    document.getElementById('startMediationModal').classList.add('flex');
}

function closeStartMediation() {
    document.getElementById('startMediationModal').classList.add('hidden');
    document.getElementById('startMediationModal').classList.remove('flex');
}

function startMediationFromModal() {
    closeCaseDetails();
    startMediation(currentCaseId);
}

function downloadCase(caseId) {
    window.open('../ajax/download_report.php?id=' + caseId, '_blank');
}

function showMediationGuide() {
    alert('Mediation Guide:\n\n1. Maintain neutrality at all times\n2. Ensure confidentiality\n3. Allow equal speaking time\n4. Focus on interests, not positions\n5. Document all agreements\n\nRefer to Lupon Manual for complete guidelines.');
}
</script>