<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header("Location: login.php");
    exit();
}

// Create moderator activity logs table if not exists
function createModeratorActivityLogsTable($conn) {
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
        // Table might already exist with proper schema
    }
}

// Log moderator activity with device and time tracking
function logModeratorActivity($conn, $actionType, $details = '', $time_in = null, $time_out = null) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') return;

    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = '127.0.0.1';
    }
    $ip = substr($ip, 0, 15);

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
        'review_content' => "Reviewed content: $details",
        'approve_content' => "Approved content: $details",
        'reject_content' => "Rejected content: $details",
        'flag_user' => "Flagged user: $details",
        'logout' => "Logged out",
        default => $details
    };

    try {
        createModeratorActivityLogsTable($conn);
        $stmt = $conn->prepare("INSERT INTO moderator_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $activity, $ip, $device, $os, $time_in, $time_out]);
    } catch (Exception $e) {
        // Silent fail - don't break the main operation
    }
}

// Create table if it doesn't exist
createModeratorActivityLogsTable($conn);

// Log page view
logModeratorActivity($conn, 'view', 'Moderator Dashboard');

$username = $_SESSION['username'] ?? 'moderator';
?>

<?php
// Fetch users for moderator view (exclude platform admins)
$current_moderator_id = $_SESSION['user_id'] ?? 0;
try {
    $users_stmt = $conn->prepare("SELECT * FROM registered_users WHERE role != 'platform_admin' OR id != :current_id");
    $users_stmt->bindParam(':current_id', $current_moderator_id, PDO::PARAM_INT);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Moderator Dashboard - Artlab</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #515151; }
        header { background-color: #333; color: white; padding: 10px 0; display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-radius:12px; }
        .logout-btn { padding: 10px 20px; background-color:darkgoldenrod; color:white; text-decoration:none; border-radius:12px; }
        .layout { display:flex; min-height:100vh; }
        .sidebar { width:220px; background-color:#333; padding:20px; }
        .logo { font-size:20px; margin-bottom:30px; letter-spacing:2px; }
        .main-content { flex:1; padding:30px; background-color:#ffffff; }
        .brand-sketchy { font-family: 'Permanent Marker', cursive; color: #ff8614; font-weight:500; font-size:30px; letter-spacing:2px; }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="layout flex w-full min-h-screen">

        <!-- SIDEBAR -->
        <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
            <h2 class="text-2xl font-bold text-white mb-5">ARTLAB MOD</h2>

            <div class="flex flex-col items-center text-center mt-8">
                <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
                <h4 class="text-white font-semibold"><?php echo htmlspecialchars($username); ?></h4>
                <p class="text-gray-400 text-sm">Moderator</p>
            </div>

            <nav class="flex flex-col gap-4 mt-8">
                <a href="moderator_dashboard.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">User Management</a>
                <a href="moderator_flag_review.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Flag Review</a>
                <a href="moderator_activity_logs.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">My Activity Logs</a>
            </nav>
        </aside>

        <!-- MAIN -->
        <main class="flex-1 p-6">
            <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
                <h3 class="text-xl font-bold">Moderator Panel</h3>
                <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
            </header>

            <section class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($username); ?></h2>
                <p class="text-gray-600 mb-4">This is your moderator dashboard. Use the sidebar to access review tools and reports.</p>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Status</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-b text-center">
                                    <td class="p-3"><?= htmlspecialchars($user['id']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($user['username']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($user['role'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($user['status'] ?? 'active') ?></td>
                                    <td class="p-3"> <!-- Moderators have view-only actions here -->
                                        
                                    </td>
                                  
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td class="p-3" colspan="6">No users found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

          
        </main>
    </div>

    <script>
    // Prevent back navigation when logged in
    if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function () { window.history.pushState(null, null, window.location.href); };
    }
    </script>

</body>
</html>
