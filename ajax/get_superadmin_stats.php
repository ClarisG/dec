<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Get stats
$stats = [
    'total_users' => getTotalUsers(),
    'active_cases' => getActiveCases(),
    'system_health' => getSystemHealth(),
    'last_backup' => getLastBackup()
];

echo json_encode($stats);

function getTotalUsers() {
    global $conn;
    $query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
    $result = $conn->query($query);
    return $result->fetch_assoc()['count'];
}