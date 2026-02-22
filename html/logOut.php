<?php
// Start the session
session_start();
require 'connection.php'; // Make sure $conn is defined here

function columnExists($conn, $table, $column) {
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column"
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_SESSION['login_log_id'])) {
    $sql = "UPDATE login_logs SET logout_time = NOW() WHERE login_id = :log_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':log_id' => $_SESSION['login_log_id']]);
    
    // Get logout timestamp
    $logout_stmt = $conn->prepare("SELECT logout_time FROM login_logs WHERE login_id = :log_id");
    $logout_stmt->execute([':log_id' => $_SESSION['login_log_id']]);
    $logout_result = $logout_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update admin_activity_logs time_out for all activities in this session
    if ($logout_result && isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        $logout_time = $logout_result['logout_time'];
        
        // Update admin_activity_logs for admin activities
        if ($_SESSION['role'] === 'platform_admin' && columnExists($conn, 'admin_activity_logs', 'time_out')) {
            $update_activities = "UPDATE admin_activity_logs SET time_out = :logout_time WHERE user_id = :user_id AND time_out IS NULL AND timestamp >= (SELECT login_time FROM login_logs WHERE login_id = :log_id)";
            $update_stmt = $conn->prepare($update_activities);
            $update_stmt->execute([
                ':logout_time' => $logout_time,
                ':user_id' => $_SESSION['user_id'],
                ':log_id' => $_SESSION['login_log_id']
            ]);
        }
        
        // Update moderator_activity_logs for moderator activities
        if ($_SESSION['role'] === 'moderator' && columnExists($conn, 'moderator_activity_logs', 'time_out')) {
            $update_activities = "UPDATE moderator_activity_logs SET time_out = :logout_time WHERE user_id = :user_id AND time_out IS NULL AND timestamp >= (SELECT login_time FROM login_logs WHERE login_id = :log_id)";
            $update_stmt = $conn->prepare($update_activities);
            $update_stmt->execute([
                ':logout_time' => $logout_time,
                ':user_id' => $_SESSION['user_id'],
                ':log_id' => $_SESSION['login_log_id']
            ]);
        }
        
        // Update user_activity_logs for user activities
        if (($_SESSION['role'] === 'artist' || $_SESSION['role'] === 'collector') && columnExists($conn, 'user_activity_logs', 'time_out')) {
            $update_activities = "UPDATE user_activity_logs SET time_out = :logout_time WHERE user_id = :user_id AND time_out IS NULL AND timestamp >= (SELECT login_time FROM login_logs WHERE login_id = :log_id)";
            $update_stmt = $conn->prepare($update_activities);
            $update_stmt->execute([
                ':logout_time' => $logout_time,
                ':user_id' => $_SESSION['user_id'],
                ':log_id' => $_SESSION['login_log_id']
            ]);
        }
    }
}


// Destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit();
