<?php
// register_personnel.php - Personnel Registration (Tanod, Secretary, Admin, Captain, Lupon, Super Admin)
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'citizen': header("Location: citizen_dashboard.php"); exit;
        case 'tanod': header("Location: tanod/tanod_dashboard.php"); exit;
        case 'secretary': header("Location: sec/secretary_dashboard.php"); exit;
        case 'captain': header("Location: captain/captain_dashboard.php"); exit;
        case 'admin': header("Location: admin/admin_dashboard.php"); exit;
        case 'lupon': header("Location: lupon/lupon_dashboard.php"); exit;
        case 'super_admin': header("Location: super_admin/super_admin_dashboard.php"); exit;
    }
}

// Include database configuration
require_once 'config/database.php';

$error = '';
$success = '';
$master_code = ''; // Store generated master code for display

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get database connection
    try {
        $conn = getDbConnection();
        
        // Sanitize and validate inputs
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $suffix = trim($_POST['suffix']);
        $sex = trim($_POST['sex']);
        $birthday = trim($_POST['birthday']);
        $age = trim($_POST['age']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = trim($_POST['role']); // This will be 'tanod', 'secretary', 'admin', 'captain', 'lupon', or 'super_admin'
        $terms = isset($_POST['terms']) ? $_POST['terms'] : false;
        
        // Calculate age if not provided
        if (empty($age) && !empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }
        
        // Validate age (must be at least 18 for personnel)
        if ($age < 18) {
            $error = "You must be at least 18 years old to register as personnel.";
        }
        
        // Validate terms agreement
        if (!$terms) {
            $error = "You must agree to the terms and conditions.";
        }
        
        // Validate required fields
        $required_fields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'sex' => 'Sex',
            'birthday' => 'Birthday',
            'address' => 'Address',
            'contact_number' => 'Contact Number',
            'email' => 'Email',
            'username' => 'Username',
            'password' => 'Password',
            'role' => 'Role'
        ];
        
        foreach ($required_fields as $field => $name) {
            if (empty($$field)) {
                $error = "$name is required.";
                break;
            }
        }
        
        // Validate email
        if (empty($error) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        }
        
        // Validate password
        if (empty($error) && strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        }
        
        if (empty($error) && $password !== $confirm_password) {
            $error = "Passwords do not match.";
        }
        
        // Validate role
        $valid_roles = ['tanod', 'secretary', 'admin', 'captain', 'lupon', 'super_admin'];
        if (empty($error) && !in_array($role, $valid_roles)) {
            $error = "Please select a valid role.";
        }
        
        // Check if email or username already exists
        if (empty($error)) {
            $check_query = "SELECT id FROM users WHERE email = :email OR username = :username";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Email or username already exists.";
            }
        }
        
        // If no errors, proceed with registration
        if (empty($error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate a 4-digit master code for personnel
            $master_code = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // Extract barangay from address
            $barangay = 'Unknown';
            if (strpos(strtolower($address), 'barangay') !== false) {
                $matches = [];
                if (preg_match('/barangay\s+(\w+)/i', $address, $matches)) {
                    $barangay = $matches[1];
                } else {
                    $barangay = substr($address, 0, 100);
                }
            } else {
                $barangay = substr($address, 0, 100);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Insert user into database
              $insert_query = "INSERT INTO users (
                first_name, middle_name, last_name, suffix, sex, birthday, age,
                permanent_address, contact_number, email, username, password, 
                role, barangay, status, is_active, master_code, created_at, updated_at, user_type
            ) VALUES (
                :first_name, :middle_name, :last_name, :suffix, :sex, :birthday, :age,
                :address, :contact_number, :email, :username, :password, 
                :role, :barangay, 'active', 1, :master_code, NOW(), NOW(), 'barangay_member'
            )";
                
                $insert_stmt = $conn->prepare($insert_query);
                
                // Bind parameters
                $insert_stmt->bindParam(':first_name', $first_name);
                $insert_stmt->bindParam(':middle_name', $middle_name);
                $insert_stmt->bindParam(':last_name', $last_name);
                $insert_stmt->bindParam(':suffix', $suffix);
                $insert_stmt->bindParam(':sex', $sex);
                $insert_stmt->bindParam(':birthday', $birthday);
                $insert_stmt->bindParam(':age', $age, PDO::PARAM_INT);
                $insert_stmt->bindParam(':address', $address);
                $insert_stmt->bindParam(':contact_number', $contact_number);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':role', $role);
                $insert_stmt->bindParam(':barangay', $barangay);
                $insert_stmt->bindParam(':master_code', $master_code);
                
                if ($insert_stmt->execute()) {
                    $user_id = $conn->lastInsertId();
                    
                    // Also insert into barangay_personnel_registrations table
                    $registration_query = "INSERT INTO barangay_personnel_registrations (
                        user_id, admin_id, master_code, master_code_used, is_active, created_at, updated_at
                    ) VALUES (
                        :user_id, 1001, :master_code, 0, 1, NOW(), NOW()
                    )";
                    
                    $reg_stmt = $conn->prepare($registration_query);
                    $reg_stmt->bindParam(':user_id', $user_id);
                    $reg_stmt->bindParam(':master_code', $master_code);
                    
                    if ($reg_stmt->execute()) {
                        $conn->commit();
                        
                        // Custom success message with master code
                        $role_titles = [
                            'tanod' => 'Tanod',
                            'secretary' => 'Secretary',
                            'admin' => 'Administrator',
                            'captain' => 'Barangay Captain',
                            'lupon' => 'Lupon Member',
                            'super_admin' => 'Super Administrator'
                        ];
                        
                        $success = "{$role_titles[$role]} registration successful! Your 4-digit Master Code is: <strong style='font-size: 24px; color: #667eea;'>{$master_code}</strong><br><br>";
                        $success .= "<div style='background: #f0fff4; padding: 15px; border-radius: 8px; border-left: 4px solid #38a169; margin: 15px 0;'>";
                        $success .= "<strong><i class='fas fa-exclamation-circle'></i> Important:</strong> Save this Master Code! You will need it after entering your username and password during login.";
                        $success .= "</div>";
                        
                        // Clear form data after successful registration
                        $_POST = array();
                        
                        // Store master code in session for display
                        $_SESSION['temp_master_code'] = $master_code;
                        $_SESSION['temp_username'] = $username;
                        $_SESSION['temp_role'] = $role_titles[$role];
                    } else {
                        $conn->rollBack();
                        $error = "Registration failed. Please try again.";
                    }
                } else {
                    $conn->rollBack();
                    $error = "Registration failed. Please try again.";
                }
            } catch(Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Personnel Registration Error: " . $e->getMessage());
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Personnel Registration Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEIR - Personnel Registration</title>
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
        
        /* Desktop Layout */
        @media (min-width: 768px) {
            .register-container {
                display: flex;
                width: 100%;
                max-width: 1200px;
                min-height: 700px;
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
            
            .logo-container {
                position: relative;
                z-index: 10;
                text-align: center;
                color: white;
                max-width: 350px;
            }
            
          .logo-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    width: 100%;
    max-width: 300px;
}

            .logo-circle img {
                width: 200px;
                height: 200px;
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
                flex: 1.5;
                display: flex;
                flex-direction: column;
                padding: 40px;
                background: white;
                overflow-y: auto;
                max-height: 700px;
            }
            
            .form-header {
                text-align: center;
                margin-bottom: 30px;
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
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 40px 30px 80px;
                text-align: center;
                color: white;
                position: relative;
                overflow: hidden;
                z-index: 1;
            }
            
            .mobile-header::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 120px;
                background: white;
                z-index: 2;
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 200'%3E%3Cpath fill='white' d='M0 0 C150 60 300 -40 450 20 C600 80 750 -20 900 40 C1050 100 1125 0 1200 60 L1200 200 L0 200 Z'/%3E%3C/svg%3E");
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 200'%3E%3Cpath fill='white' d='M0 0 C150 60 300 -40 450 20 C600 80 750 -20 900 40 C1050 100 1125 0 1200 60 L1200 200 L0 200 Z'/%3E%3C/svg%3E");
                mask-size: 100% 200px;
                transform: translateY(80px);
            }
            
            .mobile-header::before {
                content: '';
                position: absolute;
                bottom: 10px;
                left: 0;
                width: 100%;
                height: 140px;
                background: rgba(255, 255, 255, 0.25);
                z-index: 1;
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath fill='%23ffffff' d='M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z'/%3E%3C/svg%3E");
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath fill='%23ffffff' d='M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z'/%3E%3C/svg%3E");
                mask-size: 1200px 140px;
                -webkit-mask-size: 1200px 140px;
                transform: translateY(70px);
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
                max-width: 200px;
                height: auto;
                aspect-ratio: 1;
            }

            .mobile-logo-circle img {
                width: 120px;
                height: 120px;
                max-width: 100%;
                object-fit: contain;
                filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.8))
                    drop-shadow(0 0 40px rgba(255, 255, 255, 0.6));
            }
            .mobile-header h1 {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 5px;
                position: relative;
                z-index: 3;
            }
            
            .mobile-header p {
                font-size: 16px;
                opacity: 0.9;
                position: relative;
                z-index: 3;
                margin-bottom: 20px;
            }
            
            .mobile-features {
                display: flex;
                justify-content: center;
                gap: 15px;
                flex-wrap: wrap;
                margin-top: 20px;
                position: relative;
                z-index: 3;
            }
            
            .mobile-feature {
                display: flex;
                align-items: center;
                background: rgba(255, 255, 255, 0.1);
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 12px;
                backdrop-filter: blur(10px);
            }
            
            .mobile-feature i {
                margin-right: 5px;
                font-size: 10px;
            }
            
            .mobile-form-container {
                flex: 1;
                padding: 40px 20px;
                display: flex;
                flex-direction: column;
                position: relative;
                z-index: 3;
                background: white;
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
            margin-bottom: 20px;
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
        
        .form-input, .form-select, .form-textarea {
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
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 480px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
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
            margin-top: 20px;
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
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            font-size: 15px;
            color: #718096;
        }
        
        .login-link a {
            color: #667eea;
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
        
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            background: #e53e3e;
        }
        
        .password-strength-bar.good {
            background: #f6ad55;
        }
        
        .password-strength-bar.strong {
            background: #38a169;
        }
        
        .age-display {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            font-weight: 500;
            color: #2d3748;
            border: 2px solid #e2e8f0;
            text-align: center;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            z-index: 10;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .back-home:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .back-home i {
            margin-right: 8px;
        }
        
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            margin: 30px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        
        .terms-checkbox input {
            margin-top: 3px;
            margin-right: 10px;
        }
        
        .terms-checkbox label {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.5;
        }
        
        .terms-checkbox a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .terms-checkbox a:hover {
            text-decoration: underline;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            background: #f0fff4;
            color: #38a169;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 20px;
            border: 1px solid #c6f6d5;
        }
        
        .security-badge i {
            margin-right: 8px;
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
        
        .error-message {
            color: #e53e3e;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .form-help {
            color: #718096;
            font-size: 12px;
            margin-top: 5px;
        }
        
        /* Role-specific styling - FIXED: Plain white background, only icon circles colored */
        .role-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: white !important; /* Force white background */
        }
        
        .role-option:hover {
            border-color: #667eea;
            background: #f7fafc !important; /* Light gray on hover */
        }
        
        .role-option.selected {
            border-color: #667eea;
            background: #edf2f7 !important; /* Slightly darker gray when selected */
        }
        
        .role-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 18px;
        }
        
        .role-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .role-info p {
            font-size: 13px;
            color: #718096;
        }
        
        /* Role colors for icons only */
        .tanod-role { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .secretary-role { background: linear-gradient(135deg, #2196F3, #0D47A1); }
        .admin-role { background: linear-gradient(135deg, #9C27B0, #4A148C); }
        .captain-role { background: linear-gradient(135deg, #F44336, #B71C1C); }
        .lupon-role { background: linear-gradient(135deg, #FF9800, #E65100); }
        .super-admin-role { background: linear-gradient(135deg, #607D8B, #263238); }
        
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 480px) {
            .role-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Success message box */
        .success-box {
            background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
            border: 2px solid #38a169;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
            animation: slideIn 0.5s ease-out;
        }
        
        .success-box i {
            font-size: 48px;
            color: #38a169;
            margin-bottom: 20px;
        }
        
        .master-code-display {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            border: 3px dashed #667eea;
            margin: 20px auto;
            display: inline-block;
            letter-spacing: 5px;
        }
        
        .login-button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="text-gray-600 font-medium">Processing registration...</p>
    </div>
    
    <div class="register-container hidden md:flex">
        <div class="left-section">
            <a href="index.php" class="back-home">
            <i class="fas fa-long-arrow-alt-left"></i>
            </a>
            
            <div class="logo-container">
                <div class="logo-circle">
             <img src="../dec/images/10213.png" alt="LEIR Logo">
                </div>
            </div>
        </div>
        
        <div class="right-section">
            <div class="form-header">
                <h2>Personnel Account Registration</h2>
                <p>Fill in your details to register as barangay personnel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <h3 style="font-size: 24px; color: #2d3748; margin-bottom: 15px;">Registration Successful!</h3>
                    
                    <div style="font-size: 16px; color: #4a5568; margin-bottom: 20px; line-height: 1.6;">
                        <p>Your account has been created successfully.</p>
                        <p>Please save your <strong>Master Code</strong> - you will need it every time you log in.</p>
                    </div>
                    
                    <div style="margin: 25px 0;">
                        <div style="color: #718096; font-size: 14px; margin-bottom: 10px;">Your 4-digit Master Code:</div>
                        <div class="master-code-display">
                            <?php echo $master_code; ?>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 20px 0; text-align: left;">
                        <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                            <i class="fas fa-exclamation-triangle" style="color: #f6ad55;"></i> 
                            <strong>Important:</strong>
                        </p>
                        <ul style="color: #718096; font-size: 14px; padding-left: 20px;">
                            <li>Save this Master Code in a secure place</li>
                            <li>You will need to enter this code after your username and password</li>
                            <li>Only personnel accounts require Master Code verification</li>
                            <li>Citizen accounts do not use Master Codes</li>
                        </ul>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <a href="login.php" class="login-button">
                            <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                        </a>
                    </div>
                    
                    <div style="margin-top: 20px; color: #718096; font-size: 13px;">
                        Remember: Username: <strong><?php echo isset($_SESSION['temp_username']) ? htmlspecialchars($_SESSION['temp_username']) : ''; ?></strong> | 
                        Role: <strong><?php echo isset($_SESSION['temp_role']) ? htmlspecialchars($_SESSION['temp_role']) : ''; ?></strong>
                    </div>
                </div>
                
                <?php 
                    // Clear temporary session data
                    unset($_SESSION['temp_master_code']);
                    unset($_SESSION['temp_username']);
                    unset($_SESSION['temp_role']);
                ?>
                
            <?php else: ?>
            
            <form id="registrationForm" method="POST" action="" novalidate>
                <h3 style="color: #2d3748; font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user-tie mr-2"></i>Role Selection
                </h3>
                
                <div class="role-grid">
                    <div class="role-option" data-role="tanod">
                        <div class="role-icon tanod-role">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Tanod</h4>
                            <p>Community safety and security officer</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="secretary">
                        <div class="role-icon secretary-role">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Secretary</h4>
                            <p>Administrative and records management</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="admin">
                        <div class="role-icon admin-role">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="role-info">
                            <h4>System Administrator</h4>
                            <p>System management and configuration</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="captain">
                        <div class="role-icon captain-role">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Captain</h4>
                            <p>Barangay leadership and oversight</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="lupon">
                        <div class="role-icon lupon-role">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="role-info">
                            <h4>Lupon Member</h4>
                            <p>Conflict resolution and mediation</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="super_admin">
                        <div class="role-icon super-admin-role">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="role-info">
                            <h4>Super Administrator</h4>
                            <p>System-wide administration and control</p>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="role" name="role" value="<?php echo isset($_POST['role']) ? htmlspecialchars($_POST['role']) : ''; ?>" required>
                <div class="error-message" id="role_error"></div>
                
                <h3 style="color: #2d3748; font-size: 18px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user mr-2"></i>Personal Information
                </h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" class="form-label required">First Name</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" id="first_name" name="first_name" class="form-input" 
                                   placeholder="Enter first name" required
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        <div class="error-message" id="first_name_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label required">Last Name</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" id="last_name" name="last_name" class="form-input" 
                                   placeholder="Enter last name" required
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                        <div class="error-message" id="last_name_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name" class="form-label">Middle Name</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" id="middle_name" name="middle_name" class="form-input" 
                                   placeholder="Enter middle name (optional)"
                                   value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="suffix" class="form-label">Suffix</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-tag"></i>
                            </span>
                            <select id="suffix" name="suffix" class="form-select">
                                <option value="">None</option>
                                <option value="Jr" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'Jr') ? 'selected' : ''; ?>>Jr</option>
                                <option value="Sr" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'Sr') ? 'selected' : ''; ?>>Sr</option>
                                <option value="II" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'II') ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'III') ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'IV') ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sex" class="form-label required">Sex</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-venus-mars"></i>
                            </span>
                            <select id="sex" name="sex" class="form-select" required>
                                <option value="">Select Sex</option>
                                <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="error-message" id="sex_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="birthday" class="form-label required">Birthday</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-calendar"></i>
                            </span>
                            <input type="date" id="birthday" name="birthday" class="form-input" required
                                   value="<?php echo isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : ''; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="error-message" id="birthday_error"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <div class="age-display" id="ageDisplay">Enter birthday to calculate age</div>
                    <input type="hidden" id="age" name="age" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
                </div>
                
                <h3 style="color: #2d3748; font-size: 18px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-address-book mr-2"></i>Contact Details
                </h3>
                
                <div class="form-group">
                    <label for="address" class="form-label required">Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-home"></i>
                        </span>
                        <textarea id="address" name="address" class="form-textarea" 
                                  placeholder="House No., Street, Barangay, City" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    <div class="error-message" id="address_error"></div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="contact_number" class="form-label required">Contact Number</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" id="contact_number" name="contact_number" class="form-input" 
                                   placeholder="0912 345 6789" pattern="[0-9]{11}" required
                                   value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                        </div>
                        <div class="form-help">Format: 09123456789 (11 digits)</div>
                        <div class="error-message" id="contact_number_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label required">Email Address</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="you@example.com" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="error-message" id="email_error"></div>
                    </div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 18px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user-shield mr-2"></i>Account Setup
                </h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username" class="form-label required">Username</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-at"></i>
                            </span>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="Choose a username" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="form-help">Letters, numbers, and underscores only</div>
                        <div class="error-message" id="username_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label required">Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Minimum 8 characters" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="form-help">Include uppercase, lowercase, numbers, and symbols</div>
                        <div class="error-message" id="password_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                   placeholder="Re-enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="confirm_password_error"></div>
                    </div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 18px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-file-contract mr-2"></i>Terms & Conditions
                </h3>
                
                <div class="terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="#" style="color: #667eea;">Terms of Service</a> and 
                        <a href="#" style="color: #667eea;">Privacy Policy</a> of BLUEBACK Incident Reporting System.
                        I understand that as barangay personnel, I have additional responsibilities and access privileges.
                    </label>
                </div>
                <div class="error-message" id="terms_error"></div>
                
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your account will be verified by the system administrator</span>
                </div>
                
                <button type="submit" class="btn-submit" id="submitButton">
                    <i class="fas fa-user-plus mr-2"></i>Register as Personnel
                </button>
                
                <div class="login-link">
                    Already have an account? 
                    <a href="login.php">Sign in here</a> | 
                    <a href="register.php" style="color: #4CAF50;">Citizen Registration</a>
                </div>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mobile-container md:hidden">
        <div class="mobile-header">
            <div class="mobile-logo-circle">
            <img src="../dec/images/10213.png" alt="LEIR Logo">
            </div>
            <h1>Personnel Registration</h1>
            <p>Register as Barangay Personnel</p>
            
            <div class="mobile-features">
                <div class="mobile-feature">
                    <i class="fas fa-shield-alt"></i>Security
                </div>
                <div class="mobile-feature">
                    <i class="fas fa-bolt"></i>Management
                </div>
                <div class="mobile-feature">
                    <i class="fas fa-bell"></i>Alerts
                </div>
                <div class="mobile-feature">
                    <i class="fas fa-users"></i>Community
                </div>
            </div>
        </div>
        
        <div class="mobile-form-container">
            <div class="mobile-form-header">
                <h2>Personnel Account Registration</h2>
                <p>Fill in your details to register as barangay personnel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <h3 style="font-size: 22px; color: #2d3748; margin-bottom: 15px;">Registration Successful!</h3>
                    
                    <div style="font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.6;">
                        <p>Your account has been created successfully.</p>
                        <p>Please save your <strong>Master Code</strong> - you will need it every time you log in.</p>
                    </div>
                    
                    <div style="margin: 20px 0;">
                        <div style="color: #718096; font-size: 14px; margin-bottom: 10px;">Your 4-digit Master Code:</div>
                        <div class="master-code-display">
                            <?php echo $master_code; ?>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 20px 0; text-align: left;">
                        <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                            <i class="fas fa-exclamation-triangle" style="color: #f6ad55;"></i> 
                            <strong>Important:</strong>
                        </p>
                        <ul style="color: #718096; font-size: 14px; padding-left: 20px;">
                            <li>Save this Master Code in a secure place</li>
                            <li>You will need to enter this code after your username and password</li>
                            <li>Only personnel accounts require Master Code verification</li>
                        </ul>
                    </div>
                    
                    <div style="margin-top: 25px;">
                        <a href="login.php" class="login-button">
                            <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                        </a>
                    </div>
                    
                    <div style="margin-top: 20px; color: #718096; font-size: 13px;">
                        Username: <strong><?php echo isset($_SESSION['temp_username']) ? htmlspecialchars($_SESSION['temp_username']) : ''; ?></strong>
                    </div>
                </div>
                
                <?php 
                    // Clear temporary session data
                    unset($_SESSION['temp_master_code']);
                    unset($_SESSION['temp_username']);
                    unset($_SESSION['temp_role']);
                ?>
                
            <?php else: ?>
            
            <form id="mobileRegistrationForm" method="POST" action="" novalidate>
                <h3 style="color: #2d3748; font-size: 16px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user-tie mr-2"></i>Role Selection
                </h3>
                
                <div class="role-grid">
                    <div class="role-option" data-role="tanod">
                        <div class="role-icon tanod-role">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Tanod</h4>
                            <p>Safety and security officer</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="secretary">
                        <div class="role-icon secretary-role">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Secretary</h4>
                            <p>Administrative management</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="admin">
                        <div class="role-icon admin-role">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="role-info">
                            <h4>System Administrator</h4>
                            <p>System configuration</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="captain">
                        <div class="role-icon captain-role">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="role-info">
                            <h4>Barangay Captain</h4>
                            <p>Barangay leadership</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="lupon">
                        <div class="role-icon lupon-role">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="role-info">
                            <h4>Lupon Member</h4>
                            <p>Conflict resolution</p>
                        </div>
                    </div>
                    
                    <div class="role-option" data-role="super_admin">
                        <div class="role-icon super-admin-role">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="role-info">
                            <h4>Super Administrator</h4>
                            <p>System-wide control</p>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="mobile_role" name="role" value="<?php echo isset($_POST['role']) ? htmlspecialchars($_POST['role']) : ''; ?>" required>
                <div class="error-message" id="mobile_role_error"></div>
                
                <h3 style="color: #2d3748; font-size: 16px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user mr-2"></i>Personal Information
                </h3>
                
                <div class="form-group">
                    <label for="mobile_first_name" class="form-label required">First Name</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="mobile_first_name" name="first_name" class="form-input" 
                               placeholder="Enter first name" required
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div class="error-message" id="mobile_first_name_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_last_name" class="form-label required">Last Name</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="mobile_last_name" name="last_name" class="form-input" 
                               placeholder="Enter last name" required
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                    <div class="error-message" id="mobile_last_name_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_middle_name" class="form-label">Middle Name</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="mobile_middle_name" name="middle_name" class="form-input" 
                               placeholder="Enter middle name (optional)"
                               value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_suffix" class="form-label">Suffix</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-tag"></i>
                        </span>
                        <select id="mobile_suffix" name="suffix" class="form-select">
                            <option value="">None</option>
                            <option value="Jr" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'Jr') ? 'selected' : ''; ?>>Jr</option>
                            <option value="Sr" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'Sr') ? 'selected' : ''; ?>>Sr</option>
                            <option value="II" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'II') ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo (isset($_POST['suffix']) && $_POST['suffix'] == 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_sex" class="form-label required">Sex</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-venus-mars"></i>
                        </span>
                        <select id="mobile_sex" name="sex" class="form-select" required>
                            <option value="">Select Sex</option>
                            <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="error-message" id="mobile_sex_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_birthday" class="form-label required">Birthday</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-calendar"></i>
                        </span>
                        <input type="date" id="mobile_birthday" name="birthday" class="form-input" required
                               value="<?php echo isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : ''; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="error-message" id="mobile_birthday_error"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <div class="age-display" id="mobileAgeDisplay">Enter birthday to calculate age</div>
                    <input type="hidden" id="mobile_age" name="age" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
                </div>
                
                <h3 style="color: #2d3748; font-size: 16px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-address-book mr-2"></i>Contact Details
                </h3>
                
                <div class="form-group">
                    <label for="mobile_address" class="form-label required">Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-home"></i>
                        </span>
                        <textarea id="mobile_address" name="address" class="form-textarea" 
                                  placeholder="House No., Street, Barangay, City" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    <div class="error-message" id="mobile_address_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_contact_number" class="form-label required">Contact Number</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-phone"></i>
                        </span>
                        <input type="tel" id="mobile_contact_number" name="contact_number" class="form-input" 
                               placeholder="0912 345 6789" pattern="[0-9]{11}" required
                               value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                    </div>
                    <div class="form-help">Format: 09123456789 (11 digits)</div>
                    <div class="error-message" id="mobile_contact_number_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_email" class="form-label required">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="mobile_email" name="email" class="form-input" 
                               placeholder="you@example.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="error-message" id="mobile_email_error"></div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 16px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-user-shield mr-2"></i>Account Setup
                </h3>
                
                <div class="form-group">
                    <label for="mobile_username" class="form-label required">Username</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-at"></i>
                        </span>
                        <input type="text" id="mobile_username" name="username" class="form-input" 
                               placeholder="Choose a username" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="form-help">Letters, numbers, and underscores only</div>
                    <div class="error-message" id="mobile_username_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_password" class="form-label required">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="mobile_password" name="password" class="form-input" 
                               placeholder="Minimum 8 characters" required>
                        <button type="button" class="password-toggle" onclick="toggleMobilePassword('mobile_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="mobilePasswordStrengthBar"></div>
                    </div>
                    <div class="form-help">Include uppercase, lowercase, numbers, and symbols</div>
                    <div class="error-message" id="mobile_password_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_confirm_password" class="form-label required">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="mobile_confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Re-enter your password" required>
                        <button type="button" class="password-toggle" onclick="toggleMobilePassword('mobile_confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="mobile_confirm_password_error"></div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 16px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-file-contract mr-2"></i>Terms & Conditions
                </h3>
                
                <div class="terms-checkbox">
                    <input type="checkbox" id="mobile_terms" name="terms" required>
                    <label for="mobile_terms">
                        I agree to the <a href="#" style="color: #667eea;">Terms of Service</a> and 
                        <a href="#" style="color: #667eea;">Privacy Policy</a> of BLUEBACK.
                    </label>
                </div>
                <div class="error-message" id="mobile_terms_error"></div>
                
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your account will be verified by administrator</span>
                </div>
                
                <button type="submit" class="btn-submit" id="mobileSubmitButton">
                    <i class="fas fa-user-plus mr-2"></i>Register as Personnel
                </button>
                
                <div class="login-link">
                    Already have an account? 
                    <a href="login.php">Sign in here</a> | 
                    <a href="register.php" style="color: #4CAF50;">Citizen Registration</a>
                </div>
            </form>
            
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Role selection
        function setupRoleSelection() {
            // Desktop version
            document.querySelectorAll('.role-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    document.querySelectorAll('.role-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set the hidden input value
                    const role = this.getAttribute('data-role');
                    document.getElementById('role').value = role;
                    document.getElementById('mobile_role').value = role;
                    
                    // Hide error
                    hideError('role_error');
                    hideError('mobile_role_error');
                });
            });
            
            // Pre-select if role already exists
            const existingRole = document.getElementById('role').value;
            if (existingRole) {
                document.querySelectorAll(`.role-option[data-role="${existingRole}"]`).forEach(option => {
                    option.classList.add('selected');
                });
            }
        }
        
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
        
        // Calculate age from birthday
        function calculateAge(birthday) {
            if (!birthday) return null;
            
            const birthDate = new Date(birthday);
            const today = new Date();
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age >= 0 ? age : null;
        }
        
        // Update age display
        function updateAgeDisplay(birthdayId, ageDisplayId, ageInputId) {
            const birthdayInput = document.getElementById(birthdayId);
            if (!birthdayInput) return;
            
            const age = calculateAge(birthdayInput.value);
            const ageDisplay = document.getElementById(ageDisplayId);
            const ageInput = document.getElementById(ageInputId);
            
            if (age !== null) {
                ageDisplay.textContent = `${age} years old`;
                ageDisplay.style.color = '#38a169';
                ageInput.value = age;
                
                // Validate minimum age (18 for personnel)
                if (age < 18) {
                    ageDisplay.style.color = '#e53e3e';
                    ageDisplay.innerHTML = `${age} years old <span style="color: #e53e3e; font-size: 12px;">(Must be at least 18)</span>`;
                } else {
                    hideError(birthdayId + '_error');
                }
            } else {
                ageDisplay.textContent = 'Invalid date';
                ageDisplay.style.color = '#e53e3e';
                ageInput.value = '';
            }
        }
        
        // Desktop version
        document.getElementById('birthday')?.addEventListener('change', function() {
            updateAgeDisplay('birthday', 'ageDisplay', 'age');
        });
        
        // Mobile version
        document.getElementById('mobile_birthday')?.addEventListener('change', function() {
            updateAgeDisplay('mobile_birthday', 'mobileAgeDisplay', 'mobile_age');
        });
        
        // Password strength checker
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            updatePasswordStrength(password, 'passwordStrengthBar');
        });
        
        document.getElementById('mobile_password')?.addEventListener('input', function() {
            const password = this.value;
            updatePasswordStrength(password, 'mobilePasswordStrengthBar');
        });
        
        function updatePasswordStrength(password, barId) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const bar = document.getElementById(barId);
            const width = (strength / 5) * 100;
            bar.style.width = `${width}%`;
            
            bar.className = 'password-strength-bar';
            if (strength <= 2) {
                bar.style.background = '#e53e3e';
            } else if (strength <= 4) {
                bar.style.background = '#f6ad55';
                bar.classList.add('good');
            } else {
                bar.style.background = '#38a169';
                bar.classList.add('strong');
            }
        }
        
        // Show/hide error messages
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
        
        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';
            
            if (confirmPassword && password !== confirmPassword) {
                showError('confirm_password_error', 'Passwords do not match');
                return false;
            } else {
                hideError('confirm_password_error');
                return true;
            }
        }
        
        function validateMobilePasswordMatch() {
            const password = document.getElementById('mobile_password')?.value || '';
            const confirmPassword = document.getElementById('mobile_confirm_password')?.value || '';
            
            if (confirmPassword && password !== confirmPassword) {
                showError('mobile_confirm_password_error', 'Passwords do not match');
                return false;
            } else {
                hideError('mobile_confirm_password_error');
                return true;
            }
        }
        
        document.getElementById('confirm_password')?.addEventListener('input', validatePasswordMatch);
        document.getElementById('mobile_confirm_password')?.addEventListener('input', validateMobilePasswordMatch);
        
        // Form submission
        function handleFormSubmit(formId, submitButtonId, isMobile = false) {
            const form = document.getElementById(formId);
            const submitButton = document.getElementById(submitButtonId);
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (!form || !submitButton) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate all required fields
                let isValid = true;
                
                // Check role
                const roleId = isMobile ? 'mobile_role' : 'role';
                const roleErrorId = isMobile ? 'mobile_role_error' : 'role_error';
                const role = document.getElementById(roleId)?.value;
                
                if (!role) {
                    showError(roleErrorId, 'Please select a role');
                    isValid = false;
                } else {
                    hideError(roleErrorId);
                }
                
                // Check terms
                const termsId = isMobile ? 'mobile_terms' : 'terms';
                const termsErrorId = isMobile ? 'mobile_terms_error' : 'terms_error';
                const terms = document.getElementById(termsId);
                
                if (!terms?.checked) {
                    showError(termsErrorId, 'You must agree to the terms and conditions');
                    isValid = false;
                } else {
                    hideError(termsErrorId);
                }
                
                // Validate password match
                if (isMobile) {
                    isValid = validateMobilePasswordMatch() && isValid;
                } else {
                    isValid = validatePasswordMatch() && isValid;
                }
                
                // Validate age (must be at least 18)
                const ageInput = document.getElementById(isMobile ? 'mobile_age' : 'age');
                const age = parseInt(ageInput?.value || 0);
                
                if (age < 18) {
                    showError(isMobile ? 'mobile_birthday_error' : 'birthday_error', 'You must be at least 18 years old');
                    isValid = false;
                }
                
                if (isValid) {
                    // Show loading
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Account...';
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
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set up role selection
            setupRoleSelection();
            
            // Set max date for birthday (today)
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('birthday')?.setAttribute('max', today);
            document.getElementById('mobile_birthday')?.setAttribute('max', today);
            
            // Auto-calculate age if birthday exists
            const birthday = document.getElementById('birthday');
            const mobileBirthday = document.getElementById('mobile_birthday');
            
            if (birthday?.value) {
                birthday.dispatchEvent(new Event('change'));
            }
            
            if (mobileBirthday?.value) {
                mobileBirthday.dispatchEvent(new Event('change'));
            }
            
            // Set up form submission handlers
            handleFormSubmit('registrationForm', 'submitButton', false);
            handleFormSubmit('mobileRegistrationForm', 'mobileSubmitButton', true);
            
            // Phone number formatting
            document.querySelectorAll('input[type="tel"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.substr(0, 11);
                    this.value = value;
                });
            });
            
            // Focus on first field
            const firstField = document.getElementById('first_name') || document.getElementById('mobile_first_name');
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