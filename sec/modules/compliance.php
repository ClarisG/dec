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

try {
    // Fetch active cases
    $query = "SELECT r.*, 
                     DATEDIFF(NOW(), r.created_at) as days_elapsed,
                     CONCAT(u.first_name, ' ', u.last_name) as complainant_name
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.status NOT IN ('closed', 'resolved', 'referred')
              ORDER BY r.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $active_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!-- Compliance Monitoring Module -->
<div class="space-y-8">
    <div class="glass-card rounded-xl p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-clock mr-3 text-red-600"></i>
            Compliance Monitoring (RA 7160)
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Critical Deadlines</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $critical = 0;
                            foreach ($active_cases as $case) {
                                if ($case['days_elapsed'] >= 15) $critical++;
                            }
                            echo $critical;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Approaching Deadlines</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $warning = 0;
                            foreach ($active_cases as $case) {
                                if ($case['days_elapsed'] >= 10 && $case['days_elapsed'] < 15) $warning++;
                            }
                            echo $warning;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Within Compliance</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php 
                            $compliant = 0;
                            foreach ($active_cases as $case) {
                                if ($case['days_elapsed'] < 10) $compliant++;
                            }
                            echo $compliant;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <div class="p-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-semibold text-gray-800">Active Cases Timeline</h3>
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
                        <?php foreach ($active_cases as $case): 
                            $days = $case['days_elapsed'];
                            $timeline_status = '';
                            $timeline_color = '';
                            
                            if ($days >= 15) {
                                $timeline_status = 'OVERDUE (15+ Days)';
                                $timeline_color = 'bg-red-100 text-red-800 border-red-200';
                            } elseif ($days >= 12) {
                                $timeline_status = 'CRITICAL (12-14 Days)';
                                $timeline_color = 'bg-red-50 text-red-600 border-red-100';
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
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($case['title']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($case['complainant_name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php echo $case['status'] == 'pending' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo ucfirst($case['status']); ?>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h4 class="font-bold text-blue-800 mb-2">Legal Reference: RA 7160 (Local Government Code)</h4>
            <ul class="list-disc list-inside text-sm text-blue-700 space-y-1">
                <li><strong>Section 410 (b):</strong> Mediation proceedings must be completed within 15 days.</li>
                <li><strong>Filing Deadline:</strong> Notices/Summons should be issued within 3 days of filing.</li>
            </ul>
        </div>
    </div>
</div>
