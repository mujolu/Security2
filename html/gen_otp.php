<?php
include 'connection.php';
require_once 'email_helper.php';
require_once 'gmail_oauth2_helper.php';

$data = json_decode(file_get_contents("php://input"), true);
$username = isset($data['username']) ? trim($data['username']) : '';

$response = ["success" => false, "message" => ""];

if ($username === '') {
    $response["message"] = "Username required.";
    echo json_encode($response);
    exit;
}

// 1️⃣ Resolve registered email by username
$check = $conn->prepare("SELECT id, email FROM registered_users WHERE username=? LIMIT 1");
$check->bind_param("s", $username);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $response["message"] = "Username not found.";
    echo json_encode($response);
    exit;
}

$user = $result->fetch_assoc();
$email = $user['email'];

$localPart = strstr($email, '@', true);
$domainPart = strstr($email, '@');
if ($localPart === false || $domainPart === false) {
    $maskedEmail = $email;
} elseif (strlen($localPart) <= 2) {
    $maskedEmail = substr($localPart, 0, 1) . '*' . $domainPart;
} else {
    $maskedEmail = substr($localPart, 0, 2) . str_repeat('*', max(1, strlen($localPart) - 2)) . $domainPart;
}

// 2️⃣ Generate OTP
$otp = rand(100000, 999999);
$hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
$expires = date("Y-m-d H:i:s", time() + 60);

// 3️⃣ Save OTP
$deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE email=?");
$deleteStmt->bind_param("s", $email);
$deleteStmt->execute();

$stmt = $conn->prepare(
    "INSERT INTO password_resets (email, otp, expires_at)
    VALUES (?, ?, ?)"
);
$stmt->bind_param("sss", $email, $hashedOtp, $expires);
$stmt->execute();

// 4️⃣ Send OTP via configured email service
$mailService = EMAIL_CONFIG['service'] ?? ($_ENV['MAIL_SERVICE'] ?? 'gmail');
if ($mailService === 'gmail_oauth2') {
    $gmailResult = sendOTPViaGmail($email, $otp, 1);
    if ($gmailResult['success']) {
        $emailResult = $gmailResult;
    } else {
        $hasSmtpFallback = !empty(EMAIL_CONFIG['smtp_username']) && !empty(EMAIL_CONFIG['smtp_password']);
        if ($hasSmtpFallback) {
            $fallbackResult = sendOTPEmail($email, $otp, 1);
            if ($fallbackResult['success']) {
                $emailResult = [
                    'success' => true,
                    'message' => 'OTP sent successfully via SMTP fallback.'
                ];
            } else {
                $emailResult = [
                    'success' => false,
                    'message' => ($gmailResult['message'] ?? 'Gmail OAuth2 failed') . ' | SMTP fallback failed: ' . ($fallbackResult['message'] ?? 'Unknown error')
                ];
            }
        } else {
            $emailResult = [
                'success' => false,
                'message' => ($gmailResult['message'] ?? 'Gmail OAuth2 failed') . ' Please complete OAuth2 setup or add GMAIL_EMAIL and GMAIL_APP_PASSWORD for SMTP fallback.'
            ];
        }
    }
} else {
    $emailResult = sendOTPEmail($email, $otp, 1);
}

if ($emailResult['success']) {
    $response["success"] = true;
    $response["message"] = "OTP has been sent to your registered email: $email. Please check Inbox/Spam/All Mail.";
    $response["email"] = $email;
    error_log('OTP legacy flow sent: username=' . $username . ', email=' . $email . ', provider_message_id=' . ($emailResult['provider_message_id'] ?? 'n/a'));
} else {
    $response["success"] = false;
    $response["message"] = $emailResult['message'];
    error_log('OTP legacy flow failed: username=' . $username . ', email=' . $email . ', error=' . ($emailResult['message'] ?? 'Unknown'));
}

echo json_encode($response);
