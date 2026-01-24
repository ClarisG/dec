<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a tanod
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    header('Location: ../../index.php');
    exit();
}

$tanod_id = $_SESSION['user_id'];
$tanod_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get database connection
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize variables
$success_message = '';
$error_message = '';
$pending_reports = [];
$assigned_reports = [];
$completed_vettings = [];
$report_details = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_report'])) {
        $report_id = intval($_POST['report_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Assign report to tanod
            $stmt = $pdo->prepare("
                UPDATE reports 
                SET assigned_tanod = ?, 
                    status = 'assigned_for_verification',
                    assigned_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_field_verification'
            ");
            
            if ($stmt->execute([$tanod_id, $report_id])) {
                // Create notification for secretary
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, priority, is_read, created_at) 
                    VALUES (?, ?, ?, 'report_assigned', ?, 'medium', 0, NOW())
                ");
                
                // Get secretaries
                $sec_stmt = $pdo->prepare("
                    SELECT id FROM users WHERE role = 'secretary' AND status = 'active'
                ");
                $sec_stmt->execute();
                $secretaries = $sec_stmt->fetchAll();
                
                foreach ($secretaries as $secretary) {
                    $notif_stmt->execute([
                        $secretary['id'],
                        '📋 Report Assigned for Verification',
                        "Tanod $tanod_name has assigned report #$report_id for field verification",
                        $report_id
                    ]);
                }
                
                // Log activity
                addActivityLog($pdo, $tanod_id, 'report_assigned', 
                    "Assigned report #$report_id for field verification");
                
                $pdo->commit();
                $success_message = "✅ Report assigned successfully for field verification.";
            } else {
                $error_message = "Report not found or already assigned.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Report Assignment Error: " . $e->getMessage());
            $error_message = "❌ Error assigning report: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['submit_vetting'])) {
        $report_id = intval($_POST['report_id']);
        $location_verified = $_POST['location_verified'] ?? 'no';
        $facts_verified = $_POST['facts_verified'] ?? 'unconfirmed';
        $verification_notes = trim($_POST['verification_notes'] ?? '');
        $recommendation = $_POST['recommendation'] ?? 'needs_more_info';
        $verification_date = date('Y-m-d H:i:s');
        
        // Validate inputs
        if (empty($verification_notes) || strlen($verification_notes) < 10) {
            $error_message = "Verification notes must be at least 10 characters.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if vetting already exists
                $check_stmt = $pdo->prepare("SELECT id FROM report_vetting WHERE report_id = ?");
                $check_stmt->execute([$report_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing vetting
                    $stmt = $pdo->prepare("
                        UPDATE report_vetting 
                        SET location_verified = ?, 
                            facts_verified = ?, 
                            verification_notes = ?, 
                            recommendation = ?,
                            verification_date = ?,
                            updated_at = NOW()
                        WHERE report_id = ?
                    ");
                    $stmt->execute([
                        $location_verified, 
                        $facts_verified, 
                        $verification_notes, 
                        $recommendation,
                        $verification_date,
                        $report_id
                    ]);
                } else {
                    // Insert new vetting
                    $stmt = $pdo->prepare("
                        INSERT INTO report_vetting 
                        (report_id, tanod_id, location_verified, facts_verified, 
                         verification_notes, recommendation, verification_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $report_id, 
                        $tanod_id, 
                        $location_verified, 
                        $facts_verified, 
                        $verification_notes, 
                        $recommendation,
                        $verification_date
                    ]);
                }
                
                // Update report status based on recommendation
                $report_status = 'verified';
                if ($recommendation === 'approved') {
                    $report_status = 'verified_approved';
                } elseif ($recommendation === 'rejected') {
                    $report_status = 'verified_rejected';
                } elseif ($recommendation === 'needs_more_info') {
                    $report_status = 'needs_more_info';
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE reports 
                    SET status = ?, 
                        verification_notes = ?, 
                        verification_date = ?, 
                        verified_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $report_status, 
                    $verification_notes, 
                    $verification_date, 
                    $tanod_id, 
                    $report_id
                ]);
                
                // Create notification for secretary
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, priority, is_read, created_at) 
                    VALUES (?, ?, ?, 'report_vetted', ?, 'high', 0, NOW())
                ");
                
                // Get secretaries and admins
                $admin_stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE role IN ('secretary', 'admin') AND status = 'active'
                ");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll();
                
                $rec_text = ucwords(str_replace('_', ' ', $recommendation));
                
                foreach ($admins as $admin) {
                    $notif_stmt->execute([
                        $admin['id'],
                        '📋 Report Verification Complete',
                        "Tanod $tanod_name has completed verification of report #$report_id (Recommendation: $rec_text)",
                        $report_id
                    ]);
                }
                
                // Notify citizen if report was submitted by citizen
                $citizen_stmt = $pdo->prepare("
                    SELECT user_id FROM reports WHERE id = ?
                ");
                $citizen_stmt->execute([$report_id]);
                $citizen = $citizen_stmt->fetch();
                
                if ($citizen) {
                    $citizen_notif = $pdo->prepare("
                        INSERT INTO notifications 
                        (user_id, title, message, related_type, related_id, priority, is_read, created_at) 
                        VALUES (?, ?, ?, 'report_status', ?, 'medium', 0, NOW())
                    ");
                    
                    $status_message = "Your report has been verified. Status: " . ucwords(str_replace('_', ' ', $report_status));
                    $citizen_notif->execute([
                        $citizen['user_id'],
                        '📋 Report Update',
                        $status_message,
                        $report_id
                    ]);
                }
                
                // Log activity
                addActivityLog($pdo, $tanod_id, 'report_vetted', 
                    "Submitted vetting for report #$report_id with recommendation: $recommendation");
                
                $pdo->commit();
                $success_message = "✅ Vetting report submitted successfully.";
                
                // Clear form data
                unset($_POST);
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Vetting Submission Error: " . $e->getMessage());
                $error_message = "❌ Error submitting vetting report: " . $e->getMessage();
            }
        }
    }
}

// Get pending reports for verification
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name,
               u.contact_number,
               rt.name as report_type
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN report_types rt ON r.report_type_id = rt.id
        WHERE r.status = 'pending_field_verification'
        AND (r.assigned_tanod IS NULL OR r.assigned_tanod = 0)
        AND r.needs_field_verification = 1
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $pending_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pending reports: " . $e->getMessage());
}

// Get assigned reports to this tanod
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name,
               u.contact_number,
               rt.name as report_type,
               v.recommendation as vetting_recommendation,
               v.verification_date
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN report_types rt ON r.report_type_id = rt.id
        LEFT JOIN report_vetting v ON r.id = v.report_id
        WHERE r.assigned_tanod = ?
        AND r.status IN ('assigned_for_verification', 'pending_field_verification')
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tanod_id]);
    $assigned_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching assigned reports: " . $e->getMessage());
}

