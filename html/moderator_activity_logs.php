<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Moderator';
$user_id = $_SESSION['user_id'];

// Ensure moderator activity logs table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS moderator_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(9) NOT NULL,
        activity VARCHAR(500) NOT NULL,
        ip_address VARCHAR(15) NULL,
        device VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        time_in TIMESTAMP NULL,
        time_out TIMESTAMP NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES moderators(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

$time_in = null;
if (isset($_SESSION['login_log_id'])) {
    try {
        $login_stmt = $conn->prepare("SELECT login_time FROM login_logs WHERE login_id = ?");
        $login_stmt->execute([$_SESSION['login_log_id']]);
        $login_result = $login_stmt->fetch(PDO::FETCH_ASSOC);
        if ($login_result) {
            $time_in = $login_result['login_time'];
        }
    } catch (Exception $e) {
        // Silent fail - continue without login time
    }
}

// Log page view to moderator activity logs
logActivity($conn, $user_id, 'Viewed Activity Logs', 'moderator_activity_logs', $time_in, null);

// Fetch moderator's login logs from login_logs table
try {
    $stmt = $conn->prepare("
        SELECT ip_address, device, os, username, login_time, logout_time 
        FROM login_logs 
        WHERE user_id = :user_id 
        ORDER BY login_time DESC 
        LIMIT 100
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>My Activity Logs - Moderator</title>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gray-800 text-white p-6 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-2xl font-bold">ARTLAB Moderator</h1>
            <p class="text-gray-300 text-sm">Activity Logs</p>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-gray-200"><?php echo htmlspecialchars($username); ?></span>
            <a href="logOut.php" class="bg-yellow-600 px-4 py-2 rounded hover:bg-yellow-700 transition">Logout</a>
        </div>
    </header>

    <div class="flex min-h-screen">
        <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
            <h2 class="text-2xl font-bold text-white mb-5">ARTLAB MOD</h2>

            <div class="flex flex-col items-center text-center mt-8">
                <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4 object-cover">
                <h4 class="text-white font-semibold"><?php echo htmlspecialchars($username); ?></h4>
                <p class="text-gray-400 text-sm">Moderator</p>
            </div>

            <nav class="flex flex-col gap-4 mt-8">
                <a href="moderator_dashboard.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">User Management</a>
                <a href="#" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Content Review</a>
                <a href="#" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Reports</a>
                <a href="#" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">User Flags</a>
                <a href="moderator_activity_logs.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">My Activity Logs</a>
            </nav>
        </aside>

        <main class="flex-1 p-10 bg-white">
            <div class="mb-10">
                <h1 class="text-3xl font-semibold text-gray-800">My Activity Logs</h1>
                <p class="text-gray-500 mt-1">View your login history and session information</p>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                <?php if (!empty($logs)): ?>
                    <table class="w-full">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left font-semibold">Device</th>
                                <th class="px-6 py-4 text-left font-semibold">OS</th>
                                <th class="px-6 py-4 text-left font-semibold">IP Address</th>
                                <th class="px-6 py-4 text-left font-semibold">Login Time</th>
                                <th class="px-6 py-4 text-left font-semibold">Logout Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-gray-800">
                                        <span class="inline-block px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-sm font-medium">
                                            <?php echo htmlspecialchars($log['device'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($log['os'] ?? 'Unknown'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 font-mono text-sm">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($log['login_time']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if (!empty($log['logout_time'])): ?>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($log['logout_time']); ?></span>
                                        <?php else: ?>
                                            <span class="text-green-600 font-semibold">Currently logged in</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="px-6 py-4 bg-gray-50 text-gray-600 text-sm border-t">
                        Showing <?php echo count($logs); ?> recent login sessions (up to 100)
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <p class="text-lg font-semibold">No login activity found</p>
                        <p class="text-sm">Your login sessions will be recorded here</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
