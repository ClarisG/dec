<?php
// phpmailer_config.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendVerificationEmailPHPMailer($email, $first_name, $verification_token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lgulawenforcement@gmail.com';
        $mail->Password   = 'lgu4pass123.';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('lgulawenforcement@gmail.com', 'LEIR System');
        $mail->addAddress($email, $first_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Law Enforcement Incident Reporting';
        
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                /* Same email styles as above */
            </style>
        </head>
        <body>
            <!-- Same email HTML as above -->
        </body>
        </html>';
        
        $mail->AltBody = "Hello $first_name,\n\nThank you for registering with LEIR. Please verify your email by clicking this link: $verification_link\n\nThis link expires in 20 minutes.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>