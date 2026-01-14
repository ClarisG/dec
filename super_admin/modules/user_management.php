<?php
// super_admin/modules/user_management.php

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT u.*, 
                 COUNT(DISTINCT r.id) as report_count,
                 MAX(r.created_at) as last_report_date
          FROM users u 
          LEFT JOIN reports r ON u.id = r.user_id 
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter && $role_filter !== 'all') {
    $query .= " AND u.role = :role";
    $params[':role'] = $role_filter;
}

if ($barangay_filter && $barangay_filter !== 'all') {
    $query .= " AND u.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

if ($status_filter && $status_filter !== 'all') {
    $query .= " AND u.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
    COUNT(DISTINCT barangay) as barangays
    FROM users";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Universal User Management</h2>
                <p class="text-gray-600 mt-2">Create, modify, suspend, or delete any user account</p>
            </div>
            <button onclick="quickCreateUser()"
                    class="mt-4 md:mt-0 px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-user-plus mr-2"></i> Create New User
            </button>
        </div>

        <!-- User Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $user_stats['total'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total Users</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo $user_stats['active'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Active Users</div>
            </div>
            <div class="bg-red-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-red-700"><?php echo $user_stats['inactive'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Inactive Users</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo $user_stats['barangays'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Barangays</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-xl mb-6">
            <form method="GET" action="" class="space-y-4 md:space-y-0 md:flex md:space-x-4">
                <input type="hidden" name="module" value="user_management">
                
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by name, email, or username"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div>
                    <select name="role" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="all" <?php echo $role_filter === 'all' || !$role_filter ? 'selected' : ''; ?>>All Roles</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($role)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <select name="barangay" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="all" <?php echo $barangay_filter === 'all' || !$barangay_filter ? 'selected' : ''; ?>>All Barangays</option>
                        <?php foreach ($all_barangays as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo $barangay_filter === $barangay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="all" <?php echo $status_filter === 'all' || !$status_filter ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="?module=user_management" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">User</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Role</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Barangay</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Reports</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Last Active</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10 mr-3">
                                    <?php
                                    $profile_pic = "../uploads/profile_pictures/" . ($user['profile_picture'] ?? '');
                                    if (!empty($user['profile_picture']) && file_exists($profile_pic)):
                                    ?>
                                        <img src="<?php echo $profile_pic; ?>" 
                                             alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($user['first_name'] ?? '', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $user['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                         ($user['role'] === 'admin' ? 'bg-red-100 text-red-800' :
                                         ($user['role'] === 'captain' ? 'bg-blue-100 text-blue-800' :
                                         ($user['role'] === 'secretary' ? 'bg-green-100 text-green-800' :
                                         ($user['role'] === 'lupon' ? 'bg-yellow-100 text-yellow-800' :
                                         ($user['role'] === 'tanod' ? 'bg-indigo-100 text-indigo-800' :
                                         'bg-gray-100 text-gray-800'))))); ?>">
                                <?php echo ucfirst(htmlspecialchars($user['role'] ?? 'citizen')); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-gray-700"><?php echo htmlspecialchars($user['barangay'] ?? 'N/A'); ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="status-indicator <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></div>
                                <span class="text-sm <?php echo $user['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-gray-700"><?php echo $user['report_count'] ?? 0; ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-gray-500">
                                <?php 
                                $lastActive = $user['last_report_date'] ?? $user['last_login'] ?? $user['updated_at'];
                                echo $lastActive ? date('M d, Y', strtotime($lastActive)) : 'Never';
                                ?>
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="editUser(<?php echo $user['id']; ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="viewUserDetails(<?php echo $user['id']; ?>)"
                                        class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)"
                                        class="p-2 <?php echo $user['is_active'] ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition"
                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($users)): ?>
        <div class="text-center py-12">
            <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No users found</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Export Options -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Export User Data</h3>
                <p class="text-gray-600 mt-1">Export filtered user list for reporting</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="exportUsers('csv')"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-file-csv mr-2"></i> Export as CSV
                </button>
                <button onclick="exportUsers('pdf')"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-file-pdf mr-2"></i> Export as PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(userId) {
    // In real implementation, this would fetch user data via AJAX
    const content = `
        <form method="POST" action="">
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="user_id" value="${userId}">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="citizen">Citizen</option>
                        <option value="tanod">Tanod</option>
                        <option value="secretary">Secretary</option>
                        <option value="captain">Captain</option>
                        <option value="lupon">Lupon</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                    <input type="text" name="barangay" class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Update User
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function viewUserDetails(userId) {
    // AJAX call to fetch user details
    fetch(`../ajax/get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white text-2xl font-bold">
                            ${data.first_name?.charAt(0) || 'U'}
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">${data.first_name} ${data.last_name}</h3>
                            <p class="text-gray-600">${data.email}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Role</p>
                            <p class="font-medium">${data.role}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="font-medium ${data.is_active ? 'text-green-600' : 'text-red-600'}">
                                ${data.is_active ? 'Active' : 'Inactive'}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Barangay</p>
                            <p class="font-medium">${data.barangay || 'N/A'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Joined</p>
                            <p class="font-medium">${new Date(data.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h4 class="font-medium text-gray-800 mb-2">Activity Summary</h4>
                        <p>Total Reports: ${data.report_count || 0}</p>
                        <p>Last Active: ${data.last_login ? new Date(data.last_login).toLocaleDateString() : 'Never'}</p>
                    </div>
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load user details');
        });
}

function toggleUserStatus(userId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this user?`)) {
        fetch('../ajax/toggle_user_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to update user status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update user status');
        });
    }
}

function exportUsers(format) {
    // Add format parameter to current URL
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.location.href = '?' + params.toString();
}
</script>