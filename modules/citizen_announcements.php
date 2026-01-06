<?php
// citizen_announcements.php - Announcements Module
require_once __DIR__ . '/../config/database.php';

// Check if session variables are set
if (!isset($_SESSION['user_id'])) {
    die("Session expired. Please refresh the page.");
}

$user_id = $_SESSION['user_id'];
$barangay = $_SESSION['barangay'];;

try {
    $conn = getDbConnection();
    
    // Get all announcements
    $query = "SELECT a.*, 
              CASE 
                WHEN a.priority = 'high' THEN 3
                WHEN a.priority = 'medium' THEN 2
                ELSE 1 
              END as priority_order
              FROM announcements a
              WHERE (a.target_role = 'citizen' OR a.target_role = 'all')
              AND (a.barangay = :barangay OR a.barangay = 'all')
              AND a.is_active = 1
              ORDER BY priority_order DESC, a.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':barangay', $barangay);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get emergency alerts
    $emergency_query = "SELECT * FROM announcements 
                       WHERE target_role = 'citizen' 
                       AND barangay IN (:barangay, 'all')
                       AND is_emergency = 1
                       AND is_active = 1
                       ORDER BY created_at DESC 
                       LIMIT 3";
    $emergency_stmt = $conn->prepare($emergency_query);
    $emergency_stmt->bindParam(':barangay', $barangay);
    $emergency_stmt->execute();
    $emergency_alerts = $emergency_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lost and found
    $lost_query = "SELECT * FROM lost_found_items 
                  WHERE barangay IN (:barangay, 'all')
                  AND status = 'active'
                  ORDER BY created_at DESC 
                  LIMIT 5";
    $lost_stmt = $conn->prepare($lost_query);
    $lost_stmt->bindParam(':barangay', $barangay);
    $lost_stmt->execute();
    $lost_items = $lost_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Announcements Error: " . $e->getMessage());
}

// Function to get priority badge
function getPriorityBadge($priority) {
    $badges = [
        'high' => 'bg-red-100 text-red-800 border-red-200',
        'medium' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'low' => 'bg-blue-100 text-blue-800 border-blue-200'
    ];
    return $badges[$priority] ?? 'bg-gray-100 text-gray-800 border-gray-200';
}

// Function to time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>

