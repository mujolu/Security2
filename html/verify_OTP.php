<?php
include 'connection.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$otp = $data['otp'] ?? '';

$response = ["success" => false, "message" => ""];

$stmt = $conn->prepare(
    "SELECT otp, expires_at FROM password_resets WHERE email=? ORDER BY expires_at DESC LIMIT 1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (strtotime($row['expires_at']) < time()) {
        $response["message"] = "OTP expired. Please request a new OTP.";
    } elseif (password_verify($otp, $row['otp'])) {
        $_SESSION['reset_email'] = $email;
        $response["success"] = true;
        $response["message"] = "OTP verified.";
    } else {
        $response["message"] = "Invalid OTP.";
    }
} else {
    $response["message"] = "OTP not found.";
}

echo json_encode($response);
