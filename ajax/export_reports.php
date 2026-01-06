<?php
// ajax/export_reports.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/constants.php'; // For logo path

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
    $logo_path = defined('LOGO_PATH') && file_exists(LOGO_PATH) ? LOGO_PATH : null;
    
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
            }
            
            body {
                font-family: 'Times New Roman', 'Georgia', serif;
                color: #000;
                font-size: 12px;
                line-height: 1.4;
                background: #fff;
                margin: 0;
                padding: 0;
            }
            
            /* Professional Letterhead Design - Blue and White */
            .letterhead {
                position: relative;
                width: 100%;
                padding: 20px 0;
                border-bottom: 3px solid #1a4f8c;
                margin-bottom: 30px;
                text-align: center;
            }
            
            .letterhead-header {
                text-align: center;
                margin-bottom: 20px;
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
                gap: 15px;
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .summary-item {
                display: flex;
                margin-bottom: 8px;
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
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 25px;
                page-break-inside: avoid;
                background: #fff;
            }
            
            .report-header {
                background: linear-gradient(to right, #1a4f8c, #2c7bb6);
                color: white;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                border-radius: 4px 4px 0 0;
                font-weight: bold;
            }
            
            .report-title {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .report-meta {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
                color: rgba(255,255,255,0.9);
            }
            
            /* Info grid for report details */
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0;
                margin-bottom: 15px;
                border: 1px solid #000;
                font-size: 12px;
            }
            
            .info-row {
                display: flex;
                border-bottom: 1px solid #000;
                min-height: 32px;
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
                padding: 4px 12px;
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
                max-height: 100px;
                overflow: hidden;
                position: relative;
                border-left: 4px solid #1a4f8c;
            }
            
            /* Footer */
            .footer {
                margin-top: 50px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
                font-style: italic;
            }
            
            /* Print Actions - Hidden when printing */
            .print-actions {
                background: #fff;
                padding: 15px;
                border-bottom: 1px solid #ddd;
                text-align: center;
                margin-bottom: 20px;
                position: sticky;
                top: 0;
                z-index: 100;
            }
            
            .print-btn {
                background: #1a4f8c;
                color: white;
                border: none;
                padding: 10px 24px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                margin: 0 8px;
                transition: background 0.3s;
                font-weight: 500;
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
                cursor: pointer !important;
            }
            
            .print-btn:hover {
                background: #2c7bb6;
                transform: translateY(-1px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            
            /* Print-specific styles */
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                    font-size: 11px;
                    background: white !important;
                    color: black !important;
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
                    display: none;
                }
                
                /* Ensure sections don't break awkwardly */
                .section {
                    page-break-inside: avoid;
                    margin-bottom: 20px;
                }
                
                .report-card {
                    page-break-inside: avoid;
                }
                
                /* Add page breaks after every 3rd report */
                .report-card:nth-child(3n) {
                    page-break-after: always;
                }
                
                /* Adjust for portrait */
                @page {
                    size: A4 portrait;
                    margin: 20mm;
                }
                
                /* Adjust for landscape */
                @page landscape {
                    size: A4 landscape;
                    margin: 15mm;
                }
                
                .print-landscape {
                    page: landscape;
                }
                
                /* Improve print quality */
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
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
                
                .print-actions {
                    background: white;
                    border-radius: 8px 8px 0 0;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                    position: fixed;
                    top: 0;
                    left: 20px;
                    right: 20px;
                    z-index: 1000;
                }
                
                /* Force print buttons to stay visible */
                .print-btn {
                    display: inline-block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
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
                    padding: 8px 12px;
                    font-size: 12px;
                    margin: 2px;
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
            }
            
            .empty-state i {
                font-size: 48px;
                color: #ccc;
                margin-bottom: 15px;
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
            // Add landscape class and print
            document.body.classList.add('print-landscape');
            window.print();
            document.body.classList.remove('print-landscape');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Focus the window for better UX
            window.focus();
            
            // REMOVED: Auto-print feature that was causing buttons to disappear
            // const urlParams = new URLSearchParams(window.location.search);
            // if (urlParams.get('autoprint') === 'true') {
            //     setTimeout(printDocument, 500);
            // }
            
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
        <!-- Print Actions (Hidden when printing) -->
        <div class="no-print print-actions">
            <button onclick="printDocument()" class="print-btn">
                <i class="fas fa-print"></i> Print All Reports (Portrait)
            </button>
            <button onclick="printLandscape()" class="print-btn" style="background: #2c7bb6;">
                <i class="fas fa-print"></i> Print All Reports (Landscape)
            </button>
            <button onclick="closeWindow()" class="print-btn" style="background: #666;">
                <i class="fas fa-times"></i> Close
            </button>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                Choose portrait for standard printing or landscape for wider layout
            </p>
        </div>
        
        <div class="report-content">
            <!-- Professional Letterhead -->
            <div class="letterhead">
                <div class="letterhead-header">
                    <div class="header-text">
                        <h1>BARANGAY REPORTS SUMMARY</h1>
                        <h2>Multiple Reports Printout</h2>
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
                        <span class="info-label">Category:</span>
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
                
                <!-- Show evidence files if any -->
                <?php 
                $evidence_files = [];
                if (!empty($report['evidence_files'])) {
                    $evidence_files = json_decode($report['evidence_files'], true);
                }
                ?>
                
                <?php if (!empty($evidence_files)): ?>
                <div class="info-row">
                    <span class="info-label">Attached Files:</span>
                    <div class="info-value">
                        <div style="font-size: 11px; color: #555; margin-top: 5px;">
                            <?php echo count($evidence_files); ?> file(s) attached
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($report['resolution_notes'])): ?>
                <div class="info-row">
                    <span class="info-label">Resolution:</span>
                    <div class="info-value">
                        <div style="font-size: 11px; color: #155724; background: #d4edda; padding: 8px; border-radius: 3px; border-left: 4px solid #28a745;">
                            <?php 
                            $notes = $report['resolution_notes'];
                            echo nl2br(htmlspecialchars(substr($notes, 0, 150)));
                            if (strlen($notes) > 150) echo '...';
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (($index + 1) % 3 == 0 && ($index + 1) < count($reports)): ?>
            <div class="page-break"></div>
            <?php endif; ?>
            
            <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer">
                <p>Generated by Barangay Reporting System | Bulk Print Module</p>
                <p>Document ID: BULK-PRINT-<?php echo date('YmdHis'); ?> | Page 1 of 1</p>
                <p>This document contains confidential information. Handle with care.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}
?>