<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();

}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) { // prevent self-delete
        header("Location: admin_dashboard.php");
        exit();
    }
    $stmt = $conn->prepare("DELETE FROM registered_users WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['ban'])) {
    $id = $_GET['ban'];
    if ($id == $_SESSION['user_id']) { // prevent self-ban
        header("Location: admin_dashboard.php");
        exit();
    }
    $stmt = $conn->prepare("UPDATE registered_users SET status='banned' WHERE id=?");
    $stmt->execute([$id]);
    header("Location: admin_dashboard.php");
    exit();
}


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$username = $_SESSION['username'] ?? 'platform admin';
$sql = "SELECT u.username, l.*
        FROM login_logs l
        JOIN registered_users u ON l.user_id = u.id
        ORDER BY l.login_time DESC";

$stmt = $conn->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current_admin_id = $_SESSION['user_id'];

$users = $conn->prepare("
    SELECT * 
    FROM registered_users 
    WHERE role != 'platform_admin' OR id != :current_id
");
$users->bindParam(':current_id', $current_admin_id, PDO::PARAM_INT);
$users->execute();
$users = $users->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Welcome to Artlab</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #515151;
        }
        header {
            background-color: #333;
            color: white;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            border-radius: 12px;

        }
        header h1 {
            margin: 0;
        }
        .logout-btn {
            padding: 10px 20px;
            background-color:darkgoldenrod;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            border-radius: 12px;
        }
        .logout-btn:hover {
            background-color:gray;
        }
        .container {
            text-align: center;
            padding: 50px;
        }
        h2 {
            color: #333;
        }
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            padding: 10px;
            background-color: #333;
            color: white;
        }
        .brand-sketchy {
            font-family: 'Permanent Marker', cursive;
            color: #ff8614;
            font-weight: 500;
            font-size:30px;
            letter-spacing: 2px;
            text-shadow:
             
                0 0 6px rgba(247, 220, 111, 0.6),
                1px 1px 0 #000;
        }


        /* Layout */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background-color:#333
            border-right: 1px solid #eee;
            padding: 20px;
        }

        .logo {
            font-size: 20px;
            margin-bottom: 30px;
            letter-spacing: 2px;
        }

        /* Navigation */
        .sidebar nav a {
            display: block;
            padding: 10px 0;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }

        .sidebar nav a:hover {
            color: #000;
        }

        .sidebar nav a.active {
            font-weight: bold;
            border-left: 3px solid #000;
            padding-left: 10px;
        }

        /* Main content */
        .main-content {
            flex: 1;
            padding: 30px;
            background-color: #ffffff;
        }
 

    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<div class="layout flex w-full min-h-screen">

<!-- 🔥 SIDEBAR -->
<aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">

    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB ADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg"
             class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
         <h4 class="text-white font-semibold">
            <?php echo htmlspecialchars($username); ?>
        </h4>
        <p class="text-gray-400 text-sm">
            Platform Admin
        </p>
    </div>

    <!-- ADMIN NAV -->
    <nav class="flex flex-col gap-4 mt-8">

        <a href="#" onclick="showSection('users', this)"
           class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">
            User Management
        </a>

        <a href="#" onclick="showSection('reports', this)"
           class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">
            Reports & Flags
        </a>

        <a href="#" onclick="showSection('marketplace', this)"
           class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">
            Marketplace Oversight
        </a>

        <a href="#" onclick="showSection('collaboration', this)"
           class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">
            Collaboration Oversight
        </a>

        <a href="#" onclick="showSection('usersettings', this)"
        class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">
            System Settings
        </a>

    </nav>
</aside>

<!-- 🧠 MAIN CONTENT -->
        <main class="flex-1 p-6">

        <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
            <h3 class="text-xl font-bold">
                Platform Admin Panel
            </h3>

            <a href="logout.php"
            class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">
            Logout
            </a>
        </header>

