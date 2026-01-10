<?php
session_start();

// Check if user is logged in - adjust based on your actual session structure
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); // Adjust login page location
    exit();
}

// Database connection - adjust the path based on your project structure
// If evidence_handover.php is in /tanod/modules/, then config is at ../../config/
require_once __DIR__ . '/../../config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'citizen'; // Adjust based on your session

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
        
        if (empty($item_description) || empty($handover_to)) {
            $error_message = "Please fill in all required fields.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO evidence_handovers 
                    (tanod_id, item_description, item_type, handover_to, handover_date, chain_of_custody, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $item_description, $item_type, $handover_to, $handover_date, $chain_of_custody]);
                
                $success_message = "Evidence handover logged successfully. Waiting for recipient acknowledgement.";
                
                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    'evidence_handover', 
                    "Logged evidence handover: $item_type - $item_description", 
                    $_SERVER['REMOTE_ADDR']
                ]);
                
            } catch (PDOException $e) {
                $error_message = "Error logging handover: " . $e->getMessage();
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
        
        try {
            $stmt = $pdo->prepare("
                UPDATE evidence_handovers 
                SET recipient_acknowledged = 1 
                WHERE id = ? AND handover_to = ?
            ");
            $stmt->execute([$handover_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Evidence receipt acknowledged successfully.";
                
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
            } else {
                $error_message = "Handover not found or you are not the designated recipient.";
            }
            
        } catch (PDOException $e) {
            $error_message = "Error acknowledging receipt: " . $e->getMessage();
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
                   u_recipient.role as recipient_role
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
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
                   u_recipient.role as recipient_role
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            WHERE eh.handover_to = ?
            ORDER BY eh.handover_date DESC
        ");
        $stmt->execute([$user_id]);
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array($user_role, ['captain', 'lupon'])) {
        // Admin/captain/lupon can see all
        $stmt = $pdo->prepare("
            SELECT eh.*, 
                   u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
                   u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
                   u_recipient.role as recipient_role
            FROM evidence_handovers eh
            JOIN users u_tanod ON eh.tanod_id = u_tanod.id
            JOIN users u_recipient ON eh.handover_to = u_recipient.id
            ORDER BY eh.handover_date DESC
        ");
        $stmt->execute();
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Error fetching handover records: " . $e->getMessage();
}

// Fetch secretaries and admins for dropdown (Tanod only)
$recipients = [];
if ($user_role === 'tanod') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name, role 
            FROM users 
            WHERE role IN ('secretary', 'admin') AND status = 'active'
            ORDER BY last_name, first_name
        ");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error fetching recipients: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence Handover System - Barangay LEIR</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button, .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 2px;
        }
        button:hover, .btn:hover {
            background-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-pending {
            color: #856404;
            background-color: #fff3cd;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .status-acknowledged {
            color: #155724;
            background-color: #d4edda;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .handover-form {
            max-width: 800px;
        }
        .chain-of-custody {
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #007bff;
            margin-top: 10px;
            border-radius: 4px;
        }
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .badge-tanod { background-color: #17a2b8; color: white; }
        .badge-secretary { background-color: #28a745; color: white; }
        .badge-admin { background-color: #dc3545; color: white; }
        .badge-captain { background-color: #ffc107; color: black; }
        .badge-lupon { background-color: #6610f2; color: white; }
        .action-buttons {
            white-space: nowrap;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .user-info {
            float: right;
            font-size: 14px;
        }
        .guidelines {
            background-color: #e7f3ff;
            border-left: 4px solid #17a2b8;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Evidence Handover System</h1>
            <p>Barangay LEIR - Chain of Custody Tracking</p>
            <div class="user-info">
                Logged in as: <strong><?php echo htmlspecialchars($user_role); ?></strong> | 
                <a href="../logout.php" style="color: white;">Logout</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Handover Form (Tanod Only) -->
        <?php if ($user_role === 'tanod'): ?>
        <div class="section handover-form">
            <h2>Log New Evidence Handover</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="item_type">Evidence Type: *</label>
                    <select id="item_type" name="item_type" required>
                        <option value="">Select Type</option>
                        <option value="weapon">Weapon (knife, gun, etc.)</option>
                        <option value="document">Document/Paper Evidence</option>
                        <option value="electronic">Electronic Device</option>
                        <option value="clothing">Clothing/Accessory</option>
                        <option value="vehicle_part">Vehicle Part</option>
                        <option value="drugs">Suspected Drugs/Narcotics</option>
                        <option value="tool">Tool/Instrument</option>
                        <option value="money">Money/Currency</option>
                        <option value="jewelry">Jewelry/Valuables</option>
                        <option value="damaged_property">Damaged Property</option>
                        <option value="personal_effects">Personal Effects</option>
                        <option value="other">Other Evidence</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="item_description">Evidence Description: *</label>
                    <textarea id="item_description" name="item_description" required 
                              placeholder="Provide detailed description including:
- Brand, model, serial numbers
- Color, size, unique markings
- Condition (damaged, intact, etc.)
- Where and how it was found
- Any other identifying features"></textarea>
                    <small>Be as detailed as possible for proper identification</small>
                </div>
                
                <div class="form-group">
                    <label for="handover_to">Handover To: *</label>
                    <select id="handover_to" name="handover_to" required>
                        <option value="">Select Recipient</option>
                        <?php foreach ($recipients as $recipient): ?>
                            <option value="<?php echo $recipient['id']; ?>">
                                <?php echo htmlspecialchars($recipient['full_name']); ?> 
                                <span class="role-badge badge-<?php echo $recipient['role']; ?>">
                                    <?php echo $recipient['role']; ?>
                                </span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select the Secretary or Admin who will receive the evidence</small>
                </div>
                
                <div class="form-group">
                    <label for="chain_of_custody">Chain of Custody Notes:</label>
                    <textarea id="chain_of_custody" name="chain_of_custody" 
                              placeholder="Chain of custody information:
- Where was it found?
- Who handled it before?
- Storage conditions
- Sealing information
- Witnesses present
- Any transfers before this handover"></textarea>
                    <small>Document all handling of this evidence</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="submit_handover" class="btn-success">
                        📝 Log Evidence Handover
                    </button>
                    <button type="reset" class="btn-secondary">Clear Form</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Handover Records -->
        <div class="section">
            <h2>
                <?php 
                    if ($user_role === 'tanod') echo 'My Evidence Handovers';
                    elseif (in_array($user_role, ['secretary', 'admin'])) echo 'Evidence Handovers to Me';
                    else echo 'All Evidence Handovers';
                ?>
                (<?php echo count($handovers); ?> records)
            </h2>
            
            <?php if (empty($handovers)): ?>
                <div class="no-data">
                    <p>📭 No evidence handover records found.</p>
                    <?php if ($user_role === 'tanod'): ?>
                        <p>Submit your first evidence handover using the form above.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Evidence ID</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>From (Tanod)</th>
                                <th>To (Recipient)</th>
                                <th>Date/Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($handovers as $handover): ?>
                            <tr>
                                <td><strong>EVID-<?php echo str_pad($handover['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($handover['item_type'] ?? 'Evidence'); ?>:</strong><br>
                                    <?php echo htmlspecialchars(substr($handover['item_description'], 0, 60)); ?>
                                    <?php if (strlen($handover['item_description']) > 60) echo '...'; ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($handover['item_type'] ?? 'N/A')); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($handover['tanod_first'] . ' ' . $handover['tanod_last']); ?></div>
                                    <span class="role-badge badge-tanod">Tanod</span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?></div>
                                    <span class="role-badge badge-<?php echo $handover['recipient_role'] ?? 'secretary'; ?>">
                                        <?php echo ucfirst($handover['recipient_role'] ?? 'Secretary'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><strong><?php echo date('M d, Y', strtotime($handover['handover_date'])); ?></strong></div>
                                    <div><?php echo date('H:i A', strtotime($handover['handover_date'])); ?></div>
                                </td>
                                <td>
                                    <?php if ($handover['recipient_acknowledged']): ?>
                                        <span class="status-acknowledged">✓ Acknowledged</span>
                                    <?php else: ?>
                                        <span class="status-pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button onclick="viewDetails(<?php echo $handover['id']; ?>)" class="btn">
                                        👁️ View
                                    </button>
                                    
                                    <!-- Acknowledgement Button (Recipient Only) -->
                                    <?php if (!$handover['recipient_acknowledged'] && $handover['handover_to'] == $user_id && in_array($user_role, ['secretary', 'admin'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="handover_id" value="<?php echo $handover['id']; ?>">
                                        <button type="submit" name="acknowledge_handover" class="btn-success" 
                                                onclick="return confirm('Confirm physical receipt of this evidence?\n\nEvidence: <?php echo addslashes(substr($handover['item_description'], 0, 50)); ?>...')">
                                            ✓ Acknowledge
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Hidden details row -->
                            <tr id="details-<?php echo $handover['id']; ?>" style="display: none;">
                                <td colspan="8">
                                    <div class="chain-of-custody">
                                        <h4>🔍 Evidence Details - Chain of Custody Record</h4>
                                        
                                        <p><strong>Evidence ID:</strong> EVID-<?php echo str_pad($handover['id'], 5, '0', STR_PAD_LEFT); ?></p>
                                        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($handover['item_type'])); ?></p>
                                        
                                        <p><strong>Full Description:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($handover['item_description'])); ?></p>
                                        
                                        <?php if (!empty($handover['chain_of_custody'])): ?>
                                        <p><strong>Chain of Custody Notes:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($handover['chain_of_custody'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                            <div>
                                                <strong>Handed Over By:</strong><br>
                                                <?php echo htmlspecialchars($handover['tanod_first'] . ' ' . $handover['tanod_last']); ?><br>
                                                <span class="role-badge badge-tanod">Tanod</span>
                                            </div>
                                            
                                            <div>
                                                <strong>Recipient:</strong><br>
                                                <?php echo htmlspecialchars($handover['recipient_first'] . ' ' . $handover['recipient_last']); ?><br>
                                                <span class="role-badge badge-<?php echo $handover['recipient_role'] ?? 'secretary'; ?>">
                                                    <?php echo ucfirst($handover['recipient_role'] ?? 'Secretary'); ?>
                                                </span>
                                            </div>
                                            
                                            <div>
                                                <strong>Handover Date:</strong><br>
                                                <?php echo date('F j, Y, g:i a', strtotime($handover['handover_date'])); ?>
                                            </div>
                                            
                                            <div>
                                                <strong>Logged in System:</strong><br>
                                                <?php echo date('F j, Y, g:i a', strtotime($handover['created_at'])); ?>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-top: 15px; padding: 10px; background-color: <?php echo $handover['recipient_acknowledged'] ? '#d4edda' : '#fff3cd'; ?>; border-radius: 4px;">
                                            <strong>Status:</strong> 
                                            <?php if ($handover['recipient_acknowledged']): ?>
                                                <span style="color: #155724;">✓ RECEIPT ACKNOWLEDGED</span><br>
                                                <small>Evidence physically received by recipient</small>
                                            <?php else: ?>
                                                <span style="color: #856404;">⏳ PENDING ACKNOWLEDGEMENT</span><br>
                                                <small>Waiting for recipient to confirm physical receipt</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Print/Export Options -->
                <div style="margin-top: 20px; text-align: right;">
                    <button onclick="window.print()" class="btn">🖨️ Print Records</button>
                    <a href="?export=csv" class="btn">📥 Export as CSV</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Chain of Custody Guidelines -->
        <div class="section guidelines">
            <h2>🔐 Chain of Custody Guidelines & Best Practices</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <div>
                    <h3>For Tanods (Collecting Evidence):</h3>
                    <ol>
                        <li>Use gloves when handling evidence</li>
                        <li>Place in tamper-evident bags when possible</li>
                        <li>Label immediately with case details</li>
                        <li>Take photographs before moving</li>
                        <li>Minimize handling - touch only when necessary</li>
                        <li>Record who had access to evidence</li>
                    </ol>
                </div>
                
                <div>
                    <h3>For Secretaries/Admins (Receiving Evidence):</h3>
                    <ol>
                        <li>Verify evidence matches description</li>
                        <li>Check sealing/tamper evidence</li>
                        <li>Store in locked evidence cabinet</li>
                        <li>Maintain evidence logbook</li>
                        <li>Acknowledge receipt immediately in system</li>
                        <li>Limit access to authorized personnel only</li>
                    </ol>
                </div>
                
                <div>
                    <h3>Documentation Requirements:</h3>
                    <ul>
                        <li>Date/time of collection</li>
                        <li>Location found</li>
                        <li>Collector's name and signature</li>
                        <li>Recipient's name and signature</li>
                        <li>Storage location assigned</li>
                        <li>Photographs/video documentation</li>
                        <li>Witness signatures if available</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background-color: #d1ecf1; border-radius: 4px;">
                <strong>⚠️ Important Legal Note:</strong> Proper chain of custody is essential for evidence to be admissible in court. Any break in the chain can render evidence useless for legal proceedings.
            </div>
        </div>
    </div>
    
    <script>
        // Toggle evidence details view
        function viewDetails(id) {
            const detailsRow = document.getElementById('details-' + id);
            const allDetails = document.querySelectorAll('[id^="details-"]');
            
            // Close all other open details
            allDetails.forEach(row => {
                if (row.id !== 'details-' + id) {
                    row.style.display = 'none';
                }
            });
            
            // Toggle current details
            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
            } else {
                detailsRow.style.display = 'none';
            }
        }
        
        // Print functionality
        function printEvidenceRecord(id) {
            window.open('print_evidence.php?id=' + id, '_blank');
        }
        
        // Auto-expand textareas
        document.addEventListener('input', function(e) {
            if (e.target.tagName === 'TEXTAREA') {
                e.target.style.height = 'auto';
                e.target.style.height = (e.target.scrollHeight) + 'px';
            }
        });
        
        // Confirm before acknowledging
        document.addEventListener('DOMContentLoaded', function() {
            const ackButtons = document.querySelectorAll('[name="acknowledge_handover"]');
            ackButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you physically in possession of this evidence?\n\nThis acknowledgement cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>