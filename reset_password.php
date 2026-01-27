<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: forgot_password.php');
    exit();
}

// Check if token is valid
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
$stmt->execute([$token]);
$reset_request = $stmt->fetch();

if (!$reset_request) {
    $error = "Invalid or expired reset link. Please request a new password reset.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_request) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password in appropriate table
        if ($reset_request['user_type'] === 'citizen') {
            $stmt = $pdo->prepare("UPDATE citizens SET password = ? WHERE citizen_id = ?");
            $stmt->execute([$hashed_password, $reset_request['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE personnel SET password = ? WHERE personnel_id = ?");
            $stmt->execute([$hashed_password, $reset_request['user_id']]);
        }
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$reset_request['id']]);
        
        $success = "Password has been reset successfully. You can now login with your new password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LGU Law Enforcement</title>
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
        
        .password-strength {
            margin-top: 5px;
            font-size: 14px;
            display: none;
        }
        
        .strength-weak {
            color: #dc3545;
        }
        
        .strength-medium {
            color: #ffc107;
        }
        
        .strength-strong {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <h2>Reset Password</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <div class="links" style="margin-top: 10px;">
                        <a href="login.php">Go to Login</a>
                    </div>
                </div>
            <?php elseif ($reset_request): ?>
                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter new password" minlength="6">
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm new password" minlength="6">
                    </div>
                    
                    <button type="submit">Reset Password</button>
                </form>
                
                <div class="links">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthText = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthText.style.display = 'none';
                return;
            }
            
            let strength = 'Weak';
            let className = 'strength-weak';
            
            if (password.length >= 8) {
                const hasLetter = /[a-zA-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^a-zA-Z0-9]/.test(password);
                
                let score = 0;
                if (hasLetter) score++;
                if (hasNumber) score++;
                if (hasSpecial) score++;
                if (password.length >= 12) score++;
                
                if (score >= 4) {
                    strength = 'Strong';
                    className = 'strength-strong';
                } else if (score >= 2) {
                    strength = 'Medium';
                    className = 'strength-medium';
                }
            }
            
            strengthText.textContent = `Password strength: ${strength}`;
            strengthText.className = `password-strength ${className}`;
            strengthText.style.display = 'block';
        });
        
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>