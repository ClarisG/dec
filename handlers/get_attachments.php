<?php
require_once '../config/database.php';
require_once '../config/session.php';

$report_id = $_GET['report_id'] ?? 0;

try {
    // Fetch attachments for this report
    $stmt = $conn->prepare("
        SELECT * FROM report_attachments 
        WHERE report_id = ? 
        ORDER BY created_at
    ");
    $stmt->execute([$report_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($attachments) === 0) {
        echo '<div class="text-center py-8">';
        echo '<i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>';
        echo '<p class="text-gray-600">No attachments found for this report.</p>';
        echo '</div>';
        exit();
    }
    
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    
    foreach ($attachments as $attachment) {
        $filepath = '../uploads/reports/' . $attachment['filepath'];
        $filename = $attachment['filename'];
        $filetype = $attachment['filetype'];
        $filesize = formatFileSize($attachment['filesize']);
        $upload_date = date('M d, Y h:i A', strtotime($attachment['created_at']));
        
        $is_image = in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
        $is_video = in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['mp4', 'avi', 'mov', 'wmv', 'mkv']);
        $is_pdf = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf';
        
        echo '<div class="bg-gray-50 rounded-lg p-4 border border-gray-200">';
        
        // File header
        echo '<div class="flex items-center justify-between mb-3">';
        echo '<div class="flex items-center">';
        
        // File icon based on type
        if ($is_image) {
            echo '<div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">';
            echo '<i class="fas fa-image text-green-600"></i>';
            echo '</div>';
        } elseif ($is_pdf) {
            echo '<div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">';
            echo '<i class="fas fa-file-pdf text-red-600"></i>';
            echo '</div>';
        } elseif ($is_video) {
            echo '<div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">';
            echo '<i class="fas fa-video text-purple-600"></i>';
            echo '</div>';
        } else {
            echo '<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">';
            echo '<i class="fas fa-file text-blue-600"></i>';
            echo '</div>';
        }
        
        echo '<div>';
        echo '<p class="font-medium text-gray-800 truncate max-w-xs">' . htmlspecialchars($filename) . '</p>';
        echo '<p class="text-xs text-gray-500">' . $filesize . ' • ' . $upload_date . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Download button
        echo '<a href="' . $filepath . '" download class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">';
        echo '<i class="fas fa-download mr-1"></i> Download';
        echo '</a>';
        echo '</div>';
        
        // Preview
        echo '<div class="mt-3">';
        if ($is_image) {
            echo '<img src="' . $filepath . '" alt="' . htmlspecialchars($filename) . '" class="attachment-preview w-full object-cover">';
        } elseif ($is_video) {
            echo '<video controls class="video-preview w-full">';
            echo '<source src="' . $filepath . '" type="video/' . pathinfo($filename, PATHINFO_EXTENSION) . '">';
            echo 'Your browser does not support the video tag.';
            echo '</video>';
        } elseif ($is_pdf) {
            echo '<div class="bg-white p-4 rounded border text-center">';
            echo '<i class="fas fa-file-pdf text-red-500 text-4xl mb-2"></i>';
            echo '<p class="text-gray-600">PDF Document</p>';
            echo '<a href="' . $filepath . '" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">';
            echo '<i class="fas fa-external-link-alt mr-1"></i> Open in new tab';
            echo '</a>';
            echo '</div>';
        } else {
            echo '<div class="bg-white p-4 rounded border text-center">';
            echo '<i class="fas fa-file text-gray-500 text-4xl mb-2"></i>';
            echo '<p class="text-gray-600">' . strtoupper(pathinfo($filename, PATHINFO_EXTENSION)) . ' File</p>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-red-600">Error loading attachments: ' . $e->getMessage() . '</div>';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}
?>