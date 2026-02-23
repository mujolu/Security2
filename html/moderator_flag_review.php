<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

// Moderator-only access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';

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
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

// Log page view
logActivity($conn, $user_id, 'Accessed Flag Review Dashboard (Admin)', 'moderator_activity_logs');

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

// Handle resolution actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_post_id'])) {
    $post_id = (int)$_POST['resolve_post_id'];
    $action = trim($_POST['resolution_action'] ?? '');
    $notes = trim($_POST['resolution_notes'] ?? '');
    
    if (in_array($action, ['approved', 'removed', 'warned'])) {
        // Log the resolution action
        $stmt = $mysqli->prepare("INSERT INTO flag_resolutions (post_id, moderator_id, action_type, resolution_notes) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iiss', $post_id, $user_id, $action, $notes);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update post reported_status
        $new_status = ($action === 'approved') ? 'resolved' : 'removed';
        $update = $mysqli->prepare("UPDATE posts SET reported_status = ? WHERE id = ?");
        if ($update) {
            $update->bind_param('si', $new_status, $post_id);
            $update->execute();
            $update->close();
        }
        
        // Log moderator action with detailed information
        $activity_msg = "Flag Resolution: Post #$post_id - Action: $action" . ($notes ? " - Notes: " . substr($notes, 0, 100) : '');
        logActivity($conn, $user_id, $activity_msg, 'moderator_activity_logs');
    }
    
    header('Location: moderator_flag_review.php');
    exit();
}

// Fetch flagged posts with report details
$query = "
    SELECT 
        p.id, p.user_id, p.title, p.content, p.file_path, p.report_count, p.reported_status, p.created_at,
        GROUP_CONCAT(DISTINCT pr.reason SEPARATOR ', ') as reasons,
        COUNT(DISTINCT pr.reporter_id) as reporter_count,
        fr.action_type as last_action,
        fr.moderator_id,
        fr.resolution_notes,
        fr.resolved_at
    FROM posts p
    LEFT JOIN post_reports pr ON p.id = pr.post_id
    LEFT JOIN flag_resolutions fr ON p.id = fr.post_id AND fr.id = (
        SELECT MAX(id) FROM flag_resolutions WHERE post_id = p.id
    )
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

// Log viewing of the reported posts list
logActivity($conn, $user_id, 'Viewed reported posts list (' . count($flagged_posts) . ' posts)', 'moderator_activity_logs');

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
    'removed' => (int)($stats['removed_posts'] ?? 0)
];

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Flag Review - Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
    .modal.show { display: block; }
    .modal-content { background-color: #fefcfc; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; }
    .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close-modal:hover { color: black; }
</style>
</head>
<body class="bg-gray-50">

<header class="p-4 bg-gray-800 text-white flex justify-between items-center">
    <div class="flex items-center gap-3">
        <div class="text-lg font-bold">ARTLAB Admin</div>
        <div class="text-sm text-gray-200">Content Review Dashboard</div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-sm text-gray-200">Logged in as <?php echo htmlspecialchars($username); ?></div>
        <a href="logOut.php" class="bg-red-600 px-3 py-1 rounded">Logout</a>
    </div>
</header>

<div class="flex">
    <?php include 'moderator_sidebar.php'; ?>

    <main class="flex-1 p-10 bg-gray-100 min-h-screen">
        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-800">Content Flag Review</h1>
            <p class="text-gray-500 mt-1">Review and resolve flagged content from the community</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-yellow-700"><?php echo $stats['pending']; ?></div>
                <div class="text-sm text-yellow-600">Pending Flags</div>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-green-700"><?php echo $stats['resolved']; ?></div>
                <div class="text-sm text-green-600">Resolved</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-red-700"><?php echo $stats['removed']; ?></div>
                <div class="text-sm text-red-600">Removed Posts</div>
            </div>
        </div>

        <!-- Flagged Posts List -->
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
                                <p class="text-sm text-gray-500">Posted by User ID: <?php echo $post['user_id']; ?> on <?php echo $post['created_at']; ?></p>
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

                        <!-- Report Summary -->
                        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <p class="text-sm"><strong>Reports:</strong> <?php echo $post['report_count']; ?> total | <strong>Reporters:</strong> <?php echo $post['reporter_count']; ?> unique</p>
                            <?php if ($post['reasons']): ?>
                                <p class="text-sm mt-1"><strong>Reasons:</strong> <?php echo htmlspecialchars($post['reasons']); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Previous Resolution (if any) -->
                        <?php if ($post['last_action']): ?>
                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                                <p class="text-sm"><strong>Last Action:</strong> <?php echo ucfirst($post['last_action']); ?> on <?php echo $post['resolved_at']; ?></p>
                                <?php if ($post['resolution_notes']): ?>
                                    <p class="text-sm mt-1"><strong>Notes:</strong> <?php echo htmlspecialchars($post['resolution_notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Resolution Actions (only for pending flags) -->
                        <?php if ($post['reported_status'] === 'flagged'): ?>
                            <div class="flex gap-2">
                                <button class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded" onclick="openModal('modal_<?php echo $post['id']; ?>', 'approved')">
                                    Approve (Keep Post)
                                </button>
                                <button class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded" onclick="openModal('modal_<?php echo $post['id']; ?>', 'removed')">
                                    Remove Post
                                </button>
                                <button class="bg-orange-600 hover:bg-orange-500 text-white px-4 py-2 rounded" onclick="openModal('modal_<?php echo $post['id']; ?>', 'warned')">
                                    Warn User
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-500 text-sm italic">This post has already been reviewed and resolved.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Resolution Modal -->
                    <div id="modal_<?php echo $post['id']; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close-modal" onclick="closeModal('modal_<?php echo $post['id']; ?>')">&times;</span>
                            <h2 class="text-xl font-bold mb-4">Resolve Flag - Post #<?php echo $post['id']; ?></h2>
                            
                            <form method="POST">
                                <input type="hidden" name="resolve_post_id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" id="action_<?php echo $post['id']; ?>" name="resolution_action" value="">
                                
                                <div class="mb-4">
                                    <label class="block font-semibold mb-2">Resolution Notes (optional):</label>
                                    <textarea name="resolution_notes" rows="4" placeholder="Document your decision and reasoning..." class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-yellow-500 outline-none"></textarea>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400" onclick="closeModal('modal_<?php echo $post['id']; ?>')">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-500">
                                        Confirm Resolution
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function openModal(modalId, action) {
    const modal = document.getElementById(modalId);
    const actionInput = document.getElementById('action_' + modalId.split('_')[1]);
    actionInput.value = action;
    modal.classList.add('show');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>

</body>
</html>
