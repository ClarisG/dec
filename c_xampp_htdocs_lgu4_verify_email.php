<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';
$message_type = 'error'; // 'error' or 'success'

// Determine base path for assets
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base_path === '/' || $base_path === '\\') $base_path = '';

if (isset($_GET['code']) && isset($_GET['email'])) {
    $database = new Database();
    $conn = $database->getConnection();

    $code = $_GET['code'];
    $email = sanitize_input($_GET['email']);

    try {
        // Find the user with the matching verification code and email, and check if the code is still valid
        $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email = :email AND verification_code = :code AND code_expiry > NOW()");
        $stmt->execute([':email' => $email, ':code' => $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['is_verified']) {
                $message = 'This account has already been verified. You can now log in.';
                $message_type = 'success';
            } else {
                // User found and not yet verified, so let's activate the account
                $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, code_expiry = NULL WHERE id = :id");
                if ($update_stmt->execute([':id' => $user['id']])) {
                    // Also, remove the code from the verification_codes table for cleanliness
                    $delete_stmt = $conn->prepare("DELETE FROM verification_codes WHERE email = :email");
                    $delete_stmt->execute([':email' => $email]);

                    $message = 'Your email has been successfully verified! You can now log in to your account.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update your account status. Please try again or contact support.';
                    $message_type = 'error';
                }
            }
        } else {
            // No user found. Check if the email exists but the code is wrong or expired.
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $message = 'This verification link is invalid or has expired. Please try logging in to receive a new link.';
            } else {
                $message = 'This verification link is invalid. Please check the link and try again.';
            }
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $message = 'An unexpected error occurred. Please contact support.';
        $message_type = 'error';
    }
} else {
    $message = 'Invalid verification request. No code or email provided.';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Health & Safety Inspection System</title>
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>/logo/logo.jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFF9C4;
            --primary-dark: #FFF59D;
            --secondary-dark: #FFEE58;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --error-border: #fecaca;
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, var(--secondary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .verification-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .verification-card img {
            width: 80px;
            height: auto;
            margin: 0 auto 20px;
        }
        .verification-card h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
        }
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }
        .message.error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }
        .login-button {
            display: inline-block;
            background: linear-gradient(135deg, #FFEE58 0%, #facc15 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="verification-card">
        <img src="<?php echo $base_path; ?>/logo/logo.jpeg" alt="Logo">
        <h1>Email Verification</h1>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php if ($message_type === 'success'): ?>
            <a href="main_login.php" class="login-button">Proceed to Login</a>
        <?php else: ?>
            <a href="register.php" class="login-button">Back to Registration</a>
        <?php endif; ?>
    </div>
</body>
</html>

```

### Key Features of this File:

*   **Secure Token Handling**: It correctly queries the database using the long, secure `verification_code` you are now generating.
*   **Expiration Check**: The database query `code_expiry > NOW()` ensures that expired links cannot be used.
*   **Clear User Feedback**: It provides distinct messages for different scenarios:
    *   Successful verification.
    *   An already-verified account.
    *   An invalid or expired link.
    *   A general system error.
*   **Database Cleanup**: Upon successful verification, it sets `is_verified` to `1` and also cleans up the `verification_code` and `code_expiry` fields in the `users` table to prevent reuse. It also removes the entry from the `verification_codes` table.
*   **Consistent UI**: The page has a simple, clean design that matches the aesthetic of your login and registration pages.

With this file in place, your email verification workflow will be robust and secure.

<!--
[PROMPT_SUGGESTION]Can you review my `reset_password.php` file for security and usability?[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]How can I add more advanced password strength rules to the registration form?[/PROMPT_SUGGESTION]
-->