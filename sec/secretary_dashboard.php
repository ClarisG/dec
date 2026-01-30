<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has secretary role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$barangay = $_SESSION['barangay'] ?? '';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$error = '';
$current_date = date('Y-m-d');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_report_status'])) {
        // Update report status
        $report_id = intval($_POST['report_id']);
        $new_status = $conn->real_escape_string($_POST['status']);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        
        // Check if report belongs to secretary's barangay
        $check_sql = "SELECT id FROM reports WHERE id = ? AND barangay = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $report_id, $barangay);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            // Update report status
            $update_sql = "UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_status, $report_id);
            
            // Add to status history
            $history_sql = "INSERT INTO report_status_history (report_id, status, updated_by, notes, created_at) 
                           VALUES (?, ?, ?, ?, NOW())";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param("isis", $report_id, $new_status, $user_id, $notes);
            
            if ($update_stmt->execute() && $history_stmt->execute()) {
                $message = "Report status updated successfully!";
                
                // Create notification for report owner
                $notif_sql = "INSERT INTO user_notifications (user_id, title, message, type, related_id, related_type, created_at)
                             SELECT user_id, 'Report Status Updated', 'Your report status has been changed to: $new_status', 
                             'info', ?, 'report', NOW() FROM reports WHERE id = ?";
                $notif_stmt = $conn->prepare($notif_sql);
                $notif_stmt->bind_param("ii", $report_id, $report_id);
                $notif_stmt->execute();
            } else {
                $error = "Failed to update report status: " . $conn->error;
            }
        } else {
            $error = "Report not found or unauthorized access!";
        }
    }
    
    if (isset($_POST['create_announcement'])) {
        // Create new announcement
        $title = $conn->real_escape_string($_POST['title']);
        $content = $conn->real_escape_string($_POST['content']);
        $priority = $conn->real_escape_string($_POST['priority']);
        $target_role = $conn->real_escape_string($_POST['target_role']);
        $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        // Get user info for posted_by
        $user_sql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_row = $user_result->fetch_assoc();
        $posted_by = $user_row['full_name'];
        
        $announcement_sql = "INSERT INTO announcements (title, content, priority, target_role, barangay, 
                           is_emergency, is_pinned, posted_by, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $announcement_stmt = $conn->prepare($announcement_sql);
        $announcement_stmt->bind_param("sssssiss", $title, $content, $priority, $target_role, 
                                      $barangay, $is_emergency, $is_pinned, $posted_by);
        
        if ($announcement_stmt->execute()) {
            $message = "Announcement created successfully!";
            
            // Log activity
            $activity_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
                           VALUES (?, 'announcement_create', ?, ?, NOW())";
            $activity_stmt = $conn->prepare($activity_sql);
            $description = "Created announcement: $title";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $activity_stmt->bind_param("iss", $user_id, $description, $ip_address);
            $activity_stmt->execute();
        } else {
            $error = "Failed to create announcement: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_announcement'])) {
        // Update announcement status
        $announcement_id = intval($_POST['announcement_id']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $update_sql = "UPDATE announcements SET is_active = ?, updated_at = NOW() WHERE id = ? AND barangay = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iis", $is_active, $announcement_id, $barangay);
        
        if ($update_stmt->execute()) {
            $message = "Announcement updated successfully!";
        } else {
            $error = "Failed to update announcement: " . $conn->error;
        }
    }
}

// Fetch dashboard statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM reports WHERE barangay = ? AND status = 'pending') as pending_reports,
    (SELECT COUNT(*) FROM reports WHERE barangay = ? AND status = 'assigned') as assigned_reports,
    (SELECT COUNT(*) FROM reports WHERE barangay = ? AND status = 'investigating') as investigating_reports,
    (SELECT COUNT(*) FROM reports WHERE barangay = ? AND status = 'resolved') as resolved_reports,
    (SELECT COUNT(*) FROM announcements WHERE barangay = ? AND is_active = 1) as active_announcements,
    (SELECT COUNT(*) FROM users WHERE barangay = ? AND user_type = 'citizen' AND status = 'active') as total_citizens";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("ssssss", $barangay, $barangay, $barangay, $barangay, $barangay, $barangay);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Fetch recent reports (last 10)
