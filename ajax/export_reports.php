<?php
// ajax/export_reports.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/constants.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$format = $_GET['format'] ?? 'print';
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

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

try {
    $conn = getDbConnection();
    
    // Build query with filters
    $query = "SELECT r.*, rt.type_name, u.first_name, u.last_name
              FROM reports r
              LEFT JOIN report_types rt ON r.report_type_id = rt.id
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.user_id = :user_id";
    
    $params = [':user_id' => $user_id];
    
    if (!empty($status) && $status != 'all') {
        $query .= " AND r.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($category) && $category != 'all') {
        $query .= " AND r.category = :category";
        $params[':category'] = $category;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(r.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(r.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if logo exists
    $logo_url = null;
    $possible_logo_paths = [
        __DIR__ . '/../images/10213.png',
        __DIR__ . '/../images/logo.png',
        __DIR__ . '/../images/logo.jpg',
        __DIR__ . '/../images/barangay-logo.png',
        __DIR__ . '/../uploads/logo.png',
        __DIR__ . '/../uploads/barangay-logo.png'
    ];
    
    foreach ($possible_logo_paths as $path) {
        if (file_exists($path)) {
            $logo_url = getRelativePath($path);
            break;
        }
    }
    
    // Only print format is available
    header('Content-Type: text/html');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Bulk Reports Print</title>
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
                font-family: 'Times New Roman', 'Georgia', serif;
                color: #000;
                font-size: 11px;
                line-height: 1.4;
                background: #fff;
                margin: 0;
                padding: 0;
            }
            
            /* Professional Letterhead Design with Logo */
            .letterhead {
                position: relative;
                width: 100%;
                padding: 20px 0;
                border-bottom: 3px solid #1a4f8c;
                margin-bottom: 25px;
                page-break-after: avoid;
            }
            
            .letterhead-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
            }
            
            .logo-container {
                flex: 0 0 auto;
                text-align: center;
            }
            
            .logo-img {
                max-height: 120px;
                max-width: 180px;
                height: auto;
                width: auto;
                display: block;
            }
            
            .logo-placeholder {
                width: 180px;
                height: 120px;
                background: linear-gradient(135deg, #1a4f8c 0%, #2c7bb6 100%);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                border-radius: 4px;
            }
            
            .header-text {
                flex: 1;
                text-align: center;
            }
            
            .header-text h1 {
                color: #1a4f8c;
                font-size: 28px;
                font-weight: bold;
                letter-spacing: 1px;
                margin-bottom: 10px;
                text-transform: uppercase;
            }
            
            .header-text h2 {
                color: #333;
                font-size: 20px;
                font-weight: normal;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            
            .header-meta {
                display: flex;
                justify-content: space-between;
                color: #555;
                font-size: 12px;
                margin-top: 10px;
                padding: 10px 0;
            }
            
            .header-meta div {
                padding: 3px 0;
            }
            
            .header-meta strong {
                color: #1a4f8c;
                font-weight: bold;
            }
            
            /* Report Content */
            .report-content {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            .section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            .section-title {
                color: #1a4f8c;
                font-size: 16px;
                font-weight: bold;
                text-transform: uppercase;
                padding-bottom: 8px;
                margin-bottom: 15px;
                border-bottom: 2px solid #1a4f8c;
                letter-spacing: 0.5px;
            }
            
            /* Summary section */
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
                page-break-inside: avoid;
                font-size: 11px;
            }
            
            .summary-item {
                display: flex;
                margin-bottom: 6px;
            }
            
            .summary-label {
                min-width: 120px;
                font-weight: bold;
                color: #333;
                padding-right: 10px;
            }
            
            .summary-value {
                flex: 1;
                color: #000;
            }
            
            /* Report cards */
            .report-card {
                border: 2px solid #1a4f8c;
                border-radius: 6px;
                padding: 20px;
                margin-bottom: 30px;
                page-break-inside: avoid;
                background: #fff;
                break-inside: avoid;
            }
            
            .report-header {
                background: linear-gradient(to right, #1a4f8c, #2c7bb6);
                color: white;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                border-radius: 6px 6px 0 0;
                font-weight: bold;
            }
            
            .report-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .report-meta {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: rgba(255,255,255,0.9);
            }
            
            /* Info grid for report details */
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0;
                margin-bottom: 15px;
                border: 1px solid #000;
                font-size: 11px;
            }
            
            .info-row {
                display: flex;
                border-bottom: 1px solid #000;
                min-height: 34px;
            }
            
            .info-row:last-child {
                border-bottom: none;
            }
            
            .info-label {
                width: 140px;
                font-weight: bold;
                color: #000;
                padding: 8px 10px;
                background: #f0f0f0;
                border-right: 1px solid #000;
                display: flex;
                align-items: center;
            }
            
            .info-value {
                flex: 1;
                color: #000;
                word-break: break-word;
                padding: 8px 10px;
                display: flex;
                align-items: center;
            }
            
            /* Status badge */
            .status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            .status-assigned { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .status-investigating { background: #d6d8d9; color: #383d41; border: 1px solid #c6c8ca; }
            .status-resolved { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .status-closed { background: #f8f9fa; color: #343a40; border: 1px solid #e9ecef; }
            
            /* Description preview */
            .description-preview {
                background: #f9f9f9;
                border: 1px solid #ddd;
                padding: 12px;
                margin-top: 10px;
                font-size: 11px;
                line-height: 1.5;
                max-height: 120px;
                overflow: hidden;
                position: relative;
                border-left: 4px solid #1a4f8c;
                white-space: pre-line;
            }
            
            /* Footer */
            .footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
                font-style: italic;
                page-break-before: avoid;
            }
            
            /* Print Actions - Hidden when printing */
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
                transition: background 0.3s;
                font-weight: 500;
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            }
            
            .print-btn:hover {
                background: #2c7bb6;
                transform: translateY(-1px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
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
                    font-size: 10px;
                    background: white !important;
                    color: black !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .no-print {
                    display: none !important;
                }
                
                .letterhead {
                    padding-top: 15px;
                    margin-bottom: 20px;
                }
                
                .report-content {
                    padding: 0;
                    max-width: 100%;
                }
                
                .print-actions {
                    display: none !important;
                }
                
                /* Ensure sections don't break awkwardly */
                .section,
                .report-card {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }
                
                /* Add page breaks after every 2nd report */
                .report-card:nth-child(2n) {
                    page-break-after: always;
                }
                
                .report-card:last-child {
                    page-break-after: auto;
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
                    padding: 20px;
                    padding-top: 80px; /* Space for fixed print actions */
                }
                
                .report-content {
                    background: white;
                    padding: 30px;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                
                /* Force print buttons to stay visible */
                .print-btn {
                    display: inline-block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                }
                
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
            }
            
            /* Responsive adjustments */
            @media (max-width: 768px) {
                .summary-grid,
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .header-meta {
                    flex-direction: column;
                    gap: 8px;
                    text-align: center;
                }
                
                .header-text h1 {
                    font-size: 22px;
                }
                
                .header-text h2 {
                    font-size: 16px;
                }
                
                .report-meta {
                    flex-direction: column;
                    gap: 5px;
                }
                
                .print-btn {
                    padding: 10px 15px;
                    font-size: 13px;
                    margin: 5px;
                    display: block;
                    width: 100%;
                    margin-bottom: 10px;
                }
                
                .letterhead-header {
                    flex-direction: column;
                    text-align: center;
                }
                
                .logo-container {
                    margin-bottom: 15px;
                }
            }
            
            /* Empty state */
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #666;
                border: 2px dashed #ddd;
                border-radius: 8px;
                margin: 20px 0;
                page-break-inside: avoid;
            }
            
            .empty-state i {
                font-size: 48px;
                color: #ccc;
                margin-bottom: 15px;
            }
            
            /* Page numbers for print */
            .page-number {
                position: fixed;
                bottom: 10px;
                right: 20px;
                font-size: 10px;
                color: #999;
            }
            
            @media print {
                .page-number {
                    position: fixed;
                    bottom: 10mm;
                    right: 20mm;
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
            // Focus the window for better UX
            window.focus();
            
            // Force print buttons to always be visible
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
            }
        });
        </script>
    </head>
    <body>
        <!-- Print Actions (Hidden when printing) -->
        <div class="no-print print-actions">
            <button onclick="printDocument()" class="print-btn">
                <i class="fas fa-print"></i> Print All Reports (Portrait)
            </button>
            <button onclick="printLandscape()" class="print-btn landscape">
                <i class="fas fa-print"></i> Print All Reports (Landscape)
            </button>
            <button onclick="closeWindow()" class="print-btn close">
                <i class="fas fa-times"></i> Close
            </button>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                Choose portrait for standard printing or landscape for wider layout
            </p>
            <p style="margin-top: 5px; font-size: 11px; color: #888;">
                Total Reports: <?php echo count($reports); ?> | Generated: <?php echo date('F d, Y h:i A'); ?>
            </p>
        </div>
        
        <div class="report-content">
            <!-- Professional Letterhead with Logo -->
            <div class="letterhead">
                <div class="letterhead-header">
                    <div class="logo-container">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" class="logo-img" alt="Barangay Logo">
                        <?php else: ?>
                            <div class="logo-placeholder">
                                BARANGAY LOGO
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="header-text">
                        <h1>BARANGAY REPORTS SUMMARY</h1>
                        <h2>Multiple Reports Printout</h2>
                    </div>
                    <div class="logo-container" style="width: 180px;">
                        <!-- Empty for alignment -->
                    </div>
                </div>
                <div class="header-meta">
                    <div>
                        <strong>Printed:</strong> <?php echo date('F d, Y h:i A'); ?>
                    </div>
                    <div>
                        <strong>Generated by:</strong> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'User ID ' . $user_id); ?>
                    </div>
                </div>
            </div>
            
            <!-- Summary Section -->
            <div class="section">
                <div class="section-title">EXPORT SUMMARY</div>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Total Reports:</span>
                        <span class="summary-value"><?php echo count($reports); ?> report(s)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Date Range:</span>
                        <span class="summary-value">
                            <?php
                            if (!empty($date_from) && !empty($date_to)) {
                                echo date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
                            } elseif (!empty($date_from)) {
                                echo 'From ' . date('M d, Y', strtotime($date_from));
                            } elseif (!empty($date_to)) {
                                echo 'Until ' . date('M d, Y', strtotime($date_to));
                            } else {
                                echo 'All Dates';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Status Filter:</span>
                        <span class="summary-value">
                            <?php echo (!empty($status) && $status != 'all') ? ucfirst($status) : 'All Statuses'; ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Category Filter:</span>
                        <span class="summary-value">
                            <?php echo (!empty($category) && $category != 'all') ? ucfirst($category) : 'All Categories'; ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Print Date:</span>
                        <span class="summary-value"><?php echo date('F d, Y'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Print Time:</span>
                        <span class="summary-value"><?php echo date('h:i A'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Reports List -->
            <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3 style="margin-bottom: 10px; color: #666;">No reports found</h3>
                <p>No reports match the selected filters.</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($reports as $index => $report): ?>
            <div class="report-card">
                <div class="report-header">
                    <div class="report-title">
                        Report #<?php echo $index + 1; ?>: <?php echo htmlspecialchars($report['title']); ?>
                    </div>
                    <div class="report-meta">
                        <span>
                            <strong>Report #:</strong> <?php echo htmlspecialchars($report['report_number']); ?>
                        </span>
                        <span>
                            <strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-label">Report Type:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['type_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Current Status:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php echo strtoupper($report['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Priority:</span>
                        <span class="info-value"><?php echo htmlspecialchars(ucfirst($report['priority'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Category:</th>
                        <span class="info-value"><?php echo htmlspecialchars(ucfirst($report['category'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['location']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Incident Date:</span>
                        <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($report['incident_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date Reported:</span>
                        <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Submitted By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($report['description'])): ?>
                <div class="info-row">
                    <span class="info-label">Description:</span>
                    <div class="info-value">
                        <div class="description-preview">
                            <?php echo nl2br(htmlspecialchars(substr($report['description'], 0, 300))); ?>
                            <?php if (strlen($report['description']) > 300): ?>...<?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($report['resolution_notes'])): ?>
                <div class="info-row">
                    <span class="info-label">Resolution:</span>
                    <div class="info-value">
                        <div style="font-size: 11px; color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; border-left: 4px solid #28a745;">
                            <?php 
                            $notes = $report['resolution_notes'];
                            echo nl2br(htmlspecialchars(substr($notes, 0, 200)));
                            if (strlen($notes) > 200) echo '...';
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (($index + 1) % 2 == 0 && ($index + 1) < count($reports)): ?>
            <div style="page-break-after: always;"></div>
            <?php endif; ?>
            
            <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer">
                <p>Generated by Barangay Reporting System | Bulk Print Module</p>
                <p>Document ID: BULK-PRINT-<?php echo date('YmdHis'); ?> | Total Reports: <?php echo count($reports); ?></p>
                <p>This document contains confidential information. Handle with care.</p>
                <?php if (!empty($reports)): ?>
                <p style="margin-top: 8px; font-size: 10px; color: #999;">
                    Reports printed: <?php echo date('F d, Y h:i A'); ?> | Page 1 of <?php echo ceil(count($reports) / 2); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Page numbers (will be positioned by CSS) -->
        <div class="page-number no-print" style="display: none;"></div>
    </body>
    </html>
    <?php
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}
?>