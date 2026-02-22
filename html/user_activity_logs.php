<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];

// Fetch user's login logs from login_logs table
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

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>My Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <header class="p-4 bg-gray-800 text-white flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="text-lg font-bold">ARTLAB</div>
            <div class="text-sm text-gray-200">Activity Logs</div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-sm text-gray-200">Logged in as <?php echo htmlspecialchars($username); ?></div>
            <a href="logOut.php" class="bg-yellow-600 px-3 py-1 rounded hover:bg-yellow-700">Logout</a>
        </div>
    </header>

    <div class="flex">
        <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
            <h2 class="text-2xl font-bold text-white mb-5">ARTLAB</h2>

            <div class="flex flex-col items-center text-center mt-8">
                <img src="/Security2/images/profilepic.jpg" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-yellow-500 shadow-md mb-4">
                <h4 class="text-white font-semibold mb-3">Welcome, <?php echo htmlspecialchars($username); ?>!</h4>
            </div>

            <nav class="flex flex-col gap-4 mt-8">
                <a href="user_artwork.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Artwork</a>
                <a href="user_collaborations.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Collaborations</a>
                <a href="user_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Marketplace</a>
                <a href="user_activity_logs.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">My Activity Logs</a>
                <a href="user_settings.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Settings</a>
            </nav>
        </aside>

        <main class="flex-1 p-10 bg-gray-100 min-h-screen">
            <div class="mb-10">
                <h1 class="text-3xl font-semibold text-gray-800">My Activity Logs</h1>
                <p class="text-gray-500 mt-1">View your login history and session information</p>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
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
                                        <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
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
                    <div class="px-6 py-4 bg-gray-50 text-gray-600 text-sm">
                        Showing <?php echo count($logs); ?> recent login sessions (up to 100)
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <p class="text-lg">No login activity found</p>
                        <p class="text-sm">Your login sessions will be recorded here</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
