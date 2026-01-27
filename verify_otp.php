<?php
// verify_otp.php - OTP Verification Page

// Include database configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Check if user has requested OTP (has otp_user_id in session)
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_email'])) {
    header("Location: forgot_password.php");
    exit();
}

// Handle OTP verification form submission
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
    // Basic validation
    if (empty($otp)) {
        $error = "Please enter the 6-digit OTP code.";
    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error = "OTP must be exactly 6 digits.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Get user data
            $query = "SELECT * FROM users WHERE id = :id AND email = :email AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $_SESSION['otp_user_id']);
            $stmt->bindParam(':email', $_SESSION['otp_email']);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if OTP exists and hasn't expired
                if (!empty($user['reset_token']) && !empty($user['reset_token_expiry'])) {
                    $current_time = date("Y-m-d H:i:s");
                    
                    if ($current_time > $user['reset_token_expiry']) {
                        $error = "OTP has expired. Please request a new one.";
                    } else {
                        // Verify OTP
                        if (password_verify($otp, $user['reset_token'])) {
                            // OTP is correct
                            $_SESSION['otp_verified'] = true;
                            $_SESSION['reset_user_id'] = $user['id'];
                            
                            // Clear the OTP from database
                            $clear_query = "UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = :id";
                            $clear_stmt = $conn->prepare($clear_query);
                            $clear_stmt->bindParam(':id', $user['id']);
                            $clear_stmt->execute();
                            
                            // Redirect to reset password page
                            header("Location: reset_password.php");
                            exit();
                        } else {
                            $error = "Invalid OTP code. Please try again.";
                        }
                    }
                } else {
                    $error = "No OTP request found. Please request a new OTP.";
                }
            } else {
                $error = "User not found. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("OTP Verification Error: " . $e->getMessage());
        }
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    try {
        $conn = getDbConnection();
        
        // Get user data
        $query = "SELECT * FROM users WHERE id = :id AND email = :email AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $_SESSION['otp_user_id']);
        $stmt->bindParam(':email', $_SESSION['otp_email']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate new 6-digit OTP
            $new_otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otp_hash = password_hash($new_otp, PASSWORD_DEFAULT);
            $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
            
            // Update OTP in database
            $update_query = "UPDATE users 
                            SET reset_token = :otp_hash, 
                                reset_token_expiry = :expiry 
                            WHERE id = :id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':otp_hash', $otp_hash);
            $update_stmt->bindParam(':expiry', $expiry);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                // Send new OTP email
                $to = $user['email'];
                $subject = "New OTP Code - LEIR System";
                $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%); padding: 20px; color: white; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0; }
                        .otp-box { background: #ffffff; border: 2px dashed #1d4ed8; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #1d4ed8; letter-spacing: 10px; margin: 15px 0; }
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
                            <p>You requested a new OTP code for password reset.</p>
                            
                            <p>Use the new OTP code below:</p>
                            
                            <div class='otp-box'>
                                <p>Your New One-Time Password (OTP):</p>
                                <div class='otp-code'>" . $new_otp . "</div>
                                <p>Enter this code on the password reset page</p>
                            </div>
                            
                            <div class='warning'>
                                <p><strong>Important:</strong> This OTP will expire in 10 minutes.</p>
                                <p>If you didn't request this, please ignore this email or contact support.</p>
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
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: LEIR System <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
                $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                if (mail($to, $subject, $message, $headers)) {
                    $success = "New OTP code has been sent to your email.";
                } else {
                    $error = "Failed to send new OTP. Please try again.";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR | Verify OTP</title>
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
        
        /* OTP Input Styles */
        .otp-input-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: #1a4f8c;
            box-shadow: 0 0 0 3px rgba(26, 79, 140, 0.1);
            background: white;
        }
        
        /* Resend OTP link */
        .resend-link {
            text-align: center;
            margin: 20px 0;
            font-size: 14px;
            color: #718096;
        }
        
        .resend-link a {
            color: #1a4f8c;
            text-decoration: none;
            font-weight: 600;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .resend-form {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn-resend {
            background: #f1f5f9;
            color: #4a5568;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-resend:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Verifying OTP...</p>
    </div>
    
    <div class="login-container hidden md:flex">
        <div class="left-section">
            <a href="forgot_password.php" class="back-home">
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
                    <h2>Verify OTP Code</h2>
                    <p>Enter the 6-digit code sent to your email</p>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Check your email for the 6-digit OTP code. The code is valid for 10 minutes.
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
                
                <form id="verifyOtpForm" method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Enter 6-digit OTP Code</label>
                        <div class="otp-input-group">
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        </div>
                        <input type="hidden" id="fullOtp" name="otp" value="">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Verify OTP
                    </button>
                    
                    <div class="resend-form">
                        <form method="POST" action="" style="display: inline;">
                            <button type="submit" name="resend_otp" class="btn-resend">
                                <i class="fas fa-redo"></i> Resend OTP Code
                            </button>
                        </form>
                    </div>
                    
                    <a href="forgot_password.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Forgot Password
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
                <h2>Verify OTP Code</h2>
                <p>Enter the 6-digit code sent to your email</p>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Check your email for the 6-digit OTP code. The code is valid for 10 minutes.
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
            
            <form id="mobileVerifyOtpForm" method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Enter 6-digit OTP Code</label>
                    <div class="otp-input-group">
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    </div>
                    <input type="hidden" id="mobileFullOtp" name="otp" value="">
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check-circle"></i> Verify OTP
                </button>
                
                <div class="resend-form">
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="resend_otp" class="btn-resend">
                            <i class="fas fa-redo"></i> Resend OTP Code
                        </button>
                    </form>
                </div>
                
                <a href="forgot_password.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Forgot Password
                </a>
                
                <div class="login-link">
                    Remember your password? 
                    <a href="login.php">Sign In</a>        
                </div>
            </form>
        </div>
    </div>

    <script>
        // OTP input handling
        function handleOtpInput(otpInputs, fullOtpInputId) {
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    // Allow only numbers
                    this.value = this.value.replace(/\D/g, '');
                    
                    if (this.value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    
                    // Update hidden input with full OTP
                    let fullOtp = '';
                    otpInputs.forEach(otp => {
                        fullOtp += otp.value;
                    });
                    document.getElementById(fullOtpInputId).value = fullOtp;
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });
                
                // Auto-focus on paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text');
                    if (pastedData.length === 6 && /^\d+$/.test(pastedData)) {
                        for (let i = 0; i < 6; i++) {
                            if (otpInputs[i]) {
                                otpInputs[i].value = pastedData[i];
                            }
                        }
                        document.getElementById(fullOtpInputId).value = pastedData;
                        otpInputs[5].focus();
                    }
                });
            });
        }
        
        // Form submission handling
        function handleFormSubmit(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                    
                    // Show loading overlay
                    document.getElementById('loadingOverlay').style.display = 'flex';
                }
            });
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize OTP input handling for desktop
            const desktopOtpInputs = document.querySelectorAll('#verifyOtpForm .otp-input');
            handleOtpInput(desktopOtpInputs, 'fullOtp');
            
            // Initialize OTP input handling for mobile
            const mobileOtpInputs = document.querySelectorAll('#mobileVerifyOtpForm .otp-input');
            handleOtpInput(mobileOtpInputs, 'mobileFullOtp');
            
            // Form submission handling
            handleFormSubmit('verifyOtpForm');
            handleFormSubmit('mobileVerifyOtpForm');
            
            // Auto-focus first OTP input
            if (desktopOtpInputs.length > 0) {
                desktopOtpInputs[0].focus();
            } else if (mobileOtpInputs.length > 0) {
                mobileOtpInputs[0].focus();
            }
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>