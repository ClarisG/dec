<?php
// config/config.php - Updated configuration
define('DB_HOST', '153.92.15.81');
define('DB_NAME', 'u514031374_leir');
define('DB_USER', 'u514031374_leir');
define('DB_PASS', 'leirP@55w0rd');
define('DB_PORT', '3306');

// Debug mode
define('DEBUG_MODE', true);  // Set to true for debugging

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');
?>