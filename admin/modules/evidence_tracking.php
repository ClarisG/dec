<?php
// admin/modules/evidence_tracking.php - EVIDENCE TRACKING MODULE

// Handle evidence approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_evidence'])) {
    try {
        $handover_id = $_POST['handover_id'];
        $user_id = $_SESSION['user_id'] ?? null;
        
        $update_query = "UPDATE evidence_handovers 
                        SET recipient_acknowledged = 1, 
                            handover_date = NOW(),
                            updated_at = NOW()
                        WHERE id = :id";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([':id' => $handover_id]);
        
        // Create activity log
        $activity_query = "INSERT INTO activity_logs (user_id, action, description, ip_address)
                          VALUES (:user_id, 'evidence_approved', 'Approved evidence handover #{$handover_id}', :ip)";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->execute([
            ':user_id' => $user_id,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        $_SESSION['success'] = "Evidence approved successfully!";
        header("Location: ?module=evidence_tracking");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error approving evidence: " . $e->getMessage();
    }
}

// Get evidence statistics
$evidence_stats_query = "SELECT 
    COUNT(*) as total_items,
    COUNT(CASE WHEN decryption_count > 0 THEN 1 END) as accessed_items,
    COUNT(DISTINCT report_id) as cases_with_evidence
    FROM file_encryption_logs";
$evidence_stats_stmt = $conn->prepare($evidence_stats_query);
$evidence_stats_stmt->execute();
$evidence_stats = $evidence_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent evidence handovers
// Get recent evidence handovers
$handovers_query = "SELECT eh.*, u1.first_name as tanod_name, u1.last_name as tanod_last, 
                           u2.first_name as recipient_name, u2.last_name as recipient_last,
                           r.report_number, f.original_name, f.file_path,
                           f.report_id as file_report_id
                   FROM evidence_handovers eh
                   LEFT JOIN users u1 ON eh.tanod_id = u1.id
                   LEFT JOIN users u2 ON eh.handover_to = u2.id
                   LEFT JOIN file_encryption_logs f ON eh.file_id = f.id
                   LEFT JOIN reports r ON f.report_id = r.id
                   ORDER BY eh.created_at DESC 
                   LIMIT 10";
$handovers_stmt = $conn->prepare($handovers_query);
$handovers_stmt->execute();
$recent_handovers = $handovers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get encryption key audit log
$key_audit_query = "SELECT fel.*, u.first_name, u.last_name, r.report_number
                   FROM file_encryption_logs fel
                   LEFT JOIN users u ON fel.last_decrypted_by = u.id
                   LEFT JOIN reports r ON fel.report_id = r.id
                   WHERE fel.decryption_count > 0
                   ORDER BY fel.last_decrypted DESC 
                   LIMIT 10";
