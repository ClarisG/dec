<?php
// modules/citizen_my_reports.php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// citizen_my_report.php - My Reports Module with Enhanced Viewing and Management
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Get base URL
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $base_path = rtrim(str_replace('/modules', '', $script_path), '/');
    define('BASE_URL', $protocol . "://" . $host . $base_path);
}

// Define AJAX URL
define('AJAX_URL', BASE_URL . '/ajax/');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("User not logged in");
}

$error = '';
$success = '';
$reports = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'assigned' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'referred' => 0,
    'closed' => 0
];

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination settings
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $records_per_page;

try {
    $conn = getDbConnection();
    
    // Main query to get reports for the logged-in user
    $query = "SELECT 
        r.id,
        r.report_number,
        r.title,
        r.description,
        r.location,
        r.incident_date,
        r.status,
        r.created_at,
        r.category,
        r.is_anonymous,
        r.evidence_files,
        r.priority,
        rt.type_name,
        rt.jurisdiction,
        u.first_name,
        u.last_name
    FROM reports r
    LEFT JOIN report_types rt ON r.report_type_id = rt.id
    LEFT JOIN users u ON r.assigned_to = u.id
    WHERE r.user_id = :user_id";
    
    $params = [':user_id' => $user_id];
    
    // Apply status filter
    if ($status_filter != 'all' && !empty($status_filter)) {
        $query .= " AND r.status = :status";
        $params[':status'] = $status_filter;
    }
    
    // Apply category filter
    if ($category_filter != 'all' && !empty($category_filter)) {
        $query .= " AND r.category = :category";
        $params[':category'] = $category_filter;
    }
    
    // Apply date filters
    if (!empty($date_from)) {
        $query .= " AND DATE(r.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(r.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    // Count total records for pagination (without LIMIT)
    $count_query = str_replace(
        "SELECT r.id, r.report_number, r.title, r.description, r.location, r.incident_date, r.status, r.created_at, r.category, r.is_anonymous, r.evidence_files, r.priority, rt.type_name, rt.jurisdiction, u.first_name, u.last_name",
        "SELECT COUNT(*) as total",
        $query
    );
    
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);
    
    // Ensure current page is within bounds
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $records_per_page;
    }
    
    // Add pagination to main query
    $query .= " LIMIT :limit OFFSET :offset";
    $params[':limit'] = $records_per_page;
    $params[':offset'] = $offset;
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters with types for LIMIT and OFFSET
    foreach ($params as $key => $value) {
        $type = PDO::PARAM_STR;
        if ($key === ':limit' || $key === ':offset') {
            $type = PDO::PARAM_INT;
        }
        $stmt->bindValue($key, $value, $type);
    }
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'referred' THEN 1 ELSE 0 END) as referred,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM reports WHERE user_id = :user_id";
    
    // Add filters to stats if needed
    if ($status_filter != 'all' && !empty($status_filter)) {
        $stats_query .= " AND status = :status";
    }
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_params = [':user_id' => $user_id];
    if ($status_filter != 'all' && !empty($status_filter)) {
        $stats_params[':status'] = $status_filter;
    }
    $stats_stmt->execute($stats_params);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get report status history, parse evidence files, and count unread messages
    foreach ($reports as &$report) {
        // Get status history
        $history_query = "SELECT * FROM report_status_history 
                         WHERE report_id = :report_id 
                         ORDER BY created_at DESC";
        $history_stmt = $conn->prepare($history_query);
        $history_stmt->execute([':report_id' => $report['id']]);
        $report['status_history'] = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse evidence files
        $report['evidence_files_parsed'] = [];
        if (!empty($report['evidence_files'])) {
            $report['evidence_files_parsed'] = json_decode($report['evidence_files'], true);
        }
        
        // Get unread messages count
        $messages_query = "SELECT COUNT(*) as unread_count FROM messages 
                          WHERE report_id = :report_id AND is_read = 0 AND receiver_id = :receiver_id";
        $messages_stmt = $conn->prepare($messages_query);
        $messages_stmt->execute([':report_id' => $report['id'], ':receiver_id' => $user_id]);
        $messages_result = $messages_stmt->fetch(PDO::FETCH_ASSOC);
        $report['unread_messages'] = $messages_result['unread_count'] ?? 0;
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("My Reports Error: " . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div class="flex-1">
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <div class="flex-1">
                    <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <!-- Total Reports -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                    <i class="fas fa-file-alt text-blue-600 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total Reports</p>
                    <p class="text-xl font-bold text-gray-800"><?php echo $stats['total'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Pending -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                    <i class="fas fa-clock text-yellow-600 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Pending</p>
                    <p class="text-xl font-bold text-gray-800"><?php echo $stats['pending'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <!-- In Progress -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                    <i class="fas fa-spinner text-purple-600 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">In Progress</p>
                    <?php 
                    $in_progress = ($stats['assigned'] ?? 0) + ($stats['investigating'] ?? 0); 
                    ?>
                    <p class="text-xl font-bold text-gray-800"><?php echo $in_progress; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Resolved -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Resolved</p>
                    <?php 
                    $resolved = ($stats['resolved'] ?? 0) + ($stats['closed'] ?? 0); 
                    ?>
                    <p class="text-xl font-bold text-gray-800"><?php echo $resolved; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
        <h3 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">Filter Reports</h3>
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-3" id="filterForm">
            <input type="hidden" name="module" value="my-reports">
            
            <!-- Status Filter -->
            <div>
                <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500" style="font-size: 16px;">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="assigned" <?php echo $status_filter == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="investigating" <?php echo $status_filter == 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="referred" <?php echo $status_filter == 'referred' ? 'selected' : ''; ?>>Referred</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            
            <!-- Category Filter -->
            <div>
                <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500" style="font-size: 16px;">
                    <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="incident" <?php echo $category_filter == 'incident' ? 'selected' : ''; ?>>Incident Reports</option>
                    <option value="complaint" <?php echo $category_filter == 'complaint' ? 'selected' : ''; ?>>Complaints</option>
                    <option value="blotter" <?php echo $category_filter == 'blotter' ? 'selected' : ''; ?>>Blotter</option>
                </select>
            </div>
            
            <!-- Date From -->
            <div>
                <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500" style="font-size: 16px;">
            </div>
            
            <!-- Date To -->
            <div>
                <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500" style="font-size: 16px;">
            </div>
            
            <!-- Filter Buttons -->
            <div class="md:col-span-4 flex justify-end space-x-2 pt-3 border-t">
                <a href="?module=my-reports" class="px-4 py-3 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 active:bg-gray-100 min-h-[44px] flex items-center justify-center">
                    Clear
                </a>
                <button type="submit" class="px-4 py-3 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-500 min-h-[44px] flex items-center justify-center">
                    <i class="fas fa-filter mr-2"></i> Apply
                </button>
            </div>
        </form>
    </div>
    
    <!-- Reports List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <!-- Table Header -->
        <div class="px-4 py-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-gray-800 text-sm md:text-base">My Submitted Reports</h3>
                <p class="text-xs text-gray-500">
                    Showing <?php echo count($reports); ?> of <?php echo $total_records; ?> report(s) 
                    (Page <?php echo $current_page; ?> of <?php echo max(1, $total_pages); ?>)
                </p>
            </div>
            
            <!-- Pagination Info -->
            <?php if ($total_records > 0): ?>
            <div class="mt-2 sm:mt-0 text-xs text-gray-500">
                Reports <?php echo $offset + 1; ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($reports)): ?>
            <!-- Empty State -->
            <div class="p-8 text-center">
                <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                    <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-medium text-gray-700 mb-2">No reports found</h3>
                <p class="text-gray-500 text-sm mb-4">You haven't submitted any reports yet.</p>
                <a href="?module=new-report" class="inline-flex items-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 text-sm min-h-[44px]">
                    <i class="fas fa-plus mr-2"></i> Submit New Report
                </a>
            </div>
        <?php else: ?>
            <!-- Reports List - Mobile View (Enhanced for Touch) -->
            <div class="md:hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($reports as $index => $report): ?>
                        <?php
                        // Determine status colors
                        $status_colors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'assigned' => 'bg-blue-100 text-blue-800',
                            'investigating' => 'bg-purple-100 text-purple-800',
                            'resolved' => 'bg-green-100 text-green-800',
                            'referred' => 'bg-orange-100 text-orange-800',
                            'closed' => 'bg-gray-100 text-gray-800'
                        ];
                        
                        $status_color = $status_colors[$report['status']] ?? 'bg-gray-100 text-gray-800';
                        
                        // Format dates
                        $created_date = date('M d, Y', strtotime($report['created_at']));
                        $incident_date = !empty($report['incident_date']) && $report['incident_date'] != '0000-00-00' ? 
                            date('M d, Y', strtotime($report['incident_date'])) : 'Not specified';
                        
                        // Get category display name
                        $category_names = [
                            'incident' => 'Incident Report',
                            'complaint' => 'Complaint Report',
                            'blotter' => 'Blotter Report'
                        ];
                        $category_name = $category_names[$report['category']] ?? 'Report';
                        ?>
                        
                        <div class="p-4 touch-manipulation mobile-report-card" data-report-id="<?php echo $report['id']; ?>">
                            <!-- Report Header with Status -->
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 mr-2">
                                    <!-- Report Title -->
                                    <h4 class="font-bold text-gray-900 text-base mb-1">
                                        <?php echo htmlspecialchars($report['title']); ?>
                                    </h4>
                                    
                                    <!-- Report Number and Status -->
                                    <div class="flex items-center mb-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_color; ?> mr-2">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                        <span class="text-xs text-gray-600">
                                            <i class="fas fa-hashtag mr-1"></i><?php echo htmlspecialchars($report['report_number']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Quick View Icon -->
                                <button onclick="mobileViewReportDetails(<?php echo $report['id']; ?>);" 
                                        class="ml-2 p-2 text-blue-600 hover:text-blue-800 active:text-blue-900 min-h-[44px] min-w-[44px] flex items-center justify-center">
                                    <i class="fas fa-external-link-alt text-lg"></i>
                                </button>
                            </div>
                            
                            <!-- Report Details -->
                            <div class="space-y-2 mb-4">
                                <!-- Report Type -->
                                <div class="flex items-center text-sm text-gray-700">
                                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                                        <?php if ($report['category'] == 'incident'): ?>
                                            <i class="fas fa-exclamation-triangle text-red-500 text-xs"></i>
                                        <?php elseif ($report['category'] == 'complaint'): ?>
                                            <i class="fas fa-comments text-yellow-500 text-xs"></i>
                                        <?php elseif ($report['category'] == 'blotter'): ?>
                                            <i class="fas fa-file-alt text-green-500 text-xs"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file text-gray-500 text-xs"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="truncate"><?php echo htmlspecialchars($report['type_name']); ?></span>
                                </div>
                                
                                <!-- Incident Date -->
                                <div class="flex items-center text-sm text-gray-700">
                                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                                        <i class="fas fa-calendar text-blue-500 text-xs"></i>
                                    </div>
                                    <span><?php echo $incident_date; ?></span>
                                </div>
                                
                                <!-- Location -->
                                <div class="flex items-center text-sm text-gray-700">
                                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-green-500 text-xs"></i>
                                    </div>
                                    <span class="truncate"><?php echo htmlspecialchars($report['location']); ?></span>
                                </div>
                                
                                <!-- Assigned Officer (if any) -->
                                <?php if (!empty($report['first_name'])): ?>
                                    <div class="flex items-center text-sm text-gray-700">
                                        <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                                            <i class="fas fa-user-check text-purple-500 text-xs"></i>
                                        </div>
                                        <span class="truncate"><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Unread Messages -->
                                <?php if ($report['unread_messages'] > 0): ?>
                                    <div class="flex items-center text-sm font-medium text-blue-600">
                                        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center mr-2 flex-shrink-0">
                                            <i class="fas fa-envelope text-blue-600 text-xs"></i>
                                        </div>
                                        <span><?php echo $report['unread_messages']; ?> new message<?php echo $report['unread_messages'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Evidence Files -->
                                <?php if (!empty($report['evidence_files_parsed'])): ?>
                                    <div class="flex items-center text-sm text-gray-700">
                                        <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                                            <i class="fas fa-paperclip text-gray-500 text-xs"></i>
                                        </div>
                                        <span><?php echo count($report['evidence_files_parsed']); ?> attachment<?php echo count($report['evidence_files_parsed']) > 1 ? 's' : ''; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-2 mt-4">
                                <!-- View Button -->
                                <button onclick="mobileViewReportDetails(<?php echo $report['id']; ?>);" 
                                        class="flex-1 px-3 py-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-100 active:bg-blue-200 active:scale-95 transition-transform duration-150 min-h-[44px] touch-button flex flex-col items-center justify-center">
                                    <i class="fas fa-eye text-base mb-1"></i>
                                    <span class="text-xs font-medium">View</span>
                                </button>
                                
                                <!-- Timeline Button -->
                                <button onclick="mobileViewReportTimeline(<?php echo $report['id']; ?>);" 
                                        class="flex-1 px-3 py-3 bg-gray-50 border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100 active:bg-gray-200 active:scale-95 transition-transform duration-150 min-h-[44px] touch-button flex flex-col items-center justify-center">
                                    <i class="fas fa-history text-base mb-1"></i>
                                    <span class="text-xs font-medium">Timeline</span>
                                </button>
                                
                                <!-- Print Button -->
                                <button onclick="mobilePrintReport(<?php echo $report['id']; ?>);" 
                                        class="flex-1 px-3 py-3 bg-green-500 border border-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-600 active:bg-green-700 active:scale-95 transition-transform duration-150 min-h-[44px] touch-button flex flex-col items-center justify-center">
                                    <i class="fas fa-print text-base mb-1"></i>
                                    <span class="text-xs font-medium">Print</span>
                                </button>
                            </div>
                            
                            <!-- Additional Quick Actions -->
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="flex justify-between items-center">
                                    <!-- Report Date -->
                                    <div class="text-xs text-gray-500">
                                        <i class="far fa-clock mr-1"></i>
                                        Submitted: <?php echo $created_date; ?>
                                    </div>
                                    
                                    <!-- Priority Badge -->
                                    <?php if (!empty($report['priority']) && $report['priority'] != 'normal'): ?>
                                        <?php
                                        $priority_colors = [
                                            'low' => 'bg-gray-100 text-gray-800',
                                            'normal' => 'bg-blue-100 text-blue-800',
                                            'high' => 'bg-orange-100 text-orange-800',
                                            'urgent' => 'bg-red-100 text-red-800'
                                        ];
                                        $priority_color = $priority_colors[$report['priority']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $priority_color; ?>">
                                            <i class="fas fa-flag mr-1 text-xs"></i>
                                            <?php echo ucfirst($report['priority']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Reports List - Desktop View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report Details</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reports as $index => $report): ?>
                            <?php
                            // Determine status colors
                            $status_colors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'assigned' => 'bg-blue-100 text-blue-800',
                                'investigating' => 'bg-purple-100 text-purple-800',
                                'resolved' => 'bg-green-100 text-green-800',
                                'referred' => 'bg-orange-100 text-orange-800',
                                'closed' => 'bg-gray-100 text-gray-800'
                            ];
                            
                            $status_color = $status_colors[$report['status']] ?? 'bg-gray-100 text-gray-800';
                            
                            // Determine category icon
                            $category_icons = [
                                'incident' => 'fas fa-exclamation-triangle text-red-500',
                                'complaint' => 'fas fa-comments text-yellow-500',
                                'blotter' => 'fas fa-file-alt text-green-500'
                            ];
                            
                            $category_icon = $category_icons[$report['category']] ?? 'fas fa-file text-gray-500';
                            
                            // Format date
                            $created_date = date('M d, Y', strtotime($report['created_at']));
                            $created_time = date('h:i A', strtotime($report['created_at']));
                            ?>
                            
                            <tr class="hover:bg-gray-50">
                                <!-- Report Details -->
                                <td class="px-4 py-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mr-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="<?php echo $category_icon; ?> text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center mb-1">
                                                <h4 class="font-medium text-gray-900 truncate text-sm">
                                                    <a href="#" onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" class="hover:text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($report['title']); ?>
                                                    </a>
                                                </h4>
                                                <?php if ($report['is_anonymous']): ?>
                                                    <span class="ml-2 px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">
                                                        <i class="fas fa-user-secret mr-0.5"></i> Anonymous
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-500 truncate mb-1">
                                                <?php echo htmlspecialchars($report['type_name']); ?>
                                            </p>
                                            <div class="flex items-center text-xs text-gray-500">
                                                <span class="mr-3">
                                                    <i class="fas fa-hashtag mr-1"></i> <?php echo htmlspecialchars($report['report_number']); ?>
                                                </span>
                                                <span class="truncate max-w-xs">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> 
                                                    <?php echo htmlspecialchars($report['location']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Status -->
                                <td class="px-4 py-4">
                                    <div class="flex flex-col space-y-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                        <?php if (!empty($report['first_name'])): ?>
                                            <div class="text-xs text-gray-500">
                                                <i class="fas fa-user-check mr-1"></i>
                                                <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($report['unread_messages'] > 0): ?>
                                            <span class="inline-flex items-center text-xs text-blue-600 font-medium">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <?php echo $report['unread_messages']; ?> new
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Date -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $created_date; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $created_time; ?></div>
                                </td>
                                
                                <!-- Actions -->
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" 
                                                class="px-3 py-2 bg-blue-50 text-blue-700 rounded text-sm hover:bg-blue-100 min-h-[36px] min-w-[70px] flex items-center justify-center">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        <button onclick="viewReportTimeline(<?php echo $report['id'] ?>); return false;" 
                                                class="px-3 py-2 bg-gray-50 text-gray-700 rounded text-sm hover:bg-gray-100 min-h-[36px] min-w-[70px] flex items-center justify-center">
                                            <i class="fas fa-history mr-1"></i> Timeline
                                        </button>
                                        <button onclick="printReport(<?php echo $report['id']; ?>); return false;" 
                                                class="px-3 py-2 bg-green-500 text-white rounded text-sm hover:bg-green-600 min-h-[36px] min-w-[70px] flex items-center justify-center">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 border-t">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-500 mb-3 sm:mb-0">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </div>
                    
                    <div class="flex items-center space-x-1">
                        <!-- First Page -->
                        <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=1"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-l-lg <?php echo $current_page == 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> min-h-[36px] min-w-[36px] flex items-center justify-center"
                           <?php echo $current_page == 1 ? 'onclick="return false;"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- Previous Page -->
                        <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=<?php echo max(1, $current_page - 1); ?>"
                           class="px-3 py-2 text-sm border border-gray-300 <?php echo $current_page == 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> min-h-[36px] min-w-[36px] flex items-center justify-center"
                           <?php echo $current_page == 1 ? 'onclick="return false;"' : ''; ?>>
                            <i class="fas fa-angle-left"></i>
                        </a>
                        
                        <!-- Page Numbers -->
                        <?php
                        // Calculate page range to show
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Adjust if we're near the beginning
                        if ($start_page == 1) {
                            $end_page = min(5, $total_pages);
                        }
                        
                        // Adjust if we're near the end
                        if ($end_page == $total_pages) {
                            $start_page = max(1, $total_pages - 4);
                        }
                        
                        // Show first page with ellipsis if needed
                        if ($start_page > 1): ?>
                            <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=1"
                               class="px-3 py-2 text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 min-h-[36px] min-w-[36px] flex items-center justify-center">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                                <span class="px-2 py-2 text-sm text-gray-500">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=<?php echo $i; ?>"
                               class="px-3 py-2 text-sm border border-gray-300 <?php echo $i == $current_page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> min-h-[36px] min-w-[36px] flex items-center justify-center">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- Show last page with ellipsis if needed -->
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="px-2 py-2 text-sm text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=<?php echo $total_pages; ?>"
                               class="px-3 py-2 text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 min-h-[36px] min-w-[36px] flex items-center justify-center">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=<?php echo min($total_pages, $current_page + 1); ?>"
                           class="px-3 py-2 text-sm border border-gray-300 <?php echo $current_page == $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> min-h-[36px] min-w-[36px] flex items-center justify-center"
                           <?php echo $current_page == $total_pages ? 'onclick="return false;"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- Last Page -->
                        <a href="?module=my-reports&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&page=<?php echo $total_pages; ?>"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-r-lg <?php echo $current_page == $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> min-h-[36px] min-w-[36px] flex items-center justify-center"
                           <?php echo $current_page == $total_pages ? 'onclick="return false;"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                    
                    <!-- Records Per Page Selector -->
                    <div class="mt-3 sm:mt-0">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-500 mr-2">Show:</span>
                            <select onchange="changeRecordsPerPage(this.value)" class="text-sm border border-gray-300 rounded px-2 py-2" style="font-size: 16px;">
                                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $records_per_page == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span class="text-sm text-gray-500 ml-2">per page</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Print All Button -->
    <?php if (!empty($reports)): ?>
    <div class="mt-6 bg-white rounded-xl shadow-sm border p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div class="mb-3 sm:mb-0">
                <h3 class="font-semibold text-gray-800 text-sm md:text-base">Print Options</h3>
                <p class="text-xs text-gray-500">Print individual reports or all filtered reports</p>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="printAllReports()"
                        class="px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 active:bg-green-700 flex items-center text-sm min-h-[44px]">
                    <i class="fas fa-print mr-2"></i> Print All Filtered Reports
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Report Details Modal - UPDATED STRUCTURE -->
<div id="reportDetailsModal" class="fixed inset-0 z-50 items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    <div class="bg-white rounded-xl shadow-lg max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col relative z-10">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800" id="modalTitle">Report Details</h3>
                <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div id="modalContent" class="flex-1 overflow-y-auto p-6">
            <!-- Content will be loaded via AJAX -->
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t flex justify-end space-x-3 flex-shrink-0">
            <button type="button" onclick="closeModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Attachment Viewer Modal - UPDATED STRUCTURE -->
<div id="attachmentViewerModal" class="fixed inset-0 z-[60] items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-90"></div>
    <div class="bg-white rounded-xl shadow-lg max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col relative z-10">
        <div class="px-6 py-4 border-b flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800" id="attachmentTitle">Attachment Viewer</h3>
                <button type="button" onclick="closeAttachmentViewer()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div id="attachmentViewerContent" class="flex-1 overflow-y-auto p-6">
            <!-- Attachment content will be loaded here -->
        </div>
        <div class="px-6 py-4 border-t flex justify-end space-x-3 flex-shrink-0">
            <button type="button" onclick="closeAttachmentViewer()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    // Handle notification clicks
function handleNotificationClick(notificationId, reportId) {
    fetch(`ajax/get_notification_details.php?notification_id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Highlight the specific report
                highlightReport(reportId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Highlight report function
function highlightReport(reportId) {
    // Find and highlight the report
    const reportElement = document.querySelector(`[data-report-id="${reportId}"]`);
    if (reportElement) {
        reportElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        reportElement.classList.add('bg-yellow-50', 'border', 'border-yellow-300', 'rounded-lg', 'highlight-new');
        
        // Show a toast notification
        showToast('Report highlighted - showing updated classification', 'info');
        
        // Remove highlight after 5 seconds
        setTimeout(() => {
            reportElement.classList.remove('bg-yellow-50', 'border', 'border-yellow-300', 'rounded-lg', 'highlight-new');
        }, 5000);
    } else {
        // If report not found in current view, redirect to page 1
        const url = new URL(window.location.href);
        url.searchParams.set('page', 1);
        url.searchParams.set('highlight', reportId);
        window.location.href = url.toString();
    }
}

// Check for highlight parameter on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightReportId = urlParams.get('highlight');
    
    if (highlightReportId) {
        setTimeout(() => {
            highlightReport(highlightReportId);
            
            // Remove highlight parameter from URL without reloading
            const url = new URL(window.location.href);
            url.searchParams.delete('highlight');
            window.history.replaceState({}, '', url.toString());
        }, 1000);
    }
});
// Global variables
const BASE_URL = "<?php echo BASE_URL; ?>";
const AJAX_URL = "<?php echo AJAX_URL; ?>";

// Mobile touch device detection
const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

// Mobile-specific view function with better touch feedback
function mobileViewReportDetails(reportId) {
    // Add haptic feedback if available
    if (navigator.vibrate) {
        navigator.vibrate(30);
    }
    
    // Add visual feedback to the button
    const button = event?.target?.closest('button') || event?.target;
    if (button) {
        button.classList.add('active', 'scale-95');
        setTimeout(() => {
            button.classList.remove('active', 'scale-95');
        }, 200);
    }
    
    // Show loading toast
    showToast('Loading report details...', 'info');
    
    // Call the main view function
    viewReportDetails(reportId);
}

// Mobile-specific timeline function
function mobileViewReportTimeline(reportId) {
    // Add haptic feedback if available
    if (navigator.vibrate) {
        navigator.vibrate(30);
    }
    
    // Add visual feedback to the button
    const button = event?.target?.closest('button') || event?.target;
    if (button) {
        button.classList.add('active', 'scale-95');
        setTimeout(() => {
            button.classList.remove('active', 'scale-95');
        }, 200);
    }
    
    // Show loading toast
    showToast('Loading timeline...', 'info');
    
    // Call the main timeline function
    viewReportTimeline(reportId);
}

// Mobile-specific print function
function mobilePrintReport(reportId) {
    // Add haptic feedback if available
    if (navigator.vibrate) {
        navigator.vibrate(50);
    }
    
    // Add visual feedback to the button
    const button = event?.target?.closest('button') || event?.target;
    if (button) {
        button.classList.add('active', 'scale-95');
        setTimeout(() => {
            button.classList.remove('active', 'scale-95');
        }, 200);
    }
    
    // Show confirmation toast
    showToast('Preparing to print report...', 'info');
    
    // Call the main print function
    printReport(reportId);
}

// View Report Details
function viewReportDetails(reportId) {
    // Show loading
    document.getElementById('modalContent').innerHTML = `
        <div class="flex justify-center items-center h-48">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
            <span class="ml-3 text-gray-600">Loading report details...</span>
        </div>
    `;
    
    // Show modal with proper display
    const modal = document.getElementById('reportDetailsModal');
    modal.classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Report Details';
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Load report details via AJAX
    const url = `${AJAX_URL}get_report_details.php?id=${reportId}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('modalContent').innerHTML = html;
            // Initialize attachment viewers
            initializeAttachmentViewers();
        })
        .catch(error => {
            console.error('Error loading report details:', error);
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center p-6">
                    <i class="fas fa-exclamation-circle text-red-500 text-3xl mb-3"></i>
                    <p class="text-gray-700">Error loading report details. Please try again.</p>
                    <p class="text-sm text-gray-500 mt-1">${error.message}</p>
                    <button onclick="viewReportDetails(${reportId})" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 min-h-[44px]">
                        Retry
                    </button>
                </div>
            `;
        });
}

// View Report Timeline
function viewReportTimeline(reportId) {
    document.getElementById('modalContent').innerHTML = `
        <div class="flex justify-center items-center h-48">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
            <span class="ml-3 text-gray-600">Loading timeline...</span>
        </div>
    `;
    
    const modal = document.getElementById('reportDetailsModal');
    modal.classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Report Timeline';
    
    const url = `${AJAX_URL}get_report_timeline.php?id=${reportId}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('modalContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading timeline:', error);
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center p-6">
                    <i class="fas fa-exclamation-circle text-red-500 text-3xl mb-3"></i>
                    <p class="text-gray-700">Error loading timeline.</p>
                    <p class="text-sm text-gray-500 mt-1">${error.message}</p>
                    <button onclick="viewReportTimeline(${reportId})" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 min-h-[44px]">
                        Retry
                    </button>
                </div>
            `;
        });
}

// Initialize attachment viewers
function initializeAttachmentViewers() {
    // Add click handlers for attachment preview buttons
    document.querySelectorAll('.preview-attachment').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filePath = this.getAttribute('data-file-path');
            const fileName = this.getAttribute('data-file-name');
            const fileType = this.getAttribute('data-file-type');
            viewAttachment(filePath, fileName, fileType);
        });
    });
    
    // Add click handlers for download buttons
    document.querySelectorAll('.download-attachment').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filePath = this.getAttribute('data-file-path');
            const fileName = this.getAttribute('data-file-name');
            downloadAttachment(filePath, fileName);
        });
    });
}

// View Attachment
function viewAttachment(filePath, fileName, fileType) {
    const viewerContent = document.getElementById('attachmentViewerContent');
    const viewerTitle = document.getElementById('attachmentTitle');
    const modal = document.getElementById('attachmentViewerModal');
    
    viewerTitle.textContent = `Viewing: ${fileName}`;
    
    // Show loading
    viewerContent.innerHTML = `
        <div class="flex justify-center items-center h-64">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
            <span class="ml-3 text-gray-600">Loading attachment...</span>
        </div>
    `;
    
    // Show modal with proper display
    modal.classList.remove('hidden');
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Determine file type and render accordingly
    const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    const pdfTypes = ['pdf'];
    const videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
    const audioTypes = ['mp3', 'wav', 'ogg', 'm4a'];
    const documentTypes = ['doc', 'docx', 'txt', 'rtf'];
    const spreadsheetTypes = ['xls', 'xlsx', 'csv'];
    
    const extension = fileType.toLowerCase();
    
    if (imageTypes.includes(extension)) {
        // Show image
        viewerContent.innerHTML = `
            <div class="text-center p-4">
                <div class="mb-4">
                    <h4 class="font-medium text-gray-800">${fileName}</h4>
                    <p class="text-sm text-gray-500">Image File (${extension.toUpperCase()})</p>
                </div>
                <div class="max-h-[60vh] overflow-auto">
                    <img src="${filePath}" 
                         alt="${fileName}" 
                         class="max-w-full mx-auto rounded-lg shadow-lg"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSI+SW1hZ2UgTm90IEF2YWlsYWJsZTwvdGV4dD48L3N2Zz4=';">
                </div>
                <div class="mt-4 flex justify-center space-x-4">
                    <button onclick="downloadAttachment('${filePath}', '${fileName}')" 
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 min-h-[44px]">
                        <i class="fas fa-download mr-2"></i> Download
                    </button>
                    <button onclick="closeAttachmentViewer()" 
                            class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 active:bg-gray-800 min-h-[44px]">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        `;
    } else if (pdfTypes.includes(extension)) {
        // Show PDF viewer
        viewerContent.innerHTML = `
            <div class="text-center p-4">
                <div class="mb-4">
                    <h4 class="font-medium text-gray-800">${fileName}</h4>
                    <p class="text-sm text-gray-500">PDF Document</p>
                </div>
                <div class="max-h-[60vh] overflow-auto border rounded-lg">
                    <iframe src="${filePath}" 
                            title="${fileName}"
                            class="w-full h-[60vh] border-0"
                            onerror="this.onerror=null; this.innerHTML='<div class=\\'p-8 text-center text-red-500\\'><i class=\\"fas fa-exclamation-triangle text-3xl mb-3\\"></i><p>Unable to load PDF preview</p></div>';">
                    </iframe>
                </div>
                <div class="mt-4 flex justify-center space-x-4">
                    <a href="${filePath}" target="_blank" 
                       class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 no-underline min-h-[44px] flex items-center justify-center">
                        <i class="fas fa-external-link-alt mr-2"></i> Open in New Tab
                    </a>
                    <button onclick="downloadAttachment('${filePath}', '${fileName}')" 
                            class="px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 active:bg-green-800 min-h-[44px] flex items-center justify-center">
                        <i class="fas fa-download mr-2"></i> Download
                    </button>
                    <button onclick="closeAttachmentViewer()" 
                            class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 active:bg-gray-800 min-h-[44px] flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        `;
    } else if (videoTypes.includes(extension)) {
        // Show video player
        viewerContent.innerHTML = `
            <div class="text-center p-4">
                <div class="mb-4">
                    <h4 class="font-medium text-gray-800">${fileName}</h4>
                    <p class="text-sm text-gray-500">Video File (${extension.toUpperCase()})</p>
                </div>
                <div class="max-h-[60vh] overflow-auto">
                    <video controls class="max-w-full mx-auto rounded-lg shadow-lg">
                        <source src="${filePath}" type="video/${extension}">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="mt-4 flex justify-center space-x-4">
                    <button onclick="downloadAttachment('${filePath}', '${fileName}')" 
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 min-h-[44px]">
                        <i class="fas fa-download mr-2"></i> Download
                    </button>
                    <button onclick="closeAttachmentViewer()" 
                            class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 active:bg-gray-800 min-h-[44px]">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        `;
    } else if (audioTypes.includes(extension)) {
        // Show audio player
        viewerContent.innerHTML = `
            <div class="text-center p-4">
                <div class="mb-4">
                    <h4 class="font-medium text-gray-800">${fileName}</h4>
                    <p class="text-sm text-gray-500">Audio File (${extension.toUpperCase()})</p>
                </div>
                <div class="max-h-[60vh] overflow-auto p-8">
                    <audio controls class="w-full">
                        <source src="${filePath}" type="audio/${extension}">
                        Your browser does not support the audio element.
                    </audio>
                </div>
                <div class="mt-4 flex justify-center space-x-4">
                    <button onclick="downloadAttachment('${filePath}', '${fileName}')" 
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 min-h-[44px]">
                        <i class="fas fa-download mr-2"></i> Download
                    </button>
                    <button onclick="closeAttachmentViewer()" 
                            class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 active:bg-gray-800 min-h-[44px]">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        `;
    } else {
        // Unsupported file type - show download option only
        viewerContent.innerHTML = `
            <div class="text-center p-8">
                <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-file text-3xl text-gray-400"></i>
                </div>
                <h4 class="font-medium text-gray-800 mb-2">${fileName}</h4>
                <p class="text-sm text-gray-500 mb-4">File type: ${extension.toUpperCase()}</p>
                <p class="text-gray-600 mb-6">This file type cannot be previewed. Please download to view.</p>
                <div class="flex justify-center space-x-4">
                    <button onclick="downloadAttachment('${filePath}', '${fileName}')" 
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:bg-blue-800 min-h-[44px]">
                        <i class="fas fa-download mr-2"></i> Download File
                    </button>
                    <button onclick="closeAttachmentViewer()" 
                            class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 active:bg-gray-800 min-h-[44px]">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        `;
    }
}

// Download Attachment
function downloadAttachment(filePath, fileName) {
    showToast('Preparing download...', 'info');
    
    // Haptic feedback for mobile
    if (isTouchDevice && navigator.vibrate) {
        navigator.vibrate(20);
    }
    
    // Create a temporary anchor element
    const link = document.createElement('a');
    link.href = filePath;
    link.download = fileName;
    link.target = '_blank';
    
    // Append to body, click, and remove
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('Download started', 'success');
}

// Close Attachment Viewer - UPDATED
function closeAttachmentViewer() {
    const modal = document.getElementById('attachmentViewerModal');
    if (modal) {
        modal.classList.add('hidden');
        document.getElementById('attachmentViewerContent').innerHTML = '';
        // Restore body scrolling
        document.body.style.overflow = 'auto';
    }
}

// Print Report
function printReport(reportId) {
    // Haptic feedback for mobile
    if (isTouchDevice && navigator.vibrate) {
        navigator.vibrate(50);
    }
    
    // Show toast notification
    showToast('Opening print preview...', 'info');
    
    // Store current scroll position
    const scrollPosition = window.pageYOffset;
    
    // Method 1: Try to open in new window first
    const url = `${AJAX_URL}download_report.php?id=${reportId}&format=print&_=${Date.now()}`;
    
    try {
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        
        if (printWindow) {
            printWindow.location.href = url;
            printWindow.focus();
            
            // Add event listener to handle when the print window closes
            const checkWindow = setInterval(() => {
                try {
                    if (printWindow.closed) {
                        clearInterval(checkWindow);
                        showToast('Print window closed', 'info');
                        window.focus();
                        if (scrollPosition) window.scrollTo(0, scrollPosition);
                    }
                } catch (e) {
                    clearInterval(checkWindow);
                }
            }, 500);
        } else {
            // Method 2: If popup blocked, try iframe
            printUsingIframe(reportId);
        }
    } catch (error) {
        console.error('Print error:', error);
        // Method 3: Last resort - direct navigation
        showToast('Opening print in current window...', 'warning');
        window.open(url, '_self');
    }
}

// Iframe fallback method
function printUsingIframe(reportId) {
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position:fixed;width:100%;height:100%;top:0;left:0;border:none;z-index:999999;display:none;';
    iframe.src = `${AJAX_URL}download_report.php?id=${reportId}&format=print&_=${Date.now()}`;
    
    document.body.appendChild(iframe);
    
    iframe.onload = function() {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            // Remove iframe after printing
            setTimeout(() => {
                if (iframe && iframe.parentNode) {
                    document.body.removeChild(iframe);
                }
            }, 3000);
        } catch (e) {
            console.error('Iframe print failed:', e);
            showToast('Print failed. Please try the direct link.', 'error');
            if (iframe && iframe.parentNode) {
                document.body.removeChild(iframe);
            }
        }
    };
    
    // Make iframe visible for debugging
    setTimeout(() => {
        iframe.style.display = 'block';
    }, 100);
}

// Print All Reports
function printAllReports() {
    // Haptic feedback for mobile
    if (isTouchDevice && navigator.vibrate) {
        navigator.vibrate(50);
    }
    
    showToast('Preparing to print all reports...', 'info');
    
    // Store current scroll position
    const scrollPosition = window.pageYOffset;
    
    // Build base URL
    let url = `${AJAX_URL}export_reports.php?format=print&_=${Date.now()}`;
    
    // Add current filter parameters
    const status = document.querySelector('select[name="status"]')?.value || 'all';
    const category = document.querySelector('select[name="category"]')?.value || 'all';
    const dateFrom = document.querySelector('input[name="date_from"]')?.value || '';
    const dateTo = document.querySelector('input[name="date_to"]')?.value || '';
    
    if (status && status !== 'all') url += `&status=${status}`;
    if (category && category !== 'all') url += `&category=${category}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    
    try {
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        if (printWindow) {
            printWindow.location.href = url;
            printWindow.focus();
            
            // Restore scroll position when window closes
            const checkWindow = setInterval(() => {
                try {
                    if (printWindow.closed) {
                        clearInterval(checkWindow);
                        window.focus();
                        if (scrollPosition) window.scrollTo(0, scrollPosition);
                        showToast('Print window closed', 'info');
                    }
                } catch (e) {
                    clearInterval(checkWindow);
                }
            }, 500);
        } else {
            showToast('Please allow popups to print multiple reports', 'warning');
            // Fallback: open in current window
            window.open(url, '_self');
        }
    } catch (error) {
        showToast('Error opening print. Please try again.', 'error');
    }
}

// Close Modal - UPDATED
function closeModal() {
    const modal = document.getElementById('reportDetailsModal');
    if (modal) {
        modal.classList.add('hidden');
        document.getElementById('modalContent').innerHTML = '';
        // Restore body scrolling
        document.body.style.overflow = 'auto';
    }
}

// Change records per page
function changeRecordsPerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('page', 1); // Reset to first page
    window.location.href = url.toString();
}

// Toast notification
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    const typeClasses = {
        'success': 'bg-green-600',
        'error': 'bg-red-600', 
        'warning': 'bg-yellow-600',
        'info': 'bg-blue-600'
    };
    
    toast.className = `toast-notification fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg text-white z-50 transform transition-all duration-300 ${typeClasses[type] || 'bg-blue-600'} text-sm min-h-[44px] flex items-center`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Form Validation for Date Range
function validateDateRange() {
    const dateFrom = document.querySelector('input[name="date_from"]');
    const dateTo = document.querySelector('input[name="date_to"]');
    
    if (!dateFrom || !dateTo) return true;
    
    if (dateFrom.value && dateTo.value && new Date(dateFrom.value) > new Date(dateTo.value)) {
        showToast('From date cannot be after To date', 'warning');
        return false;
    }
    
    return true;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('My Reports module loaded');
    
    // Ensure modals are properly hidden on page load
    const reportModal = document.getElementById('reportDetailsModal');
    const attachmentModal = document.getElementById('attachmentViewerModal');
    
    if (reportModal) {
        reportModal.classList.add('hidden');
    }
    
    if (attachmentModal) {
        attachmentModal.classList.add('hidden');
    }
    
    // Ensure body scroll is enabled
    document.body.style.overflow = 'auto';
    document.body.style.position = 'static';
    
    if (isTouchDevice) {
        console.log('Touch device detected');
        
        // Add touch device class to HTML
        document.documentElement.classList.add('touch-device');
        
        // Fix viewport for mobile
        const fixViewport = () => {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        };
        
        fixViewport();
        window.addEventListener('resize', fixViewport);
    }
    
    // Add event listener for filter form
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            if (!validateDateRange()) {
                e.preventDefault();
                return false;
            }
            // Reset to page 1 when applying filters
            const pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            pageInput.value = '1';
            this.appendChild(pageInput);
            return true;
        });
    }
    
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        const reportModal = document.getElementById('reportDetailsModal');
        const attachmentModal = document.getElementById('attachmentViewerModal');
        
        if (reportModal && !reportModal.classList.contains('hidden')) {
            if (e.target === reportModal) {
                closeModal();
            }
        }
        
        if (attachmentModal && !attachmentModal.classList.contains('hidden')) {
            if (e.target === attachmentModal) {
                closeAttachmentViewer();
            }
        }
    });
    
    // Handle Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeAttachmentViewer();
        }
    });
    
    // Ensure all buttons are properly sized for touch
    setTimeout(() => {
        if (isTouchDevice) {
            document.querySelectorAll('button').forEach(btn => {
                const rect = btn.getBoundingClientRect();
                if (rect.height < 44 || rect.width < 44) {
                    btn.classList.add('min-h-[44px]');
                    if (rect.width < 44) {
                        btn.classList.add('min-w-[44px]');
                    }
                }
            });
        }
    }, 100);
});
</script>

<style>
/* Status Colors */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-pending { background-color: #fef3c7; color: #92400e; }
.status-assigned { background-color: #dbeafe; color: #1e40af; }
.status-investigating { background-color: #ede9fe; color: #5b21b6; }
.status-resolved { background-color: #d1fae5; color: #065f46; }
.status-referred { background-color: #ffedd5; color: #9a3412; }
.status-closed { background-color: #f3f4f6; color: #374151; }

/* Toast notifications */
.toast-notification {
    animation: slideInRight 0.3s ease-out;
    transition: opacity 0.3s, transform 0.3s;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive table */
@media (max-width: 768px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
    
    /* Mobile card view */
    .md\:hidden {
        display: block;
    }
    
    .hidden.md\:block {
        display: none;
    }
}

/* Fix the modal overlay issue */
#reportDetailsModal,
#attachmentViewerModal {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 9999; /* Lower than header but above content */
}

#reportDetailsModal:not(.hidden),
#attachmentViewerModal:not(.hidden) {
    opacity: 1;
    pointer-events: all;
    display: flex !important;
}

#reportDetailsModal .bg-black,
#attachmentViewerModal .bg-black {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1;
}

#reportDetailsModal > div,
#attachmentViewerModal > div {
    position: relative;
    z-index: 2;
    margin: 1rem;
    max-height: calc(100vh - 2rem);
}

/* Ensure main content is always accessible */
.max-w-7xl {
    position: relative;
    z-index: 1;
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
}

/* Ensure images don't overflow */
img {
    max-width: 100%;
    height: auto;
}

/* Mobile-specific fixes */
@media (max-width: 768px) {
    /* Ensure buttons are large enough for touch */
    button, .touch-button {
        min-height: 44px !important;
        min-width: 44px !important;
        padding: 12px 16px !important;
        font-size: 14px !important;
    }
    
    /* Better touch feedback */
    .touch-button:active {
        transform: scale(0.95) !important;
        transition: transform 0.1s !important;
    }
    
    /* Improve report card spacing */
    .mobile-report-card {
        padding: 16px !important;
        margin-bottom: 8px !important;
    }
    
    /* Ensure text is readable */
    body, .text-sm, .text-xs {
        font-size: 14px !important;
    }
    
    /* Modal adjustments for mobile */
    #reportDetailsModal > div,
    #attachmentViewerModal > div {
        margin: 0.5rem;
        width: calc(100% - 1rem);
        max-height: 95vh;
    }
    
    /* Fix for iOS Safari */
    @supports (-webkit-touch-callout: none) {
        input, select, textarea {
            font-size: 16px !important;
        }
    }
    
    /* Prevent zoom on iOS */
    input, select, textarea {
        font-size: 16px !important;
    }
    
    /* Remove hover effects on mobile */
    .touch-device .hover\:bg-blue-100:hover,
    .touch-device .hover\:bg-blue-600:hover,
    .touch-device .hover\:bg-gray-100:hover,
    .touch-device .hover\:bg-green-600:hover {
        background-color: inherit !important;
    }
}

/* iOS specific fixes */
@supports (-webkit-touch-callout: none) {
    input, select, textarea {
        font-size: 16px !important;
    }
    
    .touch-button {
        cursor: pointer;
    }
}

/* Button hover effects */
button:not(:disabled) {
    transition: all 0.2s ease;
}

button:not(:disabled):hover {
    transform: translateY(-1px);
}

/* Loading spinner */
.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Pagination styles */
.pagination-link {
    transition: all 0.2s ease;
}

.pagination-link:hover {
    background-color: #f3f4f6;
}

.pagination-link.active {
    background-color: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination-link.disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

/* Records per page selector */
select:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Focus styles for keyboard navigation */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* IMPORTANT: Ensure content is always accessible */
body {
    overflow-x: hidden;
    position: relative;
}

/* Remove any gray overlay from body or main content */
body::before,
main::before {
    display: none !important;
}

/* Ensure modals don't block the entire screen when hidden */
#reportDetailsModal.hidden,
#attachmentViewerModal.hidden {
    display: none !important;
}
</style>