<?php
// verify_email.php
session_start();
require_once 'config/database.php';
require_once 'config/email_functions.php';

$message = '';
$message_type = 'error'; // 'success' or 'error'

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        $conn = getDbConnection();
        
        // Check if token exists and is not expired
        $query = "SELECT id, email, first_name, last_name FROM users 
                  WHERE verification_token = :token 
                  AND verification_expiry > NOW() 
                  AND email_verified = 0";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Mark email as verified
            $update_query = "UPDATE users SET 
                            email_verified = 1,
                            verified_at = NOW(),
                            verification_token = NULL,
                            verification_expiry = NULL,
                            status = 'active'
                            WHERE id = :id";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':id', $user['id']);
            $update_stmt->execute();
            
            // Send confirmation email
            sendVerificationSuccessEmail($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            
            $message = "Your email has been successfully verified! You can now log in to your account.";
            $message_type = 'success';
        } else {
            $message = "Invalid or expired verification token. Please request a new verification email.";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
} else {
    $message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - LEIR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/10213.png">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .icon-success {
            color: #10b981;
            font-size: 80px;
            margin-bottom: 20px;
        }
        .icon-error {
            color: #ef4444;
            font-size: 80px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="verification-card">
        <?php if ($message_type == 'success'): ?>
            <div class="icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Email Verified Successfully!</h2>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn">
                <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
            </a>
        <?php else: ?>
            <div class="icon-error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Verification Failed</h2>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn">
                <i class="fas fa-arrow-left mr-2"></i>Back to Login
            </a>
        <?php endif; ?>
        
        <div class="mt-8 text-sm text-gray-500">
            <p>Need help? Contact support at support@leir-system.com</p>
        </div>
    </div>
</body>
</html>