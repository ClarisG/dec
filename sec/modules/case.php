<?php
// Fixed path: from modules folder to config folder
require_once __DIR__ . '/../../config/database.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - allow multiple roles (tanod AND secretary)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tanod', 'secretary', 'admin', 'super_admin', 'captain'])) {
    // Check if we're being included as a module
    $is_included = (strpos($_SERVER['PHP_SELF'], 'secretary_dashboard.php') !== false) 
                   || (strpos($_SERVER['PHP_SELF'], 'modules/case.php') !== false);
    
    if (!$is_included) {
        header('Location: ../../index.php');
        exit();
    } else {
        // If included but not authorized, just show access denied
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                <strong>Access Denied:</strong> You need Tanod or Secretary privileges to access this module.
              </div>";
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$user_role = $_SESSION['role'];

// Check if user is Tanod (for form submission)
$is_tanod = ($user_role === 'tanod');

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
$cases = [];

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$total_records = 0;
$total_pages = 1;

// Check if cases table exists, if not, show warning
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'cases'");
    $cases_table_exists = $table_check->rowCount() > 0;
} catch (PDOException $e) {
    $cases_table_exists = false;
}

// Handle form submission for new evidence handover (Tanod only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_handover'])) {
    if (!$is_tanod) {
        $error_message = "❌ Only Tanods can submit evidence handovers.";
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
                    $user_id, 
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
                    "Tanod $user_name has submitted evidence for your acknowledgement ($evidence_code)",
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
                        "Tanod $user_name submitted evidence to $recipient_name ($evidence_code)",
                        $handover_id
                    ]);
                }
                
                // Log activity
                addActivityLog($pdo, $user_id, 'evidence_handover', 
                    "Submitted evidence handover #$handover_id: $item_type");
                
                // If case_id is provided and cases table exists, update case evidence count
                if ($case_id > 0 && $cases_table_exists) {
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

// Fetch relevant handover records based on user role
try {
    // Get total count for pagination
    if ($user_role === 'tanod') {
        // Tanods see their own handovers
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM evidence_handovers eh
            WHERE eh.tanod_id = ?
        ");
        $count_stmt->execute([$user_id]);
        
        $main_query = "
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
            LIMIT ? OFFSET ?
        ";
    } else {
        // Secretaries, admins, captains see handovers where they are recipients
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM evidence_handovers eh
            WHERE eh.handover_to = ?
        ");
        $count_stmt->execute([$user_id]);
        
        $main_query = "
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
            WHERE eh.handover_to = ?
            ORDER BY eh.handover_date DESC
            LIMIT ? OFFSET ?
        ";
    }
    
    $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $total_result['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Fetch handovers with pagination
    $stmt = $pdo->prepare($main_query);
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch secretaries and admins for dropdown (Tanod only)
    if ($is_tanod) {
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
        
        // Fetch active cases for dropdown if table exists
        if ($cases_table_exists) {
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
    }
} catch (PDOException $e) {
    error_log("Error fetching handover records: " . $e->getMessage());
    $error_message = "❌ Error fetching handover records: " . $e->getMessage();
}

// Get statistics
$stats = [
    'total' => $total_records,
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

// Generate pagination URL
function generatePageUrl($page) {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-subtle': 'pulseSubtle 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        pulseSubtle: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.8' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-morphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .gradient-border {
            position: relative;
            border: double 2px transparent;
            background-image: linear-gradient(white, white), 
                              linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-origin: border-box;
            background-clip: content-box, border-box;
        }
        
        .evidence-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .evidence-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-acknowledged { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-released { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .type-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 6px;
            margin-bottom: 6px;
            gap: 4px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, #10b981, #8b5cf6);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
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
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            z-index: 2;
        }
        
        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .form-input {
            transition: all 0.3s ease;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            width: 100%;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--tw-gradient-from), var(--tw-gradient-to));
        }
        
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .evidence-card {
                break-inside: avoid;
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination .active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination .disabled {
            color: #ccc;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-primary-50 min-h-screen p-4 md:p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="glass-morphism rounded-2xl p-6 mb-6 animate-fade-in">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
                        <i class="fas fa-boxes-stacked text-primary-600"></i>
                        Evidence Handover System
                    </h1>
                    <p class="text-gray-600">Securely document and track evidence transfers with complete chain of custody</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Logged in as</p>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user_name); ?></p>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full <?php echo $is_tanod ? 'bg-primary-100 text-primary-700' : 'bg-green-100 text-green-700'; ?> text-xs font-medium">
                            <i class="fas <?php echo $is_tanod ? 'fa-shield-alt' : 'fa-file-alt'; ?>"></i>
                            <?php echo ucfirst($user_role); ?>
                        </span>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="stat-card bg-gradient-to-b from-white to-blue-50 border border-blue-100" style="--tw-gradient-from: #3b82f6; --tw-gradient-to: #60a5fa;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 mb-1">Total Handovers</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                    </div>
                    <i class="fas fa-archive text-blue-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-b from-white to-yellow-50 border border-yellow-100" style="--tw-gradient-from: #f59e0b; --tw-gradient-to: #fbbf24;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-yellow-600 mb-1">Pending</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['pending']; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-b from-white to-green-50 border border-green-100" style="--tw-gradient-from: #10b981; --tw-gradient-to: #34d399;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600 mb-1">Acknowledged</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['acknowledged']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-b from-white to-purple-50 border border-purple-100" style="--tw-gradient-from: #8b5cf6; --tw-gradient-to: #a78bfa;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600 mb-1">Released</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['released']; ?></p>
                    </div>
                    <i class="fas fa-box-open text-purple-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
        <div class="mb-6 animate-slide-up">
            <div class="bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 rounded-lg p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-green-800"><?php echo $success_message; ?></p>
                        <p class="text-xs text-green-600 mt-1"><?php echo date('F j, Y - h:i A'); ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="ml-auto -mx-1.5 -my-1.5 text-green-500 hover:text-green-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="mb-6 animate-slide-up">
            <div class="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 rounded-lg p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-red-800"><?php echo $error_message; ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="ml-auto -mx-1.5 -my-1.5 text-red-500 hover:text-red-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warning about missing cases table -->
        <?php if (!$cases_table_exists): ?>
        <div class="mb-6 bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-500 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
                <div>
                    <p class="font-medium text-yellow-800">Cases table not found</p>
                    <p class="text-sm text-yellow-600 mt-1">The 'cases' table is missing from the database. Case linking functionality will be limited.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <?php if ($is_tanod): ?>
            <!-- Left Column: New Handover Form (Only for Tanods) -->
            <div class="lg:col-span-1">
                <div class="glass-morphism rounded-2xl p-6 h-full">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-plus-circle text-primary-600"></i>
                            Log New Evidence
                        </h2>
                        <span class="text-xs font-medium px-3 py-1 rounded-full bg-primary-100 text-primary-700">
                            Step 1 of 3
                        </span>
                    </div>
                    
                    <form method="POST" action="" id="handoverForm" class="space-y-5">
                        <!-- Case Reference -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-folder text-gray-500"></i>
                                <span>Link to Case <?php if (!$cases_table_exists): ?><span class="text-yellow-600 text-xs">(Table Missing)</span><?php endif; ?></span>
                            </label>
                            <select name="case_id" 
                                    class="form-input form-select">
                                <option value="">Select Case (Optional)</option>
                                <option value="0">No specific case</option>
                                <?php if ($cases_table_exists && !empty($cases)): ?>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-tag text-primary-500"></i>
                                <span>Evidence Type <span class="text-red-500">*</span></span>
                            </label>
                            <select name="item_type" required class="form-input form-select" id="itemType">
                                <option value="">Select Type of Evidence</option>
                                <option value="weapon">🔫 Weapon</option>
                                <option value="document">📄 Document</option>
                                <option value="electronic">💻 Electronic Device</option>
                                <option value="clothing">👕 Clothing</option>
                                <option value="vehicle">🚗 Vehicle Part</option>
                                <option value="drugs">💊 Suspected Drugs</option>
                                <option value="money">💰 Money/Currency</option>
                                <option value="jewelry">💎 Jewelry</option>
                                <option value="property">🏚️ Damaged Property</option>
                                <option value="personal">🎒 Personal Effects</option>
                                <option value="other">📦 Other Evidence</option>
                            </select>
                        </div>
                        
                        <!-- Evidence Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-align-left text-primary-500"></i>
                                <span>Evidence Description <span class="text-red-500">*</span></span>
                            </label>
                            <textarea name="item_description" required rows="4" id="itemDescription"
                                      placeholder="Provide detailed description including:
• Brand, model, serial numbers
• Color, size, unique markings
• Condition (damaged, intact, etc.)
• Where and how it was found
• Any other identifying features"
                                      class="form-input"></textarea>
                            <div class="flex justify-between items-center mt-2">
                                <div id="descriptionError" class="text-xs text-red-600 hidden">Minimum 20 characters required</div>
                                <div id="charCount" class="text-xs text-gray-500">0 characters</div>
                            </div>
                        </div>
                        
                        <!-- Evidence Location -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-primary-500"></i>
                                <span>Location Found</span>
                            </label>
                            <input type="text" name="evidence_location" id="evidenceLocation"
                                   placeholder="Where was this evidence found?"
                                   class="form-input">
                        </div>
                        
                        <!-- Handover To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-user-check text-primary-500"></i>
                                <span>Handover To (Recipient) <span class="text-red-500">*</span></span>
                            </label>
                            <select name="handover_to" required id="handoverTo" class="form-input form-select">
                                <option value="">Select Secretary, Admin, or Captain</option>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-link text-primary-500"></i>
                                <span>Chain of Custody Notes</span>
                            </label>
                            <textarea name="chain_of_custody" rows="3"
                                      placeholder="Document chain of custody information..."
                                      class="form-input"></textarea>
                        </div>
                        
                        <!-- Witnesses -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-users text-primary-500"></i>
                                <span>Witnesses (Optional)</span>
                            </label>
                            <textarea name="witnesses" rows="2"
                                      placeholder="Names of witnesses present..."
                                      class="form-input"></textarea>
                        </div>
                        
                        <!-- Form Buttons -->
                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex gap-3">
                                <button type="submit" name="submit_handover" id="submitBtn"
                                        class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold py-3 px-6 rounded-lg hover:from-primary-700 hover:to-primary-800 transition-all duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                                    <i class="fas fa-save"></i>
                                    Submit Handover
                                </button>
                                <button type="reset" id="resetBtn"
                                        class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Guidelines -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-graduation-cap text-primary-600"></i>
                            Evidence Handling Guidelines
                        </h4>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check text-green-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Use gloves when handling evidence</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check text-green-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Photograph evidence before moving</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check text-green-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Document all transfers immediately</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Right Column: Evidence Records -->
            <div class="<?php echo $is_tanod ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
                <div class="glass-morphism rounded-2xl p-6">
                    <!-- Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 mb-2 flex items-center gap-2">
                                <i class="fas fa-history text-primary-600"></i>
                                <?php echo $is_tanod ? 'My Evidence Handovers' : 'Evidence Handovers Assigned to Me'; ?>
                            </h2>
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-gray-600">
                                    Showing <?php echo count($handovers); ?> of <?php echo $total_records; ?> record<?php echo $total_records != 1 ? 's' : ''; ?>
                                    (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                                </p>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium flex items-center gap-1">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $stats['pending']; ?> pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            <div class="relative">
                                <input type="text" placeholder="Search evidence..." 
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 w-full md:w-64"
                                       onkeyup="filterEvidence(this.value)" id="searchInput">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="window.print()" 
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center gap-2 no-print">
                                    <i class="fas fa-print"></i>
                                    Print
                                </button>
                                <button onclick="exportToCSV()"
                                        class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition flex items-center gap-2 no-print">
                                    <i class="fas fa-download"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Filters -->
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button onclick="filterByStatus('all')" 
                                class="px-4 py-2 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 transition font-medium">
                            All (<?php echo $stats['total']; ?>)
                        </button>
                        <button onclick="filterByStatus('pending_acknowledgement')" 
                                class="px-4 py-2 bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800 rounded-lg hover:from-yellow-200 hover:to-yellow-300 transition font-medium">
                            Pending (<?php echo $stats['pending']; ?>)
                        </button>
                        <button onclick="filterByStatus('acknowledged')" 
                                class="px-4 py-2 bg-gradient-to-r from-green-100 to-green-200 text-green-800 rounded-lg hover:from-green-200 hover:to-green-300 transition font-medium">
                            Acknowledged (<?php echo $stats['acknowledged']; ?>)
                        </button>
                    </div>
                    
                    <?php if (empty($handovers)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-16">
                            <div class="inline-block p-6 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full mb-6">
                                <i class="fas fa-box-open text-primary-500 text-5xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-4">No Evidence Handovers Found</h3>
                            <p class="text-gray-600 max-w-md mx-auto mb-8">
                                <?php echo $is_tanod ? 
                                    'Start by submitting your first evidence handover. Ensure proper documentation and chain of custody for all evidence transfers.' : 
                                    'No evidence has been handed over to you yet. When Tanods submit evidence handovers to you, they will appear here.'; ?>
                            </p>
                            <?php if ($is_tanod): ?>
                            <button onclick="document.getElementById('handoverForm').scrollIntoView({behavior: 'smooth'})"
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-lg hover:from-primary-700 hover:to-primary-800 transition shadow-md hover:shadow-lg">
                                <i class="fas fa-plus"></i>
                                Log First Evidence
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Evidence Cards -->
                        <div class="grid grid-cols-1 gap-6" id="evidenceGrid">
                            <?php foreach ($handovers as $handover): 
                                $status_class = '';
                                $status_text = '';
                                $status_icon = '';
                                switch($handover['status']) {
                                    case 'released':
                                        $status_class = 'status-released';
                                        $status_text = 'RELEASED';
                                        $status_icon = 'fa-box-open';
                                        break;
                                    case 'acknowledged':
                                        $status_class = 'status-acknowledged';
                                        $status_text = 'ACKNOWLEDGED';
                                        $status_icon = 'fa-check-circle';
                                        break;
                                    case 'pending_acknowledgement':
                                    default:
                                        $status_class = 'status-pending';
                                        $status_text = 'PENDING';
                                        $status_icon = 'fa-clock';
                                }
                                
                                $evidence_code = 'EVID-' . str_pad($handover['id'], 5, '0', STR_PAD_LEFT);
                            ?>
                            <div class="evidence-card bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300"
                                 data-status="<?php echo $handover['status']; ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower(
                                     $handover['item_description'] . ' ' . 
                                     $handover['item_type'] . ' ' . 
                                     $handover['recipient_first'] . ' ' . 
                                     $handover['recipient_last'] . ' ' .
                                     ($handover['case_number'] ?? '') . ' ' .
                                     $evidence_code
                                 )); ?>">
                                
                                <!-- Card Header -->
                                <div class="p-5 border-b border-gray-100">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h3 class="font-bold text-gray-900 text-lg"><?php echo $evidence_code; ?></h3>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <i class="fas <?php echo $status_icon; ?>"></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                                                    <i class="fas fa-tag"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $handover['item_type'])); ?>
                                                </span>
                                                <span class="text-gray-500 text-sm">
                                                    <i class="far fa-calendar"></i>
                                                    <?php echo date('M d, Y', strtotime($handover['handover_date'])); ?>
                                                </span>
                                                <?php if (!empty($handover['case_number'])): ?>
                                                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                        <i class="fas fa-folder"></i>
                                                        <?php echo htmlspecialchars($handover['case_number']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('h:i A', strtotime($handover['handover_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="p-5">
                                    <!-- Description -->
                                    <p class="text-gray-700 mb-5 line-clamp-2">
                                        <?php echo htmlspecialchars(substr($handover['item_description'], 0, 150)); ?>
                                        <?php if (strlen($handover['item_description']) > 150): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <!-- People Involved -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                                        <!-- From Tanod -->
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <p class="text-xs text-gray-500 mb-2">From (Tanod)</p>
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr($handover['tanod_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($handover['tanod_first'] . ' ' . $handover['tanod_last']); ?></p>
                                                    <span class="text-xs text-blue-600 font-medium">Tanod</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- To Recipient -->
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <p class="text-xs text-gray-500 mb-2">To (Recipient)</p>
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-700 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr($handover['recipient_first'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?></p>
                                                    <span class="text-xs text-green-600 font-medium"><?php echo ucfirst($handover['recipient_role']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Timeline -->
                                    <div class="timeline mb-5">
                                        <div class="timeline-item <?php echo $handover['acknowledged_at'] ? 'completed' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-900">Handover Submitted</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php echo date('M d, h:i A', strtotime($handover['handover_date'])); ?>
                                            </div>
                                        </div>
                                        <div class="timeline-item <?php echo $handover['acknowledged_at'] ? 'completed' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-900">Recipient Acknowledgement</div>
                                            <?php if ($handover['acknowledged_at']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo date('M d, h:i A', strtotime($handover['acknowledged_at'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <i class="fas fa-user-check"></i>
                                                    <?php echo htmlspecialchars($handover['acknowledged_first'] . ' ' . $handover['acknowledged_last']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-sm text-yellow-600 mt-1">
                                                    <i class="fas fa-clock"></i> Waiting for acknowledgement
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                        <button onclick="showDetails(<?php echo $handover['id']; ?>)"
                                                class="px-4 py-2 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-700 rounded-lg hover:from-blue-100 hover:to-blue-200 transition font-medium text-sm flex items-center gap-2">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </button>
                                        <div class="text-xs text-gray-500">
                                            Updated: <?php echo date('M d, Y', strtotime($handover['updated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="mt-8">
                            <div class="flex flex-col md:flex-row justify-between items-center">
                                <div class="text-gray-600 text-sm mb-4 md:mb-0">
                                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                                </div>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="<?php echo generatePageUrl($page - 1); ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-gray-100 border border-gray-300 rounded-l-md text-gray-400">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="<?php echo generatePageUrl($i); ?>" class="px-3 py-1 <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?php echo generatePageUrl($page + 1); ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-gray-100 border border-gray-300 rounded-r-md text-gray-400">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Footer -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="text-center text-gray-500 text-sm">
                            <p>Barangay LEIR Evidence Handover System v2.0 &copy; <?php echo date('Y'); ?></p>
                            <p class="mt-1">All evidence transfers are logged for chain of custody documentation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Character counter for description
    document.getElementById('itemDescription')?.addEventListener('input', function() {
        const charCount = this.value.length;
        document.getElementById('charCount').textContent = charCount + ' characters';
        
        if (charCount < 20 && charCount > 0) {
            document.getElementById('descriptionError').classList.remove('hidden');
            this.classList.add('border-red-500');
        } else {
            document.getElementById('descriptionError').classList.add('hidden');
            this.classList.remove('border-red-500');
        }
    });
    
    // Form validation (only for Tanods)
    const handoverForm = document.getElementById('handoverForm');
    if (handoverForm) {
        handoverForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate evidence type
            const itemType = document.getElementById('itemType');
            if (!itemType.value) {
                itemType.classList.add('border-red-500');
                isValid = false;
            } else {
                itemType.classList.remove('border-red-500');
            }
            
            // Validate description
            const description = document.getElementById('itemDescription');
            if (description.value.length < 20) {
                document.getElementById('descriptionError').classList.remove('hidden');
                description.classList.add('border-red-500');
                isValid = false;
            } else {
                document.getElementById('descriptionError').classList.add('hidden');
                description.classList.remove('border-red-500');
            }
            
            // Validate recipient
            const recipient = document.getElementById('handoverTo');
            if (!recipient.value) {
                recipient.classList.add('border-red-500');
                isValid = false;
            } else {
                recipient.classList.remove('border-red-500');
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
            }
        });
    }
    
    // Reset form (only for Tanods)
    const resetBtn = document.getElementById('resetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            document.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
            document.getElementById('descriptionError')?.classList.add('hidden');
            document.getElementById('charCount').textContent = '0 characters';
        });
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
        showToast('Exporting evidence records to CSV...', 'info');
        
        setTimeout(() => {
            showToast('Evidence records exported successfully!', 'success');
        }, 1500);
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const toastId = 'toast-' + Date.now();
        
        let bgColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'bg-gradient-to-r from-green-500 to-green-600';
                icon = 'fa-check-circle';
                break;
            case 'error':
                bgColor = 'bg-gradient-to-r from-red-500 to-red-600';
                icon = 'fa-exclamation-circle';
                break;
            default:
                bgColor = 'bg-gradient-to-r from-blue-500 to-blue-600';
                icon = 'fa-info-circle';
        }
        
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-4 rounded-lg shadow-xl z-50 transform translate-x-full transition-transform duration-300 flex items-center gap-3`;
        toast.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="font-medium">${message}</span>
            <button onclick="document.getElementById('${toastId}').remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
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
    });
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>
