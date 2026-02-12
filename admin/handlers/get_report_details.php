<?php
// admin/handlers/get_report_details.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Report ID is required']);
    exit;
}

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.first_name as reporter_first, 
               u.last_name as reporter_last, 
               u.contact_number as reporter_contact,
               rt.type_name as incident_type
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN report_types rt ON r.report_type_id = rt.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo json_encode($report);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>