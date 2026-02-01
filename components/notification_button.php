<?php
// components/notification_button.php - Reusable notification button component for all dashboards
// Usage: Include this file in any dashboard header where notifications are needed
// Requires: $user_id, $conn (database connection)

// Get unread notifications count
try {
    $notif_count_query = "SELECT COUNT(*) as unread_count FROM notifications 
                         WHERE user_id = :user_id AND is_read = 0";
    $notif_count_stmt = $conn->prepare($notif_count_query);
    $notif_count_stmt->bindParam(':user_id', $user_id);
    $notif_count_stmt->execute();
    $notif_count_result = $notif_count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications = $notif_count_result['unread_count'] ?? 0;
    
    // Get latest notifications for dropdown
    $latest_notif_query = "SELECT n.*, r.report_number 
                          FROM notifications n
                          LEFT JOIN reports r ON n.related_id = r.id
                          WHERE n.user_id = :user_id 
                          ORDER BY n.created_at DESC 
                          LIMIT 5";
    $latest_notif_stmt = $conn->prepare($latest_notif_query);
    $latest_notif_stmt->bindParam(':user_id', $user_id);
    $latest_notif_stmt->execute();
    $latest_notifications = $latest_notif_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $unread_notifications = 0;
    $latest_notifications = [];
}
?>

<!-- Notification Button Component -->
<div class="relative">
    <button id="notificationButton" class="relative p-2 text-gray-600 hover:text-blue-600 rounded-full hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
        <i class="fas fa-bell text-lg"></i>
        <?php if ($unread_notifications > 0): ?>
            <span class="absolute top-0 right-0 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold animate-pulse">
                <?php echo min($unread_notifications, 9); ?>
            </span>
        <?php endif; ?>
    </button>
    
    <!-- Notification Dropdown -->
    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50">
        <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
            <h3 class="font-semibold text-gray-800 flex items-center">
                <i class="fas fa-bell mr-2 text-blue-600"></i>
                Notifications
            </h3>
            <p class="text-sm text-gray-600 mt-1">Latest updates and alerts</p>
        </div>
        
        <div class="max-h-96 overflow-y-auto" id="notificationList">
            <?php if (count($latest_notifications) > 0): ?>
                <?php foreach ($latest_notifications as $notification): ?>
                    <a href="javascript:void(0)" onclick="handleNotificationClick(<?php echo $notification['id']; ?>, <?php echo $notification['related_id'] ?? 'null'; ?>)" 
                       class="block p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors notification-item <?php echo $notification['is_read'] == 0 ? 'bg-yellow-50' : ''; ?>">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <?php 
                                $icon_class = 'fa-info-circle';
                                $bg_color = 'bg-blue-100';
                                $text_color = 'text-blue-600';
                                
                                if ($notification['type'] == 'warning') {
                                    $icon_class = 'fa-exclamation-triangle';
                                    $bg_color = 'bg-yellow-100';
                                    $text_color = 'text-yellow-600';
                                } elseif ($notification['type'] == 'danger') {
                                    $icon_class = 'fa-exclamation-circle';
                                    $bg_color = 'bg-red-100';
                                    $text_color = 'text-red-600';
                                } elseif ($notification['type'] == 'success') {
                                    $icon_class = 'fa-check-circle';
                                    $bg_color = 'bg-green-100';
                                    $text_color = 'text-green-600';
                                }
                                ?>
                                <div class="w-10 h-10 rounded-full <?php echo $bg_color; ?> flex items-center justify-center">
                                    <i class="fas <?php echo $icon_class; ?> <?php echo $text_color; ?>"></i>
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></p>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($notification['message'], 0, 100)); ?></p>
                                <div class="flex items-center justify-between mt-2">
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </p>
                                    <?php if ($notification['is_read'] == 0): ?>
                                        <span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center">
                    <i class="fas fa-bell-slash text-gray-400 text-3xl mb-3"></i>
                    <p class="text-gray-500 text-sm">No new notifications</p>
                    <p class="text-gray-400 text-xs mt-1">Check back later for updates</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-3 border-t border-gray-200 bg-gray-50">
            <button onclick="markAllNotificationsAsRead()" class="w-full text-center text-sm text-blue-600 hover:text-blue-700 font-medium py-2 rounded hover:bg-blue-50 transition-colors">
                <i class="fas fa-check-double mr-1"></i>
                Mark all as read
            </button>
        </div>
    </div>
</div>

<script>
// Notification button functionality
const notificationButton = document.getElementById('notificationButton');
const notificationDropdown = document.getElementById('notificationDropdown');

if (notificationButton && notificationDropdown) {
    notificationButton.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('hidden');
    });
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (notificationDropdown && !notificationDropdown.contains(e.target) && e.target !== notificationButton) {
        notificationDropdown.classList.add('hidden');
    }
});

// Handle notification click
function handleNotificationClick(notificationId, reportId = null) {
    // Mark notification as read via AJAX
    fetch(`../ajax/mark_notification_read.php?notification_id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove highlight from notification item
                const notificationItem = document.querySelector(`.notification-item[onclick*="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('bg-yellow-50');
                    // Remove new indicator
                    const newIndicator = notificationItem.querySelector('.bg-red-500');
                    if (newIndicator) {
                        newIndicator.remove();
                    }
                }
                
                // Update badge count
                const badge = document.querySelector('#notificationButton .bg-red-500');
                if (badge) {
                    const count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count > 9 ? '9+' : count;
                    } else {
                        badge.remove();
                    }
                }
            }
        })
        .catch(error => console.error('Error:', error));
}

// Mark all notifications as read
function markAllNotificationsAsRead() {
    fetch('../ajax/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove badge
            const badge = document.querySelector('#notificationButton .bg-red-500');
            if (badge) badge.remove();
            
            // Remove yellow highlights
            document.querySelectorAll('.notification-item.bg-yellow-50').forEach(item => {
                item.classList.remove('bg-yellow-50');
            });
            
            // Remove new indicators
            document.querySelectorAll('.notification-item .bg-red-500').forEach(indicator => {
                indicator.remove();
            });
        }
    })
    .catch(error => console.error('Error:', error));
}

// Auto-refresh notifications every 60 seconds
setInterval(() => {
    fetch('../ajax/get_user_notifications.php?limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unread_count > 0) {
                // Update badge
                const badge = document.querySelector('#notificationButton .bg-red-500');
                if (badge) {
                    badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'absolute top-0 right-0 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold animate-pulse';
                    newBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                    document.getElementById('notificationButton').appendChild(newBadge);
                }
            }
        })
        .catch(error => console.error('Error refreshing notifications:', error));
}, 60000);
</script>
