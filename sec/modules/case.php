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
                // Create connection using constants from database.php
                $conn = new PDO("mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4", 
                              'u514031374_leir', 
                              'leirP@55w0rd');
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }
        }
        
        // Test connection
        $test_query = "SELECT 1 as test";
        $test_stmt = $conn->query($test_query);
        $test_result = $test_stmt->fetch();
        
    } else {
        // Direct connection
        $conn = new PDO("mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4", 
                       'u514031374_leir', 
                       'leirP@55w0rd');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $db_error = $e->getMessage();
    error_log("Database connection error in case.php: " . $e->getMessage());
    
    // Try one more time with direct connection
    try {
        $conn = new PDO("mysql:host=153.92.15.81;port=3306;dbname=u514031374_leir;charset=utf8mb4", 
                       'u514031374_leir', 
                       'leirP@55w0rd');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db_error = null;
    } catch (Exception $e2) {
        $db_error = $e2->getMessage();
    }
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
        // Show all officers regardless of status (active/offline)
        $query = "SELECT id, first_name, last_name, role, email, phone, is_online 
                  FROM users 
                  WHERE role IN ('lupon', 'lupon_member', 'tanod', 'barangay_tanod', 'barangay_captain')
                  ORDER BY 
                    CASE role
                        WHEN 'barangay_captain' THEN 1
                        WHEN 'lupon' THEN 2
                        WHEN 'lupon_member' THEN 2
                        WHEN 'tanod' THEN 3
                        WHEN 'barangay_tanod' THEN 3
                        ELSE 4
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
            <h2 class="text-2xl font-bold text-gray-800">Case & Blotter Management</h2>
            <div class="text-sm text-gray-600">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?php echo date('F d, Y'); ?>
            </div>
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
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                Filter to find specific cases
            </div>
        </div>
        
        <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <input type="hidden" name="module" value="case">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                <select name="category" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                       class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="<?php echo $currentFilter['to_date'] ?? ''; ?>"
                       class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-end space-x-2">
                <button type="button" onclick="resetFilters()" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear
                </button>
                <button type="button" onclick="applyFilters()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                    <i class="fas fa-filter mr-2"></i> Filter
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
                <span id="currentPage"><?php echo $page; ?></span> 
                of 
                <span id="totalPages"><?php echo $total_pages; ?></span> 
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
                                $status_text = getStatusText($case['status']);
                                $category_class = getCategoryClass($case['category'] ?? '');
                                
                                echo '<tr class="hover:bg-gray-50 transition-colors">';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="font-medium text-blue-600">#' . $case['id'] . '</span>';
                                if (!empty($case['blotter_number'])) {
                                    echo '<p class="text-xs text-green-600 mt-1">' . htmlspecialchars($case['blotter_number']) . '</p>';
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
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6" id="officerTypeSelection">
                        <div class="assignment-option active" data-type="all" onclick="updateOfficerTypeSelection(this, 'all')">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-users text-gray-600"></i>
                                </div>
                                <div>
                                    <h5 class="font-medium">All Officers</h5>
                                    <p class="text-sm text-gray-600">Show everyone</p>
                                </div>
                            </div>
                        </div>
                        <div class="assignment-option" data-type="lupon" onclick="updateOfficerTypeSelection(this, 'lupon')">
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
                        <div class="assignment-option" data-type="barangay_captain" onclick="updateOfficerTypeSelection(this, 'barangay_captain')">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-user-tie text-yellow-600"></i>
                                </div>
                                <div>
                                    <h5 class="font-medium">Barangay Captain</h5>
                                    <p class="text-sm text-gray-600">Assign to Captain</p>
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
                            // Display officers
                            $firstOfficer = true;
                            foreach ($availableOfficers as $officer): 
                                $officerName = htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']);
                                $role = htmlspecialchars($officer['role']);
                                $roleClass = 'role-badge ';
                                $roleDisplay = '';
                                
                                switch($role) {
                                    case 'lupon':
                                    case 'lupon_member':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Lupon Member';
                                        $officerType = 'lupon';
                                        break;
                                    case 'lupon_chairman':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Barangay Captain'; // Fallback mapping
                                        $officerType = 'barangay_captain';
                                        break;
                                    case 'tanod':
                                    case 'barangay_tanod':
                                        $roleClass .= 'tanod';
                                        $roleDisplay = 'Tanod';
                                        $officerType = 'tanod';
                                        break;
                                    case 'barangay_captain':
                                        $roleClass .= 'lupon';
                                        $roleDisplay = 'Barangay Captain';
                                        $officerType = 'barangay_captain';
                                        break;
                                    default:
                                        $roleClass .= 'lupon';
                                        $roleDisplay = $role;
                                        $officerType = 'lupon';
                                }
                                
                                // Get assigned case count for this officer
                                $assignedCount = 0;
                                try {
                                    $countColumn = ($officerType == 'tanod') ? 'assigned_tanod' : 'assigned_lupon';
                                    // Also check assigned_lupon_chairman if applicable
                                    if ($role == 'lupon_chairman' || $role == 'barangay_captain') {
                                        $countColumn = 'assigned_lupon_chairman';
                                    }
                                    
                                    // Check if column exists or handle generic assignment
                                    $countQuery = "SELECT COUNT(*) as count FROM reports WHERE $countColumn = :officer_id AND status NOT IN ('closed', 'resolved')";
                                    $countStmt = $conn->prepare($countQuery);
                                    $countStmt->bindValue(':officer_id', $officer['id']);
                                    $countStmt->execute();
                                    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
                                    $assignedCount = $result['count'] ?? 0;
                                } catch (Exception $e) {
                                    // Silently continue
                                }
                                
                                $isOnline = !empty($officer['is_online']);
                        ?>
                        <div class="officer-item <?php echo $firstOfficer ? 'active' : ''; ?>" 
                             data-officer-id="<?php echo $officer['id']; ?>" 
                             data-officer-type="<?php echo $officerType; ?>"
                             data-officer-role="<?php echo $role; ?>"
                             onclick="selectOfficer(this)">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="font-medium officer-name flex items-center">
                                        <span class="online-indicator <?php echo $isOnline ? 'online-active' : 'online-offline'; ?>" 
                                              title="<?php echo $isOnline ? 'Online' : 'Offline'; ?>"></span>
                                        <?php echo $officerName; ?>
                                    </h5>
                                    <div class="flex items-center mt-1">
                                        <span class="<?php echo $roleClass; ?> mr-2"><?php echo $roleDisplay; ?></span>
                                        <span class="text-sm text-gray-600"><?php echo $assignedCount; ?> case(s) assigned</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm text-gray-500">Contact</span>
                                    <div class="text-blue-600 font-medium text-sm">
                                        <?php 
                                        $contact = $officer['phone'] ?? $officer['email'] ?? 'N/A';
                                        echo htmlspecialchars($contact);
                                        ?>
                                    </div>
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
                            if ($role == 'lupon' || $role == 'lupon_member' || $role == 'lupon_chairman' || $role == 'barangay_captain') {
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
                                if ($role === 'lupon_chairman') echo 'Barangay Captain';
                                elseif ($role === 'barangay_captain') echo 'Barangay Captain';
                                elseif ($role === 'tanod' || $role === 'barangay_tanod') echo 'Tanod';
                                else echo 'Lupon Member';
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
    /* Status Badges - Text Only with Dot */
    .status-badge {
        padding: 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        background: none !important;
        border: none !important;
    }

    .status-badge::before {
        content: '';
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    
    .status-pending {
        color: #c2410c;
        font-size: 0.7rem; /* Smaller font for pending */
    }
    .status-pending::before { background-color: #f97316; }
    
    .status-pending_field_verification {
        color: #ea580c;
        white-space: normal;
        text-align: left;
        max-width: 140px;
        line-height: 1.1;
        display: inline-flex;
        font-size: 0.7rem; /* Smaller font */
    }
    .status-pending_field_verification::before { background-color: #f97316; margin-right: 6px; flex-shrink: 0; }
    
    .status-assigned { color: #1d4ed8; }
    .status-assigned::before { background-color: #3b82f6; }
    
    .status-investigating { color: #4338ca; }
    .status-investigating::before { background-color: #6366f1; }
    
    .status-resolved { color: #15803d; }
    .status-resolved::before { background-color: #22c55e; }
    
    .status-referred { color: #7e22ce; }
    .status-referred::before { background-color: #a855f7; }
    
    .status-closed { color: #374151; }
    .status-closed::before { background-color: #6b7280; }
    
    /* Category Badges - Text Only with Dot */
    .category-badge {
        padding: 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
        display: inline-flex;
        align-items: center;
        background: none !important;
        border: none !important;
    }

    .category-badge::before {
        content: '';
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    
    /* User requested colors: incident (red), complain (blue), blotter (green) */
    .category-incident, .category-police, .category-criminal, .category-vawc { color: #b91c1c; }
    .category-incident::before, .category-police::before, .category-criminal::before, .category-vawc::before { background-color: #ef4444; } /* Red */
    
    .category-complain, .category-barangay, .category-civil { color: #1e40af; }
    .category-complain::before, .category-barangay::before, .category-civil::before { background-color: #3b82f6; } /* Blue */
    
    .category-blotter, .category-minor { color: #166534; }
    .category-blotter::before, .category-minor::before { background-color: #22c55e; } /* Green */
    
    .category-other { color: #4b5563; }
    .category-other::before { background-color: #9ca3af; }

    /* Online Indicators */
    .online-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 8px;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px #e5e7eb;
    }
    .online-active {
        background-color: #22c55e; /* Green */
        box-shadow: 0 0 0 1px #22c55e;
    }
    .online-offline {
        background-color: #9ca3af; /* Gray */
    }

    /* Officer Selection Styles */
    .assignment-option {
        border: 1px solid #e5e7eb;
        border: 1px solid #f472b6;
    }
    
    .category-minor {
        background-color: #fffbeb;
        color: #b45309;
        border: 1px solid #fbbf24;
    }

    /* Incident - Red */
    .category-incident {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }
    
    /* Blotter - Green */
    .category-blotter {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #4ade80;
    }
    
    /* Complain - Blue */
    .category-complain {
        background-color: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #60a5fa;
    }
    
    .category-other {
        background-color: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }
    
    /* Online Status Indicator */
    .online-indicator {
        height: 8px;
        width: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .online-active { background-color: #10b981; } /* Green */
    .online-offline { background-color: #9ca3af; } /* Gray */
    
    /* Pagination Styles */
    .pagination-btn {
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        background-color: white;
        color: #374151;
        transition: all 0.2s;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        cursor: pointer;
    }
    
    .pagination-btn:hover {
        background-color: #f9fafb;
    }
    
    .pagination-btn.active {
        background-color: #2563eb;
        color: white;
        border-color: #2563eb;
        font-weight: bold;
    }
    
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Assignment Modal Styles */
    .assignment-option {
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .assignment-option:hover {
        border-color: #93c5fd;
        background-color: #f0f9ff;
    }
    
    .assignment-option.active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
    
    .officer-item {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s;
        display: none;
    }
    
    .officer-item:hover {
        border-color: #93c5fd;
        background-color: #f0f9ff;
    }
    
    .officer-item.active {
        border-color: #3b82f6;
        background-color: #eff6ff;
        display: block !important;
    }
    
    .officer-item[style*="display: block"] {
        display: block !important;
    }
    
    .role-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .role-badge.lupon {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #34d399;
    }
    
    .role-badge.tanod {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #60a5fa;
    }
    
    /* Glass Card Effect */
    .glass-card {
        background-color: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Table Styles */
    .case-table {
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .case-table thead tr th {
        text-align: left;
        font-size: 0.75rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.75rem 1rem;
    }
    
    .case-table tbody tr {
        border-bottom: 1px solid #e5e7eb;
    }
    
    .case-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .case-table tbody tr td {
        padding: 1rem;
        white-space: nowrap;
        font-size: 0.875rem;
        color: #1f2937;
        vertical-align: middle;
    }
</style>

<!-- Blotter Number Modal -->
<div id="blotterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Set Blotter Number</h3>
            <button onclick="closeBlotterModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="blotterCaseId">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Blotter Number</label>
                <input type="text" id="blotterNumberInput" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter blotter number">
                <p class="text-xs text-gray-500 mt-1">Format: YYYY-MM-XXXX</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="closeBlotterModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                <button onclick="saveBlotterNumber()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
console.log('case.php script loaded at ' + new Date().toLocaleTimeString());

// Blotter Number Functions
function openBlotterModal(caseId, currentNumber = '') {
    document.getElementById('blotterCaseId').value = caseId;
    document.getElementById('blotterNumberInput').value = currentNumber;
    const modal = document.getElementById('blotterModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeBlotterModal() {
    const modal = document.getElementById('blotterModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function saveBlotterNumber() {
    const caseId = document.getElementById('blotterCaseId').value;
    const number = document.getElementById('blotterNumberInput').value;
    
    if (!number) {
        alert('Please enter a blotter number');
        return;
    }
    
    const formData = new FormData();
    formData.append('case_id', caseId);
    formData.append('blotter_number', number);
    
    fetch('../../sec/modules/update_blotter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Blotter number updated successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
// Page state variables, initialized by PHP
let currentPage = <?php echo isset($page) ? $page : 1; ?>;
let totalPages = <?php echo isset($total_pages) ? $total_pages : 1; ?>;

// Assignment-related variables
let selectedCaseId = null;
let selectedOfficerId = null;
let selectedOfficerType = 'lupon'; // Default to lupon
let selectedOfficerRole = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Update pagination display
    document.getElementById('currentPage').textContent = currentPage;
    document.getElementById('totalPages').textContent = totalPages;
    
    // Initialize officer selection
    initializeOfficerSelection();
});

// Initialize officer selection
function initializeOfficerSelection() {
    console.log('Initializing officer selection');
    
    // Set default selection to all
    const allOption = document.querySelector('.assignment-option[data-type="all"]');
    if (allOption) {
        updateOfficerTypeSelection(allOption, 'all');
    }
    
    // Set up click handlers for officer items
    document.querySelectorAll('.officer-item').forEach(item => {
        item.addEventListener('click', function() {
            selectOfficer(this);
        });
    });
}

// --- Major Page Navigation and Filtering ---

function applyFilters() {
    console.log('applyFilters called');
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    // Build query string
    const params = new URLSearchParams();
    
    // Add module parameter
    params.set('module', 'case');
    
    // Add all form values
    if (formData.get('status')) params.set('status', formData.get('status'));
    if (formData.get('category')) params.set('category', formData.get('category'));
    if (formData.get('from_date')) params.set('from_date', formData.get('from_date'));
    if (formData.get('to_date')) params.set('to_date', formData.get('to_date'));
    
    // Always reset to page 1 when applying a new filter
    params.set('page', '1');
    
    console.log('Applying filters:', params.toString());
    
    // Reload the page with the new query string
    window.location.href = window.location.pathname + '?' + params.toString();
}

function resetFilters() {
    console.log('resetFilters called');
    // Just reload the page with only module parameter
    window.location.href = window.location.pathname + '?module=case';
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    params.set('module', 'case');
    
    window.location.href = window.location.pathname + '?' + params.toString();
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
    console.log('Opening assignment modal for case:', caseId);
    selectedCaseId = caseId;
    
    // Set default selection to 'lupon' and update the list
    const luponOption = document.querySelector('.assignment-option[data-type="lupon"]');
    if (luponOption) {
        updateOfficerTypeSelection(luponOption, 'lupon');
    }
    
    const modal = document.getElementById('assignmentModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    selectedCaseId = null;
    selectedOfficerId = null;
    selectedOfficerType = 'lupon';
}

function updateOfficerTypeSelection(element, type) {
    console.log('updateOfficerTypeSelection called with type:', type);
    
    // Update active style on type selector
    document.querySelectorAll('.assignment-option').forEach(opt => {
        opt.classList.remove('active');
    });
    element.classList.add('active');
    
    selectedOfficerType = type;
    
    // Show/hide officers based on type
    const officerItems = document.querySelectorAll('.officer-item');
    let hasVisibleOfficers = false;
    
    officerItems.forEach(item => {
        const itemType = item.getAttribute('data-officer-type');
        if (type === 'all' || itemType === type) {
            item.style.display = 'block';
            hasVisibleOfficers = true;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Auto-select the first visible officer
    if (hasVisibleOfficers) {
        const firstVisible = document.querySelector('.officer-item[style*="display: block"]');
        if (firstVisible) {
            selectOfficer(firstVisible);
        }
    } else {
        // Clear selection if no officers of this type
        selectedOfficerId = null;
        updateSelectionInfo();
    }
}

function selectOfficer(element) {
    console.log('selectOfficer called');
    
    // Update active style for all officers
    document.querySelectorAll('.officer-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Set active for selected officer
    element.classList.add('active');
    
    // Store selected officer data
    selectedOfficerId = element.getAttribute('data-officer-id');
    selectedOfficerRole = element.getAttribute('data-officer-role');
    
    console.log('Selected officer:', selectedOfficerId, selectedOfficerRole);
    updateSelectionInfo();
}

function updateSelectionInfo() {
    const infoDiv = document.getElementById('selectionInfo');
    const selectedOfficerElement = document.querySelector('.officer-item.active');
    
    if (selectedOfficerElement && selectedOfficerId) {
        const officerName = selectedOfficerElement.querySelector('.officer-name').textContent;
        const roleDisplay = selectedOfficerElement.querySelector('.role-badge').textContent;
        const roleClass = selectedOfficerElement.querySelector('.role-badge').className;
        
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
        infoDiv.innerHTML = `
            <div class="bg-yellow-50 p-4 rounded-lg text-center">
                <i class="fas fa-exclamation-circle text-yellow-600 mr-2"></i>
                <span class="text-yellow-800">Please select an officer from the list.</span>
            </div>`;
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

    fetch('../../handlers/assign_case.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
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
document.addEventListener('click', function(event) {
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

function getStatusText($status) {
    $texts = [
        'pending' => 'Pending',
        'pending_field_verification' => 'Pending Field Verification',
        'assigned' => 'Assigned',
        'investigating' => 'Investigating',
        'resolved' => 'Resolved',
        'referred' => 'Referred',
        'closed' => 'Closed',
    ];
    return $texts[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function getCategoryClass($category) {
    $classes = [
        'Barangay Matter' => 'category-barangay',
        'Police Matter' => 'category-police',
        'Criminal' => 'category-criminal',
        'Civil' => 'category-civil',
        'VAWC' => 'category-vawc',
        'Minor' => 'category-minor',
        'Incident' => 'category-incident',
        'Blotter' => 'category-blotter',
        'Complain' => 'category-complain',
        'barangay matter' => 'category-barangay',
        'police matter' => 'category-police',
        'criminal' => 'category-criminal',
        'civil' => 'category-civil',
        'vawc' => 'category-vawc',
        'minor' => 'category-minor',
        'incident' => 'category-incident',
        'blotter' => 'category-blotter',
        'complain' => 'category-complain',
    ];
    return $classes[$category] ?? 'category-other';
}
?>