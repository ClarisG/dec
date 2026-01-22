<?php
// handlers/load_cases.php

// Start session and include database
session_start();

// First, try to include database.php with error handling
$database_file = __DIR__ . '/../config/database.php';

if (!file_exists($database_file)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration file not found at: ' . $database_file
    ]);
    exit;
}

// Include database configuration
require_once $database_file;

// Set header to JSON
header('Content-Type: application/json');

// Check if database connection is established
if (!isset($conn)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not established. Please run setup_database.php first.'
    ]);
    exit;
}

// Check if user is logged in (optional, depending on your requirements)
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    // Build query with filters
    $where_clauses = [];
    $params = [];
    
    if (!empty($status)) {
        $where_clauses[] = "r.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
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
    
    // Default: show pending cases
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
                 u.contact_number,
                 u.email,
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
    
    echo json_encode([
        'success' => true,
        'cases' => $cases,
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalRecords' => $total_records,
        'recordsPerPage' => $records_per_page
    ]);
    
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Database error in load_cases.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please check your database setup.',
        'debug_info' => (ini_get('display_errors') == '1') ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>