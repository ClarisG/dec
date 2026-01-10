<?php
// config/functions.php

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
    return true;
}

// Check Tanod access
function checkTanodAccess() {
    checkLogin();
    
    if ($_SESSION['role'] !== 'tanod') {
        // Redirect based on role
        switch ($_SESSION['role']) {
            case 'citizen':
                header('Location: ' . BASE_URL . 'citizen_dashboard.php');
                break;
            case 'secretary':
                header('Location: ' . BASE_URL . 'sec/secretary_dashboard.php');
                break;
            case 'captain':
                header('Location: ' . BASE_URL . 'captain/dashboard.php');
                break;
            default:
                header('Location: ' . BASE_URL . 'login.php');
        }
        exit();
    }
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    
    // Remove whitespace
    $input = trim($input);
    // Remove backslashes
    $input = stripslashes($input);
    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Log activity
function logActivity($user_id, $action, $details, $conn) {
    $sql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $action, $details);
    return $stmt->execute();
}

// Get base URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $project = dirname(dirname($_SERVER['SCRIPT_NAME']));
    return $protocol . '://' . $host . $project . '/';
}
?>