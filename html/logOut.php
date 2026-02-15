<?php
// Start the session
session_start();
require 'connection.php'; // Make sure $conn is defined here

if (isset($_SESSION['login_log_id'])) {
    $sql = "UPDATE login_logs SET logout_time = NOW() WHERE login_id = :log_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':log_id' => $_SESSION['login_log_id']]);
}


// Destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit();
