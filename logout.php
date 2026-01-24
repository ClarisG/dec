<?php
// logout.php - FIXED
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Clear any additional cookies if needed
setcookie('remember_token', '', time()-3600, '/');

// Redirect to login page
header("Location: login.php");
exit();
?>