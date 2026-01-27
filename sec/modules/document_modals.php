<!-- Subpoena/Summons Modal -->
<div id="subpoenaModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Subpoena/Summons</h3>
            <button onclick="closeModal('subpoenaModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="subpoenaForm" onsubmit="generateSubpoena(event)">
            <div class="space-y-4">
                <!-- Report Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="subpoenaReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            onchange="loadReportDetails('subpoena')">
                        <option value="">-- Select Report --</option>
                        <!-- Will be populated via AJAX -->
                    </select>
                </div>
                
                <!-- Case Details (auto-filled) -->
                <div id="subpoenaCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="subpoenaCaseNo">-</span></div>
                        <div><span class="font-medium">Complainant:</span> <span id="subpoenaComplainant">-</span></div>
                        <div><span class="font-medium">Respondent:</span> <span id="subpoenaRespondent">-</span></div>
                        <div><span class="font-medium">Incident:</span> <span id="subpoenaIncident">-</span></div>
                    </div>
                </div>
                
                <!-- Hearing Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Date *</label>
                        <input type="date" id="subpoenaHearingDate" name="hearing_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Time *</label>
                        <input type="time" id="subpoenaHearingTime" name="hearing_time" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Location *</label>
                    <input type="text" id="subpoenaLocation" name="location" required 
                           placeholder="Barangay Hall, Conference Room" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Recipients -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recipients (Separate with commas) *</label>
                    <textarea id="subpoenaRecipients" name="recipients" rows="3" required 
                              placeholder="Juan Dela Cruz, Maria Santos, Pedro Reyes" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <!-- Additional Instructions -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Instructions</label>
                    <textarea id="subpoenaInstructions" name="instructions" rows="3" 
                              placeholder="Bring any relevant documents or evidence..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <!-- Issuing Officer -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issuing Officer *</label>
                    <input type="text" id="subpoenaIssuingOfficer" name="issuing_officer" required 
                           placeholder="Barangay Captain" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('subpoenaModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notice of Hearing Modal -->
<div id="noticeOfHearingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Similar structure as subpoena modal with specific fields for Notice of Hearing -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Notice of Hearing</h3>
            <button onclick="closeModal('noticeOfHearingModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="noticeOfHearingForm" onsubmit="generateNoticeOfHearing(event)">
            <div class="space-y-4">
                <!-- Similar form fields tailored for Notice of Hearing -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="noticeReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                            onchange="loadReportDetails('notice')">
                        <option value="">-- Select Report --</option>
                    </select>
                </div>
                
                <div id="noticeCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="noticeCaseNo">-</span></div>
                        <div><span class="font-medium">Parties:</span> <span id="noticeParties">-</span></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Date *</label>
                        <input type="date" id="noticeHearingDate" name="hearing_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Time *</label>
                        <input type="time" id="noticeHearingTime" name="hearing_time" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Venue *</label>
                    <input type="text" id="noticeVenue" name="venue" required 
                           placeholder="Barangay Hall Main Conference Room" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Purpose of Hearing *</label>
                    <textarea id="noticePurpose" name="purpose" rows="3" required 
                              placeholder="Mediation/Conciliation Hearing for the dispute between..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Required to Bring</label>
                    <textarea id="noticeRequirements" name="requirements" rows="2" 
                              placeholder="Any evidence, documents, or witnesses..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issued By *</label>
                    <input type="text" id="noticeIssuedBy" name="issued_by" required 
                           placeholder="Barangay Secretary / Barangay Captain" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('noticeOfHearingModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Certificate to File Action Modal -->
<div id="certificateToFileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Structure for Certificate to File Action -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Certificate to File Action</h3>
            <button onclick="closeModal('certificateToFileModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="certificateToFileForm" onsubmit="generateCertificateToFile(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="certificateReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"
                            onchange="loadReportDetails('certificate')">
                        <option value="">-- Select Report --</option>
                    </select>
                </div>
                
                <div id="certificateCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="certificateCaseNo">-</span></div>
                        <div><span class="font-medium">Nature:</span> <span id="certificateNature">-</span></div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Court/Jurisdiction *</label>
                    <input type="text" id="certificateCourt" name="court" required 
                           placeholder="Municipal Trial Court / Regional Trial Court" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Certification *</label>
                    <select id="certificateReason" name="reason" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        <option value="">-- Select Reason --</option>
                        <option value="mediation_failed">Mediation/Conciliation Failed</option>
                        <option value="party_refused">One Party Refused to Appear</option>
                        <option value="settlement_violated">Settlement Agreement Violated</option>
                        <option value="not_resolvable">Matter Not Resolvable at Barangay Level</option>
                        <option value="criminal_case">Involves Criminal Case</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Barangay Action *</label>
                    <input type="date" id="certificateActionDate" name="action_date" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks/Additional Information</label>
                    <textarea id="certificateRemarks" name="remarks" rows="3" 
                              placeholder="Summary of barangay proceedings..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issuing Authority *</label>
                    <input type="text" id="certificateAuthority" name="authority" required 
                           placeholder="Barangay Captain / Punong Barangay" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('certificateToFileModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Barangay Resolution Modal -->
<div id="barangayResolutionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Structure for Barangay Resolution -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Barangay Resolution</h3>
            <button onclick="closeModal('barangayResolutionModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="barangayResolutionForm" onsubmit="generateBarangayResolution(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="resolutionReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500"
                            onchange="loadReportDetails('resolution')">
                        <option value="">-- Select Report --</option>
                    </select>
                </div>
                
                <div id="resolutionCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="resolutionCaseNo">-</span></div>
                        <div><span class="font-medium">Parties:</span> <span id="resolutionParties">-</span></div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Number *</label>
                    <input type="text" id="resolutionNumber" name="resolution_number" required 
                           placeholder="BR-2024-001" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Title *</label>
                    <input type="text" id="resolutionTitle" name="resolution_title" required 
                           placeholder="Resolution Approving the Amicable Settlement..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Body *</label>
                    <textarea id="resolutionBody" name="resolution_body" rows="6" required 
                              placeholder="WHEREAS, the parties have agreed to settle their dispute...
WHEREAS, the settlement is fair and equitable...
NOW THEREFORE, BE IT RESOLVED..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Voting Results</label>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">In Favor</label>
                            <input type="number" id="resolutionInFavor" name="in_favor" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Against</label>
                            <input type="number" id="resolutionAgainst" name="against" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Abstain</label>
                            <input type="number" id="resolutionAbstain" name="abstain" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Approved By *</label>
                    <input type="text" id="resolutionApprovedBy" name="approved_by" required 
                           placeholder="Barangay Captain / Sangguniang Barangay" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('barangayResolutionModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Protection Order Modal -->
<div id="protectionOrderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Structure for Protection Order -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Protection Order (VAWC)</h3>
            <button onclick="closeModal('protectionOrderModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="protectionOrderForm" onsubmit="generateProtectionOrder(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="protectionReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                            onchange="loadReportDetails('protection')">
                        <option value="">-- Select Report --</option>
                    </select>
                </div>
                
                <div id="protectionCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="protectionCaseNo">-</span></div>
                        <div><span class="font-medium">Victim:</span> <span id="protectionVictim">-</span></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Petitioner/Protected Party *</label>
                        <input type="text" id="protectionPetitioner" name="petitioner" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Respondent *</label>
                        <input type="text" id="protectionRespondent" name="respondent" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type of Violence *</label>
                    <select id="protectionViolenceType" name="violence_type" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                        <option value="">-- Select Type --</option>
                        <option value="physical">Physical Violence</option>
                        <option value="psychological">Psychological Violence</option>
                        <option value="sexual">Sexual Violence</option>
                        <option value="economic">Economic Abuse</option>
                        <option value="multiple">Multiple Forms</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specific Acts of Violence *</label>
                    <textarea id="protectionActs" name="acts" rows="4" required 
                              placeholder="Describe the specific violent acts committed..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Protection Measures *</label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="protectionStayAway" name="measures[]" value="stay_away" class="mr-2">
                            <label for="protectionStayAway">Stay away from petitioner's residence/work/school</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="protectionNoContact" name="measures[]" value="no_contact" class="mr-2">
                            <label for="protectionNoContact">No contact (calls, messages, social media)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="protectionSurrenderWeapons" name="measures[]" value="surrender_weapons" class="mr-2">
                            <label for="protectionSurrenderWeapons">Surrender firearms/weapons</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="protectionCounseling" name="measures[]" value="counseling" class="mr-2">
                            <label for="protectionCounseling">Undergo counseling</label>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Effective Date *</label>
                        <input type="date" id="protectionEffectiveDate" name="effective_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (Days) *</label>
                        <input type="number" id="protectionDuration" name="duration" required min="1" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issuing Officer *</label>
                    <input type="text" id="protectionIssuingOfficer" name="issuing_officer" required 
                           placeholder="Barangay Captain / VAWC Desk Officer" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('protectionOrderModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Settlement Agreement Modal -->
<div id="settlementAgreementModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Structure for Settlement Agreement -->
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generate Settlement Agreement</h3>
            <button onclick="closeModal('settlementAgreementModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="settlementAgreementForm" onsubmit="generateSettlementAgreement(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Case/Report *</label>
                    <select id="settlementReportId" name="report_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            onchange="loadReportDetails('settlement')">
                        <option value="">-- Select Report --</option>
                    </select>
                </div>
                
                <div id="settlementCaseDetails" class="hidden p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-2">Case Details</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-medium">Case No:</span> <span id="settlementCaseNo">-</span></div>
                        <div><span class="font-medium">Parties:</span> <span id="settlementParties">-</span></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Party *</label>
                        <input type="text" id="settlementFirstParty" name="first_party" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Second Party *</label>
                        <input type="text" id="settlementSecondParty" name="second_party" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nature of Dispute *</label>
                    <input type="text" id="settlementNature" name="nature" required 
                           placeholder="Boundary dispute, Debt collection, Neighbor conflict" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms of Settlement *</label>
                    <textarea id="settlementTerms" name="terms" rows="6" required 
                              placeholder="1. The parties agree to...
2. Party A shall...
3. Party B shall...
4. Both parties agree to..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Implementation Timeline</label>
                    <textarea id="settlementTimeline" name="timeline" rows="3" 
                              placeholder="The settlement shall be implemented as follows..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consequences of Violation</label>
                    <textarea id="settlementConsequences" name="consequences" rows="3" 
                              placeholder="In case of violation, the aggrieved party may..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Agreement *</label>
                        <input type="date" id="settlementAgreementDate" name="agreement_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Witnesses (if any)</label>
                        <input type="text" id="settlementWitnesses" name="witnesses" 
                               placeholder="Witness names separated by commas" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mediator/Facilitator *</label>
                    <input type="text" id="settlementMediator" name="mediator" required 
                           placeholder="Lupon Member / Barangay Official" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('settlementAgreementModal')" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Generate Document
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal Functions
function openSubpoenaModal() {
    document.getElementById('subpoenaModal').classList.remove('hidden');
    loadActiveReports('subpoenaReportId');
}

function openNoticeOfHearingModal() {
    document.getElementById('noticeOfHearingModal').classList.remove('hidden');
    loadActiveReports('noticeReportId');
}

function openCertificateToFileModal() {
    document.getElementById('certificateToFileModal').classList.remove('hidden');
    loadActiveReports('certificateReportId');
}

function openBarangayResolutionModal() {
    document.getElementById('barangayResolutionModal').classList.remove('hidden');
    loadActiveReports('resolutionReportId');
}

function openProtectionOrderModal() {
    document.getElementById('protectionOrderModal').classList.remove('hidden');
    loadActiveReports('protectionReportId');
}

function openSettlementAgreementModal() {
    document.getElementById('settlementAgreementModal').classList.remove('hidden');
    loadActiveReports('settlementReportId');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Load active reports for dropdown
function loadActiveReports(selectId) {
    fetch('../ajax/get_active_reports.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Select Report --</option>';
            
            data.forEach(report => {
                const option = document.createElement('option');
                option.value = report.id;
                option.textContent = `#${report.report_number} - ${report.title}`;
                option.setAttribute('data-complainant', report.complainant || '');
                option.setAttribute('data-respondent', report.respondent || '');
                option.setAttribute('data-incident', report.description || '');
                select.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading reports:', error);
            showAlert('error', 'Failed to load reports. Please try again.');
        });
}

// Load report details when report is selected
function loadReportDetails(type) {
    const selectId = type + 'ReportId';
    const select = document.getElementById(selectId);
    const selectedOption = select.options[select.selectedIndex];
    const detailsDiv = document.getElementById(type + 'CaseDetails');
    
    if (select.value) {
        detailsDiv.classList.remove('hidden');
        // Update details based on selected report
        document.getElementById(type + 'CaseNo').textContent = selectedOption.textContent.split(' - ')[0];
        // You can add more specific data extraction here
    } else {
        detailsDiv.classList.add('hidden');
    }
}

// Form Submission Functions
async function generateSubpoena(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_subpoena.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Subpoena generated successfully!');
            closeModal('subpoenaModal');
            event.target.reset();
            // Optionally download the PDF
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

async function generateNoticeOfHearing(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_notice_of_hearing.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Notice of Hearing generated successfully!');
            closeModal('noticeOfHearingModal');
            event.target.reset();
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

async function generateCertificateToFile(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_certificate_to_file.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Certificate to File Action generated successfully!');
            closeModal('certificateToFileModal');
            event.target.reset();
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

async function generateBarangayResolution(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_barangay_resolution.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Barangay Resolution generated successfully!');
            closeModal('barangayResolutionModal');
            event.target.reset();
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

async function generateProtectionOrder(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_protection_order.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Protection Order generated successfully!');
            closeModal('protectionOrderModal');
            event.target.reset();
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

async function generateSettlementAgreement(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('../handlers/generate_settlement_agreement.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showAlert('success', 'Settlement Agreement generated successfully!');
            closeModal('settlementAgreementModal');
            event.target.reset();
            if (result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            }
        } else {
            showAlert('error', result.message || 'Failed to generate document');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'An error occurred. Please try again.');
    }
}

// Utility function for alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-300' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-300' :
        'bg-blue-100 text-blue-800 border border-blue-300'
    }`;
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['subpoenaModal', 'noticeOfHearingModal', 'certificateToFileModal', 
                   'barangayResolutionModal', 'protectionOrderModal', 'settlementAgreementModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
}

// Initialize date fields with today's date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const dateFields = document.querySelectorAll('input[type="date"]');
    dateFields.forEach(field => {
        if (!field.value) {
            field.value = today;
        }
    });
});
</script>