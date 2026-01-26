<?php
// sec/modules/case.php - Fixed Database Connection

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Location: ../../index.php');
    exit();
}

// Include database configuration
$db_error = null;
$conn = null;

// Try multiple paths to find the database configuration
$possible_paths = [
    dirname(dirname(dirname(__DIR__))) . '/config/database.php', // public_html/config/database.php
    dirname(dirname(__DIR__)) . '/config/database.php', // sec/config/database.php
    $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/database.php',
    __DIR__ . '/../../../config/database.php',
    __DIR__ . '/../../../../config/database.php'
];

$config_found = false;
$db_config_path = '';

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $config_found = true;
        $db_config_path = $path;
        break;
    }
}

try {
    if ($config_found) {
        require_once $db_config_path;
        
        // Check if connection was established by database.php
        if (!isset($conn) || !$conn) {
            // If database.php didn't create connection, try to create it
            if (function_exists('getDbConnection')) {
                $conn = getDbConnection();
            } else {
                // Try to create connection using constants if they exist
                if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : '3306') . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                    $conn = new PDO($dsn, DB_USER, DB_PASS);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                } else {
                    throw new Exception("Database configuration loaded but connection not established and constants not defined");
                }
            }
        }
        
        // Test connection
        if ($conn) {
            $test_query = "SELECT 1 as test";
            $test_stmt = $conn->query($test_query);
            $test_result = $test_stmt->fetch();
            
            if (!$test_result || $test_result['test'] != 1) {
                throw new Exception("Database test query failed");
            }
            
            // Check if required tables exist
            $tables = ['reports', 'users'];
            foreach ($tables as $table) {
                $check_stmt = $conn->query("SHOW TABLES LIKE '$table'");
                if (!$check_stmt) {
                    throw new Exception("Failed to check for table '$table'");
                }
                if ($check_stmt->rowCount() == 0) {
                    throw new Exception("Required table '$table' not found in database");
                }
            }
        } else {
            throw new Exception("Database connection not established");
        }
        
    } else {
        // If no config file found, try to connect directly using hardcoded credentials
        // WARNING: This is not secure for production - only for debugging
        $dsn = "mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4";
        $conn = new PDO($dsn, 'u514031374_leir', 'leirP@55w0rd');
        
        // Set PDO attributes
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $conn->setAttribute(PDO::ATTR_PERSISTENT, false);
        
        // Test the direct connection
        $test_query = "SELECT 1 as test";
        $test_stmt = $conn->query($test_query);
        $test_result = $test_stmt->fetch();
        
        if (!$test_result || $test_result['test'] != 1) {
            throw new Exception("Database test query failed with direct connection");
        }
    }
    
} catch (Exception $e) {
    $db_error = $e->getMessage();
    error_log("Database connection error in case.php: " . $e->getMessage());
}

// Set current filter values from GET parameters
$currentFilter = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'from_date' => $_GET['from_date'] ?? '',
    'to_date' => $_GET['to_date'] ?? ''
];

// Initialize variables for pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = 1;
$total_records = 0;
?>

