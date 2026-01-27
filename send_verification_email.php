<?php
// send_verification_email.php
function sendVerificationEmail($email, $first_name, $verification_token) {
    // Email configuration
    $to = $email;
    $subject = "Verify Your Email - Law Enforcement Incident Reporting";
    
    // Verification link (valid for 20 minutes)
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
    
    // HTML email content with similar design to login.php
    $message = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification - LEIR</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: "Poppins", sans-serif;
            }
            
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .email-header {
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
                padding: 40px;
                text-align: center;
                color: white;
            }
            
            .email-logo {
                width: 120px;
                height: 120px;
                margin: 0 auto 20px;
                display: block;
                filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5));
            }
            
            .email-header h1 {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .email-header p {
                opacity: 0.9;
                font-size: 16px;
            }
            
            .email-content {
                padding: 40px;
            }
            
            .email-content h2 {
                color: #2d3748;
                font-size: 24px;
                margin-bottom: 20px;
                font-weight: 600;
            }
            
            .email-content p {
                color: #4a5568;
                line-height: 1.6;
                margin-bottom: 20px;
                font-size: 16px;
            }
            
            .verification-btn {
                display: inline-block;
                background: linear-gradient(to right, #1e40af, #1d4ed8);
                color: white;
                padding: 16px 40px;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                font-size: 16px;
                margin: 30px 0;
                transition: all 0.3s;
            }
            
            .verification-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(29, 78, 216, 0.3);
            }
            
            .email-footer {
                padding: 30px 40px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                text-align: center;
                color: #718096;
                font-size: 14px;
            }
            
            .warning-note {
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
                color: #92400e;
                font-size: 14px;
            }
            
            .warning-note i {
                margin-right: 8px;
            }
            
            .expiry-info {
                color: #e53e3e;
                font-weight: 500;
                margin-top: 15px;
                font-size: 14px;
            }
            
            @media (max-width: 600px) {
                .email-header {
                    padding: 30px 20px;
                }
                
                .email-content {
                    padding: 30px 20px;
                }
                
                .email-footer {
                    padding: 20px;
                }
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <img src="cid:logo" alt="LEIR Logo" class="email-logo">
                <h1>Law Enforcement</h1>
                <p>Incident Reporting System</p>
            </div>
            
            <div class="email-content">
                <h2>Hello ' . htmlspecialchars($first_name) . ',</h2>
                
                <p>Thank you for registering with the Law Enforcement Incident Reporting System.</p>
                
                <p>To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                
                <div style="text-align: center;">
                    <a href="' . $verification_link . '" class="verification-btn">
                        <i class="fas fa-check-circle"></i> Verify Email Address
                    </a>
                </div>
                
                <div class="warning-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Important:</strong> If you did not create an account with LEIR, please ignore this email.
                </div>
                
                <p class="expiry-info">
                    <i class="fas fa-clock"></i> This verification link will expire in 20 minutes.
                </p>
                
                <p>Once verified, you can login to your account and start using our services.</p>
                
                <p style="margin-top: 30px;">
                    Best regards,<br>
                    <strong>LEIR Team</strong><br>
                    Law Enforcement Incident Reporting System
                </p>
            </div>
            
            <div class="email-footer">
                <p>© ' . date('Y') . ' Law Enforcement Incident Reporting System. All rights reserved.</p>
                <p>This is an automated message, please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: "Law Enforcement Incident Reporting" <lgulawenforcement@gmail.com>' . "\r\n";
    $headers .= 'Reply-To: lgulawenforcement@gmail.com' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();
    
    // Additional headers for better deliverability
    $headers .= "List-Unsubscribe: <mailto:lgulawenforcement@gmail.com?subject=Unsubscribe>" . "\r\n";
    $headers .= "X-Priority: 1 (Highest)" . "\r\n";
    $headers .= "X-MSMail-Priority: High" . "\r\n";
    $headers .= "Importance: High" . "\r\n";
    
    // Send email using SMTP configuration
    return mail($to, $subject, $message, $headers);
}

// Function to generate verification token
function generateVerificationToken() {
    return bin2hex(random_bytes(32)); // 64-character token
}
?>