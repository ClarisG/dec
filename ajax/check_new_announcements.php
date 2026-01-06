<?php
// ajax/check_new_announcements.php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['barangay'])) {
    echo json_encode(['new_count' => 0, 'has_emergency' => false]);
    exit;
}

$barangay = $_SESSION['barangay'];
$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

try {
    $conn = getDbConnection();
    
    // Count new announcements
    $query = "SELECT COUNT(*) as new_count,
              SUM(CASE WHEN is_emergency = 1 THEN 1 ELSE 0 END) as emergency_count
              FROM announcements 
              WHERE (target_role = 'citizen' OR target_role = 'all')
              AND (barangay = :barangay OR barangay = 'all')
              AND is_active = 1
              AND created_at > :last_check";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':barangay' => $barangay,
        ':last_check' => $last_check
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'new_count' => (int)$result['new_count'],
        'has_emergency' => (int)$result['emergency_count'] > 0
    ]);
    
} catch (PDOException $e) {
    error_log("Check New Announcements Error: " . $e->getMessage());
    echo json_encode(['new_count' => 0, 'has_emergency' => false]);
}
?>