<?php
// forgot_password.php - Matching design with login.php

// Include database configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    $role = $_SESSION['role'];
    switch($role) {
        case 'citizen': header("Location: citizen_dashboard.php"); exit;
        case 'tanod': header("Location: tanod/tanod_dashboard.php"); exit;
        case 'secretary': header("Location: sec/secretary_dashboard.php"); exit;
        case 'captain': header("Location: captain/captain_dashboard.php"); exit;
        case 'admin': header("Location: admin/admin_dashboard.php"); exit;
        case 'lupon': header("Location: lupon/lupon_dashboard.php"); exit;
        case 'super_admin': header("Location: super_admin/super_admin_dashboard.php"); exit;
        default: header("Location: index.php"); exit;
    }
}

// Handle forgot password form submission
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Basic validation
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if email exists in users table
            $query = "SELECT * FROM users WHERE email = :email AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $token_hash = password_hash($token, PASSWORD_DEFAULT);
                $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
                
                // Store token in database
                $update_query = "UPDATE users 
                                SET reset_token = :token_hash, 
                                    reset_token_expiry = :expiry 
                                WHERE id = :id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':token_hash', $token_hash);
                $update_stmt->bindParam(':expiry', $expiry);
                $update_stmt->bindParam(':id', $user['id']);
                
                if ($update_stmt->execute()) {
                    // Create reset link
                    $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                  "://$_SERVER[HTTP_HOST]" . 
                                  dirname($_SERVER['PHP_SELF']) . 
                                  "/reset_password.php?token=" . urlencode($token) . "&id=" . $user['id'];
                    
                    // Prepare email content
                    $to = $user['email'];
                    $subject = "Password Reset Request - LEIR System";
                    $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%); padding: 20px; color: white; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0; }
                            .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(to right, #1e40af, #1d4ed8); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
                            .warning { background: #fff5f5; border-left: 4px solid #e53e3e; padding: 15px; margin: 20px 0; }
                            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #718096; font-size: 14px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>LEIR - Law Enforcement Incident Reporting</h2>
                            </div>
                            <div class='content'>
                                <h3>Hello " . htmlspecialchars($user['first_name']) . ",</h3>
                                <p>We received a request to reset your password for your LEIR account.</p>
                                
                                <p>To reset your password, click the button below:</p>
                                
                                <a href='$reset_link' class='btn'>Reset Password</a>
                                
                                <p>Or copy and paste this link into your browser:</p>
                                <p><code>$reset_link</code></p>
                                
                                <div class='warning'>
                                    <p><strong>Important:</strong> This password reset link will expire in 1 hour.</p>
                                    <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
                                </div>
                                
                                <div class='footer'>
                                    <p>This is an automated message, please do not reply to this email.</p>
                                    <p>&copy; " . date('Y') . " LEIR System. All rights reserved.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Additional plain text version
                    $plain_message = "Hello " . $user['first_name'] . ",\n\n";
                    $plain_message .= "We received a request to reset your password for your LEIR account.\n\n";
                    $plain_message .= "To reset your password, visit this link: $reset_link\n\n";
                    $plain_message .= "This link will expire in 1 hour.\n\n";
                    $plain_message .= "If you didn't request this, please ignore this email.\n\n";
                    $plain_message .= "Best regards,\nLEIR System";
                    
                    // Email headers
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: LEIR System <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
                    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();
                    
                    // Send email
                    if (mail($to, $subject, $message, $headers)) {
                        $success = "Password reset link has been sent to your email address. Please check your inbox (and spam folder).";
                    } else {
                        $error = "Failed to send email. Please try again later.";
                    }
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = "If your email exists in our system, you will receive a password reset link shortly.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Forgot Password Error: " . $e->getMessage());
        }
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
            background: whitesmoke;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Desktop Layout */
        @media (min-width: 768px) {
            .login-container {
                display: flex;
                width: 100%;
                max-width: 1000px;
                min-height: 600px;
                background: white;
                border-radius: 30px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                position: relative;
            }
            
            .left-section {
                flex: 1;
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 40px;
                position: relative;
                overflow: hidden;
            }
            
            /* Wave Effect */
            .left-section::after {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 120px;
                background: #ffffff;
                z-index: 5;
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C70,120 40,260 70,400 C100,550 40,700 70,850 C100,950 70,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C70,120 40,260 70,400 C100,550 40,700 70,850 C100,950 70,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                mask-size: 100% 100%;
                -webkit-mask-size: 100% 100%;
            }
            
            .left-section::before {
                content: '';
                position: absolute;
                top: 0;
                right: 40px;
                bottom: 0;
                width: 160px;
                background: rgba(255, 255, 255, 0.25);
                z-index: 2;
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C60,150 20,300 60,450 C100,600 20,750 60,900 C100,980 60,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C60,150 20,300 60,450 C100,600 20,750 60,900 C100,980 60,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                mask-size: 100% 100%;
                -webkit-mask-size: 100% 100%;
            }
            
            .left-section .wave-back {
                content: '';
                position: absolute;
                top: 0;
                right: 80px;
                bottom: 0;
                width: 200px;
                background: rgba(0, 0, 0, 0.12);
                z-index: 1;
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C40,180 10,350 40,550 C80,700 10,850 40,950 C80,1000 40,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 1000' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0,0 C40,180 10,350 40,550 C80,700 10,850 40,950 C80,1000 40,1000 0,1000 L150,1000 L150,0 Z'/%3E%3C/svg%3E");
                mask-size: 100% 100%;
                -webkit-mask-size: 100% 100%;
            }
            
            .logo-container {
                position: relative;
                z-index: 10;
                text-align: center;
                color: white;
                max-width: 350px;
            }
            
            .logo-circle img {
                width: 300px;
                height: 300px;
                object-fit: contain;
                filter: drop-shadow(0 0 20px white)
                        drop-shadow(0 0 40px white);
            }
            
            .logo-container h1 {
                font-size: 32px;
                font-weight: 700;
                margin-bottom: 10px;
                letter-spacing: -0.5px;
            }
            
            .logo-container p {
                font-size: 18px;
                opacity: 0.9;
                margin-bottom: 40px;
            }
            
            .spacer {
                height: 40px;
                width: 100%;
            }
            
            .right-section {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 40px;
                background: white;
            }
            
            .right-content {
                max-width: 380px;
                width: 100%;
            }
            
            .form-header {
                text-align: center;
                margin-bottom: 40px;
            }
            
            .form-header h2 {
                font-size: 32px;
                font-weight: 700;
                color: #2d3748;
                margin-bottom: 10px;
            }
            
            .form-header p {
                color: #718096;
                font-size: 16px;
            }
        }
        
        /* Mobile Layout */
        @media (max-width: 767px) {
            body {
                background: white;
                padding: 0;
            }
            
            .mobile-container {
                width: 100%;
                min-height: 100vh;
                background: white;
                display: flex;
                flex-direction: column;
            }
            
            .mobile-header {
                flex-shrink: 0;
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
                padding: 50px 30px 100px;
                text-align: center;
                color: white;
                position: relative;
                overflow: hidden;
                z-index: 1;
            }
            
            /* Wave container */
            .wave-separator-index {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 80px;
                z-index: 2;
                pointer-events: none;
            }
            
            .wave-separator-index svg {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 80px;
                display: block;
            }
            
            /* Logo */
            .mobile-logo-circle {
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                position: relative;
                z-index: 3;
                background: transparent;
                border: none;
                border-radius: 0;
                backdrop-filter: none;
                width: 100%;
                max-width: 300px;
                height: auto;
                aspect-ratio: 1;
            }
            
            .mobile-logo-circle img {
                width: 150px;
                height: 150px;
                max-width: 100%;
                object-fit: contain;
                filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.8))
                       drop-shadow(0 0 40px rgba(255, 255, 255, 0.6));
            }
            
            .mobile-logo-circle img:hover {
                filter: drop-shadow(0 0 35px rgba(255,255,255,1))
                       drop-shadow(0 0 70px rgba(255,255,255,0.8))
                       drop-shadow(0 0 120px rgba(255,255,255,0.6));
            }
            
            .mobile-header h1 {
                font-size: 26px;
                font-weight: 700;
                margin-bottom: 8px;
                position: relative;
                z-index: 4;
            }
            
            .mobile-header p {
                font-size: 17px;
                opacity: 0.9;
                position: relative;
                z-index: 4;
                margin-bottom: 20px;
            }
            
            /* Adjust the form container */
            .mobile-form-container {
                flex: 1;
                padding: 40px 30px;
                display: flex;
                flex-direction: column;
                background: white;
                position: relative;
                z-index: 1;
                margin-top: 0;
                padding-top: 20px;
                border-radius: 30px 30px 0 0;
            }
            
            .mobile-form-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .mobile-form-header h2 {
                font-size: 28px;
                font-weight: 700;
                color: #2d3748;
                margin-bottom: 10px;
            }
            
            .mobile-form-header p {
                color: #718096;
                font-size: 15px;
            }
        }
        
        /* Common Form Styles */
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
            border-color: #1a4f8c;
            box-shadow: 0 0 0 3px rgba(26, 79, 140, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
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
            background: linear-gradient(to right, #1d4ed8, #1e3a8a);
        }
        
        .btn-secondary {
            width: 100%;
            padding: 16px;
            background: #f1f5f9;
            color: #4a5568;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            text-decoration: none;
            text-align: center;
            display: block;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            font-size: 15px;
            color: #718096;
        }
        
        .login-link a {
            color: #1a4f8c;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
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
        
        .back-home {
            position: absolute;
            top: 30px;
            left: 30px;
            color: white;
            text-decoration: none;
            font-size: 24px;
            display: flex;
            align-items: center;
            z-index: 10;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            width: auto;
            min-width: 60px;
            height: 50px;
        }
        
        .back-home:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .back-home i {
            margin-right: 10px;
            font-size: 22px;
            width: 24px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .mobile-header {
                padding: 30px 20px 80px;
            }
            
            .mobile-logo-circle {
                width: 90px;
                height: 90px;
            }
            
            .mobile-logo-circle img {
                width: 70px;
                height: 70px;
                filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.5))
                       drop-shadow(0 0 15px rgba(255, 255, 255, 0.3));
            }
            
            .mobile-header h1 {
                font-size: 22px;
            }
            
            .mobile-header p {
                font-size: 15px;
            }
            
            .mobile-form-container {
                padding: 30px 20px;
            }
            
            .mobile-form-header h2 {
                font-size: 24px;
            }
            
            .mobile-form-header p {
                font-size: 14px;
            }
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9998;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #1a4f8c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Info box */
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .info-box i {
            color: #1a4f8c;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Sending reset link...</p>
    </div>
    
    <div class="login-container hidden md:flex">
        <div class="left-section">
            <a href="login.php" class="back-home">
                <i class="fas fa-long-arrow-alt-left"></i>
            </a>
            
            <div class="logo-container">
                <div class="logo-circle">
                    <img src="images/10213.png" alt="LEIR Logo">
                </div>
            </div>
        </div>
        
        <div class="right-section">
            <div class="right-content">
                <div class="form-header">
                    <h2>Reset Password</h2>
                    <p>Enter your email to receive a reset link</p>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Enter your email address and we'll send you a link to reset your password.
                    The link will expire in 1 hour.
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
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   autocomplete="email">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                    
                    <a href="login.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                    
                    <div class="login-link">
                        Remember your password? 
                        <a href="login.php">Sign In</a> 
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="mobile-container md:hidden">
        <div class="mobile-header">
            <div class="mobile-logo-circle">
                <img src="images/10213.png" alt="LEIR Logo">
            </div>
            
            <!-- Add the wave separator -->
            <div class="wave-separator-index">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
                    <path fill="white" fill-opacity="1" d="M0,80L48,75C96,70,192,60,288,55C384,50,480,50,576,55C672,60,768,70,864,75C960,80,1056,80,1152,75C1248,70,1344,60,1392,55L1440,50L1440,100L1392,100C1344,100,1248,100,1152,100C1056,100,960,100,864,100C768,100,672,100,576,100C480,100,384,100,288,100C192,100,96,100,48,100L0,100Z"></path>
                </svg>
            </div>
        </div>
        <div class="mobile-form-container">
            <div class="mobile-form-header">
                <h2>Reset Password</h2>
                <p>Enter your email to receive a reset link</p>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Enter your email address and we'll send you a link to reset your password.
                The link will expire in 1 hour.
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
                </div>
            <?php endif; ?>
            
            <form id="mobileForgotForm" method="POST" action="">
                <div class="form-group">
                    <label for="mobile_email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="mobile_email" name="email" class="form-input" 
                               placeholder="Enter your registered email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
                
                <a href="login.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
                
                <div class="login-link">
                    Remember your password? 
                    <a href="login.php">Sign In</a>        
                </div>
            </form>
        </div>
    </div>

    <script>
        // Form submission handling
        function handleFormSubmit(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                const emailInput = this.querySelector('input[type="email"]');
                const submitBtn = this.querySelector('button[type="submit"]');
                
                if (emailInput && !emailInput.value.trim()) {
                    e.preventDefault();
                    return;
                }
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    
                    // Show loading overlay
                    document.getElementById('loadingOverlay').style.display = 'flex';
                }
            });
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission handling
            handleFormSubmit('forgotForm');
            handleFormSubmit('mobileForgotForm');
            
            // Auto-focus email field on page load
            const emailField = document.getElementById('email') || document.getElementById('mobile_email');
            if (emailField) {
                emailField.focus();
            }
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Handle enter key in email field
            emailField?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
    </script>
</body>
</html>
