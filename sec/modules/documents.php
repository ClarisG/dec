<!-- Document Generation Module -->
<div class="space-y-8">
    <div class="glass-card rounded-xl p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-file-pdf mr-3 text-blue-600"></i>
            Document Generation Center
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Subpoena/Summons -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-file-contract text-blue-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Subpoena/Summons</h3>
                <p class="text-gray-600 text-sm mb-4">Official notice to appear for barangay hearing</p>
                <button onclick="openSubpoenaModal()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
            
            <!-- Notice of Hearing -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-calendar-alt text-green-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Notice of Hearing</h3>
                <p class="text-gray-600 text-sm mb-4">Schedule and notify about barangay hearings</p>
                <button onclick="openNoticeOfHearingModal()" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
            
            <!-- Certificate to File Action -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-certificate text-purple-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Certificate to File Action</h3>
                <p class="text-gray-600 text-sm mb-4">Authorization for court filing after barangay proceedings</p>
                <button onclick="openCertificateToFileModal()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
            
            <!-- Barangay Resolution -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-gavel text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Barangay Resolution</h3>
                <p class="text-gray-600 text-sm mb-4">Formal decision on barangay matters</p>
                <button onclick="openBarangayResolutionModal()" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
            
            <!-- Protection Order -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-red-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-user-shield text-red-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Protection Order</h3>
                <p class="text-gray-600 text-sm mb-4">For VAWC and harassment cases</p>
                <button onclick="openProtectionOrderModal()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
            
            <!-- Settlement Agreement -->
            <div class="module-card bg-white rounded-xl p-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-file-signature text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Settlement Agreement</h3>
                <p class="text-gray-600 text-sm mb-4">Amicable settlement documents</p>
                <button onclick="openSettlementAgreementModal()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg">
                    Generate Document
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Document Listing Section -->
<div class="mt-12">
    <?php include 'document_list.php'; ?>
</div>

<!-- Include the modals component -->
<?php include 'document_modals.php'; ?>
