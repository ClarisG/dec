<?php
// forgot_master_code.php - For barangay personnel who forgot their master code
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getDbConnection();
        
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        
        // Validate inputs
        if (empty($email) || empty($username)) {
            $error = "Both email and username are required.";
        } else {
            // Check if user exists and is barangay personnel
            $check_query = "SELECT u.id, u.first_name, u.last_name, u.email, u.username, 
                                   u.user_type, u.status, p.position_name
                            FROM users u
                            LEFT JOIN barangay_positions p ON u.position_id = p.id
                            WHERE u.email = :email AND u.username = :username 
                            AND u.user_type = 'barangay_member'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error = "Your account is not active. Please contact administrator.";
                } else {
                    // Generate new master code
                    $new_master_code = generateMasterCode();
                    
                    // Get admin ID (first admin found)
                    $admin_query = "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1";
                    $admin_stmt = $conn->prepare($admin_query);
                    $admin_stmt->execute();
                    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Insert new master code record
                    $master_code_query = "INSERT INTO barangay_personnel_master_codes (
                        admin_id, master_code, generated_for_email, generated_for_name,
                        assigned_to, purpose, is_used, expires_at, is_active, created_at
                    ) VALUES (
                        :admin_id, :master_code, :email, :full_name,
                        :user_id, 'master_code_reset', 0, DATE_ADD(NOW(), INTERVAL 7 DAY), 1, NOW()
                    )";
                    
                    $master_code_stmt = $conn->prepare($master_code_query);
                    $full_name = $user['first_name'] . ' ' . $user['last_name'];
                    $master_code_stmt->bindParam(':admin_id', $admin['id'], PDO::PARAM_INT);
                    $master_code_stmt->bindParam(':master_code', $new_master_code);
                    $master_code_stmt->bindParam(':email', $email);
                    $master_code_stmt->bindParam(':full_name', $full_name);
                    $master_code_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                    
                    if ($master_code_stmt->execute()) {
                        $success = "A new master code has been generated for you. Please check with your administrator for the new code.";
                        
                        // Log the activity
                        logActivity($conn, $user['id'], 'request_master_code_reset', 
                                   "Requested master code reset for {$user['email']}");
                    } else {
                        $error = "Failed to generate new master code. Please try again.";
                    }
                }
            } else {
                $error = "No barangay personnel account found with those credentials.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Forgot Master Code Error: " . $e->getMessage());
    }
}

// Helper function to generate master code
function generateMasterCode() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

// Helper function to log activity
function logActivity($conn, $user_id, $action, $description) {
    $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
                  VALUES (:user_id, :action, :description, :ip_address, NOW())";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(':action', $action);
    $log_stmt->bindParam(':description', $description);
    $log_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Master Code | BLUEBACK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../dec/images/10213.png">
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
        
        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }
        
        h1 {
            text-align: center;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 700;
        }
        
        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
            font-size: 16px;
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
            margin-bottom: 20px;
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
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #718096;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin: 0 5px;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .info-box h3 {
            color: #3182ce;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box p {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.5;
        }
        
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
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Processing request...</p>
    </div>
    
    <div class="container">
        <div class="logo">
            <div class="logo-circle">
                <i class="fas fa-key"></i>
            </div>
            <h1>Forgot Master Code</h1>
            <p class="subtitle">For barangay personnel only</p>
        </div>
        
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Important Information</h3>
            <p>This form is for barangay personnel who have forgotten their master code. 
               After verification, a new master code will be generated and you will need 
               to contact your administrator to retrieve it.</p>
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
            
            <div class="info-box">
                <h3><i class="fas fa-shield-alt"></i> Next Steps</h3>
                <p>1. Contact your barangay administrator<br>
                   2. Provide your email and username<br>
                   3. The administrator will give you the new master code<br>
                   4. Use the new master code to login<br>
                   5. Change your password after login</p>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn-submit" style="display: inline-block; width: auto; padding: 12px 30px;">
                    <i class="fas fa-sign-in-alt mr-2"></i>Back to Login
                </a>
            </div>
        <?php else: ?>
            <form id="forgotMasterCodeForm" method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your registered email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Enter your username" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitButton">
                    <i class="fas fa-key mr-2"></i>Request New Master Code
                </button>
                
                <div class="links">
                    <a href="login.php"><i class="fas fa-arrow-left mr-1"></i>Back to Login</a> • 
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotMasterCodeForm');
            const submitButton = document.getElementById('submitButton');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (form && submitButton) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Simple validation
                    const email = document.getElementById('email').value.trim();
                    const username = document.getElementById('username').value.trim();
                    
                    if (!email || !username) {
                        alert('Please fill in all fields');
                        return;
                    }
                    
                    // Show loading
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                    loadingOverlay.style.display = 'flex';
                    
                    // Submit form
                    setTimeout(() => {
                        form.submit();
                    }, 1000);
                });
            }
            
            // Focus on first field
            const firstField = document.getElementById('email');
            if (firstField) {
                firstField.focus();
            }
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>