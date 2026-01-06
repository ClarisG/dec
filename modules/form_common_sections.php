<?php
// form_common_sections.php - Common form sections for all report types
?>

<!-- Involved Persons -->
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Involved Persons (if known)
    </label>
    <textarea name="involved_persons" rows="3"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Names, roles, descriptions, or other identifying information"><?php echo isset($_POST['involved_persons']) ? htmlspecialchars($_POST['involved_persons']) : ''; ?></textarea>
</div>

<!-- Witnesses -->
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Witnesses (if any)
    </label>
    <textarea name="witnesses" rows="2"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Names and contact information of witnesses"><?php echo isset($_POST['witnesses']) ? htmlspecialchars($_POST['witnesses']) : ''; ?></textarea>
</div>

<!-- Evidence Upload -->
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Evidence Files <span class="text-sm text-gray-500">(Max 10 files, 10MB each)</span>
    </label>
    
    <!-- Drop zone -->
    <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4 hover:border-blue-400 transition-colors cursor-pointer">
        <input type="file" id="evidence_files" name="evidence_files[]" 
               class="hidden" multiple accept="image/*,.pdf,.mp4,.avi,.mov,.wav,.mp3"
               onchange="handleFileUpload(this.files)">
        
        <div class="flex flex-col items-center">
            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
            <p class="text-gray-600 font-medium">Drag & drop files here or click to browse</p>
            <p class="text-sm text-gray-500 mt-1">Supports images, PDFs, videos, and audio files</p>
            <button type="button" onclick="document.getElementById('evidence_files').click()"
                    class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i> Select Files
            </button>
        </div>
    </div>
    
    <!-- File list -->
    <div class="mb-2">
        <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-gray-700">
                Selected Files: <span id="fileCount" class="text-blue-600">0</span>/10
            </span>
            <button type="button" onclick="uploadedFiles = []; document.getElementById('fileList').innerHTML = ''; updateFileInput();"
                    class="text-sm text-red-600 hover:text-red-800">
                <i class="fas fa-trash-alt mr-1"></i> Clear All
            </button>
        </div>
    </div>
    
    <div id="fileList" class="space-y-2">
        <!-- Files will be listed here -->
    </div>
</div>

<!-- Anonymous Reporting -->
<div class="mb-6">
    <div class="flex items-center">
        <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1"
               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
               <?php echo isset($_POST['is_anonymous']) ? 'checked' : ''; ?>>
        <label for="is_anonymous" class="ml-2 block text-sm text-gray-700">
            Submit anonymously (your personal information will not be shared)
        </label>
    </div>
</div>

<!-- 4-Digit PIN for Encryption -->
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <span class="text-red-500">*</span> 4-Digit PIN Code for Encryption
    </label>
    <p class="text-sm text-gray-500 mb-3">This PIN will encrypt your evidence files. Keep it safe for future access.</p>
    <div class="flex space-x-4 max-w-xs mx-auto">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <input type="password" name="pin_code[]" maxlength="1" required
                   class="w-16 h-16 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pin-input"
                   pattern="\d"
                   inputmode="numeric"
                   oninput="handlePinInput(this, <?php echo $i; ?>)"
                   onkeydown="handlePinKeydown(event, <?php echo $i; ?>)"
                   onpaste="handlePinPaste(event)"
                   autocomplete="off">
        <?php endfor; ?>
    </div>
    <input type="hidden" name="pin_code" id="full_pin">
    <div class="mt-2 text-sm text-gray-500">
        <i class="fas fa-shield-alt text-blue-500 mr-1"></i>
        Your files will be encrypted with military-grade AES-128 encryption
    </div>
</div>

<!-- Terms and Conditions -->
<div class="mb-6">
    <div class="flex items-start">
        <input type="checkbox" name="terms" id="terms" required
               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
        <label for="terms" class="ml-2 block text-sm text-gray-700">
            I confirm that the information provided is accurate to the best of my knowledge and I understand that false reporting may lead to legal consequences.
        </label>
    </div>
</div>

<!-- Submit Button -->
<div class="flex justify-end">
    <button type="submit" name="submit_report"
            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-medium rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
        <i class="fas fa-paper-plane mr-2"></i> Submit Report
    </button>
</div>