<?php
// super_admin/modules/super_notifications.php

// Get all notifications
$notifications_query = "SELECT n.*, 
                               u.first_name as created_by_first,
                               u.last_name as created_by_last
                        FROM notifications n
                        LEFT JOIN users u ON n.created_by = u.id
                        ORDER BY n.created_at DESC 
                        LIMIT 20";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->execute();
$system_notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user notifications statistics
$user_notifs_query = "SELECT 
    COUNT(*) as total_notifications,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    COUNT(DISTINCT user_id) as users_notified,
    MAX(created_at) as last_notification
    FROM user_notifications
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$user_notifs_stmt = $conn->prepare($user_notifs_query);
$user_notifs_stmt->execute();
$notif_stats = $user_notifs_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent emergency alerts
$emergency_query = "SELECT * FROM notifications 
                    WHERE priority = 'high' OR priority = 'critical'
                    ORDER BY created_at DESC 
                    LIMIT 10";
$emergency_stmt = $conn->prepare($emergency_query);
$emergency_stmt->execute();
$emergency_alerts = $emergency_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Super Notification System</h2>
                <p class="text-gray-600 mt-2">Send system-wide announcements and emergency alerts</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="quickSendNotification()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-bullhorn mr-2"></i> Send Alert
                </button>
                <button onclick="createAnnouncement()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-newspaper mr-2"></i> Create Announcement
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $notif_stats['total_notifications'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">7-Day Notifications</div>
            </div>
            <div class="bg-red-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-red-700"><?php echo $notif_stats['unread'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Unread</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo $notif_stats['users_notified'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Users Notified</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo count($system_notifications); ?></div>
                <div class="text-sm text-gray-600">System Broadcasts</div>
            </div>
        </div>
    </div>

    <!-- Send Notification -->
    <div class="glass-card rounded-xl p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Send System Notification</h3>
        
        <form method="POST" action="" class="space-y-6">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Notification Title</label>
                <input type="text" name="title" required
                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="Enter notification title...">
            </div>
            
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Message</label>
                <textarea name="message" rows="4" required
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="Enter notification message..."></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Target Audience</label>
                    <select name="target_role" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="all">All Users</option>
                        <option value="citizen">Citizens Only</option>
                        <option value="tanod">Tanods Only</option>
                        <option value="secretary">Secretaries Only</option>
                        <option value="captain">Captains Only</option>
                        <option value="lupon">Lupon Members Only</option>
                        <option value="admin">Admins Only</option>
                        <option value="super_admin">Super Admins Only</option>
                    </select>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Priority</label>
                    <select name="priority" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="low">Low (Information)</option>
                        <option value="medium" selected>Medium (Important)</option>
                        <option value="high">High (Urgent)</option>
                        <option value="critical">Critical (Emergency)</option>
                    </select>
                </div>
            </div>
            
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Select Specific Barangays (optional)</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php foreach (array_slice($all_barangays, 0, 8) as $barangay): ?>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="barangays[]" value="<?php echo htmlspecialchars($barangay); ?>"
                               class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($barangay); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500">Leave unchecked to send to all barangays</p>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="send_email" name="send_email" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="send_email" class="text-sm font-medium text-gray-700">Send as Email</label>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="send_sms" name="send_sms" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="send_sms" class="text-sm font-medium text-gray-700">Send as SMS</label>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="push_notification" name="push_notification" checked class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="push_notification" class="text-sm font-medium text-gray-700">Push Notification</label>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="reset" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Clear Form
                </button>
                <button type="submit" name="send_notification" 
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-paper-plane mr-2"></i> Send Notification
                </button>
            </div>
        </form>
    </div>

    <!-- Emergency Alerts -->
    <div class="glass-card rounded-xl p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                <h3 class="text-lg font-bold text-gray-800">Emergency Alert System</h3>
            </div>
            <button onclick="sendEmergencyAlert()"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-siren mr-2"></i> Emergency Broadcast
            </button>
        </div>
        
        <div class="space-y-4">
            <?php foreach ($emergency_alerts as $alert): ?>
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="font-medium text-red-800"><?php echo htmlspecialchars($alert['title']); ?></p>
                        <p class="text-sm text-red-600 mt-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                    </div>
                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                        <?php echo ucfirst($alert['priority']); ?>
                    </span>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                    <div>
                        <p class="text-red-700">
                            Target: <span class="font-medium"><?php echo ucfirst($alert['target_role']); ?></span>
                        </p>
                        <?php if ($alert['created_by_first']): ?>
                        <p class="text-red-600 text-xs">
                            Sent by: <?php echo htmlspecialchars($alert['created_by_first'] . ' ' . $alert['created_by_last']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="text-red-600"><?php echo date('M d, H:i', strtotime($alert['created_at'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($emergency_alerts)): ?>
            <div class="text-center py-8">
                <i class="fas fa-shield-alt text-red-300 text-3xl mb-3"></i>
                <p class="text-gray-500">No emergency alerts in the system</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification History -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Notification History</h3>
            <button onclick="clearOldNotifications()"
                    class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                <i class="fas fa-trash mr-1"></i> Clear Old
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Notification</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Target</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Priority</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Sent By</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($system_notifications as $notif): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div>
                                <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($notif['title']); ?></p>
                                <p class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($notif['message']); ?></p>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $notif['target_role'] === 'all' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                <?php echo ucfirst($notif['target_role']); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $notif['priority'] === 'critical' ? 'bg-red-100 text-red-800' :
                                       ($notif['priority'] === 'high' ? 'bg-orange-100 text-orange-800' :
                                       ($notif['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                                       'bg-green-100 text-green-800')); ?>">
                                <?php echo ucfirst($notif['priority']); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm">
                                <?php echo $notif['created_by_first'] ? 
                                    htmlspecialchars($notif['created_by_first'] . ' ' . $notif['created_by_last']) : 
                                    'System'; ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-gray-500">
                                <?php echo date('M d, H:i', strtotime($notif['created_at'])); ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="viewNotification(<?php echo $notif['id']; ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="resendNotification(<?php echo $notif['id']; ?>)"
                                        class="p-2 text-green-600 hover:bg-green-50 rounded-lg">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($system_notifications)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <i class="fas fa-bell text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">No notification history found</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Notification Templates -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Notification Templates</h3>
            <button onclick="createTemplate()"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                <i class="fas fa-plus mr-2"></i> New Template
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $templates = [
                [
                    'name' => 'System Maintenance',
                    'description' => 'Scheduled maintenance announcement',
                    'priority' => 'medium',
                    'icon' => 'fa-tools',
                    'color' => 'blue'
                ],
                [
                    'name' => 'Emergency Alert',
                    'description' => 'Critical system-wide emergency',
                    'priority' => 'critical',
                    'icon' => 'fa-siren',
                    'color' => 'red'
                ],
                [
                    'name' => 'Weather Advisory',
                    'description' => 'Weather-related warnings',
                    'priority' => 'high',
                    'icon' => 'fa-cloud-showers-heavy',
                    'color' => 'gray'
                ],
                [
                    'name' => 'Case Update',
                    'description' => 'Case status change notification',
                    'priority' => 'medium',
                    'icon' => 'fa-file-alt',
                    'color' => 'green'
                ],
                [
                    'name' => 'Meeting Reminder',
                    'description' => 'Upcoming meeting reminder',
                    'priority' => 'low',
                    'icon' => 'fa-calendar-alt',
                    'color' => 'yellow'
                ],
                [
                    'name' => 'Document Ready',
                    'description' => 'Document approval notification',
                    'priority' => 'medium',
                    'icon' => 'fa-file-pdf',
                    'color' => 'purple'
                ]
            ];
            
            foreach ($templates as $template):
            ?>
            <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition">
                <div class="flex items-start mb-4">
                    <div class="w-12 h-12 rounded-xl bg-<?php echo $template['color']; ?>-100 flex items-center justify-center mr-3">
                        <i class="fas <?php echo $template['icon']; ?> text-<?php echo $template['color']; ?>-600 text-lg"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800"><?php echo $template['name']; ?></h4>
                        <p class="text-sm text-gray-500"><?php echo $template['description']; ?></p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                        <?php echo $template['priority'] === 'critical' ? 'bg-red-100 text-red-800' :
                               ($template['priority'] === 'high' ? 'bg-orange-100 text-orange-800' :
                               ($template['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                               'bg-green-100 text-green-800')); ?>">
                        <?php echo ucfirst($template['priority']); ?> Priority
                    </span>
                    <button onclick="useTemplate('<?php echo $template['name']; ?>')"
                            class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm">
                        Use Template
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function createAnnouncement() {
    const content = `
        <form method="POST" action="../handlers/create_announcement.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Announcement Title</label>
                    <input type="text" name="title" required class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                    <textarea name="content" rows="6" required class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                        <select name="target_role" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="all">All Users</option>
                            <option value="citizen">Citizens</option>
                            <option value="tanod">Tanods</option>
                            <option value="secretary">Secretaries</option>
                            <option value="captain">Captains</option>
                            <option value="lupon">Lupon Members</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="is_emergency" name="is_emergency" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="is_emergency" class="text-sm font-medium text-gray-700">Emergency Announcement</label>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="is_pinned" name="is_pinned" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="is_pinned" class="text-sm font-medium text-gray-700">Pin to Top</label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Create Announcement
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function sendEmergencyAlert() {
    const content = `
        <form method="POST" action="../handlers/send_emergency_alert.php">
            <div class="space-y-4">
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        <p class="text-red-700 font-medium">EMERGENCY BROADCAST</p>
                    </div>
                    <p class="text-sm text-red-600 mt-1">This alert will be sent to ALL users immediately</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Type</label>
                    <select name="emergency_type" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Select emergency type...</option>
                        <option value="natural_disaster">Natural Disaster</option>
                        <option value="security_threat">Security Threat</option>
                        <option value="fire">Fire Emergency</option>
                        <option value="medical">Medical Emergency</option>
                        <option value="system_failure">System Failure</option>
                        <option value="other">Other Critical Emergency</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alert Message</label>
                    <textarea name="message" rows="4" required 
                              class="w-full p-3 border border-gray-300 rounded-lg"
                              placeholder="Enter emergency instructions..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Affected Areas (optional)</label>
                    <input type="text" name="affected_areas" 
                           class="w-full p-3 border border-gray-300 rounded-lg"
                           placeholder="Specific barangays or locations affected">
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="sound_alarm" name="sound_alarm" checked class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="sound_alarm" class="text-sm font-medium text-gray-700">Sound System Alarm</label>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="force_notification" name="force_notification" checked class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="force_notification" class="text-sm font-medium text-gray-700">Force Notification</label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-siren mr-2"></i> Send Emergency Alert
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function viewNotification(notificationId) {
    fetch(`../ajax/get_notification_details.php?id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="p-3 ${data.priority === 'critical' ? 'bg-red-50 border border-red-200' : data.priority === 'high' ? 'bg-orange-50 border border-orange-200' : data.priority === 'medium' ? 'bg-yellow-50 border border-yellow-200' : 'bg-green-50 border border-green-200'} rounded-lg">
                        <p class="font-medium ${data.priority === 'critical' ? 'text-red-800' : data.priority === 'high' ? 'text-orange-800' : data.priority === 'medium' ? 'text-yellow-800' : 'text-green-800'}">
                            ${data.title}
                        </p>
                        <p class="text-sm ${data.priority === 'critical' ? 'text-red-700' : data.priority === 'high' ? 'text-orange-700' : data.priority === 'medium' ? 'text-yellow-700' : 'text-green-700'} mt-2">
                            ${data.message}
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Target Audience</p>
                            <p class="font-medium">${data.target_role}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Priority</p>
                            <span class="px-3 py-1 rounded-full text-xs font-medium ${data.priority === 'critical' ? 'bg-red-100 text-red-800' : data.priority === 'high' ? 'bg-orange-100 text-orange-800' : data.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                                ${data.priority}
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Sent By</p>
                            <p class="font-medium">${data.created_by || 'System'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Date Sent</p>
                            <p class="font-medium">${new Date(data.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 mb-2">Delivery Status</p>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <div class="text-lg font-bold text-green-600">${data.delivered_count || 0}</div>
                                <div class="text-xs text-gray-500">Delivered</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold text-yellow-600">${data.pending_count || 0}</div>
                                <div class="text-xs text-gray-500">Pending</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold text-red-600">${data.failed_count || 0}</div>
                                <div class="text-xs text-gray-500">Failed</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load notification details');
        });
}

function resendNotification(notificationId) {
    if (confirm('Resend this notification to all recipients?')) {
        fetch('../ajax/resend_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_id: notificationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notification resent successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to resend notification');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to resend notification');
        });
    }
}

function useTemplate(templateName) {
    // Load template content
    const templates = {
        'System Maintenance': {
            title: 'System Maintenance Announcement',
            message: 'The system will undergo scheduled maintenance on [DATE] from [TIME]. During this period, the system may be temporarily unavailable. We apologize for any inconvenience.',
            priority: 'medium',
            target_role: 'all'
        },
        'Emergency Alert': {
            title: 'EMERGENCY ALERT: [EMERGENCY_TYPE]',
            message: 'ATTENTION: [EMERGENCY_DETAILS]. Please follow emergency procedures and stay safe. Updates will follow.',
            priority: 'critical',
            target_role: 'all'
        },
        'Weather Advisory': {
            title: 'Weather Advisory: [WEATHER_CONDITION]',
            message: 'A [WEATHER_CONDITION] has been issued for your area. Please take necessary precautions.',
            priority: 'high',
            target_role: 'all'
        },
        'Case Update': {
            title: 'Case Update: [CASE_NUMBER]',
            message: 'Your case [CASE_NUMBER] has been updated. Please check the system for details.',
            priority: 'medium',
            target_role: 'citizen'
        },
        'Meeting Reminder': {
            title: 'Meeting Reminder: [MEETING_TITLE]',
            message: 'Reminder: [MEETING_TITLE] is scheduled for [DATE] at [TIME] at [LOCATION].',
            priority: 'low',
            target_role: 'all'
        },
        'Document Ready': {
            title: 'Document Ready: [DOCUMENT_NAME]',
            message: 'Your document [DOCUMENT_NAME] is ready for review/download. Please check the system.',
            priority: 'medium',
            target_role: 'citizen'
        }
    };
    
    const template = templates[templateName];
    if (template) {
        // Fill the form with template data
        document.querySelector('input[name="title"]').value = template.title;
        document.querySelector('textarea[name="message"]').value = template.message;
        document.querySelector('select[name="priority"]').value = template.priority;
        document.querySelector('select[name="target_role"]').value = template.target_role;
        
        // Scroll to form
        document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        alert(`Template "${templateName}" loaded. Please customize before sending.`);
    }
}

function clearOldNotifications() {
    if (confirm('Clear notifications older than 30 days?')) {
        fetch('../ajax/clear_old_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Cleared ${data.deleted_count} old notifications`);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to clear notifications');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to clear notifications');
            });
    }
}

function createTemplate() {
    const content = `
        <form method="POST" action="../handlers/create_notification_template.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="name" required class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title Template</label>
                    <input type="text" name="title_template" required class="w-full p-3 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500">Use [VARIABLE] for dynamic content</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message Template</label>
                    <textarea name="message_template" rows="4" required class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                    <p class="text-xs text-gray-500">Use [VARIABLE] for dynamic content</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Priority</label>
                        <select name="default_priority" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Target</label>
                        <select name="default_target" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="all">All Users</option>
                            <option value="citizen">Citizens</option>
                            <option value="tanod">Tanods</option>
                            <option value="secretary">Secretaries</option>
                            <option value="captain">Captains</option>
                            <option value="lupon">Lupon Members</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Available Variables</label>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <code class="text-sm text-gray-700 block mb-1">[DATE] - Current date</code>
                        <code class="text-sm text-gray-700 block mb-1">[TIME] - Current time</code>
                        <code class="text-sm text-gray-700 block mb-1">[USER_NAME] - Recipient name</code>
                        <code class="text-sm text-gray-700 block mb-1">[CASE_NUMBER] - Case reference</code>
                        <code class="text-sm text-gray-700 block mb-1">[BARANGAY] - Barangay name</code>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Save Template
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}
</script>