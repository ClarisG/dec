<?php
// super_admin/modules/evidence_log.php

// Get evidence logs
$evidence_query = "SELECT fel.*, 
                          r.report_number,
                          r.title as report_title,
                          u.first_name as decrypted_by_first,
                          u.last_name as decrypted_by_last
                   FROM file_encryption_logs fel
                   JOIN reports r ON fel.report_id = r.id
                   LEFT JOIN users u ON fel.last_decrypted_by = u.id
                   ORDER BY fel.created_at DESC 
                   LIMIT 50";
$evidence_stmt = $conn->prepare($evidence_query);
$evidence_stmt->execute();
$evidence_logs = $evidence_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get evidence statistics
$evidence_stats_query = "SELECT 
    COUNT(*) as total_files,
    SUM(decryption_count) as total_decryptions,
    COUNT(CASE WHEN last_decrypted IS NULL THEN 1 END) as never_decrypted,
    COUNT(CASE WHEN decryption_count > 0 THEN 1 END) as accessed_files,
    MAX(last_decrypted) as last_access
    FROM file_encryption_logs";
$evidence_stats_stmt = $conn->prepare($evidence_stats_query);
$evidence_stats_stmt->execute();
$evidence_stats = $evidence_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Evidence & Encryption Master Log</h2>
                <p class="text-gray-600 mt-2">Access all encrypted files and manage evidence lifecycle</p>
            </div>
            <div class="mt-4 md:mt-0">
                <button onclick="showEncryptionKey()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-key mr-2"></i> Master Encryption Keys
                </button>
            </div>
        </div>

        <!-- Evidence Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $evidence_stats['total_files'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Encrypted Files</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo $evidence_stats['total_decryptions'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total Decryptions</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo $evidence_stats['accessed_files'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Accessed Files</div>
            </div>
            <div class="bg-red-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-red-700"><?php echo $evidence_stats['never_decrypted'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Never Accessed</div>
            </div>
        </div>
    </div>

    <!-- Evidence Log Table -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">Evidence Access Log</h3>
                <div class="flex space-x-2">
                    <input type="text" placeholder="Search evidence..." 
                           class="p-2 border border-gray-300 rounded-lg w-64"
                           onkeyup="filterEvidenceTable(this.value)">
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="evidenceTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">File</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Report</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Encryption Status</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Access History</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($evidence_logs as $evidence): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 mr-3">
                                    <?php
                                    $ext = pathinfo($evidence['original_name'], PATHINFO_EXTENSION);
                                    $icon = 'fa-file';
                                    $color = 'text-gray-600';
                                    
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $icon = 'fa-image';
                                        $color = 'text-green-600';
                                    } elseif (in_array($ext, ['pdf'])) {
                                        $icon = 'fa-file-pdf';
                                        $color = 'text-red-600';
                                    } elseif (in_array($ext, ['doc', 'docx'])) {
                                        $icon = 'fa-file-word';
                                        $color = 'text-blue-600';
                                    }
                                    ?>
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <i class="fas <?php echo $icon; ?> <?php echo $color; ?> text-lg"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800 text-sm truncate max-w-xs">
                                        <?php echo htmlspecialchars($evidence['original_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo round(filesize('../' . $evidence['file_path']) / 1024, 1); ?> KB
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div>
                                <p class="font-medium text-gray-800 text-sm">
                                    <?php echo htmlspecialchars($evidence['report_number']); ?>
                                </p>
                                <p class="text-xs text-gray-500 truncate max-w-xs">
                                    <?php echo htmlspecialchars($evidence['report_title']); ?>
                                </p>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="space-y-1">
                                <div class="flex items-center">
                                    <div class="status-indicator status-active"></div>
                                    <span class="text-xs text-green-600 ml-1">Encrypted</span>
                                </div>
                                <p class="text-xs text-gray-500">
                                    Key: <?php echo substr($evidence['encryption_key'], 0, 8) . '...'; ?>
                                </p>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="space-y-1">
                                <p class="text-sm">
                                    <span class="font-medium"><?php echo $evidence['decryption_count'] ?? 0; ?></span> accesses
                                </p>
                                <?php if ($evidence['last_decrypted']): ?>
                                <p class="text-xs text-gray-500">
                                    Last: <?php echo date('M d, H:i', strtotime($evidence['last_decrypted'])); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    By: <?php echo htmlspecialchars($evidence['decrypted_by_first'] . ' ' . $evidence['decrypted_by_last']); ?>
                                </p>
                                <?php else: ?>
                                <p class="text-xs text-gray-500">Never accessed</p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="decryptFile(<?php echo $evidence['id']; ?>)"
                                        class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm">
                                    <i class="fas fa-unlock mr-1"></i> Decrypt
                                </button>
                                <button onclick="viewFileDetails(<?php echo $evidence['id']; ?>)"
                                        class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm">
                                    <i class="fas fa-eye mr-1"></i> Details
                                </button>
                                <?php if ($evidence['decryption_count'] > 0): ?>
                                <button onclick="viewAccessLog(<?php echo $evidence['id']; ?>)"
                                        class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                                    <i class="fas fa-history mr-1"></i> Log
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chain of Custody -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Chain of Custody Management</h3>
            <button onclick="showCustodyTransfer()"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                <i class="fas fa-exchange-alt mr-2"></i> Transfer Custody
            </button>
        </div>
        
        <?php
        $custody_query = "SELECT eh.*,
                                 u1.first_name as from_first, u1.last_name as from_last,
                                 u2.first_name as to_first, u2.last_name as to_last
                          FROM evidence_handovers eh
                          JOIN users u1 ON eh.tanod_id = u1.id
                          JOIN users u2 ON eh.handover_to = u2.id
                          ORDER BY eh.handover_date DESC 
                          LIMIT 10";
        $custody_stmt = $conn->prepare($custody_query);
        $custody_stmt->execute();
        $custody_logs = $custody_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="space-y-4">
            <?php foreach ($custody_logs as $log): ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($log['item_description']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($log['item_type']); ?></p>
                    </div>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $log['recipient_acknowledged'] ? 'Acknowledged' : 'Pending'; ?>
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center mx-auto">
                                <i class="fas fa-user text-red-600 text-xs"></i>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 truncate max-w-xs">
                                <?php echo htmlspecialchars($log['from_first'] . ' ' . $log['from_last']); ?>
                            </p>
                        </div>
                        
                        <div class="text-gray-400">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mx-auto">
                                <i class="fas fa-user-check text-green-600 text-xs"></i>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 truncate max-w-xs">
                                <?php echo htmlspecialchars($log['to_first'] . ' ' . $log['to_last']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <p class="text-xs text-gray-500">
                            <?php echo date('M d, H:i', strtotime($log['handover_date'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($custody_logs)): ?>
            <div class="text-center py-8">
                <i class="fas fa-exchange-alt text-gray-300 text-3xl mb-3"></i>
                <p class="text-gray-500">No custody transfers found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showEncryptionKey() {
    const content = `
        <div class="space-y-4">
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <p class="font-medium text-red-800">Master Encryption Key</p>
                        <p class="text-sm text-red-600">This key provides unrestricted access to all encrypted files</p>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-500 mb-2">Super Admin Master Key</p>
                <div class="flex items-center">
                    <code class="flex-1 bg-white p-3 rounded border font-mono text-sm">
                        SUPER_MASTER_KEY_<?php echo strtoupper(md5($_SESSION['user_id'] . date('Ymd'))); ?>
                    </code>
                    <button onclick="copyToClipboard(this)" 
                            class="ml-3 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div class="text-xs text-gray-500">
                <p>This master key:</p>
                <ul class="list-disc list-inside mt-1">
                    <li>Decrypts all evidence files</li>
                    <li>Overrides all permission checks</li>
                    <li>Logs all access with Super Admin ID</li>
                    <li>Should be used with extreme caution</li>
                </ul>
            </div>
        </div>
    `;
    openModal('quickActionModal', content);
}

function decryptFile(fileId) {
    if (confirm('Decrypting this file will be logged in the audit trail. Continue?')) {
        fetch('../ajax/decrypt_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_id: fileId,
                master_key: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.open(data.download_url, '_blank');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                alert(data.message || 'Failed to decrypt file');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to decrypt file');
        });
    }
}

function viewFileDetails(fileId) {
    fetch(`../ajax/get_file_details.php?id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file text-gray-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">${data.original_name}</h3>
                            <p class="text-sm text-gray-500">${data.file_size} bytes</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Report</p>
                            <p class="font-medium">${data.report_number}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Encrypted</p>
                            <p class="font-medium">${new Date(data.created_at).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Access Count</p>
                            <p class="font-medium">${data.decryption_count}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Last Accessed</p>
                            <p class="font-medium">${data.last_decrypted ? new Date(data.last_decrypted).toLocaleDateString() : 'Never'}</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 mb-2">Encryption Key Hash</p>
                        <code class="block bg-gray-50 p-3 rounded text-xs font-mono break-all">
                            ${data.encryption_key_hash}
                        </code>
                    </div>
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load file details');
        });
}

function filterEvidenceTable(searchTerm) {
    const table = document.getElementById('evidenceTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    }
}

function showCustodyTransfer() {
    const content = `
        <form method="POST" action="../handlers/transfer_custody.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Description</label>
                    <input type="text" name="item_description" required class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From (Tanod)</label>
                        <select name="from_user" required class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="">Select Tanod...</option>
                            <?php
                            $tanods_query = "SELECT id, first_name, last_name FROM users WHERE role = 'tanod'";
                            $tanods_stmt = $conn->prepare($tanods_query);
                            $tanods_stmt->execute();
                            $tanods = $tanods_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($tanods as $tanod): ?>
                            <option value="<?php echo $tanod['id']; ?>">
                                <?php echo htmlspecialchars($tanod['first_name'] . ' ' . $tanod['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To (Recipient)</label>
                        <select name="to_user" required class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="">Select Recipient...</option>
                            <?php
                            $users_query = "SELECT id, first_name, last_name, role FROM users WHERE role IN ('captain', 'secretary', 'admin')";
                            $users_stmt = $conn->prepare($users_query);
                            $users_stmt->execute();
                            $recipients = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($recipients as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . ucfirst($user['role']) . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chain of Custody Notes</label>
                    <textarea name="custody_notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Transfer Custody
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}
</script>