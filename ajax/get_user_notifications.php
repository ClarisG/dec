<?php
// ajax/get_user_notifications.php - Unified notification retrieval for all user roles

session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $conn = getDbConnection();
    
    // Get unread notifications count
    $count_query = "SELECT COUNT(*) as unread_count FROM notifications 
                   WHERE user_id = :user_id AND is_read = 0";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bindParam(':user_id', $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $count_result['unread_count'] ?? 0;
    
    // Get latest notifications
    $notif_query = "SELECT n.*, r.report_number, r.title as report_title
                   FROM notifications n
                   LEFT JOIN reports r ON n.related_id = r.id
                   WHERE n.user_id = :user_id
                   ORDER BY n.created_at DESC
                   LIMIT :limit OFFSET :offset";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bindParam(':user_id', $user_id);
    $notif_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $notif_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $notif_stmt->execute();
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications,
        'total' => count($notifications)
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
