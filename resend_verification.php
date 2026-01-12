<?php
// resend_verification.php
session_start();
require_once 'config/database.php';
require_once 'config/email_functions.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if email exists and is not verified
            $query = "SELECT id, first_name, last_name, verification_token FROM users 
                      WHERE email = :email AND email_verified = 0";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate new token if none exists or expired
                $verification_token = $user['verification_token'];
                if (empty($verification_token)) {
                    $verification_token = bin2hex(random_bytes(32));
                    
                    $update_query = "UPDATE users SET verification_token = :token, 
                                    verification_expiry = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                                    WHERE id = :id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':token', $verification_token);
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                }
                
                // Send verification email
                $verification_link = BASE_URL . "/verify_email.php?token=" . $verification_token;
                $email_sent = sendRegistrationEmail($email, $user['first_name'] . ' ' . $user['last_name'], $verification_link);
                
                if ($email_sent) {
                    $success = "Verification email has been resent. Please check your inbox.";
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            } else {
                $error = "No unverified account found with that email or email is already verified.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!-- Similar HTML form to forgot_password.php for resending verification -->