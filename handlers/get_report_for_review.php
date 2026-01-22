<?php
// handlers/get_report_for_review.php
require_once '../config/database.php';
require_once '../config/session.php';

$report_id = $_GET['id'] ?? 0;

try {
    // Fetch report details with AI data
    $query = "SELECT r.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as complainant_name,
                     u.contact_number,
                     u.email,
                     r.ai_classification,
                     r.ai_confidence,
                     r.ai_keywords
              FROM reports r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $report_id);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        die('<div class="text-center py-8 text-red-600">Report not found</div>');
    }
    
    // Get AI keywords
    $keywords = json_decode($report['ai_keywords'] ?? '[]', true);
    
    ?>
    
    <div class="space-y-6">
        <!-- Report Summary -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-bold text-gray-800 mb-2">Report #<?php echo $report['id']; ?></h4>
            <p class="text-gray-700"><?php echo htmlspecialchars($report['title']); ?></p>
            <div class="mt-2 flex space-x-4 text-sm text-gray-600">
                <span><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($report['complainant_name']); ?></span>
                <span><i class="fas fa-calendar mr-1"></i> <?php echo date('M d, Y', strtotime($report['created_at'])); ?></span>
            </div>
        </div>
        
        <!-- AI Analysis -->
        <div class="bg-blue-50 p-4 rounded-lg">
            <h5 class="font-bold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-robot mr-2 text-blue-600"></i>
                AI Analysis
            </h5>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Suggested Classification:</p>
                    <p class="font-bold <?php echo $report['ai_classification'] === 'Barangay Matter' ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo htmlspecialchars($report['ai_classification']); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Confidence Level:</p>
                    <div class="flex items-center">
                        <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-blue-600 h-2 rounded-full" 
                                 style="width: <?php echo $report['ai_confidence']; ?>%"></div>
                        </div>
                        <span class="font-medium"><?php echo $report['ai_confidence']; ?>%</span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($keywords)): ?>
            <div class="mt-3">
                <p class="text-sm text-gray-600 mb-1">Keywords Detected:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($keywords as $keyword): ?>
                    <span class="px-2 py-1 bg-white text-gray-700 rounded text-xs"><?php echo htmlspecialchars($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Report Description -->
        <div class="bg-white p-4 rounded-lg border">
            <h5 class="font-bold text-gray-800 mb-2">Report Description</h5>
            <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
        </div>
        
        <!-- Manual Classification -->
        <div class="bg-white p-4 rounded-lg border">
            <h5 class="font-bold text-gray-800 mb-3">Select Correct Classification</h5>
            
            <div class="space-y-4">
                <div class="classification-option">
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-blue-50">
                        <input type="radio" name="classification" value="Barangay Matter" 
                               class="h-5 w-5 text-blue-600" 
                               <?php echo $report['ai_classification'] === 'Barangay Matter' ? 'checked' : ''; ?>>
                        <div class="ml-3">
                            <span class="font-medium text-gray-800">Barangay Matter</span>
                            <p class="text-sm text-gray-600">For mediation and barangay resolution</p>
                            <div class="mt-1 text-xs text-green-600">
                                <i class="fas fa-check-circle mr-1"></i>
                                Appropriate for: Small claims, neighborhood disputes, minor conflicts
                            </div>
                        </div>
                    </label>
                </div>
                
                <div class="classification-option">
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-red-50">
                        <input type="radio" name="classification" value="Police Matter" 
                               class="h-5 w-5 text-red-600"
                               <?php echo $report['ai_classification'] === 'Police Matter' ? 'checked' : ''; ?>>
                        <div class="ml-3">
                            <span class="font-medium text-gray-800">Police Matter</span>
                            <p class="text-sm text-gray-600">Requires police intervention or external referral</p>
                            <div class="mt-1 text-xs text-red-600">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Appropriate for: Criminal offenses, VAWC cases, serious incidents
                            </div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Classification Notes (Optional)</label>
                <textarea id="classificationNotes" rows="3" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Add notes about why you changed the classification..."></textarea>
            </div>
        </div>
    </div>
    
    <?php
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-red-600">Error: ' . $e->getMessage() . '</div>';
}
?>