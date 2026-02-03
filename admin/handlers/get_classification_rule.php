<?php
// admin/handlers/get_classification_rule.php
session_start();
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM report_types WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rule) {
        echo json_encode($rule);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Rule not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>