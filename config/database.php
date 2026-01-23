<?php
// config/database.php
require_once __DIR__ . '/config.php';

// Global connection variable
global $conn;

function getDbConnection() {
    global $conn;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $conn = new PDO($dsn, DB_USER, DB_PASS);
            
            // Set PDO attributes
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $conn->setAttribute(PDO::ATTR_PERSISTENT, false);
            
        } catch(PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] Database Connection Error: " . $e->getMessage());
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage() . 
                    "<br>Host: " . DB_HOST . 
                    "<br>Port: " . DB_PORT);
            } else {
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
    
    return $conn;
}

// Initialize connection
try {
    $conn = getDbConnection();
} catch(Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Application initialization failed: " . $e->getMessage());
    } else {
        die("Application initialization failed. Please try again later.");
    }
}

return $conn;
?>