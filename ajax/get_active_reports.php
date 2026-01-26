<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is authorized (secretary, admin, captain, lupon chairman)
$allowed_roles = ['secretary', 'admin', 'captain', 'lupon', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // Get active reports for document generation
    $query = "SELECT r.id, r.report_number, r.title, r.description, 
                     CONCAT(u.first_name, ' ', u.last_name) as complainant,
                     r.status, r.created_at
              FROM reports r
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.status IN ('pending', 'investigating', 'assigned', 'pending_field_verification')
              AND r.barangay = ?
              ORDER BY r.created_at DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['barangay']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($reports);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>