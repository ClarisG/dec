<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Simple query without filters for testing
    $query = "SELECT r.*, 
             CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
             (SELECT COUNT(*) FROM report_attachments WHERE report_id = r.id) as attachment_count
             FROM reports r 
             LEFT JOIN users u ON r.user_id = u.id 
             ORDER BY r.created_at ASC 
             LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total
    $count_query = "SELECT COUNT(*) as total FROM reports";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'currentPage' => 1,
        'totalPages' => 1,
        'totalRecords' => $total_records,
        'recordsPerPage' => 10
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>