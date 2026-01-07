<?php
// ajax/download_report.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/constants.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$report_id = $_GET['id'] ?? 0;
$format = $_GET['format'] ?? 'print';

if (!$report_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid report ID');
}

try {
    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get report details
    $query = "SELECT r.*, rt.type_name, u.first_name, u.last_name, u.email, u.contact_number
              FROM reports r
              LEFT JOIN report_types rt ON r.report_type_id = rt.id
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.id = :id AND r.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $report_id, ':user_id' => $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header('HTTP/1.1 404 Not Found');
        exit('Report not found');
    }
    
    // Get evidence files
    $evidence_files = [];
    if (!empty($report['evidence_files'])) {
        $evidence_files = json_decode($report['evidence_files'], true);
    }
    
    // Get status history
    $history_query = "SELECT * FROM report_status_history 
                     WHERE report_id = :report_id 
                     ORDER BY created_at DESC";
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->execute([':report_id' => $report_id]);
    $status_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if logo exists - Look for logo in multiple locations
    $logo_path = null;
    $possible_logo_paths = [
        __DIR__ . '/../images/10213.png',
        __DIR__ . '/images/10213.png',
        '../images/10213.png',
        'images/10213.png',
        '../../images/10213.png',
    ];
    
    foreach ($possible_logo_paths as $path) {
        if (file_exists($path)) {
            $logo_path = $path;
            // Convert to web-accessible path if needed
            if (strpos($path, __DIR__) !== false) {
                $logo_path = str_replace(__DIR__, '.', $path);
            }
            break;
        }
    }
    
    // Also check for common image extensions
    if (!$logo_path) {
        $image_extensions = ['png', 'jpg', 'jpeg', 'gif'];
        foreach ($image_extensions as $ext) {
            $test_path = __DIR__ . '/../images/10213.' . $ext;
            if (file_exists($test_path)) {
                $logo_path = str_replace(__DIR__, '.', $test_path);
                break;
            }
        }
    }
    
    // Only print format is available now
    header('Content-Type: text/html');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Report - <?php echo htmlspecialchars($report['report_number']); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* Reset and base styles */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                color: #000;
                font-size: 12px;
                line-height: 1.4;
                background: #fff;
                margin: 0;
                padding: 0;
            }
            
            /* BARANGAY OFFICIAL REPORT Header with Logo */
            .report-header {
                padding: 15px 0 20px 0;
                border-bottom: 2px solid #000;
                margin-bottom: 25px;
                position: relative;
            }
            
            .header-with-logo {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                margin-bottom: 10px;
                position: relative;
            }
            
            .logo-left {
                flex: 0 0 auto;
                margin-right: 15px;
                text-align: left;
                position: absolute;
                left: 0;
                top: 0;
                z-index: 1;
            }
            
            .logo-img-small {
                max-height: 100px;
                max-width: 120px;
                height: 120px;
                width: 100px;
            }
            
            .logo-placeholder {
                width: 50px;
                height: 50px;
                background: #f0f0f0;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #ddd;
            }
            
            .logo-placeholder span {
                font-size: 7px;
                color: #666;
                text-align: center;
                padding: 2px;
            }
            
            .header-text {
                flex: 1;
                text-align: center;
                padding-left: 100px; /* Space for logo */
                padding-right: 70px; /* Space for balance */
            }
            
            .header-text h1 {
                font-size: 20px;
                font-weight: bold;
                color: #000;
                margin-bottom: 3px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                line-height: 1.2;
            }
            
            .header-text h2 {
                font-size: 14px;
                font-weight: normal;
                color: #333;
                margin-bottom: 5px;
            }
            
            .header-meta {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
                color: #000;
                padding: 4px 0;
                border-top: 1px solid #ddd;
                margin-top: 8px;
                margin-left: 70px; /* Align with text */
                margin-right: 70px;
            }
            
            .header-meta div {
                padding: 1px 0;
            }
            
            .header-meta strong {
                color: #000;
                font-weight: bold;
            }
            
            /* Report Content */
            .report-content {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                padding: 0;
            }
            
            .section-title {
                color: #000;
                font-size: 13px;
                font-weight: bold;
                text-transform: uppercase;
                padding-bottom: 4px;
                margin-bottom: 12px;
                border-bottom: 1px solid #000;
                letter-spacing: 0.5px;
            }
            
            /* Table layout */
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 18px;
                font-size: 11px;
            }
            
            .report-table th,
            .report-table td {
                border: 1px solid #000;
                padding: 6px 8px;
                vertical-align: top;
                text-align: left;
            }
            
            .report-table th {
                background-color: #f0f0f0;
                font-weight: bold;
                width: 40%;
            }
            
            .report-table td {
                background-color: #fff;
                width: 60%;
            }
            
            /* Status badge */
            .status-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 2px;
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            .status-assigned { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .status-investigating { background: #d6d8d9; color: #383d41; border: 1px solid #c6c8ca; }
            .status-resolved { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .status-closed { background: #f8f9fa; color: #343a40; border: 1px solid #e9ecef; }
            
            /* Description box */
            .description-box {
                border: 1px solid #ddd;
                padding: 12px;
                margin: 8px 0;
                line-height: 1.5;
                font-size: 11px;
                background: #f9f9f9;
                border-radius: 3px;
            }
            
            /* Evidence files */
            .evidence-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 8px;
                margin-top: 8px;
            }
            
            .evidence-item {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: center;
                background: #f9f9f9;
                border-radius: 3px;
            }
            
            /* Timeline */
            .timeline {
                position: relative;
                padding-left: 15px;
                margin-top: 12px;
            }
            
            .timeline::before {
                content: '';
                position: absolute;
                left: 5px;
                top: 0;
                bottom: 0;
                width: 1px;
                background: #000;
            }
            
            .timeline-item {
                position: relative;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #eee;
            }
            
            /* Signature section */
            .signature-section {
                margin-top: 35px;
                padding-top: 18px;
                border-top: 1px solid #000;
            }
            
            .signature-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 35px;
                margin-top: 25px;
            }
            
            .signature-box {
                text-align: center;
            }
            
            .signature-line {
                width: 100%;
                height: 1px;
                background: #000;
                margin: 35px auto 4px;
            }
            
            .signature-name {
                font-weight: bold;
                margin-top: 4px;
                color: #000;
                font-size: 11px;
            }
            
            .signature-title {
                font-size: 9px;
                color: #666;
                margin-top: 1px;
            }
            
            /* Footer */
            .footer {
                margin-top: 25px;
                padding-top: 8px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 8px;
                color: #666;
                font-style: italic;
            }
            
            /* Print Actions - ALWAYS VISIBLE */
            .print-actions {
                background: #fff;
                padding: 12px;
                border-bottom: 1px solid #ddd;
                text-align: center;
                margin-bottom: 15px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .print-btn {
                background: #1a4f8c;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                margin: 0 4px;
                font-weight: 500;
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .print-btn:hover {
                background: #2c7bb6;
            }
            
            /* Print-specific styles */
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                    font-size: 10px;
                    background: white !important;
                    color: black !important;
                }
                
                .no-print {
                    display: none !important;
                }
                
                .print-actions {
                    display: none !important;
                }
                
                .report-header {
                    padding-top: 0;
                    margin-bottom: 15px;
                }
                
                .report-content {
                    padding: 0;
                    max-width: 100%;
                }
                
                .logo-img-small {
                    max-height: 100px;
                    max-width: 120px;
                }
                
                /* Ensure no page breaks inside important sections */
                .signature-section {
                    page-break-inside: avoid;
                }
                
                .report-table {
                    page-break-inside: avoid;
                }
                
                /* Adjust for portrait */
                @page {
                    size: A4 portrait;
                    margin: 15mm;
                }
                
                /* Adjust for landscape */
                @page landscape {
                    size: A4 landscape;
                    margin: 12mm;
                }
                
                .print-landscape {
                    page: landscape;
                }
            }
            
            /* Screen-specific styles */
            @media screen {
                body {
                    background: #f5f5f5;
                    padding-top: 60px; /* Space for fixed print actions */
                }
                
                .report-content {
                    background: white;
                    padding: 25px;
                    box-shadow: 0 0 15px rgba(0,0,0,0.1);
                    border-radius: 0;
                    margin-bottom: 15px;
                }
            }
            
            /* Responsive adjustments */
            @media (max-width: 768px) {
                .report-table {
                    font-size: 10px;
                }
                
                .report-table th,
                .report-table td {
                    padding: 5px 6px;
                }
                
                .signature-grid {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
                
                .header-meta {
                    flex-direction: column;
                    text-align: center;
                    margin-left: 0;
                    margin-right: 0;
                }
                
                .header-text {
                    padding-left: 60px;
                    padding-right: 60px;
                }
                
                .print-btn {
                    padding: 6px 10px;
                    font-size: 11px;
                    margin: 2px;
                }
                
                .logo-img-small {
                    max-height: 40px;
                    max-width: 50px;
                }
            }
            
            /* PERMANENT VISIBILITY FOR PRINT BUTTONS */
            .print-permanent {
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: static !important;
            }
            
            /* ADDED: Force print buttons to stay visible on screen */
            @media screen {
                .print-actions {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    z-index: 1000 !important;
                }
                
                .print-btn {
                    display: inline-block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                    cursor: pointer !important;
                }
            }
        </style>
        <script>
        function printDocument() {
            window.print();
        }
        
        function closeWindow() {
            window.close();
        }
        
        function printLandscape() {
            document.body.classList.add('print-landscape');
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.body.classList.remove('print-landscape');
                }, 100);
            }, 100);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            window.focus();
            
            // Ensure print buttons are always visible
            const printButtons = document.querySelectorAll('.print-btn');
            printButtons.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.style.cursor = 'pointer';
            });
            
            // Force print actions to stay visible
            const printActions = document.querySelector('.print-actions');
            if (printActions) {
                printActions.style.display = 'block';
                printActions.style.visibility = 'visible';
                printActions.style.opacity = '1';
                printActions.style.position = 'fixed';
                printActions.style.top = '0';
                printActions.style.left = '0';
                printActions.style.right = '0';
                printActions.style.zIndex = '1000';
            }
            
            // Prevent any auto-hiding
            setInterval(() => {
                printButtons.forEach(btn => {
                    if (btn.style.display === 'none' || 
                        btn.style.visibility === 'hidden' || 
                        btn.style.opacity === '0') {
                        btn.style.display = 'inline-block';
                        btn.style.visibility = 'visible';
                        btn.style.opacity = '1';
                    }
                });
            }, 1000);
        });
        </script>
    </head>
    <body>
        <!-- Print Actions - ALWAYS VISIBLE -->
        <div class="no-print print-actions">
            <button onclick="printDocument()" class="print-btn print-permanent">
                <i class="fas fa-print"></i> Print (Portrait)
            </button>
            <button onclick="printLandscape()" class="print-btn print-permanent" style="background: #2c7bb6;">
                <i class="fas fa-print"></i> Print (Landscape)
            </button>
            <button onclick="closeWindow()" class="print-btn print-permanent" style="background: #666;">
                <i class="fas fa-times"></i> Close
            </button>
            <p style="margin-top: 8px; font-size: 11px; color: #666;">
                Portrait for standard printing | Landscape for wider layout
            </p>
        </div>
        
        <div class="report-content">
            <!-- Report Header with Logo -->
            <div class="report-header">
                <div class="header-with-logo">
                    <!-- Barangay Logo - Upper Left Side -->
                    <div class="logo-left">
                        <?php if (!empty($logo_path)): ?>
                            <img src="<?php echo htmlspecialchars($logo_path); ?>"
                                 alt="Barangay Logo"
                                 class="logo-img-small">
                        <?php else: ?>
                            <div class="logo-placeholder">
                                <span>BARANGAY<br>LOGO</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="header-text">
                        <h1>BARANGAY OFFICIAL REPORT</h1>
                        <h2>Law Enforcement and Incident Report</h2>
                    </div>
                    
                    <!-- Empty div for right side balance -->
                    <div style="width: 60px; visibility: hidden;"></div>
                </div>
                
                <div class="header-meta">
                    <div>
                        <strong>Report #:</strong> <?php echo htmlspecialchars($report['report_number']); ?>
                    </div>
                    <div>
                        <strong>Printed:</strong> <?php echo date('F d, Y h:i A'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Report Information Table -->
            <div style="margin-bottom: 22px;">
                <div class="section-title">REPORT INFORMATION</div>
                <table class="report-table">
                    <tr>
                        <th>Report Number:</th>
                        <td><?php echo htmlspecialchars($report['report_number']); ?></td>
                        <th>Title:</th>
                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                    </tr>
                    <tr>
                        <th>Report Type:</th>
                        <td><?php echo htmlspecialchars($report['type_name']); ?></td>
                        <th>Current Status:</th>
                        <td>
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php echo strtoupper($report['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Priority:</th>
                        <td><?php echo htmlspecialchars(ucfirst($report['priority'])); ?></td>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars(ucfirst($report['category'])); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Incident Details Table -->
            <div style="margin-bottom: 22px;">
                <div class="section-title">INCIDENT DETAILS</div>
                <table class="report-table">
                    <tr>
                        <th>Location:</th>
                        <td><?php echo htmlspecialchars($report['location']); ?></td>
                        <th>Incident Date/Time:</th>
                        <td><?php echo date('F d, Y h:i A', strtotime($report['incident_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Date Reported:</th>
                        <td><?php echo date('F d, Y h:i A', strtotime($report['created_at'])); ?></td>
                        <th>Submitted By:</th>
                        <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                    </tr>
                    <?php if (!empty($report['contact_number'])): ?>
                    <tr>
                        <th>Contact Number:</th>
                        <td colspan="3"><?php echo htmlspecialchars($report['contact_number']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($report['email'])): ?>
                    <tr>
                        <th>Email:</th>
                        <td colspan="3"><?php echo htmlspecialchars($report['email']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Description -->
            <div style="margin-bottom: 22px;">
                <div class="section-title">INCIDENT DESCRIPTION</div>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                </div>
            </div>
            
            <!-- Evidence Files -->
            <?php if (!empty($evidence_files)): ?>
            <div style="margin-bottom: 22px;">
                <div class="section-title">ATTACHED EVIDENCE FILES</div>
                <div class="evidence-grid">
                    <?php foreach ($evidence_files as $file): ?>
                        <?php 
                        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                        $is_video = in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']);
                        $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                        $is_document = in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx']);
                        ?>
                        <div class="evidence-item">
                            <div style="font-size: 18px; color: #1a4f8c; margin-bottom: 4px;">
                                <?php if ($is_video): ?>
                                    <i class="fas fa-video"></i>
                                <?php elseif ($is_image): ?>
                                    <i class="fas fa-image"></i>
                                <?php elseif ($is_document): ?>
                                    <i class="fas fa-file-alt"></i>
                                <?php else: ?>
                                    <i class="fas fa-file"></i>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 9px; word-break: break-all; margin-bottom: 2px;">
                                <?php echo htmlspecialchars($file['name'] ?? 'Unnamed File'); ?>
                            </div>
                            <div style="font-size: 8px; color: #666;">
                                <?php 
                                if ($is_video) {
                                    echo 'Video';
                                } elseif ($is_image) {
                                    echo 'Image';
                                } elseif ($is_document) {
                                    echo 'Document';
                                } else {
                                    echo 'File';
                                }
                                if (isset($file['size'])): ?>
                                    <br><?php echo formatBytes($file['size']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Status History -->
            <?php if (!empty($status_history)): ?>
            <div style="margin-bottom: 22px;">
                <div class="section-title">STATUS HISTORY TIMELINE</div>
                <div class="timeline">
                    <?php foreach ($status_history as $history): ?>
                        <div class="timeline-item">
                            <div style="font-weight: bold; color: #1a4f8c; margin-bottom: 4px; font-size: 10px;">
                                <?php echo date('F d, Y h:i A', strtotime($history['created_at'])); ?>
                                <span class="status-badge status-<?php echo $history['status']; ?>" style="margin-left: 8px;">
                                    <?php echo strtoupper($history['status']); ?>
                                </span>
                            </div>
                            <?php if (!empty($history['notes'])): ?>
                            <div style="font-size: 9px; color: #333; padding: 4px; background: #f5f5f5; border-radius: 2px;">
                                <?php echo nl2br(htmlspecialchars($history['notes'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Resolution Notes -->
            <?php if (!empty($report['resolution_notes'])): ?>
            <div style="margin-bottom: 22px;">
                <div class="section-title">RESOLUTION NOTES</div>
                <div class="description-box" style="background: #e8f4f8;">
                    <?php echo nl2br(htmlspecialchars($report['resolution_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Signatures -->
            <div class="signature-section">
                <div class="section-title">SIGNATURES</div>
                <div class="signature-grid">
                    <div class="signature-box">
                        <div class="signature-name">
                            <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                        </div>
                        <div class="signature-title">Citizen/Reporter</div>
                        <div class="signature-title">Date: <?php echo date('m/d/Y'); ?></div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-name">_________________________</div>
                        <div class="signature-title">Barangay Official</div>
                        <div class="signature-title">Date: _________________________</div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>Generated by Barangay Reporting System | Document ID: PRINT-<?php echo $report['id']; ?>-<?php echo date('YmdHis'); ?></p>
                <p>This is an official document. Unauthorized duplication is prohibited.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database error: ' . htmlspecialchars($e->getMessage());
}

// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}