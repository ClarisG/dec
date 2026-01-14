<?php
// lupon/modules/settlement_document.php

// Fetch cases ready for settlement
$settlement_cases_query = "SELECT r.*, 
                                  u.first_name as complainant_fname, 
                                  u.last_name as complainant_lname,
                                  u2.first_name as respondent_fname,
                                  u2.last_name as respondent_lname,
                                  rt.type_name
                           FROM reports r
                           JOIN users u ON r.user_id = u.id
                           LEFT JOIN users u2 ON r.assigned_to = u2.id
                           JOIN report_types rt ON r.report_type_id = rt.id
                           WHERE r.assigned_lupon = :lupon_id
                           AND r.status IN ('in_mediation', 'mediation_complete')
                           ORDER BY r.created_at DESC";
$settlement_cases_stmt = $conn->prepare($settlement_cases_query);
$settlement_cases_stmt->execute([':lupon_id' => $user_id]);
$settlement_cases = $settlement_cases_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Settlement Document Preparation</h2>
            <p class="text-gray-600">Generate and digitally sign amicable settlement agreements</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="showTemplates()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-file-alt mr-2"></i> View Templates
            </button>
        </div>
    </div>

    <!-- Document Templates -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-6 rounded-xl shadow-sm border text-center hover:shadow-md cursor-pointer" 
             onclick="generateDocument('amicable_settlement')">
            <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-handshake text-green-600 text-2xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 mb-2">Amicable Settlement</h4>
            <p class="text-sm text-gray-600">Standard agreement for resolved disputes</p>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border text-center hover:shadow-md cursor-pointer"
             onclick="generateDocument('mediation_report')">
            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-file-contract text-blue-600 text-2xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 mb-2">Mediation Report</h4>
            <p class="text-sm text-gray-600">Official report of mediation proceedings</p>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border text-center hover:shadow-md cursor-pointer"
             onclick="generateDocument('closure_certificate')">
            <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-certificate text-purple-600 text-2xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 mb-2">Closure Certificate</h4>
            <p class="text-sm text-gray-600">Certificate of case resolution and closure</p>
        </div>
    </div>

    <!-- Cases Ready for Settlement -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800">Cases Ready for Settlement</h3>
            <p class="text-sm text-gray-600">Select a case to generate settlement documents</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parties</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mediation Outcome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($settlement_cases)): ?>
                        <?php foreach ($settlement_cases as $case): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo $case['report_number']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $case['type_name']; ?></div>
                                        <div class="text-xs text-gray-400">
                                            Filed: <?php echo date('M d, Y', strtotime($case['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-700">Complainant:</div>
                                        <div class="text-gray-600">
                                            <?php echo $case['complainant_fname'] . ' ' . $case['complainant_lname']; ?>
                                        </div>
                                        <?php if ($case['respondent_fname']): ?>
                                            <div class="font-medium text-gray-700 mt-2">Respondent:</div>
                                            <div class="text-gray-600">
                                                <?php echo $case['respondent_fname'] . ' ' . $case['respondent_lname']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $outcome = !empty($case['resolution']) ? 'Settled' : 'Pending Agreement';
                                    $outcome_class = !empty($case['resolution']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                    ?>
                                    <span class="px-3 py-1 text-xs rounded-full <?php echo $outcome_class; ?>">
                                        <?php echo $outcome; ?>
                                    </span>
                                    <?php if (!empty($case['resolution'])): ?>
                                        <p class="text-xs text-gray-600 mt-2 truncate max-w-xs">
                                            <?php echo substr($case['resolution'], 0, 100); ?>...
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="generateSettlement(<?php echo $case['id']; ?>)" 
                                                class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                                            <i class="fas fa-file-contract mr-1"></i> Generate
                                        </button>
                                        <button onclick="previewDocument(<?php echo $case['id']; ?>)" 
                                                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                                            <i class="fas fa-eye mr-1"></i> Preview
                                        </button>
                                        <button onclick="downloadDocuments(<?php echo $case['id']; ?>)" 
                                                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                                            <i class="fas fa-download mr-1"></i> Docs
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-file-contract text-3xl mb-3"></i>
                                <p>No cases ready for settlement</p>
                                <p class="text-sm mt-2">Complete mediation sessions to generate settlement documents</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Generated Documents -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Recently Generated Documents</h3>
        
        <div class="space-y-4">
            <?php
            $documents_query = "SELECT * FROM settlement_documents 
                               WHERE generated_by = :lupon_id
                               ORDER BY generated_at DESC 
                               LIMIT 5";
            $documents_stmt = $conn->prepare($documents_query);
            $documents_stmt->execute([':lupon_id' => $user_id]);
            $recent_docs = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (!empty($recent_docs)): ?>
                <?php foreach ($recent_docs as $doc): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="mr-4">
                                <div class="w-12 h-12 rounded-lg <?php 
                                    echo $doc['document_type'] == 'amicable_settlement' ? 'bg-green-100 text-green-600' :
                                    ($doc['document_type'] == 'mediation_report' ? 'bg-blue-100 text-blue-600' :
                                    'bg-purple-100 text-purple-600'); 
                                ?> flex items-center justify-center">
                                    <i class="fas fa-<?php 
                                        echo $doc['document_type'] == 'amicable_settlement' ? 'handshake' :
                                        ($doc['document_type'] == 'mediation_report' ? 'file-contract' : 'certificate'); 
                                    ?>"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                </h4>
                                <p class="text-sm text-gray-600">Case #<?php echo $doc['report_number']; ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Generated: <?php echo date('M d, Y h:i A', strtotime($doc['generated_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="viewDocument(<?php echo $doc['id']; ?>)" 
                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700">
                                <i class="fas fa-eye mr-1"></i> View
                            </button>
                            <button onclick="downloadDocument(<?php echo $doc['id']; ?>)" 
                                    class="px-3 py-1 border border-gray-300 text-gray-700 text-xs rounded-lg hover:bg-gray-50">
                                <i class="fas fa-download mr-1"></i> Download
                            </button>
                            <?php if ($doc['signature_status'] == 'pending'): ?>
                                <button onclick="signDocument(<?php echo $doc['id']; ?>)" 
                                        class="px-3 py-1 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">
                                    <i class="fas fa-signature mr-1"></i> Sign
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-file-export text-3xl mb-3"></i>
                    <p>No documents generated yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTemplates() {
    window.open('../templates/lupon_documents.html', '_blank');
}

function generateDocument(docType) {
    alert('Generating ' + docType.replace('_', ' ') + ' template...');
    // In production: Redirect to document generation page with template
}

function generateSettlement(caseId) {
    if (confirm('Generate amicable settlement agreement for this case?')) {
        window.open('../ajax/generate_settlement.php?case_id=' + caseId, '_blank');
    }
}

function previewDocument(caseId) {
    window.open('../ajax/preview_document.php?case_id=' + caseId, '_blank');
}

function downloadDocuments(caseId) {
    window.open('../ajax/download_case_docs.php?case_id=' + caseId, '_blank');
}

function viewDocument(docId) {
    window.open('../ajax/view_document.php?doc_id=' + docId, '_blank');
}

function downloadDocument(docId) {
    window.open('../ajax/download_document.php?doc_id=' + docId, '_blank');
}

function signDocument(docId) {
    if (confirm('Apply your digital signature to this document?')) {
        // Capture signature
        const signature = prompt('Enter your digital signature code (PIN):');
        if (signature) {
            fetch('../ajax/sign_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'doc_id=' + docId + '&signature=' + signature
            }).then(() => {
                alert('Document signed successfully!');
                location.reload();
            });
        }
    }
}
</script>