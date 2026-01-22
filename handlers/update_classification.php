<?php
// handlers/update_classification.php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$report_id = $_POST['report_id'] ?? 0;
$classification = $_POST['classification'] ?? '';
$notes = $_POST['notes'] ?? '';

if (empty($report_id) || empty($classification)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get current AI classification
    $current_query = "SELECT ai_classification FROM reports WHERE id = :id";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bindParam(':id', $report_id);
    $current_stmt->execute();
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update classification
    $update_query = "UPDATE reports SET 
                    classification_override = :classification,
                    override_notes = :notes,
                    overridden_by = :user_id,
                    overridden_at = NOW(),
                    updated_at = NOW()
                    WHERE id = :id";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':classification', $classification);
    $update_stmt->bindParam(':notes', $notes);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $update_stmt->bindParam(':id', $report_id);
    $update_stmt->execute();
    
    // If classification changed from Barangay to Police or vice versa, update status
    $original_ai = $current['ai_classification'] ?? '';
    if ($original_ai !== $classification) {
        // Update citizen's status in their My Reports
        $user_query = "SELECT user_id FROM reports WHERE id = :id";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bindParam(':id', $report_id);
        $user_stmt->execute();
        $report_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report_user) {
            // Create notification for citizen
            $notification_query = "INSERT INTO notifications 
                                  (user_id, title, message, type, related_id, related_type, created_at)
                                  VALUES (:user_id, :title, :message, 'info', :related_id, 'report', NOW())";
            
            $notification_stmt = $conn->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $report_user['user_id']);
            
            $title = "Report Classification Updated";
            $message = "Your report classification has been updated to: " . $classification;
            
            $notification_stmt->bindParam(':title', $title);
            $notification_stmt->bindParam(':message', $message);
            $notification_stmt->bindParam(':related_id', $report_id);
            $notification_stmt->execute();
        }
        
        // Log the override
        $log_query = "INSERT INTO classification_logs 
                     (report_id, original_classification, new_classification, 
                      changed_by, notes, created_at)
                     VALUES (:report_id, :original, :new, :user_id, :notes, NOW())";
        
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bindParam(':report_id', $report_id);
        $log_stmt->bindParam(':original', $original_ai);
        $log_stmt->bindParam(':new', $classification);
        $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $log_stmt->bindParam(':notes', $notes);
        $log_stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Classification updated successfully']);
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>