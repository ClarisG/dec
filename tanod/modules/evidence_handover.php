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
$user_role = $_SESSION['role'];

// Get database connection
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize variables
$success_message = '';
$error_message = '';
$handovers = [];
$recipients = [];

// Handle form submission for new evidence handover (Tanod only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_handover'])) {
    if ($user_role !== 'tanod') {
        $error_message = "Only Tanods can submit evidence handovers.";
    } else {
        $item_description = trim($_POST['item_description']);
        $item_type = trim($_POST['item_type']);
        $handover_to = intval($_POST['handover_to']);
        $handover_date = date('Y-m-d H:i:s');
        $chain_of_custody = trim($_POST['chain_of_custody']);
        $evidence_location = trim($_POST['evidence_location'] ?? '');
        $witnesses = trim($_POST['witnesses'] ?? '');
        $case_id = intval($_POST['case_id'] ?? 0);
        
        // Validate required fields
        if (empty($item_description) || empty($handover_to) || empty($item_type)) {
            $error_message = "Please fill in all required fields marked with *.";
        } elseif (strlen($item_description) < 20) {
            $error_message = "Evidence description must be at least 20 characters long.";
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Insert evidence handover
                $stmt = $pdo->prepare("
                    INSERT INTO evidence_handovers 
                    (tanod_id, item_description, item_type, handover_to, handover_date, 
                     chain_of_custody, evidence_location, witnesses, case_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_acknowledgement', NOW())
                ");
                $stmt->execute([
                    $tanod_id, 
                    $item_description, 
                    $item_type, 
                    $handover_to, 
                    $handover_date, 
                    $chain_of_custody,
                    $evidence_location,
                    $witnesses,
                    $case_id
                ]);
                
                $handover_id = $pdo->lastInsertId();
                
                // Create notification for recipient
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, priority, is_read, created_at) 
                    VALUES (?, ?, ?, 'evidence_handover', ?, 'high', 0, NOW())
                ");
                
                // Get recipient name for notification
                $recipient_stmt = $pdo->prepare("
                    SELECT CONCAT(first_name, ' ', last_name) as name, role 
                    FROM users WHERE id = ?
                ");
                $recipient_stmt->execute([$handover_to]);
                $recipient = $recipient_stmt->fetch();
                $recipient_name = $recipient ? $recipient['name'] : 'Recipient';
                $recipient_role = $recipient ? $recipient['role'] : 'unknown';
                
                $evidence_code = 'EVID-' . str_pad($handover_id, 5, '0', STR_PAD_LEFT);
                
                $notif_stmt->execute([
                    $handover_to,
                    '📦 New Evidence Handover',
                    "Tanod $tanod_name has submitted evidence for your acknowledgement ($evidence_code)",
                    $handover_id
                ]);
                
                // Also notify admin/super admin
                $admin_stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE role IN ('admin', 'super_admin') AND status = 'active'
                ");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll();
                
                foreach ($admins as $admin) {
                    $notif_stmt->execute([
                        $admin['id'],
                        '📦 Evidence Handover Submitted',
                        "Tanod $tanod_name submitted evidence to $recipient_name ($evidence_code)",
                        $handover_id
                    ]);
                }
                
                // Log activity
                addActivityLog($pdo, $tanod_id, 'evidence_handover', 
                    "Submitted evidence handover #$handover_id: $item_type");
                
                // If case_id is provided, update case evidence count
                if ($case_id > 0) {
                    $case_stmt = $pdo->prepare("
                        UPDATE cases SET evidence_count = evidence_count + 1 WHERE id = ?
                    ");
                    $case_stmt->execute([$case_id]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "✅ Evidence handover logged successfully (ID: $evidence_code). Waiting for recipient acknowledgement.";
                
                // Clear form data
                unset($_POST);
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Evidence Handover Error: " . $e->getMessage());
                $error_message = "❌ Error logging handover: " . $e->getMessage();
            }
        }
    }
}

// Handle acknowledgement (Secretary/Admin only) - if Tanod tries to access this, it shouldn't execute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_handover'])) {
    if (!in_array($user_role, ['secretary', 'admin'])) {
        $error_message = "Only Secretaries and Admins can acknowledge receipt.";
    } else {
        $handover_id = intval($_POST['handover_id']);
        $acknowledgement_notes = trim($_POST['acknowledgement_notes'] ?? '');
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update evidence handover
            $stmt = $pdo->prepare("
                UPDATE evidence_handovers 
                SET recipient_acknowledged = 1, 
                    acknowledged_by = ?,
                    acknowledged_at = NOW(),
                    acknowledgement_notes = ?,
                    status = 'acknowledged',
                    updated_at = NOW()
                WHERE id = ? AND recipient_acknowledged = 0
            ");
            $stmt->execute([$_SESSION['user_id'], $acknowledgement_notes, $handover_id]);
            
            if ($stmt->rowCount() > 0) {
                // Get handover details for notification
                $details_stmt = $pdo->prepare("
                    SELECT tanod_id, item_description, item_type FROM evidence_handovers WHERE id = ?
                ");
                $details_stmt->execute([$handover_id]);
                $handover_details = $details_stmt->fetch();
                
                if ($handover_details) {
                    // Create notification for tanod
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO notifications 
                        (user_id, title, message, related_type, related_id, is_read, created_at) 
                        VALUES (?, ?, ?, 'evidence_handover', ?, 0, NOW())
                    ");
                    $evidence_code = 'EVID-' . str_pad($handover_id, 5, '0', STR_PAD_LEFT);
                    $notif_stmt->execute([
                        $handover_details['tanod_id'],
                        '✅ Evidence Acknowledged',
                        "Your evidence handover ($evidence_code) has been acknowledged by $tanod_name.",
                        $handover_id
                    ]);
                }
                
                // Log activity
                addActivityLog($pdo, $_SESSION['user_id'], 'evidence_acknowledged', 
                    "Acknowledged receipt of evidence handover ID: $handover_id");
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "✅ Evidence receipt acknowledged successfully.";
            } else {
                $error_message = "Handover not found, already acknowledged, or you are not the designated recipient.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Evidence Acknowledgement Error: " . $e->getMessage());
            $error_message = "❌ Error acknowledging receipt: " . $e->getMessage();
        }
    }
}

// Fetch relevant handover records based on user role
try {
    if ($user_role === 'tanod') {
        // Tanods see their own handovers
        $stmt = $pdo->prepare("
            SELECT eh.*, 
                   u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
                   u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
                   u_recipient.role as recipient_role,
                   u_recipient.barangay_position as recipient_position,
                   u_acknowledged.first_name as acknowledged_first, u_acknowledged.last_name as acknowledged_last,
                   c.case_number, c.title as case_title
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            LEFT JOIN users u_acknowledged ON eh.acknowledged_by = u_acknowledged.id
            LEFT JOIN cases c ON eh.case_id = c.id
            WHERE eh.tanod_id = ?
            ORDER BY eh.handover_date DESC
            LIMIT 50
        ");
        $stmt->execute([$tanod_id]);
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch secretaries and admins for dropdown (Tanod only)
        $recipient_stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name, role, barangay_position 
            FROM users 
            WHERE role IN ('secretary', 'admin', 'captain') AND status = 'active'
            ORDER BY 
                CASE role 
                    WHEN 'captain' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'secretary' THEN 3
                    ELSE 4
                END,
                last_name, first_name
        ");
        $recipient_stmt->execute();
        $recipients = $recipient_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch active cases for dropdown
        $case_stmt = $pdo->prepare("
            SELECT id, case_number, title 
            FROM cases 
            WHERE status IN ('open', 'investigating', 'assigned') 
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $case_stmt->execute();
        $cases = $case_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching handover records: " . $e->getMessage());
    $error_message = "❌ Error fetching handover records: " . $e->getMessage();
}

// Get statistics
$stats = [
    'total' => count($handovers),
    'pending' => count(array_filter($handovers, function($h) { 
        return $h['status'] === 'pending_acknowledgement'; 
    })),
    'acknowledged' => count(array_filter($handovers, function($h) { 
        return $h['status'] === 'acknowledged' && !$h['released']; 
    })),
    'released' => count(array_filter($handovers, function($h) { 
        return $h['released']; 
    }))
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
    <title>Evidence Handover Log - Barangay LEIR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .evidence-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .evidence-card:hover {
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
        .status-acknowledged { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
            color: #065f46; 
            border-left-color: #10b981; 
        }
        .status-released { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); 
            color: #1e40af; 
            border-left-color: #3b82f6; 
        }
        .status-archived { 
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%); 
            color: #374151; 
            border-left-color: #6b7280; 
        }
        
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 3px;
        }
        
        .badge-weapon { background: #fecaca; color: #991b1b; }
        .badge-document { background: #bfdbfe; color: #1e40af; }
        .badge-electronic { background: #c7d2fe; color: #3730a3; }
        .badge-clothing { background: #fde68a; color: #92400e; }
        .badge-drugs { background: #fbcfe8; color: #9d174d; }
        .badge-money { background: #bbf7d0; color: #166534; }
        .badge-jewelry { background: #fef3c7; color: #92400e; }
        .badge-vehicle { background: #a5b4fc; color: #3730a3; }
        .badge-tool { background: #fcd34d; color: #92400e; }
        .badge-property { background: #fca5a5; color: #991b1b; }
        .badge-personal { background: #ddd6fe; color: #5b21b6; }
        .badge-other { background: #e5e7eb; color: #374151; }
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-tanod { background: #dbeafe; color: #1e40af; }
        .badge-secretary { background: #d1fae5; color: #065f46; }
        .badge-admin { background: #f3e8ff; color: #6b21a8; }
        .badge-captain { background: #fef3c7; color: #92400e; }
        .badge-lupon { background: #e5e7eb; color: #374151; }
        
        .chain-of-custody {
            position: relative;
            padding-left: 30px;
        }
        
        .chain-of-custody::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, #10b981, #ef4444);
        }
        
        .chain-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .chain-item::before {
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
        
        .chain-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 3px #d1fae5;
        }
        
        .chain-item.pending::before {
            background: #f59e0b;
            box-shadow: 0 0 0 3px #fef3c7;
        }
        
        .chain-item.rejected::before {
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
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .new-submission {
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
            }
            
            .evidence-card {
                break-inside: avoid;
                border: 1px solid #000 !important;
            }
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
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen p-4">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white">
                            <i class="fas fa-boxes mr-3"></i>
                            Evidence Handover Log
                        </h1>
                        <p class="text-blue-100 mt-2">Formal log for transferring physical evidence ensuring clean Chain of Custody</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-white text-sm">Logged in as:</p>
                                <p class="text-white font-bold">
                                    <?php echo htmlspecialchars($tanod_name); ?>
                                    <span class="role-badge badge-tanod ml-2">Tanod</span>
                                </p>
                                <p class="text-white text-xs mt-1">
                                    ID: TAN-<?php echo str_pad($tanod_id, 4, '0', STR_PAD_LEFT); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Bar -->
            <div class="bg-white p-4 border-b">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700"><?php echo $stats['total']; ?></div>
                        <div class="text-sm text-blue-600">Total Handovers</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-700"><?php echo $stats['pending']; ?></div>
                        <div class="text-sm text-yellow-600">Pending Acknowledgement</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                        <div class="text-2xl font-bold text-green-700"><?php echo $stats['acknowledged']; ?></div>
                        <div class="text-sm text-green-600">Acknowledged</div>
                    </div>
                    <div class="text-center p-3 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                        <div class="text-2xl font-bold text-purple-700"><?php echo $stats['released']; ?></div>
                        <div class="text-sm text-purple-600">Released/Archived</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success_message): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 rounded-lg animate-pulse">
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
        <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 rounded-lg">
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
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-indigo-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-indigo-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm font-bold text-indigo-800">Critical Data Handled</p>
                    <p class="text-xs text-indigo-700">Item description, Date/Time of handover, Recipient's acknowledgement, Chain of Custody</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: New Handover Form -->
            <div class="lg:col-span-1">
                <div class="glass-card rounded-2xl shadow-lg p-6 h-full">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                        Log New Evidence Handover
                    </h2>
                    
                    <form method="POST" action="" id="handoverForm" class="space-y-4" enctype="multipart/form-data">
                        <!-- Case Reference (Optional) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-folder text-gray-500 mr-1"></i>
                                Link to Case (Optional)
                            </label>
                            <select name="case_id" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Select Case (Optional)</option>
                                <option value="0">No specific case</option>
                                <?php if (!empty($cases)): ?>
                                    <?php foreach ($cases as $case): ?>
                                        <option value="<?php echo $case['id']; ?>">
                                            <?php echo htmlspecialchars($case['case_number']); ?> - 
                                            <?php echo htmlspecialchars(substr($case['title'], 0, 50)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <!-- Evidence Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag text-blue-500 mr-1"></i>
                                Evidence Type *
                            </label>
                            <select name="item_type" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" id="itemType">
                                <option value="">Select Type of Evidence</option>
                                <option value="weapon">🔫 Weapon (knife, gun, blunt object)</option>
                                <option value="document">📄 Document/Paper Evidence</option>
                                <option value="electronic">💻 Electronic Device/Phone</option>
                                <option value="clothing">👕 Clothing/Accessory</option>
                                <option value="vehicle">🚗 Vehicle Part</option>
                                <option value="drugs">💊 Suspected Drugs/Narcotics</option>
                                <option value="tool">🔧 Tool/Instrument</option>
                                <option value="money">💰 Money/Currency</option>
                                <option value="jewelry">💎 Jewelry/Valuables</option>
                                <option value="property">🏚️ Damaged Property</option>
                                <option value="personal">🎒 Personal Effects</option>
                                <option value="other">📦 Other Evidence</option>
                            </select>
                            <div id="itemTypeError" class="error-message hidden">Please select an evidence type</div>
                        </div>
                        
                        <!-- Evidence Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-align-left text-blue-500 mr-1"></i>
                                Evidence Description *
                            </label>
                            <textarea name="item_description" required rows="5" id="itemDescription"
                                      placeholder="Provide detailed description including:
• Brand, model, serial numbers
• Color, size, unique markings
• Condition (damaged, intact, etc.)
• Where and how it was found
• Any other identifying features"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div id="descriptionError" class="error-message hidden">Minimum 20 characters required</div>
                                <div id="charCount" class="text-xs text-gray-500">0 characters</div>
                            </div>
                        </div>
                        
                        <!-- Evidence Location -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                Location Found
                            </label>
                            <input type="text" name="evidence_location" id="evidenceLocation"
                                   placeholder="Where was this evidence found? (e.g., Main Street, Near Barangay Hall)"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <!-- Handover To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-check text-blue-500 mr-1"></i>
                                Handover To (Recipient) *
                            </label>
                            <select name="handover_to" required id="handoverTo"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Select Secretary, Admin, or Captain</option>
                                <?php if (!empty($recipients)): ?>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <option value="<?php echo $recipient['id']; ?>">
                                            <?php echo htmlspecialchars($recipient['full_name']); ?> 
                                            <span class="text-gray-500">
                                                (<?php echo ucfirst($recipient['role']); ?> 
                                                <?php if (!empty($recipient['barangay_position'])): ?>
                                                    - <?php echo $recipient['barangay_position']; ?>
                                                <?php endif; ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No recipients available</option>
                                <?php endif; ?>
                            </select>
                            <div id="recipientError" class="error-message hidden">Please select a recipient</div>
                            <?php if (empty($recipients)): ?>
                                <p class="text-red-500 text-sm mt-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No active secretaries, admins, or captains found.
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Witnesses -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-users text-blue-500 mr-1"></i>
                                Witnesses (Optional)
                            </label>
                            <textarea name="witnesses" rows="2"
                                      placeholder="Names of witnesses present during collection/handover
Example:
• Juan Dela Cruz - Neighbor
• Maria Santos - Business Owner"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                        </div>
                        
                        <!-- Chain of Custody Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-link text-blue-500 mr-1"></i>
                                Chain of Custody Notes
                            </label>
                            <textarea name="chain_of_custody" rows="4"
                                      placeholder="Document chain of custody information:
• Previous handlers (if any)
• Storage conditions
• Sealing information
• Time of collection
• Any transfers before this handover
• Security measures taken"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                        </div>
                        
                        <!-- Photo Upload (Optional) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-camera text-blue-500 mr-1"></i>
                                Upload Evidence Photo (Optional)
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                                <input type="file" name="evidence_photo" id="evidencePhoto" 
                                       class="hidden" accept="image/*">
                                <label for="evidencePhoto" class="cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                    <p class="text-gray-600">Click to upload photo</p>
                                    <p class="text-xs text-gray-500">JPG, PNG up to 5MB</p>
                                </label>
                                <div id="fileName" class="text-sm text-gray-700 mt-2 hidden"></div>
                            </div>
                        </div>
                        
                        <!-- Form Buttons -->
                        <div class="flex space-x-3 pt-4">
                            <button type="submit" name="submit_handover" id="submitBtn"
                                    class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-blue-800 transition flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i>
                                📝 Submit Handover
                            </button>
                            <button type="reset" id="resetBtn"
                                    class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                                Clear
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quick Tips -->
                    <div class="mt-8 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg">
                        <h4 class="font-bold text-blue-800 mb-3 flex items-center">
                            <i class="fas fa-lightbulb mr-2"></i>
                            Evidence Handling Guidelines
                        </h4>
                        <ul class="text-sm text-blue-700 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                                <span>Use gloves when handling evidence</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                                <span>Photograph evidence before moving</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                                <span>Use tamper-evident bags when available</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                                <span>Document all transfers immediately</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                                <span>Maintain continuous chain of custody</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Evidence Records -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <!-- Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-history text-blue-600 mr-3"></i>
                                My Evidence Handovers
                            </h2>
                            <p class="text-gray-600 mt-1">
                                Showing <?php echo count($handovers); ?> record<?php echo count($handovers) != 1 ? 's' : ''; ?>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="ml-3 bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-sm font-bold">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo $stats['pending']; ?> pending acknowledgement
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="flex space-x-3 mt-4 md:mt-0">
                            <!-- Search -->
                            <div class="relative">
                                <input type="text" placeholder="Search evidence..." 
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       onkeyup="filterEvidence(this.value)" id="searchInput">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            
                            <!-- Filters -->
                            <div class="flex space-x-2">
                                <button onclick="window.print()" 
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center no-print">
                                    <i class="fas fa-print mr-2"></i>
                                    Print
                                </button>
                                <button onclick="exportToCSV()"
                                        class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition flex items-center no-print">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Filters -->
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button onclick="filterByStatus('all')" 
                                class="px-4 py-2 bg-gradient-to-r from-blue-100 to-blue-200 text-blue-700 rounded-lg hover:from-blue-200 hover:to-blue-300 transition font-medium">
                            All (<?php echo $stats['total']; ?>)
                        </button>
                        <button onclick="filterByStatus('pending_acknowledgement')" 
                                class="px-4 py-2 bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-700 rounded-lg hover:from-yellow-200 hover:to-yellow-300 transition font-medium">
                            Pending (<?php echo $stats['pending']; ?>)
                        </button>
                        <button onclick="filterByStatus('acknowledged')" 
                                class="px-4 py-2 bg-gradient-to-r from-green-100 to-green-200 text-green-700 rounded-lg hover:from-green-200 hover:to-green-300 transition font-medium">
                            Acknowledged (<?php echo $stats['acknowledged']; ?>)
                        </button>
                        <button onclick="filterByStatus('released')" 
                                class="px-4 py-2 bg-gradient-to-r from-purple-100 to-purple-200 text-purple-700 rounded-lg hover:from-purple-200 hover:to-purple-300 transition font-medium">
                            Released (<?php echo $stats['released']; ?>)
                        </button>
                    </div>
                    
                    <?php if (empty($handovers)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-16">
                            <div class="text-gray-300 text-6xl mb-6">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-400 mb-4">No Evidence Handovers Found</h3>
                            <p class="text-gray-500 max-w-md mx-auto mb-8">
                                Submit your first evidence handover using the form on the left.
                                Ensure you have collected evidence properly and documented the chain of custody.
                            </p>
                            <button onclick="document.getElementById('handoverForm').scrollIntoView()"
                                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold rounded-lg hover:from-blue-700 hover:to-blue-800 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Log First Evidence
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Evidence Cards Grid -->
                        <div class="grid grid-cols-1 gap-6" id="evidenceGrid">
                            <?php foreach ($handovers as $handover): 
                                $status_class = '';
                                $status_text = '';
                                switch($handover['status']) {
                                    case 'released':
                                        $status_class = 'status-released';
                                        $status_text = 'RELEASED';
                                        break;
                                    case 'acknowledged':
                                        $status_class = 'status-acknowledged';
                                        $status_text = 'ACKNOWLEDGED';
                                        break;
                                    case 'pending_acknowledgement':
                                    default:
                                        $status_class = 'status-pending';
                                        $status_text = 'PENDING';
                                }
                                
                                $type_class = 'badge-' . $handover['item_type'];
                                if (!in_array($handover['item_type'], ['weapon', 'document', 'electronic', 'clothing', 'drugs', 'money', 'jewelry', 'vehicle', 'tool', 'property', 'personal'])) {
                                    $type_class = 'badge-other';
                                }
                                
                                $evidence_code = 'EVID-' . str_pad($handover['id'], 5, '0', STR_PAD_LEFT);
                            ?>
                            <div class="evidence-card bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden <?php echo $status_class; ?>"
                                 data-status="<?php echo $handover['status']; ?>"
                                 data-type="<?php echo $handover['item_type']; ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower(
                                     $handover['item_description'] . ' ' . 
                                     $handover['item_type'] . ' ' . 
                                     $handover['recipient_first'] . ' ' . 
                                     $handover['recipient_last'] . ' ' .
                                     ($handover['case_number'] ?? '') . ' ' .
                                     $evidence_code
                                 )); ?>">
                                
                                <!-- Card Header -->
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="flex items-center mb-2">
                                                <span class="font-bold text-gray-800 text-lg">
                                                    <?php echo $evidence_code; ?>
                                                </span>
                                                <span class="status-badge <?php echo $status_class; ?> ml-3">
                                                    <?php echo $status_text; ?>
                                                </span>
                                                <?php if (!empty($handover['case_number'])): ?>
                                                    <span class="ml-3 px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                                                        <i class="fas fa-folder mr-1"></i>
                                                        <?php echo htmlspecialchars($handover['case_number']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <span class="type-badge <?php echo $type_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $handover['item_type'])); ?>
                                                </span>
                                                <span class="text-gray-500 ml-3">
                                                    <i class="far fa-calendar mr-1"></i>
                                                    <?php echo date('M d, Y', strtotime($handover['handover_date'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date('h:i A', strtotime($handover['handover_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="p-4">
                                    <!-- Description -->
                                    <p class="text-gray-700 mb-4 line-clamp-2">
                                        <?php echo htmlspecialchars(substr($handover['item_description'], 0, 120)); ?>
                                        <?php if (strlen($handover['item_description']) > 120): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <!-- Case Info -->
                                    <?php if (!empty($handover['case_title'])): ?>
                                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                                            <p class="text-xs text-gray-500 mb-1">Linked to Case:</p>
                                            <p class="text-sm font-medium text-gray-800">
                                                <?php echo htmlspecialchars($handover['case_title']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- People Involved -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">From (Tanod):</p>
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-full flex items-center justify-center text-white font-bold mr-2">
                                                    <?php echo strtoupper(substr($handover['tanod_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-sm text-gray-800"><?php echo htmlspecialchars($handover['tanod_first'] . ' ' . $handover['tanod_last']); ?></p>
                                                    <span class="role-badge badge-tanod text-xs">Tanod</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">To (Recipient):</p>
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-700 rounded-full flex items-center justify-center text-white font-bold mr-2">
                                                    <?php echo strtoupper(substr($handover['recipient_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-sm text-gray-800"><?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?></p>
                                                    <span class="role-badge badge-<?php echo $handover['recipient_role']; ?> text-xs">
                                                        <?php echo ucfirst($handover['recipient_role']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Chain of Custody Timeline -->
                                    <div class="chain-of-custody mb-4">
                                        <div class="chain-item <?php echo $handover['acknowledged_at'] ? 'completed' : 'pending'; ?>">
                                            <div class="text-xs text-gray-500">Handover Submitted</div>
                                            <div class="text-sm font-medium">
                                                <?php echo date('M d, h:i A', strtotime($handover['handover_date'])); ?>
                                            </div>
                                            <?php if (!empty($handover['evidence_location'])): ?>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    <?php echo htmlspecialchars($handover['evidence_location']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="chain-item <?php echo $handover['acknowledged_at'] ? 'completed' : 'pending'; ?>">
                                            <div class="text-xs text-gray-500">Recipient Acknowledgement</div>
                                            <?php if ($handover['acknowledged_at']): ?>
                                                <div class="text-sm font-medium">
                                                    <?php echo date('M d, h:i A', strtotime($handover['acknowledged_at'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <i class="fas fa-user-check mr-1"></i>
                                                    <?php echo htmlspecialchars($handover['acknowledged_first'] . ' ' . $handover['acknowledged_last']); ?>
                                                </div>
                                                <?php if (!empty($handover['acknowledgement_notes'])): ?>
                                                    <div class="text-xs text-gray-500 mt-1 italic">
                                                        "<?php echo htmlspecialchars(substr($handover['acknowledgement_notes'], 0, 60)); ?>..."
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-sm font-medium text-yellow-600">
                                                    <i class="fas fa-clock mr-1"></i> Waiting for acknowledgement
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($handover['released']): ?>
                                        <div class="chain-item completed">
                                            <div class="text-xs text-gray-500">Evidence Released</div>
                                            <div class="text-sm font-medium">
                                                <?php echo ucfirst($handover['release_type']); ?> - 
                                                <?php echo date('M d, h:i A', strtotime($handover['released_at'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                        <button onclick="showDetails(<?php echo $handover['id']; ?>)"
                                                class="px-4 py-2 bg-gradient-to-r from-blue-100 to-blue-200 text-blue-700 rounded-lg hover:from-blue-200 hover:to-blue-300 transition text-sm font-medium">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                        
                                        <div class="text-xs text-gray-500">
                                            Last updated: <?php echo date('M d, Y', strtotime($handover['updated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if (count($handovers) >= 50): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="inline-flex rounded-md shadow">
                            <button class="px-4 py-2 rounded-l-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="px-4 py-2 border-t border-b border-gray-300 bg-gradient-to-r from-blue-500 to-blue-600 text-white">1</button>
                            <button class="px-4 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">2</button>
                            <button class="px-4 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">3</button>
                            <button class="px-4 py-2 rounded-r-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Barangay LEIR Evidence Handover System v2.0 &copy; <?php echo date('Y'); ?></p>
            <p class="mt-1">All evidence transfers are logged for chain of custody documentation.</p>
            <p class="mt-2 text-xs text-gray-400">
                <i class="fas fa-shield-alt mr-1"></i>
                Critical Data: Item description, Date/Time of handover, Recipient's acknowledgement
            </p>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] my-8">
            <div class="p-6 border-b sticky top-0 bg-white z-10">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800" id="detailsTitle"></h3>
                    <button onclick="closeDetailsModal()" 
                            class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto max-h-[70vh]" id="detailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="p-6 border-t flex justify-end">
                <button onclick="closeDetailsModal()"
                        class="px-6 py-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-lg hover:from-gray-700 hover:to-gray-800 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Character counter for description
    document.getElementById('itemDescription').addEventListener('input', function() {
        const charCount = this.value.length;
        document.getElementById('charCount').textContent = charCount + ' characters';
        
        if (charCount < 20 && charCount > 0) {
            document.getElementById('descriptionError').classList.remove('hidden');
            this.classList.add('form-error');
        } else {
            document.getElementById('descriptionError').classList.add('hidden');
            this.classList.remove('form-error');
        }
    });
    
    // File upload display
    document.getElementById('evidencePhoto').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        const fileDisplay = document.getElementById('fileName');
        
        if (fileName) {
            fileDisplay.textContent = 'Selected: ' + fileName;
            fileDisplay.classList.remove('hidden');
        } else {
            fileDisplay.classList.add('hidden');
        }
    });
    
    // Form validation
    document.getElementById('handoverForm').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate evidence type
        const itemType = document.getElementById('itemType');
        if (!itemType.value) {
            document.getElementById('itemTypeError').classList.remove('hidden');
            itemType.classList.add('form-error');
            isValid = false;
        } else {
            document.getElementById('itemTypeError').classList.add('hidden');
            itemType.classList.remove('form-error');
        }
        
        // Validate description
        const description = document.getElementById('itemDescription');
        if (description.value.length < 20) {
            document.getElementById('descriptionError').classList.remove('hidden');
            description.classList.add('form-error');
            isValid = false;
        } else {
            document.getElementById('descriptionError').classList.add('hidden');
            description.classList.remove('form-error');
        }
        
        // Validate recipient
        const recipient = document.getElementById('handoverTo');
        if (!recipient.value) {
            document.getElementById('recipientError').classList.remove('hidden');
            recipient.classList.add('form-error');
            isValid = false;
        } else {
            document.getElementById('recipientError').classList.add('hidden');
            recipient.classList.remove('form-error');
        }
        
        if (!isValid) {
            e.preventDefault();
            showToast('Please fix the errors in the form before submitting.', 'error');
        } else {
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            submitBtn.disabled = true;
            
            // Re-enable after 5 seconds in case submission fails
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        }
    });
    
    // Reset form
    document.getElementById('resetBtn').addEventListener('click', function() {
        document.getElementById('fileName').classList.add('hidden');
        document.querySelectorAll('.form-error').forEach(el => el.classList.remove('form-error'));
        document.querySelectorAll('.error-message').forEach(el => el.classList.add('hidden'));
        document.getElementById('charCount').textContent = '0 characters';
    });
    
    // Filter Functions
    function filterByStatus(status) {
        const cards = document.querySelectorAll('.evidence-card');
        cards.forEach(card => {
            if (status === 'all' || card.dataset.status === status) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update active filter button
        document.querySelectorAll('[onclick^="filterByStatus"]').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gradient-to-r', 'from-gray-100', 'to-gray-200', 'text-gray-700');
        });
        
        const activeBtn = document.querySelector(`[onclick="filterByStatus('${status}')"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-gradient-to-r', 'from-gray-100', 'to-gray-200', 'text-gray-700');
            activeBtn.classList.add('bg-gradient-to-r', 'from-blue-600', 'to-blue-700', 'text-white');
        }
    }
    
    function filterEvidence(searchTerm) {
        const cards = document.querySelectorAll('.evidence-card');
        const term = searchTerm.toLowerCase();
        
        cards.forEach(card => {
            const searchable = card.dataset.search;
            if (searchable.includes(term)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Details Modal
    function showDetails(id) {
        const modal = document.getElementById('detailsModal');
        const title = document.getElementById('detailsTitle');
        const content = document.getElementById('detailsContent');
        
        title.textContent = 'Evidence Details: EVID-' + String(id).padStart(5, '0');
        content.innerHTML = `
            <div class="flex justify-center items-center h-40">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-500"></i>
                <span class="ml-3 text-gray-600">Loading details...</span>
            </div>
        `;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Simulate loading details
        setTimeout(() => {
            const evidenceCode = 'EVID-' + String(id).padStart(5, '0');
            content.innerHTML = `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                        <h4 class="font-bold text-blue-800 mb-3">Evidence Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Evidence Code</p>
                                <p class="font-bold text-lg">${evidenceCode}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Type</p>
                                <p class="font-bold">Physical Evidence</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date Logged</p>
                                <p class="font-bold">Loading...</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <span class="status-badge status-pending">Pending</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-bold text-gray-800 mb-3">Chain of Custody</h4>
                        <div class="chain-of-custody">
                            <div class="chain-item completed">
                                <div class="text-sm font-bold">Collection by Tanod</div>
                                <div class="text-sm text-gray-600">Date: Loading...</div>
                            </div>
                            <div class="chain-item pending">
                                <div class="text-sm font-bold">Transfer to Office</div>
                                <div class="text-sm text-gray-600">Awaiting acknowledgement</div>
                            </div>
                            <div class="chain-item">
                                <div class="text-sm font-bold">Secure Storage</div>
                                <div class="text-sm text-gray-600">Pending transfer completion</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center py-8">
                        <p class="text-gray-500">Detailed evidence information is being loaded...</p>
                        <p class="text-sm text-gray-400 mt-2">Please check back later or contact system administrator</p>
                    </div>
                </div>
            `;
        }, 1000);
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
    // Export Functions
    function exportToCSV() {
        const evidenceCode = 'EVID-' + new Date().toISOString().slice(0, 10).replace(/-/g, '');
        showToast('Exporting evidence records to CSV...', 'info');
        
        // In a real implementation, this would fetch data from the server
        setTimeout(() => {
            showToast('Evidence records exported successfully!', 'success');
            
            // Simulate file download
            const link = document.createElement('a');
            link.href = '#';
            link.download = `evidence_handovers_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }, 1500);
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
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Set default filter to all
        filterByStatus('all');
        
        // Auto-expand textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
            
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // Focus search input on Ctrl+F
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeDetailsModal();
            }
        });
        
        // Auto-refresh page every 2 minutes
        setTimeout(() => {
            if (confirm('Refresh page to see latest evidence handovers?')) {
                window.location.reload();
            }
        }, 2 * 60 * 1000);
        
        // Show welcome message if first visit
        if (!localStorage.getItem('evidenceHandoverVisited')) {
            showToast('Welcome to Evidence Handover Log! Document all evidence transfers here.', 'info');
            localStorage.setItem('evidenceHandoverVisited', 'true');
        }
    });
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>