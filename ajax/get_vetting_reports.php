<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

checkTanodAccess();

$tanod_id = $_SESSION['user_id'];
$response = ['success' => false, 'data' => []];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'get_pending_count') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE vetting_status = 'Pending'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $response['success'] = true;
        $response['count'] = $result['count'];
    }
    
    if ($action == 'get_assigned_reports') {
        $stmt = $conn->prepare("
            SELECT r.report_id, r.report_title, r.incident_location, r.created_at,
                   u.full_name as reporter_name, r.vetting_status
            FROM reports r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE r.assigned_tanod_id = ? 
            AND r.vetting_status = 'In Review'
            ORDER BY r.created_at DESC
        ");
        $stmt->bind_param("i", $tanod_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reports = [];
        
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $reports;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>