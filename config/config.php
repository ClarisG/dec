<?php
// config/config.php - Updated configuration
$host = 'localhost';
$dbname = 'leir_db';
$username = 'root';
$password = '';
$port = '3307';

if (session_status() === PHP_SESSION_NONE) {
    session_cache_limiter('private');
    session_cache_expire(30);
}

// Debug mode
define('DEBUG_MODE', false);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>