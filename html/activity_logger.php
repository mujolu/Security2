<?php
/**
 * Activity Logger Helper
 * 
 * This file provides functions to log user, moderator, and admin activities
 * with device tracking and time in/out timestamps.
 * 
 * Usage: logActivity($conn, $user_id, $activity, $table_name);
 */

/**
 * Detect device type from User Agent
 * 
 * @return string Device type (Mobile, Tablet, Desktop)
 */
function detectDevice() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $user_agent)) {
        if (preg_match('/Tablet|iPad/i', $user_agent)) {
            return 'Tablet';
        }
        return 'Mobile';
    }
    return 'Desktop';
}

/**
 * Detect operating system from User Agent
 *
 * @return string OS name
 */
function detectOS() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (strpos($user_agent, 'android') !== false) {
        return 'Android';
    }
    if (strpos($user_agent, 'iphone') !== false || strpos($user_agent, 'ipad') !== false || strpos($user_agent, 'ios') !== false) {
        return 'iOS';
    }
    if (strpos($user_agent, 'windows') !== false) {
        return 'Windows';
    }
    if (strpos($user_agent, 'macintosh') !== false || strpos($user_agent, 'mac os x') !== false) {
        return 'macOS';
    }
    if (strpos($user_agent, 'linux') !== false) {
        return 'Linux';
    }

    return 'Unknown';
}

/**
 * Get IP address (IPv4 only)
 * 
 * @return string IPv4 address or 127.0.0.1
 */
function getIPAddress() {
    $ip_address = '127.0.0.1';  // Default to localhost
    
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        // Validate it's IPv4
        if (filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
    }
    return $ip_address;
}

/**
 * Log an activity with device and time tracking
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id User ID
 * @param string $activity Activity description
 * @param string $table_name Table name (admin_activity_logs, moderator_activity_logs, user_activity_logs)
 * @param string|null $time_in Optional login time
 * @param string|null $time_out Optional logout time
 * @return bool True if successfully logged, false otherwise
 */
function logActivity($conn, $user_id, $activity, $table_name = 'admin_activity_logs', $time_in = null, $time_out = null) {
    try {
        $ip_address = getIPAddress();
        $device = detectDevice();
        $os = detectOS();
        
        $stmt = $conn->prepare("
            INSERT INTO $table_name (user_id, activity, ip_address, device, os, time_in, time_out) 
            VALUES (:user_id, :activity, :ip_address, :device, :os, :time_in, :time_out)
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->bindParam(':activity', $activity, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->bindParam(':device', $device, PDO::PARAM_STR);
        $stmt->bindParam(':os', $os, PDO::PARAM_STR);
        $stmt->bindParam(':time_in', $time_in, PDO::PARAM_STR);
        $stmt->bindParam(':time_out', $time_out, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        // Silently fail - don't interrupt user experience if logging fails
        return false;
    }
}

/**
 * Log a page view
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id User ID
 * @param string $page_name Name of the page being viewed
 * @param string $table_name Activity table name
 * @return bool True if successfully logged
 */
function logPageView($conn, $user_id, $page_name, $table_name = 'admin_activity_logs') {
    return logActivity($conn, $user_id, "Viewed $page_name", $table_name);
}

/**
 * Log an upload activity
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id User ID
 * @param string $file_type Type of file uploaded (e.g., 'image', 'artwork')
 * @param string $file_name Optional file name
 * @param string $table_name Activity table name
 * @return bool True if successfully logged
 */
function logUpload($conn, $user_id, $file_type, $file_name = '', $table_name = 'user_activity_logs') {
    $activity = "Uploaded $file_type";
    if (!empty($file_name)) {
        $activity .= ": " . substr($file_name, 0, 100);
    }
    return logActivity($conn, $user_id, $activity, $table_name);
}

/**
 * Log a marketplace activity
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id User ID
 * @param string $action Action performed (e.g., 'created', 'updated', 'deleted')
 * @param string $item_title Item title
 * @param string $table_name Activity table name
 * @return bool True if successfully logged
 */
function logMarketplaceActivity($conn, $user_id, $action, $item_title, $table_name = 'user_activity_logs') {
    $activity = "Marketplace $action: " . substr($item_title, 0, 100);
    return logActivity($conn, $user_id, $activity, $table_name);
}

/**
 * Log a moderation activity
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id Moderator user ID
 * @param string $action Moderation action
 * @param string $target_user Target user affected by the action
 * @param string $table_name Activity table name
 * @return bool True if successfully logged
 */
function logModerationActivity($conn, $user_id, $action, $target_user = '', $table_name = 'moderator_activity_logs') {
    $activity = "Moderation $action";
    if (!empty($target_user)) {
        $activity .= " on user: " . substr($target_user, 0, 100);
    }
    return logActivity($conn, $user_id, $activity, $table_name);
}

/**
 * Log admin activity with optional time tracking
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id Admin user ID
 * @param string $action Admin action
 * @param string $target Target of the action
 * @param string|null $time_in Optional login time
 * @param string|null $time_out Optional logout time
 * @return bool True if successfully logged
 */
function logAdminAction($conn, $user_id, $action, $target = '', $time_in = null, $time_out = null) {
    $activity = ucfirst($action);
    if (!empty($target)) {
        $activity .= ": " . substr($target, 0, 100);
    }
    return logActivity($conn, $user_id, $activity, 'admin_activity_logs', $time_in, $time_out);
}

/**
 * Log user activity with optional time tracking
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id User/Artist ID
 * @param string $action User action
 * @param string $details Action details
 * @param string|null $time_in Optional login time
 * @param string|null $time_out Optional logout time
 * @return bool True if successfully logged
 */
function logUserAction($conn, $user_id, $action, $details = '', $time_in = null, $time_out = null) {
    $activity = ucfirst($action);
    if (!empty($details)) {
        $activity .= ": " . substr($details, 0, 100);
    }
    return logActivity($conn, $user_id, $activity, 'user_activity_logs', $time_in, $time_out);
}

/**
 * Log moderator activity with optional time tracking
 * 
 * @param PDO $conn Database connection object
 * @param string $user_id Moderator ID
 * @param string $action Moderator action
 * @param string $details Action details
 * @param string|null $time_in Optional login time
 * @param string|null $time_out Optional logout time
 * @return bool True if successfully logged
 */
function logModeratorAction($conn, $user_id, $action, $details = '', $time_in = null, $time_out = null) {
    $activity = ucfirst($action);
    if (!empty($details)) {
        $activity .= ": " . substr($details, 0, 100);
    }
    return logActivity($conn, $user_id, $activity, 'moderator_activity_logs', $time_in, $time_out);
}
?>
