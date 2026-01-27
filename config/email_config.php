<?php
// Email Configuration for LEIR System
// For Gmail SMTP

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'lgulawenforcement@gmail.com');
define('SMTP_PASSWORD', 'your-app-specific-password'); // Use app-specific password
define('SMTP_FROM_EMAIL', 'lgulawenforcement@gmail.com');
define('SMTP_FROM_NAME', 'LEIR System');
define('SMTP_SECURE', 'tls'); // tls or ssl

// Password reset settings
define('RESET_TOKEN_EXPIRY', '1 hour'); // Token expiry time
define('BASE_URL', 'http://localhost/dec'); // Change to your actual URL

// Function to send email
function sendPasswordResetEmail($toEmail, $toName, $resetToken) {
    try {
        // Load PHPMailer
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Enable debugging if needed
        // $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LEIR System';
        
        $resetLink = BASE_URL . '/reset_password.php?token=' . $resetToken;
        
        // Email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #1e3a8a; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; padding: 12px 24px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>LEIR System</h2>
                    <p>Law Enforcement and Incident Reporting</p>
                </div>
                <div class='content'>
                    <h3>Password Reset Request</h3>
                    <p>Hello $toName,</p>
                    <p>We received a request to reset your password for your LEIR account. If you made this request, please click the button below to reset your password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$resetLink' class='button'>Reset My Password</a>
                    </div>
                    
                    <p>Or copy and paste this link into your browser:</p>
                    <p><code>$resetLink</code></p>
                    
                    <div class='warning'>
                        <p><strong>Important:</strong> This password reset link will expire in 1 hour.</p>
                    </div>
                    
                    <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <p>Best regards,<br>
                    LEIR System Administrator</p>
                    
                    <div class='footer'>
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>© " . date('Y') . " LEIR System. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text alternative
        $mail->AltBody = "Password Reset Request\n\nHello $toName,\n\nWe received a request to reset your password for your LEIR account. Please use this link to reset your password:\n\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you didn't request a password reset, please ignore this email.\n\nBest regards,\nLEIR System Administrator";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}