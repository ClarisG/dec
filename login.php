<?php
// login.php - Simplified design matching your uploaded image

// Include database configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Helper function for redirection
function redirectUser($role) {
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

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role']);
}

// Handle login form submission
$error = '';
$success = '';
$showPinModal = false;

// Debug: Check what's in POST
error_log("=== LOGIN DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SERVER REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("=== LOGIN DEBUG END ===");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Processing POST request");
    
    try {
        $conn = getDbConnection();
        
        // Check if this is a PIN verification request
        if (isset($_POST['verify_pin']) && $_POST['verify_pin'] == '1') {
            error_log("Processing PIN verification");
            // Master code verification process
            $entered_code = isset($_POST['pin']) ? trim($_POST['pin']) : '';
            $tempUserId = isset($_POST['temp_user_id']) ? $_POST['temp_user_id'] : '';
            
            error_log("Entered PIN: $entered_code, Temp User ID: $tempUserId");
            
            if (empty($entered_code)) {
                $error = "Please enter your 4-digit Master Code.";
                $showPinModal = true;
                error_log("PIN is empty");
            } else if (strlen($entered_code) != 4 || !is_numeric($entered_code)) {
                $error = "Master Code must be a 4-digit number.";
                $showPinModal = true;
                error_log("Invalid PIN format: $entered_code");
            } else {
                // Get user data from database to verify master code
                $query = "SELECT * FROM users WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $tempUserId);
                $stmt->execute();
                
                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    error_log("User found for PIN verification: " . $user['username']);
                    error_log("Stored master code: " . $user['master_code']);
                    error_log("Master code used status: " . $user['is_master_code_used']);
                    
                    // Verify master code - use loose comparison
                    if (!empty($user['master_code']) && $entered_code == $user['master_code']) {
                        // Master code is correct
                        error_log("PIN verification successful!");
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['barangay'] = $user['barangay'];
                        $_SESSION['phone'] = $user['contact_number'];
                        $_SESSION['master_code_verified'] = true;
                        
                        // Update master code usage
                        $update_query = "UPDATE users SET 
                            is_master_code_used = 1, 
                            master_code_used_at = NOW(), 
                            last_login = NOW() 
                            WHERE id = :id";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bindParam(':id', $user['id']);
                        $update_stmt->execute();
                        
                        // Also update barangay_personnel_registrations if exists
                        try {
                            $update_reg_query = "UPDATE barangay_personnel_registrations 
                                                SET master_code_used = 1, master_code_used_at = NOW() 
                                                WHERE user_id = :user_id";
                            $update_reg_stmt = $conn->prepare($update_reg_query);
                            $update_reg_stmt->bindParam(':user_id', $user['id']);
                            $update_reg_stmt->execute();
                        } catch(Exception $e) {
                            error_log("Could not update barangay_personnel_registrations: " . $e->getMessage());
                        }
                        
                        // Redirect to appropriate dashboard
                        redirectUser($user['role']);
                    } else {
                        $error = "Invalid Master Code. Please try again.";
                        $showPinModal = true;
                        $_SESSION['temp_user_id'] = $tempUserId;
                        error_log("Invalid PIN entered");
                    }
                } else {
                    $error = "User not found. Please login again.";
                    unset($_SESSION['temp_user_id']);
                    error_log("User not found for ID: $tempUserId");
                }
            }
        } else {
            // Regular login process
            error_log("Processing regular login");
            
            // Get username/email and password from POST
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            
            error_log("Username input: " . ($username ?: 'EMPTY'));
            error_log("Password input: " . ($password ? 'PROVIDED' : 'EMPTY'));
            
            // Basic validation
            if (empty($username) || empty($password)) {
                $error = "Please enter both username/email and password.";
                error_log("Validation failed - empty fields");
            } else {
                // Check if user exists with username or email
                $query = "SELECT * FROM users WHERE (username = :username OR email = :email)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $username);
                
                if ($stmt->execute()) {
                    error_log("Query executed successfully");
                    
                    if ($stmt->rowCount() == 1) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        error_log("User found: ID=" . $user['id'] . ", Username=" . $user['username'] . ", Email=" . $user['email'] . ", Role=" . $user['role'] . ", Status=" . $user['status']);
                        error_log("User password hash: " . $user['password']);
                        
                        // Verify password
                        if (password_verify($password, $user['password'])) {
                            error_log("Password verification successful!");
                            
                            // Allow both 'active' and 'pending' status for personnel
                            if ($user['is_active'] && ($user['status'] == 'active' || $user['status'] == 'pending')) {
                                // Check if user is personnel (requires master code)
                                $personnel_roles = ['tanod', 'secretary', 'admin', 'captain', 'lupon', 'super_admin'];
                                
                                if (in_array($user['role'], $personnel_roles)) {
                                    error_log("User is personnel with role: " . $user['role']);
                                    error_log("Master code in DB: " . ($user['master_code'] ?: 'NOT SET'));
                                    error_log("Master code used: " . $user['is_master_code_used']);
                                    
                                    // Check if user has a master code and hasn't used it yet
                                    if (!empty($user['master_code']) && $user['is_master_code_used'] == 0) {
                                        // Store user data temporarily for master code verification
                                        $_SESSION['temp_user_id'] = $user['id'];
                                        $_SESSION['temp_role'] = $user['role'];
                                        $_SESSION['temp_username'] = $user['username'];
                                        $_SESSION['temp_name'] = $user['first_name'] . ' ' . $user['last_name'];
                                        $showPinModal = true;
                                        error_log("Showing PIN modal for personnel");
                                    } else if (!empty($user['master_code']) && $user['is_master_code_used'] == 1) {
                                        // Master code already used, proceed to login
                                        error_log("Master code already used, logging in directly");
                                        
                                        $_SESSION['user_id'] = $user['id'];
                                        $_SESSION['username'] = $user['username'];
                                        $_SESSION['email'] = $user['email'];
                                        $_SESSION['first_name'] = $user['first_name'];
                                        $_SESSION['last_name'] = $user['last_name'];
                                        $_SESSION['role'] = $user['role'];
                                        $_SESSION['barangay'] = $user['barangay'];
                                        $_SESSION['phone'] = $user['contact_number'];
                                        $_SESSION['master_code_verified'] = true;
                                        
                                        // Update last login
                                        $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
                                        $update_stmt = $conn->prepare($update_query);
                                        $update_stmt->bindParam(':id', $user['id']);
                                        $update_stmt->execute();
                                        
                                        // Redirect based on role
                                        redirectUser($user['role']);
                                    } else {
                                        // No master code set
                                        $error = "No master code assigned. Please contact administrator.";
                                        error_log("No master code assigned for personnel");
                                    }
                                } else {
                                    // Check if citizen has verified email
                                    if ($user['email_verified'] == 1) {
                                        // Set session variables for citizens (no master code required)
                                        error_log("User is citizen with verified email, logging in directly");
                                        
                                        $_SESSION['user_id'] = $user['id'];
                                        $_SESSION['username'] = $user['username'];
                                        $_SESSION['email'] = $user['email'];
                                        $_SESSION['first_name'] = $user['first_name'];
                                        $_SESSION['last_name'] = $user['last_name'];
                                        $_SESSION['role'] = $user['role'];
                                        $_SESSION['barangay'] = $user['barangay'];
                                        $_SESSION['phone'] = $user['contact_number'];
                                        
                                        // Update last login
                                        $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
                                        $update_stmt = $conn->prepare($update_query);
                                        $update_stmt->bindParam(':id', $user['id']);
                                        $update_stmt->execute();
                                        
                                        // Redirect based on role
                                        redirectUser($user['role']);
                                    } else {
                                        $error = "Please verify your email address before logging in. Check your inbox (and spam folder) for the verification email.";
                                        error_log("Citizen login failed - email not verified");
                                    }
                                }
                            } else {
                                // Account is not active or pending
                                if (!$user['is_active']) {
                                    $error = "Your account is deactivated. Please contact the administrator.";
                                } elseif ($user['status'] != 'active' && $user['status'] != 'pending') {
                                    $error = "Your account status is '" . htmlspecialchars($user['status']) . "'. Please contact the administrator.";
                                } else {
                                    $error = "Your account is not active. Please contact the administrator.";
                                }
                                error_log("Account not active: is_active=" . $user['is_active'] . ", status=" . $user['status']);
                            }
                        } else {
                            $error = "Invalid username/email or password.";
                            error_log("Password verification failed");
                        }
                    } else {
                        $error = "Invalid username/email or password.";
                        error_log("No user found with username/email: $username");
                    }
                } else {
                    $error = "Something went wrong. Please try again.";
                    error_log("Database query execution failed");
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        // Log the error for debugging
        error_log("Login Error: " . $e->getMessage());
    }
}

