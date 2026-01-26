
<?php
session_start();
header("Content-Type: application/json");

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

// Generate 6-digit OTP
$otp = rand(100000, 999999);

// Store OTP in session (temporary)
$_SESSION['otp'] = $otp;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_expire'] = time() + 120; // 2 minutes expiry

// Send OTP via email
$subject = "Your OTP Code";
$message = "Your OTP code is: $otp\nThis code will expire in 2 minutes.";
$headers = "From: noreply@example.com";

if (mail($email, $subject, $message, $headers)) {
    echo json_encode([
        "status" => "success",
        "message" => "OTP sent successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to send OTP"
    ]);
}
