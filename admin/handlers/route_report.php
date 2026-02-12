<?php
// admin/handlers/route_report.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'captain', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['route_report'])) {
    $report_id = $_POST['report_id'] ?? null;
    $routed_to = $_POST['routed_to'] ?? null;
    $notes = $_POST['routing_notes'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Validation
    if (!$report_id) {
        echo json_encode(['success' => false, 'message' => 'Report ID is required']);
        exit;
    }
    if (!$routed_to) {
        echo json_encode(['success' => false, 'message' => 'Recipient is required']);
        exit;
    }

    try {
        $conn = getDbConnection();
        
        // Check if report exists
        $check_report = $conn->prepare("SELECT id, status FROM reports WHERE id = :id");
        $check_report->execute([':id' => $report_id]);
        if (!$check_report->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            exit;
        }

        // Check if recipient exists and is valid role
        $check_user = $conn->prepare("SELECT id, role, first_name, last_name FROM users WHERE id = :id AND role IN ('tanod', 'secretary') AND is_active = 1");
        $check_user->execute([':id' => $routed_to]);
        $recipient = $check_user->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipient user']);
            exit;
        }

        // Begin transaction
        $conn->beginTransaction();

        // 1. Create routing log
        // Check if report_routing_logs table exists, if not create it (safe fallback)
        $conn->exec("CREATE TABLE IF NOT EXISTS report_routing_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            routed_by INT NOT NULL,
            routed_to INT NOT NULL,
            routing_notes TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id),
            FOREIGN KEY (routed_by) REFERENCES users(id),
            FOREIGN KEY (routed_to) REFERENCES users(id)
        )");

        $log_stmt = $conn->prepare("INSERT INTO report_routing_logs (report_id, routed_by, routed_to, routing_notes, status) VALUES (:report_id, :routed_by, :routed_to, :notes, 'pending')");
        $log_stmt->execute([
            ':report_id' => $report_id,
            ':routed_by' => $user_id,
            ':routed_to' => $routed_to,
            ':notes' => $notes
        ]);

        // 2. Update report status and assigned user
        // Determine status based on role
        $new_status = ($recipient['role'] === 'tanod') ? 'investigating' : 'processing';
        
        // Update report table - adding assigned_tanod if role is tanod
        if ($recipient['role'] === 'tanod') {
            $update_sql = "UPDATE reports SET status = :status, assigned_tanod = :assigned_to, updated_at = NOW() WHERE id = :id";
        } else {
            $update_sql = "UPDATE reports SET status = :status, updated_at = NOW() WHERE id = :id";
        }
        
        $update_stmt = $conn->prepare($update_sql);
        $params = [':status' => $new_status, ':id' => $report_id];
        if ($recipient['role'] === 'tanod') {
            $params[':assigned_to'] = $routed_to;
        }
        $update_stmt->execute($params);

        // 3. Log activity
        $activity_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, 'route_report', :description, :ip)");
        $activity_stmt->execute([
            ':user_id' => $user_id,
            ':description' => "Routed report #{$report_id} to {$recipient['first_name']} {$recipient['last_name']} ({$recipient['role']})",
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Report routed successfully']);

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error routing report: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>