<?php
// reset_password.php - Password reset page

// Include required files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Initialize variables
$error = '';
$success = '';
$validToken = false;
$email = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Check if token is provided
if (empty($token)) {
    $error = "Invalid or missing reset token.";
} else {
    try {
        $conn = getDbConnection();
        
        // Check if token exists and is not expired
        $query = "SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $resetData['email'];
            $validToken = true;
            
            // Handle password reset form submission
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
                
                // Validate passwords
                if (empty($password) || empty($confirm_password)) {
                    $error = "Please fill in all fields.";
                } elseif (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } else {
                    // Hash the new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update user's password
                    $updateQuery = "UPDATE users SET password = :password WHERE email = :email";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindParam(':password', $hashedPassword);
                    $updateStmt->bindParam(':email', $email);
                    
                    if ($updateStmt->execute()) {
                        // Delete used token
                        $deleteQuery = "DELETE FROM password_resets WHERE token = :token";
                        $deleteStmt = $conn->prepare($deleteQuery);
                        $deleteStmt->bindParam(':token', $token);
                        $deleteStmt->execute();
                        
                        $success = "Password has been reset successfully! You can now <a href='login.php' class='text-blue-600 hover:underline'>login with your new password</a>.";
                        $validToken = false; // Token is now used
                    } else {
                        $error = "Failed to update password. Please try again.";
                    }
                }
            }
        } else {
            $error = "Invalid or expired reset token. Please request a new password reset.";
        }
    } catch(PDOException $e) {
        $error = "Database error. Please try again later.";
        error_log("Reset Password Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR | Reset Password</title>
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
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .reset-header::after {
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
            width: 100px;
            height: 100px;
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
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.5));
        }
        
        .reset-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .reset-body {
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
        
        .alert-info {
            background-color: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1d4ed8;
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
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        
        .password-strength {
            margin-top: 8px;
            font-size: 13px;
        }
        
        .strength-meter {
            height: 5px;
            background-color: #e2e8f0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-text {
            font-size: 12px;
            margin-top: 3px;
            color: #718096;
        }
        
        .requirements {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 13px;
        }
        
        .requirements h4 {
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
        }
        
        .requirements ul {
            list-style-type: none;
            padding-left: 5px;
        }
        
        .requirements li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .requirements li i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .requirements li.valid {
            color: #10b981;
        }
        
        .requirements li.invalid {
            color: #e53e3e;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #10b981, #059669);
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
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .link-item a:hover {
            color: #059669;
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
            border-top: 3px solid #10b981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .reset-container {
                border-radius: 15px;
            }
            
            .reset-header {
                padding: 30px 20px;
            }
            
            .reset-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Processing...</p>
    </div>
    
    <div class="reset-container">
        <div class="reset-header">
            <div class="logo-circle">
                <img src="images/10213.png" alt="LEIR Logo">
            </div>
            <h1>Reset Password</h1>
            <p>Set your new password</p>
        </div>
        
        <div class="reset-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$validToken && empty($success)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    This reset token is invalid or has expired. Please request a new password reset.
                </div>
                
                <div class="links-container">
                    <div class="link-item">
                        <a href="forgot_password.php">
                            <i class="fas fa-redo"></i> Request New Reset Link
                        </a>
                    </div>
                    <div class="link-item">
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Back to Login
                        </a>
                    </div>
                </div>
            <?php elseif ($validToken): ?>
                <?php if (!empty($email)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-user-check"></i>
                        Reset password for: <strong><?php echo htmlspecialchars($email); ?></strong>
                    </div>
                <?php endif; ?>
                
                <form id="resetForm" method="POST" action="">
                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter new password" required
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Password strength</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                   placeholder="Confirm new password" required
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="text-sm mt-2"></div>
                    </div>
                    
                    <div class="requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li id="reqLength" class="invalid">
                                <i class="fas fa-times-circle"></i> At least 8 characters
                            </li>
                            <li id="reqUppercase" class="invalid">
                                <i class="fas fa-times-circle"></i> At least one uppercase letter
                            </li>
                            <li id="reqLowercase" class="invalid">
                                <i class="fas fa-times-circle"></i> At least one lowercase letter
                            </li>
                            <li id="reqNumber" class="invalid">
                                <i class="fas fa-times-circle"></i> At least one number
                            </li>
                            <li id="reqSpecial" class="invalid">
                                <i class="fas fa-times-circle"></i> At least one special character
                            </li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
                
                <div class="links-container">
                    <div class="link-item">
                        <a href="login.php">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            let passwordValid = false;
            let passwordMatch = false;
            
            // Password toggle functionality
            function togglePasswordVisibility(inputId, toggleBtnId) {
                const passwordInput = document.getElementById(inputId);
                const toggleBtn = document.getElementById(toggleBtnId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                }
            }
            
            document.getElementById('togglePassword')?.addEventListener('click', function() {
                togglePasswordVisibility('password', 'togglePassword');
            });
            
            document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
                togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
            });
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };
                
                // Update requirement indicators
                document.getElementById('reqLength').className = requirements.length ? 'valid' : 'invalid';
                document.getElementById('reqLength').innerHTML = requirements.length ? 
                    '<i class="fas fa-check-circle"></i> At least 8 characters' :
                    '<i class="fas fa-times-circle"></i> At least 8 characters';
                
                document.getElementById('reqUppercase').className = requirements.uppercase ? 'valid' : 'invalid';
                document.getElementById('reqUppercase').innerHTML = requirements.uppercase ? 
                    '<i class="fas fa-check-circle"></i> At least one uppercase letter' :
                    '<i class="fas fa-times-circle"></i> At least one uppercase letter';
                
                document.getElementById('reqLowercase').className = requirements.lowercase ? 'valid' : 'invalid';
                document.getElementById('reqLowercase').innerHTML = requirements.lowercase ? 
                    '<i class="fas fa-check-circle"></i> At least one lowercase letter' :
                    '<i class="fas fa-times-circle"></i> At least one lowercase letter';
                
                document.getElementById('reqNumber').className = requirements.number ? 'valid' : 'invalid';
                document.getElementById('reqNumber').innerHTML = requirements.number ? 
                    '<i class="fas fa-check-circle"></i> At least one number' :
                    '<i class="fas fa-times-circle"></i> At least one number';
                
                document.getElementById('reqSpecial').className = requirements.special ? 'valid' : 'invalid';
                document.getElementById('reqSpecial').innerHTML = requirements.special ? 
                    '<i class="fas fa-check-circle"></i> At least one special character' :
                    '<i class="fas fa-times-circle"></i> At least one special character';
                
                // Calculate strength
                strength += requirements.length ? 1 : 0;
                strength += requirements.uppercase ? 1 : 0;
                strength += requirements.lowercase ? 1 : 0;
                strength += requirements.number ? 1 : 0;
                strength += requirements.special ? 1 : 0;
                
                // Update strength meter
                const strengthFill = document.getElementById('strengthFill');
                const strengthText = document.getElementById('strengthText');
                
                let percentage = (strength / 5) * 100;
                let color = '#e53e3e';
                let text = 'Very Weak';
                
                if (strength >= 2) {
                    color = '#ed8936';
                    text = 'Weak';
                }
                if (strength >= 3) {
                    color = '#ecc94b';
                    text = 'Fair';
                }
                if (strength >= 4) {
                    color = '#48bb78';
                    text = 'Good';
                }
                if (strength === 5) {
                    color = '#10b981';
                    text = 'Strong';
                }
                
                strengthFill.style.width = percentage + '%';
                strengthFill.style.backgroundColor = color;
                strengthText.textContent = text;
                strengthText.style.color = color;
                
                // Check if all requirements are met
                passwordValid = requirements.length && requirements.uppercase && 
                              requirements.lowercase && requirements.number && 
                              requirements.special;
                
                updateSubmitButton();
            }
            
            // Check if passwords match
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const matchDiv = document.getElementById('passwordMatch');
                
                if (!password || !confirmPassword) {
                    matchDiv.textContent = '';
                    matchDiv.className = 'text-sm mt-2';
                    passwordMatch = false;
                } else if (password === confirmPassword) {
                    matchDiv.innerHTML = '<i class="fas fa-check-circle text-green-500"></i> Passwords match';
                    matchDiv.className = 'text-sm mt-2 text-green-600';
                    passwordMatch = true;
                } else {
                    matchDiv.innerHTML = '<i class="fas fa-times-circle text-red-500"></i> Passwords do not match';
                    matchDiv.className = 'text-sm mt-2 text-red-600';
                    passwordMatch = false;
                }
                
                updateSubmitButton();
            }
            
            // Update submit button state
            function updateSubmitButton() {
                if (passwordValid && passwordMatch) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // Event listeners
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Form submission
            form?.addEventListener('submit', function(e) {
                if (!passwordValid || !passwordMatch) {
                    e.preventDefault();
                    alert('Please fix the password requirements before submitting.');
                    return;
                }
                
                // Show loading spinner
                loadingSpinner.style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting Password...';
                
                // Hide spinner after form submission (in case of error)
                setTimeout(() => {
                    loadingSpinner.style.display = 'none';
                }, 5000);
            });
            
            // Initial checks
            checkPasswordStrength('');
            checkPasswordMatch();
        });
    </script>
</body>
</html>