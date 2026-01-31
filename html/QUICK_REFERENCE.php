<?php
/**
 * QUICK REFERENCE: Using PHPMailer in Your Application
 * 
 * This file shows common examples of how to send emails
 * using the EmailService class in your application.
 */

// ============================================
// EXAMPLE 1: Send OTP Email
// ============================================
/*
require_once 'html/email_helper.php';

$email = 'user@example.com';
$otp = rand(100000, 999999);

$result = sendOTPEmail($email, $otp, 2); // 2 minutes expiry

if ($result['success']) {
    echo "OTP sent successfully!";
} else {
    echo "Error: " . $result['message'];
}
*/

// ============================================
// EXAMPLE 2: Send Password Reset Email
// ============================================
/*
require_once 'html/email_helper.php';

$email = 'user@example.com';
$resetLink = 'https://yourdomain.com/reset-password.php?token=xyz123';

$result = sendPasswordResetEmail($email, $resetLink);

if ($result['success']) {
    echo "Password reset email sent!";
} else {
    echo "Error: " . $result['message'];
}
*/

// ============================================
// EXAMPLE 3: Send Welcome Email
// ============================================
/*
require_once 'html/email_helper.php';

$email = 'user@example.com';
$userName = 'John Doe';

$result = sendWelcomeEmail($email, $userName);

if ($result['success']) {
    echo "Welcome email sent!";
} else {
    echo "Error: " . $result['message'];
}
*/

// ============================================
// EXAMPLE 4: Using EmailService Class Directly
// ============================================
/*
require_once 'html/email_helper.php';

try {
    $emailService = new EmailService();
    
    $result = $emailService->sendOTP('user@example.com', '123456', 2);
    
    if ($result['success']) {
        // Success handling
    } else {
        // Error handling
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
*/

// ============================================
// EXAMPLE 5: Sending OTP in Registration Flow
// ============================================
/*
require_once 'html/email_helper.php';

// After user registration
$user_email = $_POST['email'];
$otp = rand(100000, 999999);

// Store OTP in database
$stmt = $conn->prepare("INSERT INTO otps (email, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $user_email, password_hash($otp, PASSWORD_DEFAULT), date("Y-m-d H:i:s", strtotime("+2 minutes")));
$stmt->execute();

// Send OTP email
$result = sendOTPEmail($user_email, $otp, 2);

if (!$result['success']) {
    die("Failed to send OTP: " . $result['message']);
}
*/

// ============================================
// EXAMPLE 6: Resend OTP with Rate Limiting
// ============================================
/*
require_once 'html/email_helper.php';

$email = $_POST['email'];
$current_time = time();

// Check if OTP was sent in last 30 seconds (prevent spam)
if (isset($_SESSION['last_otp_request'][$email]) && 
    ($current_time - $_SESSION['last_otp_request'][$email]) < 30) {
    die("Please wait before requesting OTP again");
}

// Generate and send OTP
$otp = rand(100000, 999999);
$result = sendOTPEmail($email, $otp, 2);

if ($result['success']) {
    $_SESSION['last_otp_request'][$email] = $current_time;
    echo "OTP sent successfully";
} else {
    echo "Error: " . $result['message'];
}
*/

// ============================================
// CONFIGURATION CHECKLIST
// ============================================
/*

Before using email functions, ensure:

1. ✅ .env file exists in project root with:
   - GMAIL_EMAIL=your_gmail@gmail.com
   - GMAIL_APP_PASSWORD=xxxx xxxx xxxx xxxx

2. ✅ Composer dependencies installed:
   - Run: composer install

3. ✅ Files exist:
   - html/email_config.php
   - html/email_helper.php
   - vendor/autoload.php

4. ✅ Gmail account:
   - 2FA enabled
   - App Password generated

5. ✅ PHP Configuration:
   - php_ini_loaded_file shows correct php.ini
   - No disabled functions affecting mail

6. ✅ Database table (for storing OTPs):
   CREATE TABLE password_resets (
       id INT PRIMARY KEY AUTO_INCREMENT,
       email VARCHAR(255),
       otp VARCHAR(255),
       expires_at DATETIME,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       UNIQUE KEY unique_email (email)
   );
*/

// ============================================
// DEBUGGING TIPS
// ============================================
/*

1. Check if .env is loaded:
   var_dump($_ENV);

2. Test email configuration:
   $config = EMAIL_CONFIG;
   var_dump($config);

3. Test PHPMailer connection:
   use PHPMailer\PHPMailer\PHPMailer;
   $mail = new PHPMailer(true);
   $mail->SMTPDebug = 2; // Enable debug output

4. Check error logs:
   tail -f /var/log/php-errors.log

5. Verify SMTP Settings:
   - Host: smtp.gmail.com
   - Port: 587 (TLS) or 465 (SSL)
   - Authentication: Required
   - App Password: 16-character password

6. Test SMTP connection:
   telnet smtp.gmail.com 587

7. Check firewall:
   Make sure port 587 is not blocked
*/

?>
