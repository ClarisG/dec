<?php
// reports_list.php
session_start();
require_once 'config/database.php';

$db = Database::getInstance();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT 
    r.id,
    r.report_number,
    r.title,
    r.description,
    r.status,
    r.priority,
    r.created_at,
    rt.type_name,
    u.first_name AS assigned_first_name,
    u.last_name AS assigned_last_name
FROM reports r
LEFT JOIN report_types rt ON r.report_type_id = rt.id
LEFT JOIN users u ON r.assigned_to = u.id
WHERE r.user_id = ?";

$params = [$user_id];

if ($status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.report_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY r.created_at DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>