<?php
// config/functions.php

// Function to check if user is logged in and is citizen
function requireCitizenAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
        header("Location: ../login.php");
        exit;
    }
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to format date
function formatDate($date, $format = 'F d, Y h:i A') {
    return date($format, strtotime($date));
}

// Function to get user's initials
function getUserInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

// Function to check file extension
function isAllowedFile($filename, $allowedExtensions) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}