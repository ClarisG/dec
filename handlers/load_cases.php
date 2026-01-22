<?php
// Start output buffering to prevent any stray output
ob_start();

require_once '../config/database.php';
session_start();

// Check if user is authorized
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set header to JSON
header('Content-Type: application/json');

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Default to pending cases if no status filter
if (empty($status)) {
    $status = 'pending';
}

$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    // Initialize connection
    if (!isset($conn)) {
        throw new Exception('Database connection failed');
    }
    
    // Build the base query
    $query = "SELECT r.*, 
             CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
             u.contact_number,
             u.email,
             (SELECT COUNT(*) FROM report_attachments WHERE report_id = r.id) as attachment_count
             FROM reports r 
             LEFT JOIN users u ON r.user_id = u.id 
             WHERE 1=1";
    
    $count_query = "SELECT COUNT(*) as total FROM reports r WHERE 1=1";
    
    $params = [];
    
    // Add filters
    if (!empty($status) && $status !== 'all') {
        $query .= " AND r.status = :status";
        $count_query .= " AND r.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($category) && $category !== 'all') {
        $query .= " AND r.category = :category";
        $count_query .= " AND r.category = :category";
        $params[':category'] = $category;
    }
    
    if (!empty($from_date)) {
        $query .= " AND DATE(r.created_at) >= :from_date";
        $count_query .= " AND DATE(r.created_at) >= :from_date";
        $params[':from_date'] = $from_date;
    }
    
    if (!empty($to_date)) {
        $query .= " AND DATE(r.created_at) <= :to_date";
        $count_query .= " AND DATE(r.created_at) <= :to_date";
        $params[':to_date'] = $to_date;
    }
    
    // First, get total count
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add ordering and pagination to main query
    $query .= " ORDER BY r.created_at ASC LIMIT :limit OFFSET :offset";
    
    // Fetch cases
    $cases_stmt = $conn->prepare($query);
    
    // Bind all parameters including limit and offset
    foreach ($params as $key => $value) {
        $cases_stmt->bindValue($key, $value);
    }
    $cases_stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $cases_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $cases_stmt->execute();
    $cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean output buffer
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalRecords' => $total_records,
        'recordsPerPage' => $records_per_page
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // Clean output buffer
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // Clean output buffer
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

// End output buffering
ob_end_flush();
?>