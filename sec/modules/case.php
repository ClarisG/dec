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
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            try {
                                $blotter_query = "SELECT CONCAT('BLT-', YEAR(NOW()), '-', LPAD(COUNT(*), 3, '0')) as last_blotter FROM blotter_records WHERE YEAR(created_at) = YEAR(NOW())";
                                $blotter_stmt = $conn->prepare($blotter_query);
                                $blotter_stmt->execute();
                                $blotter_count = $blotter_stmt->fetch(PDO::FETCH_ASSOC);
                                echo 'BLT-' . date('Y') . '-001 to ' . ($blotter_count ? '0' . $blotter_count['last_blotter'] : '045');
                            } catch (Exception $e) {
                                echo 'BLT-' . date('Y') . '-001 to 045';
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
                        <p class="text-sm text-gray-600">Assigned Lupon Members</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            try {
                                $lupon_query = "SELECT COUNT(*) as count FROM users WHERE role IN ('lupon', 'lupon_chairman') AND status = 'active'";
                                $lupon_stmt = $conn->prepare($lupon_query);
                                $lupon_stmt->execute();
                                $lupon_count = $lupon_stmt->fetch(PDO::FETCH_ASSOC);
                                echo ($lupon_count['count'] ?? 0) . ' Active';
                            } catch (Exception $e) {
                                echo '12 Active';
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
                        <p class="text-sm text-gray-600">Formal Case Notes</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?php
                            try {
                                $notes_query = "SELECT COUNT(*) as count FROM case_notes";
                                $notes_stmt = $conn->prepare($notes_query);
                                $notes_stmt->execute();
                                $notes_count = $notes_stmt->fetch(PDO::FETCH_ASSOC);
                                echo ($notes_count['count'] ?? 0) . ' Entries';
                            } catch (Exception $e) {
                                echo '156 Entries';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Filter Reports</h3>
            <div class="flex space-x-2">
                <button id="filterAll" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    All
                </button>
                <button id="filterBarangay" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Barangay Matters
                </button>
                <button id="filterCriminal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Criminal
                </button>
                <button id="filterCivil" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Civil
                </button>
            </div>
        </div>
        
        <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Categories</option>
                    <option value="Barangay Matter">Barangay Matter</option>
                    <option value="Criminal">Criminal</option>
                    <option value="Civil">Civil</option>
                    <option value="VAWC">VAWC</option>
                    <option value="Minor">Minor</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-end space-x-2">
                <button type="button" id="clearFilter" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Clear
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Apply
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
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                            <p>Loading cases...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="flex justify-center items-center mt-6 space-x-2" id="paginationContainer">
            <!-- Pagination will be loaded here -->
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
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end space-x-3">
            <button onclick="closeCaseDetailsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Close
            </button>
            <button onclick="printCaseDetails()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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
        </div>
        
        <div class="p-6 border-t bg-gray-50 flex justify-end">
            <button onclick="closeAttachmentsModal()" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
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
            <form id="newBlotterForm" method="POST" action="../../handlers/create_blotter.php" enctype="multipart/form-data">
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
                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
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
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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
    
    .badge-pending {
        background-color: #fef3c7;
        color: #92400e;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-assigned {
        background-color: #dbeafe;
        color: #1e40af;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-in-progress {
        background-color: #f3e8ff;
        color: #7c3aed;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-resolved {
        background-color: #d1fae5;
        color: #065f46;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .badge-closed {
        background-color: #e5e7eb;
        color: #374151;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
    }
    
    .pagination-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        background-color: white;
        color: #374151;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pagination-btn:hover:not(:disabled) {
        background-color: #f9fafb;
    }
    
    .pagination-btn.active {
        background-color: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .category-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .category-barangay {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .category-criminal {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .category-civil {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .category-vawc {
        background-color: #f3e8ff;
        color: #7c3aed;
    }
    
    .category-minor {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .category-other {
        background-color: #e5e7eb;
        color: #374151;
    }
</style>

<script>
// Current page state
let currentPage = 1;
let totalPages = 1;
let currentFilter = {
    status: 'pending',
    category: '',
    from_date: '',
    to_date: ''
};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadCases();
    setupFilterListeners();
    setupFileUpload();
});

// Setup filter listeners
function setupFilterListeners() {
    // Quick filter buttons
    document.getElementById('filterAll').addEventListener('click', function() {
        resetFilters();
        currentFilter.status = '';
        currentPage = 1;
        loadCases();
        updateFilterButtons('all');
    });

    document.getElementById('filterBarangay').addEventListener('click', function() {
        resetFilters();
        currentFilter.category = 'Barangay Matter';
        document.querySelector('select[name="category"]').value = 'Barangay Matter';
        currentPage = 1;
        loadCases();
        updateFilterButtons('barangay');
    });

    document.getElementById('filterCriminal').addEventListener('click', function() {
        resetFilters();
        currentFilter.category = 'Criminal';
        document.querySelector('select[name="category"]').value = 'Criminal';
        currentPage = 1;
        loadCases();
        updateFilterButtons('criminal');
    });

    document.getElementById('filterCivil').addEventListener('click', function() {
        resetFilters();
        currentFilter.category = 'Civil';
        document.querySelector('select[name="category"]').value = 'Civil';
        currentPage = 1;
        loadCases();
        updateFilterButtons('civil');
    });

    // Filter form submission
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentFilter = {
            status: document.querySelector('select[name="status"]').value || '',
            category: document.querySelector('select[name="category"]').value || '',
            from_date: document.querySelector('input[name="from_date"]').value || '',
            to_date: document.querySelector('input[name="to_date"]').value || ''
        };
        currentPage = 1;
        loadCases();
        updateFilterButtons('custom');
    });

    // Clear filter button
    document.getElementById('clearFilter').addEventListener('click', function() {
        resetFilters();
        currentFilter = {
            status: 'pending',
            category: '',
            from_date: '',
            to_date: ''
        };
        currentPage = 1;
        loadCases();
        updateFilterButtons('all');
    });
}

function resetFilters() {
    document.querySelector('select[name="status"]').value = '';
    document.querySelector('select[name="category"]').value = '';
    document.querySelector('input[name="from_date"]').value = '';
    document.querySelector('input[name="to_date"]').value = '';
}

function updateFilterButtons(activeFilter) {
    const buttons = {
        all: document.getElementById('filterAll'),
        barangay: document.getElementById('filterBarangay'),
        criminal: document.getElementById('filterCriminal'),
        civil: document.getElementById('filterCivil')
    };

    // Reset all buttons
    Object.values(buttons).forEach(btn => {
        if (btn) {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-700');
        }
    });

    // Set active button
    if (buttons[activeFilter]) {
        buttons[activeFilter].classList.remove('bg-gray-100', 'text-gray-700');
        buttons[activeFilter].classList.add('bg-blue-600', 'text-white');
    }
}

// Load cases with pagination
function loadCases() {
    const tableBody = document.getElementById('casesTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="py-8 text-center text-gray-500">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                <p>Loading cases...</p>
            </td>
        </tr>
    `;
    
    // Build query string
    const queryParams = new URLSearchParams({
        page: currentPage,
        ...currentFilter
    });
    
    // Remove empty values
    queryParams.forEach((value, key) => {
        if (!value) queryParams.delete(key);
    });
    
    fetch(`../../handlers/load_cases.php?${queryParams}`)
        .then(response => {
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Expected JSON response but got: ' + contentType);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderCasesTable(data.cases);
                renderPagination(data.totalPages, data.currentPage, data.totalRecords);
                document.getElementById('currentPage').textContent = data.currentPage;
                document.getElementById('totalPages').textContent = data.totalPages;
            } else {
                showError(data.message || 'Failed to load cases');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showError('Error loading cases. Please try again.');
            // Try fallback
            setTimeout(() => {
                loadFallbackCases();
            }, 1000);
        });
}

function loadFallbackCases() {
    const tableBody = document.getElementById('casesTableBody');
    
    // Simple fallback - show static message
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="py-8 text-center text-gray-500">
                <i class="fas fa-database text-4xl mb-4 text-gray-300"></i>
                <p>Unable to load cases at the moment.</p>
                <p class="text-sm text-gray-400 mt-2">Please check your connection and try again.</p>
            </td>
        </tr>
    `;
}

function showError(message) {
    const tableBody = document.getElementById('casesTableBody');
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="py-8 text-center text-red-500">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                ${message}
            </td>
        </tr>
    `;
}

function renderCasesTable(cases) {
    const tableBody = document.getElementById('casesTableBody');
    
    if (!cases || cases.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                    <p>No cases found matching your criteria.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    cases.forEach(caseItem => {
        const statusClass = getStatusClass(caseItem.status);
        const statusText = formatStatusText(caseItem.status);
        const categoryClass = getCategoryClass(caseItem.category);
        const formattedDate = formatDate(caseItem.created_at);
        const complainantName = escapeHtml(caseItem.complainant_name || 'Unknown');
        const category = escapeHtml(caseItem.category || 'Uncategorized');
        
        html += `
            <tr data-case-id="${caseItem.id}" class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-4">
                    <span class="font-medium text-blue-600">#${caseItem.id}</span>
                    ${caseItem.blotter_number ? 
                        `<p class="text-xs text-green-600 mt-1">${escapeHtml(caseItem.blotter_number)}</p>` : 
                        '<p class="text-xs text-gray-500 mt-1">Needs blotter number</p>'
                    }
                </td>
                <td class="py-3 px-4">${formattedDate}</td>
                <td class="py-3 px-4">${complainantName}</td>
                <td class="py-3 px-4">
                    <span class="category-badge ${categoryClass}">
                        ${category}
                    </span>
                </td>
                <td class="py-3 px-4">
                    ${caseItem.attachment_count > 0 ? 
                        `<div class="flex items-center">
                            <span class="mr-2 text-sm text-gray-600">${caseItem.attachment_count} file(s)</span>
                            <button onclick="viewAttachments(${caseItem.id})" class="text-blue-600 hover:text-blue-800 transition-colors" title="View attachments">
                                <i class="fas fa-paperclip"></i>
                            </button>
                        </div>` : 
                        '<span class="text-gray-400 text-sm">No attachments</span>'
                    }
                </td>
                <td class="py-3 px-4">
                    <span class="${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex space-x-2">
                        <button onclick="viewCaseDetails(${caseItem.id})" 
                                class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition-colors" 
                                title="View full report">
                            <i class="fas fa-eye mr-1"></i> View
                        </button>
                        ${caseItem.status === 'pending' ? 
                            `<button onclick="openAssignmentModal(${caseItem.id})" 
                                    class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors" 
                                    title="Assign to officer">
                                <i class="fas fa-user-check mr-1"></i> Assign
                            </button>` : 
                            `<button class="px-3 py-1 bg-gray-300 text-gray-600 rounded-lg text-sm cursor-not-allowed" 
                                    title="Already assigned">
                                <i class="fas fa-user-check mr-1"></i> Assigned
                            </button>`
                        }
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

function renderPagination(totalPages, currentPage, totalRecords) {
    const paginationContainer = document.getElementById('paginationContainer');
    
    if (totalPages <= 1) {
        paginationContainer.innerHTML = `
            <div class="text-gray-600">
                Showing ${totalRecords} records
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="flex items-center space-x-2">
            <button onclick="changePage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''} class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </button>
    `;
    
    // Show page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <button onclick="changePage(${i})" class="pagination-btn ${i === currentPage ? 'active' : ''}">
                ${i}
            </button>
        `;
    }
    
    html += `
            <button onclick="changePage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''} class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="text-gray-600 ml-4">
            Page ${currentPage} of ${totalPages} • ${totalRecords} records
        </div>
    `;
    
    paginationContainer.innerHTML = html;
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadCases();
    // Smooth scroll to top of table
    document.querySelector('.case-table').scrollIntoView({ behavior: 'smooth' });
}

// Utility functions
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Invalid Date';
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: '2-digit', 
        year: 'numeric' 
    });
}

function formatStatusText(status) {
    if (!status) return 'UNKNOWN';
    return status.replace('_', ' ').toUpperCase();
}

function getStatusClass(status) {
    switch(status) {
        case 'pending': return 'badge-pending';
        case 'assigned': return 'badge-assigned';
        case 'in_progress': return 'badge-in-progress';
        case 'resolved': return 'badge-resolved';
        case 'closed': return 'badge-closed';
        default: return 'badge-pending';
    }
}

function getCategoryClass(category) {
    switch(category) {
        case 'Barangay Matter': return 'category-barangay';
        case 'Criminal': return 'category-criminal';
        case 'Civil': return 'category-civil';
        case 'VAWC': return 'category-vawc';
        case 'Minor': return 'category-minor';
        default: return 'category-other';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// File upload handling
function setupFileUpload() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
}

function handleFileSelect(e) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    Array.from(e.target.files).forEach(file => {
        if (file.size > 10 * 1024 * 1024) { // 10MB limit
            alert(`File ${file.name} exceeds 10MB limit. Skipping.`);
            return;
        }
        
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
}

function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) {
        return { class: 'file-icon-image', icon: 'fas fa-image' };
    } else if (['pdf'].includes(ext)) {
        return { class: 'file-icon-pdf', icon: 'fas fa-file-pdf' };
    } else if (['doc', 'docx', 'txt', 'rtf'].includes(ext)) {
        return { class: 'file-icon-doc', icon: 'fas fa-file-word' };
    } else if (['mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv'].includes(ext)) {
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
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading case details...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    fetch(`../../handlers/get_case_details.php?id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading case details:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <p class="text-red-600">Error loading case details</p>
                    <p class="text-sm text-gray-500 mt-2">${error.message}</p>
                </div>
            `;
        });
}

// View attachments
function viewAttachments(caseId) {
    const modal = document.getElementById('attachmentsModal');
    const content = document.getElementById('attachmentsContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading attachments...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    fetch(`../../handlers/get_attachments.php?report_id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading attachments:', error);
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
let selectedAssignmentTitle = null;

function openAssignmentModal(caseId) {
    selectedCaseId = caseId;
    selectedOfficerId = null;
    selectedOfficerType = null;
    selectedAssignmentTitle = null;
    
    const modal = document.getElementById('assignmentModal');
    const content = document.getElementById('assignmentModalContent');
    
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-600">Loading assignment options...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    fetch(`../../handlers/get_assignment_options.php?case_id=${caseId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
            attachAssignmentListeners();
        })
        .catch(error => {
            console.error('Error loading assignment options:', error);
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
    selectedAssignmentTitle = null;
}

function attachAssignmentListeners() {
    document.querySelectorAll('.assignment-option').forEach(option => {
        option.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            const title = this.querySelector('h5').textContent.trim();
            
            document.querySelectorAll('.assignment-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            this.classList.add('active');
            selectedAssignmentTitle = title;
            loadOfficersForType(type);
        });
    });
}

function loadOfficersForType(type) {
    const officerList = document.getElementById('officerList');
    
    officerList.innerHTML = `
        <div class="text-center py-4">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
            <p class="text-gray-600">Loading officers...</p>
        </div>
    `;
    
    fetch(`../../handlers/get_officers.php?type=${type}&case_id=${selectedCaseId}`)
        .then(response => response.text())
        .then(data => {
            officerList.innerHTML = data;
            
            // Add officer selection listeners
            document.querySelectorAll('.officer-item').forEach(item => {
                item.addEventListener('click', function() {
                    const officerId = this.getAttribute('data-officer-id');
                    const officerType = this.getAttribute('data-officer-type');
                    
                    document.querySelectorAll('.officer-item').forEach(officer => {
                        officer.classList.remove('active');
                    });
                    
                    this.classList.add('active');
                    selectedOfficerId = officerId;
                    selectedOfficerType = officerType;
                    updateSelectionInfo();
                });
            });
            
            updateSelectionInfo();
        })
        .catch(error => {
            console.error('Error loading officers:', error);
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
    
    if (selectedOfficerId && selectedOfficerType) {
        const officerItem = document.querySelector(`.officer-item[data-officer-id="${selectedOfficerId}"]`);
        if (officerItem) {
            const officerName = officerItem.querySelector('.officer-name')?.textContent || 'Selected Officer';
            const displayTitle = selectedAssignmentTitle || 
                               (selectedOfficerType === 'lupon' ? 'Lupon Member' : 
                               selectedOfficerType === 'lupon_chairman' ? 'Lupon Chairman' : 'Tanod');
            
            selectionInfo.innerHTML = `
                <div class="bg-green-50 p-4 rounded-lg mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="font-medium">Selected:</span>
                        <span class="ml-2">${officerName}</span>
                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${selectedOfficerType.includes('lupon') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                            ${displayTitle}
                        </span>
                    </div>
                </div>
            `;
        }
    } else {
        selectionInfo.innerHTML = '';
    }
}

function submitAssignment() {
    if (!selectedCaseId) {
        alert('No case selected.');
        return;
    }
    
    if (!selectedOfficerId || !selectedOfficerType) {
        alert('Please select an officer to assign this case to.');
        return;
    }
    
    const confirmMessage = `Are you sure you want to assign Case #${selectedCaseId} to the selected officer?`;
    if (!confirm(confirmMessage)) return;
    
    const formData = new FormData();
    formData.append('case_id', selectedCaseId);
    formData.append('officer_id', selectedOfficerId);
    formData.append('officer_type', selectedOfficerType);
    
    fetch('../../handlers/assign_case.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Case assigned successfully!');
            closeAssignmentModal();
            // Reload cases to update status
            loadCases();
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
    const form = document.getElementById('newBlotterForm');
    if (form) form.reset();
    const fileList = document.getElementById('fileList');
    if (fileList) fileList.innerHTML = '';
}

// Print case details
function printCaseDetails() {
    const content = document.getElementById('caseDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>Case Report - Print</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 20px;
                        color: #333;
                    }
                    .header { 
                        text-align: center; 
                        border-bottom: 2px solid #333; 
                        padding-bottom: 20px; 
                        margin-bottom: 30px; 
                    }
                    .header h1 {
                        color: #2c3e50;
                        margin-bottom: 10px;
                    }
                    .section { 
                        margin-bottom: 25px; 
                        padding: 15px;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                    }
                    .section-title {
                        color: #2c3e50;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                        margin-bottom: 15px;
                        font-weight: bold;
                    }
                    .info-grid {
                        display: grid;
                        grid-template-columns: 1fr 2fr;
                        gap: 10px;
                        margin-bottom: 10px;
                    }
                    .label { 
                        font-weight: bold; 
                        color: #555; 
                    }
                    .value { 
                        color: #333;
                    }
                    .attachments { 
                        margin-top: 20px; 
                    }
                    .file-item { 
                        margin-bottom: 10px; 
                        padding: 10px; 
                        border: 1px solid #ddd; 
                        border-radius: 5px;
                        background: #f9f9f9;
                    }
                    @media print {
                        button { display: none !important; }
                        .no-print { display: none !important; }
                        body { padding: 0; }
                    }
                    @page {
                        margin: 0.5in;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Barangay Case Report</h1>
                    <p>Printed on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                </div>
                ${content}
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Close Window
                    </button>
                </div>
                <script>
                    // Auto-print after loading
                    setTimeout(function() {
                        window.print();
                    }, 500);
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = [
        { id: 'caseDetailsModal', close: closeCaseDetailsModal },
        { id: 'attachmentsModal', close: closeAttachmentsModal },
        { id: 'newBlotterModal', close: closeNewBlotterModal },
        { id: 'assignmentModal', close: closeAssignmentModal }
    ];
    
    modals.forEach(modal => {
        const modalElement = document.getElementById(modal.id);
        if (event.target === modalElement) {
            modal.close();
        }
    });
}

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCaseDetailsModal();
        closeAttachmentsModal();
        closeNewBlotterModal();
        closeAssignmentModal();
    }
});
</script>