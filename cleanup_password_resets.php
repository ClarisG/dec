<?php
require_once 'config/database.php';

// Delete expired password reset tokens (older than 24 hours)
$stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();

echo "Cleanup completed: " . $stmt->rowCount() . " expired tokens removed.";