<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if email exists
            $query = "SELECT id, first_name FROM users WHERE email = :email AND status = 'active'";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $updateQuery = "UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindParam(':token', $token);
                $updateStmt->bindParam(':expiry', $expiry);
                $updateStmt->bindParam(':id', $user['id']);
                
                if ($updateStmt->execute()) {
                    // In production, send email with reset link
                    // $resetLink = "https://yourdomain.com/reset_password.php?token=$token";
                    // mail($email, "Password Reset", "Click here to reset: $resetLink");
                    
                    $success = "Password reset instructions have been sent to your email.";
                } else {
                    $error = "Failed to generate reset token. Please try again.";
                }
            } else {
                $error = "No account found with this email address.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | BLUEBACK System</title>
    <link rel="stylesheet" href="styles/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-right-panel">
            <div class="auth-form-container">
                <div class="form-header">
                    <h2>Forgot Password</h2>
                    <p>Enter your email to receive reset instructions</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <p class="mt-2"><a href="auth.php?mode=login" class="font-medium">Back to Login</a></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="Enter your registered email" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i> Send Reset Instructions
                    </button>
                    
                    <div class="form-footer">
                        Remember your password? 
                        <a href="login.php">Sign in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>