<div class="max-w-7xl mx-auto">
 
    
    <!-- Emergency Alerts -->
    <?php if (count($emergency_alerts) > 0): ?>
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Emergency Alerts
                </h3>
                <span class="text-sm text-gray-500"><?php echo count($emergency_alerts); ?> active alert(s)</span>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($emergency_alerts as $alert): ?>
                    <div class="bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 p-5 rounded-r-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center animate-pulse">
                                    <i class="fas fa-bell text-red-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-bold text-red-800 text-lg"><?php echo htmlspecialchars($alert['title']); ?></h4>
                                    <span class="text-xs text-red-600 font-semibold">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo timeAgo($alert['created_at']); ?>
                                    </span>
                                </div>
                                <p class="text-red-700 mb-3"><?php echo nl2br(htmlspecialchars($alert['content'])); ?></p>
                                <?php if (!empty($alert['attachment'])): ?>
                                    <a href="../uploads/announcements/<?php echo htmlspecialchars($alert['attachment']); ?>" target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        View Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- NEW: Notification Subscription -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                    <i class="fas fa-bell text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 mb-1">Push Notifications</h4>
                    <p class="text-sm text-gray-600">Get instant alerts for new announcements</p>
                </div>
            </div>
            <button onclick="subscribeNotifications()" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                <i class="fas fa-bell mr-2"></i>
                Enable Notifications
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Announcements List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Community Announcements</h3>
                </div>
                
                <?php if (count($announcements) > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="p-6 hover:bg-gray-50 transition-colors" data-announcement="<?php echo $announcement['id']; ?>">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-800 text-lg mb-1">
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h4>
                                        <div class="flex items-center space-x-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getPriorityBadge($announcement['priority']); ?>">
                                                <i class="fas fa-flag mr-1"></i>
                                                <?php echo ucfirst($announcement['priority']); ?> Priority
                                            </span>
                                            <span class="text-sm text-gray-500">
                                                <i class="far fa-calendar mr-1"></i>
                                                <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($announcement['is_pinned']): ?>
                                        <span class="text-yellow-500">
                                            <i class="fas fa-thumbtack"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="prose max-w-none text-gray-600 mb-4">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                
                                <?php if (!empty($announcement['attachment'])): ?>
                                    <div class="mt-4">
                                        <a href="../uploads/announcements/<?php echo htmlspecialchars($announcement['attachment']); ?>" 
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                                            <i class="fas fa-paperclip mr-2"></i>
                                            View Attachment
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4 pt-4 border-t flex items-center justify-between text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-circle mr-2"></i>
                                        <span>Posted by: <?php echo htmlspecialchars($announcement['posted_by'] ?: 'Barangay Official'); ?></span>
                                    </div>
                                    <div>
                                        <?php if ($announcement['barangay'] != 'all'): ?>
                                            <span class="inline-flex items-center">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <?php echo htmlspecialchars($announcement['barangay']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-bullhorn text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No announcements yet</h3>
                        <p class="text-gray-500">Check back later for updates from your barangay.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Lost & Found -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-search text-blue-500 mr-2"></i>
                    Lost & Found
                </h3>
                
                <?php if (count($lost_items) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($lost_items as $item): ?>
                            <div class="p-4 border rounded-lg hover:bg-gray-50">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-lg <?php echo $item['item_type'] == 'lost' ? 'bg-red-100' : 'bg-green-100'; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $item['item_type'] == 'lost' ? 'fa-search text-red-600' : 'fa-check text-green-600'; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="font-medium text-gray-800 text-sm">
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                        </p>
                                        <div class="mt-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                <?php echo $item['item_type'] == 'lost' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <?php echo ucfirst($item['item_type']); ?>
                                            </span>
                                            <span class="ml-2 text-xs text-gray-500">
                                                <?php echo timeAgo($item['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t">
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-700 font-medium block text-center">
                            View All Lost & Found Items
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-search text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 text-sm">No lost or found items reported</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Links</h3>
                <div class="space-y-3">
                    <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                            <i class="fas fa-calendar-alt text-purple-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Events Calendar</p>
                            <p class="text-xs text-gray-500">Upcoming barangay events</p>
                        </div>
                    </a>
                    
                    <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                            <i class="fas fa-file-pdf text-blue-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Ordinances</p>
                            <p class="text-xs text-gray-500">Barangay rules & regulations</p>
                        </div>
                    </a>
                    
                    <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mr-3">
                            <i class="fas fa-users text-green-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Officials Directory</p>
                            <p class="text-xs text-gray-500">Contact barangay officials</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl p-6 text-white">
                <h3 class="text-lg font-semibold mb-4">Community Stats</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Total Reports</p>
                            <p class="text-2xl font-bold"><?php echo count($announcements); ?></p>
                        </div>
                        <i class="fas fa-chart-line text-2xl opacity-80"></i>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Emergency Alerts</p>
                            <p class="text-2xl font-bold"><?php echo count($emergency_alerts); ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-2xl opacity-80"></i>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Active Items</p>
                            <p class="text-2xl font-bold"><?php echo count($lost_items); ?></p>
                        </div>
                        <i class="fas fa-search text-2xl opacity-80"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mark announcement as read
function markAsRead(announcementId) {
    fetch(`ajax/mark_announcement_read.php?id=${announcementId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.querySelector(`[data-announcement="${announcementId}"]`);
                if (badge) {
                    badge.style.opacity = '0.7';
                }
            }
        });
}

// NEW: Subscribe to notifications
function subscribeNotifications() {
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                fetch('ajax/subscribe_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        subscribe: true,
                        user_id: <?php echo $user_id; ?>
                    })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          alert('You will now receive notifications for new announcements.');
                      }
                  });
            } else if (permission === 'denied') {
                alert('Notifications blocked. Please enable them in your browser settings.');
            }
        });
    } else {
        alert('Your browser does not support notifications.');
    }
}

// NEW: Emergency alert sound
function playAlertSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.type = 'sine';
        oscillator.frequency.value = 800;
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 1);
        
        // Vibrate if supported
        if (navigator.vibrate) {
            navigator.vibrate([200, 100, 200, 100, 200]);
        }
    } catch (e) {
        console.log('Audio/vibration not supported:', e);
    }
}

// NEW: Check for new announcements every 5 minutes
setInterval(() => {
    fetch('ajax/check_new_announcements.php?last_check=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            if (data.new_count > 0) {
                // Show notification
                if (Notification.permission === 'granted') {
                    new Notification('New Announcements', {
                        body: `You have ${data.new_count} new announcement(s)`,
                        icon: '../dec/images/10213.png',
                        tag: 'new-announcements'
                    });
                }
                
                // Play sound if emergency
                if (data.has_emergency) {
                    playAlertSound();
                }
                
                // Update badge
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    const current = parseInt(badge.textContent) || 0;
                    badge.textContent = current + data.new_count;
                }
                
                // Show toast notification
                showToastNotification(data.new_count, data.has_emergency);
            }
        })
        .catch(error => console.error('Polling error:', error));
}, 300000); // 5 minutes

// NEW: Show toast notification
function showToastNotification(count, isEmergency) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
        isEmergency ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${isEmergency ? 'fa-exclamation-triangle' : 'fa-bell'} mr-3"></i>
            <div>
                <p class="font-semibold">${isEmergency ? '🚨 Emergency Alert' : 'New Announcements'}</p>
                <p class="text-sm opacity-90">${count} new announcement(s)</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// NEW: Mark all as read
function markAllAsRead() {
    fetch('ajax/mark_all_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: <?php echo $user_id; ?> })
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              // Remove all badges
              document.querySelectorAll('.notification-badge').forEach(badge => badge.remove());
              // Update announcement styles
              document.querySelectorAll('[data-announcement]').forEach(ann => {
                  ann.style.opacity = '0.7';
              });
          }
      });
}
</script>