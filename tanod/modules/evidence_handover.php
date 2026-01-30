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

// Handle form submission for new evidence handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_handover'])) {
    if ($user_role !== 'tanod') {
        $error_message = "Only Tanods can submit evidence handovers.";
    } else {
        $item_description = trim($_POST['item_description']);
        $item_type = trim($_POST['item_type']);
        $handover_to = intval($_POST['handover_to']);
        $handover_date = date('Y-m-d H:i:s');
        $chain_of_custody = trim($_POST['chain_of_custody'] ?? '');
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
                
                // Insert evidence handover - using correct column names from database
                $stmt = $pdo->prepare("
                    INSERT INTO evidence_handovers 
                    (tanod_id, item_description, item_type, handover_to, handover_date, chain_of_custody) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tanod_id, 
                    $item_description, 
                    $item_type, 
                    $handover_to, 
                    $handover_date, 
                    $chain_of_custody
                ]);
                
                $handover_id = $pdo->lastInsertId();
                
                // Create notification for recipient
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, title, message, related_type, related_id, created_at) 
                    VALUES (?, ?, ?, 'evidence_handover', ?, NOW())
                ");
                
                // Get recipient name for notification
                $recipient_stmt = $pdo->prepare("
                    SELECT CONCAT(first_name, ' ', last_name) as name, role 
                    FROM users WHERE id = ?
                ");
                $recipient_stmt->execute([$handover_to]);
                $recipient = $recipient_stmt->fetch();
                $recipient_name = $recipient ? $recipient['name'] : 'Recipient';
                
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
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "✅ Evidence handover logged successfully (ID: $evidence_code).";
                
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

// Fetch evidence handover records
try {
    // Updated query with correct JOINs and removed non-existent columns
    $stmt = $pdo->prepare("
        SELECT eh.*, 
               u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
               u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
               u_recipient.role as recipient_role,
               u_acknowledged.first_name as acknowledged_first, u_acknowledged.last_name as acknowledged_last
        FROM evidence_handovers eh
        JOIN users u_tanod ON eh.tanod_id = u_tanod.id
        JOIN users u_recipient ON eh.handover_to = u_recipient.id
        LEFT JOIN users u_acknowledged ON eh.recipient_acknowledged = u_acknowledged.id
        WHERE eh.tanod_id = ?
        ORDER BY eh.handover_date DESC
        LIMIT 50
    ");
    $stmt->execute([$tanod_id]);
    $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch secretaries and admins for dropdown
    $recipient_stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as full_name, role
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
    
} catch (PDOException $e) {
    error_log("Error fetching handover records: " . $e->getMessage());
    $error_message = "❌ Error fetching handover records: " . $e->getMessage();
}

// Get statistics
$stats = [
    'total' => count($handovers),
    'pending' => count(array_filter($handovers, function($h) { 
        return $h['recipient_acknowledged'] == 0; 
    })),
    'acknowledged' => count(array_filter($handovers, function($h) { 
        return $h['recipient_acknowledged'] == 1; 
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .glass-morphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
        }
        
        .compact-card {
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .compact-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        
        .status-chip {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .chip-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .chip-acknowledged {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .type-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        
        .tag-weapon { background: #fee2e2; color: #991b1b; }
        .tag-document { background: #dbeafe; color: #1e40af; }
        .tag-electronic { background: #e0e7ff; color: #3730a3; }
        .tag-drugs { background: #fce7f3; color: #9d174d; }
        .tag-money { background: #dcfce7; color: #166534; }
        .tag-other { background: #f3f4f6; color: #374151; }
        
        .role-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-tanod { background: #dbeafe; color: #1e40af; }
        .badge-secretary { background: #d1fae5; color: #065f46; }
        .badge-admin { background: #f3e8ff; color: #6b21a8; }
        .badge-captain { background: #fef3c7; color: #92400e; }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Gradient backgrounds */
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-success {
            background: linear-gradient(135deg, #38b2ac 0%, #319795 100%);
        }
        
        .gradient-warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-gray-100 min-h-screen p-2 md:p-4">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-4 md:mb-6">
            <div class="glass-morphism rounded-2xl p-4 md:p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-boxes text-purple-600 mr-2"></i>
                            Evidence Handover
                        </h1>
                        <p class="text-gray-600 text-sm mt-1">Securely transfer evidence with digital trail</p>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex flex-wrap gap-2">
                        <div class="bg-white rounded-xl px-4 py-3 shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></div>
                            <div class="text-xs text-gray-500">Total</div>
                        </div>
                        <div class="bg-white rounded-xl px-4 py-3 shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></div>
                            <div class="text-xs text-gray-500">Pending</div>
                        </div>
                        <div class="bg-white rounded-xl px-4 py-3 shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-green-600"><?php echo $stats['acknowledged']; ?></div>
                            <div class="text-xs text-gray-500">Acknowledged</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success_message): ?>
        <div class="mb-4 animate-slide-in">
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl p-4 shadow-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-xl mr-3"></i>
                    <div class="flex-1">
                        <p class="font-semibold"><?php echo $success_message; ?></p>
                        <p class="text-green-100 text-sm mt-1"><?php echo date('h:i A - M j, Y'); ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-100 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="mb-4 animate-slide-in">
            <div class="bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl p-4 shadow-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                    <div class="flex-1">
                        <p class="font-semibold"><?php echo $error_message; ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-100 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
            <!-- Left Column: New Handover Form -->
            <div class="lg:col-span-1">
                <div class="glass-morphism rounded-2xl p-4 md:p-6">
                    <!-- Form Header -->
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-plus-circle text-purple-600 mr-2"></i>
                            New Evidence Transfer
                        </h2>
                        <p class="text-gray-600 text-sm mt-1">Submit evidence for handover</p>
                    </div>
                    
                    <!-- Compact Form -->
                    <form method="POST" action="" id="handoverForm" class="space-y-4">
                        <!-- Evidence Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Evidence Type <span class="text-red-500">*</span>
                            </label>
                            <select name="item_type" required 
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition appearance-none bg-white">
                                <option value="">Select type</option>
                                <option value="weapon">Weapon</option>
                                <option value="document">Document</option>
                                <option value="electronic">Electronic</option>
                                <option value="drugs">Drugs</option>
                                <option value="money">Money</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Evidence Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea name="item_description" required rows="3" id="itemDescription"
                                      placeholder="Detailed description of the evidence..."
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition resize-none"></textarea>
                            <div class="flex justify-between mt-1">
                                <span class="text-xs text-gray-500">Min. 20 characters</span>
                                <span id="charCount" class="text-xs text-gray-500">0</span>
                            </div>
                        </div>
                        
                        <!-- Handover To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Handover To <span class="text-red-500">*</span>
                            </label>
                            <select name="handover_to" required id="handoverTo"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition appearance-none bg-white">
                                <option value="">Select recipient</option>
                                <?php if (!empty($recipients)): ?>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <option value="<?php echo $recipient['id']; ?>">
                                            <?php echo htmlspecialchars($recipient['full_name']); ?> 
                                            <span class="text-gray-500">
                                                (<?php echo ucfirst($recipient['role']); ?>)
                                            </span>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No recipients available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <!-- Chain of Custody -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Chain of Custody Notes
                            </label>
                            <textarea name="chain_of_custody" rows="2"
                                      placeholder="Document chain of custody..."
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition resize-none"></textarea>
                        </div>
                        
                        <!-- Form Buttons -->
                        <div class="pt-2">
                            <button type="submit" name="submit_handover" id="submitBtn"
                                    class="w-full gradient-primary text-white font-semibold py-3 px-4 rounded-lg hover:opacity-90 transition duration-200 shadow-md">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Submit Handover
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quick Help -->
                    <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            <span class="text-sm font-semibold text-blue-800">Quick Tips</span>
                        </div>
                        <ul class="text-xs text-blue-700 space-y-1.5">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>
                                <span>Always wear gloves when handling evidence</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>
                                <span>Document every transfer immediately</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>
                                <span>Maintain continuous chain of custody</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Evidence Records -->
            <div class="lg:col-span-2">
                <div class="glass-morphism rounded-2xl p-4 md:p-6">
                    <!-- Records Header -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-history text-gray-600 mr-2"></i>
                                Transfer History
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">
                                <?php echo count($handovers); ?> record<?php echo count($handovers) != 1 ? 's' : ''; ?>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="ml-2 bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full text-xs font-medium">
                                        <?php echo $stats['pending']; ?> pending
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-2 mt-4 md:mt-0">
                            <!-- Search -->
                            <div class="relative flex-1 md:flex-none">
                                <input type="text" placeholder="Search evidence..." 
                                       class="pl-9 pr-3 py-2 text-sm w-full md:w-64 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                       onkeyup="filterEvidence(this.value)" id="searchInput">
                                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                            </div>
                            
                            <!-- Filters -->
                            <div class="flex space-x-1">
                                <button onclick="filterByStatus('all')" 
                                        class="px-3 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                                    All
                                </button>
                                <button onclick="filterByStatus('pending')" 
                                        class="px-3 py-2 text-sm bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition font-medium">
                                    Pending
                                </button>
                                <button onclick="filterByStatus('acknowledged')" 
                                        class="px-3 py-2 text-sm bg-green-100 text-green-800 rounded-lg hover:bg-green-200 transition font-medium">
                                    Done
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($handovers)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="text-gray-300 text-5xl mb-4">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-400 mb-2">No Evidence Transfers</h3>
                            <p class="text-gray-500 max-w-sm mx-auto text-sm mb-6">
                                Submit your first evidence handover using the form on the left.
                            </p>
                            <button onclick="document.getElementById('handoverForm').scrollIntoView({behavior: 'smooth'})"
                                    class="inline-flex items-center px-5 py-2.5 gradient-primary text-white font-semibold rounded-lg hover:opacity-90 transition shadow-md">
                                <i class="fas fa-plus mr-2"></i>
                                Create First Transfer
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Evidence Cards List -->
                        <div class="space-y-3 max-h-[calc(100vh-300px)] overflow-y-auto scrollbar-thin p-1" id="evidenceGrid">
                            <?php foreach ($handovers as $handover): 
                                $status_class = $handover['recipient_acknowledged'] == 1 ? 'chip-acknowledged' : 'chip-pending';
                                $status_text = $handover['recipient_acknowledged'] == 1 ? 'ACKNOWLEDGED' : 'PENDING';
                                
                                $type_class = 'tag-' . $handover['item_type'];
                                if (!in_array($handover['item_type'], ['weapon', 'document', 'electronic', 'drugs', 'money'])) {
                                    $type_class = 'tag-other';
                                }
                                
                                $evidence_code = 'EVID-' . str_pad($handover['id'], 5, '0', STR_PAD_LEFT);
                                $short_description = strlen($handover['item_description']) > 80 ? 
                                    substr($handover['item_description'], 0, 80) . '...' : 
                                    $handover['item_description'];
                            ?>
                            <div class="compact-card bg-white border border-gray-200 rounded-xl p-4 hover:border-gray-300"
                                 data-status="<?php echo $handover['recipient_acknowledged'] == 1 ? 'acknowledged' : 'pending'; ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower(
                                     $handover['item_description'] . ' ' . 
                                     $handover['item_type'] . ' ' . 
                                     $handover['recipient_first'] . ' ' . 
                                     $handover['recipient_last'] . ' ' .
                                     $evidence_code
                                 )); ?>">
                                
                                <!-- Card Content -->
                                <div class="flex flex-col md:flex-row md:items-center">
                                    <!-- Left: Info -->
                                    <div class="flex-1 mb-3 md:mb-0 md:mr-4">
                                        <div class="flex items-center mb-2">
                                            <span class="font-semibold text-gray-800 text-sm">
                                                <?php echo $evidence_code; ?>
                                            </span>
                                            <span class="status-chip <?php echo $status_class; ?> ml-2">
                                                <?php echo $status_text; ?>
                                            </span>
                                            <span class="type-tag <?php echo $type_class; ?> ml-2">
                                                <?php echo ucfirst($handover['item_type']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-700 text-sm mb-3 line-clamp-2">
                                            <?php echo htmlspecialchars($short_description); ?>
                                        </p>
                                        
                                        <div class="flex flex-wrap items-center text-xs text-gray-500 gap-2">
                                            <span>
                                                <i class="far fa-calendar mr-1"></i>
                                                <?php echo date('M d, Y', strtotime($handover['handover_date'])); ?>
                                            </span>
                                            <span>•</span>
                                            <span>
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date('h:i A', strtotime($handover['handover_date'])); ?>
                                            </span>
                                            <span>•</span>
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-1"></i>
                                                <?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?>
                                                <span class="role-badge badge-<?php echo $handover['recipient_role']; ?> ml-1">
                                                    <?php echo ucfirst($handover['recipient_role']); ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Right: Actions -->
                                    <div class="flex items-center space-x-2">
                                        <button onclick="showDetails(<?php echo $handover['id']; ?>)"
                                                class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        
                                        <?php if ($handover['recipient_acknowledged'] == 0): ?>
                                            <span class="animate-pulse">
                                                <i class="fas fa-circle text-yellow-500 text-xs"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>
                                    Showing <?php echo min(50, count($handovers)); ?> of <?php echo count($handovers); ?> records
                                </span>
                                <div class="flex items-center space-x-2">
                                    <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                        <i class="fas fa-download mr-1"></i> Export
                                    </button>
                                    <button onclick="window.print()" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                        <i class="fas fa-print mr-1"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer Note -->
        <div class="mt-6 text-center text-gray-500 text-xs">
            <p>Barangay LEIR Evidence System v2.0 • Secured digital evidence trail • <?php echo date('Y'); ?></p>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-hidden">
            <div class="p-4 border-b sticky top-0 bg-white z-10">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800" id="detailsTitle"></h3>
                    <button onclick="closeDetailsModal()" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 overflow-y-auto max-h-[calc(90vh-120px)]" id="detailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="p-4 border-t">
                <button onclick="closeDetailsModal()"
                        class="w-full px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Character counter
    document.getElementById('itemDescription').addEventListener('input', function() {
        document.getElementById('charCount').textContent = this.value.length;
    });
    
    // Form validation
    document.getElementById('handoverForm').addEventListener('submit', function(e) {
        const description = document.getElementById('itemDescription');
        if (description.value.length < 20) {
            e.preventDefault();
            showToast('Description must be at least 20 characters.', 'error');
            description.focus();
        }
    });
    
    // Filter Functions
    function filterByStatus(status) {
        const cards = document.querySelectorAll('.compact-card');
        cards.forEach(card => {
            if (status === 'all' || card.dataset.status === status) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    function filterEvidence(searchTerm) {
        const cards = document.querySelectorAll('.compact-card');
        const term = searchTerm.toLowerCase();
        
        cards.forEach(card => {
            const searchable = card.dataset.search;
            if (searchable.includes(term)) {
                card.style.display = 'flex';
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
        
        // Find the handover data
        const handovers = <?php echo json_encode($handovers); ?>;
        const handover = handovers.find(h => h.id == id);
        
        if (handover) {
            const evidenceCode = 'EVID-' + String(handover.id).padStart(5, '0');
            title.textContent = evidenceCode;
            
            const statusClass = handover.recipient_acknowledged == 1 ? 'chip-acknowledged' : 'chip-pending';
            const statusText = handover.recipient_acknowledged == 1 ? 'ACKNOWLEDGED' : 'PENDING';
            const typeClass = handover.item_type && ['weapon','document','electronic','drugs','money'].includes(handover.item_type) ? 
                'tag-' + handover.item_type : 'tag-other';
            
            content.innerHTML = `
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <span class="${statusClass}">${statusText}</span>
                        <span class="${typeClass}">${handover.item_type ? handover.item_type.toUpperCase() : 'UNKNOWN'}</span>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Description</h4>
                        <p class="text-gray-800 bg-gray-50 p-3 rounded-lg">${handover.item_description || 'No description'}</p>
                    </div>
                    
                    <!-- Details Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Submitted By</h4>
                            <p class="text-gray-800">${handover.tanod_first} ${handover.tanod_last}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Recipient</h4>
                            <p class="text-gray-800">${handover.recipient_first} ${handover.recipient_last}</p>
                            <span class="text-xs text-gray-500">${handover.recipient_role}</span>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Date & Time</h4>
                            <p class="text-gray-800">${new Date(handover.handover_date).toLocaleDateString()}</p>
                            <p class="text-xs text-gray-500">${new Date(handover.handover_date).toLocaleTimeString()}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Created</h4>
                            <p class="text-gray-800">${new Date(handover.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                    
                    <!-- Chain of Custody -->
                    ${handover.chain_of_custody ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Chain of Custody</h4>
                        <p class="text-gray-800 bg-blue-50 p-3 rounded-lg text-sm">${handover.chain_of_custody}</p>
                    </div>
                    ` : ''}
                    
                    <!-- Acknowledgement -->
                    ${handover.recipient_acknowledged == 1 && handover.acknowledged_first ? `
                    <div class="bg-green-50 p-3 rounded-lg">
                        <h4 class="text-sm font-medium text-green-800 mb-1">Acknowledged By</h4>
                        <p class="text-green-800">${handover.acknowledged_first} ${handover.acknowledged_last}</p>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const toastId = 'toast-' + Date.now();
        
        let bgColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'bg-gradient-to-r from-green-500 to-emerald-600';
                icon = 'fa-check-circle';
                break;
            case 'error':
                bgColor = 'bg-gradient-to-r from-red-500 to-pink-600';
                icon = 'fa-exclamation-circle';
                break;
            default:
                bgColor = 'bg-gradient-to-r from-blue-500 to-indigo-600';
                icon = 'fa-info-circle';
        }
        
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-3 rounded-lg shadow-xl z-50 animate-slide-in`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-3"></i>
                <span class="font-medium">${message}</span>
                <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 text-white/80 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (document.getElementById(toastId)) {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        filterByStatus('all');
        
        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                const pending = <?php echo $stats['pending']; ?>;
                if (pending > 0) {
                    showToast(`${pending} pending handovers need attention`, 'warning');
                }
            }
        }, 5 * 60 * 1000);
    });
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Close modal on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDetailsModal();
    });
    </script>
</body>
</html>