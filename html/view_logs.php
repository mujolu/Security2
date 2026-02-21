<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    echo "<p class='text-red-600'>Access denied.</p>";
    exit();
}

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    echo "<p class='text-red-600'>Invalid user selected.</p>";
    return;
}

// Fetch logs
$sql = "SELECT * FROM login_logs WHERE user_id = ? ORDER BY login_time DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch username
$user_stmt = $conn->prepare("SELECT username FROM registered_users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
$username = $user_row['username'] ?? "Unknown User";
?>

<!DOCTYPE html>
<html>
<head>
<title>User Logs</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<!-- 🔥 HEADER (same as dashboard) -->
<header class="bg-gray-800 text-white p-6 flex items-center justify-between shadow-lg">
    <!-- LEFT: Back button -->
    <a href="admin_dashboard.php"
       class="bg-yellow-600 px-4 py-2 rounded hover:bg-yellow-700 transition">
       ← Back
    </a>
    <!-- CENTER: Title -->
    <h1 class="text-2xl font-bold mx-auto">ARTLAB Admin</h1>

    <!-- RIGHT: Empty spacer -->
    <div class="w-24"></div>
</header>


<!-- 🧠 MAIN CONTENT -->
<div class="max-w-6xl mx-auto mt-8">

    <!-- WHITE CARD -->
    <div class="bg-white rounded-xl shadow-lg p-6">

        <h2 class="text-2xl font-bold mb-6 text-gray-800">
            Login History of <?= htmlspecialchars($username) ?>
        </h2>

        <?php if (empty($logs)): ?>
            <p class="text-gray-600">No login activity found for this user.</p>
        <?php else: ?>

        <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden">

            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-3 border-b border-blue-800">Device</th>
                    <th class="p-3 border-b border-blue-800">IP Address</th>
                    <th class="p-3 border-b border-blue-800">Username</th>
                    <th class="p-3 border-b border-blue-800">Login Time</th>
                    <th class="p-3 border-b border-blue-800">Logout Time</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr class="text-center border-b hover:bg-gray-100">
                    <td class="p-3 border-b border-blue-800"><?= htmlspecialchars($log['device']) ?></td>
                    <td class="p-3 border-b border-blue-800"><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td class="p-3 border-b border-blue-800"><?= htmlspecialchars($log['username']) ?></td>
                    <td class="p-3 border-b border-blue-800"><?= $log['login_time'] ?></td>
                    <td class="p-3 border-b border-blue-800">
                        <?= $log['logout_time'] ?? '<span class="text-green-600 font-semibold">Still logged in</span>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>

        </table>
        </div>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
