<?php
// super_admin/modules/global_config.php

// Get all system configurations
$config_query = "SELECT * FROM system_config ORDER BY config_key";
$config_stmt = $conn->prepare($config_query);
$config_stmt->execute();
$configs = $config_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available system settings templates
$config_templates = [
    'security' => ['login_attempts', 'session_timeout', 'password_expiry'],
    'ai' => ['classification_threshold', 'ai_model_version', 'training_data'],
    'system' => ['system_timezone', 'data_retention_days', 'backup_frequency'],
    'notification' => ['email_notifications', 'sms_notifications', 'push_notifications']
];
?>
<div class="space-y-6">
    <!-- Configuration Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Global System Configuration</h2>
                <p class="text-gray-600 mt-2">Configure all system rules, models, and security policies</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="showConfigTemplate()" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-plus mr-2"></i> New Setting
                </button>
                <button onclick="saveAllConfig()" 
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-save mr-2"></i> Save All Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Configuration Categories -->
    <div class="space-y-6">
        <!-- Security Configuration -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-shield-alt text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Security Settings</h3>
                    <p class="text-gray-600">System security and access control</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Max Login Attempts</label>
                    <input type="number" name="config_max_login_attempts" 
                           value="<?php echo getConfigValue($configs, 'max_login_attempts', 5); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Maximum failed login attempts before lockout</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Session Timeout (minutes)</label>
                    <input type="number" name="config_session_timeout" 
                           value="<?php echo getConfigValue($configs, 'session_timeout', 30); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">User session inactivity timeout</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Password Expiry (days)</label>
                    <input type="number" name="config_password_expiry" 
                           value="<?php echo getConfigValue($configs, 'password_expiry', 90); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Days before password expires</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Two-Factor Authentication</label>
                    <select name="config_two_factor" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="0" <?php echo getConfigValue($configs, 'two_factor', 0) == 0 ? 'selected' : ''; ?>>Disabled</option>
                        <option value="1" <?php echo getConfigValue($configs, 'two_factor', 0) == 1 ? 'selected' : ''; ?>>Enabled</option>
                    </select>
                    <p class="text-xs text-gray-500">Require 2FA for admin access</p>
                </div>
            </div>
        </div>

        <!-- AI Configuration -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-robot text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">AI & Classification Settings</h3>
                    <p class="text-gray-600">AI model and incident classification parameters</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Classification Threshold</label>
                    <input type="number" step="0.01" min="0" max="1" name="config_classification_threshold" 
                           value="<?php echo getConfigValue($configs, 'classification_threshold', 0.7); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Confidence threshold for police classification</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">AI Model Version</label>
                    <input type="text" name="config_ai_model_version" 
                           value="<?php echo getConfigValue($configs, 'ai_model_version', 'v1.2.0'); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Current AI model version</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Auto-retrain Frequency (days)</label>
                    <input type="number" name="config_retrain_frequency" 
                           value="<?php echo getConfigValue($configs, 'retrain_frequency', 30); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Days between model retraining</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Manual Review Threshold</label>
                    <select name="config_manual_review" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="0.6" <?php echo getConfigValue($configs, 'manual_review', 0.6) == 0.6 ? 'selected' : ''; ?>>60% Confidence</option>
                        <option value="0.7" <?php echo getConfigValue($configs, 'manual_review', 0.6) == 0.7 ? 'selected' : ''; ?>>70% Confidence</option>
                        <option value="0.8" <?php echo getConfigValue($configs, 'manual_review', 0.6) == 0.8 ? 'selected' : ''; ?>>80% Confidence</option>
                    </select>
                    <p class="text-xs text-gray-500">Confidence level requiring manual review</p>
                </div>
            </div>
        </div>

        <!-- System Configuration -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cog text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">System Settings</h3>
                    <p class="text-gray-600">General system and maintenance settings</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">System Timezone</label>
                    <select name="config_system_timezone" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="Asia/Manila" <?php echo getConfigValue($configs, 'system_timezone', 'Asia/Manila') == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (UTC+8)</option>
                        <option value="UTC" <?php echo getConfigValue($configs, 'system_timezone', 'Asia/Manila') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    </select>
                    <p class="text-xs text-gray-500">System default timezone</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Data Retention (days)</label>
                    <input type="number" name="config_data_retention_days" 
                           value="<?php echo getConfigValue($configs, 'data_retention_days', 90); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Days to keep audit logs</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Auto-backup Frequency</label>
                    <select name="config_backup_frequency" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="daily" <?php echo getConfigValue($configs, 'backup_frequency', 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo getConfigValue($configs, 'backup_frequency', 'daily') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo getConfigValue($configs, 'backup_frequency', 'daily') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                    <p class="text-xs text-gray-500">Automatic backup schedule</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Maintenance Mode</label>
                    <select name="config_maintenance_mode" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="0" <?php echo getConfigValue($configs, 'maintenance_mode', 0) == 0 ? 'selected' : ''; ?>>Disabled</option>
                        <option value="1" <?php echo getConfigValue($configs, 'maintenance_mode', 0) == 1 ? 'selected' : ''; ?>>Enabled</option>
                    </select>
                    <p class="text-xs text-gray-500">Enable maintenance mode</p>
                </div>
            </div>
        </div>

        <!-- Notification Configuration -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-bell text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Notification Settings</h3>
                    <p class="text-gray-600">System notification and alert preferences</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Email Notifications</label>
                    <select name="config_email_notifications" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="1" <?php echo getConfigValue($configs, 'email_notifications', 1) == 1 ? 'selected' : ''; ?>>Enabled</option>
                        <option value="0" <?php echo getConfigValue($configs, 'email_notifications', 1) == 0 ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                    <p class="text-xs text-gray-500">Send email notifications</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">SMS Notifications</label>
                    <select name="config_sms_notifications" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="0" <?php echo getConfigValue($configs, 'sms_notifications', 0) == 0 ? 'selected' : ''; ?>>Disabled</option>
                        <option value="1" <?php echo getConfigValue($configs, 'sms_notifications', 0) == 1 ? 'selected' : ''; ?>>Enabled</option>
                    </select>
                    <p class="text-xs text-gray-500">Send SMS notifications</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Push Notifications</label>
                    <select name="config_push_notifications" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="1" <?php echo getConfigValue($configs, 'push_notifications', 1) == 1 ? 'selected' : ''; ?>>Enabled</option>
                        <option value="0" <?php echo getConfigValue($configs, 'push_notifications', 1) == 0 ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                    <p class="text-xs text-gray-500">Send push notifications</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Notification Cooldown (minutes)</label>
                    <input type="number" name="config_notification_cooldown" 
                           value="<?php echo getConfigValue($configs, 'notification_cooldown', 5); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-xs text-gray-500">Minutes between notifications</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Configuration -->
    <div class="glass-card rounded-xl p-6 bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Apply Configuration Changes</h3>
                <p class="text-gray-600 mt-1">Review and save all configuration settings</p>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="resetConfig()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-undo mr-2"></i> Reset Changes
                </button>
                <button type="submit" name="save_config" 
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-save mr-2"></i> Save All Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showConfigTemplate() {
    const content = `
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Configuration Key</label>
                <input type="text" id="newConfigKey" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="e.g., report_approval_threshold">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Configuration Value</label>
                <input type="text" id="newConfigValue" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="e.g., 0.8">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Configuration Type</label>
                <select id="newConfigType" class="w-full p-3 border border-gray-300 rounded-lg">
                    <option value="string">String</option>
                    <option value="number">Number</option>
                    <option value="boolean">Boolean</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="newConfigDesc" class="w-full p-3 border border-gray-300 rounded-lg" rows="3" placeholder="Description of this configuration..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancel
                </button>
                <button type="button" onclick="addNewConfig()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Add Configuration
                </button>
            </div>
        </div>
    `;
    openModal('quickActionModal', content);
}

function addNewConfig() {
    const key = document.getElementById('newConfigKey').value;
    const value = document.getElementById('newConfigValue').value;
    const type = document.getElementById('newConfigType').value;
    const desc = document.getElementById('newConfigDesc').value;
    
    if (!key || !value) {
        alert('Please fill in all required fields');
        return;
    }
    
    // In a real implementation, this would be an AJAX call
    console.log('Adding new config:', {key, value, type, desc});
    closeModal('quickActionModal');
    alert('Configuration added. Please save to apply.');
}

function saveAllConfig() {
    document.querySelector('form').submit();
}

function resetConfig() {
    if (confirm('Are you sure you want to reset all changes?')) {
        window.location.reload();
    }
}
</script>

<?php
// Helper function to get configuration value
function getConfigValue($configs, $key, $default = '') {
    foreach ($configs as $config) {
        if ($config['config_key'] === $key) {
            return $config['config_value'];
        }
    }
    return $default;
}
?>