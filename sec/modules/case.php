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

// Function to get available officers
function getAvailableOfficers($conn) {
    $officers = [];
    try {
        // Get users with officer roles (lupon, tanod, etc.)
        $query = "SELECT id, first_name, last_name, role, email, phone 
                  FROM users 
                  WHERE role IN ('lupon', 'tanod', 'lupon_chairman', 'barangay_captain')
                  AND status = 'active'
                  ORDER BY 
                    CASE role
                        WHEN 'barangay_captain' THEN 1
                        WHEN 'lupon_chairman' THEN 2
                        WHEN 'lupon' THEN 3
                        WHEN 'tanod' THEN 4
                        ELSE 5
                    END, 
                    last_name, first_name";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting officers: " . $e->getMessage());
    }
    return $officers;
}

// Get officers for the current session
$availableOfficers = $conn ? getAvailableOfficers($conn) : [];
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
                                    // FIXED: Include all non-closed cases including pending, assigned, in_progress, resolved
                                    $query = "SELECT COUNT(*) as count FROM reports WHERE status NOT IN ('closed', 'referred')";
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
                                    // FIXED: Include all pending statuses
                                    $query = "SELECT COUNT(*) as count FROM reports WHERE status IN ('pending', 'pending_field_verification')";
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
                    <option value="pending_field_verification" <?php echo ($currentFilter['status'] ?? '') === 'pending_field_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                    <option value="assigned" <?php echo ($currentFilter['status'] ?? '') === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="investigating" <?php echo ($currentFilter['status'] ?? '') === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                    <option value="resolved" <?php echo ($currentFilter['status'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="referred" <?php echo ($currentFilter['status'] ?? '') === 'referred' ? 'selected' : ''; ?>>Referred</option>
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
                    <option value="Minor" <?php echo ($currentFilter['category'] ?? '') === 'Minor' ? 'selected' : ''; ?>>Minor</option>
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
                <button type="button" onclick="resetFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-1"></i> Clear
                </button>
                <button type="button" onclick="applyFilters()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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
                        
                        // REMOVED: Default filter showing only pending cases
                        // This was causing pending cases to "disappear"
                        // Now shows all cases when no filters are applied
                        
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
                                $status_text = ucwords(str_replace('_', ' ', $case['status']));
                                $category_class = getCategoryClass($case['category'] ?? '');
                                
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
                                echo '<span class="status-badge ' . $status_class . '">' . $status_text . '</span>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<div class="flex space-x-2">';
                                echo '<button onclick="viewCaseDetails(' . $case['id'] . ')" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200 transition-colors" title="View full report">';
                                echo '<i class="fas fa-eye mr-1"></i> View';
                                echo '</button>';
                                if ($case['status'] === 'pending' || $case['status'] === 'pending_field_verification') {
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
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Case Details</h3>
            <button onclick="closeCaseDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto" id="caseDetailsContent">
            <!-- Content will be loaded via AJAX -->
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3 mt-auto">
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

<!-- Assignment Modal -->
<div id="assignmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Assign Case to Officer</h3>
            <button onclick="closeAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto" id="assignmentModalContent">
            <!-- Content will be loaded directly from PHP -->
            <div id="assignmentContent">
                <?php if (!$db_error && $conn): ?>
                <div class="mb-6">
                    <h4 class="font-medium text-gray-800 mb-3">Select Officer Type</h4>
                    <div class="grid grid-cols-2 gap-3 mb-6" id="officerTypeSelection">
                        <div class="assignment-option active" data-type="lupon" onclick="updateOfficerTypeSelection(this, 'lupon')">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-user-tie text-green-600"></i>
                                </div>
                                <div>
                                    <h5 class="font-medium">Lupon Members</h5>
                                    <p class="text-sm text-gray-600">Assign to barangay lupon</p>
                                </div>
                            </div>
                        </div>
                        <div class="assignment-option" data-type="tanod" onclick="updateOfficerTypeSelection(this, 'tanod')">
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
                        <?php 
                        if (count($availableOfficers) > 0): 
                            $firstOfficer = true;
                            foreach ($availableOfficers as $officer): 
                                $officerName = htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']);
                                $role = htmlspecialchars($officer['role']);
                                $roleClass = 'role-badge ';
                                $roleDisplay = '';
                                
                                switch($role) {
                                    case 'lupon':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Lupon Member';
                                        $officerType = 'lupon';
                                        break;
                                    case 'lupon_chairman':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Lupon Chairman';
                                        $officerType = 'lupon';
                                        break;
                                    case 'tanod':
                                        $roleClass .= 'tanod';
                                        $roleDisplay = 'Tanod';
                                        $officerType = 'tanod';
                                        break;
                                    case 'barangay_captain':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Barangay Captain';
                                        $officerType = 'lupon';
                                        break;
                                    default:
                                        $roleClass .= 'lupon';
                                        $roleDisplay = $role;
                                        $officerType = 'lupon';
                                }
                                
                                // Get assigned case count for this officer
                                $assignedCount = 0;
                                try {
                                    $countQuery = "SELECT COUNT(*) as count FROM reports WHERE assigned_officer_id = :officer_id AND status NOT IN ('closed', 'resolved')";
                                    $countStmt = $conn->prepare($countQuery);
                                    $countStmt->bindValue(':officer_id', $officer['id']);
                                    $countStmt->execute();
                                    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
                                    $assignedCount = $result['count'] ?? 0;
                                } catch (Exception $e) {
                                    // Silently continue
                                }
                        ?>
                        <div class="officer-item <?php echo $firstOfficer && $officerType == 'lupon' ? 'active' : ''; ?>" 
                             data-officer-id="<?php echo $officer['id']; ?>" 
                             data-officer-type="<?php echo $officerType; ?>"
                             data-officer-role="<?php echo $role; ?>"
                             style="<?php echo $officerType == 'tanod' ? 'display: none;' : ''; ?>"
                             onclick="selectOfficer(this)">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="font-medium officer-name"><?php echo $officerName; ?></h5>
                                    <div class="flex items-center mt-1">
                                        <span class="<?php echo $roleClass; ?> mr-2"><?php echo $roleDisplay; ?></span>
                                        <span class="text-sm text-gray-600"><?php echo $assignedCount; ?> case(s) assigned</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm text-gray-500">Contact</span>
                                    <div class="text-blue-600 font-medium text-sm"><?php echo htmlspecialchars($officer['phone'] ?? $officer['email'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php 
                                $firstOfficer = false;
                            endforeach; 
                        else: 
                        ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-600">No officers available</p>
                            <p class="text-sm text-gray-500 mt-1">Please add officers in the system first.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="selectionInfo">
                    <?php if (count($availableOfficers) > 0): 
                        // Find first lupon officer for default selection
                        $defaultOfficer = null;
                        foreach ($availableOfficers as $officer) {
                            $role = $officer['role'];
                            if ($role == 'lupon' || $role == 'lupon_chairman' || $role == 'barangay_captain') {
                                $defaultOfficer = $officer;
                                break;
                            }
                        }
                        if (!$defaultOfficer && count($availableOfficers) > 0) {
                            $defaultOfficer = $availableOfficers[0];
                        }
                        if ($defaultOfficer):
                    ?>
                    <div class="bg-green-50 p-4 rounded-lg mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="font-medium">Selected:</span>
                            <span class="ml-2" id="selectedOfficerName">
                                <?php echo htmlspecialchars($defaultOfficer['first_name'] . ' ' . $defaultOfficer['last_name']); ?>
                            </span>
                            <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800" id="selectedOfficerRole">
                                <?php 
                                $role = $defaultOfficer['role'];
                                echo $role === 'lupon_chairman' ? 'Lupon Chairman' : 
                                     ($role === 'tanod' ? 'Tanod' : 
                                     ($role === 'barangay_captain' ? 'Barangay Captain' : 'Lupon Member'));
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Database Connection Error</p>
                    <p class="text-sm text-gray-500 mt-2">Cannot load officers without database connection.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3 mt-auto">
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
    /* Pagination Styles */
    .pagination-btn {
        @apply w-10 h-10 flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition-colors shadow-sm;
    }
    
    .pagination-btn.active {
        @apply bg-blue-600 text-white border-blue-600 font-bold;
    }
    
    .pagination-btn:disabled {
        @apply opacity-50 cursor-not-allowed;
    }

    /* Status Badges */
    .status-badge {
        @apply px-3 py-1 rounded-full text-xs font-medium;
    }
    
    .status-pending {
        @apply bg-yellow-100 text-yellow-800;
    }
    
    .status-pending_field_verification {
        @apply bg-orange-100 text-orange-800;
    }
    
    .status-assigned {
        @apply bg-blue-100 text-blue-800;
    }
    
    .status-investigating {
        @apply bg-purple-100 text-purple-800;
    }
    
    .status-resolved {
        @apply bg-green-100 text-green-800;
    }
    
    .status-referred {
        @apply bg-indigo-100 text-indigo-800;
    }
    
    .status-closed {
        @apply bg-gray-100 text-gray-800;
    }
    
    /* Category Badges */
    .category-badge {
        @apply px-3 py-1 rounded-full text-xs font-medium capitalize;
    }
    
    .category-incident {
        @apply bg-red-100 text-red-800;
    }
    
    .category-complaint {
        @apply bg-yellow-100 text-yellow-800;
    }
    
    .category-blotter {
        @apply bg-blue-100 text-blue-800;
    }

    .category-barangay {
        @apply bg-blue-100 text-blue-800;
    }
    
    .category-police {
        @apply bg-red-100 text-red-800;
    }
    
    .category-criminal {
        @apply bg-purple-100 text-purple-800;
    }
    
    .category-civil {
        @apply bg-green-100 text-green-800;
    }
    
    .category-vawc {
        @apply bg-pink-100 text-pink-800;
    }
    
    .category-minor {
        @apply bg-yellow-100 text-yellow-800;
    }
    
    .category-other {
        @apply bg-gray-100 text-gray-800;
    }
    
    /* Assignment Modal Styles */
    .assignment-option {
        @apply border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-blue-300;
    }
    
    .assignment-option.active {
        @apply border-blue-500 bg-blue-50;
    }
    
    .officer-item {
        @apply border border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-blue-300 hover:bg-blue-50;
    }
    
    .officer-item.active {
        @apply border-blue-500 bg-blue-50;
    }
    
    .role-badge {
        @apply px-2 py-1 rounded-full text-xs font-medium;
    }
    
    .role-badge.lupon {
        @apply bg-green-100 text-green-800;
    }
    
    .role-badge.tanod {
        @apply bg-blue-100 text-blue-800;
    }
    
    /* Glass Card Effect */
    .glass-card {
        @apply bg-white bg-opacity-80 backdrop-blur-sm border border-gray-200;
    }
    
    /* Table Styles */
    .case-table {
        @apply min-w-full divide-y divide-gray-200;
    }
    
    .case-table thead tr th {
        @apply text-left text-xs font-medium text-gray-500 uppercase tracking-wider;
    }
    
    .case-table tbody tr {
        @apply hover:bg-gray-50;
    }
    
    .case-table tbody tr td {
        @apply px-6 py-4 whitespace-nowrap text-sm text-gray-900;
    }
</style>

<script>
console.log('case.php script loaded at ' + new Date().toLocaleTimeString());
// Page state variables, initialized by PHP
let currentPage = <?php echo isset($page) ? $page : 1; ?>;
let totalPages = <?php echo isset($total_pages) ? $total_pages : 1; ?>;

// Assignment-related variables
let selectedCaseId = null;
let selectedOfficerId = null;
let selectedOfficerType = 'lupon'; // Default to lupon
let selectedOfficerRole = null;

document.addEventListener('DOMContentLoaded', function() {
    // Update pagination display
    document.getElementById('currentPage').textContent = currentPage;
    document.getElementById('totalPages').textContent = totalPages;
    
    // Set initial officer selection based on what's visible
    updateOfficerTypeSelection(document.querySelector('.assignment-option.active'), 'lupon');
});

// --- Major Page Navigation and Filtering ---

function applyFilters() {
    console.log('applyFilters called');
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(window.location.search);

    // Update params with form data
    formData.forEach((value, key) => {
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
    });
    
    // Always reset to page 1 when applying a new filter
    params.set('page', '1');
    
    // Reload the page with the new query string
    window.location.search = params.toString();
}

function resetFilters() {
    console.log('resetFilters called');
    const params = new URLSearchParams(window.location.search);
    
    // Keep essential parameters like 'module', remove filter-related ones
    params.delete('status');
    params.delete('category');
    params.delete('from_date');
    params.delete('to_date');
    params.delete('page');
    
    // Reload the page
    window.location.search = params.toString();
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.search = params.toString();
}

function retryConnection() {
    location.reload();
}

// --- Modals and Details ---

function viewCaseDetails(caseId) {
    const modal = document.getElementById('caseDetailsModal');
    const content = document.getElementById('caseDetailsContent');
    
    content.innerHTML = '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div><p class="mt-4">Loading case details...</p></div>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Fetch details from the corrected handler
    fetch(`../../handlers/get_case_details.php?id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading case details:', error);
            content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-4xl mb-4"></i><p>Error loading details.</p><p class="text-sm">${error.message}</p></div>`;
        });
}

function closeCaseDetailsModal() {
    document.getElementById('caseDetailsModal').classList.add('hidden');
}

// --- Case Assignment Logic ---

function openAssignmentModal(caseId) {
    selectedCaseId = caseId;
    
    // Set default selection to 'lupon' and update the list
    updateOfficerTypeSelection(document.querySelector('.assignment-option[data-type="lupon"]'), 'lupon');
    
    const modal = document.getElementById('assignmentModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    selectedCaseId = null;
    selectedOfficerId = null;
}

function updateOfficerTypeSelection(element, type) {
    console.log('updateOfficerTypeSelection called with type:', type);
    // Update active style on type selector
    document.querySelectorAll('.assignment-option').forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');
    
    selectedOfficerType = type;
    updateOfficerListForType(type);
}

function updateOfficerListForType(type) {
    console.log('updateOfficerListForType called with type:', type);
    const officerList = document.getElementById('officerList');
    const items = officerList.querySelectorAll('.officer-item');
    let visibleCount = 0;
    console.log('Found', items.length, 'officer items');
    
    // Hide or show officers based on the selected type
    items.forEach(item => {
        if (item.getAttribute('data-officer-type') === type) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    console.log('Visible officers:', visibleCount);

    // Remove any existing "empty" message
    const emptyMsg = officerList.querySelector('.empty-list-message');
    if (emptyMsg) emptyMsg.remove();

    // If no officers for this type, show a message
    if (visibleCount === 0) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'empty-list-message text-center py-4 text-gray-500';
        msgDiv.innerHTML = `<i class="fas fa-user-slash text-3xl mb-2"></i><p>No available officers of type '${type}'.</p>`;
        officerList.appendChild(msgDiv);
        selectedOfficerId = null; // Clear selection
    } else {
        // Auto-select the first visible officer in the list
        const firstVisible = officerList.querySelector('.officer-item:not([style*="display: none"])');
        if (firstVisible) {
            selectOfficer(firstVisible);
        }
    }
    
    updateSelectionInfo();
}

function selectOfficer(element) {
    // Update active style for the selected officer
    document.querySelectorAll('.officer-item').forEach(item => item.classList.remove('active'));
    element.classList.add('active');

    // Store selected officer's data
    selectedOfficerId = element.getAttribute('data-officer-id');
    selectedOfficerRole = element.getAttribute('data-officer-role');

    updateSelectionInfo();
}

function updateSelectionInfo() {
    const infoDiv = document.getElementById('selectionInfo');
    const selectedOfficerElement = document.querySelector('.officer-item.active');

    if (selectedOfficerId && selectedOfficerElement) {
        const officerName = selectedOfficerElement.querySelector('.officer-name').textContent;
        const roleDisplay = selectedOfficerElement.querySelector('.role-badge').textContent;
        const roleClass = selectedOfficerElement.querySelector('.role-badge').className.replace('role-badge', '');

        infoDiv.innerHTML = `
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center flex-wrap">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="font-medium">Selected:</span>
                    <span class="ml-2 font-semibold">${officerName}</span>
                    <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${roleClass}">
                        ${roleDisplay}
                    </span>
                </div>
            </div>`;
    } else {
        infoDiv.innerHTML = '<div class="bg-yellow-50 p-4 rounded-lg text-center text-yellow-800">Please select an officer from the list.</div>';
    }
}

function submitAssignment() {
    if (!selectedCaseId || !selectedOfficerId) {
        alert('Please select a case and an officer before assigning.');
        return;
    }

    if (!confirm(`Assign Case #${selectedCaseId} to the selected officer?`)) return;

    const formData = new FormData();
    formData.append('case_id', selectedCaseId);
    formData.append('officer_id', selectedOfficerId);
    formData.append('officer_type', selectedOfficerType);

    fetch('../../handlers/assign_case.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Case assigned successfully!');
                location.reload();
            } else {
                alert('Assignment failed: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(error => {
            console.error('Assignment error:', error);
            alert('An error occurred during assignment. See console for details.');
        });
}

// --- Utility and Event Handlers ---

function printCaseDetails() {
    const content = document.getElementById('caseDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print Case</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body>' + content + '</body></html>');
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

function viewAttachments(reportId) {
    alert('Attachment viewing for report #' + reportId + ' needs to be implemented.');
}

// Global event listeners for closing modals
window.addEventListener('click', function(event) {
    if (event.target == document.getElementById('caseDetailsModal')) {
        closeCaseDetailsModal();
    }
    if (event.target == document.getElementById('assignmentModal')) {
        closeAssignmentModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closeCaseDetailsModal();
        closeAssignmentModal();
    }
});
</script>

<?php
// Helper functions for styling badges
function getStatusClass($status) {
    $classes = [
        'pending' => 'status-pending',
        'pending_field_verification' => 'status-pending_field_verification',
        'assigned' => 'status-assigned',
        'investigating' => 'status-investigating',
        'resolved' => 'status-resolved',
        'referred' => 'status-referred',
        'closed' => 'status-closed',
    ];
    return $classes[$status] ?? 'status-pending';
}

function getCategoryClass($category) {
    $classes = [
        'incident' => 'category-incident',
        'complaint' => 'category-complaint',
        'blotter' => 'category-blotter',
        'barangay matter' => 'category-barangay',
        'police matter' => 'category-police',
        'criminal' => 'category-criminal',
        'civil' => 'category-civil',
        'vawc' => 'category-vawc',
        'minor' => 'category-minor',
    ];
    return $classes[strtolower($category)] ?? 'category-other';
}
?>