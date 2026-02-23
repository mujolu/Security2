<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

// Admin-only access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user's full name and role
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

$username = $_SESSION['username'] ?? 'Super Admin';

// Log page view for super admin
logAdminActivity($conn, 'view', 'Flag Audit Dashboard');

// Ensure admin activity logs table exists
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
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

// Log page view
logPageView($conn, $user_id, 'Flag Review Dashboard (Super Admin)', 'admin_activity_logs');

$mysqli = new mysqli("localhost","root","","artlab_db");
if ($mysqli->connect_error) {
    die('DB error');
}

// Ensure necessary tables exist
$mysqli->query("CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    file_path VARCHAR(255) NULL,
    like_count INT DEFAULT 0,
    report_count INT DEFAULT 0,
    reported_status ENUM('none', 'flagged', 'resolved', 'removed') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_posts (user_id),
    INDEX idx_reported_status (reported_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS post_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_report (post_id, reporter_id),
    INDEX idx_post_report_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS flag_resolutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    moderator_id INT NOT NULL,
    action_type ENUM('approved', 'removed', 'warned') DEFAULT 'approved',
    resolution_notes TEXT NULL,
    resolved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_resolution (post_id),
    INDEX idx_moderator_resolution (moderator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add missing columns to existing posts table if they don't exist
try {
    $mysqli->query("ALTER TABLE posts ADD COLUMN like_count INT DEFAULT 0 AFTER file_path");
} catch (Exception $e) {
    // Column already exists
}
try {
    $mysqli->query("ALTER TABLE posts ADD COLUMN report_count INT DEFAULT 0 AFTER like_count");
} catch (Exception $e) {
    // Column already exists
}
try {
    $mysqli->query("ALTER TABLE posts ADD COLUMN reported_status ENUM('none', 'flagged', 'resolved', 'removed') DEFAULT 'none' AFTER report_count");
} catch (Exception $e) {
    // Column already exists
}
try {
    $mysqli->query("ALTER TABLE posts ADD INDEX idx_reported_status (reported_status)");
} catch (Exception $e) {
    // Index already exists
}

// Fetch flagged posts with resolution details
$query = "
    SELECT 
        p.id, p.user_id, p.title, p.content, p.file_path, p.report_count, p.reported_status, p.created_at,
        GROUP_CONCAT(DISTINCT pr.reason SEPARATOR ', ') as reasons,
        COUNT(DISTINCT pr.reporter_id) as reporter_count,
        fr.action_type as last_action,
        fr.moderator_id as resolver_id,
        fr.resolution_notes,
        fr.resolved_at,
        u.username as resolver_username
    FROM posts p
    LEFT JOIN post_reports pr ON p.id = pr.post_id
    LEFT JOIN flag_resolutions fr ON p.id = fr.post_id AND fr.id = (
        SELECT MAX(id) FROM flag_resolutions WHERE post_id = p.id
    )
    LEFT JOIN registered_users u ON fr.moderator_id = u.id
    WHERE p.reported_status IN ('flagged', 'resolved', 'removed')
    GROUP BY p.id
    ORDER BY p.reported_status = 'flagged' DESC, p.created_at DESC
";

$result = $mysqli->query($query);
$flagged_posts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $flagged_posts[] = $row;
    }
    $result->close();
}

// Log specific action for viewing flagged posts list
logAdminActivity($conn, 'view', 'Viewed reported posts list (' . count($flagged_posts) . ' posts)');

// Get stats
$stats_query = "SELECT 
    SUM(CASE WHEN reported_status = 'flagged' THEN 1 ELSE 0 END) as pending_flags,
    SUM(CASE WHEN reported_status = 'resolved' THEN 1 ELSE 0 END) as resolved_flags,
    SUM(CASE WHEN reported_status = 'removed' THEN 1 ELSE 0 END) as removed_posts
FROM posts";

$stats = $mysqli->query($stats_query)->fetch_assoc();
$stats = [
    'pending' => (int)($stats['pending_flags'] ?? 0),
    'resolved' => (int)($stats['resolved_flags'] ?? 0),
    'removed' => (int)($stats['removed_posts'] ?? 0),
    'total' => 0
];
$stats['total'] = $stats['pending'] + $stats['resolved'] + $stats['removed'];

// Get moderator stats
$mod_stats_query = "
    SELECT 
        fr.moderator_id,
        u.username,
        COUNT(*) as resolutions,
        SUM(CASE WHEN fr.action_type = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN fr.action_type = 'removed' THEN 1 ELSE 0 END) as removed,
        SUM(CASE WHEN fr.action_type = 'warned' THEN 1 ELSE 0 END) as warned
    FROM flag_resolutions fr
    LEFT JOIN registered_users u ON fr.moderator_id = u.id
    GROUP BY fr.moderator_id, u.username
    ORDER BY COUNT(*) DESC
";

$mod_result = $mysqli->query($mod_stats_query);
$moderator_stats = [];
if ($mod_result) {
    while ($row = $mod_result->fetch_assoc()) {
        $moderator_stats[] = $row;
    }
    $mod_result->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Flag Audit - Super Admin Dashboard</title>
</head>

<body class="bg-gray-100 min-h-screen">
<div class="layout flex w-full min-h-screen">

<!-- 🔥 SIDEBAR -->
<aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">

    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB SUPERADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg"
             class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
         <h4 class="text-white font-semibold">
            <?php echo htmlspecialchars($user_full_name); ?>
        </h4>
        <p class="text-gray-400 text-sm">
            <?php echo htmlspecialchars($user_role); ?>
        </p>
    </div>

    <!-- ADMIN NAV -->
    <nav class="flex flex-col gap-4 mt-8">

        <a href="admin_dashboard.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_flag_review.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">Flag Audit</a>
        <a href="admin_deploy.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Deploy Admins</a>
        <a href="admin_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Marketplace Art </a>
        <a href="admin_collab.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Activity Logs</a>

    </nav>
</aside>

<!-- 🧠 MAIN CONTENT -->
        <main class="flex-1 p-6">

        <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
            <h3 class="text-xl font-bold">
                Flag Audit Dashboard
            </h3>

            <a href="logOut.php"
            class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">
            Logout
            </a>
        </header>

        <div class="mb-8">

        <!-- Overall Stats -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-blue-700"><?php echo $stats['total']; ?></div>
                <div class="text-sm text-blue-600">Total Flags</div>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-yellow-700"><?php echo $stats['pending']; ?></div>
                <div class="text-sm text-yellow-600">Pending</div>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-green-700"><?php echo $stats['resolved']; ?></div>
                <div class="text-sm text-green-600">Resolved</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-red-700"><?php echo $stats['removed']; ?></div>
                <div class="text-sm text-red-600">Removed</div>
            </div>
        </div>

        <!-- Admin Performance Stats -->
        <?php if (!empty($moderator_stats)): ?>
        <div class="bg-white border border-gray-300 rounded-lg p-6 mb-8 shadow-md">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Admin Performance</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="text-left p-3">Admin</th>
                            <th class="text-center p-3">Total Resolutions</th>
                            <th class="text-center p-3">Approved</th>
                            <th class="text-center p-3">Removed</th>
                            <th class="text-center p-3">Warned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($moderator_stats as $mod): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="p-3"><?php echo htmlspecialchars($mod['username'] ?? 'Unknown'); ?></td>
                            <td class="text-center p-3 font-semibold"><?php echo $mod['resolutions']; ?></td>
                            <td class="text-center p-3"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs"><?php echo $mod['approved'] ?? 0; ?></span></td>
                            <td class="text-center p-3"><span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs"><?php echo $mod['removed'] ?? 0; ?></span></td>
                            <td class="text-center p-3"><span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs"><?php echo $mod['warned'] ?? 0; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flagged Posts Audit -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Flagged Content Audit</h2>
            
            <div class="space-y-6">
                <?php if (empty($flagged_posts)): ?>
                    <div class="bg-white border border-gray-300 rounded-lg p-6 text-center text-gray-500">
                        No flagged posts at this time.
                    </div>
                <?php else: ?>
                    <?php foreach ($flagged_posts as $post): ?>
                        <div class="bg-white border <?php 
                            echo $post['reported_status'] === 'flagged' ? 'border-red-300' : 
                                 ($post['reported_status'] === 'resolved' ? 'border-green-300' : 'border-gray-300');
                        ?> rounded-lg p-6 shadow-md">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <p class="text-sm text-gray-500">Post ID: <?php echo $post['id']; ?> | User ID: <?php echo $post['user_id']; ?> | Posted: <?php echo $post['created_at']; ?></p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?php 
                                    echo $post['reported_status'] === 'flagged' ? 'bg-red-100 text-red-700' :
                                         ($post['reported_status'] === 'resolved' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700');
                                ?>">
                                    <?php echo ucfirst($post['reported_status']); ?>
                                </span>
                            </div>

                            <div class="mb-4 p-4 bg-gray-50 rounded border border-gray-200">
                                <p class="text-gray-700"><?php echo htmlspecialchars(substr($post['content'], 0, 300)); ?><?php echo strlen($post['content']) > 300 ? '...' : ''; ?></p>
                            </div>

                            <!-- Flag Details -->
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded">
                                    <p class="text-sm"><strong>Total Reports:</strong> <?php echo $post['report_count']; ?></p>
                                    <p class="text-sm"><strong>Unique Reporters:</strong> <?php echo $post['reporter_count']; ?></p>
                                    <?php if ($post['reasons']): ?>
                                        <p class="text-xs mt-2"><strong>Reasons:</strong> <?php echo htmlspecialchars($post['reasons']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Admin Action -->
                                <div class="p-3 <?php echo $post['last_action'] ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200'; ?> rounded">
                                    <?php if ($post['last_action']): ?>
                                        <p class="text-sm"><strong>Admin Decision:</strong> <?php echo ucfirst($post['last_action']); ?></p>
                                        <p class="text-sm"><strong>Resolved By:</strong> <?php echo htmlspecialchars($post['resolver_username'] ?? 'Unknown'); ?></p>
                                        <p class="text-sm"><strong>Resolved At:</strong> <?php echo $post['resolved_at']; ?></p>
                                        <?php if ($post['resolution_notes']): ?>
                                            <p class="text-xs mt-2"><strong>Notes:</strong> <?php echo htmlspecialchars($post['resolution_notes']); ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-600 italic">Awaiting admin action...</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 italic">
                                This is a read-only audit view. Only admins can resolve flags.
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
