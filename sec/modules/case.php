<!-- Case-Blotter Management Module -->
<div class="space-y-8">
    <!-- Header Section -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-gavel mr-3 text-blue-600"></i>
                Case & Blotter Management
            </h2>
            <button onclick="openNewBlotterModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> New Blotter Entry
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hashtag text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Official Blotter Numbers</p>
                        <p class="text-xl font-bold text-gray-800">BLT-2024-001 to 045</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Assigned Lupon Members</p>
                        <p class="text-xl font-bold text-gray-800">12 Active</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-sticky-note text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Formal Case Notes</p>
                        <p class="text-xl font-bold text-gray-800">156 Entries</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cases Table -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Pending Cases for Assignment</h3>
            <div class="flex space-x-2">
                <button class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg">All</button>
                <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Barangay Matters</button>
                <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Criminal</button>
                <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Civil</button>
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
                <tbody>
                    <?php
                    // Fetch pending cases from database with attachments
                    try {
                        $cases_query = "SELECT r.*, 
                                       u.first_name, 
                                       u.last_name,
                                       u.contact_number,
                                       u.email,
                                       (SELECT COUNT(*) FROM report_attachments WHERE report_id = r.id) as attachment_count
                                       FROM reports r 
                                       LEFT JOIN users u ON r.user_id = u.id 
                                       WHERE r.status = 'pending'
                                       ORDER BY r.created_at DESC 
                                       LIMIT 10";
                        $cases_stmt = $conn->prepare($cases_query);
                        $cases_stmt->execute();
                        $pending_cases = $cases_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($pending_cases) > 0) {
                            foreach ($pending_cases as $case) {
                                $case_id = $case['id'];
                                $complaint_date = date('M d, Y', strtotime($case['created_at']));
                                $complainant_name = htmlspecialchars($case['first_name'] . ' ' . $case['last_name']);
                                $category = htmlspecialchars($case['title']);
                                $attachment_count = $case['attachment_count'] ?? 0;
                                
                                // Fetch attachments for this report
                                $attachments_query = "SELECT * FROM report_attachments WHERE report_id = :report_id ORDER BY created_at";
                                $attachments_stmt = $conn->prepare($attachments_query);
                                $attachments_stmt->bindParam(':report_id', $case_id);
                                $attachments_stmt->execute();
                                $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                echo '<tr data-case-id="' . $case_id . '">';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="font-medium text-blue-600">#' . $case_id . '</span>';
                                echo '<p class="text-xs text-gray-500">Needs blotter number</p>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">' . $complaint_date . '</td>';
                                echo '<td class="py-3 px-4">' . $complainant_name . '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="badge badge-pending">' . $category . '</span>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                if ($attachment_count > 0) {
                                    echo '<div class="flex items-center">';
                                    echo '<span class="mr-2 text-sm text-gray-600">' . $attachment_count . ' file(s)</span>';
                                    echo '<button onclick="viewAttachments(' . $case_id . ')" class="text-blue-600 hover:text-blue-800" title="View attachments">';
                                    echo '<i class="fas fa-paperclip"></i>';
                                    echo '</button>';
                                    echo '</div>';
                                } else {
                                    echo '<span class="text-gray-400 text-sm">No attachments</span>';
                                }
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending</span>';
                                echo '</td>';
                                echo '<td class="py-3 px-4">';
                                echo '<div class="flex space-x-2">';
                                echo '<button onclick="viewCaseDetails(' . $case_id . ')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200" title="View full report">';
                                echo '<i class="fas fa-eye mr-1"></i> View';
                                echo '</button>';
                                echo '<button onclick="openAssignmentModal(' . $case_id . ')" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700" title="Assign to officer">';
                                echo '<i class="fas fa-user-check mr-1"></i> Assign';
                                echo '</button>';
                                echo '</div>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="py-8 text-center text-gray-500">No pending cases found.</td></tr>';
                        }
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="7" class="py-8 text-center text-red-500">Error loading cases: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Case Details Modal -->
<div id="caseDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Case Report Details</h3>
            <button onclick="closeCaseDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="caseDetailsContent">
            <!-- Content will be loaded via AJAX -->
            <div class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-600">Loading case details...</p>
            </div>
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeCaseDetailsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Close
            </button>
            <button onclick="printCaseDetails()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
        </div>
    </div>
