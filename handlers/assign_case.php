<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$case_id = $_POST['case_id'] ?? 0;
$action = $_POST['action'] ?? 'assign';
$officer_id = $_POST['officer_id'] ?? 0;
$officer_type = $_POST['officer_type'] ?? '';

if (empty($case_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing case ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    if ($action === 'keep_pending') {
        // Just add a note to status history that it was reviewed but kept pending
        $history_query = "INSERT INTO report_status_history 
                         (report_id, status, updated_by, notes, created_at)
                         VALUES (:report_id, 'pending', :updated_by, :notes, NOW())";
        $history_stmt = $conn->prepare($history_query);
        $history_stmt->bindParam(':report_id', $case_id);
        $history_stmt->bindParam(':updated_by', $_SESSION['user_id']);
        $notes = "Case reviewed but kept pending for further assessment";
        $history_stmt->bindParam(':notes', $notes);
        $history_stmt->execute();
        
        // Update the report's updated_at timestamp
        $update_query = "UPDATE reports SET updated_at = NOW() WHERE id = :case_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':case_id', $case_id);
        $update_stmt->execute();
        
    } else {
        // Validate assignment parameters
        if (empty($officer_id) || empty($officer_type)) {
            throw new Exception('Missing officer parameters');
        }
        
        // Update report based on officer type
        if ($officer_type === 'lupon') {
            $update_query = "UPDATE reports SET 
                            assigned_lupon = :officer_id,
                            status = 'assigned',
                            updated_at = NOW()
                            WHERE id = :case_id";
        } elseif ($officer_type === 'tanod') {
            $update_query = "UPDATE reports SET 
                            assigned_tanod = :officer_id,
                            status = 'assigned',
                            updated_at = NOW()
                            WHERE id = :case_id";
        } else {
            throw new Exception('Invalid officer type');
        }
        
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
        $history_stmt->bindParam(':updated_by', $_SESSION['user_id']);
        $notes = "Case assigned to officer ID: $officer_id";
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
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Case assignment updated successfully']);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}