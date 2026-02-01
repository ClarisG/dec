<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

if (!isset($_GET['notification_id'])) {
    echo json_encode(['error' => 'Notification ID required']);
    exit;
}

$notification_id = $_GET['notification_id'];
$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get notification details
    $stmt = $conn->prepare("
        SELECT n.*, r.report_number, r.title, r.description 
        FROM notifications n
        LEFT JOIN reports r ON n.related_id = r.id
        WHERE n.id = :id AND n.user_id = :user_id
    ");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($notification) {
        // Mark as read
        $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id");
        $update_stmt->execute([':id' => $notification_id]);
        
        echo json_encode([
            'success' => true,
            'notification' => $notification,
            'redirect_url' => '?module=my-reports'
        ]);
    } else {
        echo json_encode(['error' => 'Notification not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>