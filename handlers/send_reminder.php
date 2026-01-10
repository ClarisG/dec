<?php
// handlers/send_reminder.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Hearing ID required']);
    exit;
}

$hearing_id = (int)$_GET['id'];

try {
    $conn = getDbConnection();
    
    // Get hearing details
    $query = "SELECT ch.*, r.report_number, r.title, 
                     u.email as complainant_email, u.contact_number as complainant_phone
              FROM captain_hearings ch
              JOIN reports r ON ch.report_id = r.id
              LEFT JOIN users u ON r.user_id = u.id
              WHERE ch.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$hearing_id]);
    $hearing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hearing) {
        echo json_encode(['success' => false, 'message' => 'Hearing not found']);
        exit;
    }
    
    // In a real system, you would:
    // 1. Send email reminders
    // 2. Send SMS notifications
    // 3. Update the hearing record
    
    // Simulate sending reminders
    $update_query = "UPDATE captain_hearings SET 
                     reminders_sent = TRUE, 
                     last_reminder_sent = NOW() 
                     WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute([$hearing_id]);
    
    // Log activity
    $activity_query = "INSERT INTO activity_logs (user_id, action, description, affected_id, affected_type, ip_address) 
                      VALUES (?, 'reminder_sent', 'Sent hearing reminders', ?, 'hearing', ?)";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->execute([$_SESSION['user_id'], $hearing_id, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reminders sent successfully',
        'recipients' => [
            'complainant' => $hearing['complainant_email']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}