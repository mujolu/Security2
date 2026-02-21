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
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist with proper schema
    }
}

function logAdminActivity($conn, $actionType, $details = '') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'platform_admin') return;

    $user_id = $_SESSION['user_id'];
    // Convert IP to IPv4 only
    $ip = $_SERVER['REMOTE_ADDR'];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // If IPv6, try to extract IPv4 or use localhost equivalent
        $ip = '127.0.0.1';
    }
    $ip = substr($ip, 0, 15); // Truncate to IPv4 length for VARCHAR(15)

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
        $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $activity, $ip]);
    } catch (Exception $e) {
        // If insert fails, try creating table first then insert
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(9) NOT NULL,
                activity VARCHAR(500) NOT NULL,
                ip_address VARCHAR(15) NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Try insert again
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $activity, $ip]);
        } catch (Exception $e2) {
            // Silent fail - don't break the main operation
        }
    }
}

// Create table if it doesn't exist
createAdminActivityLogsTable($conn);

// Diagnostic check for the table
$table_exists = false;
$table_row_count = 0;
$error_msg = '';

try {
    // Verify table exists and has the right schema
    $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admin_activity_logs");
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $table_exists = true;
    $table_row_count = $result['cnt'] ?? 0;
} catch (Exception $e) {
    // Table doesn't exist or has wrong schema
    $table_exists = false;
    $error_msg = "Database table not ready. Attempting to recreate...";
    
    // Try to recreate the table
    try {
        $conn->exec("DROP TABLE IF EXISTS admin_activity_logs");
        $conn->exec("CREATE TABLE admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(9) NOT NULL,
            activity VARCHAR(500) NOT NULL,
            ip_address VARCHAR(15) NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $table_exists = true;
        $table_row_count = 0;
        $error_msg = ""; // Clear error after successful recreation
    } catch (Exception $e2) {
        $table_exists = false;
        $error_msg = "Error: Unable to create logging table. " . $e2->getMessage();
    }
}

// Log the page view
logAdminActivity($conn, 'view', 'Activity Logs Page');

// Fetch activity logs
$activity_logs = [];
try {
    // Query with error handling for schema issues
    if ($table_exists) {
        $stmt = $conn->prepare("SELECT al.id, ru.username, al.activity, al.ip_address, al.timestamp 
                                FROM admin_activity_logs al
                                JOIN registered_users ru ON al.user_id = ru.id
                                ORDER BY al.timestamp DESC
                                LIMIT 100");
        $stmt->execute();
        $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error_msg = 'Error fetching logs: ' . $e->getMessage() . ' (Schema issue detected. Trying to recreate table...)';
    
    // Try to fix the schema
    try {
        $conn->exec("DROP TABLE IF EXISTS admin_activity_logs");
        $conn->exec("CREATE TABLE admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(9) NOT NULL,
            activity VARCHAR(500) NOT NULL,
            ip_address VARCHAR(15) NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $error_msg = "Table schema was fixed. New logs will be recorded from now.";
        $table_exists = true;
        $table_row_count = 0;
    } catch (Exception $e2) {
        $error_msg = 'Schema error: ' . $e->getMessage();
    }
    
    $activity_logs = [];
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
                <strong>Status:</strong> 
                <?php echo $table_exists ? '✓ Logging table active' : '✗ Logging table not ready'; ?> | 
                <strong>Total logs:</strong> <?= $table_row_count ?>
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
                        <th class="p-3 text-left">Admin Username</th>
                        <th class="p-3 text-left">Activity</th>
                        <th class="p-3 text-left">IP Address</th>
                        <th class="p-3 text-left">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                    <tr class="border-b text-sm align-top hover:bg-gray-50">
                        <td class="p-3"><?= htmlspecialchars($log['username']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['activity']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                        <td class="p-3"><?= htmlspecialchars($log['timestamp']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 text-gray-600 text-sm">
                Showing <?= count($activity_logs) ?> of <?= $table_row_count ?> total logs
            </div>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 p-6 rounded text-center">
            <p class="text-gray-600 mb-4">
                <?php 
                if ($table_exists && $table_row_count == 0) {
                    echo 'No activity logs found yet. Activity logs will appear here when admin actions are performed.';
                } else {
                    echo 'Unable to retrieve activity logs. Please refresh the page or contact support.';
                }
                ?>
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
