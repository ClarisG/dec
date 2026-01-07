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

// Helper function to get web-accessible path
function getRelativePath($absolute_path) {
    $base_dir = realpath(__DIR__ . '/../');
    $file_path = realpath($absolute_path);
    
    if ($file_path && strpos($file_path, $base_dir) === 0) {
        return '.' . str_replace($base_dir, '', $file_path);
    }
    
    // Fallback to relative path
    if (strpos($absolute_path, __DIR__) === 0) {
        return '.' . str_replace(__DIR__, '', $absolute_path);
    }
    
    return $absolute_path;
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

// Helper function to get file placeholder HTML (UPDATED - No duplicate file name)
function getFilePlaceholderHTML($file_name, $file_type, $file_size, $extension) {
    $icon = 'fa-file';
    $color = 'file-icon-other';
    
    switch($file_type) {
        case 'Image': $icon = 'fa-image'; $color = 'file-icon-image'; break;
        case 'PDF': $icon = 'fa-file-pdf'; $color = 'file-icon-pdf'; break;
        case 'Video': $icon = 'fa-file-video'; $color = 'file-icon-video'; break;
        case 'Audio': $icon = 'fa-file-audio'; $color = 'file-icon-audio'; break;
        case 'Document': $icon = 'fa-file-word'; $color = 'file-icon-document'; break;
        case 'Spreadsheet': $icon = 'fa-file-excel'; $color = 'file-icon-spreadsheet'; break;
    }
    
    // Return only placeholder without duplicate file name
    return '
        <div class="evidence-placeholder">
            <i class="fas ' . $icon . ' evidence-file-icon ' . $color . '"></i>
            <div class="file-details">
                <span class="file-type">' . strtoupper($extension) . '</span>
                <span>' . $file_size . '</span>
            </div>
        </div>
    ';
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
    
    // Check if logo exists
    $logo_path = null;
    $logo_url = null;
    $possible_logo_paths = [
        __DIR__ . '/../images/10213.png',
        __DIR__ . '/../images/logo.png',
        __DIR__ . '/../images/logo.jpg',
        __DIR__ . '/../images/barangay-logo.png',
        __DIR__ . '/../uploads/logo.png',
        __DIR__ . '/images/10213.png',
        '../images/10213.png',
        'images/10213.png',
        '../../images/10213.png',
    ];
    
    foreach ($possible_logo_paths as $path) {
        if (file_exists($path)) {
            $logo_path = $path;
            // Convert to web-accessible path
            $logo_url = getRelativePath($path);
            break;
        }
    }
    
    // Also check for common image extensions
    if (!$logo_path) {
        $image_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
        foreach ($image_extensions as $ext) {
            $test_path = __DIR__ . '/../images/10213.' . $ext;
            if (file_exists($test_path)) {
                $logo_path = $test_path;
                $logo_url = getRelativePath($test_path);
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
            
            /* BARANGAY OFFICIAL REPORT Header with Logo - UPDATED LOGO STYLING */
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
            
            /* UPDATED: Better logo size handling */
            .logo-img-small {
                max-height: 100px;
                max-width: 150px;
                height: auto;
                width: auto;
                display: block;
                object-fit: contain;
            }
            
            .logo-placeholder {
                width: 120px;
                height: 120px;
                background: #f0f0f0;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #ddd;
            }
            
            .logo-placeholder span {
                font-size: 12px;
                color: #666;
                text-align: center;
                padding: 5px;
            }
            
            .header-text {
                flex: 1;
                text-align: center;
                padding-left: 150px; /* Space for logo */
                padding-right: 70px; /* Space for balance */
            }
            
            .header-text h1 {
                font-size: 24px;
                font-weight: bold;
                color: #1a4f8c; /* CHANGED TO BLUE */
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                line-height: 1.2;
            }
            
            .header-text h2 {
                font-size: 16px;
                font-weight: normal;
                color: #1a4f8c; /* CHANGED TO BLUE */
                margin-bottom: 8px;
            }
            
            .header-meta {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: #000;
                padding: 8px 0;
                border-top: 1px solid #ddd;
                margin-top: 10px;
                margin-left: 150px; /* Align with text */
                margin-right: 70px;
            }
            
            .header-meta div {
                padding: 2px 0;
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
                font-size: 16px;
                font-weight: bold;
                text-transform: uppercase;
                padding-bottom: 8px;
                margin-bottom: 15px;
                border-bottom: 2px solid #000;
                letter-spacing: 0.5px;
            }
            
            /* Table layout */
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 12px;
            }
            
            .report-table th,
            .report-table td {
                border: 1px solid #000;
                padding: 8px 10px;
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
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 11px;
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
                padding: 15px;
                margin: 10px 0;
                line-height: 1.6;
                font-size: 12px;
                background: #f9f9f9;
                border-radius: 4px;
                white-space: pre-line;
            }
            
            /* Timeline */
            .timeline {
                position: relative;
                padding-left: 20px;
                margin-top: 15px;
            }
            
            .timeline::before {
                content: '';
                position: absolute;
                left: 7px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: #000;
            }
            
            .timeline-item {
                position: relative;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            /* Signature section */
            .signature-section {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #000;
                page-break-inside: avoid;
            }
            
            .signature-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                margin-top: 30px;
            }
            
            .signature-box {
                text-align: center;
            }
            
            .signature-line {
                width: 80%;
                height: 1px;
                background: #000;
                margin: 40px auto 8px;
            }
            
            .signature-name {
                font-weight: bold;
                margin-top: 8px;
                color: #000;
                font-size: 14px;
            }
            
            .signature-title {
                font-size: 11px;
                color: #666;
                margin-top: 2px;
            }
            
            /* Footer */
            .footer {
                margin-top: 30px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
                font-style: italic;
                page-break-inside: avoid;
            }
            
            /* Print Actions - ALWAYS VISIBLE */
            .print-actions {
                background: #fff;
                padding: 15px;
                border-bottom: 1px solid #ddd;
                text-align: center;
                margin-bottom: 20px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .print-btn {
                background: #1a4f8c;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 0 10px;
                font-weight: 500;
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
                transition: background 0.3s;
            }
            
            .print-btn:hover {
                background: #2c7bb6;
            }
            
            .print-btn.landscape {
                background: #2c7bb6;
            }
            
            .print-btn.close {
                background: #666;
            }
            
            /* Print-specific styles */
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                    font-size: 11px;
                    background: white !important;
                    color: black !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .no-print {
                    display: none !important;
                }
                
                .print-actions {
                    display: none !important;
                }
                
                .report-header {
                    padding-top: 0;
                    margin-bottom: 20px;
                }
                
                .report-content {
                    padding: 0;
                    max-width: 100%;
                }
                
                .logo-img-small {
                    max-height: 80px;
                    max-width: 100px;
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
                    padding-top: 80px; /* Space for fixed print actions */
                }
                
                .report-content {
                    background: white;
                    padding: 30px;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    border-radius: 8px;
                    margin-bottom: 40px;
                }
                
                /* Force print buttons to stay visible on screen */
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
            
            /* Responsive adjustments */
            @media (max-width: 768px) {
                .report-table {
                    font-size: 11px;
                }
                
                .report-table th,
                .report-table td {
                    padding: 6px 8px;
                }
                
                .signature-grid {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }
                
                .header-meta {
                    flex-direction: column;
                    text-align: center;
                    margin-left: 0;
                    margin-right: 0;
                }
                
                .header-text {
                    padding-left: 0;
                    padding-right: 0;
                    margin-left: 140px;
                }
                
                .print-btn {
                    padding: 10px 15px;
                    font-size: 13px;
                    margin: 5px;
                    display: block;
                    width: 100%;
                    margin-bottom: 10px;
                }
                
                .logo-img-small {
                    max-height: 80px;
                    max-width: 100px;
                }
                
                .header-text h1 {
                    font-size: 20px;
                }
                
                .header-text h2 {
                    font-size: 14px;
                }
            }
            
            /* Page break helpers */
            .page-break {
                page-break-before: always;
            }
            
            .avoid-break {
                page-break-inside: avoid;
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
            // Add landscape class to body
            document.body.classList.add('print-landscape');
            
            // Print after a short delay
            setTimeout(() => {
                window.print();
                // Remove landscape class after printing
                setTimeout(() => {
                    document.body.classList.remove('print-landscape');
                }, 100);
            }, 100);
        }
        
        // Function to ensure all images are loaded before printing
        function ensureImagesLoaded() {
            const images = document.querySelectorAll('img');
            let loadedCount = 0;
            const totalImages = images.length;
            
            if (totalImages === 0) {
                return Promise.resolve();
            }
            
            return new Promise((resolve) => {
                images.forEach(img => {
                    if (img.complete) {
                        loadedCount++;
                    } else {
                        img.onload = () => {
                            loadedCount++;
                            if (loadedCount === totalImages) {
                                resolve();
                            }
                        };
                        img.onerror = () => {
                            loadedCount++;
                            if (loadedCount === totalImages) {
                                resolve();
                            }
                        };
                    }
                });
                
                // If all images are already loaded
                if (loadedCount === totalImages) {
                    resolve();
                }
            });
        }
        
        // Enhanced print function with image loading
        function printWithImages() {
            showPrintStatus('Loading images...');
            ensureImagesLoaded().then(() => {
                showPrintStatus('Opening print dialog...');
                window.print();
                setTimeout(() => {
                    showPrintStatus('Ready');
                }, 1000);
            });
        }
        
        function showPrintStatus(message) {
            const statusEl = document.getElementById('printStatus');
            if (statusEl) {
                statusEl.textContent = message;
            }
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
            
            // Update print buttons to use enhanced print
            const portraitBtn = document.querySelector('.print-btn:not(.landscape):not(.close)');
            const landscapeBtn = document.querySelector('.print-btn.landscape');
            
            if (portraitBtn) {
                portraitBtn.onclick = printWithImages;
            }
            
            if (landscapeBtn) {
                landscapeBtn.onclick = function() {
                    document.body.classList.add('print-landscape');
                    printWithImages();
                };
            }
        });
        </script>
    </head>
    <body>
        <!-- Print Actions - ALWAYS VISIBLE -->
        <div class="no-print print-actions">
            <button onclick="printWithImages()" class="print-btn">
                <i class="fas fa-print"></i> Print (Portrait)
            </button>
            <button onclick="printLandscape()" class="print-btn landscape">
                <i class="fas fa-print"></i> Print (Landscape)
            </button>
            <button onclick="closeWindow()" class="print-btn close">
                <i class="fas fa-times"></i> Close
            </button>
            <div id="printStatus" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
            <p style="margin-top: 8px; font-size: 12px; color: #666;">
                Portrait for standard printing | Landscape for wider layout
            </p>
        </div>
        
        <div class="report-content">
            <!-- Report Header with Logo -->
            <div class="report-header">
                <div class="header-with-logo">
                    <!-- Barangay Logo - Upper Left Side -->
                    <div class="logo-left">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo htmlspecialchars($logo_url); ?>"
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
            <div style="margin-bottom: 25px;" class="avoid-break">
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
            <div style="margin-bottom: 25px;" class="avoid-break">
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
                    <?php if (!empty($report['involved_persons'])): ?>
                    <tr>
                        <th>Involved Persons:</th>
                        <td colspan="3"><?php echo nl2br(htmlspecialchars($report['involved_persons'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($report['witnesses'])): ?>
                    <tr>
                        <th>Witnesses:</th>
                        <td colspan="3"><?php echo nl2br(htmlspecialchars($report['witnesses'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Description -->
            <div style="margin-bottom: 25px;" class="avoid-break">
                <div class="section-title">INCIDENT DESCRIPTION</div>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                </div>
            </div>
            
            <!-- Status History -->
            <?php if (!empty($status_history)): ?>
            <div style="margin-bottom: 25px;" class="avoid-break">
                <div class="section-title">STATUS HISTORY TIMELINE</div>
                <div class="timeline">
                    <?php foreach ($status_history as $history): ?>
                        <div class="timeline-item">
                            <div style="font-weight: bold; color: #1a4f8c; margin-bottom: 5px; font-size: 12px;">
                                <?php echo date('F d, Y h:i A', strtotime($history['created_at'])); ?>
                                <span class="status-badge status-<?php echo $history['status']; ?>" style="margin-left: 10px;">
                                    <?php echo strtoupper($history['status']); ?>
                                </span>
                            </div>
                            <?php if (!empty($history['notes'])): ?>
                            <div style="font-size: 11px; color: #333; padding: 8px; background: #f5f5f5; border-radius: 3px; margin-top: 5px;">
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
            <div style="margin-bottom: 25px;" class="avoid-break">
                <div class="section-title">RESOLUTION NOTES</div>
                <div class="description-box" style="background: #e8f4f8; border-left: 4px solid #1a4f8c;">
                    <?php echo nl2br(htmlspecialchars($report['resolution_notes'])); ?>
                    <?php if (!empty($report['resolved_at'])): ?>
                    <p style="font-size: 11px; color: #666; margin-top: 10px; font-style: italic;">
                        <i class="fas fa-calendar-check"></i> Resolved on: <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?>
                    </p>
                    <?php endif; ?>
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
                        <div class="signature-line"></div>
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
                <p style="margin-top: 8px; font-size: 9px; color: #999;">
                    Printed on: <?php echo date('F d, Y h:i A'); ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database error: ' . htmlspecialchars($e->getMessage());
}
?>