<?php
// config/database.php
require_once __DIR__ . '/config.php';

function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $conn = new PDO($dsn, DB_USER, DB_PASS);
        
        // Set PDO attributes
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $conn->setAttribute(PDO::ATTR_PERSISTENT, false);
        
        return $conn;
    } catch(PDOException $e) {
        // Log error
        error_log("[" . date('Y-m-d H:i:s') . "] Database Connection Error: " . $e->getMessage());
        
        // Show user-friendly message with debug info if enabled
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage() . 
                "<br>Host: " . DB_HOST . 
                "<br>Port: " . DB_PORT);
        } else {
            die("Database connection failed. Please contact administrator.");
        }
    }
}

// Create connection instance (optional)
try {
    $pdo = getDbConnection();
} catch(Exception $e) {
    // Handle initialization error
    die("Application initialization failed. Please try again later.");
}
?>