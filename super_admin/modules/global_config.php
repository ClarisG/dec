<?php
// super_admin/modules/global_config.php
?>
<div class="space-y-6">
    <!-- System Configuration Header -->
    <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800">Global System Configuration</h3>
        <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
            <i class="fas fa-save mr-2"></i>Save All Changes
        </button>
    </div>

    <!-- Configuration Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b">
            <nav class="flex space-x-8 px-6" aria-label="Configuration Tabs">
                <button class="py-4 px-1 border-b-2 border-purple-600 text-sm font-medium text-purple-600">
                    AI & Classification
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    Security Settings
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    System Rules
                </button>
                <button class="py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700">
                    Integration
                </button>
            </nav>
        </div>

        <!-- AI Configuration Form -->
        <div class="p-6">
            <h4 class="text-md font-medium text-gray-800 mb-4">AI Classification Settings</h4>
            
            <div class="space-y-6">
                <!-- Confidence Threshold -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Classification Threshold</label>
                        <div class="flex items-center">
                            <input type="range" min="0.1" max="1.0" step="0.1" value="0.7" 
                                   class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <span class="ml-3 text-sm font-medium text-gray-700">0.7</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Confidence level for police classification</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">AI Model Version</label>
                        <select class="w-full p-2 border border-gray-300 rounded-lg">
                            <option>v1.2.0 (Production)</option>
                            <option>v1.1.5 (Stable)</option>
                            <option>v1.3.0-beta (Testing)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Auto-retrain Interval</label>
                        <select class="w-full p-2 border border-gray-300 rounded-lg">
                            <option>Weekly</option>
                            <option>Monthly</option>
                            <option>Quarterly</option>
                            <option>Disabled</option>
                        </select>
                    </div>
                </div>

                <!-- Jurisdiction Keywords -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jurisdiction Keywords</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h5 class="text-sm font-medium text-gray-600 mb-2">Police Keywords</h5>
                            <textarea class="w-full h-32 p-3 border border-gray-300 rounded-lg" 
                                      placeholder="murder, robbery, drugs, kidnapping...">murder, robbery, drugs, kidnapping, assault, arson, firearms, stabbing</textarea>
                        </div>
                        <div>
                            <h5 class="text-sm font-medium text-gray-600 mb-2">Barangay Keywords</h5>
                            <textarea class="w-full h-32 p-3 border border-gray-300 rounded-lg" 
                                      placeholder="noise, garbage, neighbor dispute...">noise, garbage, neighbor, dispute, animal, boundary, water, electricity</textarea>
                        </div>
                    </div>
                </div>

                <!-- Report Type Configuration -->
                <div>
                    <h5 class="text-sm font-medium text-gray-600 mb-3">Report Type Settings</h5>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-700">Auto-escalation for high-priority cases</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-700">Require evidence for police reports</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Anonymous reporting allowed</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Constants -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-medium text-gray-800 mb-4">System Constants</h4>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    // Get system config from database
                    $config_query = "SELECT * FROM system_config ORDER BY config_key";
                    $config_stmt = $conn->prepare($config_query);
                    $config_stmt->execute();
                    $configs = $config_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($configs as $config):
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($config['config_key']); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <input type="text" value="<?php echo htmlspecialchars($config['config_value']); ?>" 
                                   class="w-full p-2 border border-gray-300 rounded text-sm">
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($config['config_type']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <?php echo htmlspecialchars($config['description'] ?? 'No description'); ?>
                        </td>
                        <td class="px-4 py-3">
                            <button class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                Update
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>