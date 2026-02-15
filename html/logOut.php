<?php
// Start the session
session_start();

if (isset($_SESSION['log_id'])) {

    $sql = "UPDATE login_logs 
            SET logout_time = NOW() 
            WHERE login_id = :log_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':log_id' => $_SESSION['log_id']
    ]);
}

// Destroy the session to log out the user
session_unset();
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>
