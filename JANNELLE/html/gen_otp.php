<?php
include 'connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

$response = ["success" => false, "message" => ""];

if (!$email) {
    $response["message"] = "Email required.";
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

// TEMP: return OTP (REMOVE IN PRODUCTION)
$response["success"] = true;
$response["message"] = "OTP sent successfully. OTP: $otp";

echo json_encode($response);