$reports_sql = "SELECT r.*, rt.type_name, u.first_name, u.last_name 
                FROM reports r 
                JOIN report_types rt ON r.report_type_id = rt.id 
                JOIN users u ON r.user_id = u.id 
                WHERE r.barangay = ? 
                ORDER BY r.created_at DESC 
                LIMIT 10";
$reports_stmt = $conn->prepare($reports_sql);
$reports_stmt->bind_param("s", $barangay);
$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();

// Fetch active announcements
$announcements_sql = "SELECT * FROM announcements 
                     WHERE barangay = ? AND is_active = 1 
                     ORDER BY is_pinned DESC, created_at DESC 
                     LIMIT 5";
$announcements_stmt = $conn->prepare($announcements_sql);
$announcements_stmt->bind_param("s", $barangay);
$announcements_stmt->execute();
$announcements_result = $announcements_stmt->get_result();

// Fetch report types for filtering
$report_types_sql = "SELECT * FROM report_types ORDER BY category, type_name";
$report_types_result = $conn->query($report_types_sql);

// Fetch user info for the header
$user_sql = "SELECT first_name, last_name, email FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - Barangay Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
        }
        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #1a252f);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background-color: #f8f9fa;
            border-left: 3px solid var(--secondary-color);
            color: var(--secondary-color);
        }
        .sidebar .nav-link.active {
            background-color: #e3f2fd;
            border-left: 3px solid var(--secondary-color);
            color: var(--secondary-color);
            font-weight: 500;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            border-left: 4px solid var(--secondary-color);
        }
        .stat-card.pending {
            border-left-color: #f39c12;
        }
        .stat-card.resolved {
            border-left-color: #27ae60;
        }
        .badge-status {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .badge-pending { background-color: #f39c12; }
        .badge-assigned { background-color: #3498db; }
        .badge-investigating { background-color: #9b59b6; }
        .badge-resolved { background-color: #27ae60; }
        .badge-closed { background-color: #7f8c8d; }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            border: none;
            padding: 8px 20px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #1a252f);
            color: white;
        }
        .search-box {
            max-width: 300px;
        }
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            .search-box {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-building"></i> Barangay Secretary Dashboard
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> 
                    <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 p-0">
                <div class="sidebar">
                    <div class="p-3">
                        <h5 class="text-muted mb-3">Navigation</h5>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="#">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                    <i class="bi bi-megaphone"></i> Create Announcement
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="reports.php">
                                    <i class="bi bi-file-text"></i> All Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="announcements.php">
                                    <i class="bi bi-newspaper"></i> Announcements
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="citizens.php">
                                    <i class="bi bi-people"></i> Citizens
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="profile.php">
                                    <i class="bi bi-person"></i> My Profile
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 p-4">
                <!-- Alerts -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Dashboard</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-geo-alt"></i> Barangay: <?php echo htmlspecialchars($barangay); ?>
                        </p>
                    </div>
                    <div class="search-box">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search reports...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending</h6>
                                        <h3 class="mb-0"><?php echo $stats['pending_reports']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-warning">
                                        <i class="bi bi-clock text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Assigned</h6>
                                        <h3 class="mb-0"><?php echo $stats['assigned_reports']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-info">
                                        <i class="bi bi-person-check text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Investigating</h6>
                                        <h3 class="mb-0"><?php echo $stats['investigating_reports']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-purple">
                                        <i class="bi bi-search text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card stat-card resolved">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Resolved</h6>
                                        <h3 class="mb-0"><?php echo $stats['resolved_reports']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-success">
                                        <i class="bi bi-check-circle text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Announcements</h6>
                                        <h3 class="mb-0"><?php echo $stats['active_announcements']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-secondary">
                                        <i class="bi bi-megaphone text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Citizens</h6>
                                        <h3 class="mb-0"><?php echo $stats['total_citizens']; ?></h3>
                                    </div>
                                    <div class="icon-circle bg-primary">
                                        <i class="bi bi-people text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Recent Reports -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Reports</h5>
                                <a href="reports.php" class="btn btn-sm btn-outline-primary">
                                    View All <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Report #</th>
                                                <th>Type</th>
                                                <th>Title</th>
                                                <th>Reporter</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($report = $reports_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($report['report_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($report['type_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($report['title'], 0, 30)) . (strlen($report['title']) > 30 ? '...' : ''); ?></td>
                                                    <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'badge-pending';
                                                        if ($report['status'] === 'assigned') $status_class = 'badge-assigned';
                                                        elseif ($report['status'] === 'investigating') $status_class = 'badge-investigating';
                                                        elseif ($report['status'] === 'resolved') $status_class = 'badge-resolved';
                                                        elseif ($report['status'] === 'closed') $status_class = 'badge-closed';
                                                        ?>
                                                        <span class="badge badge-status <?php echo $status_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#updateStatusModal"
                                                                data-report-id="<?php echo $report['id']; ?>"
                                                                data-current-status="<?php echo $report['status']; ?>">
                                                            <i class="bi bi-pencil"></i> Update
                                                        </button>
                                                        <a href="view_report.php?id=<?php echo $report['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Announcements & Quick Actions -->
                    <div class="col-lg-4">
                        <!-- Active Announcements -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Active Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($announcements_result->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                                            <div class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex align-items-start">
                                                    <?php if ($announcement['is_pinned']): ?>
                                                        <i class="bi bi-pin-angle-fill text-warning me-2 mt-1"></i>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar"></i> 
                                                            <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                            <?php if ($announcement['is_emergency']): ?>
                                                                <span class="badge bg-danger ms-2">Emergency</span>
                                                            <?php endif; ?>
                                                        </small>
                                                        <p class="mt-2 mb-0 small">
                                                            <?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . '...'; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No active announcements.</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="announcements.php" class="btn btn-sm btn-outline-primary w-100">
                                    Manage Announcements
                                </a>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                        <i class="bi bi-megaphone"></i> Create Announcement
                                    </button>
                                    <a href="reports.php?filter=pending" class="btn btn-outline-primary">
                                        <i class="bi bi-clock"></i> View Pending Reports
                                    </a>
                                    <a href="citizens.php" class="btn btn-outline-primary">
                                        <i class="bi bi-people"></i> View Citizens List
                                    </a>
                                    <a href="generate_report.php" class="btn btn-outline-success">
                                        <i class="bi bi-file-pdf"></i> Generate Monthly Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Report Status</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="modalReportId">
                        <input type="hidden" name="update_report_status" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="modalCurrentStatus" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="investigating">Investigating</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                                <option value="referred">Referred</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="Add any notes about the status update..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Announcement</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="create_announcement" value="1">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       placeholder="Enter announcement title">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="6" required 
                                      placeholder="Enter announcement content..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-select" name="target_role">
                                    <option value="all">All Users</option>
                                    <option value="citizen">Citizens Only</option>
                                    <option value="tanod">Tanod Only</option>
                                    <option value="secretary">Secretary Only</option>
                                    <option value="captain">Captain Only</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_emergency" id="emergencyCheck">
                                    <label class="form-check-label" for="emergencyCheck">
                                        Mark as Emergency Announcement
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_pinned" id="pinnedCheck">
                                    <label class="form-check-label" for="pinnedCheck">
                                        Pin to Top
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Publish Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update Status Modal Handler
        document.addEventListener('DOMContentLoaded', function() {
            var updateStatusModal = document.getElementById('updateStatusModal');
            if (updateStatusModal) {
                updateStatusModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    var reportId = button.getAttribute('data-report-id');
                    var currentStatus = button.getAttribute('data-current-status');
                    
                    document.getElementById('modalReportId').value = reportId;
                    document.getElementById('modalCurrentStatus').value = 
                        currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1).replace('_', ' ');
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>