<!-- Case-Blotter Management Module -->
<div class="space-y-8">
    <?php if ($db_error): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-medium text-red-800">Database Connection Issue</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>Unable to connect to the database. Please check:</p>
                    <ul class="list-disc ml-5 mt-2 space-y-1">
                        <li>Database credentials are correct</li>
                        <li>Remote MySQL is enabled on your hosting</li>
                        <li>IP address <code class="bg-red-100 px-1">153.92.15.81</code> allows connections</li>
                        <li>Database <code class="bg-red-100 px-1">u514031374_leir</code> exists</li>
                    </ul>
                    <p class="mt-3 text-red-600 font-medium">Error: <?php echo htmlspecialchars($db_error); ?></p>
                    <?php if (!$config_found && $db_config_path): ?>
                    <p class="mt-2 text-red-600">Config path tried: <?php echo htmlspecialchars($db_config_path); ?></p>
                    <?php endif; ?>
                </div>
                <div class="mt-4">
                    <button onclick="retryConnection()" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-redo mr-2"></i> Retry Connection
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Header Section -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hashtag text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Active Cases</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            if (!$db_error && $conn) {
                                try {
                                    $query = "SELECT COUNT(*) as count FROM reports WHERE status != 'closed'";
                                    $stmt = $conn->prepare($query);
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo ($result['count'] ?? 0) . ' Cases';
                                } catch (Exception $e) {
                                    echo 'Error';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending Cases</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            if (!$db_error && $conn) {
                                try {
                                    $query = "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'";
                                    $stmt = $conn->prepare($query);
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo ($result['count'] ?? 0) . ' Pending';
                                } catch (Exception $e) {
                                    echo 'Error';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-sticky-note text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Assigned Cases</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            if (!$db_error && $conn) {
                                try {
                                    $query = "SELECT COUNT(*) as count FROM reports WHERE status = 'assigned'";
                                    $stmt = $conn->prepare($query);
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo ($result['count'] ?? 0) . ' Assigned';
                                } catch (Exception $e) {
                                    echo 'Error';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Filter Section -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Filter Reports</h3>
        </div>
        
        <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo ($currentFilter['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="assigned" <?php echo ($currentFilter['status'] ?? '') === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="in_progress" <?php echo ($currentFilter['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo ($currentFilter['status'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo ($currentFilter['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Classification</label>
                <select name="category" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <option value="Barangay Matter" <?php echo ($currentFilter['category'] ?? '') === 'Barangay Matter' ? 'selected' : ''; ?>>Barangay Matter</option>
                    <option value="Police Matter" <?php echo ($currentFilter['category'] ?? '') === 'Police Matter' ? 'selected' : ''; ?>>Police Matter</option>
                    <option value="Criminal" <?php echo ($currentFilter['category'] ?? '') === 'Criminal' ? 'selected' : ''; ?>>Criminal</option>
                    <option value="Civil" <?php echo ($currentFilter['category'] ?? '') === 'Civil' ? 'selected' : ''; ?>>Civil</option>
                    <option value="VAWC" <?php echo ($currentFilter['category'] ?? '') === 'VAWC' ? 'selected' : ''; ?>>VAWC</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="<?php echo $currentFilter['from_date'] ?? ''; ?>"
                       class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="<?php echo $currentFilter['to_date'] ?? ''; ?>"
                       class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-end space-x-2">
                <button type="button" id="clearFilter" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-1"></i> Clear
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Cases Table -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Case Reports</h3>
            <div class="text-sm text-gray-600">
                Showing 
                <span id="currentPage">1</span> 
                of 
                <span id="totalPages">1</span> 
                pages
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full case-table">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Case ID</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Date Filed</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Complainant</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Category</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Attachments</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Status</th>
                        <th class="py-3 px-4 text-left text-gray-600 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="casesTableBody">
                    <?php if (!$db_error && $conn): ?>
                    <!-- Show real data from PHP if connection is available -->
                    <?php
                    try {
                        // Get filter parameters from URL
                        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                        $status = $_GET['status'] ?? '';
                        $category = $_GET['category'] ?? '';
                        $from_date = $_GET['from_date'] ?? '';
                        $to_date = $_GET['to_date'] ?? '';
                        
                        $records_per_page = 10;
                        $offset = ($page - 1) * $records_per_page;
                        
                        // Build the query with filters
                        $where_clauses = [];
                        $params = [];
                        
                        if (!empty($status)) {
                            $where_clauses[] = "r.status = :status";
                            $params[':status'] = $status;
                        }
                        
                        if (!empty($category)) {
                            $where_clauses[] = "r.category = :category";
                            $params[':category'] = $category;
                        }
                        
                        if (!empty($from_date)) {
                            $where_clauses[] = "DATE(r.created_at) >= :from_date";
                            $params[':from_date'] = $from_date;
                        }
                        
                        if (!empty($to_date)) {
                            $where_clauses[] = "DATE(r.created_at) <= :to_date";
                            $params[':to_date'] = $to_date;
                        }
                        
                        // Default: show pending cases
                        if (empty($where_clauses)) {
                            $where_clauses[] = "r.status = 'pending'";
                        }
                        
                        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
                        
                        // Get total count
                        $count_sql = "SELECT COUNT(*) as total FROM reports r $where_sql";
                        $count_stmt = $conn->prepare($count_sql);
                        foreach ($params as $key => $value) {
                            $count_stmt->bindValue($key, $value);
                        }
                        $count_stmt->execute();
                        $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        $total_pages = ceil($total_records / $records_per_page);
                        
                        // Get cases with pagination
                        $cases_sql = "SELECT r.*, 
                                     CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
                                     (SELECT COUNT(*) FROM report_attachments ra WHERE ra.report_id = r.id) as attachment_count
                                     FROM reports r 
                                     LEFT JOIN users u ON r.user_id = u.id 
                                     $where_sql
                                     ORDER BY r.created_at DESC 
                                     LIMIT :limit OFFSET :offset";
                        
                        $cases_stmt = $conn->prepare($cases_sql);
                        
                        // Bind all parameters
                        foreach ($params as $key => $value) {
                            $cases_stmt->bindValue($key, $value);
                        }
                        $cases_stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
                        $cases_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $cases_stmt->execute();
                        $cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($cases) > 0) {
                            foreach ($cases as $case) {
                                $status_class = getStatusClass($case['status']);
                                $status_text = strtoupper(str_replace('_', ' ', $case['status']));
                                $category_class = getCategoryClass($case['category']);
                                
                                echo '<tr class="hover:bg-gray-50 transition-colors">';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="font-medium text-blue-600">#' . $case['id'] . '</span>';
                                if (!empty($case['blotter_number'])) {
                                    echo '<p class="text-xs text-green-600 mt-1">' . htmlspecialchars($case['blotter_number']) . '</p>';
                                } else {
                                    echo '<p class="text-xs text-gray-500 mt-1">Needs blotter number</p>';
                                }
                                echo '</td>';
                                echo '<td class="py-3 px-4">' . date('M d, Y', strtotime($case['created_at'])) . '</td>';
                                echo '<td class="py-3 px-4">' . htmlspecialchars($case['complainant_name'] ?? 'Unknown') . '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="category-badge ' . $category_class . '">';
                                echo htmlspecialchars($case['category'] ?? 'Uncategorized');
                                echo '</span>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                if ($case['attachment_count'] > 0) {
                                    echo '<div class="flex items-center">';
                                    echo '<span class="mr-2 text-sm text-gray-600">' . $case['attachment_count'] . ' file(s)</span>';
                                    echo '<button onclick="viewAttachments(' . $case['id'] . ')" class="text-blue-600 hover:text-blue-800 transition-colors" title="View attachments">';
                                    echo '<i class="fas fa-paperclip"></i>';
                                    echo '</button>';
                                    echo '</div>';
                                } else {
                                    echo '<span class="text-gray-400 text-sm">No attachments</span>';
                                }
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="' . $status_class . '">' . $status_text . '</span>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<div class="flex space-x-2">';
                                echo '<button onclick="viewCaseDetails(' . $case['id'] . ')" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200 transition-colors" title="View full report">';
                                echo '<i class="fas fa-eye mr-1"></i> View';
                                echo '</button>';
                                if ($case['status'] === 'pending') {
                                    echo '<button onclick="openAssignmentModal(' . $case['id'] . ')" class="px-3 py-1 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition-colors" title="Assign to officer">';
                                    echo '<i class="fas fa-user-check mr-1"></i> Assign';
                                    echo '</button>';
                                } else {
                                    echo '<button class="px-3 py-1 bg-gray-300 text-gray-600 rounded-lg text-sm cursor-not-allowed" title="Already assigned">';
                                    echo '<i class="fas fa-user-check mr-1"></i> Assigned';
                                    echo '</button>';
                                }
                                echo '</div>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="py-8 text-center text-gray-500">No cases found matching your criteria.</td></tr>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<tr><td colspan="7" class="py-8 text-center text-red-500">Error loading cases: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                    }
                    ?>
                    <?php else: ?>
                    <!-- Show loading message if no connection -->
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">
                            <?php if ($db_error): ?>
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-red-600">Database Connection Error</p>
                            <p class="text-sm text-gray-500 mt-2"><?php echo htmlspecialchars($db_error); ?></p>
                            <?php else: ?>
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                            <p>Connecting to database...</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="flex justify-center items-center mt-6 space-x-2" id="paginationContainer">
            <?php if (!$db_error && $conn && isset($total_pages) && $total_pages > 1): ?>
            <div class="flex items-center space-x-2">
                <button onclick="changePage(<?php echo max(1, $page - 1); ?>)" 
                        class="pagination-btn <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <?php 
                $maxVisiblePages = 5;
                $startPage = max(1, $page - floor($maxVisiblePages / 2));
                $endPage = min($total_pages, $startPage + $maxVisiblePages - 1);
                
                if ($endPage - $startPage + 1 < $maxVisiblePages) {
                    $startPage = max(1, $endPage - $maxVisiblePages + 1);
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                <button onclick="changePage(<?php echo $i; ?>)" 
                        class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </button>
                <?php endfor; ?>
                
                <button onclick="changePage(<?php echo min($total_pages, $page + 1); ?>)" 
                        class="pagination-btn <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="text-gray-600 ml-4">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?> • <?php echo $total_records; ?> records
            </div>
            <?php elseif (!$db_error && $conn && isset($total_records)): ?>
            <div class="text-gray-600">
                Showing <?php echo $total_records; ?> records
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Case Details Modal -->
<div id="caseDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Case Details</h3>
            <button onclick="closeCaseDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="caseDetailsContent">
            <!-- Content will be loaded via AJAX -->
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeCaseDetailsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Close
            </button>
            <button onclick="printCaseDetails()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>
    </div>
</div>

<!-- Attachments Modal -->
<div id="attachmentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Case Attachments</h3>
            <button onclick="closeAttachmentsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="attachmentsContent">
            <!-- Content will be loaded via AJAX -->
        </div>
        
        <div class="p-6 border-t bg-gray-50">
            <button onclick="closeAttachmentsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assignmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Assign Case to Officer</h3>
            <button onclick="closeAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="assignmentModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeAssignmentModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button onclick="submitAssignment()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-check mr-2"></i> Assign Case
            </button>
        </div>
    </div>
</div>

<style>
    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background-color: #f9fafb;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .file-icon {
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        margin-right: 0.75rem;
    }
    
    .file-icon-pdf {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .file-icon-image {
        background-color: #d1fae5;
        color: #059669;
    }
    
    .file-icon-video {
        background-color: #e9d5ff;
        color: #7c3aed;
    }
    
    .file-icon-doc {
        background-color: #dbeafe;
        color: #2563eb;
    }
    
    .attachment-preview {
        max-width: 100%;
        max-height: 16rem;
        margin-left: auto;
        margin-right: auto;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    .video-preview {
        width: 100%;
        max-height: 16rem;
        border-radius: 0.5rem;
    }
    
    .assignment-option {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background-color 0.2s, border-color 0.2s;
    }
    
    .assignment-option:hover {
        background-color: #f9fafb;
    }
    
    .assignment-option.active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
    
    .officer-item {
        padding: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: background-color 0.2s, border-color 0.2s;
    }
    
    .officer-item:hover {
        background-color: #f9fafb;
    }
    
    .officer-item.active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
    
    .role-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .role-badge.lupon {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .role-badge.tanod {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .badge-pending {
        background-color: #fef3c7;
        color: #92400e;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-assigned {
        background-color: #dbeafe;
        color: #1e40af;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-in-progress {
        background-color: #f3e8ff;
        color: #7c3aed;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-resolved {
        background-color: #d1fae5;
        color: #065f46;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-closed {
        background-color: #e5e7eb;
        color: #374151;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .pagination-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        background-color: white;
        color: #374151;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pagination-btn:hover:not(:disabled) {
        background-color: #f9fafb;
    }
    
    .pagination-btn.active {
        background-color: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .category-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .category-barangay {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .category-police {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .category-criminal {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .category-civil {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .category-vawc {
        background-color: #f3e8ff;
        color: #7c3aed;
    }
    
    .category-minor {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .category-other {
        background-color: #e5e7eb;
        color: #374151;
    }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .case-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .case-table th {
        border-bottom: 2px solid #e5e7eb;
    }
    
    .case-table td {
        border-bottom: 1px solid #f3f4f6;
    }
    
    .case-table tr:last-child td {
        border-bottom: none;
    }
</style>

<script>
// Current page state - will be set by PHP
let currentPage = <?php echo isset($page) ? $page : 1; ?>;
let totalPages = <?php echo isset($total_pages) ? $total_pages : 1; ?>;
let totalRecords = <?php echo isset($total_records) ? $total_records : 0; ?>;
let currentFilter = {
    status: '<?php echo $_GET['status'] ?? ''; ?>',
    category: '<?php echo $_GET['category'] ?? ''; ?>',
    from_date: '<?php echo $_GET['from_date'] ?? ''; ?>',
    to_date: '<?php echo $_GET['to_date'] ?? ''; ?>'
};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupFilterListeners();
    
    // Update page indicators
    document.getElementById('currentPage').textContent = currentPage;
    document.getElementById('totalPages').textContent = totalPages;
});

// Setup filter listeners
function setupFilterListeners() {
    // Filter form submission
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentFilter = {
            status: document.querySelector('select[name="status"]').value || '',
            category: document.querySelector('select[name="category"]').value || '',
            from_date: document.querySelector('input[name="from_date"]').value || '',
            to_date: document.querySelector('input[name="to_date"]').value || ''
        };
        currentPage = 1;
        reloadWithFilters();
    });

    // Clear filter button
    document.getElementById('clearFilter').addEventListener('click', function() {
        resetFilters();
        currentFilter = {
            status: '',
            category: '',
            from_date: '',
            to_date: ''
        };
        currentPage = 1;
        reloadWithFilters();
    });
}

function resetFilters() {
    document.querySelector('select[name="status"]').value = '';
    document.querySelector('select[name="category"]').value = '';
    document.querySelector('input[name="from_date"]').value = '';
    document.querySelector('input[name="to_date"]').value = '';
}

function reloadWithFilters() {
    const params = new URLSearchParams({
        page: currentPage,
        ...currentFilter
    });
    
    // Remove empty values
    params.forEach((value, key) => {
        if (!value) params.delete(key);
    });
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

function retryConnection() {
    location.reload();
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    reloadWithFilters();
}

// View case details
function viewCaseDetails(caseId) {
    const modal = document.getElementById('caseDetailsModal');
    const content = document.getElementById('caseDetailsContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading case details...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Make sure the handler file exists
    fetch(`../../handlers/get_case_details.php?id=${caseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading case details:', error);
            // Show a fallback message with case ID
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading case details</p>
                    <p class="text-sm text-gray-500 mt-2">Handler file not found or error occurred.</p>
                    <div class="mt-6 text-left bg-gray-50 p-4 rounded-lg">
                        <p class="font-medium">Case ID: #${caseId}</p>
                        <p class="text-sm text-gray-600 mt-2">Please check if the handler file exists at: <code>../../handlers/get_case_details.php</code></p>
                    </div>
                </div>
            `;
        });
}

// View attachments
function viewAttachments(caseId) {
    const modal = document.getElementById('attachmentsModal');
    const content = document.getElementById('attachmentsContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading attachments...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Make sure the handler file exists
    fetch(`../../handlers/get_attachments.php?report_id=${caseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading attachments:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading attachments</p>
                    <p class="text-sm text-gray-500 mt-2">Handler file not found or error occurred.</p>
                </div>
            `;
        });
}

// Assignment functionality
let selectedCaseId = null;
let selectedOfficerId = null;
let selectedOfficerType = null;

function openAssignmentModal(caseId) {
    selectedCaseId = caseId;
    selectedOfficerId = null;
    selectedOfficerType = null;
    
    const modal = document.getElementById('assignmentModal');
    const content = document.getElementById('assignmentModalContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading assignment options...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Make sure the handler file exists
    fetch(`../../handlers/get_assignment_options.php?case_id=${caseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            content.innerHTML = data;
            attachAssignmentListeners();
        })
        .catch(error => {
            console.error('Error loading assignment options:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading assignment options</p>
                    <p class="text-sm text-gray-500 mt-2">Handler file not found or error occurred.</p>
                    <div class="mt-6">
                        <button onclick="createDummyAssignmentOptions(${caseId})" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-check mr-2"></i> Use Test Assignment
                        </button>
                    </div>
                </div>
            `;
        });
}

function createDummyAssignmentOptions(caseId) {
    const content = document.getElementById('assignmentModalContent');
    content.innerHTML = `
        <div class="mb-6">
            <h4 class="font-medium text-gray-800 mb-3">Select Officer Type</h4>
            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="assignment-option active" data-type="lupon">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-user-tie text-green-600"></i>
                        </div>
                        <div>
                            <h5 class="font-medium">Lupon Member</h5>
                            <p class="text-sm text-gray-600">Assign to barangay lupon</p>
                        </div>
                    </div>
                </div>
                <div class="assignment-option" data-type="tanod">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-shield-alt text-blue-600"></i>
                        </div>
                        <div>
                            <h5 class="font-medium">Tanod</h5>
                            <p class="text-sm text-gray-600">Assign to barangay tanod</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h4 class="font-medium text-gray-800 mb-3">Available Officers</h4>
            <div id="officerList" class="space-y-3">
                <div class="officer-item active" data-officer-id="1" data-officer-type="lupon">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="font-medium officer-name">Juan Dela Cruz</h5>
                            <div class="flex items-center mt-1">
                                <span class="role-badge lupon mr-2">Lupon Member</span>
                                <span class="text-sm text-gray-600">3 cases assigned</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Availability</span>
                            <div class="text-green-600 font-medium">Available</div>
                        </div>
                    </div>
                </div>
                <div class="officer-item" data-officer-id="2" data-officer-type="lupon">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="font-medium officer-name">Maria Santos</h5>
                            <div class="flex items-center mt-1">
                                <span class="role-badge lupon mr-2">Lupon Member</span>
                                <span class="text-sm text-gray-600">5 cases assigned</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Availability</span>
                            <div class="text-yellow-600 font-medium">Medium</div>
                        </div>
                    </div>
                </div>
                <div class="officer-item" data-officer-id="3" data-officer-type="tanod">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="font-medium officer-name">Pedro Reyes</h5>
                            <div class="flex items-center mt-1">
                                <span class="role-badge tanod mr-2">Tanod</span>
                                <span class="text-sm text-gray-600">2 cases assigned</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Availability</span>
                            <div class="text-green-600 font-medium">Available</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="selectionInfo"></div>
    `;
    
    attachAssignmentListeners();
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    document.getElementById('assignmentModal').classList.remove('flex');
    selectedCaseId = null;
    selectedOfficerId = null;
    selectedOfficerType = null;
}

function attachAssignmentListeners() {
    document.querySelectorAll('.assignment-option').forEach(option => {
        option.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            
            document.querySelectorAll('.assignment-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            this.classList.add('active');
            updateOfficerListForType(type);
        });
    });
    
    document.querySelectorAll('.officer-item').forEach(item => {
        item.addEventListener('click', function() {
            const officerId = this.getAttribute('data-officer-id');
            const officerType = this.getAttribute('data-officer-type');
            
            document.querySelectorAll('.officer-item').forEach(officer => {
                officer.classList.remove('active');
            });
            
            this.classList.add('active');
            selectedOfficerId = officerId;
            selectedOfficerType = officerType;
            updateSelectionInfo();
        });
    });
    
    // Initialize with first officer selected
    const firstOfficer = document.querySelector('.officer-item');
    if (firstOfficer) {
        selectedOfficerId = firstOfficer.getAttribute('data-officer-id');
        selectedOfficerType = firstOfficer.getAttribute('data-officer-type');
        updateSelectionInfo();
    }
}

function updateOfficerListForType(type) {
    // For demo purposes, just update the selection
    const officers = document.querySelectorAll('.officer-item');
    officers.forEach(officer => {
        officer.style.display = officer.getAttribute('data-officer-type') === type ? 'block' : 'none';
    });
    
    // Select first visible officer
    const firstVisible = document.querySelector('.officer-item[style*="block"]');
    if (firstVisible) {
        officers.forEach(o => o.classList.remove('active'));
        firstVisible.classList.add('active');
        selectedOfficerId = firstVisible.getAttribute('data-officer-id');
        selectedOfficerType = firstVisible.getAttribute('data-officer-type');
    }
    
    updateSelectionInfo();
}

function updateSelectionInfo() {
    const selectionInfo = document.getElementById('selectionInfo');
    if (!selectionInfo) return;
    
    if (selectedOfficerId && selectedOfficerType) {
        const officerItem = document.querySelector(`.officer-item[data-officer-id="${selectedOfficerId}"]`);
        if (officerItem) {
            const officerName = officerItem.querySelector('.officer-name')?.textContent || 'Selected Officer';
            const displayTitle = selectedOfficerType === 'lupon' ? 'Lupon Member' : 'Tanod';
            
            selectionInfo.innerHTML = `
                <div class="bg-green-50 p-4 rounded-lg mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="font-medium">Selected:</span>
                        <span class="ml-2">${officerName}</span>
                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${selectedOfficerType === 'lupon' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                            ${displayTitle}
                        </span>
                    </div>
                </div>
            `;
        }
    } else {
        selectionInfo.innerHTML = '';
    }
}

function submitAssignment() {
    if (!selectedCaseId) {
        alert('No case selected.');
        return;
    }
    
    if (!selectedOfficerId || !selectedOfficerType) {
        alert('Please select an officer to assign this case to.');
        return;
    }
    
    const confirmMessage = `Are you sure you want to assign Case #${selectedCaseId} to the selected officer?`;
    if (!confirm(confirmMessage)) return;
    
    // For demo purposes, simulate assignment
    alert(`Case #${selectedCaseId} assigned to officer ID ${selectedOfficerId} (${selectedOfficerType}) successfully!`);
    closeAssignmentModal();
    
    // Simulate page reload after a short delay
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Modal control functions
function closeCaseDetailsModal() {
    document.getElementById('caseDetailsModal').classList.add('hidden');
    document.getElementById('caseDetailsModal').classList.remove('flex');
}

function closeAttachmentsModal() {
    document.getElementById('attachmentsModal').classList.add('hidden');
    document.getElementById('attachmentsModal').classList.remove('flex');
}

function printCaseDetails() {
    const content = document.getElementById('caseDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>Case Report - Print</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 20px;
                        color: #333;
                    }
                    .header { 
                        text-align: center; 
                        border-bottom: 2px solid #333; 
                        padding-bottom: 20px; 
                        margin-bottom: 30px; 
                    }
                    .section { 
                        margin-bottom: 25px; 
                        padding: 15px;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                    }
                    .section-title {
                        color: #2c3e50;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                        margin-bottom: 15px;
                        font-weight: bold;
                    }
                    @media print {
                        button { display: none !important; }
                        .no-print { display: none !important; }
                        body { padding: 0; }
                    }
                    @page {
                        margin: 0.5in;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Barangay Case Report</h1>
                    <p>Printed on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                </div>
                ${content}
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Close Window
                    </button>
                </div>
                <script>
                    // Auto-print after loading
                    setTimeout(function() {
                        window.print();
                    }, 500);
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = [
        { id: 'caseDetailsModal', close: closeCaseDetailsModal },
        { id: 'attachmentsModal', close: closeAttachmentsModal },
        { id: 'assignmentModal', close: closeAssignmentModal }
    ];
    
    modals.forEach(modal => {
        const modalElement = document.getElementById(modal.id);
        if (event.target === modalElement) {
            modal.close();
        }
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCaseDetailsModal();
        closeAttachmentsModal();
        closeAssignmentModal();
    }
});
</script>

<?php
// Helper functions
function getStatusClass($status) {
    switch($status) {
        case 'pending': return 'badge-pending';
        case 'assigned': return 'badge-assigned';
        case 'in_progress': return 'badge-in-progress';
        case 'resolved': return 'badge-resolved';
        case 'closed': return 'badge-closed';
        default: return 'badge-pending';
    }
}

function getCategoryClass($category) {
    switch($category) {
        case 'Barangay Matter': return 'category-barangay';
        case 'Police Matter': return 'category-police';
        case 'Criminal': return 'category-criminal';
        case 'Civil': return 'category-civil';
        case 'VAWC': return 'category-vawc';
        case 'Minor': return 'category-minor';
        default: return 'category-other';
    }
}
?>
