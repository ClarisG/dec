<?php
// config/session.php

// Start session first
session_start();

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Check if user is logged in (basic check)
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Session security can be set via .htaccess or php.ini
// For XAMPP, you can add these to php.ini:
// session.cookie_httponly = 1
// session.cookie_secure = 0 (for development)
// session.use_strict_mode = 1
?>