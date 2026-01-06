<?php
// config/base_url.php
if (!defined('BASE_URL')) {
    // Get the protocol (http or https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    
    // Get the host
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the base path (remove the filename if present)
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_path = dirname($script_name);
    
    // Construct the base URL
    $base_url = $protocol . "://" . $host . $base_path;
    
    // Remove any trailing slashes
    $base_url = rtrim($base_url, '/');
    
    // Define constant
    define('BASE_URL', $base_url);
}
?>