<?php
// ajax/decrypt_file.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$report_id = intval($_POST['report_id'] ?? 0);
$pin_code = trim($_POST['pin_code'] ?? '');
$file_index = intval($_POST['file_index'] ?? 0);

if (!$report_id || !$pin_code) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameters');
}

try {
    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get report and verify ownership
    $query = "SELECT r.*, rt.type_name 
              FROM reports r
              LEFT JOIN report_types rt ON r.report_type_id = rt.id
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
    
    if (!isset($evidence_files[$file_index])) {
        header('HTTP/1.1 404 Not Found');
        exit('File not found');
    }
    
    $file = $evidence_files[$file_index];
    
    // Check if file is encrypted
    if (!isset($file['encrypted']) || !$file['encrypted']) {
        header('HTTP/1.1 400 Bad Request');
        exit('File is not encrypted');
    }
    
    // Verify PIN
    if ($report['pin_code'] !== $pin_code) {
        header('HTTP/1.1 401 Unauthorized');
        exit('Incorrect PIN');
    }
    
    // Decrypt file
    $encrypted_file_path = '../' . $file['path'];
    if (!file_exists($encrypted_file_path)) {
        header('HTTP/1.1 404 Not Found');
        exit('Encrypted file not found on server');
    }
    
    // Generate encryption key from PIN
    $encryption_key = hash_pbkdf2("sha256", $pin_code, "LEIR_SALT", 10000, 32);
    $encryption_key_hash = hash('sha256', $encryption_key);
    
    // Verify encryption key hash
    if ($file['encryption_key_hash'] !== $encryption_key_hash) {
        header('HTTP/1.1 401 Unauthorized');
        exit('Encryption key mismatch');
    }
    
    // Read encrypted file
    $encrypted_content = file_get_contents($encrypted_file_path);
    
    // Extract IV (last 16 bytes)
    $iv = hex2bin($file['iv']);
    $encrypted_data = substr($encrypted_content, 0, -16); // Remove IV from end
    
    // Decrypt
    $decrypted_content = openssl_decrypt(
        $encrypted_data,
        'AES-256-CBC',
        $encryption_key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    if ($decrypted_content === false) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Decryption failed');
    }
    
    // Verify hash
    $decrypted_hash = hash('sha256', $decrypted_content);
    if ($decrypted_hash !== $file['original_hash']) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('File integrity check failed');
    }
    
    // Output decrypted file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . strlen($decrypted_content));
    echo $decrypted_content;
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}