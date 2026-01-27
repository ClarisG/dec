<?php
// clear_temp_session.php - Clear temporary session data
require_once __DIR__ . '/session.php';

// Clear all temporary session variables
unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_role']);
unset($_SESSION['temp_username']);
unset($_SESSION['temp_name']);
unset($_SESSION['otp_user_id']);
unset($_SESSION['otp_email']);
unset($_SESSION['otp_verified']);
unset($_SESSION['reset_user_id']);

echo "Session cleared";
?>