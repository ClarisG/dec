<?php
// sec/modules/compliance.php - Compliance Monitoring
// Monitors cases approaching 3-day filing deadline or 15-day resolution deadline (RA 7160)

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    exit('Access denied');
}

// Ensure database connection is available
if (!isset($conn)) {
    // Try to include database configuration
    $possible_paths = [
        dirname(dirname(dirname(__DIR__))) . '/config/database.php',
        dirname(dirname(__DIR__)) . '/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config/database.php'
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (function_exists('getDbConnection')) {
        $conn = getDbConnection();
    }
}

/** @var PDO $conn */

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

try {
    // Fetch all active cases for categorization
    $query = "SELECT r.*, 
                     DATEDIFF(NOW(), r.created_at) as days_elapsed,
                     CONCAT(u.first_name, ' ', u.last_name) as complainant_name
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.status NOT IN ('closed', 'resolved', 'referred')
              ORDER BY r.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $all_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Categorize cases
    $critical_cases = [];
    $approaching_cases = [];
    $compliant_cases = [];
    
    foreach ($all_cases as $case) {
        $days = $case['days_elapsed'];
        if ($days >= 15) {
            $critical_cases[] = $case;
        } elseif ($days >= 10) {
            $approaching_cases[] = $case;
        } else {
            $compliant_cases[] = $case;
        }
    }
    
    // Get current section from URL
    $section = isset($_GET['section']) ? $_GET['section'] : 'critical';
    
    // Determine which cases to display
    switch($section) {
        case 'approaching':
            $display_cases = $approaching_cases;
            break;
        case 'compliant':
            $display_cases = $compliant_cases;
            break;
        case 'critical':
        default:
            $display_cases = $critical_cases;
            break;
    }
    
    // Calculate pagination
    $total_cases = count($display_cases);
    $total_pages = ceil($total_cases / $items_per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    $paginated_cases = array_slice($display_cases, $offset, $items_per_page);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $critical_cases = [];
    $approaching_cases = [];
    $compliant_cases = [];
    $paginated_cases = [];
    $total_pages = 1;
    $all_cases = [];
}
?>

<!-- Compliance Monitoring Module -->
<div class="space-y-8">
    <div class="glass-card rounded-xl p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-clock mr-3 text-red-600"></i>
            Compliance Monitoring (RA 7160)
        </h2>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Critical Deadlines Card -->
            <a href="?module=compliance&section=critical" class="cursor-pointer transform hover:scale-105 transition-transform">
                <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500 hover:shadow-lg">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Critical Deadlines</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count($critical_cases); ?>
                            </p>
                            <p class="text-xs text-red-600">15+ days elapsed</p>
                        </div>
                    </div>
                </div>
            </a>
            
            <!-- Approaching Deadlines Card -->
            <a href="?module=compliance&section=approaching" class="cursor-pointer transform hover:scale-105 transition-transform">
                <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-500 hover:shadow-lg">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Approaching Deadlines</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count($approaching_cases); ?>
                            </p>
                            <p class="text-xs text-yellow-600">10-14 days elapsed</p>
                        </div>
                    </div>
                </div>
            </a>
            
            <!-- Within Compliance Card -->
            <a href="?module=compliance&section=compliant" class="cursor-pointer transform hover:scale-105 transition-transform">
                <div class="bg-green-50 p-4 rounded-lg border-l-4 border-green-500 hover:shadow-lg">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Within Compliance</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count($compliant_cases); ?>
                            </p>
                            <p class="text-xs text-green-600">Less than 10 days</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Section Tabs -->
        <div class="flex space-x-2 mb-6 border-b border-gray-200">
            <a href="?module=compliance&section=critical&page=1" 
               class="px-4 py-3 font-medium border-b-2 transition-colors <?php echo $section === 'critical' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-exclamation-circle mr-2"></i>Critical Deadlines (<?php echo count($critical_cases); ?>)
            </a>
            <a href="?module=compliance&section=approaching&page=1" 
               class="px-4 py-3 font-medium border-b-2 transition-colors <?php echo $section === 'approaching' ? 'border-yellow-600 text-yellow-600' : 'border-transparent text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-exclamation-triangle mr-2"></i>Approaching Deadlines (<?php echo count($approaching_cases); ?>)
            </a>
            <a href="?module=compliance&section=compliant&page=1" 
               class="px-4 py-3 font-medium border-b-2 transition-colors <?php echo $section === 'compliant' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-check-circle mr-2"></i>Within Compliance (<?php echo count($compliant_cases); ?>)
            </a>
        </div>
        
        <!-- Cases Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <div class="p-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-semibold text-gray-800">
                    <?php 
                    $section_title = '';
                    switch($section) {
                        case 'critical':
                            $section_title = 'Critical Deadline Cases (15+ Days)';
                            break;
                        case 'approaching':
                            $section_title = 'Approaching Deadline Cases (10-14 Days)';
                            break;
                        case 'compliant':
                            $section_title = 'Compliant Cases (Less than 10 Days)';
                            break;
                    }
                    echo $section_title;
                    ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">Case ID</th>
                            <th class="px-6 py-3">Title / Complainant</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Days Elapsed</th>
                            <th class="px-6 py-3">Timeline Status</th>
                            <th class="px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (count($paginated_cases) > 0):
                            foreach ($paginated_cases as $case): 
                                $days = $case['days_elapsed'];
                                $timeline_status = '';
                                $timeline_color = '';
                                
                                if ($days >= 15) {
                                    $timeline_status = 'OVERDUE (15+ Days)';
                                    $timeline_color = 'bg-red-100 text-red-800 border-red-200';
                                } elseif ($days >= 12) {
                                    $timeline_status = 'CRITICAL (12-14 Days)';
                                    $timeline_color = 'bg-red-50 text-red-600 border-red-100';
                                } elseif ($days >= 10) {
                                    $timeline_status = 'WARNING (10-11 Days)';
                                    $timeline_color = 'bg-yellow-50 text-yellow-600 border-yellow-100';
                                } elseif ($days >= 3) {
                                    $timeline_status = 'Standard Process';
                                    $timeline_color = 'bg-blue-50 text-blue-600 border-blue-100';
                                } else {
                                    $timeline_status = 'New Case';
                                    $timeline_color = 'bg-green-50 text-green-600 border-green-100';
                                }
                        ?>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                #<?php echo $case['id']; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($case['title'] ?? 'No Title'); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($case['complainant_name'] ?? 'Unknown'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php echo $case['status'] == 'pending' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold <?php echo $days >= 15 ? 'text-red-600' : ($days >= 10 ? 'text-yellow-600' : 'text-gray-600'); ?>">
                                    <?php echo $days; ?> Days
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded border text-xs font-medium <?php echo $timeline_color; ?>">
                                    <?php echo $timeline_status; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="?module=case&action=view&id=<?php echo $case['id']; ?>" 
                                   class="font-medium text-blue-600 hover:underline">
                                    View Case
                                </a>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                <p>No cases in this category</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center mt-6 space-x-2 p-4 border-t border-gray-100">
                <div class="flex items-center space-x-2">
                    <?php if ($current_page > 1): ?>
                    <a href="?module=compliance&section=<?php echo $section; ?>&page=1" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-double-left"></i>
                    </a>
                    <a href="?module=compliance&section=<?php echo $section; ?>&page=<?php echo $current_page - 1; ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $maxVisiblePages = 5;
                    $startPage = max(1, $current_page - floor($maxVisiblePages / 2));
                    $endPage = min($total_pages, $startPage + $maxVisiblePages - 1);
                    
                    if ($endPage - $startPage + 1 < $maxVisiblePages) {
                        $startPage = max(1, $endPage - $maxVisiblePages + 1);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <a href="?module=compliance&section=<?php echo $section; ?>&page=<?php echo $i; ?>" 
                       class="pagination-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?module=compliance&section=<?php echo $section; ?>&page=<?php echo $current_page + 1; ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="?module=compliance&section=<?php echo $section; ?>&page=<?php echo $total_pages; ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="text-gray-600 ml-4">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> • <?php echo $total_cases; ?> records
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Legal Reference -->
        <div class="mt-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h4 class="font-bold text-blue-800 mb-2">Legal Reference: RA 7160 (Local Government Code)</h4>
            <ul class="list-disc list-inside text-sm text-blue-700 space-y-1">
                <li><strong>Section 410 (b):</strong> Mediation proceedings must be completed within 15 days.</li>
                <li><strong>Filing Deadline:</strong> Notices/Summons should be issued within 3 days of filing.</li>
                <li><strong>Critical Threshold:</strong> Cases exceeding 15 days require immediate action.</li>
                <li><strong>Warning Threshold:</strong> Cases between 10-14 days need close monitoring.</li>
            </ul>
        </div>
    </div>
</div>

<style>
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
        text-decoration: none;
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
</style>
