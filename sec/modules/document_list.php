<!-- Document Listing Module -->
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Generated Documents</h2>
        <div class="flex space-x-3">
            <select id="documentTypeFilter" onchange="filterDocuments()" 
                    class="border border-gray-300 rounded-lg px-3 py-2">
                <option value="all">All Documents</option>
                <option value="subpoena">Subpoena/Summons</option>
                <option value="notice_of_hearing">Notice of Hearing</option>
                <option value="certificate_to_file">Certificate to File Action</option>
                <option value="barangay_resolution">Barangay Resolution</option>
                <option value="protection_order">Protection Order</option>
                <option value="settlement_agreement">Settlement Agreement</option>
            </select>
            <input type="date" id="dateFilter" onchange="filterDocuments()" 
                   class="border border-gray-300 rounded-lg px-3 py-2">
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case/Report</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issued To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Generated</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="documentsTableBody" class="bg-white divide-y divide-gray-200">
                    <!-- Documents will be loaded via AJAX -->
                </tbody>
            </table>
        </div>
        <div id="documentsPagination" class="px-6 py-4 border-t border-gray-200">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDocuments();
});

function loadDocuments(page = 1) {
    const type = document.getElementById('documentTypeFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    fetch(`../ajax/get_documents.php?page=${page}&type=${type}&date=${date}`)
        .then(response => response.json())
        .then(data => {
            updateDocumentsTable(data.documents);
            updatePagination(data.pagination);
        })
        .catch(error => {
            console.error('Error loading documents:', error);
            showAlert('error', 'Failed to load documents');
        });
}

function updateDocumentsTable(documents) {
    const tbody = document.getElementById('documentsTableBody');
    tbody.innerHTML = '';
    
    if (documents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    No documents found
                </td>
            </tr>
        `;
        return;
    }
    
    documents.forEach(doc => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        
        const typeBadge = getDocumentTypeBadge(doc.document_type);
        const statusBadge = getStatusBadge(doc.status);
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${doc.document_number}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${typeBadge}
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-900">${doc.report_title || 'N/A'}</div>
                <div class="text-xs text-gray-500">${doc.report_number || ''}</div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-900">${doc.issued_to || 'N/A'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${formatDate(doc.created_at)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${statusBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="viewDocument('${doc.document_number}')" 
                        class="text-blue-600 hover:text-blue-900 mr-3">
                    <i class="fas fa-eye"></i> View
                </button>
                <button onclick="downloadDocument('${doc.document_number}')" 
                        class="text-green-600 hover:text-green-900 mr-3">
                    <i class="fas fa-download"></i> PDF
                </button>
                ${doc.status === 'draft' ? `
                <button onclick="editDocument('${doc.id}', '${doc.document_type}')" 
                        class="text-yellow-600 hover:text-yellow-900 mr-3">
                    <i class="fas fa-edit"></i> Edit
                </button>
                ` : ''}
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function getDocumentTypeBadge(type) {
    const badges = {
        'subpoena': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Subpoena</span>',
        'notice_of_hearing': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Notice</span>',
        'certificate_to_file': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Certificate</span>',
        'barangay_resolution': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Resolution</span>',
        'protection_order': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Protection</span>',
        'settlement_agreement': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Settlement</span>'
    };
    return badges[type] || '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' + type + '</span>';
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Draft</span>',
        'issued': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Issued</span>',
        'served': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Served</span>',
        'signed': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Signed</span>',
        'completed': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Completed</span>',
        'archived': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Archived</span>'
    };
    return badges[status] || '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' + status + '</span>';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function filterDocuments() {
    loadDocuments(1);
}

function viewDocument(documentNumber) {
    window.open(`../ajax/view_document.php?doc=${documentNumber}`, '_blank');
}

function downloadDocument(documentNumber) {
    window.location.href = `../ajax/download_document.php?doc=${documentNumber}`;
}

function editDocument(documentId, documentType) {
    // Implement edit functionality
    alert('Edit functionality for ' + documentType + ' coming soon!');
}

function updatePagination(pagination) {
    const container = document.getElementById('documentsPagination');
    
    let html = `
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing <span class="font-medium">${pagination.start}</span> to 
                <span class="font-medium">${pagination.end}</span> of 
                <span class="font-medium">${pagination.total}</span> documents
            </div>
            <div class="flex space-x-2">
    `;
    
    // Previous button
    if (pagination.current > 1) {
        html += `<button onclick="loadDocuments(${pagination.current - 1})" 
                 class="px-3 py-1 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                    Previous
                </button>`;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === pagination.current) {
            html += `<span class="px-3 py-1 bg-blue-600 text-white rounded-md text-sm">${i}</span>`;
        } else if (i >= pagination.current - 2 && i <= pagination.current + 2) {
            html += `<button onclick="loadDocuments(${i})" 
                     class="px-3 py-1 border border-gray-300 rounded-md text-sm hover:bg-gray-50">${i}</button>`;
        }
    }
    
    // Next button
    if (pagination.current < pagination.pages) {
        html += `<button onclick="loadDocuments(${pagination.current + 1})" 
                 class="px-3 py-1 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                    Next
                </button>`;
    }
    
    html += `</div></div>`;
    container.innerHTML = html;
}
</script>