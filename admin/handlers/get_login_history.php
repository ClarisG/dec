<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([]);
    exit;
}

try {
    $conn = getDbConnection();
    
    $query = "SELECT * FROM login_history WHERE user_id = :user_id ORDER BY login_time DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($history);
    
} catch(PDOException $e) {
    echo json_encode([]);
}