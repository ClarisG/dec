<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$tanod_id = $_SESSION['user_id'];

// Check current status
$stmt = $pdo->prepare("SELECT * FROM tanod_duty_logs WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
$stmt->execute([$tanod_id]);
$current_duty = $stmt->fetch();

if ($current_duty) {
    // Clock out
    $stmt = $pdo->prepare("UPDATE tanod_duty_logs SET clock_out = NOW() WHERE id = ?");
    if ($stmt->execute([$current_duty['id']])) {
        // Update status table
        $stmt = $pdo->prepare("INSERT INTO tanod_status (user_id, status) VALUES (?, 'Off-Duty') ON DUPLICATE KEY UPDATE status = 'Off-Duty'");
        $stmt->execute([$tanod_id]);
        
        echo json_encode(['success' => true, 'status' => 'Off-Duty', 'message' => 'Successfully clocked out']);
    }
} else {
    // Clock in
    $stmt = $pdo->prepare("INSERT INTO tanod_duty_logs (user_id, clock_in) VALUES (?, NOW())");
    if ($stmt->execute([$tanod_id])) {
        // Update status table
        $stmt = $pdo->prepare("INSERT INTO tanod_status (user_id, status) VALUES (?, 'On-Duty') ON DUPLICATE KEY UPDATE status = 'On-Duty'");
        $stmt->execute([$tanod_id]);
        
        echo json_encode(['success' => true, 'status' => 'On-Duty', 'message' => 'Successfully clocked in']);
    }
}
?>