</div>

<!-- View Attachments Modal -->
<div id="attachmentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Report Attachments</h3>
            <button onclick="closeAttachmentsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]" id="attachmentsContent">
            <!-- Content will be loaded via AJAX -->
            <div class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-600">Loading attachments...</p>
            </div>
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end">
            <button onclick="closeAttachmentsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>

<!-- New Blotter Entry Modal -->
<div id="newBlotterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">New Blotter Entry</h3>
            <button onclick="closeNewBlotterModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <form id="newBlotterForm" method="POST" action="../handlers/create_blotter.php" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Complainant Name</label>
                        <input type="text" name="complainant_name" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Complainant Contact</label>
                        <input type="text" name="complainant_contact" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Case Category</label>
                        <select name="category" required
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Category</option>
                            <option value="Barangay Matter">Barangay Matter</option>
                            <option value="Criminal">Criminal Case</option>
                            <option value="Civil">Civil Case</option>
                            <option value="VAWC">VAWC</option>
                            <option value="Minor">Minor Case</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Incident Date</label>
                        <input type="date" name="incident_date" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Case Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Provide detailed description of the incident..."></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Attachments (Optional)</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2">Drag & drop files or click to browse</p>
                            <p class="text-sm text-gray-500 mb-4">Supports images, PDF, DOCX, and videos (Max 10MB each)</p>
                            <input type="file" name="attachments[]" multiple 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.avi,.mov"
                                   class="hidden" id="fileInput">
                            <button type="button" onclick="document.getElementById('fileInput').click()" 
                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                                <i class="fas fa-plus mr-2"></i> Add Files
                            </button>
                        </div>
                        <div id="fileList" class="mt-4 text-left"></div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Initial Action</label>
                    <textarea name="initial_action" rows="2"
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Initial action taken or recommended..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewBlotterModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i> Save Blotter Entry
                    </button>
                </div>
            </form>
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
        
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <div id="assignmentModalContent">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                    <p class="text-gray-600">Loading assignment options...</p>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeAssignmentModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="submitAssignment()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
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
    
    .badge.badge-pending {
        padding: 0.25rem 0.5rem;
        background-color: #fef3c7;
        color: #92400e;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
</style>

<script>
// File preview handling
document.getElementById('fileInput').addEventListener('change', function(e) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    Array.from(e.target.files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        
        const fileIcon = getFileIcon(file.name);
        
        fileItem.innerHTML = `
            <div class="flex items-center">
                <div class="${fileIcon.class} file-icon">
                    <i class="${fileIcon.icon}"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800 text-sm truncate max-w-xs">${file.name}</p>
                    <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                </div>
            </div>
            <button type="button" onclick="removeFile(this)" class="text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        fileList.appendChild(fileItem);
    });
});

function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
        return { class: 'file-icon-image', icon: 'fas fa-image' };
    } else if (['pdf'].includes(ext)) {
        return { class: 'file-icon-pdf', icon: 'fas fa-file-pdf' };
    } else if (['doc', 'docx'].includes(ext)) {
        return { class: 'file-icon-doc', icon: 'fas fa-file-word' };
    } else if (['mp4', 'avi', 'mov', 'mkv', 'wmv'].includes(ext)) {
        return { class: 'file-icon-video', icon: 'fas fa-video' };
    } else {
        return { class: 'bg-gray-100 text-gray-600', icon: 'fas fa-file' };
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function removeFile(button) {
    button.parentElement.remove();
}

// View case details
function viewCaseDetails(caseId) {
    const modal = document.getElementById('caseDetailsModal');
    const content = document.getElementById('caseDetailsContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading case details...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Fetch case details via AJAX
    fetch(`../handlers/get_case_details.php?id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading case details</p>
                </div>
            `;
        });
}

// View attachments
function viewAttachments(caseId) {
    const modal = document.getElementById('attachmentsModal');
    const content = document.getElementById('attachmentsContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading attachments...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Fetch attachments via AJAX
    fetch(`../handlers/get_attachments.php?report_id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading attachments</p>
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
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading assignment options...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Fetch assignment options via AJAX
    fetch(`../handlers/get_assignment_options.php?case_id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
            attachAssignmentListeners();
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading assignment options</p>
                </div>
            `;
        });
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    document.getElementById('assignmentModal').classList.remove('flex');
    selectedCaseId = null;
    selectedOfficerId = null;
    selectedOfficerType = null;
}

function attachAssignmentListeners() {
    // Add click listeners to assignment type options
    document.querySelectorAll('.assignment-option').forEach(option => {
        option.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            
            // Remove active class from all options
            document.querySelectorAll('.assignment-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Load officer list for this type
            loadOfficersForType(type);
        });
    });
    
    // Add click listeners to officer items
    document.querySelectorAll('.officer-item').forEach(item => {
        item.addEventListener('click', function() {
            const officerId = this.getAttribute('data-officer-id');
            const officerType = this.getAttribute('data-officer-type');
            
            // Remove active class from all officer items
            document.querySelectorAll('.officer-item').forEach(officer => {
                officer.classList.remove('active');
            });
            
            // Add active class to clicked officer
            this.classList.add('active');
            
            // Store selection
            selectedOfficerId = officerId;
            selectedOfficerType = officerType;
            
            // Update selection info
            updateSelectionInfo();
        });
    });
}

