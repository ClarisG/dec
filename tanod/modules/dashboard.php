<?php
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$pdo = getDbConnection();

// Get statistics
try {
    // Today's duty status
    $duty_stmt = $pdo->prepare("
        SELECT status FROM tanod_duty_logs 
        WHERE user_id = ? AND DATE(clock_in) = CURDATE() 
        ORDER BY id DESC LIMIT 1
    ");
    $duty_stmt->execute([$tanod_id]);
    $duty_status = $duty_stmt->fetchColumn() ?? 'off_duty';
    
    // Pending reports for verification
    $report_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reports 
        WHERE status = 'pending_field_verification' 
        AND barangay_id = ? 
        AND needs_field_verification = 1
    ");
    $report_stmt->execute([$barangay_id]);
    $pending_reports = $report_stmt->fetchColumn();
    
    // Assigned reports
    $assigned_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reports 
        WHERE assigned_tanod = ? 
        AND status IN ('assigned_for_verification', 'pending_field_verification')
    ");
    $assigned_stmt->execute([$tanod_id]);
    $assigned_reports = $assigned_stmt->fetchColumn();
    
    // Today's incidents
    $incident_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tanod_incidents 
        WHERE user_id = ? AND DATE(reported_at) = CURDATE()
    ");
    $incident_stmt->execute([$tanod_id]);
    $today_incidents = $incident_stmt->fetchColumn();
    
    // Active patrols
    $patrol_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tanod_duty_logs 
        WHERE barangay_id = ? AND clock_out IS NULL 
        AND DATE(clock_in) = CURDATE()
    ");
    $patrol_stmt->execute([$barangay_id]);
    $active_patrols = $patrol_stmt->fetchColumn();
    
    // Recent announcements
    $announce_stmt = $pdo->prepare("
        SELECT id, title, content, created_at, priority 
        FROM announcements 
        WHERE barangay_id = ? 
        AND (target_roles LIKE '%tanod%' OR target_roles = 'all')
        AND status = 'active'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $announce_stmt->execute([$barangay_id]);
    $announcements = $announce_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent incidents for timeline
    $timeline_stmt = $pdo->prepare("
        SELECT ti.id, ti.incident_type, ti.location, ti.reported_at,
               ti.status, r.case_number
        FROM tanod_incidents ti
        LEFT JOIN reports r ON ti.report_id = r.id
        WHERE ti.user_id = ?
        ORDER BY ti.reported_at DESC
        LIMIT 5
    ");
    $timeline_stmt->execute([$tanod_id]);
    $recent_incidents = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
        $duty_status = 'off_duty'; 
    $pending_reports = $assigned_reports = $today_incidents = $active_patrols = 0;
    $announcements = $recent_incidents = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanod Dashboard - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .module-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.15);
        }
        
        .announcement-high {
            border-left: 4px solid #ef4444;
        }
        
        .announcement-medium {
            border-left: 4px solid #f59e0b;
        }
        
        .announcement-low {
            border-left: 4px solid #10b981;
        }
        
        .status-on-duty {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .status-off-duty {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .timeline-item {
            position: relative;
            padding-left: 25px;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3b82f6;
            border: 2px solid white;
            box-shadow: 0 0 0 3px #dbeafe;
        }
        
        .timeline-item.pending::before {
            background: #f59e0b;
            box-shadow: 0 0 0 3px #fef3c7;
        }
        
        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 3px #d1fae5;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .gradient-border {
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #3b82f6, #8b5cf6) border-box;
            border: 2px solid transparent;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white">
                            <i class="fas fa-shield-alt mr-3"></i>
                            Tanod / Law Enforcement Dashboard
                        </h1>
                        <p class="text-blue-100 mt-2">Field operations, evidence collection, and initial report verification</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                                    <?php echo strtoupper(substr($tanod_name, 0, 2)); ?>
                                </div>
                                <?php if ($duty_status === 'on_duty'): ?>
                                <span class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 rounded-full border-2 border-white"></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="text-white text-sm">Tanod Officer</p>
                                <p class="text-white font-bold text-lg"><?php echo htmlspecialchars($tanod_name); ?></p>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $duty_status === 'on_duty' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <span class="w-2 h-2 mr-2 rounded-full <?php echo $duty_status === 'on_duty' ? 'bg-green-500' : 'bg-gray-500'; ?>"></span>
                                        <?php echo $duty_status === 'on_duty' ? 'ON DUTY' : 'OFF DUTY'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Critical Data Handled -->
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-bold text-blue-800">Critical Data Handled: Field-observed incident details, GPS location, Evidence chain of custody, Verification notes</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="stat-card bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Pending Verification</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $pending_reports; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Reports need field check</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Assigned to Me</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $assigned_reports; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Reports assigned for vetting</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-check text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Today's Incidents</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $today_incidents; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Field incidents logged</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Patrols</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $active_patrols; ?></p>
                        <p class="text-xs text-gray-500 mt-1">On-duty tanods now</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-walking text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Tanod Modules -->
            <div class="lg:col-span-2">
                <!-- Tanod Modules Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Module 1: My Duty & Patrol Schedule -->
                    <a href="?module=duty_schedule" class="module-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold text-gray-800">My Duty & Patrol Schedule</h3>
                                <p class="text-sm text-gray-600">View assigned shifts and routes</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-clock mr-2"></i>
                                <span>Real-time status tracker</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mt-2">
                                <i class="fas fa-map-marked-alt mr-2"></i>
                                <span>Assigned patrol routes</span>
                            </div>
                        </div>
                        <div class="mt-6">
                            <span class="inline-flex items-center text-blue-600 font-medium">
                                Open Module <i class="fas fa-arrow-right ml-2"></i>
                            </span>
                        </div>
                    </a>
                    
                    <!-- Module 2: Incident Logging -->
                    <a href="?module=incident_logging" class="module-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold text-gray-800">Incident Logging & Submission</h3>
                                <p class="text-sm text-gray-600">Quick field form for patrol incidents</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                <span>GPS location recording</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mt-2">
                                <i class="fas fa-file-upload mr-2"></i>
                                <span>Field evidence upload</span>
                            </div>
                        </div>
                        <div class="mt-6">
                            <span class="inline-flex items-center text-green-600 font-medium">
                                Log Incident <i class="fas fa-arrow-right ml-2"></i>
                            </span>
                        </div>
                    </a>
                    
                    <!-- Module 3: Report Vetting Queue -->
                    <a href="?module=report_vetting" class="module-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-check text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold text-gray-800">Witness & Report Vetting Queue</h3>
                                <p class="text-sm text-gray-600">Review incoming citizen reports</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-search-location mr-2"></i>
                                <span>Location verification</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mt-2">
                                <i class="fas fa-file-signature mr-2"></i>
                                <span>Approved/Needs Info</span>
                            </div>
                        </div>
                        <div class="mt-6">
                            <span class="inline-flex items-center text-yellow-600 font-medium">
                                Start Vetting <i class="fas fa-arrow-right ml-2"></i>
                            </span>
                        </div>
                    </a>
                    
                    <!-- Module 4: Evidence Handover Log -->
                    <a href="?module=evidence_handover" class="module-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-boxes text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold text-gray-800">Evidence Handover Log</h3>
                                <p class="text-sm text-gray-600">Transfer physical evidence formally</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-link mr-2"></i>
                                <span>Chain of Custody</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500 mt-2">
                                <i class="fas fa-user-check mr-2"></i>
                                <span>Recipient acknowledgement</span>
                            </div>
                        </div>
                        <div class="mt-6">
                            <span class="inline-flex items-center text-purple-600 font-medium">
                                Log Evidence <i class="fas fa-arrow-right ml-2"></i>
                            </span>
                        </div>
                    </a>
                </div>
                
                <!-- Recent Activity Timeline -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Recent Activity Timeline</h2>
                        <span class="text-sm text-gray-500">Today's incidents & actions</span>
                    </div>
                    
                    <?php if (!empty($recent_incidents)): ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_incidents as $incident): ?>
                                <div class="timeline-item <?php echo $incident['status'] === 'pending' ? 'pending' : 'completed'; ?>">
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-medium text-gray-800">
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?>
                                                </h4>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                                    <?php echo htmlspecialchars($incident['location']); ?>
                                                </p>
                                                <?php if (!empty($incident['case_number'])): ?>
                                                    <p class="text-xs text-blue-600 mt-1">
                                                        <i class="fas fa-link mr-1"></i>
                                                        Case: <?php echo htmlspecialchars($incident['case_number']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-block px-3 py-1 text-xs rounded-full 
                                                    <?php echo $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo ucfirst($incident['status']); ?>
                                                </span>
                                                <p class="text-xs text-gray-500 mt-2">
                                                    <?php echo date('h:i A', strtotime($incident['reported_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-history text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">No recent incidents logged</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column: Announcements & Profile -->
            <div class="space-y-6">
                <!-- Quick Profile -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                            <?php echo strtoupper(substr($tanod_name, 0, 2)); ?>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($tanod_name); ?></h3>
                            <p class="text-sm text-gray-600">Tanod Officer</p>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                    <?php echo $duty_status === 'on_duty' ? 'status-on-duty text-white' : 'bg-gray-100 text-gray-800'; ?>">
                                    <i class="fas fa-<?php echo $duty_status === 'on_duty' ? 'shield-alt' : 'user-clock'; ?> mr-2"></i>
                                    <?php echo $duty_status === 'on_duty' ? 'ON DUTY' : 'OFF DUTY'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <a href="?module=profile" class="flex items-center p-3 hover:bg-blue-50 rounded-lg transition">
                            <i class="fas fa-user-circle text-blue-500 mr-3 text-lg"></i>
                            <span class="font-medium text-gray-700">Profile & Account Settings</span>
                            <i class="fas fa-chevron-right ml-auto text-gray-400"></i>
                        </a>
                        <a href="?module=duty_schedule" class="flex items-center p-3 hover:bg-blue-50 rounded-lg transition">
                            <i class="fas fa-clock text-green-500 mr-3 text-lg"></i>
                            <span class="font-medium text-gray-700">Clock In/Out</span>
                            <i class="fas fa-chevron-right ml-auto text-gray-400"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Barangay Announcements -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Barangay Announcements</h2>
                        <span class="text-sm text-gray-500">Latest</span>
                    </div>
                    
                    <?php if (!empty($announcements)): ?>
                        <div class="space-y-4">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="p-4 rounded-lg border <?php echo 'announcement-' . $announcement['priority']; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            <?php echo $announcement['priority'] === 'high' ? 'bg-red-100 text-red-800' : 
                                                   ($announcement['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                            <?php echo ucfirst($announcement['priority']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 line-clamp-2">
                                        <?php echo htmlspecialchars($announcement['content']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-3">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-bullhorn text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">No announcements</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- System Status -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl shadow-lg p-6 text-white">
                    <h3 class="text-lg font-bold mb-4">System Status</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-wifi mr-3 text-green-300"></i>
                            <div>
                                <p class="text-sm font-medium">Connection</p>
                                <p class="text-xs text-blue-100">Online & Secure</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-database mr-3 text-green-300"></i>
                            <div>
                                <p class="text-sm font-medium">Data Sync</p>
                                <p class="text-xs text-blue-100">Real-time updates</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt mr-3 text-green-300"></i>
                            <div>
                                <p class="text-sm font-medium">Security</p>
                                <p class="text-xs text-blue-100">Encryption Active</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Barangay LEIR System v2.0 &copy; <?php echo date('Y'); ?></p>
            <p class="mt-1">Tanod Dashboard: Field Operations & Evidence Collection</p>
        </div>
    </div>
    
    <script>
    // Auto-refresh for real-time updates
    let refreshTimer;
    
    function startAutoRefresh() {
        refreshTimer = setTimeout(() => {
            const dutyStatus = "<?php echo $duty_status; ?>";
            if (dutyStatus === 'on_duty') {
                fetch('../ajax/check_updates.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.hasUpdates) {
                            // Show notification
                            showToast('New updates available', 'info');
                        }
                    });
            }
        }, 60000); // Every minute
    }
    
    function showToast(message, type = 'info') {
        // Toast implementation
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg text-white z-50 transform translate-x-full transition-transform ${
            type === 'info' ? 'bg-blue-500' : 
            type === 'success' ? 'bg-green-500' : 
            'bg-yellow-500'
        }`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'info' ? 'info-circle' : type === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-3"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }
        });
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        clearTimeout(refreshTimer);
    });
    </script>
</body>
</html>