<!-- USER MANAGEMENT -->

            <section id="users" class="bg-white rounded-xl shadow-lg p-6">

            <h2 class="text-2xl font-bold mb-6">User Management</h2>

            <table class="w-full border-collapse">
            <thead class="bg-gray-800 text-white">
            <tr>
            <th class="p-3">ID</th>
            <th class="p-3">Username</th>
            <th class="p-3">Role</th>
            <th class="p-3">Status</th>
            <th class="p-3">Actions</th>
            <th class="p-3">Logs</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($users as $user): ?>
            <tr class="border-b text-center">
            <td class="p-3"><?= $user['id'] ?></td>
            <td class="p-3"><?= htmlspecialchars($user['username']) ?></td>
            <td class="p-3"><?= $user['role'] ?></td>
            <td class="p-3"><?= $user['status'] ?? 'active' ?></td>

            <td class="p-3 space-x-2">

            <a href="?delete=<?= $user['id'] ?>"
            class="bg-red-600 text-white px-3 py-1 rounded"
            onclick="return confirm('Delete this user?')">
            Delete
            </a>

            <a href="?ban=<?= $user['id'] ?>"
            class="bg-yellow-500 text-white px-3 py-1 rounded">
            Ban
            </a>

            </td>

            <td class="p-3">
                <a href="view_logs.php?user_id=<?= urlencode($user['id']) ?>" 
                class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                View Logs
                </a>
            </td>

            </tr>

            <?php endforeach; ?>
            </tbody>
            </table>
            
            <section id="userlogs" class="hidden bg-white rounded-xl shadow-lg w-full max-w-5xl p-6 mx-auto mb-10">
                <?php 
                if (isset($_GET['user_id'])) {
                    $user_id = $_GET['user_id']; // pass this to view_logs.php
                    include 'view_logs.php';
                } else {
                    echo '<p class="text-gray-600">Select a user to view login history.</p>';
                }
                ?>
            </section>



            </section>

            <!-- REPORTS -->
            <section id="reports" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Reports & Flags</h2>
                <p>Review reported artworks and users.</p>
            </section>

            <!-- MARKETPLACE -->
            <section id="marketplace" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Marketplace Oversight</h2>
                <p>Monitor listings and transactions.</p>
            </section>

            <!-- COLLABORATION -->
            <section id="collaboration" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Collaboration Oversight</h2>
                <p>Handle disputes and collaborations.</p>
            </section>

            <!-- SYSTEM SETTINGS -->
            <section id="usersettings" class="hidden bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-4">System Settings</h2>
                <p>Configure platform rules and security.</p>
                 <?php include 'user_settings.php'; ?>
            </section>

</main>
</div>

<!-- 🔁 SECTION SWITCH SCRIPT -->
        <?php
        $defaultSection = $_GET['section'] ?? 'users';
        ?>
        
        <script>
        function showSection(sectionId, link) {
            ['users','reports','marketplace','collaboration','usersettings']
                .forEach(id => {
                    document.getElementById(id).classList.add('hidden');
                });

            document.getElementById(sectionId).classList.remove('hidden');

            document.querySelectorAll('.sidebar-link').forEach(l=>{
                l.classList.remove('bg-yellow-700');
                l.classList.add('bg-gray-700');
            });

            if(link){
                link.classList.remove('bg-gray-700');
                link.classList.add('bg-yellow-700');
            }
        }
                    // default section from URL
            const urlParams = new URLSearchParams(window.location.search);
            const defaultSection = urlParams.get('section') || 'users';


        // default section from URL or fallback
        <?php
        $defaultSection = $_GET['section'] ?? 'users';
        ?>
        showSection('<?= $defaultSection ?>');

        </script>

            <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Block the back button if the user is logged in
            if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
                // Push a new state to block the user from navigating back to the previous pages
                window.history.pushState(null, null, window.location.href);

                // Periodically push state to block back navigation
                setInterval(function () {
                    window.history.pushState(null, null, window.location.href);
                }, 100);

                // Prevent navigating back when the user tries to use the back button
                window.onpopstate = function () {
                    window.history.pushState(null, null, window.location.href);
                };
            }
        });

</body>
</html>