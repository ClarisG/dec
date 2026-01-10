<?php
// captain/modules/profile.php
?>
<div class="space-y-6 max-w-4xl">
    <!-- Profile Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <?php 
                    $profile_pic_path = "../uploads/profile_pictures/" . ($profile_picture ?? '');
                    if (!empty($profile_picture) && file_exists($profile_pic_path)): 
                    ?>
                        <img src="<?php echo $profile_pic_path; ?>" 
                             alt="Profile" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg">
                    <?php else: ?>
                        <div class="w-20 h-20 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold text-2xl">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 w-6 h-6 rounded-full border-2 border-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($user_name); ?></h3>
                    <p class="text-gray-600">Barangay Captain</p>
                    <div class="flex items-center space-x-2 mt-2">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            <i class="fas fa-shield-alt mr-1"></i> Executive Access
                        </span>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                            <i class="fas fa-check-circle mr-1"></i> Verified
                        </span>
                    </div>
                </div>
            </div>
            <div class="mt-4 md:mt-0">
                <button onclick="openEditModal()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-edit mr-2"></i> Edit Profile
                </button>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Contact Information -->
        <div class="glass-card rounded-xl p-6 lg:col-span-2">
            <h4 class="text-lg font-bold text-gray-800 mb-6">Contact Information</h4>
            
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg border">
                            <i class="fas fa-envelope text-gray-400 mr-3"></i>
                            <span class="text-gray-800"><?php echo htmlspecialchars($email); ?></span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg border">
                            <i class="fas fa-phone text-gray-400 mr-3"></i>
                            <span class="text-gray-800"><?php echo htmlspecialchars($contact_number); ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Barangay Office</label>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg border">
                        <i class="fas fa-map-marker-alt text-gray-400 mr-3"></i>
                        <span class="text-gray-800"><?php echo htmlspecialchars($barangay); ?></span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Office Hours</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                            <p class="text-sm text-blue-800">Monday - Friday</p>
                            <p class="font-medium text-blue-900">8:00 AM - 5:00 PM</p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                            <p class="text-sm text-blue-800">Saturday</p>
                            <p class="font-medium text-blue-900">9:00 AM - 12:00 PM</p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                            <p class="text-sm text-blue-800">Emergency</p>
                            <p class="font-medium text-blue-900">24/7 Available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Status -->
        <div class="glass-card rounded-xl p-6">
            <h4 class="text-lg font-bold text-gray-800 mb-6">Account Status</h4>
            
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Account Status</span>
                        <div class="flex items-center">
                            <div class="w-10 h-6 flex items-center <?php echo $is_active ? 'bg-green-500' : 'bg-gray-400'; ?> rounded-full p-1 cursor-pointer">
                                <div class="bg-white w-4 h-4 rounded-full shadow-md transform <?php echo $is_active ? 'translate-x-4' : 'translate-x-0'; ?>"></div>
                            </div>
                            <span class="ml-2 text-sm font-medium <?php echo $is_active ? 'text-green-600' : 'text-gray-600'; ?>">
                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Your account is currently <?php echo $is_active ? 'active and receiving notifications' : 'inactive'; ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Last Login</p>
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <div class="flex items-center">
                            <i class="fas fa-sign-in-alt text-gray-400 mr-3"></i>
                            <div>
                                <p class="text-gray-800"><?php echo date('F j, Y \a\t g:i A'); ?></p>
                                <p class="text-xs text-gray-500">From your current location</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Account Security</p>
                    <div class="space-y-3">
                        <a href="../reset_password.php" 
                           class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                            <div class="flex items-center">
                                <i class="fas fa-key text-gray-400 mr-3"></i>
                                <span>Change Password</span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        
                        <a href="#" 
                           class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-gray-400 mr-3"></i>
                                <span>Two-Factor Authentication</span>
                            </div>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Enabled</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h4 class="text-lg font-bold text-gray-800">Recent Activity</h4>
            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View Full Log
            </a>
        </div>
        
        <div class="space-y-4">
            <?php
            // Get recent activity
            $activity_query = "SELECT * FROM activity_logs 
                              WHERE user_id = ? OR affected_id = ?
                              ORDER BY created_at DESC 
                              LIMIT 5";
            $activity_stmt = $conn->prepare($activity_query);
            $activity_stmt->execute([$user_id, $user_id]);
            $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($activities)):
                foreach ($activities as $activity):
            ?>
                <div class="flex items-start space-x-4 p-4 rounded-lg border hover:bg-gray-50 transition">
                    <div class="w-10 h-10 rounded-full <?php echo strpos($activity['action'], 'approved') !== false ? 'bg-green-100' : 'bg-blue-100'; ?> flex items-center justify-center">
                        <i class="fas <?php echo strpos($activity['action'], 'approved') !== false ? 'fa-check text-green-600' : 'fa-file-alt text-blue-600'; ?>"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-gray-800"><?php echo htmlspecialchars($activity['description']); ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo date('M j, Y \a\t g:i A', strtotime($activity['created_at'])); ?>
                        </p>
                    </div>
                    <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                        <?php echo htmlspecialchars($activity['action']); ?>
                    </span>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <div class="text-center py-8">
                    <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                    <p class="text-gray-600">No recent activity</p>
                    <p class="text-sm text-gray-500 mt-1">Your activity log will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="glass-card rounded-xl p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-6">Performance Metrics</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl">
                <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-gavel text-blue-600 text-2xl"></i>
                </div>
                <p class="text-3xl font-bold text-blue-900">142</p>
                <p class="text-sm text-blue-800">Cases Reviewed</p>
                <p class="text-xs text-blue-700 mt-1">This Quarter</p>
            </div>
            
            <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl">
                <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-green-600 text-2xl"></i>
                </div>
                <p class="text-3xl font-bold text-green-900">2.3d</p>
                <p class="text-sm text-green-800">Avg Review Time</p>
                <p class="text-xs text-green-700 mt-1">Within compliance</p>
            </div>
            
            <div class="text-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl">
                <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-calendar-check text-purple-600 text-2xl"></i>
                </div>
                <p class="text-3xl font-bold text-purple-900">89</p>
                <p class="text-sm text-purple-800">Hearings Scheduled</p>
                <p class="text-xs text-purple-700 mt-1">This Year</p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="sticky top-0 bg-white border-b px-6 py-4">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Edit Profile</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <form id="editForm" method="POST" action="../handlers/update_profile.php">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Profile Picture</label>
                    <div class="flex items-center space-x-6">
                        <div class="relative">
                            <?php if (!empty($profile_picture) && file_exists($profile_pic_path)): ?>
                                <img src="<?php echo $profile_pic_path; ?>" 
                                     alt="Profile" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg" id="profilePreview">
                            <?php else: ?>
                                <div class="w-20 h-20 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white font-bold text-2xl" id="profilePreview">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="file" id="profileImage" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(event)">
                            <button type="button" onclick="document.getElementById('profileImage').click()" 
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-upload mr-2"></i> Upload Photo
                            </button>
                            <p class="text-xs text-gray-500 mt-2">JPG, PNG up to 2MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($contact_number); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Office Address</label>
                    <textarea name="office_address" rows="2" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($barangay); ?></textarea>
                </div>
                
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                        <div>
                            <p class="text-sm font-medium text-blue-800">Profile Visibility</p>
                            <p class="text-sm text-blue-700">
                                Your profile information is visible to barangay personnel and may be used for official communications.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal() {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

function previewImage(event) {
    const input = event.target;
    const preview = document.getElementById('profilePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Replace div with image
            if (preview.tagName === 'DIV') {
                const newImg = document.createElement('img');
                newImg.id = 'profilePreview';
                newImg.className = 'w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg';
                newImg.src = e.target.result;
                preview.parentNode.replaceChild(newImg, preview);
            } else {
                preview.src = e.target.result;
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Form submission handling
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../handlers/update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            closeEditModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating profile: ' + error.message);
    });
});
</script>