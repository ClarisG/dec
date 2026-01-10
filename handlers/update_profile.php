<?php
// handlers/update_profile.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
        
        if (in_array($file_type, $allowed_types) && move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
            $profile_picture = $filename;
            
            // Delete old profile picture if exists
            $old_query = "SELECT profile_picture FROM users WHERE id = ?";
            $old_stmt = $conn->prepare($old_query);
            $old_stmt->execute([$user_id]);
            $old_picture = $old_stmt->fetchColumn();
            
            if ($old_picture && file_exists($upload_dir . $old_picture)) {
                unlink($upload_dir . $old_picture);
            }
        }
    }
    
    // Update user information
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $office_address = $_POST['office_address'] ?? '';
    
    $query = "UPDATE users SET 
              first_name = ?, 
              last_name = ?, 
              email = ?, 
              contact_number = ?,
              barangay = ?";
    
    $params = [$first_name, $last_name, $email, $phone, $office_address];
    
    if ($profile_picture) {
        $query .= ", profile_picture = ?";
        $params[] = $profile_picture;
    }
    
    $query .= " WHERE id = ?";
    $params[] = $user_id;
    
    $stmt = $conn->prepare($query);
    $success = $stmt->execute($params);
    
    if ($success) {
        // Update session variables
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;
        $_SESSION['barangay'] = $office_address;
        
        if ($profile_picture) {
            $_SESSION['profile_picture'] = $profile_picture;
        }
        
        // Log activity
        $activity_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                          VALUES (?, 'profile_update', 'Updated profile information', ?)";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? '']);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}