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

        if ($report['needs_verification'] == 0 && !isset($_POST['status_value'])) {
            echo json_encode(['success' => false, 'message' => 'Report is already verified']);
            exit;
        }

        // Determine new status based on input
        $new_verification_status = 0; // Default to verified
        if (isset($_POST['status_value'])) {
            $new_verification_status = (int)$_POST['status_value']; // 0 for Verified, 1 for Pending
        }

        // Begin transaction
        $conn->beginTransaction();

        // Update report status
        $status_text = ($new_verification_status == 0) ? 'verified' : 'pending';
        $update_stmt = $conn->prepare("UPDATE reports SET needs_verification = :needs_verification, status = :status, updated_at = NOW() WHERE id = :id");
        $update_stmt->execute([
            ':needs_verification' => $new_verification_status,
            ':status' => $status_text,
            ':id' => $report_id
        ]);

        // Log activity
        $action_desc = ($new_verification_status == 0) ? "Verified report #{$report_id}" : "Marked report #{$report_id} as pending verification";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, 'verify_report', :description, :ip)");
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':description' => $action_desc,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Report status updated successfully']);

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