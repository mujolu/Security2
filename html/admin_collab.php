<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Get current user's full name and role
$user_id = $_SESSION['user_id'] ?? '';
$user_full_name = 'Super Admin';
$user_role = 'Super Admin';

if ($user_id) {
    try {
        $user_stmt_temp = $conn->prepare("SELECT first_name, last_name, role FROM registered_users WHERE id = ?");
        $user_stmt_temp->execute([$user_id]);
        $user_data = $user_stmt_temp->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $user_full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
            if (!$user_full_name) {
                $user_full_name = 'Super Admin';
            }
            // Set role label based on role value
            if ($user_data['role'] === 'platform_admin') {
                $user_role = 'Super Admin';
            } else {
                $user_role = ucfirst($user_data['role'] ?? 'artist');
            }
        }
    } catch (Exception $e) {
        // Use defaults
    }
}

$username = $_SESSION['username'] ?? 'super admin';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Collaboration Oversight - Artlab Super Admin</title>
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
    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB SUPERADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
         <h4 class="text-white font-semibold"><?php echo htmlspecialchars($user_full_name); ?></h4>
        <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($user_role); ?></p>
    </div>

    <nav class="flex flex-col gap-4 mt-8">
        <a href="admin_dashboard.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_flag_review.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Flag Audit</a>
        <a href="admin_deploy.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Deploy Admins</a>
        <a href="admin_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Marketplace</a>
        <a href="admin_collab.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Activity Logs</a>
    </nav>
</aside>

<main class="flex-1 p-6">
    <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
        <h3 class="text-xl font-bold">Collaboration Oversight</h3>
        <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
    </header>

    <section class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Collaboration Oversight</h2>
        <p class="text-gray-600">No content yet. This page will show collaboration disputes and details.</p>
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
