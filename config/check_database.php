<?php
// config/check_database.php
require_once 'database.php';

try {
    $conn = getDbConnection();
    
    // Check if reports table exists and has required columns
    $check_table = "SHOW TABLES LIKE 'reports'";
    $table_exists = $conn->query($check_table)->rowCount() > 0;
    
    if (!$table_exists) {
        echo "ERROR: Reports table doesn't exist. Please run database setup.";
        exit();
    }
    
    // Check for required columns
    $check_columns = "SHOW COLUMNS FROM reports";
    $columns = $conn->query($check_columns)->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'user_id', 'report_number', 'title', 'status', 'created_at'];
    $missing = array_diff($required_columns, $columns);
    
    if (!empty($missing)) {
        echo "ERROR: Missing columns in reports table: " . implode(', ', $missing);
        exit();
    }
    
    echo "Database connection and table structure OK.";
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}