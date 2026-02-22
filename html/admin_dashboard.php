<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();

}

// Include the logging function
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

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) { // prevent self-delete
        header("Location: admin_dashboard.php");
        exit();
    }
    
    // Get user info before deleting for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username, email FROM registered_users WHERE id = ?");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $user_details = $user_info['first_name'] . ' ' . $user_info['last_name'] . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')';
        }
    } catch (Exception $e) {
        $user_details = "ID: $id";
    }
    
    $stmt = $conn->prepare("DELETE FROM registered_users WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log the deletion
    logAdminActivity($conn, 'delete_user', $user_details ?? "ID: $id");
    
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['ban'])) {
    $id = $_GET['ban'];
    if ($id == $_SESSION['user_id']) { // prevent self-ban
        header("Location: admin_dashboard.php");
        exit();
    }
    
    // Get user info before banning for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username, email FROM registered_users WHERE id = ?");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $user_details = $user_info['first_name'] . ' ' . $user_info['last_name'] . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')';
        }
    } catch (Exception $e) {
        $user_details = "ID: $id";
    }
    
    $stmt = $conn->prepare("UPDATE registered_users SET status='banned' WHERE id=?");
    $stmt->execute([$id]);
    
    // Log the ban
    logAdminActivity($conn, 'ban_user', $user_details ?? "ID: $id");
    
    header("Location: admin_dashboard.php");
    exit();
}


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$username = $_SESSION['username'] ?? 'platform admin';
$current_page = basename($_SERVER['PHP_SELF']);

// Log page view
logAdminActivity($conn, 'view', 'User Management Dashboard');

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

        <a href="admin_dashboard.php" class="sidebar-link <?php echo $current_page === 'admin_dashboard.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_flag_review.php" class="sidebar-link <?php echo $current_page === 'admin_flag_review.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Flag Audit</a>
        <a href="admin_deploy.php" class="sidebar-link <?php echo $current_page === 'admin_deploy.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Deploy Moderators</a>
        <a href="admin_marketplace.php" class="sidebar-link <?php echo $current_page === 'admin_marketplace.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Marketplace Art </a>
        <a href="admin_collab.php" class="sidebar-link <?php echo $current_page === 'admin_collab.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link <?php echo $current_page === 'admin_activity.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Activity Logs</a>

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

            // If no link element was provided (page load), try to find the sidebar
            // link that matches the current filename and highlight it.
            if (!link) {
                var currentFilename = window.location.pathname.split('/').pop();
                link = document.querySelector('.sidebar-link[href$="' + currentFilename + '"]');
            }

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