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
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist with proper schema
    }
}

// Create unban requests table if not exists
function createUnbanRequestsTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS unban_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(9) NOT NULL,
            requested_by VARCHAR(9) NOT NULL,
            reason VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_by VARCHAR(9) NULL,
            reviewed_at TIMESTAMP NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // ignore
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
        'ban_user' => "Banned user: $details",
        'unban_user' => "Unbanned user: $details",
        'request_unban' => "Requested unban: $details",
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
createUnbanRequestsTable($conn);

// Log page view using the standard logging function
logActivity($conn, $_SESSION['user_id'], 'Accessed Admin Dashboard', 'moderator_activity_logs');

$username = $_SESSION['username'] ?? 'admin';

// Handle unban request from admin (moderator) for users with reports
if (isset($_GET['request_unban'])) {
    $id = $_GET['request_unban'];
    $reason = trim($_GET['request_reason'] ?? '');
    if ($reason === '') {
        $reason = 'Unban requested by Admin.';
    }
    $reason = substr($reason, 0, 255);

    try {
        // Only request approval if the user has flagged posts
        $rep_stmt = $conn->prepare("SELECT COUNT(*)
            FROM post_reports pr
            WHERE pr.post_id IN (SELECT id FROM posts WHERE user_id = ?)
               OR pr.post_id IN (SELECT id FROM marketplace_items WHERE user_id = ?)");
        $rep_stmt->execute([$id, $id]);
        $report_count = (int)$rep_stmt->fetchColumn();
        if ($report_count === 0) {
            header("Location: moderator_dashboard.php");
            exit();
        }

        // Avoid duplicate pending requests
        $check_stmt = $conn->prepare("SELECT status FROM unban_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $check_stmt->execute([$id]);
        $last_status = $check_stmt->fetchColumn();

        if ($last_status !== 'pending') {
            $stmt = $conn->prepare("INSERT INTO unban_requests (user_id, requested_by, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$id, $_SESSION['user_id'], $reason]);
            logModeratorActivity($conn, 'request_unban', "ID: $id | Reason: $reason | Page: User Management");
        }
    } catch (Exception $e) {
        // ignore
    }

    header("Location: moderator_dashboard.php");
    exit();
}

// Handle unban action (moderators can unban only if approved when reports exist)
if (isset($_GET['unban'])) {
    $id = $_GET['unban'];

    // Check report count for the user's posts
    $report_count = 0;
    try {
        $rep_stmt = $conn->prepare("SELECT COUNT(*)
            FROM post_reports pr
            WHERE pr.post_id IN (SELECT id FROM posts WHERE user_id = ?)
               OR pr.post_id IN (SELECT id FROM marketplace_items WHERE user_id = ?)");
        $rep_stmt->execute([$id, $id]);
        $report_count = (int)$rep_stmt->fetchColumn();
    } catch (Exception $e) {
        $report_count = 0;
    }

    $approved = true;
    if ($report_count > 0) {
        $approved = false;
        try {
            $app_stmt = $conn->prepare("SELECT status FROM unban_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $app_stmt->execute([$id]);
            $approved = $app_stmt->fetchColumn() === 'approved';
        } catch (Exception $e) {
            $approved = false;
        }
    }

    if ($approved) {
        try {
            $stmt = $conn->prepare("UPDATE registered_users SET status='active', ban_reason = NULL, status_reason = NULL, status_updated_at = NOW() WHERE id = ? AND role != 'platform_admin'");
            $stmt->execute([$id]);
            logModeratorActivity($conn, 'unban_user', "ID: $id | Page: User Management");
        } catch (Exception $e) {
            // ignore
        }
    } else {
        // Create a pending request if not already pending
        try {
            $check_stmt = $conn->prepare("SELECT status FROM unban_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $check_stmt->execute([$id]);
            $last_status = $check_stmt->fetchColumn();

            if ($last_status !== 'pending') {
                $reason = 'Unban requested by Admin.';
                $stmt = $conn->prepare("INSERT INTO unban_requests (user_id, requested_by, reason, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$id, $_SESSION['user_id'], $reason]);
                logModeratorActivity($conn, 'request_unban', "ID: $id | Reason: $reason | Page: User Management");
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    header("Location: moderator_dashboard.php");
    exit();
}

// Handle ban action (moderators can ban but not unban)
if (isset($_GET['ban'])) {
    $id = $_GET['ban'];
    if ($id == $_SESSION['user_id']) { // prevent self-ban
        header("Location: moderator_dashboard.php");
        exit();
    }

    $ban_reason = trim($_GET['ban_reason'] ?? '');
    if ($ban_reason === '') {
        $ban_reason = 'Violation of platform rules.';
    }
    $ban_reason = substr($ban_reason, 0, 255);

    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username FROM registered_users WHERE id = ? AND role != 'platform_admin'");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        $user_details = $user_info
            ? (($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '') . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')')
            : "ID: $id";
    } catch (Exception $e) {
        $user_details = "ID: $id";
    }

    $status_reason = 'Banned by Admin';
    try {
        $stmt = $conn->prepare("UPDATE registered_users SET status='banned', ban_reason = ?, status_reason = ?, status_updated_at = NOW() WHERE id = ? AND role != 'platform_admin'");
        $stmt->execute([$ban_reason, $status_reason, $id]);
        logModeratorActivity($conn, 'ban_user', ($user_details ?? "ID: $id") . " | Reason: " . $ban_reason . " | Page: User Management");
    } catch (Exception $e) {
        // ignore
    }

    header("Location: moderator_dashboard.php");
    exit();
}
?>

<?php
// Fetch users for moderator view (exclude platform admins)
$current_moderator_id = $_SESSION['user_id'] ?? 0;
try {
    $users_stmt = $conn->prepare("
         SELECT u.*,
             (SELECT COUNT(*)
              FROM post_reports pr
              WHERE pr.post_id IN (SELECT id FROM posts WHERE user_id = u.id)
                 OR pr.post_id IN (SELECT id FROM marketplace_items WHERE user_id = u.id)) AS report_count,
               (SELECT status FROM unban_requests ur WHERE ur.user_id = u.id ORDER BY ur.id DESC LIMIT 1) AS unban_status
        FROM registered_users u
        WHERE u.role != 'platform_admin' AND u.id != :current_id
    ");
    $users_stmt->bindParam(':current_id', $current_moderator_id, PDO::PARAM_STR);
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
    <title>Admin Dashboard - Artlab</title>
    <script>
    function doBan(userId, username) {
        var reason = prompt('Enter ban reason for ' + username + ':');
        if (reason === null) return false;
        var finalReason = reason.trim().length > 0 ? reason.trim() : 'Violation of platform rules.';
        window.location.href = '?ban=' + encodeURIComponent(userId) + '&ban_reason=' + encodeURIComponent(finalReason);
        return false;
    }

    function doRequestUnban(userId, username) {
        var reason = prompt('Request unban for ' + username + '. Provide reason:');
        if (reason === null) return false;
        var finalReason = reason.trim().length > 0 ? reason.trim() : 'Unban requested by Admin.';
        window.location.href = '?request_unban=' + encodeURIComponent(userId) + '&request_reason=' + encodeURIComponent(finalReason);
        return false;
    }
    </script>
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
        <?php include 'moderator_sidebar.php'; ?>

        <!-- MAIN -->
        <main class="flex-1 p-6">
            <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
                <h3 class="text-xl font-bold">Admin Panel</h3>
                <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
            </header>

            <section class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($username); ?></h2>
                <p class="text-gray-600 mb-4">This is your admin dashboard. Use the sidebar to access review tools and reports.</p>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Reports</th>
                                <th class="p-3">Actions</th>
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
                                    <td class="p-3"><?= htmlspecialchars((string)($user['report_count'] ?? 0)) ?></td>
                                    <td class="p-3">
                                        <?php
                                        $status = $user['status'] ?? 'active';
                                        $report_count = (int)($user['report_count'] ?? 0);
                                        $unban_status = $user['unban_status'] ?? '';
                                        ?>

                                        <?php if ($status === 'banned'): ?>
                                            <?php if ($report_count > 0): ?>
                                                <?php if ($unban_status === 'approved'): ?>
                                                    <a href="?unban=<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>"
                                                    class="bg-green-600 text-white px-3 py-1 rounded"
                                                    onclick="return confirm('Unban this user (approved by Super Admin)?')">
                                                    Unban
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-yellow-600 text-sm">Pending approval</span>
                                                    <?php if ($unban_status !== 'pending'): ?>
                                                        <a href="?unban=<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>"
                                                        class="bg-blue-600 text-white px-3 py-1 rounded ml-2"
                                                        onclick="return confirm('Request unban approval from Super Admin?')">
                                                        Request Unban
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="?unban=<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>"
                                                class="bg-green-600 text-white px-3 py-1 rounded"
                                                onclick="return confirm('Unban this user?')">
                                                Unban
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($report_count === 0): ?>
                                                <span class="bg-gray-300 text-gray-600 px-3 py-1 rounded cursor-not-allowed">Ban</span>
                                            <?php else: ?>
                                                <a href="#"
                                                class="bg-yellow-500 text-white px-3 py-1 rounded"
                                                onclick="return doBan('<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                                Ban
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                  
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td class="p-3" colspan="5">No users found.</td></tr>
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
