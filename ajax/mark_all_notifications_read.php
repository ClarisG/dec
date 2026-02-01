<?php
// ajax/mark_all_notifications_read.php - Mark all notifications as read for current user

session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Update all unread notifications as read
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
