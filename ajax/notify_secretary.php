<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

checkTanodAccess();

$tanod_id = $_SESSION['user_id'];
$report_id = intval($_POST['report_id']);
$recommendation = sanitizeInput($_POST['recommendation']);
$notes = sanitizeInput($_POST['notes']);

// Get tanod name
$tanod_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$tanod_stmt->bind_param("i", $tanod_id);
$tanod_stmt->execute();
$tanod_name = $tanod_stmt->get_result()->fetch_assoc()['full_name'];

// Create notification for secretary
$notification = "Tanod $tanod_name has submitted a vetting report for case #$report_id with recommendation: $recommendation";
$notification_type = 'vetting_report';

$stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message, reference_id) 
                        SELECT user_id, ?, ?, ? FROM users WHERE role = 'secretary' AND is_active = 1");
$stmt->bind_param("ssi", $notification_type, $notification, $report_id);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Secretary notified']);
?>
