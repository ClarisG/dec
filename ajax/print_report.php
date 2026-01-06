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
                 u.first_name, u.last_name, u.email, u.phone, u.address,
                 CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM reports r 
          LEFT JOIN report_types rt ON r.type_id = rt.id 
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

// Get attachments
$attachments = [];
$attachment_query = "SELECT * FROM report_attachments WHERE report_id = ?";
$attachment_stmt = $conn->prepare($attachment_query);
$attachment_stmt->bind_param("i", $report_id);
$attachment_stmt->execute();
$attachment_result = $attachment_stmt->get_result();

while ($attachment = $attachment_result->fetch_assoc()) {
    $attachments[] = $attachment;
}

// Get timeline
$timeline = [];
$timeline_query = "SELECT * FROM report_timeline WHERE report_id = ? ORDER BY created_at ASC";
$timeline_stmt = $conn->prepare($timeline_query);
$timeline_stmt->bind_param("i", $report_id);
$timeline_stmt->execute();
$timeline_result = $timeline_stmt->get_result();

while ($event = $timeline_result->fetch_assoc()) {
    $timeline[] = $event;
}
?>

<div id="printContent">
    <!-- Header -->
    <div class="print-header">
        <div class="header-top">
            <!-- Logo placeholder - you should replace with your actual logo -->
            <div class="header-logo">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); 
                            border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 12px; font-weight: bold; text-align: center;">
                        BARANGAY<br>LOGO
                    </span>
                </div>
            </div>
            <div class="header-text">
                <h1 class="header-title">BARANGAY OFFICIAL REPORT</h1>
                <p class="header-subtitle">Law Enforcement and Incident Report</p>
            </div>
        </div>
        <div class="header-info">
            <div>
                <strong>Report #:</strong> <?php echo htmlspecialchars($report['report_number']); ?>
            </div>
            <div>
                <strong>Printed:</strong> <?php echo date('F d, Y h:i A'); ?>
            </div>
        </div>
    </div>

    <!-- Report Information -->
    <div class="print-section">
        <div class="section-title">REPORT INFORMATION</div>
        
        <div class="info-row">
            <div class="info-label">Report Number:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['report_number']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Title:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['title']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Type:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['type_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">
                <?php 
                $status_class = 'status-' . strtolower(str_replace(' ', '-', $report['status']));
                echo '<span class="timeline-status ' . $status_class . '">' . htmlspecialchars($report['status']) . '</span>';
                ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Date & Time:</div>
            <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($report['incident_datetime'])); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Location:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['location']); ?></div>
        </div>
    </div>

    <!-- Description -->
    <div class="print-section">
        <div class="section-title">INCIDENT DESCRIPTION</div>
        <div style="padding: 0 15px;">
            <?php echo nl2br(htmlspecialchars($report['description'])); ?>
        </div>
    </div>

    <!-- Reporter Information -->
    <div class="print-section">
        <div class="section-title">REPORTER INFORMATION</div>
        
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['user_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['email']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['phone']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Address:</div>
            <div class="info-value"><?php echo htmlspecialchars($report['address']); ?></div>
        </div>
    </div>

    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
    <div class="print-section">
        <div class="section-title">ATTACHMENTS</div>
        <div class="attachment-grid">
            <?php foreach ($attachments as $attachment): 
                $file_path = '../uploads/reports/' . $attachment['file_name'];
                $file_ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            ?>
                <div class="attachment-item">
                    <?php if ($is_image && file_exists($file_path)): ?>
                        <img src="<?php echo $file_path; ?>" 
                             alt="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                             class="attachment-preview"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2YzZjRmNiIvPjx0ZXh0IHg9IjEwMCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM5Y2EwYTYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZTwvdGV4dD48L3N2Zz4='">
                    <?php else: ?>
                        <div class="attachment-preview" style="display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-alt" style="font-size: 48px; color: #6b7280;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="attachment-name">
                        <?php echo htmlspecialchars($attachment['original_name']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Timeline -->
    <?php if (!empty($timeline)): ?>
    <div class="print-section">
        <div class="section-title">REPORT TIMELINE</div>
        <div class="timeline">
            <?php foreach ($timeline as $event): 
                $status_class = 'status-' . strtolower(str_replace(' ', '-', $event['status']));
            ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <?php echo date('F d, Y h:i A', strtotime($event['created_at'])); ?>
                        <?php if ($event['status']): ?>
                            <span class="timeline-status <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($event['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-content">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                        <?php if ($event['officer_name']): ?>
                            <div style="margin-top: 5px; font-size: 12px; color: #6b7280;">
                                <strong>Officer:</strong> <?php echo htmlspecialchars($event['officer_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="margin-top: 50px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
        <div style="text-align: center; color: #6b7280; font-size: 12px;">
            <p>This document is computer-generated and does not require a signature.</p>
            <p>Barangay Official Report System - <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>