<?php
// super_admin/modules/user_management.php

// Get all users with roles
$users_query = "SELECT u.*, 
                       GROUP_CONCAT(DISTINCT r.role_name) as roles,
                       (SELECT COUNT(*) FROM reports WHERE user_id = u.id) as report_count
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id
                ORDER BY u.created_at DESC";
$users_stmt = $conn->prepare($users_query);
$users_stmt->execute();
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles for dropdown
$roles_query = "SELECT * FROM roles ORDER BY role_name";
$roles_stmt = $conn->prepare($roles_query);
$roles_stmt->execute();
$all_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- User Management Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Universal User Management</h3>
            <p class="text-sm text-gray-600">Create, modify, suspend, or delete any user account</p>
        </div>
        <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700" 
                onclick="showCreateUserModal()">
            <i class="fas fa-plus mr-2"></i>Create New User
        </button>
    </div>

    <!-- User Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User Type</label>
                <select class="w-full p-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All Types</option>
                    <option value="citizen">Citizen</option>
                    <option value="barangay_member">Barangay Member</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select class="w-full p-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="captain">Captain</option>
                    <option value="secretary">Secretary</option>
                    <option value="tanod">Tanod</option>
                    <option value="lupon">Lupon</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select class="w-full p-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                <input type="text" placeholder="Filter by barangay" 
                       class="w-full p-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role & Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reports</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($all_users as $user): 
                        $status_class = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-red-100 text-red-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'rejected' => 'bg-gray-100 text-gray-800'
                        ][$user['status']] ?? 'bg-gray-100 text-gray-800';
                        
                        $type_badge = $user['user_type'] == 'barangay_member' ? 
                            'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <?php if (!empty($user['profile_picture'])): ?>
                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                     alt="Profile" class="h-10 w-10 rounded-full object-cover">
                                <?php else: ?>
                                <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        @<?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $type_badge; ?>">
                                    <?php echo htmlspecialchars($user['user_type']); ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                <?php echo htmlspecialchars($user['roles'] ?? 'No role assigned'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['contact_number']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-xs rounded-full <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($user['status']); ?>
                            </span>
                            <div class="text-xs text-gray-500 mt-1">
                                Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo $user['report_count']; ?></div>
                            <div class="text-xs text-gray-500">Reports</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex space-x-2">
                                <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="viewUserDetails(<?php echo $user['id']; ?>)" 
                                        class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($user['status'] == 'active'): ?>
                                <button onclick="suspendUser(<?php echo $user['id']; ?>)" 
                                        class="text-yellow-600 hover:text-yellow-900">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <?php else: ?>
                                <button onclick="activateUser(<?php echo $user['id']; ?>)" 
                                        class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-play"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span class="font-medium">1</span> to <span class="font-medium">10</span> of 
                    <span class="font-medium"><?php echo count($all_users); ?></span> users
                </div>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                        Previous
                    </button>
                    <button class="px-3 py-1 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">
                        1
                    </button>
                    <button class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                        2
                    </button>
                    <button class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Create New User</h3>
                <button onclick="closeCreateUserModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="createUserForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-700">Personal Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number *</label>
                            <input type="tel" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-700">Account Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                            <input type="text" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                            <input type="password" required class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">User Type *</label>
                            <select required class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="">Select Type</option>
                                <option value="citizen">Citizen</option>
                                <option value="barangay_member">Barangay Member</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select required class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="">Select Role</option>
                                <?php foreach ($all_roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Barangay Information -->
                <div class="mt-6">
                    <h4 class="text-md font-medium text-gray-700 mb-4">Location Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Barangay</label>
                            <input type="text" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Enter barangay">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Permanent Address</label>
                            <textarea class="w-full p-3 border border-gray-300 rounded-lg" rows="2" placeholder="Full address"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="mt-8 flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateUserModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
    document.getElementById('createUserModal').classList.add('flex');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
    document.getElementById('createUserModal').classList.remove('flex');
}

function editUser(userId) {
    alert('Edit user: ' + userId);
    // Implement edit functionality
}

function viewUserDetails(userId) {
    window.open('../ajax/get_user_details.php?id=' + userId, '_blank');
}

function suspendUser(userId) {
    if (confirm('Are you sure you want to suspend this user?')) {
        // Implement suspend functionality
        alert('User suspended: ' + userId);
    }
}

function activateUser(userId) {
    if (confirm('Are you sure you want to activate this user?')) {
        // Implement activate functionality
        alert('User activated: ' + userId);
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        // Implement delete functionality
        alert('User deleted: ' + userId);
    }
}
</script>