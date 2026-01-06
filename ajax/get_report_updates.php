<?php
// ajax/get_report_updates.php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['updates' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get timestamp of last check (from client)
    $last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    // Get status updates since last check
    $query = "SELECT r.id as report_id, r.report_number, r.status, 
              h.created_at as timestamp, h.notes,
              CASE 
                WHEN r.status = 'pending' THEN 'Pending'
                WHEN r.status = 'submitted' THEN 'Submitted'
                WHEN r.status = 'for_verification' THEN 'Under Verification'
                WHEN r.status = 'for_mediation' THEN 'Under Mediation'
                WHEN r.status = 'referred' THEN 'Referred to Authorities'
                WHEN r.status = 'resolved' THEN 'Resolved'
                WHEN r.status = 'closed' THEN 'Closed'
                ELSE r.status
              END as status_text,
              CASE 
                WHEN EXISTS (
                    SELECT 1 FROM user_notifications 
                    WHERE user_id = :user_id 
                    AND related_id = r.id 
                    AND related_type = 'report_status'
                    AND is_read = 1
                ) THEN 1 ELSE 0 
              END as is_read
              FROM reports r
              LEFT JOIN report_status_history h ON r.id = h.report_id
              WHERE r.user_id = :user_id
              AND h.created_at > :last_check
              AND h.id IN (
                  SELECT MAX(id) FROM report_status_history 
                  WHERE report_id = r.id 
                  GROUP BY report_id
              )
              ORDER BY h.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':last_check' => $last_check
    ]);
    
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark new updates as read in notifications
    foreach ($updates as $update) {
        if (!$update['is_read']) {
            $notif_query = "INSERT INTO user_notifications 
                           (user_id, title, message, type, related_id, related_type) 
                           VALUES (:user_id, 'Report Status Updated', 
                                   CONCAT('Report ', :report_number, ' is now ', :status_text),
                                   'info', :report_id, 'report_status')";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->execute([
                ':user_id' => $user_id,
                ':report_number' => $update['report_number'],
                ':status_text' => $update['status_text'],
                ':report_id' => $update['report_id']
            ]);
        }
    }
    
    echo json_encode(['updates' => $updates]);
    
} catch (PDOException $e) {
    error_log("Get Report Updates Error: " . $e->getMessage());
    echo json_encode(['updates' => []]);
}
?>