<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    // Build the base query
    $query = "SELECT r.*, 
             CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
             (SELECT COUNT(*) FROM report_attachments WHERE report_id = r.id) as attachment_count
             FROM reports r 
             LEFT JOIN users u ON r.user_id = u.id 
             WHERE 1=1";
    
    $count_query = "SELECT COUNT(*) as total FROM reports r WHERE 1=1";
    
    $params = [];
    $types = [];
    
    // Add filters
    if (!empty($status)) {
        $query .= " AND r.status = ?";
        $count_query .= " AND r.status = ?";
        $params[] = $status;
        $types[] = 's';
    }
    
    if (!empty($category)) {
        $query .= " AND r.category = ?";
        $count_query .= " AND r.category = ?";
        $params[] = $category;
        $types[] = 's';
    }
    
    if (!empty($from_date)) {
        $query .= " AND DATE(r.created_at) >= ?";
        $count_query .= " AND DATE(r.created_at) >= ?";
        $params[] = $from_date;
        $types[] = 's';
    }
    
    if (!empty($to_date)) {
        $query .= " AND DATE(r.created_at) <= ?";
        $count_query .= " AND DATE(r.created_at) <= ?";
        $params[] = $to_date;
        $types[] = 's';
    }
    
    // First, get total count
    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->execute($params);
    } else {
        $count_stmt->execute();
    }
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add ordering and pagination to main query
    $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $records_per_page;
    $params[] = $offset;
    $types[] = 'i';
    $types[] = 'i';
    
    // Fetch cases
    $cases_stmt = $conn->prepare($query);
    $cases_stmt->execute($params);
    $cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalRecords' => $total_records,
        'recordsPerPage' => $records_per_page
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading cases: ' . $e->getMessage()
    ]);
}
?>