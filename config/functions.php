<?php
// config/functions.php

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
    return true;
}

// Check Tanod access
function checkTanodAccess() {
    checkLogin();
    
    if ($_SESSION['role'] !== 'tanod') {
        // Redirect based on role
        switch ($_SESSION['role']) {
            case 'citizen':
                header('Location: ' . BASE_URL . 'citizen_dashboard.php');
                break;
            case 'secretary':
                header('Location: ' . BASE_URL . 'sec/secretary_dashboard.php');
                break;
            case 'captain':
                header('Location: ' . BASE_URL . 'captain/dashboard.php');
                break;
            default:
                header('Location: ' . BASE_URL . 'login.php');
        }
        exit();
    }
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    
    // Remove whitespace
    $input = trim($input);
    // Remove backslashes
    $input = stripslashes($input);
    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Log activity
function logActivity($user_id, $action, $details, $conn) {
    $sql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $action, $details);
    return $stmt->execute();
}

// Get base URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $project = dirname(dirname($_SERVER['SCRIPT_NAME']));
    return $protocol . '://' . $host . $project . '/';
}

// Try to load TCPDF from various possible locations
function loadTCPDF() {
    // 1. Try Composer autoload (vendor folder)
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
        if (class_exists('TCPDF')) {
            return true;
        }
    }
    
    // 2. Try manual TCPDF installation in tcpdf folder
    $manualTCPDF = __DIR__ . '/../tcpdf/tcpdf.php';
    if (file_exists($manualTCPDF)) {
        require_once $manualTCPDF;
        if (class_exists('TCPDF')) {
            // Define constants if not defined
            if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
            if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
            if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');
            return true;
        }
    }
    
    // 3. Try TCPDF in includes folder
    $includesTCPDF = __DIR__ . '/../includes/tcpdf/tcpdf.php';
    if (file_exists($includesTCPDF)) {
        require_once $includesTCPDF;
        if (class_exists('TCPDF')) {
            if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
            if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
            if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');
            return true;
        }
    }
    
    // TCPDF not found
    error_log('TCPDF library not found. Please install via: composer require tecnickcom/tcpdf');
    return false;
}

// Generate PDF document for various barangay documents
function generateBarangayDocument($type, $data) {
    // Load TCPDF library
    if (!loadTCPDF()) {
        throw new Exception('PDF generation failed: TCPDF library not found');
    }
    
    // Create PDF instance
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Barangay Document System');
    $pdf->SetAuthor('Barangay ' . ($data['barangay'] ?? 'Unknown'));
    $pdf->SetTitle($data['document_title'] ?? 'Barangay Document');
    $pdf->SetSubject($type);
    
    // Set default header and footer
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Add a page
    $pdf->AddPage();
    
    // Generate content based on type
    $html = generateDocumentHTML($type, $data);
    
    // Output HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Ensure uploads directory exists
    $uploadsDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documents/';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }
    
    // Generate filename
    $filename = 'uploads/documents/' . ($data['document_number'] ?? 'document_' . time()) . '.pdf';
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filename;
    
    // Save PDF file
    $pdf->Output($fullPath, 'F');
    
    return $filename;
}

