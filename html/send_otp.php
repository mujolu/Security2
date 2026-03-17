
<?php
session_start();
header("Content-Type: application/json");

// Include email helpers
require_once 'email_helper.php';
require_once 'gmail_oauth2_helper.php';

// Read JSON input from fetch
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Email is required"
    ]);
    exit;
}

$email = $data['email'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format"
    ]);
    exit;
}

// Generate 6-digit OTP
$otp = rand(100000, 999999);

// Store OTP in session (temporary)
$_SESSION['otp'] = $otp;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_expire'] = time() + 60; // 60 seconds expiry

// Send OTP via Gmail OAuth2 or email helper
$mailService = EMAIL_CONFIG['service'] ?? ($_ENV['MAIL_SERVICE'] ?? 'gmail');

if ($mailService === 'gmail_oauth2') {
    $gmailResult = sendOTPViaGmail($email, $otp, 1);

    if ($gmailResult['success']) {
        $result = $gmailResult;
    } else {
        error_log('OTP Gmail OAuth2 send failed: ' . ($gmailResult['message'] ?? 'Unknown error'));

        // Fallback to SMTP/email helper only when SMTP credentials are configured
        $hasSmtpFallback = !empty(EMAIL_CONFIG['smtp_username']) && !empty(EMAIL_CONFIG['smtp_password']);

        if ($hasSmtpFallback) {
            $fallbackResult = sendOTPEmail($email, $otp, 1);

            if ($fallbackResult['success']) {
                $result = [
                    'success' => true,
                    'message' => 'OTP sent successfully via SMTP fallback.'
                ];
            } else {
                error_log('OTP SMTP fallback failed: ' . ($fallbackResult['message'] ?? 'Unknown error'));
                $result = [
                    'success' => false,
                    'message' => ($gmailResult['message'] ?? 'Gmail OAuth2 failed') . ' | SMTP fallback failed: ' . ($fallbackResult['message'] ?? 'Unknown error')
                ];
            }
        } else {
            $result = [
                'success' => false,
                'message' => ($gmailResult['message'] ?? 'Gmail OAuth2 failed') . ' Please complete OAuth2 setup or add GMAIL_EMAIL and GMAIL_APP_PASSWORD for SMTP fallback.'
            ];
        }
    }
} else {
    error_log('OTP send using SMTP service: ' . $mailService);
    $result = sendOTPEmail($email, $otp, 1);
}

echo json_encode([
    "status" => $result['success'] ? "success" : "error",
    "message" => $result['message']
]);
