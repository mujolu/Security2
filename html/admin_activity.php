<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$username = $_SESSION['username'] ?? 'platform admin';

function createAdminActivityLogsTable($conn) {
    try {
        // Create table with correct schema - IPv4 only (VARCHAR 15)
        // Use IF NOT EXISTS so we don't drop existing logs
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(9) NOT NULL,
            activity VARCHAR(500) NOT NULL,
            ip_address VARCHAR(15) NULL,
            device VARCHAR(50) NULL,
            os VARCHAR(50) NULL,
            time_in TIMESTAMP NULL,
            time_out TIMESTAMP NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist with proper schema
    }
}

function logAdminActivity($conn, $actionType, $details = '', $time_in = null, $time_out = null) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'platform_admin') return;

    $user_id = $_SESSION['user_id'];
    // Convert IP to IPv4 only
    $ip = $_SERVER['REMOTE_ADDR'];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // If IPv6, try to extract IPv4 or use localhost equivalent
        $ip = '127.0.0.1';
    }
    $ip = substr($ip, 0, 15); // Truncate to IPv4 length for VARCHAR(15)

    // Detect device from User Agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $user_agent)) {
        $device = preg_match('/Tablet|iPad/i', $user_agent) ? 'Tablet' : 'Mobile';
    } else {
        $device = 'Desktop';
    }

    $user_agent_lower = strtolower($user_agent);
    if (strpos($user_agent_lower, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($user_agent_lower, 'iphone') !== false || strpos($user_agent_lower, 'ipad') !== false || strpos($user_agent_lower, 'ios') !== false) {
        $os = 'iOS';
    } elseif (strpos($user_agent_lower, 'windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($user_agent_lower, 'macintosh') !== false || strpos($user_agent_lower, 'mac os x') !== false) {
        $os = 'macOS';
    } elseif (strpos($user_agent_lower, 'linux') !== false) {
        $os = 'Linux';
    } else {
        $os = 'Unknown';
    }

    // Get login_time from current login session if not provided
    if ($time_in === null && isset($_SESSION['login_log_id'])) {
        try {
            $login_stmt = $conn->prepare("SELECT login_time FROM login_logs WHERE login_id = ?");
            $login_stmt->execute([$_SESSION['login_log_id']]);
            $login_result = $login_stmt->fetch(PDO::FETCH_ASSOC);
            if ($login_result) {
                $time_in = $login_result['login_time'];
            }
        } catch (Exception $e) {
            // Silent fail - just continue without login time
        }
    }

    // Build dynamic activity message
    $activity = match($actionType) {
        'view' => "Viewed page: $details",
        'delete_user' => "Deleted user ID: $details",
        'delete_moderator' => "Deleted moderator ID: $details",
        'add_moderator' => "Added moderator: $details",
        'approve_artwork' => "Approved artwork ID: $details",
        'edit_user' => "Edited user ID: $details",
        'ban_user' => "Banned user ID: $details",
        'logout' => "Logged out",
        default => $details
    };

    try {
        // Just insert the activity log - table should already exist from createAdminActivityLogsTable()
        $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $activity, $ip, $device, $os, $time_in, $time_out]);
    } catch (Exception $e) {
        // If insert fails, try creating table first then insert
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(9) NOT NULL,
                activity VARCHAR(500) NOT NULL,
                ip_address VARCHAR(15) NULL,
                device VARCHAR(50) NULL,
                os VARCHAR(50) NULL,
                time_in TIMESTAMP NULL,
                time_out TIMESTAMP NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Try insert again
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $activity, $ip, $device, $os, $time_in, $time_out]);
        } catch (Exception $e2) {
            // Silent fail - don't break the main operation
        }
    }
}

// Create table if it doesn't exist
createAdminActivityLogsTable($conn);

// Fetch admin's activity logs (view, delete, alter actions)
$activity_logs = [];
$error_msg = '';

try {
    $stmt = $conn->prepare("
        SELECT activity, device, os, ip_address, time_in, time_out, timestamp 
        FROM admin_activity_logs 
        WHERE user_id = :user_id 
        ORDER BY timestamp DESC 
        LIMIT 100
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_logs = [];
    $error_msg = 'Error fetching activity logs: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Activity Logs - Artlab Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #515151; }
        header { background-color: #333; color: white; padding: 10px 0; display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-radius:12px; }
        .logout-btn { padding: 10px 20px; background-color:darkgoldenrod; color:white; text-decoration:none; border-radius:12px; }
        .logout-btn:hover { background-color:gray; }
        .layout { display:flex; min-height:100vh; }
        .sidebar { width:220px; background-color:#333; padding:20px; }
        .logo { font-size:20px; margin-bottom:30px; letter-spacing:2px; }
        .main-content { flex:1; padding:30px; background-color:#ffffff; }
        .brand-sketchy { font-family: 'Permanent Marker', cursive; color: #ff8614; font-weight:500; font-size:30px; letter-spacing:2px; }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<div class="layout flex w-full min-h-screen">

<aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB ADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
         <h4 class="text-white font-semibold"><?php echo htmlspecialchars($username); ?></h4>
        <p class="text-gray-400 text-sm">Platform Admin</p>
    </div>

    <nav class="flex flex-col gap-4 mt-8">
        <a href="admin_dashboard.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_flag_review.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Flag Audit</a>
        <a href="admin_deploy.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Deploy Moderators</a>
        <a href="admin_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Marketplace Art </a>
        <a href="admin_collab.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">Activity Logs</a>
    </nav>
</aside>

<main class="flex-1 p-6">
    <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
        <h3 class="text-xl font-bold">Activity Logs</h3>
        <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
    </header>

    <section class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold">Activity Logs</h2>
            <button onclick="location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Refresh</button>
        </div>
        
        <!-- Status Indicator -->
        <div class="mb-4 p-3 rounded bg-blue-50 border-l-4 border-blue-400">
            <p class="text-sm text-blue-800">
                <strong>Status:</strong> ✓ Activity tracking active | 
                <strong>Total activities:</strong> <?= count($activity_logs) ?>
            </p>
        </div>
        
        <?php if (!empty($error_msg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <p><?= htmlspecialchars($error_msg) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($activity_logs)): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-3 text-left">Activity</th>
                        <th class="p-3 text-left">Device</th>
                        <th class="p-3 text-left">OS</th>
                        <th class="p-3 text-left">IP Address</th>
                        <th class="p-3 text-left">Time In</th>
                        <th class="p-3 text-left">Time Out</th>
                        <th class="p-3 text-left">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                    <tr class="border-b text-sm align-top hover:bg-gray-50">
                        <td class="p-3"><?= htmlspecialchars($log['activity']) ?></td>
                        <td class="p-3">
                            <span class="inline-block px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-medium">
                                <?= htmlspecialchars($log['device'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td class="p-3"><?= htmlspecialchars($log['os'] ?? 'Unknown') ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['time_in'] ?? '-') ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['time_out'] ?? '-') ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['timestamp']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 text-gray-600 text-sm">
                Showing <?= count($activity_logs) ?> activities
            </div>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 p-6 rounded text-center">
            <p class="text-gray-600 mb-4">
                No admin activities logged yet. Your activities (view, delete, edit) will appear here.
            </p>
            <button onclick="location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Check Again</button>
        </div>
        <?php endif; ?>
    </section>

</main>
</div>

<script>
if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function () { window.history.pushState(null, null, window.location.href); };
}
</script>

</body>
</html>
