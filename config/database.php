<?php
// config/database.php

function getDbConnection() {
    // Use the actual database credentials directly
    $host = 'localhost';
    $dbname = 'leir_db';
    $username = 'root';
    $password = '';
    $port = '3307';
    
    try {
        $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", 
                       $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $conn;
    } catch(PDOException $e) {
        // Log the error but don't show database credentials
        error_log("Database Connection Error: " . $e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Keep for backward compatibility if needed
if (!isset($pdo)) {
    $pdo = getDbConnection();
}
?>