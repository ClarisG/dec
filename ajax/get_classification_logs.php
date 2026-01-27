<?php
// sec/ajax/get_classification_logs.php
session_start();
require_once '../../config/database.php';

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$report_id = $_GET['report_id'] ?? 0;

try {
    $conn = getDbConnection();
    
    $query = "SELECT cl.*, u.first_name, u.last_name 
              FROM classification_logs cl
              LEFT JOIN users u ON cl.changed_by = u.id
              WHERE cl.report_id = :report_id
              ORDER BY cl.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':report_id' => $report_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($logs);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>