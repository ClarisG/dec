<?php
// setup_password_resets.php - Run this once to create the password_resets table

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<h2>Creating Password Reset Table</h2>";

try {
    $conn = getDbConnection();
    
    // Create password_resets table
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    echo "<p style='color: green;'>✓ Password resets table created successfully!</p>";
    
    // Clean up old tokens (optional)
    $cleanupSql = "DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 2 DAY)";
    $cleanupResult = $conn->exec($cleanupSql);
    
    echo "<p>✓ Cleaned up expired tokens.</p>";
    echo "<p><a href='forgot_password.php'>Go to Forgot Password Page</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Password Reset Setup Complete</h1>
    <p>You can now use the forgot password feature.</p>
    <p><a href="forgot_password.php">Test Forgot Password</a></p>
</body>
</html>