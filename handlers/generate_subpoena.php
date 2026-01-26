<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authorization
$allowed_roles = ['secretary', 'admin', 'captain', 'lupon_chairman', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // Validate required fields
    $required = ['report_id', 'hearing_date', 'hearing_time', 'location', 'recipients', 'issuing_officer'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Get report details
    $stmt = $db->prepare("SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as complainant_name 
                          FROM reports r 
                          LEFT JOIN users u ON r.user_id = u.id 
                          WHERE r.id = ? AND r.barangay = ?");
    $stmt->execute([$_POST['report_id'], $_SESSION['barangay']]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }
    
    // Generate unique document number
    $document_number = 'SUBP-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Prepare data for PDF generation
    $document_data = [
        'document_number' => $document_number,
        'report_number' => $report['report_number'],
        'title' => $report['title'],
        'description' => $report['description'],
        'complainant' => $report['complainant_name'],
        'hearing_date' => $_POST['hearing_date'],
        'hearing_time' => $_POST['hearing_time'],
        'location' => $_POST['location'],
        'recipients' => $_POST['recipients'],
        'instructions' => $_POST['instructions'] ?? '',
        'issuing_officer' => $_POST['issuing_officer'],
        'generated_by' => $_SESSION['user_id'],
        'generated_at' => date('Y-m-d H:i:s'),
        'barangay' => $_SESSION['barangay']
    ];
    
    // Generate PDF
    $pdf_url = generatePDF('subpoena', $document_data);
    
    // Log the document generation
    $log_stmt = $db->prepare("INSERT INTO subpoena_documents 
                              (document_number, report_id, hearing_date, hearing_time, location, 
                               recipients, instructions, issuing_officer, generated_by, 
                               pdf_path, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $log_stmt->execute([
        $document_number,
        $_POST['report_id'],
        $_POST['hearing_date'],
        $_POST['hearing_time'],
        $_POST['location'],
        $_POST['recipients'],
        $_POST['instructions'] ?? '',
        $_POST['issuing_officer'],
        $_SESSION['user_id'],
        $pdf_url
    ]);
    
    // Add to activity logs
    $activity_stmt = $db->prepare("INSERT INTO activity_logs 
                                   (user_id, action, description, affected_id, affected_type, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
    $activity_stmt->execute([
        $_SESSION['user_id'],
        'document_generated',
        'Generated Subpoena/Summons for Report #' . $report['report_number'],
        $_POST['report_id'],
        'report'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Subpoena generated successfully',
        'document_number' => $document_number,
        'pdf_url' => $pdf_url
    ]);
    
} catch (Exception $e) {
    error_log('Subpoena generation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error generating document: ' . $e->getMessage()]);
}

function generatePDF($type, $data) {
    // Use TCPDF, Dompdf, or mPDF for PDF generation
    // For now, create a simple HTML file that can be printed
    $filename = 'uploads/documents/' . $data['document_number'] . '.html';
    $html = generateSubpoenaHTML($data);
    
    file_put_contents($filename, $html);
    
    // In production, convert HTML to PDF using a library
    // For simplicity, we'll return the HTML file path
    return $filename;
}

function generateSubpoenaHTML($data) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Subpoena/Summons - {$data['document_number']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .document-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            .document-number { font-size: 14px; color: #666; }
            .content { line-height: 1.6; }
            .section { margin-bottom: 20px; }
            .label { font-weight: bold; }
            .signature { margin-top: 50px; }
            .footer { margin-top: 50px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <div class='document-title'>SUBPOENA/SUMMONS</div>
            <div class='document-number'>Document No: {$data['document_number']}</div>
            <div>Republic of the Philippines</div>
            <div>Barangay {$data['barangay']}</div>
        </div>
        
        <div class='content'>
            <div class='section'>
                <div class='label'>TO:</div>
                <div>{$data['recipients']}</div>
            </div>
            
            <div class='section'>
                <div class='label'>CASE NO:</div>
                <div>{$data['report_number']} - {$data['title']}</div>
            </div>
            
            <div class='section'>
                <div>You are hereby commanded to appear before the Barangay on:</div>
                <div><strong>Date:</strong> {$data['hearing_date']}</div>
                <div><strong>Time:</strong> {$data['hearing_time']}</div>
                <div><strong>Venue:</strong> {$data['location']}</div>
            </div>
            
            <div class='section'>
                <div class='label'>PURPOSE:</div>
                <div>To hear and resolve the complaint regarding: {$data['description']}</div>
            </div>
            
            <div class='section'>
                <div class='label'>INSTRUCTIONS:</div>
                <div>{$data['instructions']}</div>
            </div>
            
            <div class='section'>
                <div>Failure to appear may result in appropriate legal action.</div>
            </div>
            
            <div class='signature'>
                <div>Issued by:</div>
                <br><br>
                <div><strong>{$data['issuing_officer']}</strong></div>
                <div>Barangay Official</div>
                <div>Date: " . date('F j, Y') . "</div>
            </div>
        </div>
        
        <div class='footer'>
            <div>Generated on: {$data['generated_at']}</div>
            <div>Barangay {$data['barangay']} Document Management System</div>
        </div>
    </body>
    </html>
    ";
}
?>