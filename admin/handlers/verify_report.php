<?php
// admin/handlers/verify_report.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission (admin or captain)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'captain', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_report'])) {
    $report_id = $_POST['report_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$report_id) {
        echo json_encode(['success' => false, 'message' => 'Report ID is required']);
        exit;
    }

    try {
        $conn = getDbConnection();
        
        // Check if report exists and current status
        $check_stmt = $conn->prepare("SELECT id, needs_verification, status FROM reports WHERE id = :id");
        $check_stmt->execute([':id' => $report_id]);
        $report = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            exit;
        }

        if ($report['needs_verification'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Report is already verified']);
            exit;
        }

        // Begin transaction
        $conn->beginTransaction();

        // Update report status
        $update_stmt = $conn->prepare("UPDATE reports SET needs_verification = 0, status = 'verified', updated_at = NOW() WHERE id = :id");
        $update_stmt->execute([':id' => $report_id]);

        // Log activity
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, 'verify_report', :description, :ip)");
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':description' => "Verified report #{$report_id}",
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Report verified successfully']);

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error verifying report: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>