// If we get here, show the login form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR | Login</title>
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
        
        /* UPDATED: Larger logo without circle, with white shadow */
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
            /* Container size constraints */
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
    
    .forgot-link {
        display: block;
        text-align: right;
        color: #1a4f8c;
        text-decoration: none;
        font-size: 14px;
        margin-top: 8px;
        font-weight: 500;
    }
    
    .forgot-link:hover {
        text-decoration: underline;
    }
    
    .btn-login {
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
    
    .btn-login:hover {
        transform: translateY(-2px);
        background: linear-gradient(to right, #1d4ed8, #1e3a8a);
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
    
    /* PIN Modal */
    .pin-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(5px);
    }
    
    .pin-modal {
        background: white;
        border-radius: 20px;
        padding: 30px;
        max-width: 380px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: modalFadeIn 0.3s ease-out;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .pin-modal-header {
        margin-bottom: 20px;
    }
    
    .pin-modal-header i {
        font-size: 40px;
        color: #1a4f8c;
        margin-bottom: 15px;
    }
    
    .pin-modal-header h3 {
        font-size: 22px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
    }
    
    .pin-modal-header p {
        color: #718096;
        font-size: 14px;
    }
    
    .pin-input-group {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: 25px 0;
    }
    
    .pin-input {
        width: 55px;
        height: 55px;
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        transition: all 0.2s;
    }
    
    .pin-input:focus {
        outline: none;
        border-color: #1a4f8c;
        box-shadow: 0 0 0 3px rgba(26, 79, 140, 0.1);
    }
    
    .btn-pin {
        width: 100%;
        padding: 14px;
        background: linear-gradient(to right, #1e40af, #1d4ed8);
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 15px;
    }
    
    .btn-pin:hover {
        transform: translateY(-2px);
        background: linear-gradient(to right, #1d4ed8, #1e3a8a);
    }
    
    .pin-cancel {
        background: #f1f5f9;
        color: #4a5568;
        margin-top: 10px;
    }
    
    .pin-cancel:hover {
        background: #e2e8f0;
        transform: none;
        box-shadow: none;
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
        
        .pin-input {
            width: 50px;
            height: 50px;
            font-size: 22px;
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
</style>
</head>
<body>
    
    <div class="pin-modal-overlay" id="pinModal" style="<?php echo $showPinModal ? 'display: flex;' : 'display: none;'; ?>">
        <div class="pin-modal">
            <div class="pin-modal-header">
                <i class="fas fa-shield-alt"></i>
                <h3>Master Code Required</h3>
                <p>Enter your 4-digit Master Code to continue</p>
                <?php if (isset($_SESSION['temp_name'])): ?>
                    <p style="font-size: 12px; color: #718096; margin-top: 5px;">
                        Logging in as: <?php echo htmlspecialchars($_SESSION['temp_name']); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <form id="pinForm" method="POST" action="">
                <input type="hidden" name="temp_user_id" value="<?php echo isset($_SESSION['temp_user_id']) ? $_SESSION['temp_user_id'] : ''; ?>">
                <input type="hidden" name="verify_pin" value="1">
                
                <div class="pin-input-group">
                    <input type="text" name="pin[]" class="pin-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                    <input type="text" name="pin[]" class="pin-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" name="pin[]" class="pin-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" name="pin[]" class="pin-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>
                
                <input type="hidden" id="fullPin" name="pin" value="">
                
                <?php if ($error && $showPinModal): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-pin">
                    <i class="fas fa-lock"></i> Verify Master Code
                </button>
                
                <button type="button" class="btn-pin pin-cancel" onclick="cancelPin()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </form>
        </div>
    </div>
    
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
            </div>
        </div>
        
        <div class="right-section">
            <div class="right-content">
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Sign In to your account</p>
                </div>
                
                <?php if ($error && !$showPinModal): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Registration successful! Please verify your email to login.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['verified'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Email verified successfully! You can now login.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        You have been successfully logged out.
                    </div>
                <?php endif; ?>
                
                <form id="loginForm" method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">Email or Username</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="Enter your email or username" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   autocomplete="username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <a href="forgot_password.php" class="forgot-link">forgot password</a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        Sign In
                    </button>
                    
                    <div class="register-link">
                        Don't have an account? 
                        <a href="register.php">Sign Up</a> 
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
            
            <!-- Add the wave separator from index.php -->
            <div class="wave-separator-index">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
                    <path fill="white" fill-opacity="1" d="M0,80L48,75C96,70,192,60,288,55C384,50,480,50,576,55C672,60,768,70,864,75C960,80,1056,80,1152,75C1248,70,1344,60,1392,55L1440,50L1440,100L1392,100C1344,100,1248,100,1152,100C1056,100,960,100,864,100C768,100,672,100,576,100C480,100,384,100,288,100C192,100,96,100,48,100L0,100Z"></path>
                </svg>
            </div>
        </div>
        <div class="mobile-form-container">
            <div class="mobile-form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error && !$showPinModal): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Registration successful! Please verify your email to login.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['verified'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Email verified successfully! You can now login.
                </div>
            <?php endif; ?>
            
            <form id="mobileLoginForm" method="POST" action="">
                <div class="form-group">
                    <label for="mobile_username" class="form-label">Email or Username</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="text" id="mobile_username" name="username" class="form-input" 
                               placeholder="Enter your email or username" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="mobile_password" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="mobileTogglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">forgot password</a>
                </div>
                
                <button type="submit" class="btn-login">
                    Sign In
                </button>
                
                <div class="register-link">
                    Don't have an account? 
                    <a href="register.php">Sign Up</a>        
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
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
        
        // PIN input handling
        function handlePinInput() {
            const pinInputs = document.querySelectorAll('.pin-input');
            const fullPinInput = document.getElementById('fullPin');
            
            pinInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    // Allow only numbers
                    this.value = this.value.replace(/\D/g, '');
                    
                    if (this.value.length === 1 && index < pinInputs.length - 1) {
                        pinInputs[index + 1].focus();
                    }
                    
                    // Update hidden input with full PIN
                    let fullPin = '';
                    pinInputs.forEach(pin => {
                        fullPin += pin.value;
                    });
                    fullPinInput.value = fullPin;
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        pinInputs[index - 1].focus();
                    }
                });
            });
        }
        
        // Cancel PIN verification
        function cancelPin() {
            document.getElementById('pinModal').style.display = 'none';
            // Clear PIN inputs
            document.querySelectorAll('.pin-input').forEach(input => {
                input.value = '';
            });
            document.getElementById('fullPin').value = '';
            
            // Clear any existing error messages
            const errorAlert = document.querySelector('.pin-modal .alert-error');
            if (errorAlert) {
                errorAlert.remove();
            }
            
            // Clear temporary session
            fetch('config/clear_temp_session.php').catch(console.error);
        }
        
        // Form submission handling
        function handleFormSubmit(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                    
                    // Show loading overlay
                    document.getElementById('loadingOverlay').style.display = 'flex';
                }
            });
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize PIN input handling if modal is shown
            if (document.getElementById('pinModal').style.display === 'flex') {
                handlePinInput();
                // Auto-focus first PIN input
                const firstPinInput = document.querySelector('.pin-input');
                if (firstPinInput) {
                    firstPinInput.focus();
                }
            }
            
            // Password toggle for desktop
            document.getElementById('togglePassword')?.addEventListener('click', function() {
                togglePasswordVisibility('password', 'togglePassword');
            });
            
            // Password toggle for mobile
            document.getElementById('mobileTogglePassword')?.addEventListener('click', function() {
                togglePasswordVisibility('mobile_password', 'mobileTogglePassword');
            });
            
            // Form submission handling
            handleFormSubmit('loginForm');
            handleFormSubmit('mobileLoginForm');
            handleFormSubmit('pinForm');
            
            // Auto-focus username field on page load (if PIN modal not shown)
            const usernameField = document.getElementById('username') || document.getElementById('mobile_username');
            if (usernameField && document.getElementById('pinModal').style.display !== 'flex') {
                usernameField.focus();
            }
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Handle keyboard for better mobile experience
            if ('visualViewport' in window) {
                window.visualViewport.addEventListener('resize', function() {
                    if (window.innerWidth < 768) {
                        document.documentElement.style.height = `${this.height}px`;
                    }
                });
            }
        });
        
        // Handle enter key in password field
        document.getElementById('password')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
        
        document.getElementById('mobile_password')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('mobileLoginForm').submit();
            }
        });
    </script>
</body>
</html>