<?php
// admin/modules/evidence_tracking.php - EVIDENCE TRACKING MODULE

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
$handovers_query = "SELECT eh.*, u1.first_name as tanod_name, u2.first_name as recipient_name
                   FROM evidence_handovers eh
                   LEFT JOIN users u1 ON eh.tanod_id = u1.id
                   LEFT JOIN users u2 ON eh.handover_to = u2.id
                   ORDER BY eh.handover_date DESC 
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
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evidence ID</th>
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
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars(substr($handover['item_description'], 0, 50)); ?>
                                <?php if (strlen($handover['item_description']) > 50): ?>...<?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($handover['tanod_name'] ?? 'Unknown'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($handover['recipient_name'] ?? 'Not Assigned'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $handover['handover_date'] ? date('M d, H:i', strtotime($handover['handover_date'])) : 'Pending'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge <?php echo $handover['recipient_acknowledged'] ? 'status-success' : 'status-pending'; ?>">
                                    <?php echo $handover['recipient_acknowledged'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewEvidenceDetails(<?php echo $handover['id']; ?>)" 
                                        class="text-purple-600 hover:text-purple-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (!$handover['recipient_acknowledged']): ?>
                                    <button onclick="confirmHandover(<?php echo $handover['id']; ?>)" 
                                            class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-check"></i>
                                    </button>
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

<script>
function confirmHandover(handoverId) {
    if (confirm('Confirm that this evidence has been received?')) {
        fetch('handlers/confirm_handover.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ handover_id: handoverId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>