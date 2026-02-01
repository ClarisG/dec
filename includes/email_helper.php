<?php
// includes/email_helper.php - Email Helper Functions

// Load PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email_config.php';

/**
 * Send Email Notification
 * Generic function to send emails using PHPMailer
 * 
 * @param string $toEmail - Recipient email address
 * @param string $toName - Recipient name
 * @param string $subject - Email subject
 * @param string $htmlBody - HTML email body
 * @param string $plainTextBody - Plain text alternative (optional)
 * @return bool - True if sent successfully, false otherwise
 */
function sendEmailNotification($toEmail, $toName, $subject, $htmlBody, $plainTextBody = null) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        
        // Plain text alternative
        if ($plainTextBody) {
            $mail->AltBody = $plainTextBody;
        } else {
            // Generate plain text from HTML if not provided
            $mail->AltBody = strip_tags($htmlBody);
        }
        
        // Send email
        $mail->send();
        
        // Log successful send
        error_log("Email sent successfully to: $toEmail");
        
        return true;
        
    } catch (Exception $e) {
        // Log error
        error_log("Email sending failed to $toEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Classification Update Email
 * Specialized function for classification update notifications
 * 
 * @param string $toEmail - Recipient email
 * @param string $toName - Recipient name
 * @param int $reportId - Report ID
 * @param string $reportNumber - Report number
 * @param string $newClassification - New classification (barangay/police)
 * @param string $category - Report category
 * @param string $severity - Severity level
 * @param string $priority - Priority level
 * @param string $reason - Reason for change
 * @return bool - True if sent successfully
 */
function sendClassificationUpdateEmail($toEmail, $toName, $reportId, $reportNumber, $newClassification, $category, $severity, $priority, $reason) {
    $newJurisdiction = $newClassification == 'barangay' ? 'Barangay Matter' : 'Police Matter';
    
    $subject = "Report Classification Update - Report #$reportNumber";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1e3a8a; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .details { background-color: white; padding: 15px; border-left: 4px solid #2196f3; margin: 15px 0; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            .badge { display: inline-block; padding: 5px 10px; background-color: #e3f2fd; color: #1976d2; border-radius: 3px; margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LEIR System</h2>
                <p>Law Enforcement and Incident Reporting</p>
            </div>
            <div class='content'>
                <h3>Report Classification Update</h3>
                <p>Dear $toName,</p>
                <p>Your report has been reviewed and updated by our secretary.</p>
                
                <div class='details'>
                    <h4>Report Details:</h4>
                    <p><strong>Report ID:</strong> #$reportNumber</p>
                    <p><strong>New Classification:</strong> <span class='badge'>$newJurisdiction</span></p>
                    <p><strong>Report Category:</strong> " . htmlspecialchars(ucfirst($category)) . "</p>
                    <p><strong>Severity Level:</strong> <span class='badge'>" . htmlspecialchars(ucfirst($severity)) . "</span></p>
                    <p><strong>Priority:</strong> <span class='badge'>" . htmlspecialchars(ucfirst($priority)) . "</span></p>
                </div>
                
                <div class='details'>
                    <h4>Reason for Change:</h4>
                    <p>" . htmlspecialchars($reason) . "</p>
                </div>
                
                <p><strong>Next Steps:</strong> The report has been moved to the appropriate department for processing. You can view the updated report details by clicking the notification in your dashboard or visiting your reports page.</p>
                
                <p>Thank you for using our reporting system.</p>
                
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>© " . date('Y') . " LEIR System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plainTextBody = "Report Classification Update\n\n" .
                     "Dear $toName,\n\n" .
                     "Your report has been reviewed and updated by our secretary.\n\n" .
                     "Report Details:\n" .
                     "Report ID: #$reportNumber\n" .
                     "New Classification: $newJurisdiction\n" .
                     "Report Category: " . ucfirst($category) . "\n" .
                     "Severity Level: " . ucfirst($severity) . "\n" .
                     "Priority: " . ucfirst($priority) . "\n\n" .
                     "Reason for Change:\n" .
                     "$reason\n\n" .
                     "Next Steps: The report has been moved to the appropriate department for processing.\n\n" .
                     "Thank you for using our reporting system.\n\n" .
                     "© " . date('Y') . " LEIR System. All rights reserved.";
    
    return sendEmailNotification($toEmail, $toName, $subject, $htmlBody, $plainTextBody);
}

/**
 * Send Generic Notification Email
 * For any generic notification
 * 
 * @param string $toEmail - Recipient email
 * @param string $toName - Recipient name
 * @param string $subject - Email subject
 * @param string $title - Email title/heading
 * @param string $message - Email message
 * @param array $details - Additional details (key => value pairs)
 * @return bool - True if sent successfully
 */
function sendGenericNotificationEmail($toEmail, $toName, $subject, $title, $message, $details = []) {
    $detailsHtml = '';
    if (!empty($details)) {
        $detailsHtml = '<div class="details"><h4>Details:</h4>';
        foreach ($details as $key => $value) {
            $detailsHtml .= '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
        }
        $detailsHtml .= '</div>';
    }
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1e3a8a; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .details { background-color: white; padding: 15px; border-left: 4px solid #2196f3; margin: 15px 0; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LEIR System</h2>
                <p>Law Enforcement and Incident Reporting</p>
            </div>
            <div class='content'>
                <h3>$title</h3>
                <p>Dear $toName,</p>
                <p>$message</p>
                $detailsHtml
                <p>Thank you for using our reporting system.</p>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>© " . date('Y') . " LEIR System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmailNotification($toEmail, $toName, $subject, $htmlBody);
}
?>
