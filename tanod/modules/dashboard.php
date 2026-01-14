<?php
// dashboard.php - Fixed version

// Don't call session_start() again since it's already started in tanod_dashboard.php
// Remove line: session_start();

// Include database configuration with correct relative path
require_once __DIR__ . '/../../config/database.php';

// Now include the config.php directly since database.php already includes it
// But database.php might not set the $pdo variable globally, so let's get it
try {
    $pdo = getDbConnection();
    
    // Fetch dashboard statistics
    $stats = [
        'pending_cases' => 0,
        'approaching_deadline' => 0,
        'total_reports' => 0
    ];
    
    // Example queries - adjust based on your actual database structure
    // Pending cases
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cases WHERE status = 'pending'");
    $stats['pending_cases'] = $stmt->fetchColumn() ?: 0;
    
    // Approaching deadline (within 3 days)
    $deadlineDate = date('Y-m-d', strtotime('+3 days'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cases WHERE deadline_date <= :deadline AND status != 'completed'");
    $stmt->execute(['deadline' => $deadlineDate]);
    $stats['approaching_deadline'] = $stmt->fetchColumn() ?: 0;
    
    // Total reports
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $stats['total_reports'] = $stmt->fetchColumn() ?: 0;
    
    // Fetch recent reports (last 5)
    $stmt = $pdo->query("SELECT r.*, u.first_name, u.last_name FROM reports r 
                         LEFT JOIN users u ON r.user_id = u.id 
                         ORDER BY r.created_at DESC LIMIT 5");
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch recent announcements
    $stmt = $pdo->query("SELECT * FROM announcements 
                         ORDER BY created_at DESC LIMIT 5");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user address if available
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT address FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_address = $user_data['address'] ?? 'Barangay Hall';
    } else {
        $user_address = 'Barangay Hall';
    }
    
} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Dashboard Error: " . $e->getMessage());
    // Set default values if database fails
    $stats = [
        'pending_cases' => 0,
        'approaching_deadline' => 0,
        'total_reports' => 0
    ];
    $recent_reports = [];
    $announcements = [];
    $user_address = 'Barangay Hall';
}

// If session variables aren't set, set defaults
if (!isset($_SESSION['first_name'])) {
    $_SESSION['first_name'] = 'User';
}
?>

<!-- Dashboard Overview -->
<div class="space-y-8">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl p-6 text-white mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
                <p class="opacity-90">Your central hub for managing barangay cases, documents, and referrals.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex items-center space-x-2 bg-white bg-opacity-20 backdrop-blur-sm rounded-lg p-3">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-30 flex items-center justify-center">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Secretary Office</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($user_address ?? 'Barangay Hall'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column -->
        <div class="space-y-8">
            <!-- Quick Stats -->
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-chart-bar mr-3 text-blue-600"></i>
                    Quick Statistics
                </h2>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="stat-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Pending Cases</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_cases'] ?? 0; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Approaching Deadline</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['approaching_deadline'] ?? 0; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Reports</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_reports'] ?? 0; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">External Referrals</p>
                                <p class="text-2xl font-bold text-gray-800">8</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-bolt mr-3 text-blue-600"></i>
                    Quick Actions
                </h2>
                
                <div class="grid grid-cols-2 gap-4">
                    <a href="?module=case" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-gavel text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Assign Case</p>
                                <p class="text-sm text-gray-600">New blotter assignment</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="?module=documents" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file-contract text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Generate Document</p>
                                <p class="text-sm text-gray-600">Legal paperwork</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="?module=referral" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-shield-alt text-red-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">VAWC Referral</p>
                                <p class="text-sm text-gray-600">Confidential protocol</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="?module=compliance" class="module-card bg-white rounded-lg p-4 shadow-sm hover:shadow-md">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-clock text-orange-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Check Deadlines</p>
                                <p class="text-sm text-gray-600">RA 7160 compliance</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Recent Reports -->
            <?php if (isset($recent_reports) && count($recent_reports) > 0): ?>
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-file-alt mr-3 text-blue-600"></i>
                    Recent Reports
                </h2>
                
                <div class="space-y-4">
                    <?php foreach ($recent_reports as $report): ?>
                    <div class="bg-white rounded-lg p-4 border border-gray-200 hover:border-blue-300 transition-colors">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($report['title']); ?></h4>
                            <span class="status-badge status-<?php echo isset($report['status']) ? str_replace('_', '-', $report['status']) : 'pending'; ?>">
                                <?php echo isset($report['status']) ? ucwords($report['status']) : 'Pending'; ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2">By: <?php echo htmlspecialchars(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')); ?></p>
                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <span><?php echo isset($report['created_at']) ? date('M d, Y', strtotime($report['created_at'])) : 'N/A'; ?></span>
                            <a href="?module=case" class="text-blue-600 hover:text-blue-800">View Details →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Modules Overview -->
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-cogs mr-3 text-blue-600"></i>
                    Secretary Functions
                </h2>
                
                <div class="space-y-4">
                    <div class="module-card bg-white rounded-lg p-5">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-gavel text-blue-600 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Case-Blotter Management</h3>
                                <p class="text-gray-600 text-sm mb-2">Issues formal blotter numbers, assigns cases to Lupon members, handles internal barangay matters</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-pending">Official Blotter Numbers</span>
                                    <span class="badge badge-processing">Assigned Lupon Members</span>
                                    <span class="badge badge-resolved">Case Notes</span>
                                </div>
                            </div>
                            <a href="?module=case" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="module-card bg-white rounded-lg p-5">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-clock text-orange-600 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Compliance Monitoring</h3>
                                <p class="text-gray-600 text-sm mb-2">Color-coded alerts for 3-day filing and 15-day resolution deadlines (RA 7160)</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Urgent: ≤1 day</span>
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Warning: 2-3 days</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">On Track: >3 days</span>
                                </div>
                            </div>
                            <a href="?module=compliance" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="module-card bg-white rounded-lg p-5">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-pdf text-green-600 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Document Generation</h3>
                                <p class="text-gray-600 text-sm mb-2">Automated creation of legal paperwork with secure PDF export</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-pending">Subpoena</span>
                                    <span class="badge badge-processing">Hearing Notices</span>
                                    <span class="badge badge-resolved">Certificates</span>
                                    <span class="badge badge-referred">Resolutions</span>
                                </div>
                            </div>
                            <a href="?module=documents" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="module-card bg-white rounded-lg p-5 urgent">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-shield-alt text-red-600 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">External Referral Desk</h3>
                                <p class="text-gray-600 text-sm mb-2">Confidential protocols for VAWC/minor cases, digital handover to PNP/City Hall</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-vawc">VAWC Protocol</span>
                                    <span class="badge badge-minor">Minor Cases</span>
                                    <span class="badge badge-referred">Digital Handover</span>
                                </div>
                            </div>
                            <a href="?module=referral" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Announcements -->
            <?php if (isset($announcements) && count($announcements) > 0): ?>
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-bullhorn mr-3 text-blue-600"></i>
                    Recent Announcements
                </h2>
                
                <div class="space-y-4">
                    <?php foreach ($announcements as $announcement): ?>
                    <div class="bg-white rounded-lg p-4 border border-gray-200 hover:bg-gray-50 transition-colors">
                        <h4 class="font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p class="text-sm text-gray-600 mb-2 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['content'] ?? '', 0, 100)); ?>...</p>
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span><?php echo isset($announcement['created_at']) ? date('M d, Y', strtotime($announcement['created_at'])) : 'N/A'; ?></span>
                            <?php if (isset($announcement['priority']) && $announcement['priority'] == 'high'): ?>
                            <span class="px-2 py-1 bg-red-100 text-red-600 rounded-full text-xs font-medium">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Important
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>