$key_audit_stmt = $conn->prepare($key_audit_query);
$key_audit_stmt->execute();
$key_audits = $key_audit_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Evidence Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Evidence Items</p>
                    <h3 id="totalEvidenceItems" class="text-3xl font-bold text-gray-800">
                        <?php echo $evidence_stats['total_items'] ?? 0; ?>
                    </h3>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-box text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <?php echo $evidence_stats['cases_with_evidence'] ?? 0; ?> cases with evidence
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Accessed Evidence</p>
                    <h3 class="text-3xl font-bold text-gray-800">
                        <?php echo $evidence_stats['accessed_items'] ?? 0; ?>
                    </h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-unlock text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Chain of custody maintained
            </div>
        </div>
        
        <div class="stat-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Handovers</p>
                    <h3 id="pendingHandovers" class="text-3xl font-bold text-gray-800">
                        <?php echo count(array_filter($recent_handovers, fn($h) => !$h['recipient_acknowledged'])); ?>
                    </h3>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-exchange-alt text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="text-sm text-gray-600">
                Awaiting recipient confirmation
            </div>
        </div>
    </div>
    
    <!-- Evidence Chain of Custody -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Chain of Custody Log</h2>
            <button onclick="showAddEvidenceModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                <i class="fas fa-plus mr-2"></i>Add Evidence
            </button>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evidence ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Handover From</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Handover To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($recent_handovers as $handover): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                EVD-<?php echo str_pad($handover['id'], 6, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($handover['report_number'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars(substr($handover['item_description'] ?? 'No description', 0, 50)); ?>
                                <?php if (strlen($handover['item_description'] ?? '') > 50): ?>...<?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars(($handover['tanod_name'] ?? 'Unknown') . ' ' . ($handover['tanod_last'] ?? '')); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars(($handover['recipient_name'] ?? 'Not Assigned') . ' ' . ($handover['recipient_last'] ?? '')); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $handover['handover_date'] ? date('M d, H:i', strtotime($handover['handover_date'])) : 'Pending'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $handover['recipient_acknowledged'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $handover['recipient_acknowledged'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewEvidenceDetails(<?php echo $handover['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900 mr-3 px-2 py-1 hover:bg-purple-50 rounded">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if (!$handover['recipient_acknowledged']): ?>
                                    <form method="POST" action="" class="inline-block">
                                        <input type="hidden" name="handover_id" value="<?php echo $handover['id']; ?>">
                                        <button type="submit" name="approve_evidence" 
                                                class="text-green-600 hover:text-green-900 px-2 py-1 hover:bg-green-50 rounded">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Encryption Key Audit -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Encryption Key Access Audit</h3>
        
        <div class="space-y-4">
            <?php if (!empty($key_audits)): ?>
                <?php foreach($key_audits as $audit): ?>
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($audit['report_number']); ?></span>
                                <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($audit['original_name']); ?></p>
                            </div>
                            <span class="text-sm font-medium text-gray-700">
                                <?php echo $audit['decryption_count']; ?> access<?php echo $audit['decryption_count'] > 1 ? 'es' : ''; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm text-gray-500">
                            <div>
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($audit['first_name'] . ' ' . $audit['last_name']); ?>
                            </div>
                            <div>
                                Last accessed: <?php echo $audit['last_decrypted'] ? date('M d, H:i', strtotime($audit['last_decrypted'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-lock text-3xl mb-2"></i>
                    <p>No encryption key access logs found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Evidence Details Modal -->
<div id="evidenceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="evidenceModalTitle" class="text-xl font-bold text-gray-800">Evidence Details</h3>
            <button onclick="closeEvidenceModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="evidenceDetails" class="space-y-4"></div>
    </div>
</div>

<script>
function viewEvidenceDetails(id){
    // Simple AJAX call to fetch evidence details
    fetch('../ajax/get_evidence_details.php?id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(evidence => {
            const title = `Evidence: EVD-${String(id).padStart(6,'0')}`;
            document.getElementById('evidenceModalTitle').textContent = title;
            
            const html = `
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Report #: <span class="font-medium">${evidence.report_number || 'N/A'}</span></p>
                            <p class="text-sm text-gray-600">Evidence ID: <span class="font-medium">EVD-${String(id).padStart(6,'0')}</span></p>
                            <p class="text-sm text-gray-600">Item Type: <span class="font-medium">${evidence.item_type || 'N/A'}</span></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Handover From: <span class="font-medium">${evidence.tanod_name || 'Unknown'} ${evidence.tanod_last || ''}</span></p>
                            <p class="text-sm text-gray-600">Handover To: <span class="font-medium">${evidence.recipient_name || 'Not Assigned'} ${evidence.recipient_last || ''}</span></p>
                            <p class="text-sm text-gray-600">Date: <span class="font-medium">${new Date(evidence.created_at).toLocaleString() || 'N/A'}</span></p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-2">Item Description:</p>
                        <p class="text-sm text-gray-600">${evidence.item_description || 'No description available'}</p>
                    </div>
                    
                    ${evidence.original_name ? `
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-2">Attached File:</p>
                        <p class="text-sm text-gray-600 mb-2">${evidence.original_name}</p>
                        ${evidence.file_path ? `
                            <a href="${evidence.file_path}" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                <i class="fas fa-external-link-alt mr-2"></i>View File
                            </a>
                        ` : '<p class="text-sm text-red-500">File not available</p>'}
                    </div>
                    ` : ''}
                    
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm text-gray-600">Status:</p>
                            <span class="px-3 py-1 rounded-full text-xs font-medium ${evidence.recipient_acknowledged ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${evidence.recipient_acknowledged ? 'Confirmed' : 'Pending'}
                            </span>
                        </div>
                        ${!evidence.recipient_acknowledged ? `
                        <form method="POST" action="">
                            <input type="hidden" name="handover_id" value="${id}">
                            <button type="submit" name="approve_evidence" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                <i class="fas fa-check mr-1"></i>Approve Handover
                            </button>
                        </form>
                        ` : ''}
                    </div>
                </div>
            `;
            
            document.getElementById('evidenceDetails').innerHTML = html;
            document.getElementById('evidenceModal').classList.remove('hidden');
            document.getElementById('evidenceModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error loading evidence details:', error);
            alert('Unable to load evidence details. Please try again.');
        });
}

function closeEvidenceModal(){
    document.getElementById('evidenceModal').classList.add('hidden');
    document.getElementById('evidenceModal').classList.remove('flex');
}

// Create AJAX endpoint if it doesn't exist
function showAddEvidenceModal() {
    alert('Add Evidence feature coming soon!');
}
</script>