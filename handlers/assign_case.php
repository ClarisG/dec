<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$case_id = $_POST['case_id'] ?? 0;
$officer_id = $_POST['officer_id'] ?? 0;
$officer_type = $_POST['officer_type'] ?? '';

if (empty($case_id) || empty($officer_id) || empty($officer_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Determine which column to update based on officer type
    $update_column = '';
    $officer_role = '';
    
    switch ($officer_type) {
        case 'lupon':
        case 'lupon_member':
            $update_column = 'assigned_lupon';
            $officer_role = 'lupon';
            break;
        case 'lupon_chairman':
        case 'barangay_captain':
            $update_column = 'assigned_lupon_chairman';
            $officer_role = 'barangay_captain';
            break;
        case 'tanod':
            $update_column = 'assigned_tanod';
            $officer_role = 'tanod';
            break;
        default:
            throw new Exception('Invalid officer type');
    }
    
    // Update the report assignment
    $update_query = "UPDATE reports SET 
                    $update_column = :officer_id,
                    status = 'assigned',
                    updated_at = NOW()
                    WHERE id = :case_id";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':officer_id', $officer_id);
    $update_stmt->bindParam(':case_id', $case_id);
    $update_stmt->execute();
    
    // Add to status history
    $history_query = "INSERT INTO report_status_history 
                     (report_id, status, updated_by, notes, created_at)
                     VALUES (:report_id, 'assigned', :updated_by, :notes, NOW())";
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->bindParam(':report_id', $case_id);
    $history_stmt->bindValue(':updated_by', $_SESSION['user_id'] ?? 0);
    $notes = "Case assigned to $officer_role ID: $officer_id";
    $history_stmt->bindParam(':notes', $notes);
    $history_stmt->execute();
    
    // Create notification for the assigned officer
    $notification_query = "INSERT INTO notifications 
                          (user_id, title, message, type, related_id, related_type, created_at)
                          VALUES (:user_id, :title, :message, 'info', :related_id, 'report', NOW())";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bindParam(':user_id', $officer_id);
    $title = "New Case Assignment";
    $message = "You have been assigned to handle Case #$case_id";
    $notification_stmt->bindParam(':title', $title);
    $notification_stmt->bindParam(':message', $message);
    $notification_stmt->bindParam(':related_id', $case_id);
    $notification_stmt->execute();
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Case assigned successfully']);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>