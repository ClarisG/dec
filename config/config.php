<?php
// config/config.php - Updated configuration
    $host = '153.92.15.81';
    $dbname = 'lu514031374_leir';
    $username = 'lu514031374_leir';
    $password = 'leirP@55w0rd';
    $port = '3306';

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