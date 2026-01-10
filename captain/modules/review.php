<?php
// captain/modules/review.php
?>
<div class="space-y-6">
    <!-- Header with Stats -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Final Case Review & Approval</h3>
                <p class="text-gray-600 text-sm">Review formally processed cases and provide digital sign-off</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-4">
                <div class="text-center">
                    <p class="text-sm text-gray-600">Pending Review</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo count($review_cases); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">This Week</p>
                    <p class="text-2xl font-bold text-blue-600">12</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">Compliance</p>
                    <p class="text-2xl font-bold text-green-600">92%</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
            <div class="flex space-x-4">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    All Cases
                </button>
                <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    Pending 3/15 Day
                </button>
                <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    Ready for Approval
                </button>
            </div>
            <div class="flex space-x-4">
                <input type="text" placeholder="Search cases..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Cases Table -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($review_cases)): ?>
                        <?php foreach ($review_cases as $case): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($case['report_number']); ?></span>
                                            <span class="badge badge-<?php echo $case['priority']; ?>"><?php echo ucfirst($case['priority']); ?></span>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($case['title']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Filed: <?php echo date('M d, Y', strtotime($case['created_at'])); ?>
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-3">
                                            <?php 
                                            $days = $case['days_pending'] ?? 0;
                                            $width = min(($days / 15) * 100, 100);
                                            $color = $days >= 15 ? 'bg-red-600' : ($days >= 12 ? 'bg-yellow-600' : 'bg-green-600');
                                            ?>
                                            <div class="h-2 rounded-full <?php echo $color; ?>" style="width: <?php echo $width; ?>%"></div>
                                        </div>
                                        <span class="font-medium <?php echo $days >= 15 ? 'text-red-600' : ($days >= 12 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                            <?php echo $days; ?> days
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['lupon_name'] ?? 'Unassigned'); ?></p>
                                            <p class="text-xs text-gray-500">Lupon Member</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($case['status'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="openReviewModal(<?php echo $case['id']; ?>)" 
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                                            <i class="fas fa-eye mr-1"></i> Review
                                        </button>
                                        <a href="../reports_view.php?id=<?php echo $case['id']; ?>" 
                                           target="_blank"
                                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm">
                                            <i class="fas fa-file-alt mr-1"></i> Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                                <p class="text-gray-600">No cases pending review</p>
                                <p class="text-sm text-gray-500 mt-1">All cases have been processed and approved</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span class="font-medium">1</span> to <span class="font-medium">10</span> of <span class="font-medium"><?php echo count($review_cases); ?></span> results
                </div>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Previous
                    </button>
                    <button class="px-3 py-1 bg-blue-600 text-white rounded-lg">1</button>
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">2</button>
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">3</button>
                    <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Digital Signature Information -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex items-center space-x-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                <i class="fas fa-signature text-purple-600"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-800">Digital Signature & Approval</h4>
                <p class="text-sm text-gray-600">Your digital signature will be attached to approved cases</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-blue-50 rounded-lg">
                <p class="text-sm font-medium text-blue-800">Signature Status</p>
                <p class="text-lg font-bold text-blue-900 mt-1">Active</p>
                <p class="text-xs text-blue-700 mt-1">Last used: Today</p>
            </div>
            <div class="p-4 bg-green-50 rounded-lg">
                <p class="text-sm font-medium text-green-800">Approvals This Month</p>
                <p class="text-lg font-bold text-green-900 mt-1">24</p>
                <p class="text-xs text-green-700 mt-1">+3 from last month</p>
            </div>
            <div class="p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm font-medium text-yellow-800">Average Review Time</p>
                <p class="text-lg font-bold text-yellow-900 mt-1">2.3 days</p>
                <p class="text-xs text-yellow-700 mt-1">Within compliance window</p>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Case Review & Approval</h3>
                <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Case Details -->
            <div id="caseDetails" class="mb-6">
                <!-- Will be populated by JavaScript -->
            </div>
            
            <!-- Review Form -->
            <form id="reviewForm" method="POST" action="">
                <input type="hidden" name="case_id" id="reviewCaseId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resolution Type</label>
                        <select name="resolution_type" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select resolution type</option>
                            <option value="mediated_settlement">Mediated Settlement</option>
                            <option value="arbitration_award">Arbitration Award</option>
                            <option value="dismissed">Dismissed</option>
                            <option value="referred_out">Referred to External Agency</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select name="priority" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="expedited">Expedited</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Resolution Notes</label>
                    <textarea name="resolution_notes" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter detailed resolution notes..."></textarea>
                </div>
                
                <!-- Digital Signature -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Digital Signature</label>
                    <div class="signature-pad h-32 w-full mb-2" id="signaturePad">
                        <canvas id="signatureCanvas" class="w-full h-full"></canvas>
                    </div>
                    <div class="flex justify-between items-center">
                        <button type="button" onclick="clearSignature()" class="text-sm text-gray-600 hover:text-gray-800">
                            <i class="fas fa-eraser mr-1"></i> Clear Signature
                        </button>
                        <input type="hidden" name="digital_signature" id="digitalSignature">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeReviewModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="approve_case" 
                            class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i> Approve & Sign
                    </button>
                    <button type="button" onclick="showRejectForm()"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i> Reject
                    </button>
                </div>
                
                <!-- Rejection Form (Hidden by default) -->
                <div id="rejectForm" class="hidden mt-6 p-4 bg-red-50 rounded-lg">
                    <label class="block text-sm font-medium text-red-700 mb-2">Rejection Reason</label>
                    <textarea name="rejection_reason" rows="3" class="w-full p-3 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Please provide detailed reason for rejection..."></textarea>
                    <div class="flex justify-end space-x-3 mt-3">
                        <button type="button" onclick="hideRejectForm()" 
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" name="reject_case" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                            Confirm Rejection
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let signaturePad;
let reviewModalOpen = false;

// Signature Pad functionality
function initializeSignaturePad() {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;
    
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1e3a8a';
    
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;
    
    canvas.addEventListener('mousedown', (e) => {
        isDrawing = true;
        [lastX, lastY] = [e.offsetX, e.offsetY];
    });
    
    canvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
        [lastX, lastY] = [e.offsetX, e.offsetY];
        
        // Update hidden input
        document.getElementById('digitalSignature').value = canvas.toDataURL();
    });
    
    canvas.addEventListener('mouseup', () => isDrawing = false);
    canvas.addEventListener('mouseout', () => isDrawing = false);
}

