<?php
// super_admin/modules/api_control.php

// Get all API integrations
$api_query = "SELECT * FROM api_integrations ORDER BY status DESC, created_at DESC";
$api_stmt = $conn->prepare($api_query);
$api_stmt->execute();
$apis = $api_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get data transfer logs
$transfer_query = "SELECT * FROM data_transfer_logs ORDER BY created_at DESC LIMIT 20";
$transfer_stmt = $conn->prepare($transfer_query);
$transfer_stmt->execute();
$transfers = $transfer_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get API statistics
$stats_query = "SELECT 
    COUNT(*) as total_apis,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_apis,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_apis,
    SUM(CASE WHEN status = 'testing' THEN 1 ELSE 0 END) as testing_apis,
    MAX(last_sync) as last_sync
    FROM api_integrations";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$api_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">API Integration Master Control</h2>
                <p class="text-gray-600 mt-2">Manage external integrations and monitor data transfers</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="addNewAPI()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-plus mr-2"></i> New Integration
                </button>
                <button onclick="testAllAPIs()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-sync mr-2"></i> Test All APIs
                </button>
            </div>
        </div>

        <!-- API Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $api_stats['total_apis'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total APIs</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo $api_stats['active_apis'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Active</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-yellow-700"><?php echo $api_stats['testing_apis'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Testing</div>
            </div>
            <div class="bg-red-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-red-700"><?php echo $api_stats['inactive_apis'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Inactive</div>
            </div>
        </div>
    </div>

    <!-- API Integrations -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">API Integrations</h3>
            <div class="flex space-x-2">
                <button onclick="refreshAPIStatus()"
                        class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">API Name</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Endpoint</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Last Sync</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Sync Status</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($apis as $api): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 mr-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-plug text-blue-600"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($api['api_name']); ?></p>
                                    <p class="text-xs text-gray-500">Created: <?php echo date('M d, Y', strtotime($api['created_at'])); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $api['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                       ($api['status'] === 'testing' ? 'bg-yellow-100 text-yellow-800' :
                                       'bg-red-100 text-red-800'); ?>">
                                <?php echo ucfirst($api['status']); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-gray-700 truncate max-w-xs">
                                <?php echo htmlspecialchars($api['endpoint'] ?? 'N/A'); ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm">
                                <?php echo $api['last_sync'] ? date('M d, H:i', strtotime($api['last_sync'])) : 'Never'; ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $api['sync_status'] === 'success' ? 'bg-green-100 text-green-800' :
                                           ($api['sync_status'] === 'failed' ? 'bg-red-100 text-red-800' :
                                           'bg-yellow-100 text-yellow-800'); ?>">
                                    <?php echo ucfirst($api['sync_status'] ?? 'pending'); ?>
                                </span>
                                <?php if ($api['sync_message']): ?>
                                <button onclick="showSyncMessage(<?php echo $api['id']; ?>)"
                                        class="ml-2 p-1 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="testAPI(<?php echo $api['id']; ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg"
                                        title="Test API">
                                    <i class="fas fa-bolt"></i>
                                </button>
                                <button onclick="editAPI(<?php echo $api['id']; ?>)"
                                        class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg"
                                        title="Edit API">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="toggleAPIStatus(<?php echo $api['id']; ?>, '<?php echo $api['status']; ?>')"
                                        class="p-2 <?php echo $api['status'] === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg"
                                        title="<?php echo $api['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $api['status'] === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($apis)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <i class="fas fa-plug text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">No API integrations configured</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Data Transfer Logs -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Data Transfer Logs</h3>
            <button onclick="clearTransferLogs()"
                    class="px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm">
                <i class="fas fa-trash mr-1"></i> Clear Old Logs
            </button>
        </div>
        
        <div class="space-y-4">
            <?php foreach ($transfers as $transfer): ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($transfer['api_name']); ?></p>
                        <p class="text-sm text-gray-500">Operation: <?php echo htmlspecialchars($transfer['operation']); ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                        <?php echo $transfer['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($transfer['status']); ?>
                    </span>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                    <div>
                        <p class="text-gray-600"><?php echo $transfer['records_count']; ?> records transferred</p>
                        <?php if ($transfer['message']): ?>
                        <p class="text-gray-500 text-xs mt-1"><?php echo htmlspecialchars($transfer['message']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="text-gray-500"><?php echo date('M d, H:i:s', strtotime($transfer['created_at'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($transfers)): ?>
            <div class="text-center py-8">
                <i class="fas fa-exchange-alt text-gray-300 text-3xl mb-3"></i>
                <p class="text-gray-500">No data transfer logs found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- API Configuration -->
    <div class="glass-card rounded-xl p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">API Configuration</h3>
        
        <form method="POST" action="../handlers/api_config.php" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">API Rate Limit (requests/minute)</label>
                    <input type="number" name="rate_limit" value="60"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Maximum requests per minute per API</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Timeout (seconds)</label>
                    <input type="number" name="timeout" value="30"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">API request timeout in seconds</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Retry Attempts</label>
                    <input type="number" name="retry_attempts" value="3"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Number of retry attempts on failure</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Sync Interval (minutes)</label>
                    <input type="number" name="sync_interval" value="15"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Automatic sync interval</p>
                </div>
            </div>
            
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Allowed IPs (comma-separated)</label>
                <textarea name="allowed_ips" rows="3"
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="192.168.1.1, 10.0.0.1"></textarea>
                <p class="text-xs text-gray-500">IP addresses allowed to access APIs (leave empty for all)</p>
            </div>
            
            <div class="flex items-center space-x-3">
                <input type="checkbox" id="enable_webhooks" name="enable_webhooks" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="enable_webhooks" class="text-sm font-medium text-gray-700">Enable Webhook Support</label>
            </div>
            
            <div class="flex items-center space-x-3">
                <input type="checkbox" id="enable_logging" name="enable_logging" checked class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="enable_logging" class="text-sm font-medium text-gray-700">Enable Detailed Logging</label>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="reset" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Reset
                </button>
                <button type="submit" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addNewAPI() {
    const content = `
        <form method="POST" action="../handlers/add_api.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Name</label>
                    <input type="text" name="api_name" required class="w-full p-3 border border-gray-300 rounded-lg" placeholder="e.g., PNP Integration">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint URL</label>
                    <input type="url" name="endpoint" required class="w-full p-3 border border-gray-300 rounded-lg" placeholder="https://api.example.com/v1">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                    <input type="text" name="api_key" required class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Enter API key">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Secret (optional)</label>
                    <input type="password" name="api_secret" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Enter API secret">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Initial Status</label>
                    <select name="status" class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="testing">Testing</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Add API Integration
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function testAPI(apiId) {
    fetch(`../ajax/test_api.php?id=${apiId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`API Test Successful\nResponse: ${data.message}`);
                window.location.reload();
            } else {
                alert(`API Test Failed\nError: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to test API');
        });
}

function editAPI(apiId) {
    fetch(`../ajax/get_api_details.php?id=${apiId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <form method="POST" action="../handlers/update_api.php">
                    <input type="hidden" name="api_id" value="${data.id}">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Name</label>
                            <input type="text" name="api_name" value="${data.api_name}" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint URL</label>
                            <input type="url" name="endpoint" value="${data.endpoint}" class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="testing" ${data.status === 'testing' ? 'selected' : ''}>Testing</option>
                                <option value="active" ${data.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sync Status</label>
                            <select name="sync_status" class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="pending" ${data.sync_status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="success" ${data.sync_status === 'success' ? 'selected' : ''}>Success</option>
                                <option value="failed" ${data.sync_status === 'failed' ? 'selected' : ''}>Failed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sync Message</label>
                            <textarea name="sync_message" rows="3" class="w-full p-3 border border-gray-300 rounded-lg">${data.sync_message || ''}</textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Update API
                            </button>
                        </div>
                    </div>
                </form>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load API details');
        });
}

function toggleAPIStatus(apiId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this API?`)) {
        fetch('../ajax/toggle_api_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                api_id: apiId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to update API status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update API status');
        });
    }
}

function showSyncMessage(apiId) {
    fetch(`../ajax/get_api_details.php?id=${apiId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-800">Sync Details</p>
                        <p class="text-sm text-gray-600 mt-1">API: ${data.api_name}</p>
                        <p class="text-sm text-gray-600">Last Sync: ${data.last_sync ? new Date(data.last_sync).toLocaleString() : 'Never'}</p>
                    </div>
                    
                    <div class="p-3 ${data.sync_status === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'} rounded-lg">
                        <p class="font-medium ${data.sync_status === 'success' ? 'text-green-800' : 'text-red-800'}">
                            Status: ${data.sync_status || 'Unknown'}
                        </p>
                        ${data.sync_message ? `
                            <p class="text-sm ${data.sync_status === 'success' ? 'text-green-700' : 'text-red-700'} mt-2">
                                ${data.sync_message}
                            </p>
                        ` : ''}
                    </div>
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load sync details');
        });
}

function testAllAPIs() {
    if (confirm('Test all API integrations? This may take a few moments.')) {
        fetch('../ajax/test_all_apis.php')
            .then(response => response.json())
            .then(data => {
                alert(`API Testing Complete\nSuccess: ${data.success_count}\nFailed: ${data.fail_count}`);
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to test APIs');
            });
    }
}

function refreshAPIStatus() {
    window.location.reload();
}

function clearTransferLogs() {
    if (confirm('Clear transfer logs older than 30 days?')) {
        fetch('../ajax/clear_transfer_logs.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Cleared ${data.deleted_count} old logs`);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to clear logs');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to clear logs');
            });
    }
}
</script>