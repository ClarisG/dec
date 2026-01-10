<?php
// admin/handlers/get_tanod_locations.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDbConnection();
    
    $query = "SELECT u.id, u.first_name, u.last_name, 
                     ts.status, td.location_lat, td.location_lng,
                     td.last_updated
              FROM users u
              LEFT JOIN tanod_status ts ON u.id = ts.user_id
              LEFT JOIN tanod_duty_logs td ON u.id = td.user_id 
                AND td.id = (SELECT MAX(id) FROM tanod_duty_logs WHERE user_id = u.id)
              WHERE u.role = 'tanod' AND u.is_active = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $tanods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'tanods' => $tanods]);
    
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}