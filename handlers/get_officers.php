<?php
require_once '../config/database.php';

$type = $_GET['type'] ?? '';
$case_id = $_GET['case_id'] ?? 0;

if (empty($type) || empty($case_id)) {
    echo '<div class="text-center py-4">';
    echo '<i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>';
    echo '<p class="text-red-600">Invalid parameters</p>';
    echo '</div>';
    exit;
}

try {
    // Determine role based on type
    $role = '';
    $role_name = '';
    $badge_class = '';
    
    if ($type === 'lupon_member') {
        $role = 'lupon';
        $role_name = 'Lupon Member';
        $badge_class = 'role-badge lupon';
        // Fetch active lupon members (non-chairman)
        $officers_query = "SELECT u.id, u.first_name, u.last_name, u.contact_number, 
                           u.barangay, u.status, u.is_online,
                           (SELECT COUNT(*) FROM reports WHERE assigned_lupon = u.id AND status != 'closed') as assigned_cases
                           FROM users u 
                           WHERE u.role = :role 
                           AND u.status = 'active'
                           AND u.is_active = 1
                           AND u.is_chairman = 0
                           ORDER BY u.first_name, u.last_name";
    } elseif ($type === 'barangay_captain') {
        $role = 'barangay_captain';
        $role_name = 'Barangay Captain';
        $badge_class = 'role-badge lupon';
        // Fetch active barangay captain
        $officers_query = "SELECT u.id, u.first_name, u.last_name, u.contact_number, 
                           u.barangay, u.status, u.is_online,
                           (SELECT COUNT(*) FROM reports WHERE assigned_lupon_chairman = u.id AND status != 'closed') as assigned_cases
                           FROM users u 
                           WHERE u.role = :role 
                           AND u.status = 'active'
                           AND u.is_active = 1
                           ORDER BY u.first_name, u.last_name";
    } elseif ($type === 'tanod') {
        $role = 'tanod';
        $role_name = 'Tanod';
        $badge_class = 'role-badge tanod';
        // Fetch active tanod
        $officers_query = "SELECT u.id, u.first_name, u.last_name, u.contact_number, 
                           u.barangay, u.status, u.is_online,
                           (SELECT COUNT(*) FROM reports WHERE assigned_tanod = u.id AND status != 'closed') as assigned_cases
                           FROM users u 
                           WHERE u.role = :role 
                           AND u.status = 'active'
                           AND u.is_active = 1
                           ORDER BY u.first_name, u.last_name";
    } else {
        echo '<div class="text-center py-4">';
        echo '<i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>';
        echo '<p class="text-red-600">Invalid officer type</p>';
        echo '</div>';
        exit;
    }
    
    $officers_stmt = $conn->prepare($officers_query);
    $officers_stmt->bindParam(':role', $role);
    $officers_stmt->execute();
    $officers = $officers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($officers)) {
        echo '<div class="text-center py-4">';
        echo '<i class="fas fa-user-slash text-gray-400 text-2xl mb-2"></i>';
        echo '<p class="text-gray-600">No ' . htmlspecialchars($role_name) . ' officers available</p>';
        echo '</div>';
        exit;
    }
    
    foreach ($officers as $officer) {
        echo '<div class="officer-item" data-officer-id="' . $officer['id'] . '" data-officer-type="' . $role . '">';
        echo '<div class="flex items-center justify-between">';
        echo '<div class="flex items-center">';
        echo '<div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">';
        echo '<i class="fas fa-user text-gray-600"></i>';
        echo '</div>';
        echo '<div>';
        echo '<p class="font-medium officer-name">' . htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']) . '</p>';
        echo '<p class="text-sm text-gray-600">' . htmlspecialchars($officer['contact_number']) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="text-right">';
        echo '<span class="' . $badge_class . '">' . htmlspecialchars($role_name) . '</span>';
        echo '<p class="text-xs text-gray-500 mt-1">' . $officer['assigned_cases'] . ' active cases</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="text-center py-4">';
    echo '<i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>';
    echo '<p class="text-red-600">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>