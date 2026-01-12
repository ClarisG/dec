<?php
// forgot_password.php
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

require_once 'config/database.php';
require_once 'config/email_functions.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if email exists
            $query = "SELECT id, first_name, last_name, email_verified FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if email is verified
                if (!$user['email_verified']) {
                    $error = "Please verify your email address first before resetting password.";
                } else {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token
                    $insert_query = "INSERT INTO password_resets (email, token, expires_at) 
                                     VALUES (:email, :token, :expires)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':token', $reset_token);
                    $insert_stmt->bindParam(':expires', $expires_at);
                    $insert_stmt->execute();
                    
                    // Send reset email
                    $reset_link = BASE_URL . "/reset_password.php?token=" . $reset_token;
                    $email_sent = sendPasswordResetEmail(
                        $email, 
                        $user['first_name'] . ' ' . $user['last_name'], 
                        $reset_link
                    );
                    
                    if ($email_sent) {
                        $success = "Password reset instructions have been sent to your email. Please check your inbox.";
                    } else {
                        $error = "Failed to send reset email. Please try again.";
                    }
                }
            } else {
                $error = "No account found with that email address.";
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
    <title>Forgot Password - LEIR</title>
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
        .forgot-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            color: #1e40af;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
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
    </style>
</head>
<body>
    <div class="forgot-card">
        <a href="login.php" class="back-btn">
            <i class="fas fa-arrow-left mr-2"></i>Back to Login
        </a>
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                <i class="fas fa-key text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Forgot Password?</h2>
            <p class="text-gray-600 mt-2">Enter your email to reset your password</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    Return to login page
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" 
                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="you@example.com" required>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        Enter the email address associated with your account
                    </p>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane mr-2"></i>Send Reset Instructions
                </button>
                
                <div class="text-center mt-6 text-sm text-gray-600">
                    <p>Remember your password? 
                        <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">
                            Sign in here
                        </a>
                    </p>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
            <p>Need help? Contact support at support@leir-system.com</p>
        </div>
    </div>
</body>
</html>