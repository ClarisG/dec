<?php
session_start();
unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_role']);
echo 'OK';
?>