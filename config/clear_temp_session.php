<?php
// clear_temp_session.php
session_start();
unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_role']);
unset($_SESSION['temp_username']);
unset($_SESSION['temp_name']);
unset($_SESSION['temp_master_code']);
header("Location: login.php");
exit;
?>