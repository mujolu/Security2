
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
$_SESSION['otp_expire'] = time() + 120; // 2 minutes expiry

// Send OTP via Gmail OAuth2 or email helper
$mailService = $_ENV['MAIL_SERVICE'] ?? 'mailtrap';
if ($mailService === 'gmail_oauth2') {
    $result = sendOTPViaGmail($email, $otp, 2);
} else {
    $result = sendOTPEmail($email, $otp, 2);
}

echo json_encode([
    "status" => $result['success'] ? "success" : "error",
    "message" => $result['message']
]);
