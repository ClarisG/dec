<?php
// forgot_password.php - Password reset request page

// Include required files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Initialize variables
$error = '';
$success = '';
$email = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getDbConnection();
        
        // Get email from POST
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        // Validate email
        if (empty($email)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if user exists with this email
            $query = "SELECT id, email, username, first_name, last_name FROM users WHERE email = :email AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete any existing tokens for this email
                $deleteQuery = "DELETE FROM password_resets WHERE email = :email";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->bindParam(':email', $email);
                $deleteStmt->execute();
                
                // Insert new token
                $insertQuery = "INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':token', $token);
                $insertStmt->bindParam(':expires_at', $expiresAt);
                
                if ($insertStmt->execute()) {
                    // Include email configuration
                    require_once __DIR__ . '/config/email_config.php';
                    
                    // Send reset email
                    $userName = $user['first_name'] . ' ' . $user['last_name'];
                    if (sendPasswordResetEmail($email, $userName, $token)) {
                        $success = "Password reset instructions have been sent to your email address. Please check your inbox (and spam folder).";
                    } else {
                        $error = "Failed to send reset email. Please try again later or contact administrator.";
                    }
                } else {
                    $error = "Failed to generate reset token. Please try again.";
                }
            } else {
                // For security, don't reveal if email exists or not
                $success = "If your email is registered, you will receive password reset instructions shortly.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error. Please try again later.";
        error_log("Forgot Password Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR | Forgot Password</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .forgot-container {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .forgot-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 20px 20px 0 0;
        }
        
        .logo-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .logo-circle img {
            width: 80px;
            height: 80px;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.5));
        }
        
        .forgot-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .forgot-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .forgot-body {
            padding: 40px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error {
            background-color: #fff5f5;
            border: 1px solid #fed7d7;
            color: #e53e3e;
        }
        
        .alert-success {
            background-color: #f0fff4;
            border: 1px solid #c6f6d5;
            color: #38a169;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 10;
        }
        
        .form-input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
            color: #2d3748;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .links-container {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .link-item {
            margin: 10px 0;
        }
        
        .link-item a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .link-item a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .forgot-container {
                border-radius: 15px;
            }
            
            .forgot-header {
                padding: 30px 20px;
            }
            
            .forgot-body {
                padding: 30px 20px;
            }
            
            .logo-circle {
                width: 100px;
                height: 100px;
            }
            
            .logo-circle img {
                width: 70px;
                height: 70px;
            }
            
            .forgot-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Processing...</p>
    </div>
    
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="logo-circle">
                <img src="images/10213.png" alt="LEIR Logo">
            </div>
            <h1>Forgot Password</h1>
            <p>Enter your email to reset your password</p>
        </div>
        
        <div class="forgot-body">
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
                </div>
            <?php endif; ?>
            
            <form id="forgotForm" method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your registered email" required
                               value="<?php echo htmlspecialchars($email); ?>"
                               autocomplete="email">
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        Enter the email address associated with your account. We'll send you instructions to reset your password.
                    </p>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Reset Instructions
                </button>
            </form>
            
            <div class="links-container">
                <div class="link-item">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
                <div class="link-item">
                    <a href="index.php">
                        <i class="fas fa-home"></i> Return to Homepage
                    </a>
                </div>
                <div class="link-item">
                    <a href="register.php">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                
                if (!email) {
                    e.preventDefault();
                    alert('Please enter your email address.');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
                
                // Show loading spinner
                loadingSpinner.style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                // Hide spinner after form submission (in case of error)
                setTimeout(() => {
                    loadingSpinner.style.display = 'none';
                }, 5000);
            });
            
            function isValidEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }
            
            // Auto-focus email field
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>