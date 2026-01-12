<?php
// login.php - User Login
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'citizen': header("Location: citizen_dashboard.php"); exit;
        case 'tanod': header("Location: tanod_dashboard.php"); exit;
        case 'secretary': header("Location: secretary_dashboard.php"); exit;
        case 'captain': header("Location: captain_dashboard.php"); exit;
        case 'admin': header("Location: admin_dashboard.php"); exit;
    }
}

// Include database configuration
require_once 'config/database.php';

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    try {
        $conn = getDbConnection();
        
        // Prepare SQL query to fetch user
        $query = "SELECT id, username, password, role, email, email_verified, first_name, last_name, 
                  is_active, status, barangay, pin_code, user_type 
                  FROM users WHERE username = :username OR email = :username";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                
                // Check if email is verified
                if ($user['email_verified'] == 0) {
                    $error = "Please verify your email address before logging in. Check your inbox for the verification link.";
                    
                    // Add option to resend verification email
                    $resend_link = "resend_verification.php?email=" . urlencode($user['email']);
                } 
                // Check if account is active
                elseif ($user['is_active'] == 0) {
                    $error = "Your account is currently inactive. Please contact the administrator.";
                }
                // Check if account is approved (status)
                elseif ($user['status'] != 'active') {
                    $error = "Your account is pending approval. Please wait for administrator approval.";
                } else {
                    // Login successful - set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['barangay'] = $user['barangay'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Set pin_code if exists
                    if (!empty($user['pin_code'])) {
                        $_SESSION['pin_code'] = $user['pin_code'];
                    }
                    
                    // Log login activity
                    $log_query = "INSERT INTO user_logs (user_id, activity_type, ip_address, user_agent, created_at) 
                                  VALUES (:user_id, 'login', :ip_address, :user_agent, NOW())";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->bindParam(':user_id', $user['id']);
                    $log_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                    $log_stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
                    $log_stmt->execute();
                    
                    // Redirect based on role
                    switch($user['role']) {
                        case 'citizen': 
                            header("Location: citizen_dashboard.php"); 
                            exit;
                        case 'tanod': 
                            header("Location: tanod_dashboard.php"); 
                            exit;
                        case 'secretary': 
                            header("Location: secretary_dashboard.php"); 
                            exit;
                        case 'captain': 
                            header("Location: captain_dashboard.php"); 
                            exit;
                        case 'admin': 
                            header("Location: admin_dashboard.php"); 
                            exit;
                        default: 
                            header("Location: index.php"); 
                            exit;
                    }
                }
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Login Error: " . $e->getMessage());
    }
}

// Check for success message from registration
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = "Registration successful! Please check your email to verify your account before logging in.";
}

// Check for password reset success
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success = "Password reset successful! You can now login with your new password.";
}

