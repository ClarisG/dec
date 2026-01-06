<?php
// ajax/load_module.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

if (isset($_GET['module'])) {
    $module = $_GET['module'];
    $allowedModules = ['new_report', 'my_reports', 'announcements', 'profile'];
    
    if (in_array($module, $allowedModules)) {
        $moduleFile = "../modules/citizen_$module.php";
        
        if (file_exists($moduleFile)) {
            include $moduleFile;
        } else {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">';
            echo '<p class="text-red-700">Module file not found</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">';
        echo '<p class="text-red-700">Invalid module requested</p>';
        echo '</div>';
    }
} else {
    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">';
    echo '<p class="text-red-700">No module specified</p>';
    echo '</div>';
}