function loadOfficersForType(type) {
    const officerList = document.getElementById('officerList');
    
    // Show loading
    officerList.innerHTML = `
        <div class="text-center py-4">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
            <p class="text-gray-600">Loading officers...</p>
        </div>
    `;
    
    // Fetch officers for the selected type
    fetch(`../handlers/get_officers.php?type=${type}&case_id=${selectedCaseId}`)
        .then(response => response.text())
        .then(data => {
            officerList.innerHTML = data;
            
            // Re-attach click listeners to officer items
            document.querySelectorAll('.officer-item').forEach(item => {
                item.addEventListener('click', function() {
                    const officerId = this.getAttribute('data-officer-id');
                    const officerType = this.getAttribute('data-officer-type');
                    
                    // Remove active class from all officer items
                    document.querySelectorAll('.officer-item').forEach(officer => {
                        officer.classList.remove('active');
                    });
                    
                    // Add active class to clicked officer
                    this.classList.add('active');
                    
                    // Store selection
                    selectedOfficerId = officerId;
                    selectedOfficerType = officerType;
                    
                    // Update selection info
                    updateSelectionInfo();
                });
            });
            
            // Add a "None" option at the top
            const noneOption = document.createElement('div');
            noneOption.className = 'officer-item';
            noneOption.setAttribute('data-officer-id', '0');
            noneOption.setAttribute('data-officer-type', 'none');
            noneOption.innerHTML = `
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-user-slash text-gray-600"></i>
                    </div>
                    <div>
                        <p class="font-medium">Unassigned / Keep Pending</p>
                        <p class="text-sm text-gray-600">Case will remain in pending queue</p>
                    </div>
                </div>
            `;
            
            noneOption.addEventListener('click', function() {
                document.querySelectorAll('.officer-item').forEach(officer => {
                    officer.classList.remove('active');
                });
                this.classList.add('active');
                selectedOfficerId = '0';
                selectedOfficerType = 'none';
                updateSelectionInfo();
            });
            
            officerList.insertBefore(noneOption, officerList.firstChild);
        })
        .catch(error => {
            officerList.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
                    <p class="text-red-600">Error loading officers</p>
                </div>
            `;
        });
}

function updateSelectionInfo() {
    const selectionInfo = document.getElementById('selectionInfo');
    if (!selectionInfo) return;
    
    if (selectedOfficerId && selectedOfficerType && selectedOfficerId !== '0') {
        const officerName = document.querySelector(`.officer-item[data-officer-id="${selectedOfficerId}"] .officer-name`)?.textContent || 'Selected Officer';
        selectionInfo.innerHTML = `
            <div class="bg-green-50 p-4 rounded-lg mb-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="font-medium">Selected:</span>
                    <span class="ml-2">${officerName}</span>
                    <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${selectedOfficerType === 'lupon' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                        ${selectedOfficerType === 'lupon' ? 'Lupon Member' : 'Tanod'}
                    </span>
                </div>
            </div>
        `;
    } else if (selectedOfficerId === '0') {
        selectionInfo.innerHTML = `
            <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                    <span class="font-medium">Case will remain unassigned and pending.</span>
                </div>
            </div>
        `;
    } else {
        selectionInfo.innerHTML = '';
    }
}

function submitAssignment() {
    if (!selectedCaseId) {
        alert('No case selected.');
        return;
    }
    
    // If officer ID is 0, we're keeping it unassigned
    if (selectedOfficerId === '0') {
        if (!confirm('Keep this case unassigned and in pending queue?')) {
            return;
        }
        
        // Submit unassignment via AJAX
        const formData = new FormData();
        formData.append('case_id', selectedCaseId);
        formData.append('action', 'keep_pending');
        
        fetch('../handlers/assign_case.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Case remains pending.');
                closeAssignmentModal();
                // Refresh the page or update the table row
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
        return;
    }
    
    if (!selectedOfficerId || !selectedOfficerType) {
        alert('Please select an officer to assign this case to, or select "Unassigned" to keep pending.');
        return;
    }
    
    // Confirm assignment
    if (!confirm('Are you sure you want to assign this case to the selected officer?')) {
        return;
    }
    
    // Submit assignment via AJAX
    const formData = new FormData();
    formData.append('case_id', selectedCaseId);
    formData.append('officer_id', selectedOfficerId);
    formData.append('officer_type', selectedOfficerType);
    
    fetch('../handlers/assign_case.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Case assigned successfully!');
            closeAssignmentModal();
            // Refresh the page or update the table row
            location.reload();
        } else {
            alert('Error assigning case: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error assigning case: ' + error.message);
    });
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

function openNewBlotterModal() {
    document.getElementById('newBlotterModal').classList.remove('hidden');
    document.getElementById('newBlotterModal').classList.add('flex');
}

function closeNewBlotterModal() {
    document.getElementById('newBlotterModal').classList.add('hidden');
    document.getElementById('newBlotterModal').classList.remove('flex');
    document.getElementById('newBlotterForm').reset();
    document.getElementById('fileList').innerHTML = '';
}

// Print case details
function printCaseDetails() {
    const content = document.getElementById('caseDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Case Report - Print</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
                    .section { margin-bottom: 20px; }
                    .label { font-weight: bold; color: #555; }
                    .value { margin-bottom: 10px; }
                    .attachments { margin-top: 20px; }
                    .file-item { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
                    @media print {
                        button { display: none; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Barangay Case Report</h1>
                    <p>Printed on ${new Date().toLocaleDateString()}</p>
                </div>
                ${content}
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                        Close
                    </button>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['caseDetailsModal', 'attachmentsModal', 'newBlotterModal', 'assignmentModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            if (modalId === 'caseDetailsModal') closeCaseDetailsModal();
            if (modalId === 'attachmentsModal') closeAttachmentsModal();
            if (modalId === 'newBlotterModal') closeNewBlotterModal();
            if (modalId === 'assignmentModal') closeAssignmentModal();
        }
    });
}

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>