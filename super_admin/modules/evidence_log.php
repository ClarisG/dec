<?php
// super_admin/modules/evidence_log.php

// Get evidence logs
$evidence_query = "
    SELECT fel.*, r.report_number, r.title as report_title,
           u_decrypt.first_name as decrypt_first, u_decrypt.last_name as decrypt_last,
           u_report.first_name as report_first, u_report.last_name as report_last
    FROM file_encryption_logs fel
    LEFT JOIN reports r ON fel.report_id = r.id
    LEFT JOIN users u_decrypt ON fel.last_decrypted_by = u_decrypt.id
    LEFT JOIN users u_report ON r.user_id = u_report.id
    ORDER BY fel.created_at DESC
    LIMIT 20
";
$evidence_stmt = $conn->prepare($evidence_query);
$evidence_stmt->execute();
$evidence_logs = $evidence_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get encryption statistics
$encryption_stats_query = "
    SELECT 
        COUNT(*) as total_files,
        SUM(file_size) as total_size,
        AVG(file_size) as avg_size,
        MAX(decryption_count) as max_decrypts,
        COUNT(CASE WHEN last_decrypted IS NOT NULL THEN 1 END) as accessed_files
    FROM file_encryption_logs
";
$encryption_stats_stmt = $conn->prepare($encryption_stats_query);
$encryption_stats_stmt->execute();
$encryption_stats = $encryption_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Format file size
function formatSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<div class="space-y-6">
    <!-- Evidence Log Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Evidence & Encryption Master Log</h3>
            <p class="text-sm text-gray-600">Access all encrypted files without restriction</p>
        </div>
        <div class="flex space-x-3">
            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                <i class="fas fa-key mr-2"></i>Master Key Access
            </button>
            <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                <i class="fas fa-shield-alt mr-2"></i>Security Audit
            </button>
        </div>
    </div>

    <!-- Encryption Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Encrypted Files</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $encryption_stats['total_files']; ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-lock text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Total size: <?php echo formatSize($encryption_stats['total_size']); ?>
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Accessed Files</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $encryption_stats['accessed_files']; ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-unlock text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <?php echo round(($encryption_stats['accessed_files'] / max(1, $encryption_stats['total_files'])) * 100, 1); ?>% accessed
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Avg File Size</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo formatSize($encryption_stats['avg_size']); ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-file text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Average size per file
            </div>
        </div>
        
        <div class="super-stat-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Max Accesses</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $encryption_stats['max_decrypts']; ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-lg">
                    <i class="fas fa-history text-red-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Most accessed file
            </div>
        </div>
    </div>

    <!-- Evidence Logs Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-md font-medium text-gray-800">Encrypted File Logs</h4>
                <div class="flex space-x-2">
                    <input type="text" placeholder="Search files..." 
                           class="p-2 border border-gray-300 rounded-lg text-sm w-64">
                    <select class="p-2 border border-gray-300 rounded-lg text-sm">
                        <option>All Status</option>
                        <option>Never Accessed</option>
                        <option>Recently Accessed</option>
                        <option>Multiple Accesses</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Encryption Info</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access History</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($evidence_logs as $log): 
                            $access_class = $log['decryption_count'] == 0 ? 
                                          'bg-gray-100 text-gray-800' : 
                                          ($log['decryption_count'] > 5 ? 
                                           'bg-yellow-100 text-yellow-800' : 
                                           'bg-green-100 text-green-800');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                        <?php
                                        $extension = pathinfo($log['original_name'], PATHINFO_EXTENSION);
                                        $icon = 'file';
                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'file-image';
                                        elseif (in_array($extension, ['pdf'])) $icon = 'file-pdf';
                                        elseif (in_array($extension, ['doc', 'docx'])) $icon = 'file-word';
                                        ?>
                                        <i class="fas fa-<?php echo $icon; ?> text-purple-600"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($log['original_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo pathinfo($log['original_name'], PATHINFO_EXTENSION); ?> • 
                                            <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($log['report_number']); ?>
                                </div>
                                <div class="text-xs text-gray-500 truncate" style="max-width: 150px;">
                                    <?php echo htmlspecialchars($log['report_title']); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    By: <?php echo htmlspecialchars($log['report_first'] . ' ' . $log['report_last']); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs">
                                    <div class="mb-1">
                                        <span class="font-medium">Key Hash:</span>
                                        <span class="text-gray-600 font-mono text-xs truncate block" style="max-width: 120px;">
                                            <?php echo substr($log['encryption_key'], 0, 20) . '...'; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium">IV:</span>
                                        <span class="text-gray-600 font-mono text-xs">
                                            <?php echo substr($log['iv'] ?? 'N/A', 0, 10) . '...'; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $access_class; ?> mr-2">
                                        <?php echo $log['decryption_count']; ?> accesses
                                    </span>
                                    <?php if ($log['last_decrypted']): ?>
                                    <div class="text-xs text-gray-500">
                                        Last: <?php echo date('M d', strtotime($log['last_decrypted'])); ?>
                                        <br>
                                        By: <?php echo htmlspecialchars($log['decrypt_first'] . ' ' . $log['decrypt_last']); ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">Never accessed</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo formatSize($log['file_size']); ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex space-x-2">
                                    <button onclick="decryptFile(<?php echo $log['id']; ?>)" 
                                            class="px-3 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                                        <i class="fas fa-unlock-alt mr-1"></i>Decrypt
                                    </button>
                                    <button onclick="viewFileDetails(<?php echo $log['id']; ?>)" 
                                            class="px-3 py-1 border border-gray-300 text-gray-700 text-xs rounded hover:bg-gray-50">
                                        <i class="fas fa-info-circle mr-1"></i>Details
                                    </button>
                                    <button onclick="showEncryptionKey(<?php echo $log['id']; ?>)" 
                                            class="px-3 py-1 border border-gray-300 text-gray-700 text-xs rounded hover:bg-gray-50">
                                        <i class="fas fa-key mr-1"></i>Key
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chain of Custody -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-md font-medium text-gray-800">Chain of Custody Logs</h4>
            <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-plus mr-2"></i>Add Custody Record
            </button>
        </div>
        
        <div class="space-y-4">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm mr-3">
                            E<?php echo $i; ?>
                        </div>
                        <div>
                            <h5 class="font-medium text-gray-800">Evidence Item #E<?php echo $i; ?></h5>
                            <p class="text-sm text-gray-500">Case: RPT-20260110-ABC123</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                        <i class="fas fa-check-circle mr-1"></i>In Custody
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Current Holder:</span>
                        <span class="font-medium text-gray-700 ml-2">Barangay Secretary</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Location:</span>
                        <span class="font-medium text-gray-700 ml-2">Evidence Room, Cabinet A</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Last Updated:</span>
                        <span class="font-medium text-gray-700 ml-2">Today, 10:30 AM</span>
                    </div>
                </div>
                
                <div class="mt-3 flex space-x-2">
                    <button class="text-xs text-blue-600 hover:text-blue-800">
                        <i class="fas fa-history mr-1"></i>View Full History
                    </button>
                    <button class="text-xs text-green-600 hover:text-green-800">
                        <i class="fas fa-exchange-alt mr-1"></i>Transfer
                    </button>
                    <button class="text-xs text-red-600 hover:text-red-800">
                        <i class="fas fa-trash-alt mr-1"></i>Dispose
                    </button>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Encryption Key Modal -->
<div id="encryptionKeyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Encryption Key Details</h3>
                <button onclick="closeEncryptionKeyModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Information</label>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-medium text-gray-900" id="modalFileName"></p>
                        <p class="text-xs text-gray-500" id="modalFileReport"></p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Encryption Key Hash</label>
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                        <code id="modalKeyHash">Loading...</code>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Initialization Vector (IV)</label>
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                        <code id="modalIV">Loading...</code>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Access Warnings</label>
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                            <p class="text-sm text-yellow-700">
                                This key provides direct access to encrypted evidence. All access is logged.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeEncryptionKeyModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Close
                    </button>
                    <button onclick="copyEncryptionKey()" 
                            class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-copy mr-2"></i>Copy Key
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function decryptFile(fileId) {
    if (confirm('Decrypt this file? This action will be logged.')) {
        // Implement decryption functionality
        window.open('../ajax/decrypt_file.php?id=' + fileId, '_blank');
    }
}

function viewFileDetails(fileId) {
    // Implement file details view
    alert('Viewing file details for ID: ' + fileId);
}

function showEncryptionKey(fileId) {
    // In real implementation, fetch encryption key via AJAX
    document.getElementById('modalFileName').textContent = 'evidence_photo.jpg';
    document.getElementById('modalFileReport').textContent = 'Report #RPT-20260115-ABC123';
    document.getElementById('modalKeyHash').textContent = 'dd5aaf0ba30faee45db2b88214916c4e5b5717d5c5872e6df695c63fffa7ca16';
    document.getElementById('modalIV').textContent = 'f23c1baeacf99860d3d27a176ccb9acc';
    
    document.getElementById('encryptionKeyModal').classList.remove('hidden');
    document.getElementById('encryptionKeyModal').classList.add('flex');
}

function closeEncryptionKeyModal() {
    document.getElementById('encryptionKeyModal').classList.add('hidden');
    document.getElementById('encryptionKeyModal').classList.remove('flex');
}

function copyEncryptionKey() {
    const key = document.getElementById('modalKeyHash').textContent;
    navigator.clipboard.writeText(key).then(() => {
        alert('Encryption key copied to clipboard!');
    });
}
</script>