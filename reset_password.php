<?php
// reset_password.php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "_dashboard.php");
    exit;
}

require_once 'config/database.php';

$error = '';
$success = '';
$valid_token = false;
$email = '';

// Check token
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        $conn = getDbConnection();
        
        // Check if token is valid and not expired
        $query = "SELECT email FROM password_resets 
                  WHERE token = :token 
                  AND expires_at > NOW() 
                  AND used = 0";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $valid_token = true;
            $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $reset_data['email'];
        } else {
            $error = "Invalid or expired reset token. Please request a new password reset.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "No reset token provided.";
}

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $token = trim($_POST['token']);
    
    // Validate passwords
    if (empty($password)) {
        $error = "Please enter a new password.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Check token again (in case of double submission)
            $check_query = "SELECT id, email FROM password_resets 
                           WHERE token = :token 
                           AND expires_at > NOW() 
                           AND used = 0";
            
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':token', $token);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $reset_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                $email = $reset_data['email'];
                
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user password
                $update_query = "UPDATE users SET password = :password WHERE email = :email";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':email', $email);
                
                if ($update_stmt->execute()) {
                    // Mark token as used
                    $mark_used_query = "UPDATE password_resets SET used = 1 WHERE token = :token";
                    $mark_used_stmt = $conn->prepare($mark_used_query);
                    $mark_used_stmt->bindParam(':token', $token);
                    $mark_used_stmt->execute();
                    
                    $success = "Password reset successfully! You can now log in with your new password.";
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
            } else {
                $error = "Invalid or expired reset token.";
            }
        } catch (PDOException $e) {
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
    <title>Reset Password - LEIR</title>
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
        .reset-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c00;
        }
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .password-toggle {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <i class="fas fa-lock text-green-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Set New Password</h2>
            <p class="text-gray-600 mt-2">Create a new password for your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="text-center mt-4">
                <a href="forgot_password.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    Request new reset link
                </a>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="btn-submit">
                    <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                </a>
            </div>
        <?php elseif ($valid_token): ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">New Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" 
                               class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Enter new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        Must be at least 8 characters long
                    </p>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Confirm new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save mr-2"></i>Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
            <p>Remember your password? 
                <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    Sign in here
                </a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
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
        
        // Password strength indicator
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(strength);
        });
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }
        
        function updateStrengthIndicator(strength) {
            // Optional: Add visual strength indicator
        }
    </script>
</body>
</html>