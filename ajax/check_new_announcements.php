<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];
$barangay = $_SESSION['barangay'] ?? '';

try {
    $conn = getDbConnection();
    
    // Count unread notifications
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = :user_id AND is_read = 0
    ");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['count' => $result['count'] ?? 0]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>