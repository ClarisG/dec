<?php
// evidence_handover.php - COMPLETE UPDATED VERSION
// This is a standalone evidence handover system for all user roles

// Start session at the very beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../config/database.php';

// If database.php doesn't set $pdo, create the connection here
if (!isset($pdo)) {
    try {
        // IMPORTANT: Update these with your actual database credentials
        $host = 'localhost';
        $dbname = 'barangay_leir_db'; // Change to your actual database name
        $username = 'root'; // Change to your actual username
        $password = ''; // Change to your actual password
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'citizen';
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Initialize variables
$success_message = '';
$error_message = '';
$handovers = [];

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
                    (tanod_id, item_description, item_type, handover_to, handover_date, chain_of_custody, 
                     evidence_location, witnesses, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id, 
                    $item_description, 
                    $item_type, 
                    $handover_to, 
                    $handover_date, 
                    $chain_of_custody,
                    $evidence_location,
                    $witnesses
                ]);
                
                $handover_id = $pdo->lastInsertId();
                
                // Create notification for recipient
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, is_read, created_at) 
                    VALUES (?, ?, ?, 'evidence_handover', ?, 0, NOW())
                ");
                
                // Get recipient name for notification
                $recipient_stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $recipient_stmt->execute([$handover_to]);
                $recipient = $recipient_stmt->fetch();
                $recipient_name = $recipient ? $recipient['name'] : 'Recipient';
                
                $notif_stmt->execute([
                    $handover_to,
                    '📦 New Evidence Handover',
                    "Tanod $user_name has submitted evidence for your acknowledgement (EVID-" . str_pad($handover_id, 5, '0', STR_PAD_LEFT) . ")",
                    $handover_id
                ]);
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    'evidence_handover', 
                    "Submitted evidence handover #$handover_id: $item_type - " . substr($item_description, 0, 50) . "...", 
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "✅ Evidence handover logged successfully (ID: EVID-" . str_pad($handover_id, 5, '0', STR_PAD_LEFT) . "). Waiting for recipient acknowledgement.";
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "❌ Error logging handover: " . $e->getMessage();
            }
        }
    }
}

// Handle acknowledgement (Secretary/Admin only)
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
                    acknowledged_at = NOW(),
                    acknowledgement_notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND handover_to = ? AND recipient_acknowledged = 0
            ");
            $stmt->execute([$acknowledgement_notes, $handover_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Get handover details for notification
                $details_stmt = $pdo->prepare("
                    SELECT tanod_id, item_description FROM evidence_handovers WHERE id = ?
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
                    $notif_stmt->execute([
                        $handover_details['tanod_id'],
                        '✅ Evidence Acknowledged',
                        "Your evidence handover #$handover_id has been acknowledged by $user_name.",
                        $handover_id
                    ]);
                }
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    'evidence_acknowledged', 
                    "Acknowledged receipt of evidence handover ID: $handover_id", 
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "✅ Evidence receipt acknowledged successfully.";
            } else {
                $error_message = "Handover not found, already acknowledged, or you are not the designated recipient.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "❌ Error acknowledging receipt: " . $e->getMessage();
        }
    }
}

