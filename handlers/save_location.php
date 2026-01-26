<?php
// handlers/save_location.php
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
        // Save location log
        $insert_query = "INSERT INTO tanod_location_logs 
                         (user_id, duty_log_id, latitude, longitude, accuracy, created_at) 
                         VALUES (:user_id, :duty_id, :lat, :lng, :accuracy, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->execute([
            'user_id' => $user_id,
            'duty_id' => $duty['id'],
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? 10
        ]);
        
        // Update current location in duty logs
        $update_query = "UPDATE tanod_duty_logs 
                        SET location_lat = :lat, location_lng = :lng, last_location_update = NOW() 
                        WHERE id = :duty_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'duty_id' => $duty['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Location saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active duty session']);
    }
}