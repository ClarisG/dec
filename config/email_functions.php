<?php
// config/email_functions.php

require_once 'mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = MAIL_DEBUG;
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = MAIL_SMTP_AUTH;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_PORT;
        
        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if (!$isHTML) {
            $mail->AltBody = strip_tags($body);
        }
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send registration confirmation email
 */
function sendRegistrationEmail($email, $name, $verificationLink) {
    $subject = "Account Registration Confirmation - LEIR System";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .button { 
                display: inline-block; 
                padding: 12px 24px; 
                background: #1e3a8a; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #ddd; 
                text-align: center; 
                color: #666; 
                font-size: 12px; 
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>LEIR System Registration</h2>
            </div>
            <div class="content">
                <h3>Dear ' . htmlspecialchars($name) . ',</h3>
                <p>Thank you for registering with the Law Enforcement Incident Reporting System!</p>
                <p>Your account has been created successfully. Please click the button below to confirm your email address:</p>
                
                <p style="text-align: center;">
                    <a href="' . $verificationLink . '" class="button">Confirm Email Address</a>
                </p>
                
                <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                <p style="word-break: break-all; background: #eee; padding: 10px; border-radius: 5px;">
                    ' . $verificationLink . '
                </p>
                
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create this account, please ignore this email.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from LEIR System. Please do not reply to this email.</p>
                <p>© ' . date('Y') . ' Law Enforcement Incident Reporting System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $resetLink) {
    $subject = "Password Reset Request - LEIR System";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .button { 
                display: inline-block; 
                padding: 12px 24px; 
                background: #dc2626; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .warning { 
                background: #fff3cd; 
                border: 1px solid #ffc107; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #ddd; 
                text-align: center; 
                color: #666; 
                font-size: 12px; 
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Password Reset Request</h2>
            </div>
            <div class="content">
                <h3>Dear ' . htmlspecialchars($name) . ',</h3>
                <p>We received a request to reset your password for your LEIR System account.</p>
                
                <div class="warning">
                    <strong>Important:</strong> If you did not request a password reset, please ignore this email and ensure your account is secure.
                </div>
                
                <p>To reset your password, click the button below:</p>
                
                <p style="text-align: center;">
                    <a href="' . $resetLink . '" class="button">Reset Password</a>
                </p>
                
                <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                <p style="word-break: break-all; background: #eee; padding: 10px; border-radius: 5px;">
                    ' . $resetLink . '
                </p>
                
                <p><strong>This link will expire in 1 hour for security reasons.</strong></p>
                <p>After resetting, you can log in with your new password.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from LEIR System. Please do not reply to this email.</p>
                <p>© ' . date('Y') . ' Law Enforcement Incident Reporting System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send account verification success email
 */
function sendVerificationSuccessEmail($email, $name) {
    $subject = "Account Verified Successfully - LEIR System";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #059669; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .button { 
                display: inline-block; 
                padding: 12px 24px; 
                background: #059669; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #ddd; 
                text-align: center; 
                color: #666; 
                font-size: 12px; 
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Account Verified!</h2>
            </div>
            <div class="content">
                <h3>Dear ' . htmlspecialchars($name) . ',</h3>
                <p>Congratulations! Your LEIR System account has been successfully verified.</p>
                
                <p>You can now access all features of the system by logging in:</p>
                
                <p style="text-align: center;">
                    <a href="' . BASE_URL . '/login.php" class="button">Login to Your Account</a>
                </p>
                
                <p>Features you can now access:</p>
                <ul>
                    <li>Report incidents</li>
                    <li>Track your reports</li>
                    <li>View announcements</li>
                    <li>Update your profile</li>
                </ul>
                
                <p>If you have any questions, please contact your barangay administration.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from LEIR System. Please do not reply to this email.</p>
                <p>© ' . date('Y') . ' Law Enforcement Incident Reporting System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmail($email, $subject, $body);
}
?>