// Check for email verification success
if (isset($_GET['verified']) && $_GET['verified'] == '1') {
    $success = "Email verified successfully! You can now login to your account.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR</title>
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
                margin-bottom: 20px;
            }
            
            .features {
                margin-top: 40px;
                text-align: left;
                width: 100%;
                max-width: 300px;
            }
            
            .feature-item {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                color: rgba(255, 255, 255, 0.95);
                font-size: 15px;
            }
            
            .feature-item i {
                width: 30px;
                height: 30px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
                flex-shrink: 0;
            }
            
            .right-section {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 40px;
                background: white;
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
            
            /* Wave separator */
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
            
            .mobile-features {
                margin-top: 20px;
                text-align: left;
                padding: 0 20px;
                position: relative;
                z-index: 4;
            }
            
            .mobile-feature {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
                color: rgba(255, 255, 255, 0.95);
                font-size: 14px;
            }
            
            .mobile-feature i {
                width: 25px;
                height: 25px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 10px;
                flex-shrink: 0;
                font-size: 12px;
            }
            
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
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
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
        
        .required::after {
            content: ' *';
            color: #e53e3e;
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
            font-family: 'Poppins', sans-serif;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #1a4f8c;
            box-shadow: 0 0 0 3px rgba(26, 79, 140, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            color: white;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(to right, #1d4ed8, #1e3a8a);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .register-link {
            text-align: center;
            margin-top: 30px;
            font-size: 15px;
            color: #718096;
        }
        
        .register-link a {
            color: #1a4f8c;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .register-link a:hover {
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
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: #1a4f8c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
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
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        
        .remember-me input {
            margin-right: 8px;
        }
        
        .remember-me label {
            color: #4a5568;
            font-size: 14px;
        }
        
        /* Email verification notice */
        .verification-notice {
            background: #e0f2fe;
            border: 2px solid #38bdf8;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .verification-notice h4 {
            color: #0369a1;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .verification-notice h4 i {
            margin-right: 10px;
        }
        
        .verification-notice p {
            color: #0c4a6e;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .verification-notice .btn-resend {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #dc2626;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .verification-notice .btn-resend:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Logging in...</p>
    </div>
    
    <div class="login-container hidden md:flex">
        <div class="left-section">
            <a href="index.php" class="back-home">
                <i class="fas fa-long-arrow-alt-left"></i>
            </a>
            
            <div class="logo-container">
                <div class="logo-circle">
                    <img src="images/10213.png" alt="LEIR Logo">
                </div>
                <h1>Law Enforcement</h1>
                <p>Incident Reporting System</p>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure & Encrypted Reporting</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-bolt"></i>
                    <span>Real-time Incident Updates</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-users"></i>
                    <span>Community-Driven Safety</span>
                </div>
            </div>
        </div>
        
        <div class="right-section">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    
                    <?php if (isset($resend_link)): ?>
                        <div class="verification-notice" style="margin-top: 15px;">
                            <h4><i class="fas fa-envelope"></i> Email Not Verified</h4>
                            <p>You need to verify your email before logging in.</p>
                            <a href="<?php echo $resend_link; ?>" class="btn-resend">
                                <i class="fas fa-paper-plane mr-1"></i> Resend Verification Email
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="username" class="form-label required">Username or Email</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Enter username or email" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="error-message" id="username_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label required">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="password_error"></div>
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me for 30 days</label>
                </div>
                
                <button type="submit" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
                
                <div class="register-link">
                    Don't have an account? 
                    <a href="register.php">Create account</a>
                </div>
                
                <div class="register-link" style="margin-top: 10px; font-size: 13px;">
                    <a href="resend_verification.php">Resend verification email</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MOBILE VIEW -->
    <div class="mobile-container md:hidden">
        <div class="mobile-header">
            <div class="mobile-logo-circle">
                <img src="images/10213.png" alt="LEIR Logo">
            </div>
            
            <h1>Law Enforcement</h1>
            <p>Incident Reporting System</p>
            
            <div class="wave-separator-index">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
                    <path fill="white" fill-opacity="1" d="M0,80L48,75C96,70,192,60,288,55C384,50,480,50,576,55C672,60,768,70,864,75C960,80,1056,80,1152,75C1248,70,1344,60,1392,55L1440,50L1440,100L1392,100C1344,100,1248,100,1152,100C1056,100,960,100,864,100C768,100,672,100,576,100C480,100,384,100,288,100C192,100,96,100,48,100L0,100Z"></path>
                </svg>
            </div>
            
            <div class="mobile-features">
                <div class="mobile-feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure & Encrypted</span>
                </div>
                <div class="mobile-feature">
                    <i class="fas fa-bolt"></i>
                    <span>Real-time Updates</span>
                </div>
                <div class="mobile-feature">
                    <i class="fas fa-users"></i>
                    <span>Community Safety</span>
                </div>
            </div>
        </div>
        
        <div class="mobile-form-container">
            <div class="mobile-form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    
                    <?php if (isset($resend_link)): ?>
                        <div class="verification-notice" style="margin-top: 15px;">
                            <h4><i class="fas fa-envelope"></i> Email Not Verified</h4>
                            <p>You need to verify your email before logging in.</p>
                            <a href="<?php echo $resend_link; ?>" class="btn-resend">
                                <i class="fas fa-paper-plane mr-1"></i> Resend Verification
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form id="mobileLoginForm" method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="mobile_username" class="form-label required">Username or Email</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="mobile_username" name="username" class="form-input" 
                               placeholder="Enter username or email" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="error-message" id="mobile_username_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_password" class="form-label required">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="mobile_password" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="toggleMobilePassword('mobile_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="mobile_password_error"></div>
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="mobile_remember" name="remember">
                    <label for="mobile_remember">Remember me</label>
                </div>
                
                <button type="submit" class="btn-login" id="mobileLoginButton">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
                
                <div class="register-link">
                    Don't have an account? 
                    <a href="register.php">Create account</a>
                </div>
                
                <div class="register-link" style="margin-top: 10px; font-size: 13px;">
                    <a href="resend_verification.php">Resend verification email</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentNode.querySelector('.password-toggle i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function toggleMobilePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentNode.querySelector('.password-toggle i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        function validateForm(formId, usernameId, passwordId, submitButtonId, isMobile = false) {
            const form = document.getElementById(formId);
            const username = document.getElementById(usernameId);
            const password = document.getElementById(passwordId);
            const submitButton = document.getElementById(submitButtonId);
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (!form || !username || !password) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                
                // Validate username
                if (!username.value.trim()) {
                    showError(isMobile ? 'mobile_username_error' : 'username_error', 'Username or email is required');
                    isValid = false;
                } else {
                    hideError(isMobile ? 'mobile_username_error' : 'username_error');
                }
                
                // Validate password
                if (!password.value.trim()) {
                    showError(isMobile ? 'mobile_password_error' : 'password_error', 'Password is required');
                    isValid = false;
                } else {
                    hideError(isMobile ? 'mobile_password_error' : 'password_error');
                }
                
                if (isValid) {
                    // Show loading
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
                    loadingOverlay.style.display = 'flex';
                    
                    // Submit form
                    setTimeout(() => {
                        form.submit();
                    }, 1000);
                } else {
                    // Scroll to first error
                    const firstError = form.querySelector('.error-message[style*="block"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
            
            // Real-time validation
            username.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(isMobile ? 'mobile_username_error' : 'username_error', 'Username or email is required');
                } else {
                    hideError(isMobile ? 'mobile_username_error' : 'username_error');
                }
            });
            
            password.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(isMobile ? 'mobile_password_error' : 'password_error', 'Password is required');
                } else {
                    hideError(isMobile ? 'mobile_password_error' : 'password_error');
                }
            });
        }
        
        function showError(errorId, message) {
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        
        function hideError(errorId) {
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set up form validation
            validateForm('loginForm', 'username', 'password', 'loginButton', false);
            validateForm('mobileLoginForm', 'mobile_username', 'mobile_password', 'mobileLoginButton', true);
            
            // Focus on username field
            const usernameField = document.getElementById('username') || document.getElementById('mobile_username');
            if (usernameField) {
                usernameField.focus();
            }
            
            // Auto-fill remember me if cookie exists
            checkRememberMe();
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
        
        // Remember me functionality (cookie-based)
        function checkRememberMe() {
            const cookies = document.cookie.split(';');
            let rememberedUsername = '';
            
            for (let cookie of cookies) {
                cookie = cookie.trim();
                if (cookie.startsWith('remembered_username=')) {
                    rememberedUsername = cookie.substring('remembered_username='.length);
                    break;
                }
            }
            
            if (rememberedUsername) {
                const usernameField = document.getElementById('username') || document.getElementById('mobile_username');
                const rememberCheckbox = document.getElementById('remember') || document.getElementById('mobile_remember');
                
                if (usernameField && rememberCheckbox) {
                    usernameField.value = decodeURIComponent(rememberedUsername);
                    rememberCheckbox.checked = true;
                }
            }
        }
        
        // Save remember me cookie on successful login (this would be handled server-side)
        // For now, we'll just handle it client-side
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const rememberCheckbox = document.getElementById('remember');
            const usernameField = document.getElementById('username');
            
            if (rememberCheckbox?.checked && usernameField?.value) {
                // Set cookie for 30 days
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 30);
                document.cookie = `remembered_username=${encodeURIComponent(usernameField.value)}; expires=${expiryDate.toUTCString()}; path=/`;
            } else {
                // Clear cookie
                document.cookie = 'remembered_username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
        });
        
        document.getElementById('mobileLoginForm')?.addEventListener('submit', function(e) {
            const rememberCheckbox = document.getElementById('mobile_remember');
            const usernameField = document.getElementById('mobile_username');
            
            if (rememberCheckbox?.checked && usernameField?.value) {
                // Set cookie for 30 days
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 30);
                document.cookie = `remembered_username=${encodeURIComponent(usernameField.value)}; expires=${expiryDate.toUTCString()}; path=/`;
            } else {
                // Clear cookie
                document.cookie = 'remembered_username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
        });
    </script>
</body>
</html>