<?php
session_start();

// Check if user is logged in and is secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$case_id = $_POST['case_id'] ?? null;
$officer_id = $_POST['officer_id'] ?? null;
$officer_type = $_POST['officer_type'] ?? null;

if (!$case_id || !$officer_id || !$officer_type) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

// Database connection
try {
    $dsn = "mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4";
    $conn = new PDO($dsn, 'u514031374_leir', 'leirP@55w0rd');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Start transaction
    $conn->beginTransaction();
    
    // 1. Update the report status and assign officer
    $updateReport = $conn->prepare("
        UPDATE reports 
        SET status = 'assigned', 
            assigned_officer_id = :officer_id,
            assigned_officer_type = :officer_type,
            assigned_at = NOW()
        WHERE id = :case_id AND status = 'pending'
    ");
    
    $updateReport->execute([
        ':case_id' => $case_id,
        ':officer_id' => $officer_id,
        ':officer_type' => $officer_type
    ]);
    
    // 2. Create assignment record
    $createAssignment = $conn->prepare("
        INSERT INTO report_assignments 
        (report_id, officer_id, officer_type, assigned_by, assigned_at) 
        VALUES (:case_id, :officer_id, :officer_type, :secretary_id, NOW())
    ");
    
    $createAssignment->execute([
        ':case_id' => $case_id,
        ':officer_id' => $officer_id,
        ':officer_type' => $officer_type,
        ':secretary_id' => $_SESSION['user_id']
    ]);
    
    // 3. Create activity log
    $logActivity = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, action, details, ip_address, user_agent) 
        VALUES (:user_id, 'case_assigned', :details, :ip_address, :user_agent)
    ");
    
    $logActivity->execute([
        ':user_id' => $_SESSION['user_id'],
        ':details' => json_encode([
            'case_id' => $case_id,
            'officer_id' => $officer_id,
            'officer_type' => $officer_type
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'],
        ':user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Case assigned successfully']);
    
} catch (PDOException $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
