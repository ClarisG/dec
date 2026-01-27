<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists in citizens table
        $stmt = $pdo->prepare("SELECT citizen_id, first_name FROM citizens WHERE email = ? AND account_status = 'active'");
        $stmt->execute([$email]);
        $citizen = $stmt->fetch();
        
        // Check if email exists in personnel table
        $stmt = $pdo->prepare("SELECT personnel_id, first_name FROM personnel WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $personnel = $stmt->fetch();
        
        if ($citizen || $personnel) {
            $user = $citizen ?: $personnel;
            $user_type = $citizen ? 'citizen' : 'personnel';
            $user_id = $citizen ? $citizen['citizen_id'] : $personnel['personnel_id'];
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, user_type, user_id, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $token, $user_type, $user_id, $expires]);
            
            // Create reset link
            $reset_link = "https://leir.jampzdev.com/reset_password.php?token=$token";
            
            // Send email
            require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'lgulawenforcement@gmail.com';
                $mail->Password   = 'lgu4pass123.';
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('lgulawenforcement@gmail.com', 'LGU Law Enforcement');
                $mail->addAddress($email);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "
                    <h3>Password Reset Request</h3>
                    <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>You have requested to reset your password. Click the link below to reset your password:</p>
                    <p><a href='$reset_link' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>Or copy and paste this link in your browser:<br>$reset_link</p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <hr>
                    <p><small>LGU Law Enforcement System</small></p>
                ";
                $mail->AltBody = "Password Reset Request\n\nClick this link to reset your password: $reset_link\n\nThis link expires in 1 hour.";
                
                $mail->send();
                $success = "Password reset link has been sent to your email.";
            } catch (Exception $e) {
                $error = "Failed to send reset email. Please try again later.";
                error_log("Mailer Error: " . $mail->ErrorInfo);
            }
        } else {
            $error = "No account found with that email address";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LGU Law Enforcement</title>
    <link rel="stylesheet" href="styles/auth.css">
    <style>
        .container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .auth-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: #4CAF50;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <h2>Forgot Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your registered email">
                </div>
                
                <button type="submit">Send Reset Link</button>
            </form>
            
            <div class="links">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>