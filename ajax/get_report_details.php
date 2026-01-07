<?php
// ajax/get_report_details.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid report ID');
}

try {
    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get report details
    $query = "SELECT r.*, rt.type_name, u.first_name, u.last_name, u.email, u.contact_number
              FROM reports r
              LEFT JOIN report_types rt ON r.report_type_id = rt.id
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.id = :id AND r.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $report_id, ':user_id' => $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header('HTTP/1.1 404 Not Found');
        exit('Report not found');
    }
    
    // Get evidence files
    $evidence_files = [];
    if (!empty($report['evidence_files'])) {
        $evidence_files = json_decode($report['evidence_files'], true);
    }
    
    // Get status history
    $history_query = "SELECT * FROM report_status_history 
                     WHERE report_id = :report_id 
                     ORDER BY created_at DESC";
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->execute([':report_id' => $report_id]);
    $status_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates
    $created_date = date('F d, Y h:i A', strtotime($report['created_at']));
    $incident_date = date('F d, Y h:i A', strtotime($report['incident_date']));
    
    ?>
    <div class="p-4">
        <!-- Report Header -->
        <div class="mb-6 pb-4 border-b">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($report['title']); ?></h3>
                    <div class="flex items-center space-x-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo getStatusColor($report['status']); ?>">
                            <i class="fas fa-circle mr-2" style="font-size: 8px;"></i>
                            <?php echo ucfirst($report['status']); ?>
                        </span>
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-hashtag mr-1"></i>
                            <?php echo htmlspecialchars($report['report_number']); ?>
                        </span>
                        <span class="text-sm text-gray-600">
                            <i class="far fa-calendar-alt mr-1"></i>
                            <?php echo $created_date; ?>
                        </span>
                    </div>
                </div>
                <button onclick="printReport(<?php echo $report['id']; ?>)" 
                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Report Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Left Column -->
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($report['type_name']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars(ucfirst($report['category'])); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars(ucfirst($report['priority'])); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Incident Date & Time</label>
                    <p class="text-gray-900"><?php echo $incident_date; ?></p>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($report['location']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Submitted By</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></p>
                </div>
                
                <?php if ($report['is_anonymous']): ?>
                <div>
                    <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                        <i class="fas fa-user-secret mr-1"></i> Submitted Anonymously
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Description -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($report['description']); ?></p>
            </div>
        </div>
        
        <!-- Involved Persons & Witnesses -->
        <?php if (!empty($report['involved_persons']) || !empty($report['witnesses'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <?php if (!empty($report['involved_persons'])): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Involved Persons</label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($report['involved_persons']); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($report['witnesses'])): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Witnesses</label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($report['witnesses']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Attached Files - UPDATED TO SHOW ACTUAL ATTACHMENTS (FIXED PATH ERROR) -->
        <?php if (!empty($evidence_files)): ?>
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <label class="block text-sm font-medium text-gray-700">Attached Evidence Files</label>
                <span class="text-sm text-gray-500">
                    <?php echo count($evidence_files); ?> file(s)
                </span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($evidence_files as $index => $file): 
                    $extension = strtolower(pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION));
                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                    $is_pdf = $extension === 'pdf';
                    $is_video = in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']);
                    $is_audio = in_array($extension, ['mp3', 'wav', 'ogg', 'm4a']);
                    $is_document = in_array($extension, ['doc', 'docx', 'txt', 'rtf']);
                    $is_spreadsheet = in_array($extension, ['xls', 'xlsx', 'csv']);
                    
                    // FIXED: Check if 'path' key exists before using it
                    $file_path = isset($file['path']) ? '../' . ltrim($file['path'], './') : '';
                    $file_name = $file['original_name'] ?? 'Unnamed File';
                    $file_size = isset($file['file_size']) ? formatBytes($file['file_size']) : 'Unknown size';
                    
                    // Determine file type display
                    $file_type = 'File';
                    $icon = 'fa-file';
                    $icon_color = 'text-gray-500';
                    
                    if ($is_image) { 
                        $file_type = 'Image'; 
                        $icon = 'fa-image';
                        $icon_color = 'text-blue-500';
                    } elseif ($is_pdf) { 
                        $file_type = 'PDF'; 
                        $icon = 'fa-file-pdf';
                        $icon_color = 'text-red-500';
                    } elseif ($is_video) { 
                        $file_type = 'Video'; 
                        $icon = 'fa-file-video';
                        $icon_color = 'text-purple-500';
                    } elseif ($is_audio) { 
                        $file_type = 'Audio'; 
                        $icon = 'fa-file-audio';
                        $icon_color = 'text-green-500';
                    } elseif ($is_document) { 
                        $file_type = 'Document'; 
                        $icon = 'fa-file-word';
                        $icon_color = 'text-blue-600';
                    } elseif ($is_spreadsheet) { 
                        $file_type = 'Spreadsheet'; 
                        $icon = 'fa-file-excel';
                        $icon_color = 'text-green-600';
                    }
                ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-white hover:shadow-md transition-shadow duration-200">
                        <?php if ($is_image && !empty($file_path)): ?>
                            <div class="relative h-48 bg-gray-100 flex items-center justify-center overflow-hidden cursor-pointer" 
                                 onclick="viewImageInModal('<?php echo addslashes($file_path); ?>', '<?php echo addslashes($file_name); ?>')">
                                <?php if (!empty($file_path) && file_exists(str_replace('../', '', $file_path))): ?>
                                    <img src="<?php echo $file_path; ?>" 
                                         alt="<?php echo htmlspecialchars($file_name); ?>"
                                         class="max-h-full max-w-full object-contain"
                                         onerror="this.style.display='none'; document.getElementById('placeholder-<?php echo $index; ?>').style.display='flex';">
                                <?php endif; ?>
                                <div id="placeholder-<?php echo $index; ?>" 
                                     class="absolute inset-0 flex flex-col items-center justify-center <?php echo (!empty($file_path) && file_exists(str_replace('../', '', $file_path))) ? 'hidden' : ''; ?>">
                                    <i class="fas <?php echo $icon; ?> text-4xl <?php echo $icon_color; ?> mb-2"></i>
                                    <span class="text-sm text-gray-600"><?php echo $file_type; ?></span>
                                    <span class="text-xs text-gray-500 mt-1"><?php echo strtoupper($extension); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-48 bg-gray-50 flex flex-col items-center justify-center p-4">
                                <i class="fas <?php echo $icon; ?> text-5xl <?php echo $icon_color; ?> mb-3"></i>
                                <span class="text-sm font-medium text-gray-700 text-center"><?php echo $file_type; ?></span>
                                <span class="text-xs text-gray-500 mt-1"><?php echo strtoupper($extension); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-3 border-t border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate" title="<?php echo htmlspecialchars($file_name); ?>">
                                        <?php echo htmlspecialchars($file_name); ?>
                                    </p>
                                </div>
                                <?php if (isset($file['encrypted']) && $file['encrypted']): ?>
                                    <span class="ml-2 text-red-500 text-xs" title="Encrypted">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <span><?php echo $file_size; ?></span>
                                <span class="px-2 py-1 bg-gray-100 rounded"><?php echo strtoupper($extension); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status History -->
        <?php if (!empty($status_history)): ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-4">Status History</label>
            <div class="space-y-3">
                <?php foreach ($status_history as $history): ?>
                    <div class="border-l-4 <?php echo getStatusBorderColor($history['status']); ?> pl-4 py-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo getStatusColor($history['status']); ?>">
                                    <?php echo ucfirst($history['status']); ?>
                                </span>
                                <?php if (!empty($history['notes'])): ?>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($history['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500">
                                <?php echo date('M d, Y h:i A', strtotime($history['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Resolution Notes -->
        <?php if (!empty($report['resolution_notes'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center mb-2">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                <h4 class="font-medium text-green-800">Resolution Notes</h4>
            </div>
            <p class="text-green-700 text-sm whitespace-pre-line"><?php echo htmlspecialchars($report['resolution_notes']); ?></p>
            <?php if (!empty($report['resolved_at'])): ?>
            <p class="text-xs text-green-600 mt-2">
                <i class="far fa-calendar-alt mr-1"></i>
                Resolved on: <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Image Viewer Modal (UPDATED - Removed download button) -->
    <div id="imageViewerModal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden">
        <div class="flex flex-col h-full">
            <div class="flex justify-between items-center p-4 bg-gray-900 text-white">
                <h3 id="imageViewerTitle" class="text-lg font-medium"></h3>
                <button onclick="closeImageViewer()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto">
                <img id="viewerImage" src="" alt="" class="max-w-full max-h-full object-contain">
            </div>
        </div>
    </div>
    
    <script>
    function viewImageInModal(imageSrc, imageName) {
        const modal = document.getElementById('imageViewerModal');
        const image = document.getElementById('viewerImage');
        const title = document.getElementById('imageViewerTitle');
        
        image.src = imageSrc;
        title.textContent = imageName;
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageViewer() {
        document.getElementById('imageViewerModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageViewer();
        }
    });
    
    // Close modal when clicking outside image
    document.getElementById('imageViewerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeImageViewer();
        }
    });
    </script>
    <?php
    
} catch(PDOException $e) {
    echo '<div class="p-4 text-red-600">Error loading report details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helper functions
function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'investigating' => 'bg-purple-100 text-purple-800',
        'resolved' => 'bg-green-100 text-green-800',
        'referred' => 'bg-orange-100 text-orange-800',
        'closed' => 'bg-gray-100 text-gray-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusBorderColor($status) {
    $colors = [
        'pending' => 'border-yellow-500',
        'assigned' => 'border-blue-500',
        'investigating' => 'border-purple-500',
        'resolved' => 'border-green-500',
        'referred' => 'border-orange-500',
        'closed' => 'border-gray-500'
    ];
    return $colors[$status] ?? 'border-gray-500';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>