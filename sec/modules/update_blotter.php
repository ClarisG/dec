<?php
// sec/modules/update_blotter.php
session_start();
require_once '../../config/database.php';

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$case_id = $_POST['case_id'] ?? null;
$blotter_number = $_POST['blotter_number'] ?? null;

if (!$case_id || empty($blotter_number)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    $conn = getDbConnection();
    
    // Check if blotter number already exists for another case
    $check = $conn->prepare("SELECT id FROM reports WHERE blotter_number = :bn AND id != :id");
    $check->execute([':bn' => $blotter_number, ':id' => $case_id]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Blotter number already exists']);
        exit();
    }
    
    // Update
    $stmt = $conn->prepare("UPDATE reports SET blotter_number = :bn WHERE id = :id");
    $stmt->execute([':bn' => $blotter_number, ':id' => $case_id]);
    
    echo json_encode(['success' => true, 'message' => 'Blotter number updated']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
