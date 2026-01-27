<?php
// verify_email.php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get token from URL - handle both URL encoded and raw formats
$raw_token = isset($_GET['token']) ? $_GET['token'] : '';
$token = rawurldecode(trim($raw_token));

// Clean the token - remove any non-hex characters (should only be [a-f0-9])
// This is safer than using deprecated filter functions
$token = preg_replace('/[^a-f0-9]/i', '', $token);

$message = '';
$is_success = false;

// Debug logging
error_log("=== Verification Request ===");
error_log("Raw token from URL: " . $raw_token);
error_log("Decoded and cleaned token: " . $token);
error_log("Token length: " . strlen($token));
error_log("Token format check: " . (preg_match('/^[a-f0-9]{64}$/i', $token) ? 'Valid' : 'Invalid'));

if (empty($token) || strlen($token) !== 64 || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    error_log("Invalid token format: " . $token);
    $message = "Invalid verification link. Please check your email for the correct link.";
} else {
    try {
        $conn = getDbConnection();
        
        // Check if token exists and is not expired
        $query = "SELECT id, first_name, email, verification_expiry, email_verified 
                  FROM users 
                  WHERE verification_token = :token 
                  AND verification_expiry > NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug log
            error_log("Token found for user ID: " . $user['id'] . ", Email: " . $user['email']);
            
            // Check if already verified
            if ($user['email_verified'] == 1) {
                $message = "Email is already verified. You can login now.";
                $is_success = true;
                header("refresh:1.5;url=login.php");
            } else {
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
                $update_stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    error_log("Email verified successfully for user ID: " . $user['id']);
                    $message = "Email successfully verified! Your account is now active.";
                    $is_success = true;
                    
                    // Redirect to login after 1.5 seconds
                    header("refresh:1.5;url=login.php");
                } else {
                    error_log("Failed to update user verification status for ID: " . $user['id']);
                    $message = "Verification failed. Please try again or contact support.";
                }
            }
        } else {
            // Check if token exists but expired or already used
            $check_query = "SELECT id, email_verified, verification_expiry 
                           FROM users 
                           WHERE verification_token = :token";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 1) {
                $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user['email_verified'] == 1) {
                    $message = "Email is already verified. You can login now.";
                    $is_success = true;
                    header("refresh:1.5;url=login.php");
                } else {
                    // Check if expired
                    $expiry = new DateTime($user['verification_expiry']);
                    $now = new DateTime();
                    
                    if ($expiry < $now) {
                        $message = "Verification link has expired. Please register again.";
                    } else {
                        $message = "Verification link is invalid. Please check your email.";
                    }
                }
            } else {
                error_log("Token not found in database: " . substr($token, 0, 20) . "...");
                
                // Try to find similar tokens for debugging
                $similar_query = "SELECT id, email, verification_token, LENGTH(verification_token) as token_len 
                                 FROM users 
                                 WHERE verification_token IS NOT NULL 
                                 ORDER BY created_at DESC LIMIT 5";
                $similar_stmt = $conn->prepare($similar_query);
                $similar_stmt->execute();
                $similar_tokens = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Recent tokens in database:");
                foreach ($similar_tokens as $row) {
                    error_log("  ID: " . $row['id'] . ", Email: " . $row['email'] . 
                             ", Token: " . substr($row['verification_token'], 0, 20) . "...");
                }
                
                $message = "Invalid verification link. Please check your email for the correct link.";
            }
        }
    } catch(PDOException $e) {
        error_log("Database error in verify_email.php: " . $e->getMessage());
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