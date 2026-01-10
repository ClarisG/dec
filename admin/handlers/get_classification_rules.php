<?php
// admin/handlers/get_classification_rules.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDbConnection();
    
    $query = "SELECT * FROM report_types ORDER BY category, type_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($rules);
    
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}