function clearSignature() {
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('digitalSignature').value = '';
    }
}

function openReviewModal(caseId) {
    reviewModalOpen = true;
    document.getElementById('reviewCaseId').value = caseId;
    document.getElementById('reviewModal').classList.remove('hidden');
    document.getElementById('reviewModal').classList.add('flex');
    
    // Load case details via AJAX
    fetch(`../handlers/get_case_details.php?id=${caseId}`)
        .then(response => response.json())
        .then(data => {
            const detailsDiv = document.getElementById('caseDetails');
            detailsDiv.innerHTML = `
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h4 class="font-bold text-gray-800">${data.report_number} - ${data.title}</h4>
                            <p class="text-sm text-gray-600 mt-1">${data.description}</p>
                        </div>
                        <span class="px-3 py-1 bg-${data.priority === 'critical' ? 'red' : data.priority === 'high' ? 'yellow' : 'blue'}-100 text-${data.priority === 'critical' ? 'red' : data.priority === 'high' ? 'yellow' : 'blue'}-800 rounded-lg text-sm">
                            ${data.priority}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Filed Date</p>
                            <p class="font-medium">${new Date(data.created_at).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Complainant</p>
                            <p class="font-medium">${data.complainant_name || 'N/A'}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Assigned To</p>
                            <p class="font-medium">${data.assigned_to || 'Unassigned'}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Status</p>
                            <p class="font-medium">${data.status}</p>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error loading case details:', error);
        });
    
    // Initialize signature pad
    setTimeout(initializeSignaturePad, 100);
}

function closeReviewModal() {
    reviewModalOpen = false;
    document.getElementById('reviewModal').classList.add('hidden');
    document.getElementById('reviewModal').classList.remove('flex');
    clearSignature();
    document.getElementById('rejectForm').classList.add('hidden');
}

function showRejectForm() {
    document.getElementById('rejectForm').classList.remove('hidden');
}

function hideRejectForm() {
    document.getElementById('rejectForm').classList.add('hidden');
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && reviewModalOpen) {
        closeReviewModal();
    }
});
</script>