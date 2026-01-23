<?php
// tanod/modules/dashboard.php - Tanod Dashboard
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'];

try {
    $pdo = getDbConnection();
    
    // Fetch current duty status
    $duty_stmt = $pdo->prepare("
        SELECT dl.*, ts.schedule_date, ts.shift_start, ts.shift_end, 
               ts.patrol_route, a.area_name, ts.shift_type
        FROM tanod_duty_logs dl
        LEFT JOIN tanod_schedules ts ON dl.schedule_id = ts.id
        LEFT JOIN patrol_areas a ON ts.patrol_area_id = a.id
        WHERE dl.user_id = ? AND dl.clock_out IS NULL 
        ORDER BY dl.clock_in DESC LIMIT 1
    ");
    $duty_stmt->execute([$tanod_id]);
    $current_duty = $duty_stmt->fetch();
    
    // Dashboard statistics - Optimized queries
    $stats = [
        'active_duty' => $current_duty ? 1 : 0,
        'incidents_today' => 0,
        'pending_vetting' => 0,
        'evidence_pending' => 0,
        'patrol_hours' => 0,
        'verified_reports' => 0
    ];
    
    // Today's incidents
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM tanod_incidents 
        WHERE user_id = ? AND DATE(reported_at) = CURDATE()
    ");
    $stmt->execute([$tanod_id]);
    $stats['incidents_today'] = $stmt->fetchColumn() ?: 0;
    
    // Reports pending vetting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM reports 
        WHERE assigned_tanod = ? AND status IN ('pending_field_verification', 'assigned_for_verification')
    ");
    $stmt->execute([$tanod_id]);
    $stats['pending_vetting'] = $stmt->fetchColumn() ?: 0;
    
    // Evidence pending acknowledgement
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM evidence_handovers 
        WHERE tanod_id = ? AND recipient_acknowledged = 0
    ");
    $stmt->execute([$tanod_id]);
    $stats['evidence_pending'] = $stmt->fetchColumn() ?: 0;
    
    // Patrol hours this week
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_hours), 0) as total_hours 
        FROM tanod_duty_logs 
        WHERE user_id = ? AND clock_in >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$tanod_id]);
    $patrol_stats = $stmt->fetch();
    $stats['patrol_hours'] = $patrol_stats['total_hours'] ?: 0;
    
    // Verified reports count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM reports 
        WHERE verified_by = ? AND status IN ('verified', 'verified_approved')
    ");
    $stmt->execute([$tanod_id]);
    $stats['verified_reports'] = $stmt->fetchColumn() ?: 0;
    
    // Recent assignments (last 5)
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.location, r.status, 
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name,
               r.created_at, rt.name as report_type
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN report_types rt ON r.report_type_id = rt.id
        WHERE r.assigned_tanod = ? 
        AND r.status IN ('pending_field_verification', 'assigned_for_verification')
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$tanod_id]);
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent incidents (last 5)
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_incidents 
        WHERE user_id = ? 
        ORDER BY reported_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$tanod_id]);
    $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System announcements
    $stmt = $pdo->prepare("
        SELECT * FROM announcements 
        WHERE (target_role = 'tanod' OR target_role = 'all')
        AND status = 'active'
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = ['active_duty' => 0, 'incidents_today' => 0, 'pending_vetting' => 0, 
              'evidence_pending' => 0, 'patrol_hours' => 0, 'verified_reports' => 0];
    $recent_assignments = [];
    $recent_incidents = [];
    $announcements = [];
    $current_duty = null;
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Field Operations Dashboard</h1>
            <p class="text-gray-600 text-sm mt-1">Welcome back, <?php echo htmlspecialchars($tanod_name); ?> | ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1.5 <?php echo $stats['active_duty'] ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-gray-100 text-gray-800 border border-gray-200'; ?> rounded-full text-sm font-medium flex items-center">
                <span class="w-2 h-2 rounded-full <?php echo $stats['active_duty'] ? 'bg-green-500 pulse-dot' : 'bg-gray-400'; ?> mr-2"></span>
                <?php echo $stats['active_duty'] ? 'ON DUTY' : 'OFF DUTY'; ?>
            </span>
        </div>
    </div>
    
    <!-- System Announcements -->
    <?php if (!empty($announcements)): ?>
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg p-4 mb-4">
        <div class="flex items-start">
            <i class="fas fa-bullhorn text-blue-500 mt-1 mr-3"></i>
            <div class="flex-1">
                <h3 class="font-bold text-blue-800 text-sm mb-2">System Announcements</h3>
                <div class="space-y-3">
                    <?php foreach ($announcements as $announcement): ?>
                    <div class="pb-3 <?php echo !$loop['last'] ? 'border-b border-blue-100' : ''; ?>">
                        <p class="text-sm font-medium text-blue-900"><?php echo htmlspecialchars($announcement['title']); ?></p>
                        <p class="text-xs text-blue-700 mt-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                        <p class="text-xs text-blue-600 mt-2"><?php echo date('M d, Y - h:i A', strtotime($announcement['created_at'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Active Patrol Banner -->
    <?php if ($current_duty): ?>
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-route text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-blue-800">Active Patrol</p>
                    <p class="text-xs text-blue-700">
                        <span class="font-medium"><?php echo $current_duty['area_name'] ?? $current_duty['patrol_route'] ?? 'General Patrol'; ?></span>
                        • Since <?php echo date('h:i A', strtotime($current_duty['clock_in'])); ?>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-blue-600 font-medium">
                    <?php echo date('h:i A', strtotime($current_duty['shift_start'] ?? '')); ?> - 
                    <?php echo date('h:i A', strtotime($current_duty['shift_end'] ?? '')); ?>
                </p>
                <a href="?module=duty_schedule" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
                    <i class="fas fa-external-link-alt mr-1"></i>Manage
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Duty Status -->
        <a href="?module=duty_schedule" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 <?php echo $stats['active_duty'] ? 'bg-green-50' : 'bg-gray-50'; ?> rounded-lg flex items-center justify-center mb-3">
                    <i class="fas <?php echo $stats['active_duty'] ? 'fa-walking text-green-500' : 'fa-pause text-gray-400'; ?> text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Status</p>
                <p class="text-lg font-bold <?php echo $stats['active_duty'] ? 'text-green-600' : 'text-gray-600'; ?>">
                    <?php echo $stats['active_duty'] ? 'Active' : 'Standby'; ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <?php echo $current_duty ? date('h:i A', strtotime($current_duty['clock_in'])) : 'Clock in to start'; ?>
                </p>
            </div>
        </a>
        
        <!-- Today's Incidents -->
        <a href="?module=incident_logging" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-blue-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-exclamation-triangle text-blue-500 text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Incidents Today</p>
                <p class="text-lg font-bold text-blue-600"><?php echo $stats['incidents_today']; ?></p>
                <p class="text-xs text-gray-500 mt-1">Field logging</p>
            </div>
        </a>
        
        <!-- Reports to Vet -->
        <a href="?module=report_vetting" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-orange-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-orange-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-user-check text-orange-500 text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Reports to Vet</p>
                <p class="text-lg font-bold text-orange-600"><?php echo $stats['pending_vetting']; ?></p>
                <p class="text-xs text-gray-500 mt-1">Field verification</p>
            </div>
        </a>
        
        <!-- Evidence Pending -->
        <a href="?module=evidence_handover" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-purple-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-purple-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-box text-purple-500 text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Evidence Pending</p>
                <p class="text-lg font-bold text-purple-600"><?php echo $stats['evidence_pending']; ?></p>
                <p class="text-xs text-gray-500 mt-1">Chain of custody</p>
            </div>
        </a>
        
        <!-- Weekly Patrol Hours -->
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-clock text-indigo-500 text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Patrol Hours</p>
                <p class="text-lg font-bold text-indigo-600"><?php echo number_format($stats['patrol_hours'], 1); ?></p>
                <p class="text-xs text-gray-500 mt-1">This week</p>
            </div>
        </div>
        
        <!-- Verified Reports -->
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-green-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-clipboard-check text-green-500 text-xl"></i>
                </div>
                <p class="text-xs text-gray-500 font-medium mb-1">Verified Reports</p>
                <p class="text-lg font-bold text-green-600"><?php echo $stats['verified_reports']; ?></p>
                <p class="text-xs text-gray-500 mt-1">Field validated</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
        <!-- Recent Assignments -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800 text-lg">Recent Assignments</h3>
                <a href="?module=report_vetting" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center">
                    View All <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>
            
            <?php if (!empty($recent_assignments)): ?>
            <div class="space-y-3">
                <?php foreach ($recent_assignments as $assignment): 
                    $status_bg = '';
                    if ($assignment['status'] === 'pending_field_verification') {
                        $status_bg = 'bg-yellow-50 text-yellow-800 border-yellow-200';
                    } elseif ($assignment['status'] === 'assigned_for_verification') {
                        $status_bg = 'bg-blue-50 text-blue-800 border-blue-200';
                    } else {
                        $status_bg = 'bg-gray-50 text-gray-800 border-gray-200';
                    }
                ?>
                <a href="?module=report_vetting&view_report=<?php echo $assignment['id']; ?>" 
                   class="block p-3 rounded-lg border hover:shadow-sm transition-all duration-200 <?php echo $status_bg; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="text-sm font-medium text-gray-800 truncate flex-1">
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </span>
                                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-white border font-medium">
                                    <?php echo $assignment['report_type'] ?: 'Report'; ?>
                                </span>
                            </div>
                            <div class="flex items-center text-xs text-gray-600 space-x-4">
                                <span class="flex items-center">
                                    <i class="fas fa-user mr-1.5 text-gray-400"></i>
                                    <?php echo htmlspecialchars($assignment['reporter_name']); ?>
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-map-marker-alt mr-1.5 text-gray-400"></i>
                                    <?php echo htmlspecialchars(substr($assignment['location'], 0, 20)); ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 text-right ml-2">
                            <?php echo date('M d', strtotime($assignment['created_at'])); ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-300 text-4xl mb-3">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <p class="text-gray-500 text-sm">No pending assignments</p>
                <p class="text-xs text-gray-400 mt-1">Reports will appear here when assigned</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions & Recent Incidents -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                <h3 class="font-bold text-gray-800 text-lg mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="?module=duty_schedule" class="p-3 bg-gradient-to-br from-gray-50 to-gray-100 hover:from-blue-50 hover:to-blue-100 rounded-lg border border-gray-200 hover:border-blue-300 transition-all duration-200 text-center group">
                        <div class="h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-calendar-alt text-white text-sm"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-blue-700">Duty Schedule</p>
                        <p class="text-xs text-gray-500 mt-1">Shift management</p>
                    </a>
                    
                    <a href="?module=incident_logging" class="p-3 bg-gradient-to-br from-gray-50 to-gray-100 hover:from-green-50 hover:to-green-100 rounded-lg border border-gray-200 hover:border-green-300 transition-all duration-200 text-center group">
                        <div class="h-10 w-10 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-green-700">Log Incident</p>
                        <p class="text-xs text-gray-500 mt-1">Field reporting</p>
                    </a>
                    
                    <a href="?module=evidence_handover" class="p-3 bg-gradient-to-br from-gray-50 to-gray-100 hover:from-purple-50 hover:to-purple-100 rounded-lg border border-gray-200 hover:border-purple-300 transition-all duration-200 text-center group">
                        <div class="h-10 w-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-box text-white text-sm"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-purple-700">Evidence Log</p>
                        <p class="text-xs text-gray-500 mt-1">Chain of custody</p>
                    </a>
                    
                    <a href="?module=report_vetting" class="p-3 bg-gradient-to-br from-gray-50 to-gray-100 hover:from-orange-50 hover:to-orange-100 rounded-lg border border-gray-200 hover:border-orange-300 transition-all duration-200 text-center group">
                        <div class="h-10 w-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-user-check text-white text-sm"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-orange-700">Vet Reports</p>
                        <p class="text-xs text-gray-500 mt-1">Field verification</p>
                    </a>
                </div>
            </div>
            
            <!-- Recent Incidents -->
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
                <h3 class="font-bold text-gray-800 text-lg mb-4">Recent Incidents</h3>
                
                <?php if (!empty($recent_incidents)): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_incidents as $incident): ?>
                    <div class="p-3 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="text-sm font-medium text-gray-800">
                                        INC-<?php echo str_pad($incident['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <span class="ml-3 px-2 py-1 text-xs rounded-full 
                                        <?php echo $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($incident['status'] === 'processed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($incident['status']); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo htmlspecialchars(substr($incident['location'], 0, 30)); ?>...
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M d', strtotime($incident['reported_at'])); ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php echo date('h:i A', strtotime($incident['reported_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500 text-sm">No incidents logged today</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Data Security Notice -->
    <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-shield-alt text-blue-500 mr-3"></i>
            <div>
                <p class="text-sm font-medium text-gray-800">Field Operations Data Protection</p>
                <p class="text-xs text-gray-600">All operations are logged with timestamp, GPS location, and encrypted evidence files. Data integrity is maintained through automated audit trails.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Update duty status indicator
function updateDutyStatus() {
    const statusElement = document.querySelector('[class*="bg-green-100"], [class*="bg-gray-100"]');
    if (statusElement && statusElement.textContent.includes('ON DUTY')) {
        // Update time since clock in
        const sinceText = statusElement.querySelector('.text-xs.text-gray-500');
        if (sinceText && sinceText.textContent.includes('Since')) {
            // In a real implementation, you would calculate the elapsed time
            // For now, we'll just update the display every minute
            setTimeout(updateDutyStatus, 60000);
        }
    }
}

// Start updating duty status
updateDutyStatus();
</script>