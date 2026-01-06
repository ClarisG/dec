<?php
// config/constants.php

// Only define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    // Get the protocol (http or https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    
    // Get the host
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the base path
    $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    
    // Construct the base URL
    $base_url = $protocol . "://" . $host . $base_path;
    
    // Remove any trailing slashes
    $base_url = rtrim($base_url, '/');
    
    // Define constant
    define('BASE_URL', $base_url);
}

// Define logo paths if not already defined
if (!defined('LOGO_PATH')) {
    define('LOGO_PATH', __DIR__ . '/../images/10213.png');
}
if (!defined('LOGO_URL') && defined('BASE_URL')) {
    define('LOGO_URL', BASE_URL . 'dec/images/10213.png');
}
define('SITE_NAME', 'Barangay Reporting System');
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp4', 'avi', 'mov']);
?>