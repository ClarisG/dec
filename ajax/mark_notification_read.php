<?php
// ajax/mark_notification_read.php - Mark a single notification as read

session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0;

if ($notification_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Update notification as read
    $update_query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':id', $notification_id);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
