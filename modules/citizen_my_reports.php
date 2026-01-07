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

// Handle PIN entry for anonymous report decryption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enter_pin'])) {
    $report_id = intval($_POST['report_id']);
    $pin_code = trim($_POST['pin_code']);
    
    try {
        $conn = getDbConnection();
        
        // Check if report belongs to user and is anonymous
        $report_query = "SELECT * FROM reports WHERE id = :id AND user_id = :user_id AND is_anonymous = 1";
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->execute([':id' => $report_id, ':user_id' => $user_id]);
        $report = $report_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            // Check PIN
            if ($report['pin_code'] == $pin_code) {
                $_SESSION['decrypted_reports'][$report_id] = true;
                $success = "PIN verified. You can now view the decrypted files.";
            } else {
                $error = "Incorrect PIN. Please try again.";
            }
        } else {
            $error = "Report not found or is not anonymous.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
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
    <!-- Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">My Reports</h2>
        <p class="text-gray-600">View and manage all your submitted reports, complaints, and blotter entries</p>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded animate-fadeIn">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div class="flex-1">
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded animate-fadeIn">
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
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
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
                <select name="category" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
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
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Date To -->
            <div>
                <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Filter Buttons -->
            <div class="md:col-span-4 flex justify-end space-x-2 pt-3 border-t">
                <a href="?module=my-reports" class="px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Clear
                </a>
                <button type="submit" class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <i class="fas fa-filter mr-1"></i> Apply
                </button>
            </div>
        </form>
    </div>
    
    <!-- Reports List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <!-- Table Header -->
        <div class="px-4 py-3 border-b">
            <h3 class="font-semibold text-gray-800 text-sm md:text-base">My Submitted Reports</h3>
            <p class="text-xs text-gray-500">Showing <?php echo count($reports); ?> report(s)</p>
        </div>
        
        <?php if (empty($reports)): ?>
            <!-- Empty State -->
            <div class="p-8 text-center">
                <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                    <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-medium text-gray-700 mb-2">No reports found</h3>
                <p class="text-gray-500 text-sm mb-4">You haven't submitted any reports yet.</p>
                <a href="?module=new-report" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Submit New Report
                </a>
            </div>
        <?php else: ?>
            <!-- Reports List - Mobile View -->
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
                        
                        // Format date
                        $created_date = date('M d, Y', strtotime($report['created_at']));
                        ?>
                        
                        <div class="p-4" data-report-id="<?php echo $report['id']; ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 text-sm">
                                        <a href="#" onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" class="hover:text-blue-600">
                                            <?php echo htmlspecialchars($report['title']); ?>
                                        </a>
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-hashtag mr-1"></i> <?php echo htmlspecialchars($report['report_number']); ?>
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?> ml-2">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                            </div>
                            
                            <div class="text-xs text-gray-600 mb-3">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-file-alt mr-2 text-gray-400"></i>
                                    <span><?php echo htmlspecialchars($report['type_name']); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar mr-2 text-gray-400"></i>
                                    <span><?php echo $created_date; ?></span>
                                </div>
                            </div>
                            
                            <!-- Mobile Actions - PRINT BUTTON ALWAYS VISIBLE -->
                            <div class="flex space-x-2">
                                <button onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" 
                                        class="flex-1 px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs hover:bg-blue-100 print-action-btn">
                                    <i class="fas fa-eye mr-1"></i> View
                                </button>
                                <button onclick="viewReportTimeline(<?php echo $report['id']; ?>); return false;" 
                                        class="flex-1 px-3 py-1.5 bg-gray-50 text-gray-700 rounded text-xs hover:bg-gray-100 print-action-btn">
                                    <i class="fas fa-history mr-1"></i> Timeline
                                </button>
                                <button onclick="printReport(<?php echo $report['id']; ?>); return false;" 
                                        class="flex-1 px-3 py-1.5 bg-green-500 text-white rounded text-xs hover:bg-green-600 print-action-btn print-permanent print-button-fixed">
                                    <i class="fas fa-print mr-1"></i> Print
                                </button>
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
                            
                            <tr class="hover:bg-gray-50 transition-colors" data-report-id="<?php echo $report['id']; ?>">
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
                                
                                <!-- Actions - PRINT BUTTON ALWAYS VISIBLE -->
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" 
                                                class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs hover:bg-blue-100 transition-colors print-action-btn">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        <button onclick="viewReportTimeline(<?php echo $report['id']; ?>); return false;" 
                                                class="px-3 py-1.5 bg-gray-50 text-gray-700 rounded text-xs hover:bg-gray-100 transition-colors print-action-btn">
                                            <i class="fas fa-history mr-1"></i> Timeline
                                        </button>
                                        <button onclick="printReport(<?php echo $report['id']; ?>); return false;" 
                                                class="px-3 py-1.5 bg-green-500 text-white rounded text-xs hover:bg-green-600 transition-colors print-action-btn print-permanent print-button-fixed">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 flex items-center text-sm transition-colors print-action-btn print-button-fixed">
                    <i class="fas fa-print mr-2"></i> Print All Filtered Reports
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Report Details Modal -->
<div id="reportDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-4 mx-auto p-4 border w-full max-w-4xl shadow-lg rounded-lg bg-white max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="flex justify-between items-center mb-4 pb-3 border-b sticky top-0 bg-white z-10">
            <h3 class="text-lg font-bold text-gray-800" id="modalTitle">Report Details</h3>
            <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div id="modalContent" class="overflow-y-auto max-h-[calc(90vh-80px)] pr-1">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- PIN Entry Modal -->
<div id="pinModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-4 mx-auto p-4 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <!-- Modal Header -->
        <div class="flex justify-between items-center mb-4 pb-3 border-b">
            <h3 class="text-base font-bold text-gray-800">Enter PIN Code</h3>
            <button type="button" onclick="closePinModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-3">This report was submitted anonymously. Please enter your 4-digit PIN to view encrypted files.</p>
            
            <form id="pinForm" method="POST" action="">
                <input type="hidden" id="pinReportId" name="report_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">4-Digit Security PIN</label>
                    <div class="flex justify-center space-x-2 mb-3">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <input type="password" name="pin_code[]" 
                                   maxlength="1" 
                                   oninput="handleModalPinInput(this, <?php echo $i + 1; ?>)" 
                                   onkeydown="handleModalPinKeydown(event, <?php echo $i + 1; ?>)"
                                   class="pin-input w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 outline-none transition-colors"
                                   autocomplete="off">
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-500 text-center">
                        Enter the same 4-digit PIN you used when submitting the report.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closePinModal()"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="enter_pin"
                            class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-colors">
                        Verify PIN
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
const BASE_URL = "<?php echo BASE_URL; ?>";
const AJAX_URL = "<?php echo AJAX_URL; ?>";

// View Report Details
function viewReportDetails(reportId) {
    // Show loading
    document.getElementById('modalContent').innerHTML = `
        <div class="flex justify-center items-center h-48">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
            <span class="ml-3 text-gray-600">Loading report details...</span>
        </div>
    `;
    
    // Show modal
    document.getElementById('reportDetailsModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Report Details';
    
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
            // Initialize any interactive elements in the loaded content
            initModalContent();
        })
        .catch(error => {
            console.error('Error loading report details:', error);
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center p-6">
                    <i class="fas fa-exclamation-circle text-red-500 text-3xl mb-3"></i>
                    <p class="text-gray-700">Error loading report details. Please try again.</p>
                    <p class="text-sm text-gray-500 mt-1">${error.message}</p>
                    <button onclick="viewReportDetails(${reportId})" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
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
    
    document.getElementById('reportDetailsModal').classList.remove('hidden');
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
                    <button onclick="viewReportTimeline(${reportId})" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Retry
                    </button>
                </div>
            `;
        });
}

// Print Report - Enhanced with multiple fallbacks
function printReport(reportId) {
    // Show toast notification
    showToast('Opening print preview...', 'info');
    
    // Store current scroll position
    const scrollPosition = window.pageYOffset;
    
    // Method 1: Try to open in new window first
    const url = `${AJAX_URL}download_report.php?id=${reportId}&format=print&_=${Date.now()}`;
    
    try {
        // First check if popups are allowed
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

// Close Modal
function closeModal() {
    document.getElementById('reportDetailsModal').classList.add('hidden');
    document.getElementById('modalContent').innerHTML = '';
}

// Open PIN Modal
function openPinModal(reportId) {
    document.getElementById('pinReportId').value = reportId;
    document.getElementById('pinModal').classList.remove('hidden');
    
    // Clear PIN inputs
    const pinInputs = document.querySelectorAll('#pinForm .pin-input');
    pinInputs.forEach(input => input.value = '');
    if (pinInputs[0]) pinInputs[0].focus();
}

// Close PIN Modal
function closePinModal() {
    document.getElementById('pinModal').classList.add('hidden');
}

// PIN Input Handling for Modal
function handleModalPinInput(input, index) {
    const value = input.value;
    
    // Only allow numbers
    if (!/^\d*$/.test(value)) {
        input.value = '';
        return;
    }
    
    // Auto-focus next input if a number is entered
    if (value && index < 4) {
        const pinInputs = document.querySelectorAll('#pinForm .pin-input');
        if (pinInputs[index]) {
            pinInputs[index].focus();
        }
    }
    
    // If user enters more than one character, take only the first
    if (value.length > 1) {
        input.value = value.charAt(0);
    }
}

function handleModalPinKeydown(event, index) {
    const key = event.key;
    const pinInputs = document.querySelectorAll('#pinForm .pin-input');
    
    if (key === 'Backspace' || key === 'Delete') {
        // If current input is empty and backspace pressed, go to previous input
        if (pinInputs[index - 1].value === '' && index > 1) {
            setTimeout(() => {
                if (pinInputs[index - 2]) {
                    pinInputs[index - 2].focus();
                    pinInputs[index - 2].select();
                }
            }, 10);
        }
    }
    
    // Arrow key navigation
    if (key === 'ArrowLeft' && index > 1 && pinInputs[index - 2]) {
        pinInputs[index - 2].focus();
        pinInputs[index - 2].select();
    }
    if (key === 'ArrowRight' && index < 4 && pinInputs[index]) {
        pinInputs[index].focus();
        pinInputs[index].select();
    }
}

// Initialize modal content after loading
function initModalContent() {
    // Add any initialization for loaded modal content here
    const printButtons = document.querySelectorAll('#modalContent button[onclick*="printReport"]');
    printButtons.forEach(btn => {
        btn.classList.add('print-permanent', 'print-button-fixed');
    });
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
    
    toast.className = `toast-notification fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white z-50 transform transition-all duration-300 animate-fadeIn ${typeClasses[type] || 'bg-blue-600'} text-sm`;
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

// ENSURE PRINT BUTTONS ARE ALWAYS VISIBLE - FIXED VERSION
function ensurePrintButtonsVisible() {
    const printButtons = document.querySelectorAll('.print-action-btn, .print-permanent, .print-button-fixed, button[onclick*="printReport"], button[onclick*="printAllReports"]');
    
    printButtons.forEach(btn => {
        // Force visibility with highest priority using inline styles
        btn.style.setProperty('display', 'inline-block', 'important');
        btn.style.setProperty('visibility', 'visible', 'important');
        btn.style.setProperty('opacity', '1', 'important');
        btn.style.setProperty('position', 'static', 'important');
        btn.style.setProperty('z-index', '9999', 'important');
        btn.style.setProperty('pointer-events', 'auto', 'important');
        
        // Make sure print buttons are green and stand out
        if (btn.onclick && btn.onclick.toString().includes('printReport') || 
            btn.onclick && btn.onclick.toString().includes('printAllReports') ||
            btn.getAttribute('onclick') && btn.getAttribute('onclick').includes('print')) {
            btn.style.setProperty('background-color', '#10b981', 'important');
            btn.style.setProperty('color', 'white', 'important');
            btn.style.setProperty('border-color', '#10b981', 'important');
            btn.style.setProperty('font-weight', 'bold', 'important');
        }
        
        // Remove any hiding classes
        btn.classList.remove('hidden', 'invisible', 'opacity-0');
        btn.classList.add('print-permanent', 'print-button-fixed');
    });
}

// Initialize - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    console.log('My Reports module loaded - PRINT BUTTONS SECURED');
    
    // Run immediately and multiple times to catch all buttons
    ensurePrintButtonsVisible();
    setTimeout(ensurePrintButtonsVisible, 100);
    setTimeout(ensurePrintButtonsVisible, 500);
    setTimeout(ensurePrintButtonsVisible, 1000);
    
    // Set up a mutation observer to watch for DOM changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' || mutation.type === 'attributes') {
                ensurePrintButtonsVisible();
            }
        });
    });
    
    // Start observing
    observer.observe(document.body, { 
        childList: true, 
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class', 'onclick']
    });
    
    // Also run on user interaction
    document.addEventListener('click', ensurePrintButtonsVisible);
    document.addEventListener('mouseover', ensurePrintButtonsVisible);
    document.addEventListener('scroll', ensurePrintButtonsVisible);
    
    // Keep checking every 2 seconds (reduced frequency)
    const printButtonInterval = setInterval(ensurePrintButtonsVisible, 2000);
    
    // Add event listener for PIN form submission
    const pinForm = document.getElementById('pinForm');
    if (pinForm) {
        pinForm.addEventListener('submit', function(e) {
            // Validate PIN
            const pinInputs = document.querySelectorAll('#pinForm input[name="pin_code[]"]');
            let pin = '';
            pinInputs.forEach(input => pin += input.value);
            
            if (pin.length !== 4 || !/^\d{4}$/.test(pin)) {
                e.preventDefault();
                showToast('Please enter a valid 4-digit PIN', 'error');
                return false;
            }
            
            // Add the pin as a single value for PHP processing
            const hiddenPinInput = document.createElement('input');
            hiddenPinInput.type = 'hidden';
            hiddenPinInput.name = 'pin_code';
            hiddenPinInput.value = pin;
            this.appendChild(hiddenPinInput);
        });
    }
    
    // Add event listener for filter form
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            if (!validateDateRange()) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    }
    
    // Close modals when clicking outside
    const reportDetailsModal = document.getElementById('reportDetailsModal');
    if (reportDetailsModal) {
        reportDetailsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
    
    const pinModal = document.getElementById('pinModal');
    if (pinModal) {
        pinModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePinModal();
            }
        });
    }
    
    // Handle Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closePinModal();
        }
    });
    
    // Final check - make absolutely sure print buttons are visible
    window.addEventListener('load', function() {
        setTimeout(ensurePrintButtonsVisible, 2000);
    });
});
</script>

<style>
/* Modal animations */
.fixed {
    transition: all 0.3s ease;
}

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

/* PRINT BUTTONS - PERMANENTLY VISIBLE - HIGHEST PRIORITY */
.print-permanent,
.print-action-btn,
.print-button-fixed,
button.print-permanent,
button.print-action-btn,
button.print-button-fixed,
button[onclick*="printReport"],
button[onclick*="printAllReports"] {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: static !important;
    z-index: 9999 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
}

/* Override ANY style that tries to hide print buttons */
.print-permanent[style*="display: none"],
.print-action-btn[style*="display: none"],
.print-button-fixed[style*="display: none"],
.print-permanent[style*="visibility: hidden"],
.print-action-btn[style*="visibility: hidden"],
.print-button-fixed[style*="visibility: hidden"],
.print-permanent[style*="opacity: 0"],
.print-action-btn[style*="opacity: 0"],
.print-button-fixed[style*="opacity: 0"] {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Make sure print buttons have clear, prominent styling */
button[onclick*="printReport"],
button[onclick*="printAllReports"],
.print-button-fixed[onclick*="print"],
button.bg-green-500,
button.bg-green-600 {
    background-color: #10b981 !important;
    color: white !important;
    border: 2px solid #10b981 !important;
    font-weight: bold !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3) !important;
}

button[onclick*="printReport"]:hover,
button[onclick*="printAllReports"]:hover,
.print-button-fixed:hover,
button.bg-green-500:hover,
button.bg-green-600:hover {
    background-color: #059669 !important;
    border-color: #059669 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 6px rgba(5, 150, 105, 0.4) !important;
}

/* Animations */
.animate-fadeIn {
    animation: fadeIn 0.3s ease-in-out;
}

.animate-fadeOut {
    animation: fadeOut 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
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
    
    /* Ensure print buttons are larger on mobile */
    .print-button-fixed {
        min-width: 70px !important;
        padding: 10px 5px !important;
        font-size: 11px !important;
    }
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
}

/* PIN input styling */
.pin-input {
    transition: all 0.2s;
}

.pin-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Modal backdrop */
.bg-opacity-50 {
    backdrop-filter: blur(4px);
}

/* Ensure images don't overflow */
img {
    max-width: 100%;
    height: auto;
}

/* Fix for modal scrolling on mobile */
@media (max-width: 640px) {
    #reportDetailsModal > div {
        margin: 0.5rem;
        width: calc(100% - 1rem);
        max-height: 95vh;
    }
    
    #modalContent {
        max-height: calc(95vh - 60px);
    }
}

/* Better mobile touch targets */
@media (max-width: 768px) {
    button, a {
        min-height: 44px;
        min-width: 44px;
    }
    
    .pin-input {
        width: 50px;
        height: 50px;
    }
}

/* Improve mobile scrolling */
@media (max-width: 768px) {
    body {
        -webkit-tap-highlight-color: transparent;
    }
    
    /* Better scrolling on iOS */
    .overflow-y-auto {
        -webkit-overflow-scrolling: touch;
    }
}

/* Button hover effects */
button {
    transition: all 0.2s ease;
}

button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Ensure all action buttons have good contrast */
.print-action-btn {
    border: 1px solid transparent !important;
}

.print-action-btn:hover {
    border-color: currentColor !important;
}

/* PRINT BUTTON SPECIAL STYLING */
.print-button-fixed {
    animation: pulse-green 2s infinite;
    position: relative;
    overflow: hidden;
}

.print-button-fixed::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    transform: rotate(30deg);
    animation: shine 3s infinite;
}

@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@keyframes shine {
    0% {
        transform: translateX(-100%) translateY(-100%) rotate(30deg);
    }
    100% {
        transform: translateX(100%) translateY(100%) rotate(30deg);
    }
}

/* Force print button container to not hide buttons */
td.whitespace-nowrap,
td.px-4.py-4 {
    position: relative;
    z-index: 1;
}

/* Make sure nothing overlays print buttons */
button.print-button-fixed {
    z-index: 99999 !important;
}
</style>