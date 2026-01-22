<?php
// handlers/load_cases.php
session_start();

// Set header to JSON
header('Content-Type: application/json');

// Include the direct database connection
require_once 'direct_db.php';

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    // Check if we have a database connection
    if (!$conn) {
        throw new Exception('Could not establish database connection');
    }
    
    // Build the query
    $where_clauses = [];
    $params = [];
    
    if (!empty($status) && $status !== 'all') {
        $where_clauses[] = "r.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category) && $category !== 'all') {
        $where_clauses[] = "r.category = ?";
        $params[] = $category;
    }
    
    if (!empty($from_date)) {
        $where_clauses[] = "DATE(r.created_at) >= ?";
        $params[] = $from_date;
    }
    
    if (!empty($to_date)) {
        $where_clauses[] = "DATE(r.created_at) <= ?";
        $params[] = $to_date;
    }
    
    // Default to pending cases
    if (empty($where_clauses)) {
        $where_clauses[] = "r.status = 'pending'";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM reports r $where_sql";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get cases with pagination
    $cases_sql = "SELECT r.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
                 (SELECT COUNT(*) FROM report_attachments ra WHERE ra.report_id = r.id) as attachment_count
                 FROM reports r 
                 LEFT JOIN users u ON r.user_id = u.id 
                 $where_sql
                 ORDER BY r.created_at ASC 
                 LIMIT ? OFFSET ?";
    
    // Add pagination parameters
    $params[] = $records_per_page;
    $params[] = $offset;
    
    $cases_stmt = $conn->prepare($cases_sql);
    $cases_stmt->execute($params);
    $cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all required fields exist
    foreach ($cases as &$case) {
        $case['complainant_name'] = $case['complainant_name'] ?? 'Unknown';
        $case['category'] = $case['category'] ?? 'Uncategorized';
        $case['attachment_count'] = $case['attachment_count'] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalRecords' => $total_records,
        'recordsPerPage' => $records_per_page
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading cases: ' . $e->getMessage(),
        'cases' => [],
        'currentPage' => 1,
        'totalPages' => 1,
        'totalRecords' => 0
    ]);
}
?>