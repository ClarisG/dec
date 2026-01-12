<?php
// config/mail.php - Email Configuration

// SMTP Configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'lgu4lawenforcement@gmail.com');
define('MAIL_PASSWORD', 'lgu4pass11.');
define('MAIL_FROM_EMAIL', 'no-reply@leir-system.com');
define('MAIL_FROM_NAME', 'LEIR System');
define('MAIL_SMTP_SECURE', 'tls');
define('MAIL_SMTP_AUTH', true);
define('MAIL_DEBUG', 0);

// Email verification settings
define('EMAIL_VERIFICATION_ENABLED', true);
define('EMAIL_VERIFICATION_EXPIRY', 24 * 60 * 60); // 24 hours in seconds

// Password reset settings
define('PASSWORD_RESET_EXPIRY', 60 * 60); // 1 hour in seconds
?>