function generateDocumentHTML($type, $data) {
    $barangay_name = $data['barangay'] ?? 'Barangay';
    $today = date('F j, Y');
    
    switch($type) {
        case 'subpoena':
            return "
                <h1 style='text-align: center;'>SUBPOENA/SUMMONS</h1>
                <p style='text-align: center;'>Republic of the Philippines<br>Province of ________<br>Municipality of ________<br>Barangay $barangay_name</p>
                <hr>
                <p><strong>Document No:</strong> " . ($data['document_number'] ?? 'N/A') . "</p>
                <p><strong>To:</strong> " . ($data['recipients'] ?? 'Parties Concerned') . "</p>
                <p><strong>Case No:</strong> " . ($data['report_number'] ?? 'N/A') . "</p>
                <p><strong>Hearing Date:</strong> " . ($data['hearing_date'] ?? 'Date') . " at " . ($data['hearing_time'] ?? 'Time') . "</p>
                <p><strong>Venue:</strong> " . ($data['location'] ?? 'Barangay Hall') . "</p>
                <p><strong>Purpose:</strong> " . ($data['description'] ?? 'Hearing') . "</p>
                <p>" . ($data['instructions'] ?? 'You are hereby summoned to appear at the said date, time, and venue.') . "</p>
                <br><br>
                <p style='margin-top: 50px;'>
                    <strong>Issued by:</strong><br>
                    " . ($data['issuing_officer'] ?? 'Barangay Official') . "<br>
                    Barangay Official<br>
                    Date: $today
                </p>
                <br><br>
                <p><strong>Received by:</strong><br><br>
                    ________________________<br>
                    Signature over Printed Name<br>
                    Date Received: ________________
                </p>
            ";
            
        case 'notice_of_hearing':
            return "
                <h1 style='text-align: center;'>NOTICE OF HEARING</h1>
                <p style='text-align: center;'>Republic of the Philippines<br>Province of ________<br>Municipality of ________<br>Barangay $barangay_name</p>
                <hr>
                <p><strong>To the Parties Concerned:</strong></p>
                <p>You are hereby notified that a hearing has been scheduled for:</p>
                <p>
                    <strong>Date:</strong> " . ($data['hearing_date'] ?? 'Date') . "<br>
                    <strong>Time:</strong> " . ($data['hearing_time'] ?? 'Time') . "<br>
                    <strong>Venue:</strong> " . ($data['venue'] ?? 'Barangay Hall') . "
                </p>
                <p><strong>Case/Subject:</strong> " . ($data['case_subject'] ?? 'Case') . "</p>
                <p><strong>Purpose:</strong> " . ($data['purpose'] ?? 'Hearing') . "</p>
                <p><strong>Required to Bring:</strong> " . ($data['requirements'] ?? 'Identification and relevant documents') . "</p>
                <p><strong>Note:</strong> Failure to appear may result in resolution of the case based on available evidence.</p>
                <br><br>
                <p style='margin-top: 50px;'>
                    <strong>Issued by:</strong><br>
                    " . ($data['issued_by'] ?? 'Barangay Captain') . "<br>
                    Barangay Captain<br>
                    Date: $today
                </p>
            ";
            
        case 'certificate_to_file':
            return "
                <h1 style='text-align: center;'>CERTIFICATE TO FILE ACTION</h1>
                <p style='text-align: center;'>Republic of the Philippines<br>Province of ________<br>Municipality of ________<br>Barangay $barangay_name</p>
                <hr>
                <p>This is to certify that:</p>
                <p style='text-align: center;'><strong>" . ($data['complainant'] ?? 'Complainant') . "</strong></p>
                <p>has filed a complaint against:</p>
                <p style='text-align: center;'><strong>" . ($data['respondent'] ?? 'Respondent') . "</strong></p>
                <p>for:</p>
                <p style='text-align: center;'><strong>" . ($data['complaint_subject'] ?? 'Subject of Complaint') . "</strong></p>
                <p>and that the barangay has conducted conciliation proceedings but no settlement was reached.</p>
                <br>
                <p>This certificate is issued for whatever legal purpose it may serve.</p>
                <br><br>
                <p style='margin-top: 50px;'>
                    <strong>Issued by:</strong><br>
                    " . ($data['issued_by'] ?? 'Barangay Captain') . "<br>
                    Barangay Captain<br>
                    Date: $today
                </p>
            ";
            
        default:
            return "
                <h1 style='text-align: center;'>BARANGAY DOCUMENT</h1>
                <p style='text-align: center;'>Republic of the Philippines<br>Barangay $barangay_name</p>
                <hr>
                <p><strong>Document Type:</strong> " . ucwords(str_replace('_', ' ', $type)) . "</p>
                <p><strong>Date Issued:</strong> $today</p>
                <p><strong>Details:</strong><br>" . json_encode($data, JSON_PRETTY_PRINT) . "</p>
            ";
    }
}

/**
 * Send document notification to involved parties
 */
function sendDocumentNotification($document_type, $document_id, $recipient_ids, $sender_id) {
    global $db;
    
    $document_types = [
        'subpoena' => 'Subpoena/Summons',
        'notice_of_hearing' => 'Notice of Hearing',
        'certificate_to_file' => 'Certificate to File Action',
        'barangay_resolution' => 'Barangay Resolution',
        'protection_order' => 'Protection Order',
        'settlement_agreement' => 'Settlement Agreement'
    ];
    
    $title = ($document_types[$document_type] ?? 'Document') . ' Generated';
    $message = "A new " . ($document_types[$document_type] ?? 'document') . " has been generated. Please check your documents.";
    
    foreach ($recipient_ids as $recipient_id) {
        $stmt = $db->prepare("INSERT INTO user_notifications 
                              (user_id, title, message, type, related_id, related_type, created_at) 
                              VALUES (?, ?, ?, 'info', ?, 'document', NOW())");
        $stmt->execute([$recipient_id, $title, $message, $document_id]);
    }
    
    // Log the notification
    $log_stmt = $db->prepare("INSERT INTO activity_logs 
                              (user_id, action, description, affected_id, affected_type, created_at) 
                              VALUES (?, 'document_notification', ?, ?, ?, NOW())");
    $log_stmt->execute([$sender_id, "Sent notifications for $document_type #$document_id", $document_id, 'document']);
}

/**
 * Helper function to check if PDF can be generated
 */
function canGeneratePDF() {
    return loadTCPDF();
}

/**
 * Test PDF generation (for debugging)
 */
function testPDFGeneration() {
    try {
        if (!canGeneratePDF()) {
            return "TCPDF not installed. Please install via: composer require tecnickcom/tcpdf";
        }
        
        $testData = [
            'barangay' => 'Sample Barangay',
            'document_title' => 'Test Document',
            'document_number' => 'TEST-' . time(),
            'recipients' => 'John Doe',
            'report_number' => '2024-001',
            'hearing_date' => date('F j, Y', strtotime('+7 days')),
            'hearing_time' => '9:00 AM',
            'location' => 'Barangay Hall',
            'description' => 'Test hearing',
            'instructions' => 'Please attend.',
            'issuing_officer' => 'Barangay Captain'
        ];
        
        $filename = generateBarangayDocument('subpoena', $testData);
        return "PDF generated successfully: " . $filename;
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}
?>