// Handle evidence release (Return/dispose of evidence)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_evidence'])) {
    if (!in_array($user_role, ['secretary', 'admin', 'captain'])) {
        $error_message = "Only Secretaries, Admins, and Barangay Captains can release evidence.";
    } else {
        $handover_id = intval($_POST['handover_id']);
        $release_type = trim($_POST['release_type']);
        $release_notes = trim($_POST['release_notes'] ?? '');
        $released_to = trim($_POST['released_to'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE evidence_handovers 
                SET released = 1, 
                    release_type = ?,
                    release_notes = ?,
                    released_to = ?,
                    released_by = ?,
                    released_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND recipient_acknowledged = 1
            ");
            $stmt->execute([$release_type, $release_notes, $released_to, $user_id, $handover_id]);
            
            if ($stmt->rowCount() > 0) {
                // Get handover details for notification
                $details_stmt = $pdo->prepare("
                    SELECT tanod_id FROM evidence_handovers WHERE id = ?
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
                    $notif_stmt->execute([
                        $handover_details['tanod_id'],
                        '📤 Evidence Released',
                        "Evidence handover #$handover_id has been released ($release_type).",
                        $handover_id
                    ]);
                }
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    'evidence_released', 
                    "Released evidence handover ID: $handover_id ($release_type)", 
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $success_message = "✅ Evidence released successfully.";
            } else {
                $error_message = "Evidence not found or not yet acknowledged by recipient.";
            }
            
        } catch (PDOException $e) {
            $error_message = "❌ Error releasing evidence: " . $e->getMessage();
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
                   u_released.first_name as released_first, u_released.last_name as released_last
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            LEFT JOIN users u_released ON eh.released_by = u_released.id
            WHERE eh.tanod_id = ?
            ORDER BY eh.handover_date DESC
        ");
        $stmt->execute([$user_id]);
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array($user_role, ['secretary', 'admin'])) {
        // Secretaries/Admins see handovers to them
        $stmt = $pdo->prepare("
            SELECT eh.*, 
                   u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
                   u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
                   u_recipient.role as recipient_role,
                   u_recipient.barangay_position as recipient_position,
                   u_released.first_name as released_first, u_released.last_name as released_last
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            LEFT JOIN users u_released ON eh.released_by = u_released.id
            WHERE eh.handover_to = ?
            ORDER BY eh.handover_date DESC
        ");
        $stmt->execute([$user_id]);
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array($user_role, ['captain', 'lupon', 'admin'])) {
        // Captain, Lupon, and Admin can see all
        $stmt = $pdo->prepare("
            SELECT eh.*, 
                   u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
                   u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
                   u_recipient.role as recipient_role,
                   u_recipient.barangay_position as recipient_position,
                   u_released.first_name as released_first, u_released.last_name as released_last
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            LEFT JOIN users u_released ON eh.released_by = u_released.id
            ORDER BY eh.handover_date DESC
        ");
        $stmt->execute();
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "❌ Error fetching handover records: " . $e->getMessage();
}

// Fetch secretaries and admins for dropdown (Tanod only)
$recipients = [];
if ($user_role === 'tanod') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name, role, barangay_position 
            FROM users 
            WHERE role IN ('secretary', 'admin') AND status = 'active'
            ORDER BY 
                CASE role 
                    WHEN 'admin' THEN 1
                    WHEN 'secretary' THEN 2
                    ELSE 3
                END,
                last_name, first_name
        ");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "❌ Error fetching recipients: " . $e->getMessage();
    }
}

// Get statistics
$stats = [
    'total' => count($handovers),
    'pending' => count(array_filter($handovers, function($h) { return !$h['recipient_acknowledged']; })),
    'acknowledged' => count(array_filter($handovers, function($h) { return $h['recipient_acknowledged'] && !$h['released']; })),
    'released' => count(array_filter($handovers, function($h) { return $h['released']; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence Handover System - Barangay LEIR</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
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
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; border-left-color: #f59e0b; }
        .status-acknowledged { background: #d1fae5; color: #065f46; border-left-color: #10b981; }
        .status-released { background: #dbeafe; color: #1e40af; border-left-color: #3b82f6; }
        
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
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
        .badge-other { background: #e5e7eb; color: #374151; }
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-tanod { background: #dbeafe; color: #1e40af; }
        .badge-secretary { background: #d1fae5; color: #065f46; }
        .badge-admin { background: #f3e8ff; color: #6b21a8; }
        .badge-captain { background: #fef3c7; color: #92400e; }
        .badge-lupon { background: #e5e7eb; color: #374151; }
        
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
            background: #e5e7eb;
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
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                padding: 0 !important;
            }
            
            .container {
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .evidence-card {
                break-inside: avoid;
                border: 1px solid #000 !important;
            }
        }
        
        /* Animation for new submissions */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .new-submission {
            animation: pulse 2s ease-in-out infinite;
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
    </style>
</head>
<body class="min-h-screen p-4 md:p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white">
                            <i class="fas fa-box-open mr-3"></i>
                            Evidence Handover System
                        </h1>
                        <p class="text-blue-100 mt-2">Barangay LEIR - Chain of Custody Tracking</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-white text-sm">Logged in as:</p>
                                <p class="text-white font-bold">
                                    <?php echo htmlspecialchars($user_name); ?>
                                    <span class="role-badge badge-<?php echo $user_role; ?> ml-2">
                                        <?php echo ucfirst($user_role); ?>
                                    </span>
                                </p>
                            </div>
                            <a href="../logout.php" 
                               class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition flex items-center">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Bar -->
            <div class="bg-white p-4 border-b">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700"><?php echo $stats['total']; ?></div>
                        <div class="text-sm text-blue-600">Total Records</div>
                    </div>
                    <div class="text-center p-3 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-700"><?php echo $stats['pending']; ?></div>
                        <div class="text-sm text-yellow-600">Pending Acknowledgement</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-700"><?php echo $stats['acknowledged']; ?></div>
                        <div class="text-sm text-green-600">Acknowledged</div>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-700"><?php echo $stats['released']; ?></div>
                        <div class="text-sm text-purple-600">Released</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success_message): ?>
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg animate-pulse">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                <div>
                    <p class="text-green-700 font-medium"><?php echo $success_message; ?></p>
                    <p class="text-green-600 text-sm mt-1"><?php echo date('F j, Y - h:i A'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                <p class="text-red-700 font-medium"><?php echo $error_message; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: New Handover Form (Tanod Only) -->
            <?php if ($user_role === 'tanod'): ?>
            <div class="lg:col-span-1">
                <div class="glass-card rounded-2xl shadow-lg p-6 h-full">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                        Log New Evidence Handover
                    </h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <!-- Evidence Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag text-blue-500 mr-1"></i>
                                Evidence Type *
                            </label>
                            <select name="item_type" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Select Type of Evidence</option>
                                <option value="weapon">🔫 Weapon (knife, gun, etc.)</option>
                                <option value="document">📄 Document/Paper Evidence</option>
                                <option value="electronic">💻 Electronic Device</option>
                                <option value="clothing">👕 Clothing/Accessory</option>
                                <option value="vehicle_part">🚗 Vehicle Part</option>
                                <option value="drugs">💊 Suspected Drugs/Narcotics</option>
                                <option value="tool">🔧 Tool/Instrument</option>
                                <option value="money">💰 Money/Currency</option>
                                <option value="jewelry">💎 Jewelry/Valuables</option>
                                <option value="damaged_property">🏚️ Damaged Property</option>
                                <option value="personal_effects">🎒 Personal Effects</option>
                                <option value="other">📦 Other Evidence</option>
                            </select>
                        </div>
                        
                        <!-- Evidence Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-align-left text-blue-500 mr-1"></i>
                                Evidence Description *
                            </label>
                            <textarea name="item_description" required rows="5"
                                      placeholder="Provide detailed description including:
• Brand, model, serial numbers
• Color, size, unique markings
• Condition (damaged, intact, etc.)
• Where and how it was found
• Any other identifying features"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Minimum 20 characters required</p>
                        </div>
                        
                        <!-- Evidence Location -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                Location Found
                            </label>
                            <input type="text" name="evidence_location"
                                   placeholder="Where was this evidence found?"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <!-- Handover To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-check text-blue-500 mr-1"></i>
                                Handover To (Recipient) *
                            </label>
                            <select name="handover_to" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Select Secretary or Admin</option>
                                <?php foreach ($recipients as $recipient): ?>
                                    <option value="<?php echo $recipient['id']; ?>">
                                        <?php echo htmlspecialchars($recipient['full_name']); ?> 
                                        <span class="text-gray-500">
                                            (<?php echo ucfirst($recipient['role']); ?> 
                                            <?php if (!empty($recipient['barangay_position'])): ?>
                                                - <?php echo $recipient['barangay_position']; ?>
                                            <?php endif; ?>)
                                        </span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($recipients)): ?>
                                <p class="text-red-500 text-sm mt-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No active secretaries or admins found.
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
                                      placeholder="Names of witnesses present during collection/handover"
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
• Previous handlers
• Storage conditions
• Sealing information
• Time of collection
• Any transfers before this handover"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                        </div>
                        
                        <!-- Form Buttons -->
                        <div class="flex space-x-3 pt-4">
                            <button type="submit" name="submit_handover"
                                    class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-blue-800 transition flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i>
                                📝 Submit Handover
                            </button>
                            <button type="reset"
                                    class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                                Clear
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quick Tips -->
                    <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="font-medium text-blue-800 mb-3 flex items-center">
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
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Right Column: Evidence Records -->
            <div class="<?php echo $user_role === 'tanod' ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <!-- Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-history text-blue-600 mr-3"></i>
                                <?php 
                                    if ($user_role === 'tanod') echo 'My Evidence Handovers';
                                    elseif (in_array($user_role, ['secretary', 'admin'])) echo 'Evidence Handovers to Me';
                                    else echo 'All Evidence Handovers';
                                ?>
                            </h2>
                            <p class="text-gray-600 mt-1">
                                Showing <?php echo count($handovers); ?> record<?php echo count($handovers) != 1 ? 's' : ''; ?>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="ml-2 bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-sm">
                                        <?php echo $stats['pending']; ?> pending
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="flex space-x-3 mt-4 md:mt-0">
                            <!-- Search and Filter -->
                            <div class="relative">
                                <input type="text" placeholder="Search evidence..." 
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       onkeyup="filterEvidence(this.value)">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="window.print()" 
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center">
                                    <i class="fas fa-print mr-2"></i>
                                    Print
                                </button>
                                <button onclick="exportToCSV()"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button onclick="filterByStatus('all')" 
                                class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                            All (<?php echo $stats['total']; ?>)
                        </button>
                        <button onclick="filterByStatus('pending')" 
                                class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition">
                            Pending (<?php echo $stats['pending']; ?>)
                        </button>
                        <button onclick="filterByStatus('acknowledged')" 
                                class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                            Acknowledged (<?php echo $stats['acknowledged']; ?>)
                        </button>
                        <button onclick="filterByStatus('released')" 
                                class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition">
                            Released (<?php echo $stats['released']; ?>)
                        </button>
                    </div>
                    
                    <?php if (empty($handovers)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-16">
                            <div class="text-gray-400 text-6xl mb-6">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-400 mb-4">No Evidence Handovers Found</h3>
                            <p class="text-gray-500 max-w-md mx-auto mb-8">
                                <?php if ($user_role === 'tanod'): ?>
                                    Submit your first evidence handover using the form on the left.
                                <?php elseif (in_array($user_role, ['secretary', 'admin'])): ?>
                                    No evidence has been handed over to you yet.
                                <?php else: ?>
                                    No evidence handover records available.
                                <?php endif; ?>
                            </p>
                            <?php if ($user_role === 'tanod'): ?>
                                <a href="#new-handover"
                                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-plus mr-2"></i>
                                    Log First Evidence
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Evidence Cards Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="evidenceGrid">
                            <?php foreach ($handovers as $handover): 
                                $status_class = '';
                                $status_text = '';
                                if ($handover['released']) {
                                    $status_class = 'status-released';
                                    $status_text = 'RELEASED';
                                } elseif ($handover['recipient_acknowledged']) {
                                    $status_class = 'status-acknowledged';
                                    $status_text = 'ACKNOWLEDGED';
                                } else {
                                    $status_class = 'status-pending';
                                    $status_text = 'PENDING';
                                }
                                
                                $type_class = 'badge-' . $handover['item_type'];
                                if (!in_array($handover['item_type'], ['weapon', 'document', 'electronic', 'clothing', 'drugs', 'money', 'jewelry'])) {
                                    $type_class = 'badge-other';
                                }
                            ?>
                            <div class="evidence-card bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden <?php echo $status_class; ?>"
                                 data-status="<?php echo $handover['released'] ? 'released' : ($handover['recipient_acknowledged'] ? 'acknowledged' : 'pending'); ?>"
                                 data-type="<?php echo $handover['item_type']; ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower($handover['item_description'] . ' ' . $handover['item_type'] . ' ' . $handover['tanod_first'] . ' ' . $handover['tanod_last'] . ' ' . $handover['recipient_first'] . ' ' . $handover['recipient_last'])); ?>">
                                
                                <!-- Card Header -->
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="flex items-center mb-2">
                                                <span class="font-bold text-gray-800 text-lg">
                                                    EVID-<?php echo str_pad($handover['id'], 5, '0', STR_PAD_LEFT); ?>
                                                </span>
                                                <span class="status-badge <?php echo $status_class; ?> ml-3">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <span class="type-badge <?php echo $type_class; ?>">
                                                    <?php echo ucfirst($handover['item_type']); ?>
                                                </span>
                                                <span class="text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($handover['handover_date'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">
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
                                    
                                    <!-- People Involved -->
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">From (Tanod):</p>
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold mr-2">
                                                    <?php echo strtoupper(substr($handover['tanod_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-sm"><?php echo htmlspecialchars($handover['tanod_first'] . ' ' . $handover['tanod_last']); ?></p>
                                                    <span class="role-badge badge-tanod text-xs">Tanod</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">To (Recipient):</p>
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 font-bold mr-2">
                                                    <?php echo strtoupper(substr($handover['recipient_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-sm"><?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?></p>
                                                    <span class="role-badge badge-<?php echo $handover['recipient_role']; ?> text-xs">
                                                        <?php echo ucfirst($handover['recipient_role']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Timeline -->
                                    <div class="timeline mb-4">
                                        <div class="timeline-item <?php echo $handover['recipient_acknowledged'] ? 'completed' : 'pending'; ?>">
                                            <div class="text-xs text-gray-500">Handover Submitted</div>
                                            <div class="text-sm font-medium"><?php echo date('M d, h:i A', strtotime($handover['handover_date'])); ?></div>
                                        </div>
                                        <div class="timeline-item <?php echo $handover['recipient_acknowledged'] ? 'completed' : 'pending'; ?>">
                                            <div class="text-xs text-gray-500">Recipient Acknowledgement</div>
                                            <?php if ($handover['recipient_acknowledged']): ?>
                                                <div class="text-sm font-medium"><?php echo date('M d, h:i A', strtotime($handover['acknowledged_at'])); ?></div>
                                            <?php else: ?>
                                                <div class="text-sm font-medium text-yellow-600">Pending</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($handover['released']): ?>
                                        <div class="timeline-item completed">
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
                                                class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition text-sm">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                        
                                        <div class="flex space-x-2">
                                            <?php if (!$handover['recipient_acknowledged'] && $handover['handover_to'] == $user_id && in_array($user_role, ['secretary', 'admin'])): ?>
                                            <button onclick="showAcknowledgeModal(<?php echo $handover['id']; ?>, '<?php echo addslashes(substr($handover['item_description'], 0, 50)); ?>...')"
                                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                                <i class="fas fa-check mr-1"></i> Acknowledge
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($handover['recipient_acknowledged'] && !$handover['released'] && in_array($user_role, ['secretary', 'admin', 'captain'])): ?>
                                            <button onclick="showReleaseModal(<?php echo $handover['id']; ?>)"
                                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i> Release
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pagination (if needed) -->
                    <?php if (count($handovers) > 12): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="inline-flex rounded-md shadow">
                            <button class="px-3 py-2 rounded-l-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="px-3 py-2 border-t border-b border-gray-300 bg-blue-50 text-blue-600">1</button>
                            <button class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">2</button>
                            <button class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">3</button>
                            <button class="px-3 py-2 rounded-r-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <!-- Acknowledge Modal -->
    <div id="acknowledgeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full">
            <form method="POST" action="">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        Acknowledge Evidence Receipt
                    </h3>
                    <p class="text-gray-600 mt-2" id="ackEvidenceDesc"></p>
                </div>
                
                <div class="p-6">
                    <input type="hidden" name="handover_id" id="ackHandoverId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Acknowledgement Notes
                        </label>
                        <textarea name="acknowledgement_notes" rows="3"
                                  placeholder="Add any notes about the condition of evidence, storage location, etc."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"></textarea>
                    </div>
                    
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> This confirms you have physically received the evidence.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 border-t flex justify-end space-x-3">
                    <button type="button" onclick="closeAcknowledgeModal()"
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" name="acknowledge_handover"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-check mr-2"></i>
                        Confirm Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Release Modal -->
    <div id="releaseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full">
            <form method="POST" action="">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-external-link-alt text-purple-500 mr-2"></i>
                        Release Evidence
                    </h3>
                    <p class="text-gray-600 mt-2">Document the release or disposal of evidence</p>
                </div>
                
                <div class="p-6">
                    <input type="hidden" name="handover_id" id="releaseHandoverId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Release Type *
                        </label>
                        <select name="release_type" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                            <option value="">Select Release Type</option>
                            <option value="returned_to_owner">Returned to Owner</option>
                            <option value="disposed">Properly Disposed</option>
                            <option value="transferred_to_pnp">Transferred to PNP</option>
                            <option value="transferred_to_court">Transferred to Court</option>
                            <option value="donated">Donated</option>
                            <option value="auctioned">Auctioned</option>
                            <option value="destroyed">Destroyed</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Released To (If applicable)
                        </label>
                        <input type="text" name="released_to"
                               placeholder="Name of person/organization receiving evidence"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Release Notes
                        </label>
                        <textarea name="release_notes" rows="3"
                                  placeholder="Document the release process, witnesses, etc."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t flex justify-end space-x-3">
                    <button type="button" onclick="closeReleaseModal()"
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" name="release_evidence"
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Release Evidence
                    </button>
                </div>
            </form>
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
                <!-- Content loaded via AJAX -->
            </div>
            <div class="p-6 border-t flex justify-end">
                <button onclick="closeDetailsModal()"
                        class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="mt-8 text-center text-gray-500 text-sm">
        <p>Barangay LEIR Evidence Handover System v2.0 &copy; <?php echo date('Y'); ?></p>
        <p class="mt-1">All evidence transfers are logged for chain of custody documentation.</p>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Modal Functions
    function showAcknowledgeModal(id, description) {
        document.getElementById('ackHandoverId').value = id;
        document.getElementById('ackEvidenceDesc').textContent = 'Evidence: ' + description;
        document.getElementById('acknowledgeModal').classList.remove('hidden');
        document.getElementById('acknowledgeModal').classList.add('flex');
    }
    
    function closeAcknowledgeModal() {
        document.getElementById('acknowledgeModal').classList.add('hidden');
        document.getElementById('acknowledgeModal').classList.remove('flex');
    }
    
    function showReleaseModal(id) {
        document.getElementById('releaseHandoverId').value = id;
        document.getElementById('releaseModal').classList.remove('hidden');
        document.getElementById('releaseModal').classList.add('flex');
    }
    
    function closeReleaseModal() {
        document.getElementById('releaseModal').classList.add('hidden');
        document.getElementById('releaseModal').classList.remove('flex');
    }
    
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
        
        // Load details via AJAX
        fetch('ajax/get_evidence_details.php?id=' + id)
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-4"></i>
                        <p class="text-red-600 font-medium">Failed to load details</p>
                        <p class="text-gray-500 text-sm">Please try again later</p>
                    </div>
                `;
            });
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
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
            btn.classList.add('bg-gray-100', 'text-gray-700');
        });
        
        const activeBtn = document.querySelector(`[onclick="filterByStatus('${status}')"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-gray-100', 'text-gray-700');
            activeBtn.classList.add('bg-blue-600', 'text-white');
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
    
    // Export Functions
    function exportToCSV() {
        let csv = [];
        let headers = ['Evidence ID', 'Type', 'Description', 'From Tanod', 'To Recipient', 'Date', 'Status', 'Acknowledged At', 'Released At'];
        csv.push(headers.join(','));
        
        const cards = document.querySelectorAll('.evidence-card');
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                let row = [];
                // Extract data from card (simplified)
                const id = card.querySelector('span.font-bold')?.textContent || '';
                const type = card.querySelector('.type-badge')?.textContent || '';
                const desc = card.querySelector('p.text-gray-700')?.textContent || '';
                const from = card.querySelector('.flex.items-center .font-medium')?.textContent || '';
                const to = card.querySelectorAll('.flex.items-center .font-medium')[1]?.textContent || '';
                const date = card.querySelector('.text-gray-500')?.textContent || '';
                const status = card.querySelector('.status-badge')?.textContent || '';
                
                row.push(id, type, `"${desc}"`, from, to, date, status, '', '');
                csv.push(row.join(','));
            }
        });
        
        let csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `evidence_handovers_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Auto-expand textareas
    document.addEventListener('input', function(e) {
        if (e.target.tagName === 'TEXTAREA') {
            e.target.style.height = 'auto';
            e.target.style.height = (e.target.scrollHeight) + 'px';
        }
    });
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Set default filter to all
        filterByStatus('all');
        
        // Initialize textarea heights
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        });
        
        // Confirm before acknowledging
        const acknowledgeForms = document.querySelectorAll('form[action*="acknowledge_handover"]');
        acknowledgeForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you physically in possession of this evidence?\n\nThis acknowledgement cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
        
        // Confirm before releasing
        const releaseForms = document.querySelectorAll('form[action*="release_evidence"]');
        releaseForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to release this evidence?\n\nThis action is permanent.')) {
                    e.preventDefault();
                }
            });
        });
        
        // Auto-refresh page every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 5 * 60 * 1000);
    });
    
    // Close modals with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAcknowledgeModal();
            closeReleaseModal();
            closeDetailsModal();
        }
    });
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>