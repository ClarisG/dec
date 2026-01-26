<?php
// handlers/start_patrol.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Check if already on duty
    $check_query = "SELECT id FROM tanod_duty_logs 
                    WHERE user_id = :user_id 
                    AND DATE(clock_in) = CURDATE() 
                    AND clock_out IS NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute(['user_id' => $user_id]);
    
    if ($check_stmt->rowCount() === 0) {
        // Start new duty session
        $insert_query = "INSERT INTO tanod_duty_logs 
                         (user_id, clock_in, created_at) 
                         VALUES (:user_id, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->execute(['user_id' => $user_id]);
        
        // Update status
        $status_query = "INSERT INTO tanod_status 
                         (user_id, status, last_updated) 
                         VALUES (:user_id, 'On-Duty', NOW())
                         ON DUPLICATE KEY UPDATE 
                         status = 'On-Duty', last_updated = NOW()";
        $status_stmt = $conn->prepare($status_query);
        $status_stmt->execute(['user_id' => $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Patrol started']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Already on duty']);
    }
}