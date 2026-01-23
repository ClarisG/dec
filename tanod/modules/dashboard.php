<?php
// dashboard.php - Tanod Dashboard

// Database connection
require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDbConnection();
    
    // Fetch tanod-specific statistics
    $stats = [
        'incidents_today' => 0,
        'pending_reports' => 0,
        'active_duty' => 0,
        'evidence_logged' => 0
    ];
    
    // Today's incidents
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tanod_incidents WHERE user_id = ? AND DATE(reported_at) = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['incidents_today'] = $stmt->fetchColumn() ?: 0;
    
    // Pending reports for vetting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM reports 
        WHERE assigned_tanod = ? AND status = 'assigned_for_verification'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_reports'] = $stmt->fetchColumn() ?: 0;
    
    // Check if on duty
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['active_duty'] = $stmt->fetchColumn() ?: 0;
    
    // Evidence logged this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM evidence_handovers 
        WHERE tanod_id = ? AND MONTH(handover_date) = MONTH(CURDATE())
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['evidence_logged'] = $stmt->fetchColumn() ?: 0;
    
    // Recent incidents (last 3)
    $stmt = $pdo->prepare("
        SELECT * FROM tanod_incidents 
        WHERE user_id = ? 
        ORDER BY reported_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = [
        'incidents_today' => 0,
        'pending_reports' => 0,
        'active_duty' => 0,
        'evidence_logged' => 0
    ];
    $recent_incidents = [];
}
?>

<!-- Compact Dashboard Layout -->
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-2">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Field Operations Dashboard</h1>
            <p class="text-gray-600 text-sm">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1 <?php echo $stats['active_duty'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> rounded-full text-sm font-medium">
                <i class="fas <?php echo $stats['active_duty'] ? 'fa-signal' : 'fa-signal-slash'; ?> mr-1"></i>
                <?php echo $stats['active_duty'] ? 'ON DUTY' : 'OFF DUTY'; ?>
            </span>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Today's Incidents</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $stats['incidents_today']; ?></p>
                </div>
                <div class="h-10 w-10 bg-blue-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-blue-500"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Reports to Vet</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['pending_reports']; ?></p>
                </div>
                <div class="h-10 w-10 bg-orange-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-check text-orange-500"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Evidence Logged</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo $stats['evidence_logged']; ?></p>
                </div>
                <div class="h-10 w-10 bg-purple-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-box text-purple-500"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p class="text-lg font-bold <?php echo $stats['active_duty'] ? 'text-green-600' : 'text-gray-600'; ?>">
                        <?php echo $stats['active_duty'] ? 'Active Patrol' : 'Standing By'; ?>
                    </p>
                </div>
                <div class="h-10 w-10 <?php echo $stats['active_duty'] ? 'bg-green-50' : 'bg-gray-50'; ?> rounded-lg flex items-center justify-center">
                    <i class="fas <?php echo $stats['active_duty'] ? 'fa-walking text-green-500' : 'fa-pause text-gray-500'; ?>"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="?module=incident_logging" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-blue-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-exclamation-triangle text-blue-500 text-xl"></i>
                </div>
                <p class="font-medium text-gray-800 text-sm">Log Incident</p>
                <p class="text-xs text-gray-500 mt-1">Field reporting</p>
            </div>
        </a>
        
        <a href="?module=report_vetting" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-green-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-green-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-user-check text-green-500 text-xl"></i>
                </div>
                <p class="font-medium text-gray-800 text-sm">Verify Reports</p>
                <p class="text-xs text-gray-500 mt-1">Citizen vetting</p>
            </div>
        </a>
        
        <a href="?module=evidence_handover" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-purple-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-purple-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-box text-purple-500 text-xl"></i>
                </div>
                <p class="font-medium text-gray-800 text-sm">Evidence Log</p>
                <p class="text-xs text-gray-500 mt-1">Chain of custody</p>
            </div>
        </a>
        
        <a href="?module=duty_schedule" class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 hover:border-orange-300 hover:shadow-md transition-all duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 bg-orange-50 rounded-lg flex items-center justify-center mb-3">
                    <i class="fas fa-calendar-alt text-orange-500 text-xl"></i>
                </div>
                <p class="font-medium text-gray-800 text-sm">Duty Schedule</p>
                <p class="text-xs text-gray-500 mt-1">Shift management</p>
            </div>
        </a>
    </div>
    
    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Incidents -->
        <?php if (!empty($recent_incidents)): ?>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800">Recent Incidents</h3>
                <a href="?module=incident_logging" class="text-sm text-blue-600 hover:text-blue-800">View All →</a>
            </div>
            <div class="space-y-3">
                <?php foreach ($recent_incidents as $incident): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="h-8 w-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation text-blue-500 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($incident['incident_type']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($incident['reported_at'])); ?></p>
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full <?php echo $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                        <?php echo ucfirst($incident['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Announcements -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800">System Notices</h3>
                <span class="text-xs text-gray-500">Updated Today</span>
            </div>
            <div class="space-y-3">
                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                    <div>
                        <p class="text-sm font-medium text-blue-800">System Upgrade</p>
                        <p class="text-xs text-blue-700">Enhanced GPS tracking activated. Ensure location services are enabled.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg border border-green-200">
                    <i class="fas fa-shield-alt text-green-500 mt-1"></i>
                    <div>
                        <p class="text-sm font-medium text-green-800">Security Protocol</p>
                        <p class="text-xs text-green-700">New evidence handling guidelines have been implemented.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Critical Data Notice -->
    <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-indigo-500 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-database text-indigo-500 mt-1 mr-3"></i>
            <div>
                <p class="text-sm font-medium text-indigo-800">Critical Data Protected</p>
                <p class="text-xs text-indigo-700">All field operations are logged with timestamp, location, and digital signatures for accountability.</p>
            </div>
        </div>
    </div>
</div>