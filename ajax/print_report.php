<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$report_id = $_GET['id'] ?? 0;

// Get report details
$query = "SELECT r.*, rt.type_name, 
                 u.first_name, u.last_name, u.email, u.contact_number as phone, u.address,
                 CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM reports r 
          LEFT JOIN report_types rt ON r.report_type_id = rt.id 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE r.id = ? AND r.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $report_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Report not found');
}

$report = $result->fetch_assoc();

// Get evidence files from JSON
$evidence_files = [];
if (!empty($report['evidence_files'])) {
    $evidence_files = json_decode($report['evidence_files'], true);
}

// Get timeline
$timeline = [];
$timeline_query = "SELECT * FROM report_status_history WHERE report_id = ? ORDER BY created_at ASC";
$timeline_stmt = $conn->prepare($timeline_query);
$timeline_stmt->bind_param("i", $report_id);
$timeline_stmt->execute();
$timeline_result = $timeline_stmt->get_result();

while ($event = $timeline_result->fetch_assoc()) {
    $timeline[] = $event;
}

// Check if logo exists
$logo_url = null;
$possible_logo_paths = [
    __DIR__ . '/../images/10213.png',
    __DIR__ . '/../images/logo.png',
    __DIR__ . '/../images/logo.jpg',
    __DIR__ . '/../images/barangay-logo.png',
    __DIR__ . '/../uploads/logo.png',
];

