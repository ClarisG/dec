<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$report_id = $data['report_id'] ?? '';
$pin_code = $data['pin_code'] ?? '';
$action = $data['action'] ?? 'evidence';

try {
    $conn = getDbConnection();
    
    // Get the encrypted PIN from the report
    $query = "SELECT pin_code FROM reports WHERE id = :report_id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':report_id', $report_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }
    
    // Verify PIN (assuming PIN is stored as plain text for demo)
    // In production, use password_verify() for hashed PINs
    if ($report['pin_code'] === $pin_code) {
        // Store in session for subsequent requests
        $_SESSION['decrypted_pin_' . $report_id] = $pin_code;
        
        echo json_encode([
            'success' => true,
            'exportFormat' => isset($data['exportFormat']) ? $data['exportFormat'] : null
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>