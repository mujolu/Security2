<?php
include 'connection.php';
require_once 'email_helper.php';
require_once 'gmail_oauth2_helper.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

$response = ["success" => false, "message" => ""];

if (!$email) {
    $response["message"] = "Email required.";
    echo json_encode($response);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response["message"] = "Invalid email format.";
    echo json_encode($response);
    exit;
}

// 1️⃣ Check if user exists
$check = $conn->prepare("SELECT id FROM registered_users WHERE email=?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $response["message"] = "Email not registered.";
    echo json_encode($response);
    exit;
}

// 2️⃣ Generate OTP
$otp = rand(100000, 999999);
$hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
$expires = date("Y-m-d H:i:s", strtotime("+2 minutes"));

// 3️⃣ Save OTP
$stmt = $conn->prepare(
    "INSERT INTO password_resets (email, otp, expires_at)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE otp=?, expires_at=?"
);
$stmt->bind_param("sssss", $email, $hashedOtp, $expires, $hashedOtp, $expires);
$stmt->execute();

// 4️⃣ Send OTP via Gmail OAuth2 or email helper
$mailService = $_ENV['MAIL_SERVICE'] ?? 'mailtrap';
if ($mailService === 'gmail_oauth2') {
    $emailResult = sendOTPViaGmail($email, $otp, 2);
} else {
    $emailResult = sendOTPEmail($email, $otp, 2);
}

if ($emailResult['success']) {
    $response["success"] = true;
    $response["message"] = "OTP sent successfully to your email address.";
} else {
    $response["success"] = false;
    $response["message"] = $emailResult['message'];
}

echo json_encode($response);