// Get completed vettings by this tanod
try {
    $stmt = $pdo->prepare("
        SELECT v.*, 
               r.title as report_title,
               r.location,
               r.case_number,
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name
        FROM report_vetting v
        JOIN reports r ON v.report_id = r.id
        JOIN users u ON r.user_id = u.id
        WHERE v.tanod_id = ?
        ORDER BY v.verification_date DESC
        LIMIT 10
    ");
    $stmt->execute([$tanod_id]);
    $completed_vettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching completed vettings: " . $e->getMessage());
}

// Get specific report details if requested
if (isset($_GET['view_report'])) {
    $report_id = intval($_GET['view_report']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as reporter_name, 
                   u.contact_number, u.email,
                   rt.name as report_type,
                   v.*
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN report_types rt ON r.report_type_id = rt.id
            LEFT JOIN report_vetting v ON r.id = v.report_id
            WHERE r.id = ?
        ");
        $stmt->execute([$report_id]);
        
        if ($stmt->rowCount() > 0) {
            $report_details = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching report details: " . $e->getMessage());
    }
}

// Get statistics
$stats = [
    'pending' => count($pending_reports),
    'assigned' => count($assigned_reports),
    'completed' => count($completed_vettings),
    'total' => count($pending_reports) + count($assigned_reports) + count($completed_vettings)
];

// Activity log function
function addActivityLog($pdo, $user_id, $action, $description) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Witness & Report Vetting Queue - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .report-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
            color: #92400e; 
            border-left-color: #f59e0b; 
        }
        .status-assigned { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); 
            color: #1e40af; 
            border-left-color: #3b82f6; 
        }
        .status-verified { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
            color: #065f46; 
            border-left-color: #10b981; 
        }
        .status-rejected { 
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%); 
            color: #991b1b; 
            border-left-color: #ef4444; 
        }
        
        .priority-high { 
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%); 
            color: #991b1b; 
        }
        .priority-medium { 
            background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%); 
            color: #92400e; 
        }
        .priority-low { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
            color: #065f46; 
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, #10b981, #ef4444);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3b82f6;
            border: 2px solid white;
            box-shadow: 0 0 0 3px #dbeafe;
        }
        
        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 3px #d1fae5;
        }
        
        .timeline-item.pending::before {
            background: #f59e0b;
            box-shadow: 0 0 0 3px #fef3c7;
        }
        
        .timeline-item.rejected::before {
            background: #ef4444;
            box-shadow: 0 0 0 3px #fecaca;
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .mobile-stack {
                flex-direction: column;
            }
            
            .mobile-full {
                width: 100% !important;
            }
        }
        
        /* Form validation styles */
        .form-error {
            border-color: #ef4444 !important;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-green-50 min-h-screen p-4">
    <div class="max-w-7xl mx-auto">
            <!-- Stats Bar -->
            <div class="bg-white p-4 border-b">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700"><?php echo $stats['pending']; ?></div>
                        <div class="text-sm text-blue-600">Pending Reports</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-700"><?php echo $stats['assigned']; ?></div>
                        <div class="text-sm text-yellow-600">Assigned to Me</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                        <div class="text-2xl font-bold text-green-700"><?php echo $stats['completed']; ?></div>
                        <div class="text-sm text-green-600">Completed</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                        <div class="text-2xl font-bold text-purple-700"><?php echo $stats['total']; ?></div>
                        <div class="text-sm text-purple-600">Total in Queue</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success_message): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 rounded-lg animate-pulse slide-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                <div class="flex-1">
                    <p class="text-green-700 font-bold"><?php echo $success_message; ?></p>
                    <p class="text-green-600 text-sm mt-1"><?php echo date('F j, Y - h:i A'); ?></p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 rounded-lg slide-in">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                <div class="flex-1">
                    <p class="text-red-700 font-bold"><?php echo $error_message; ?></p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Critical Data Handled Info -->
        <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-emerald-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-emerald-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-bold text-emerald-800">Critical Data Handled</p>
                    <p class="text-xs text-emerald-700">Citizen report details, Tanod verification notes, Recommendation status, GPS location verification</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Pending Reports -->
            <div class="lg:col-span-2">
                <!-- Pending Reports Card -->
                <div class="glass-card rounded-2xl shadow-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Pending Reports for Verification</h2>
                            <p class="text-gray-600 mt-1">Reports requiring field verification and assignment</p>
                        </div>
                        <span class="px-3 py-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-bold rounded-full">
                            <?php echo $stats['pending']; ?> pending
                        </span>
                    </div>
                    
                    <?php if (!empty($pending_reports)): ?>
                        <div class="space-y-4">
                            <?php foreach ($pending_reports as $report): ?>
                                <div class="report-card bg-white rounded-xl border border-gray-200 overflow-hidden status-pending">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <span class="font-bold text-gray-800 text-lg">
                                                        #<?php echo str_pad($report['id'], 5, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                    <span class="status-badge status-pending ml-3">
                                                        Pending Assignment
                                                    </span>
                                                    <?php if ($report['priority'] === 'high'): ?>
                                                        <span class="ml-3 px-3 py-1 priority-high text-xs rounded-full">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i> High Priority
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h3 class="font-bold text-gray-800 text-lg mb-2">
                                                    <?php echo htmlspecialchars($report['title']); ?>
                                                </h3>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                                    <div>
                                                        <p class="text-sm text-gray-600">
                                                            <i class="fas fa-user mr-2"></i>
                                                            <?php echo htmlspecialchars($report['reporter_name']); ?>
                                                        </p>
                                                        <?php if (!empty($report['contact_number'])): ?>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                <i class="fas fa-phone mr-2"></i>
                                                                <?php echo htmlspecialchars($report['contact_number']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if (!empty($report['location'])): ?>
                                                            <p class="text-sm text-gray-600">
                                                                <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                                                                <?php echo htmlspecialchars($report['location']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <p class="text-sm text-gray-600 mt-1">
                                                            <i class="far fa-calendar mr-2"></i>
                                                            <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <button type="submit" name="assign_report" 
                                                            class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition font-medium">
                                                        <i class="fas fa-user-check mr-2"></i>
                                                        Assign to Me
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                                            <div>
                                                <button onclick="viewReportDetails(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars(addslashes($report['title'])); ?>')"
                                                        class="px-4 py-2 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-lg hover:from-gray-200 hover:to-gray-300 transition text-sm">
                                                    <i class="fas fa-eye mr-1"></i> View Details
                                                </button>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('h:i A', strtotime($report['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="text-gray-300 text-5xl mb-4">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">No Pending Reports</h3>
                            <p class="text-gray-500">All reports have been assigned or verified</p>
                            <p class="text-sm text-gray-400 mt-2">Check back later for new reports</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- My Assigned Reports Card -->
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">My Assigned Reports</h2>
                            <p class="text-gray-600 mt-1">Reports assigned to you for field verification</p>
                        </div>
                        <span class="px-3 py-1 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white text-sm font-bold rounded-full">
                            <?php echo $stats['assigned']; ?> assigned
                        </span>
                    </div>
                    
                    <?php if (!empty($assigned_reports)): ?>
                        <div class="space-y-4">
                            <?php foreach ($assigned_reports as $report): ?>
                                <?php
                                $status_class = 'status-assigned';
                                $status_text = 'Assigned';
                                
                                if ($report['vetting_recommendation']) {
                                    if ($report['vetting_recommendation'] === 'approved') {
                                        $status_class = 'status-verified';
                                        $status_text = 'Verified';
                                    } elseif ($report['vetting_recommendation'] === 'rejected') {
                                        $status_class = 'status-rejected';
                                        $status_text = 'Rejected';
                                    }
                                }
                                ?>
                                <div class="report-card bg-white rounded-xl border border-gray-200 overflow-hidden <?php echo $status_class; ?>">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <span class="font-bold text-gray-800 text-lg">
                                                        #<?php echo str_pad($report['id'], 5, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                    <span class="status-badge <?php echo $status_class; ?> ml-3">
                                                        <?php echo ucwords(str_replace('_', ' ', $status_text)); ?>
                                                    </span>
                                                    <?php if (!empty($report['report_type'])): ?>
                                                        <span class="ml-3 px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                                                            <?php echo htmlspecialchars($report['report_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h3 class="font-bold text-gray-800 text-lg mb-2">
                                                    <?php echo htmlspecialchars($report['title']); ?>
                                                </h3>
                                                
                                                <div class="mb-3">
                                                    <p class="text-sm text-gray-600 line-clamp-2">
                                                        <?php echo htmlspecialchars(substr($report['description'], 0, 150)); ?>
                                                        <?php if (strlen($report['description']) > 150): ?>...<?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <a href="?view_report=<?php echo $report['id']; ?>" 
                                                   class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition font-medium inline-block">
                                                    <i class="fas fa-edit mr-2"></i>
                                                    <?php echo $report['vetting_recommendation'] ? 'Review' : 'Verify'; ?>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-100">
                                            <div>
                                                <p class="text-xs text-gray-500">Reporter</p>
                                                <p class="text-sm font-medium"><?php echo htmlspecialchars($report['reporter_name']); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Location</p>
                                                <p class="text-sm font-medium">
                                                    <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                                    <?php echo htmlspecialchars($report['location']); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Assigned</p>
                                                <p class="text-sm font-medium">
                                                    <?php echo date('M d', strtotime($report['assigned_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="text-gray-300 text-5xl mb-4">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">No Assigned Reports</h3>
                            <p class="text-gray-500">Assign pending reports to get started</p>
                            <p class="text-sm text-gray-400 mt-2">Use the "Assign to Me" button on pending reports</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column: Verification Form & Stats -->
            <div class="lg:col-span-1">
                <?php if ($report_details): ?>
                    <!-- Verification Form Card -->
                    <div class="glass-card rounded-2xl shadow-lg p-6 sticky top-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Field Verification</h2>
                            <a href="?" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        
                        <!-- Report Summary -->
                        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                            <h3 class="font-bold text-blue-800 mb-2">Report Summary</h3>
                            <p class="text-sm text-gray-700">#<?php echo str_pad($report_details['id'], 5, '0', STR_PAD_LEFT); ?>: 
                                <?php echo htmlspecialchars(substr($report_details['title'], 0, 50)); ?>
                            </p>
                            <p class="text-xs text-gray-600 mt-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo htmlspecialchars($report_details['location']); ?>
                            </p>
                        </div>
                        
                        <!-- Verification Form -->
                        <form method="POST" id="vettingForm" class="space-y-4">
                            <input type="hidden" name="report_id" value="<?php echo $report_details['id']; ?>">
                            
                            <!-- Location Verification -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-map-pin mr-1"></i>Location Verified?
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="location_verified" value="yes" 
                                               class="form-radio h-4 w-4 text-green-600" 
                                               <?php echo ($report_details['location_verified'] ?? '') == 'yes' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-check-circle text-green-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Yes - Location confirmed</span>
                                            <p class="text-xs text-gray-500">Accurate as reported</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="location_verified" value="partial" 
                                               class="form-radio h-4 w-4 text-yellow-600"
                                               <?php echo ($report_details['location_verified'] ?? '') == 'partial' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-exclamation-circle text-yellow-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Partial - Some discrepancies</span>
                                            <p class="text-xs text-gray-500">Minor inaccuracies found</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="location_verified" value="no" 
                                               class="form-radio h-4 w-4 text-red-600"
                                               <?php echo ($report_details['location_verified'] ?? '') == 'no' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-times-circle text-red-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">No - Location incorrect</span>
                                            <p class="text-xs text-gray-500">Significant discrepancies</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Facts Verification -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-clipboard-check mr-1"></i>Facts Verified?
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="facts_verified" value="confirmed" 
                                               class="form-radio h-4 w-4 text-green-600"
                                               <?php echo ($report_details['facts_verified'] ?? '') == 'confirmed' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-check-circle text-green-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Confirmed</span>
                                            <p class="text-xs text-gray-500">All facts verified</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="facts_verified" value="partially_confirmed" 
                                               class="form-radio h-4 w-4 text-yellow-600"
                                               <?php echo ($report_details['facts_verified'] ?? '') == 'partially_confirmed' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-exclamation-circle text-yellow-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Partially Confirmed</span>
                                            <p class="text-xs text-gray-500">Some facts verified</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer border border-gray-200">
                                        <input type="radio" name="facts_verified" value="unconfirmed" 
                                               class="form-radio h-4 w-4 text-red-600"
                                               <?php echo ($report_details['facts_verified'] ?? '') == 'unconfirmed' ? 'checked' : ''; ?>
                                               required>
                                        <i class="fas fa-times-circle text-red-500 ml-3 mr-3"></i>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Unconfirmed</span>
                                            <p class="text-xs text-gray-500">Cannot verify facts</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Verification Notes -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-notes-medical mr-1"></i>Verification Notes *
                                </label>
                                <textarea name="verification_notes" required rows="4" id="verificationNotes"
                                          placeholder="Enter detailed field verification notes...
• What you found at the location
• Witness statements collected
• Evidence observed
• Any discrepancies found
• Recommendations for further action"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"><?php echo htmlspecialchars($report_details['verification_notes'] ?? ''); ?></textarea>
                                <div class="flex justify-between items-center mt-1">
                                    <div id="notesError" class="error-message hidden">Minimum 10 characters required</div>
                                    <div id="notesCharCount" class="text-xs text-gray-500">0 characters</div>
                                </div>
                            </div>
                            
                            <!-- Recommendation -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-file-signature mr-1"></i>Recommendation *
                                </label>
                                <select name="recommendation" required id="recommendation"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition">
                                    <option value="">Select recommendation</option>
                                    <option value="approved" <?php echo ($report_details['recommendation'] ?? '') == 'approved' ? 'selected' : ''; ?>>
                                        ✓ Approve Report (Verified and Accurate)
                                    </option>
                                    <option value="needs_more_info" <?php echo ($report_details['recommendation'] ?? '') == 'needs_more_info' ? 'selected' : ''; ?>>
                                        ⚠ Needs More Information
                                    </option>
                                    <option value="rejected" <?php echo ($report_details['recommendation'] ?? '') == 'rejected' ? 'selected' : ''; ?>>
                                        ✗ Reject Report (Inaccurate or False)
                                    </option>
                                </select>
                                <div id="recommendationError" class="error-message hidden">Please select a recommendation</div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-2">
                                <button type="submit" name="submit_vetting" id="submitVetting"
                                        class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white font-bold py-3 px-4 rounded-lg hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Vetting Report
                                </button>
                                <p class="text-xs text-gray-500 mt-2 text-center">
                                    This will update the report status and notify relevant officials
                                </p>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Stats and Quick Actions Card -->
                    <div class="glass-card rounded-2xl shadow-lg p-6 sticky top-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Vetting Dashboard</h2>
                        
                        <!-- Quick Stats -->
                        <div class="space-y-4 mb-6">
                            <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-700">Pending Assignment</p>
                                        <p class="text-2xl font-bold text-blue-800"><?php echo $stats['pending']; ?></p>
                                    </div>
                                    <div class="h-12 w-12 bg-blue-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-yellow-700">Assigned to Me</p>
                                        <p class="text-2xl font-bold text-yellow-800"><?php echo $stats['assigned']; ?></p>
                                    </div>
                                    <div class="h-12 w-12 bg-yellow-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-check text-yellow-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-green-700">Completed Vettings</p>
                                        <p class="text-2xl font-bold text-green-800"><?php echo $stats['completed']; ?></p>
                                    </div>
                                    <div class="h-12 w-12 bg-green-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-700 mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <a href="../tanod_dashboard.php" 
                                   class="flex items-center p-3 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-200 rounded-lg transition">
                                    <i class="fas fa-tachometer-alt mr-3 text-gray-600"></i>
                                    <span class="font-medium">Return to Dashboard</span>
                                </a>
                                <button onclick="window.location.reload()" 
                                        class="flex items-center p-3 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-200 rounded-lg transition w-full text-left">
                                    <i class="fas fa-sync-alt mr-3 text-gray-600"></i>
                                    <span class="font-medium">Refresh List</span>
                                </button>
                                <a href="incident_logging.php" 
                                   class="flex items-center p-3 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-200 rounded-lg transition">
                                    <i class="fas fa-exclamation-triangle mr-3 text-gray-600"></i>
                                    <span class="font-medium">Log Field Incident</span>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Recent Completed Vettings -->
                        <?php if (!empty($completed_vettings)): ?>
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-4">Recently Completed</h3>
                            <div class="space-y-3">
                                <?php foreach ($completed_vettings as $vetting): ?>
                                    <div class="p-3 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-medium text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars(substr($vetting['report_title'], 0, 40)); ?>
                                                    <?php if (strlen($vetting['report_title']) > 40): ?>...<?php endif; ?>
                                                </h4>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    <?php echo date('M d', strtotime($vetting['verification_date'])); ?>
                                                </p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full font-bold 
                                                <?php echo $vetting['recommendation'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                                       ($vetting['recommendation'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                <?php echo ucfirst($vetting['recommendation']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Barangay LEIR Report Vetting System v2.0 &copy; <?php echo date('Y'); ?></p>
            <p class="mt-1">Field verification ensures accurate and reliable incident reporting.</p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Character counter for verification notes
    document.addEventListener('DOMContentLoaded', function() {
        const notesTextarea = document.getElementById('verificationNotes');
        if (notesTextarea) {
            notesTextarea.addEventListener('input', function() {
                const charCount = this.value.length;
                document.getElementById('notesCharCount').textContent = charCount + ' characters';
                
                if (charCount < 10 && charCount > 0) {
                    document.getElementById('notesError').classList.remove('hidden');
                    this.classList.add('form-error');
                } else {
                    document.getElementById('notesError').classList.add('hidden');
                    this.classList.remove('form-error');
                }
            });
            
            // Initial count
            const initialCount = notesTextarea.value.length;
            document.getElementById('notesCharCount').textContent = initialCount + ' characters';
        }
        
        // Form validation
        const vettingForm = document.getElementById('vettingForm');
        if (vettingForm) {
            vettingForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Check verification notes
                const notes = document.getElementById('verificationNotes');
                if (!notes || notes.value.length < 10) {
                    document.getElementById('notesError').classList.remove('hidden');
                    notes.classList.add('form-error');
                    isValid = false;
                }
                
                // Check recommendation
                const recommendation = document.getElementById('recommendation');
                if (!recommendation || !recommendation.value) {
                    document.getElementById('recommendationError').classList.remove('hidden');
                    recommendation.classList.add('form-error');
                    isValid = false;
                }
                
                // Check radio buttons
                const locationVerified = document.querySelector('input[name="location_verified"]:checked');
                const factsVerified = document.querySelector('input[name="facts_verified"]:checked');
                
                if (!locationVerified || !factsVerified) {
                    showToast('Please complete all verification fields', 'error');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    showToast('Please fix the errors in the form', 'error');
                } else {
                    // Show loading state
                    const submitBtn = document.getElementById('submitVetting');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        // Auto-expand textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
            
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // Show welcome message
        if (!localStorage.getItem('reportVettingVisited')) {
            showToast('Welcome to Report Vetting Queue! Verify citizen reports in the field.', 'info');
            localStorage.setItem('reportVettingVisited', 'true');
        }
    });
    
    // View report details in modal
    function viewReportDetails(reportId, reportTitle) {
        // In a real implementation, this would fetch report details via AJAX
        showToast('Loading report details...', 'info');
        setTimeout(() => {
            window.location.href = '?view_report=' + reportId;
        }, 500);
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        const toastId = 'toast-' + Date.now();
        
        let bgColor, textColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'bg-gradient-to-r from-green-500 to-green-600';
                textColor = 'text-white';
                icon = 'fa-check-circle';
                break;
            case 'error':
                bgColor = 'bg-gradient-to-r from-red-500 to-red-600';
                textColor = 'text-white';
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                bgColor = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
                textColor = 'text-white';
                icon = 'fa-exclamation-triangle';
                break;
            default:
                bgColor = 'bg-gradient-to-r from-blue-500 to-blue-600';
                textColor = 'text-white';
                icon = 'fa-info-circle';
        }
        
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${bgColor} ${textColor} px-6 py-4 rounded-lg shadow-xl z-50 transform translate-x-full transition-transform duration-300`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-3"></i>
                <span class="font-medium">${message}</span>
                <button onclick="document.getElementById('${toastId}').remove()" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (document.getElementById(toastId)) {
                    document.getElementById(toastId).remove();
                }
            }, 300);
        }, 5000);
    }
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Refresh on Ctrl+R
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            window.location.reload();
        }
        
        // Escape to clear view
        if (e.key === 'Escape' && window.location.search.includes('view_report')) {
            window.location.href = window.location.pathname;
        }
    });
    </script>
</body>
</html>