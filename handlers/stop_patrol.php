<?php
// handlers/stop_patrol.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    
    // Get current duty session
    $duty_query = "SELECT id FROM tanod_duty_logs 
                   WHERE user_id = :user_id 
                   AND DATE(clock_in) = CURDATE() 
                   AND clock_out IS NULL 
                   LIMIT 1";
    $duty_stmt = $conn->prepare($duty_query);
    $duty_stmt->execute(['user_id' => $user_id]);
    $duty = $duty_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duty) {
        // End duty session
        $update_query = "UPDATE tanod_duty_logs 
                        SET clock_out = NOW(), 
                            total_distance = :distance,
                            duration = TIMESTAMPDIFF(MINUTE, clock_in, NOW())
                        WHERE id = :duty_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([
            'distance' => $data['total_distance'] ?? 0,
            'duty_id' => $duty['id']
        ]);
        
        // Save route data if available
        if (isset($data['route_data'])) {
            $route_query = "INSERT INTO tanod_routes 
                           (duty_id, route_data, created_at) 
                           VALUES (:duty_id, :route_data, NOW())";
            $route_stmt = $conn->prepare($route_query);
            $route_stmt->execute([
                'duty_id' => $duty['id'],
                'route_data' => json_encode($data['route_data'])
            ]);
        }
        
        // Update status
        $status_query = "UPDATE tanod_status 
                        SET status = 'Off-Duty', last_updated = NOW() 
                        WHERE user_id = :user_id";
        $status_stmt = $conn->prepare($status_query);
        $status_stmt->execute(['user_id' => $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Patrol ended']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active duty session']);
    }
}