<?php
// register.php - Citizen Registration Only
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
        $permanent_address = trim($_POST['permanent_address']);
        $contact_number = trim($_POST['contact_number']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $emergency_number = trim($_POST['emergency_number']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $is_minor = isset($_POST['is_minor']) ? 1 : 0;
        $guardian_name = isset($_POST['guardian_name']) ? trim($_POST['guardian_name']) : '';
        $guardian_contact = isset($_POST['guardian_contact']) ? trim($_POST['guardian_contact']) : '';
        
        // Calculate age if not provided
        if (empty($age) && !empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }
        
        // Determine if user is minor (15-17 years old)
        if ($age < 15) {
            $error = "You must be at least 15 years old to register.";
        } elseif ($age < 18) {
            $is_minor = 1;
        } else {
            $is_minor = 0;
        }
        
        // Validate required fields
        $required_fields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'sex' => 'Sex',
            'birthday' => 'Birthday',
            'permanent_address' => 'Permanent Address',
            'contact_number' => 'Contact Number',
            'email' => 'Email',
            'username' => 'Username',
            'password' => 'Password'
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
        
        // Validate minor requirements
        if (empty($error) && $is_minor) {
            if (empty($guardian_name)) {
                $error = "Guardian name is required for minors.";
            } elseif (empty($guardian_contact)) {
                $error = "Guardian contact number is required for minors.";
            }
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
            
            // Handle file upload for ID verification
            $id_verification_path = null;
            $guardian_id_path = null;
            $id_type = $is_minor ? 'school_id' : 'barangay_id';
            
            // Create upload directories if they don't exist
            $uploads_dir = __DIR__ . '/uploads';
            $ids_dir = $uploads_dir . '/ids';
            $guardian_ids_dir = $uploads_dir . '/guardian_ids';
            
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }
            if (!file_exists($ids_dir)) {
                mkdir($ids_dir, 0777, true);
            }
            if (!file_exists($guardian_ids_dir)) {
                mkdir($guardian_ids_dir, 0777, true);
            }
            
            // Determine which file input to check based on user type
            if ($is_minor) {
                // For minors: Check for school_id_verification
                $file_input_name = 'school_id_verification';
            } else {
                // For adults: Check for id_verification
                $file_input_name = 'id_verification';
            }
            
            // Check if file was uploaded
            if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                
                if ($_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $destination = 'uploads/ids/' . $filename;
                    
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (in_array(strtolower($file_extension), $allowed_extensions) &&
                        $_FILES[$file_input_name]['size'] <= 5 * 1024 * 1024) {
                        
                        if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $destination)) {
                            $id_verification_path = $destination;
                        } else {
                            $error = "Failed to upload ID. Please try again.";
                        }
                    } else {
                        $error = "Invalid file type or size. Only JPG, PNG, PDF up to 5MB are allowed.";
                    }
                } else {
                    // Check what the actual error is
                    switch ($_FILES[$file_input_name]['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error = "File is too large. Maximum size is 5MB.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = "File was only partially uploaded.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error = "Missing temporary folder.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error = "Failed to write file to disk.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error = "A PHP extension stopped the file upload.";
                            break;
                        default:
                            $error = "Unknown upload error (Error code: " . $_FILES[$file_input_name]['error'] . ").";
                            break;
                    }
                }
            } else {
                $error = $is_minor ? "School ID upload is required for minors." : "Barangay ID upload is required for adults.";
            }
            
            // Upload guardian's barangay ID for minors (only if first upload succeeded)
            if (empty($error) && $is_minor) {
                if (isset($_FILES['guardian_id_verification']) && $_FILES['guardian_id_verification']['error'] !== UPLOAD_ERR_NO_FILE) {
                    
                    if ($_FILES['guardian_id_verification']['error'] == UPLOAD_ERR_OK) {
                        $file_extension = pathinfo($_FILES['guardian_id_verification']['name'], PATHINFO_EXTENSION);
                        $filename = 'guardian_' . uniqid() . '_' . time() . '.' . $file_extension;
                        $destination = 'uploads/guardian_ids/' . $filename;
                        
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (in_array(strtolower($file_extension), $allowed_extensions) &&
                            $_FILES['guardian_id_verification']['size'] <= 5 * 1024 * 1024) {
                            
                            if (move_uploaded_file($_FILES['guardian_id_verification']['tmp_name'], $destination)) {
                                $guardian_id_path = $destination;
                            } else {
                                $error = "Failed to upload guardian's ID. Please try again.";
                            }
                        } else {
                            $error = "Invalid file type or size for guardian's ID. Only JPG, PNG, PDF up to 5MB are allowed.";
                        }
                    } else {
                        switch ($_FILES['guardian_id_verification']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $error = "Guardian's file is too large. Maximum size is 5MB.";
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error = "Guardian's file was only partially uploaded.";
                                break;
                            default:
                                $error = "Error uploading guardian's ID. Please try again.";
                                break;
                        }
                    }
                } else {
                    $error = "Guardian's Barangay ID upload is required for minors.";
                }
            }
            
            // Extract barangay from address
            $barangay = 'Unknown';
            if (empty($error)) {
                if (strpos(strtolower($permanent_address), 'barangay') !== false) {
                    $matches = [];
                    if (preg_match('/barangay\s+(\w+)/i', $permanent_address, $matches)) {
                        $barangay = $matches[1];
                    } else {
                        $barangay = substr($permanent_address, 0, 100);
                    }
                } else {
                    $barangay = substr($permanent_address, 0, 100);
                }
                
                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Insert user into database - ONLY AS CITIZEN
                    $insert_query = "INSERT INTO users (
                        first_name, middle_name, last_name, suffix, sex, birthday, age,
                        permanent_address, contact_number, emergency_contact, emergency_number,
                        id_verification_path, email, username, password, role, barangay, status,
                        is_active, pin_code, created_at, updated_at, user_type
                    ) VALUES (
                        :first_name, :middle_name, :last_name, :suffix, :sex, :birthday, :age,
                        :permanent_address, :contact_number, :emergency_contact, :emergency_number,
                        :id_verification_path, :email, :username, :password, 'citizen', :barangay, 'active',
                        1, NULL, NOW(), NOW(), 'citizen'
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
                    $insert_stmt->bindParam(':permanent_address', $permanent_address);
                    $insert_stmt->bindParam(':contact_number', $contact_number);
                    $insert_stmt->bindParam(':emergency_contact', $emergency_contact);
                    $insert_stmt->bindParam(':emergency_number', $emergency_number);
                    $insert_stmt->bindParam(':id_verification_path', $id_verification_path);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':username', $username);
                    $insert_stmt->bindParam(':password', $hashed_password);
                    $insert_stmt->bindParam(':barangay', $barangay);
                    
                    if ($insert_stmt->execute()) {
                        $user_id = $conn->lastInsertId();
                        
                        // Insert citizen details (ONLY for citizen users)
                        $citizen_query = "INSERT INTO user_citizen_details (
                            user_id, guardian_name, guardian_contact, id_type, 
                            id_upload_path, guardian_id_upload_path, created_at, updated_at
                        ) VALUES (
                            :user_id, :guardian_name, :guardian_contact, :id_type,
                            :id_upload_path, :guardian_id_upload_path, NOW(), NOW()
                        )";
                        
                        $citizen_stmt = $conn->prepare($citizen_query);
                        $citizen_stmt->bindParam(':user_id', $user_id);
                        $citizen_stmt->bindParam(':guardian_name', $guardian_name);
                        $citizen_stmt->bindParam(':guardian_contact', $guardian_contact);
                        $citizen_stmt->bindParam(':id_type', $id_type);
                        $citizen_stmt->bindParam(':id_upload_path', $id_verification_path);
                        $citizen_stmt->bindParam(':guardian_id_upload_path', $guardian_id_path);
                        
                        if ($citizen_stmt->execute()) {
                            $conn->commit();
                            $success = "Registration successful! You can now login with your credentials.";
                            $_POST = array(); // Clear form data
                        } else {
                            $conn->rollBack();
                            $error = "Failed to save citizen details. Please try again.";
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
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Registration Error: " . $e->getMessage());
        if (isset($insert_query)) {
            error_log("SQL Query: " . $insert_query);
        }
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Registration Error: " . $e->getMessage());
    }
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
                max-width: 1000px;
                min-height: 600px;
                background: white;
                border-radius: 30px;
                overflow: hidden;
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
            
            /* Wave Effect - Matching login.php */
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
                padding: 40px;
                background: white;
                overflow-y: auto;
                max-height: 600px;
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
        
        /* Mobile Layout - UPDATED TO MATCH login.php */
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
            
            /* Wave container - matching login.php */
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
            
            /* UPDATED: Larger logo without circle, with white shadow - matching login.php */
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
            
            /* Remove mobile-features styles */
            .mobile-features,
            .mobile-feature {
                display: none;
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
            border-color: #1a4f8c;
            box-shadow: 0 0 0 3px rgba(26, 79, 140, 0.1);
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
            color: white;
            background: linear-gradient(to right, #1e40af, #1d4ed8);
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
            background: linear-gradient(to right, #1d4ed8, #1e3a8a);
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
        
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: #1a4f8c;
            background: #edf2f7;
        }
        
        .file-upload-area i {
            font-size: 40px;
            color: #1a4f8c;
            margin-bottom: 15px;
        }
        
        .file-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f0fff4;
            border-radius: 8px;
            border: 1px solid #c6f6d5;
            display: none;
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
            color: #1a4f8c;
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
        
        /* Minor info styles */
        .minor-section {
            display: none;
            background: #fff7ed;
            border: 2px solid #fed7aa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .minor-notice {
            display: flex;
            align-items: center;
            background: #fef3c7;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #fbbf24;
        }
        
        .minor-notice i {
            margin-right: 10px;
            font-size: 18px;
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
                    <img src="images/10213.png" alt="LEIR Logo">
                </div>                
            </div>
        </div>
        
        <div class="right-section">
            <div class="form-header">
                <h2>Sign Up</h2>
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
                    <p style="margin-top: 10px;"><a href="login.php" style="font-weight: 600; color: #1a4f8c;">Click here to login</a></p>
                </div>
            <?php endif; ?>
            
            <form id="registrationForm" method="POST" action="" enctype="multipart/form-data" novalidate>
                <h3 style="color: #2d3748; font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
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
                                <option value="Other" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Other') ? 'selected' : ''; ?>>Other</option>
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
                    <input type="hidden" id="is_minor" name="is_minor" value="0">
                </div>
                
                <!-- Minor Information Section -->
                <div class="minor-section" id="minorSection">
                    <div class="minor-notice">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Since you are a minor (15-17 years old), guardian information is required.</span>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="guardian_name" class="form-label required">Guardian's Name</label>
                            <div class="input-group">
                                <span class="input-icon">
                                    <i class="fas fa-user-friends"></i>
                                </span>
                                <input type="text" id="guardian_name" name="guardian_name" class="form-input" 
                                       placeholder="Guardian's full name"
                                       value="<?php echo isset($_POST['guardian_name']) ? htmlspecialchars($_POST['guardian_name']) : ''; ?>">
                            </div>
                            <div class="error-message" id="guardian_name_error"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_contact" class="form-label required">Guardian's Contact</label>
                            <div class="input-group">
                                <span class="input-icon">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" id="guardian_contact" name="guardian_contact" class="form-input" 
                                       placeholder="0912 345 6789" pattern="[0-9]{11}"
                                       value="<?php echo isset($_POST['guardian_contact']) ? htmlspecialchars($_POST['guardian_contact']) : ''; ?>">
                            </div>
                            <div class="error-message" id="guardian_contact_error"></div>
                        </div>
                    </div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 18px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-address-book mr-2"></i>Contact Details
                </h3>
                
                <div class="form-group">
                    <label for="permanent_address" class="form-label required">Permanent Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-home"></i>
                        </span>
                        <textarea id="permanent_address" name="permanent_address" class="form-textarea" 
                                  placeholder="House No., Street, Barangay, City" required><?php echo isset($_POST['permanent_address']) ? htmlspecialchars($_POST['permanent_address']) : ''; ?></textarea>
                    </div>
                    <div class="error-message" id="permanent_address_error"></div>
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
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emergency_contact" class="form-label">Emergency Contact Person</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user-friends"></i>
                            </span>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-input" 
                                   placeholder="Full name (Optional)"
                                   value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_number" class="form-label">Emergency Contact Number</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-phone-alt"></i>
                            </span>
                            <input type="tel" id="emergency_number" name="emergency_number" class="form-input" 
                                   placeholder="0912 345 6789 (Optional)" pattern="[0-9]{11}"
                                   value="<?php echo isset($_POST['emergency_number']) ? htmlspecialchars($_POST['emergency_number']) : ''; ?>">
                        </div>
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
                    <i class="fas fa-file-upload mr-2"></i>Verification & Terms
                </h3>
                
                <!-- Adult ID Upload -->
                <div class="form-group" id="adultIdSection">
                    <label class="form-label required">Barangay ID Verification</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-id-card"></i>
                        <p style="font-weight: 500; margin: 10px 0;">Drag & drop or click to upload your Barangay ID</p>
                        <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                        <input type="file" id="id_verification" name="id_verification" 
                            accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                    </div>
                    <div class="file-preview" id="filePreview">
                        <p style="font-weight: 500; color: #38a169;">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span id="fileName"></span> uploaded successfully
                        </p>
                    </div>
                    <div class="error-message" id="id_verification_error"></div>
                </div>

                <!-- Minor ID Uploads -->
                <div class="minor-section" id="minorIdSection">
                    <div class="form-group">
                        <label class="form-label required">School ID Verification</label>
                        <div class="file-upload-area" id="schoolFileUploadArea">
                            <i class="fas fa-graduation-cap"></i>
                            <p style="font-weight: 500; margin: 10px 0;">Drag & drop or click to upload your School ID</p>
                            <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                            <input type="file" id="school_id_verification" name="school_id_verification" 
                                accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                        </div>
                        <div class="file-preview" id="schoolFilePreview">
                            <p style="font-weight: 500; color: #38a169;">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span id="schoolFileName"></span> uploaded successfully
                            </p>
                        </div>
                        <div class="error-message" id="school_id_verification_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Guardian's Barangay ID Verification</label>
                        <div class="file-upload-area" id="guardianFileUploadArea">
                            <i class="fas fa-id-card"></i>
                            <p style="font-weight: 500; margin: 10px 0;">Drag & drop or click to upload Guardian's Barangay ID</p>
                            <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                            <input type="file" id="guardian_id_verification" name="guardian_id_verification" 
                                accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                        </div>
                        <div class="file-preview" id="guardianFilePreview">
                            <p style="font-weight: 500; color: #38a169;">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span id="guardianFileName"></span> uploaded successfully
                            </p>
                        </div>
                        <div class="error-message" id="guardian_id_verification_error"></div>
                    </div>
                </div>
                
                <div class="terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="#" style="color: #1a4f8c;">Terms of Service</a> and 
                        <a href="#" style="color: #1a4f8c;">Privacy Policy</a> of Law Enforcement Incident Reporting System.
                        I understand that my account needs to be verified before I can access all features.
                    </label>
                </div>
                <div class="error-message" id="terms_error"></div>
                
                <button type="submit" class="btn-submit" id="submitButton">
                    <i class="fas fa-user-plus mr-2"></i>Sign Up
                </button>
                
                <div class="login-link">
                    Already have an account? 
                    <a href="login.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MOBILE VIEW - UPDATED TO MATCH login.php -->
    <div class="mobile-container md:hidden">
        <div class="mobile-header">
            <!-- Updated logo container -->
            <div class="mobile-logo-circle">
                <img src="images/10213.png" alt="LEIR Logo">
            </div>
            
            <h1>Law Enforcement</h1>
            <p>Incident Reporting System</p>
            
            <!-- Add the wave separator from login.php -->
            <div class="wave-separator-index">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
                    <path fill="white" fill-opacity="1" d="M0,80L48,75C96,70,192,60,288,55C384,50,480,50,576,55C672,60,768,70,864,75C960,80,1056,80,1152,75C1248,70,1344,60,1392,55L1440,50L1440,100L1392,100C1344,100,1248,100,1152,100C1056,100,960,100,864,100C768,100,672,100,576,100C480,100,384,100,288,100C192,100,96,100,48,100L0,100Z"></path>
                </svg>
            </div>
        </div>
        
        <div class="mobile-form-container">
            <div class="mobile-form-header">
                <h2>Sign up</h2>
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
                    <p style="margin-top: 10px;"><a href="login.php" style="font-weight: 600; color: #1a4f8c;">Click here to login</a></p>
                </div>
            <?php endif; ?>
            
            <form id="mobileRegistrationForm" method="POST" action="" enctype="multipart/form-data" novalidate>
                <h3 style="color: #2d3748; font-size: 16px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
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
                    <input type="hidden" id="mobile_is_minor" name="is_minor" value="0">
                </div>
                
                <!-- Mobile Minor Information Section -->
                <div class="minor-section" id="mobileMinorSection">
                    <div class="minor-notice">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Since you are a minor (15-17 years old), guardian information is required.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_guardian_name" class="form-label required">Guardian's Name</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user-friends"></i>
                            </span>
                            <input type="text" id="mobile_guardian_name" name="guardian_name" class="form-input" 
                                   placeholder="Guardian's full name"
                                   value="<?php echo isset($_POST['guardian_name']) ? htmlspecialchars($_POST['guardian_name']) : ''; ?>">
                        </div>
                        <div class="error-message" id="mobile_guardian_name_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_guardian_contact" class="form-label required">Guardian's Contact</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" id="mobile_guardian_contact" name="guardian_contact" class="form-input" 
                                   placeholder="0912 345 6789" pattern="[0-9]{11}"
                                   value="<?php echo isset($_POST['guardian_contact']) ? htmlspecialchars($_POST['guardian_contact']) : ''; ?>">
                        </div>
                        <div class="error-message" id="mobile_guardian_contact_error"></div>
                    </div>
                </div>
                
                <h3 style="color: #2d3748; font-size: 16px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-address-book mr-2"></i>Contact Details
                </h3>
                
                <div class="form-group">
                    <label for="mobile_permanent_address" class="form-label required">Permanent Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-home"></i>
                        </span>
                        <textarea id="mobile_permanent_address" name="permanent_address" class="form-textarea" 
                                  placeholder="House No., Street, Barangay, City" required><?php echo isset($_POST['permanent_address']) ? htmlspecialchars($_POST['permanent_address']) : ''; ?></textarea>
                    </div>
                    <div class="error-message" id="mobile_permanent_address_error"></div>
                    <div class="form-help">Example: 123 Main Street, Barangay 1, City</div>
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
                
                <div class="form-group">
                    <label for="mobile_emergency_contact" class="form-label">Emergency Contact Person</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user-friends"></i>
                        </span>
                        <input type="text" id="mobile_emergency_contact" name="emergency_contact" class="form-input" 
                               placeholder="Full name (Optional)"
                               value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mobile_emergency_number" class="form-label">Emergency Contact Number</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-phone-alt"></i>
                        </span>
                        <input type="tel" id="mobile_emergency_number" name="emergency_number" class="form-input" 
                               placeholder="0912 345 6789 (Optional)" pattern="[0-9]{11}"
                               value="<?php echo isset($_POST['emergency_number']) ? htmlspecialchars($_POST['emergency_number']) : ''; ?>">
                    </div>
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
                    <i class="fas fa-file-upload mr-2"></i>Verification & Terms
                </h3>
                
                <!-- Mobile Adult ID Upload -->
                <div class="form-group" id="mobileAdultIdSection">
                    <label class="form-label required">Barangay ID Verification</label>
                    <div class="file-upload-area" id="mobileFileUploadArea">
                        <i class="fas fa-id-card"></i>
                        <p style="font-weight: 500; margin: 10px 0;">Tap to upload your Barangay ID</p>
                        <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                        <input type="file" id="mobile_id_verification" name="id_verification" 
                               accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                    </div>
                    <div class="file-preview" id="mobileFilePreview">
                        <p style="font-weight: 500; color: #38a169;">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span id="mobileFileName"></span> uploaded successfully
                        </p>
                    </div>
                    <div class="error-message" id="mobile_id_verification_error"></div>
                </div>

                <!-- Mobile Minor ID Uploads -->
                <div class="minor-section" id="mobileMinorIdSection">
                    <div class="form-group">
                        <label class="form-label required">School ID Verification</label>
                        <div class="file-upload-area" id="mobileSchoolFileUploadArea">
                            <i class="fas fa-graduation-cap"></i>
                            <p style="font-weight: 500; margin: 10px 0;">Tap to upload your School ID</p>
                            <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                            <input type="file" id="mobile_school_id_verification" name="school_id_verification" 
                                   accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                        </div>
                        <div class="file-preview" id="mobileSchoolFilePreview">
                            <p style="font-weight: 500; color: #38a169;">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span id="mobileSchoolFileName"></span> uploaded successfully
                            </p>
                        </div>
                        <div class="error-message" id="mobile_school_id_verification_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Guardian's Barangay ID Verification</label>
                        <div class="file-upload-area" id="mobileGuardianFileUploadArea">
                            <i class="fas fa-id-card"></i>
                            <p style="font-weight: 500; margin: 10px 0;">Tap to upload Guardian's Barangay ID</p>
                            <p class="form-help">JPG, PNG or PDF (Max 5MB)</p>
                            <input type="file" id="mobile_guardian_id_verification" name="guardian_id_verification" 
                                   accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                        </div>
                        <div class="file-preview" id="mobileGuardianFilePreview">
                            <p style="font-weight: 500; color: #38a169;">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span id="mobileGuardianFileName"></span> uploaded successfully
                            </p>
                        </div>
                        <div class="error-message" id="mobile_guardian_id_verification_error"></div>
                    </div>
                </div>
                
                <div class="terms-checkbox">
                    <input type="checkbox" id="mobile_terms" name="terms" required>
                    <label for="mobile_terms">
                        I agree to the <a href="#" style="color: #1a4f8c;">Terms of Service</a> and 
                        <a href="#" style="color: #1a4f8c;">Privacy Policy</a> of Law Enforcement and Incident Reporting.
                         I understand that my account needs to be verified before I can access all features.
                    </label>
                </div>
                <div class="error-message" id="mobile_terms_error"></div>
                
                <button type="submit" class="btn-submit" id="mobileSubmitButton">
                    <i class="fas fa-user-plus mr-2"></i>Register as Citizen
                </button>
                
                <div class="login-link">
                    Already have an account? 
                    <a href="login.php">Sign in here</a>
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
        
        // Update age display and show/hide minor sections
        function updateAgeDisplay(birthdayId, ageDisplayId, ageInputId, isMinorId, minorSectionId, minorIdSectionId, adultIdSectionId) {
            const birthdayInput = document.getElementById(birthdayId);
            if (!birthdayInput) return;
            
            const age = calculateAge(birthdayInput.value);
            const ageDisplay = document.getElementById(ageDisplayId);
            const ageInput = document.getElementById(ageInputId);
            const isMinorInput = document.getElementById(isMinorId);
            const minorSection = document.getElementById(minorSectionId);
            const minorIdSection = minorIdSectionId ? document.getElementById(minorIdSectionId) : null;
            const adultIdSection = adultIdSectionId ? document.getElementById(adultIdSectionId) : null;
            
            if (age !== null) {
                ageDisplay.textContent = `${age} years old`;
                ageDisplay.style.color = '#38a169';
                ageInput.value = age;
                
                // Check if minor (15-17 years old)
                if (age >= 15 && age <= 17) {
                    // Minor
                    ageDisplay.style.color = '#d97706'; // Orange color for minors
                    ageDisplay.innerHTML = `${age} years old <span style="color: #dc2626; font-size: 12px;">(Minor)</span>`;
                    isMinorInput.value = '1';
                    
                    // Show minor sections
                    if (minorSection) minorSection.style.display = 'block';
                    if (minorIdSection) minorIdSection.style.display = 'block';
                    if (adultIdSection) adultIdSection.style.display = 'none';
                    
                    // Validate minimum age for minors
                    if (age < 15) {
                        showError(birthdayId + '_error', 'You must be at least 15 years old');
                    } else {
                        hideError(birthdayId + '_error');
                    }
                } else if (age >= 18) {
                    // Adult
                    isMinorInput.value = '0';
                    
                    // Hide minor sections
                    if (minorSection) minorSection.style.display = 'none';
                    if (minorIdSection) minorIdSection.style.display = 'none';
                    if (adultIdSection) adultIdSection.style.display = 'block';
                    
                    hideError(birthdayId + '_error');
                } else {
                    // Too young
                    ageDisplay.style.color = '#dc2626';
                    ageDisplay.innerHTML = `${age} years old <span style="color: #dc2626; font-size: 12px;">(Must be at least 15)</span>`;
                    showError(birthdayId + '_error', 'You must be at least 15 years old');
                    isMinorInput.value = '0';
                    
                    // Hide all sections
                    if (minorSection) minorSection.style.display = 'none';
                    if (minorIdSection) minorIdSection.style.display = 'none';
                    if (adultIdSection) adultIdSection.style.display = 'none';
                }
            } else {
                ageDisplay.textContent = 'Invalid date';
                ageDisplay.style.color = '#e53e3e';
                ageInput.value = '';
                isMinorInput.value = '0';
                
                // Hide all sections
                if (minorSection) minorSection.style.display = 'none';
                if (minorIdSection) minorIdSection.style.display = 'none';
                if (adultIdSection) adultIdSection.style.display = 'none';
            }
        }
        
        // Desktop version
        document.getElementById('birthday')?.addEventListener('change', function() {
            updateAgeDisplay('birthday', 'ageDisplay', 'age', 'is_minor', 'minorSection', 'minorIdSection', 'adultIdSection');
        });
        
        // Mobile version
        document.getElementById('mobile_birthday')?.addEventListener('change', function() {
            updateAgeDisplay('mobile_birthday', 'mobileAgeDisplay', 'mobile_age', 'mobile_is_minor', 'mobileMinorSection', 'mobileMinorIdSection', 'mobileAdultIdSection');
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
        
        // File upload handling
        function setupFileUpload(uploadAreaId, fileInputId, previewId, fileNameId, errorId) {
            const uploadArea = document.getElementById(uploadAreaId);
            const fileInput = document.getElementById(fileInputId);
            const preview = document.getElementById(previewId);
            const fileName = document.getElementById(fileNameId);
            
            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());
                
                fileInput.addEventListener('change', function(e) {
                    handleFileUpload(this.files[0], preview, fileName, errorId);
                });
                
                // Drag and drop
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        const event = new Event('change');
                        fileInput.dispatchEvent(event);
                    }
                });
            }
        }
        
        function handleFileUpload(file, previewElement, nameElement, errorId) {
            if (file) {
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showError(errorId, 'File size must be less than 5MB');
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!validTypes.includes(file.type)) {
                    showError(errorId, 'Only JPG, PNG, and PDF files are allowed');
                    return;
                }
                
                if (nameElement) nameElement.textContent = file.name;
                if (previewElement) previewElement.style.display = 'block';
                hideError(errorId);
            }
        }
        
        // Setup file uploads for desktop
        setupFileUpload('fileUploadArea', 'id_verification', 'filePreview', 'fileName', 'id_verification_error');
        setupFileUpload('schoolFileUploadArea', 'school_id_verification', 'schoolFilePreview', 'schoolFileName', 'school_id_verification_error');
        setupFileUpload('guardianFileUploadArea', 'guardian_id_verification', 'guardianFilePreview', 'guardianFileName', 'guardian_id_verification_error');

        // Setup file uploads for mobile
        setupFileUpload('mobileFileUploadArea', 'mobile_id_verification', 'mobileFilePreview', 'mobileFileName', 'mobile_id_verification_error');
        setupFileUpload('mobileSchoolFileUploadArea', 'mobile_school_id_verification', 'mobileSchoolFilePreview', 'mobileSchoolFileName', 'mobile_school_id_verification_error');
        setupFileUpload('mobileGuardianFileUploadArea', 'mobile_guardian_id_verification', 'mobileGuardianFilePreview', 'mobileGuardianFileName', 'mobile_guardian_id_verification_error');
        
        // Real-time validation
        function validateField(fieldId, errorId, validationFn) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            field.addEventListener('blur', function() {
                validationFn(this.value, errorId);
            });
            
            // Validate on change for select elements
            if (field.tagName === 'SELECT') {
                field.addEventListener('change', function() {
                    validationFn(this.value, errorId);
                });
            }
        }
        
        function validateRequired(value, errorId, fieldName) {
            if (!value.trim()) {
                showError(errorId, `${fieldName} is required`);
                return false;
            } else {
                hideError(errorId);
                return true;
            }
        }
        
        function validateEmail(value, errorId) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showError(errorId, 'Please enter a valid email address');
                return false;
            } else {
                hideError(errorId);
                return true;
            }
        }
        
        function validatePhone(value, errorId) {
            const phoneRegex = /^\d{11}$/;
            if (!phoneRegex.test(value.replace(/\D/g, ''))) {
                showError(errorId, 'Please enter a valid 11-digit number');
                return false;
            } else {
                hideError(errorId);
                return true;
            }
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
        
        // Set up validation for all fields
        const validations = [
            // Desktop
            { field: 'first_name', error: 'first_name_error', fn: (v) => validateRequired(v, 'first_name_error', 'First Name') },
            { field: 'last_name', error: 'last_name_error', fn: (v) => validateRequired(v, 'last_name_error', 'Last Name') },
            { field: 'sex', error: 'sex_error', fn: (v) => validateRequired(v, 'sex_error', 'Sex') },
            { field: 'birthday', error: 'birthday_error', fn: (v) => validateRequired(v, 'birthday_error', 'Birthday') },
            { field: 'permanent_address', error: 'permanent_address_error', fn: (v) => validateRequired(v, 'permanent_address_error', 'Permanent Address') },
            { field: 'contact_number', error: 'contact_number_error', fn: (v) => validatePhone(v, 'contact_number_error') },
            { field: 'email', error: 'email_error', fn: (v) => validateEmail(v, 'email_error') },
            { field: 'username', error: 'username_error', fn: (v) => validateRequired(v, 'username_error', 'Username') },
            { field: 'password', error: 'password_error', fn: (v) => v.length >= 8 ? hideError('password_error') : showError('password_error', 'Password must be at least 8 characters') },
            { field: 'guardian_name', error: 'guardian_name_error', fn: (v) => validateRequired(v, 'guardian_name_error', 'Guardian Name') },
            { field: 'guardian_contact', error: 'guardian_contact_error', fn: (v) => validatePhone(v, 'guardian_contact_error') },
            
            // Mobile
            { field: 'mobile_first_name', error: 'mobile_first_name_error', fn: (v) => validateRequired(v, 'mobile_first_name_error', 'First Name') },
            { field: 'mobile_last_name', error: 'mobile_last_name_error', fn: (v) => validateRequired(v, 'mobile_last_name_error', 'Last Name') },
            { field: 'mobile_sex', error: 'mobile_sex_error', fn: (v) => validateRequired(v, 'mobile_sex_error', 'Sex') },
            { field: 'mobile_birthday', error: 'mobile_birthday_error', fn: (v) => validateRequired(v, 'mobile_birthday_error', 'Birthday') },
            { field: 'mobile_permanent_address', error: 'mobile_permanent_address_error', fn: (v) => validateRequired(v, 'mobile_permanent_address_error', 'Permanent Address') },
            { field: 'mobile_contact_number', error: 'mobile_contact_number_error', fn: (v) => validatePhone(v, 'mobile_contact_number_error') },
            { field: 'mobile_email', error: 'mobile_email_error', fn: (v) => validateEmail(v, 'mobile_email_error') },
            { field: 'mobile_username', error: 'mobile_username_error', fn: (v) => validateRequired(v, 'mobile_username_error', 'Username') },
            { field: 'mobile_password', error: 'mobile_password_error', fn: (v) => v.length >= 8 ? hideError('mobile_password_error') : showError('mobile_password_error', 'Password must be at least 8 characters') },
            { field: 'mobile_guardian_name', error: 'mobile_guardian_name_error', fn: (v) => validateRequired(v, 'mobile_guardian_name_error', 'Guardian Name') },
            { field: 'mobile_guardian_contact', error: 'mobile_guardian_contact_error', fn: (v) => validatePhone(v, 'mobile_guardian_contact_error') },
        ];
        
        validations.forEach(({ field, error, fn }) => {
            const element = document.getElementById(field);
            if (element) {
                element.addEventListener('blur', function() {
                    fn(this.value);
                });
            }
        });
        
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
                
                // Check age and validate ID uploads
                const ageInput = document.getElementById(isMobile ? 'mobile_age' : 'age');
                const age = parseInt(ageInput?.value || 0);
                const isMinor = age >= 15 && age <= 17;
                
                if (isMinor) {
                    // Validate guardian fields for minors
                    const guardianName = document.getElementById(isMobile ? 'mobile_guardian_name' : 'guardian_name')?.value || '';
                    const guardianContact = document.getElementById(isMobile ? 'mobile_guardian_contact' : 'guardian_contact')?.value || '';
                    
                    if (!guardianName.trim()) {
                        showError(isMobile ? 'mobile_guardian_name_error' : 'guardian_name_error', 'Guardian name is required for minors');
                        isValid = false;
                    }
                    
                    if (!guardianContact.trim() || !/^\d{11}$/.test(guardianContact.replace(/\D/g, ''))) {
                        showError(isMobile ? 'mobile_guardian_contact_error' : 'guardian_contact_error', 'Valid guardian contact is required for minors');
                        isValid = false;
                    }
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