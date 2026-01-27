<?php
// verify_email.php
session_start();
require_once 'config/database.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$message = '';
$is_success = false;

if (empty($token)) {
    $message = "Invalid verification link. Please check your email for the correct link.";
} else {
    try {
        $conn = getDbConnection();
        
        // Check if token exists and is not expired
        $query = "SELECT id, first_name, email, verification_expiry FROM users 
                  WHERE verification_token = :token AND verification_expiry > NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update user as verified
            $update_query = "UPDATE users SET 
                email_verified = 1,
                status = 'active',
                is_active = 1,
                verification_token = NULL,
                verification_expiry = NULL,
                verified_at = NOW(),
                updated_at = NOW()
                WHERE id = :id";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                $message = "Email successfully verified! Your account is now active.";
                $is_success = true;
                
                // Redirect to login after 1.5 seconds
                header("refresh:1.5;url=login.php");
            } else {
                $message = "Verification failed. Please try again or contact support.";
            }
        } else {
            // Check if token exists but expired
            $expired_query = "SELECT id FROM users WHERE verification_token = :token";
            $expired_stmt = $conn->prepare($expired_query);
            $expired_stmt->bindParam(':token', $token);
            $expired_stmt->execute();
            
            if ($expired_stmt->rowCount() == 1) {
                $message = "Verification link has expired. Please register again or request a new verification link.";
            } else {
                $message = "Invalid verification link. Please check your email for the correct link.";
            }
        }
    } catch(PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/10213.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: whitesmoke;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .verification-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .verification-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
            padding: 40px;
            color: white;
        }
        
        .verification-header i {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
        }
        
        .verification-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .verification-content {
            padding: 40px;
        }
        
        .verification-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        
        .success-icon {
            color: #38a169;
        }
        
        .error-icon {
            color: #e53e3e;
        }
        
        .verification-message {
            color: #2d3748;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .redirect-notice {
            color: #718096;
            font-size: 14px;
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .btn-login {
            display: inline-block;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
            color: white;
            padding: 15px 40px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.3);
        }
        
        @media (max-width: 480px) {
            .verification-header {
                padding: 30px 20px;
            }
            
            .verification-content {
                padding: 30px 20px;
            }
            
            .verification-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Email Verification</h1>
            <p>Law Enforcement Incident Reporting System</p>
        </div>
        
        <div class="verification-content">
            <?php if ($is_success): ?>
                <div class="verification-icon success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="verification-message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <div class="redirect-notice">
                    <i class="fas fa-info-circle mr-2"></i>
                    You will be redirected to the login page in a few seconds...
                </div>
                <a href="login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt mr-2"></i> Go to Login Now
                </a>
            <?php else: ?>
                <div class="verification-icon error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="verification-message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <a href="register.php" class="btn-login">
                    <i class="fas fa-user-plus mr-2"></i> Register Again
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // If success, show countdown
        <?php if ($is_success): ?>
        let seconds = 1;
        const countdownElement = document.querySelector('.redirect-notice');
        
        const countdownInterval = setInterval(() => {
            seconds++;
            if (seconds <= 2) {
                countdownElement.innerHTML = `
                    <i class="fas fa-info-circle mr-2"></i>
                    Redirecting to login in ${2 - seconds} second${seconds === 1 ? '' : 's'}...
                `;
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>