foreach ($possible_logo_paths as $path) {
    if (file_exists($path)) {
        $logo_url = '.' . str_replace(realpath(__DIR__ . '/../'), '', realpath($path));
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Print - <?php echo htmlspecialchars($report['report_number']); ?></title>
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        /* Print-specific styles */
        @media print {
            body {
                font-family: 'Times New Roman', Times, serif;
                line-height: 1.5;
                color: #000;
                background: #fff;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            img {
                max-width: 100% !important;
                height: auto !important;
            }
            
            .evidence-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 10px;
                margin: 20px 0;
            }
            
            .evidence-item {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: center;
                page-break-inside: avoid;
            }
            
            .evidence-image {
                max-height: 150px;
                object-fit: contain;
                width: 100%;
                background: #f8f8f8;
            }
            
            .evidence-placeholder {
                height: 150px;
                background: #f5f5f5;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            
            @page {
                size: A4;
                margin: 20mm;
            }
        }
        
        @media screen {
            body {
                font-family: Arial, sans-serif;
                padding: 20px;
                background: #f5f5f5;
            }
            
            #printContent {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            
            .print-controls {
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                background: #e8f4fd;
                border-radius: 8px;
            }
            
            .evidence-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            
            .evidence-item {
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                overflow: hidden;
                background: white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            .evidence-image {
                width: 100%;
                height: 150px;
                object-fit: contain;
                background: #f8f8f8;
            }
            
            .evidence-placeholder {
                height: 150px;
                background: #f5f5f5;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Print Controls (only on screen) -->
    <div class="print-controls no-print">
        <button onclick="window.print()" style="padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px; font-size: 14px;">
            🖨️ Print Report
        </button>
        <button onclick="window.close()" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            ❌ Close
        </button>
        <p style="margin-top: 10px; color: #666; font-size: 14px;">
            Report #: <?php echo htmlspecialchars($report['report_number']); ?> | 
            Generated: <?php echo date('F d, Y h:i A'); ?>
        </p>
    </div>

    <!-- Report Content -->
    <div id="printContent">
        <!-- Header with Logo -->
        <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px;">
            <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                <div style="margin-right: 20px;">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo $logo_url; ?>" alt="Barangay Logo" style="max-height: 100px; max-width: 150px;">
                    <?php else: ?>
                        <div style="width: 150px; height: 100px; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); 
                                    border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <span style="color: white; font-size: 14px; font-weight: bold; text-align: center;">
                                BARANGAY<br>LOGO
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 style="margin: 0; font-size: 28px; color: #1e40af;">BARANGAY OFFICIAL REPORT</h1>
                    <p style="margin: 5px 0 0 0; font-size: 16px; color: #666;">Law Enforcement and Incident Report</p>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 20px; font-size: 14px;">
                <div>
                    <strong>Report #:</strong> <?php echo htmlspecialchars($report['report_number']); ?>
                </div>
                <div>
                    <strong>Printed:</strong> <?php echo date('F d, Y h:i A'); ?>
                </div>
            </div>
        </div>

        <!-- Report Information -->
        <div style="margin-bottom: 30px;">
            <h2 style="background: #f3f4f6; padding: 10px; border-left: 4px solid #3b82f6; margin: 0 0 15px 0;">
                REPORT INFORMATION
            </h2>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; width: 30%; font-weight: bold;">Report Number:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($report['report_number']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Title:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($report['title']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Type:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($report['type_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Status:</td>
                    <td style="padding: 8px 0;">
                        <span style="padding: 4px 12px; background: 
                            <?php 
                            $status_colors = [
                                'pending' => '#fef3c7',
                                'assigned' => '#dbeafe',
                                'investigating' => '#ede9fe',
                                'resolved' => '#d1fae5',
                                'referred' => '#ffedd5',
                                'closed' => '#f3f4f6'
                            ];
                            echo $status_colors[$report['status']] ?? '#f3f4f6';
                            ?>; 
                            color: #000; border-radius: 20px; font-size: 12px;">
                            <?php echo strtoupper($report['status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Date & Time:</td>
                    <td style="padding: 8px 0;"><?php echo date('F d, Y h:i A', strtotime($report['incident_date'])); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Location:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($report['location']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Reported On:</td>
                    <td style="padding: 8px 0;"><?php echo date('F d, Y h:i A', strtotime($report['created_at'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Description -->
        <div style="margin-bottom: 30px;">
            <h2 style="background: #f3f4f6; padding: 10px; border-left: 4px solid #3b82f6; margin: 0 0 15px 0;">
                INCIDENT DESCRIPTION
            </h2>
            <div style="padding: 15px; background: #f9fafb; border-radius: 4px; white-space: pre-line;">
                <?php echo htmlspecialchars($report['description']); ?>
            </div>
        </div>

        <!-- Evidence Files - ACTUAL IMAGES DISPLAYED -->
        <?php if (!empty($evidence_files)): ?>
        <div class="page-break" style="margin-bottom: 30px;">
            <h2 style="background: #f3f4f6; padding: 10px; border-left: 4px solid #3b82f6; margin: 0 0 15px 0;">
                EVIDENCE FILES (<?php echo count($evidence_files); ?> files)
            </h2>
            
            <div class="evidence-grid">
                <?php foreach ($evidence_files as $file): 
                    $extension = strtolower(pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION));
                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                    $file_path = '../' . ltrim($file['path'], './');
                    $file_name = $file['original_name'] ?? 'Unnamed File';
                    $file_size = isset($file['file_size']) ? formatBytes($file['file_size']) : 'Unknown size';
                ?>
                    <div class="evidence-item">
                        <?php if ($is_image): ?>
                            <?php if (file_exists(str_replace('../', '', $file_path))): ?>
                                <img src="<?php echo $file_path; ?>" 
                                     alt="<?php echo htmlspecialchars($file_name); ?>"
                                     class="evidence-image"
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo getFileDisplayHTML($file_name, $file_size, $extension); ?>';">
                            <?php else: ?>
                                <?php echo getFileDisplayHTML($file_name, $file_size, $extension); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo getFileDisplayHTML($file_name, $file_size, $extension); ?>
                        <?php endif; ?>
                        
                        <div style="padding: 10px;">
                            <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold; word-break: break-all;">
                                <?php echo htmlspecialchars($file_name); ?>
                            </p>
                            <p style="margin: 0; font-size: 10px; color: #666;">
                                <?php echo strtoupper($extension); ?> • <?php echo $file_size; ?>
                                <?php if (isset($file['encrypted']) && $file['encrypted']): ?>
                                    <br><span style="color: #ff0000; font-size: 9px;">
                                        <i class="fas fa-lock"></i> Encrypted
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resolution Notes -->
        <?php if (!empty($report['resolution_notes'])): ?>
        <div style="margin-bottom: 30px; padding: 15px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; color: #065f46;">RESOLUTION NOTES</h3>
            <p style="margin: 0; color: #065f46; white-space: pre-line;"><?php echo htmlspecialchars($report['resolution_notes']); ?></p>
            <?php if (!empty($report['resolved_at'])): ?>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #047857;">
                Resolved on: <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="margin-top: 50px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
            <div style="text-align: center; color: #6b7280; font-size: 12px;">
                <p>This document is computer-generated and does not require a signature.</p>
                <p>Barangay Official Report System - <?php echo date('Y'); ?></p>
                <p style="margin-top: 10px; font-size: 10px;">
                    Report ID: <?php echo htmlspecialchars($report['report_number']); ?> | 
                    Printed: <?php echo date('Y-m-d H:i:s'); ?> |
                    Attachments: <?php echo count($evidence_files); ?> file(s)
                </p>
            </div>
        </div>
    </div>

    <script>
    // Auto-print when page loads (optional)
    window.onload = function() {
        // You can enable auto-print if desired
        // window.print();
    };
    </script>
</body>
</html>
<?php
// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Helper function to get file display HTML
function getFileDisplayHTML($file_name, $file_size, $extension) {
    $icon = 'fa-file';
    $color = '#6b7280';
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        $icon = 'fa-image';
        $color = '#3b82f6';
    } elseif ($extension === 'pdf') {
        $icon = 'fa-file-pdf';
        $color = '#ef4444';
    } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv'])) {
        $icon = 'fa-file-video';
        $color = '#8b5cf6';
    } elseif (in_array($extension, ['mp3', 'wav', 'ogg'])) {
        $icon = 'fa-file-audio';
        $color = '#10b981';
    } elseif (in_array($extension, ['doc', 'docx'])) {
        $icon = 'fa-file-word';
        $color = '#2563eb';
    } elseif (in_array($extension, ['xls', 'xlsx'])) {
        $icon = 'fa-file-excel';
        $color = '#16a34a';
    }
    
    return '
        <div class="evidence-placeholder">
            <i class="fas ' . $icon . '" style="font-size: 36px; color: ' . $color . '; margin-bottom: 10px;"></i>
            <span style="font-size: 11px; color: #666;">' . strtoupper($extension) . '</span>
            <span style="font-size: 10px; color: #999; margin-top: 5px;">' . $file_size . '</span>
        </div>
    ';
}
?>