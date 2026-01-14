<?php
// ajax/get_lupon_stats.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lupon') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get statistics
    $stats = [];
    
    // Assigned cases
    $assigned_query = "SELECT COUNT(*) as count FROM reports WHERE assigned_lupon = :lupon_id AND status IN ('pending', 'assigned', 'in_mediation')";
    $assigned_stmt = $conn->prepare($assigned_query);
    $assigned_stmt->execute([':lupon_id' => $user_id]);
    $stats['assigned_cases'] = $assigned_stmt->fetchColumn();
    
    // Success rate
    $total_query = "SELECT COUNT(*) as total FROM reports WHERE assigned_lupon = :lupon_id";
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->execute([':lupon_id' => $user_id]);
    $total = $total_stmt->fetchColumn();
    
    $successful_query = "SELECT COUNT(*) as successful FROM reports WHERE assigned_lupon = :lupon_id AND status IN ('closed', 'resolved')";
    $successful_stmt = $conn->prepare($successful_query);
    $successful_stmt->execute([':lupon_id' => $user_id]);
    $successful = $successful_stmt->fetchColumn();
    
    $stats['success_rate'] = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    $stats['upcoming_sessions'] = 0; // You can add actual logic here
    
    echo json_encode($stats);
    
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>