<?php
require_once '../config/database.php';

$case_id = $_GET['case_id'] ?? 0;

// Fetch case details
$case_query = "SELECT r.*, u.first_name, u.last_name FROM reports r 
               LEFT JOIN users u ON r.user_id = u.id 
               WHERE r.id = :case_id";
$case_stmt = $conn->prepare($case_query);
$case_stmt->bindParam(':case_id', $case_id);
$case_stmt->execute();
$case = $case_stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    echo '<div class="text-center py-8"><p class="text-red-600">Case not found</p></div>';
    exit;
}
?>

<div class="mb-6">
    <h4 class="font-bold text-gray-700 mb-2">Case Information</h4>
    <div class="bg-gray-50 p-4 rounded-lg">
        <p><strong>Case #:</strong> <?php echo htmlspecialchars($case['id']); ?></p>
        <p><strong>Complainant:</strong> <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></p>
        <p><strong>Title:</strong> <?php echo htmlspecialchars($case['title']); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($case['category']); ?></p>
    </div>
</div>

<div class="mb-6">
    <h4 class="font-bold text-gray-700 mb-2">Select Assignment Type</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="assignment-option" data-type="lupon_member">
            <div class="text-center p-4">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
                <h5 class="font-bold text-gray-800">Lupon Member</h5>
                <p class="text-sm text-gray-600 mt-1">Assign to Lupon member for mediation</p>
            </div>
        </div>
        
        <div class="assignment-option" data-type="lupon_chairman">
            <div class="text-center p-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-user-tie text-yellow-600 text-xl"></i>
                </div>
                <h5 class="font-bold text-gray-800">Lupon Chairman</h5>
                <p class="text-sm text-gray-600 mt-1">Assign to Lupon chairman for arbitration</p>
            </div>
        </div>
        
        <div class="assignment-option" data-type="tanod">
            <div class="text-center p-4">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                </div>
                <h5 class="font-bold text-gray-800">Tanod</h5>
                <p class="text-sm text-gray-600 mt-1">Assign to Tanod for field verification</p>
            </div>
        </div>
    </div>
</div>

<div class="mb-6">
    <h4 class="font-bold text-gray-700 mb-2">Available Officers</h4>
    <div id="officerList" class="bg-gray-50 p-4 rounded-lg">
        <p class="text-gray-500 text-center py-4">Select an assignment type above to see available officers</p>
    </div>
</div>

<div id="selectionInfo"></div>