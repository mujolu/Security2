<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$username = $_SESSION['username'] ?? 'User';

// Ensure user activity logs table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
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
logPageView($conn, $_SESSION['user_id'], 'Artwork', 'user_activity_logs');

$mysqli = new mysqli("localhost","root","","artlab_db");
if ($mysqli->connect_error) {
    die('DB error');
}

// Like/Report settings
$canLikeReport = ($_SESSION['role'] ?? '') === 'artist';
$reportThreshold = 3;

// Ensure posts table with flag tracking
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

// Ensure flag_resolutions table for moderator actions
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

// Ensure like/report tables for posts
$mysqli->query("CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_like (post_id, user_id),
    INDEX idx_post_like_post (post_id)
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

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_post_id'])) {
    if ($canLikeReport) {
        $post_id = (int)$_POST['like_post_id'];
        $user_id = (int)$_SESSION['user_id'];
        $check = $mysqli->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        if ($check) {
            $check->bind_param('ii', $post_id, $user_id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                $del = $mysqli->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                if ($del) {
                    $del->bind_param('ii', $post_id, $user_id);
                    $del->execute();
                    $del->close();
                }
            } else {
                $ins = $mysqli->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                if ($ins) {
                    $ins->bind_param('ii', $post_id, $user_id);
                    $ins->execute();
                    $ins->close();
                }
            }
            
            // Update like_count in posts table
            $count = $mysqli->prepare("SELECT COUNT(*) as like_count FROM post_likes WHERE post_id = ?");
            if ($count) {
                $count->bind_param('i', $post_id);
                $count->execute();
                $result = $count->get_result()->fetch_assoc();
                $like_count = (int)$result['like_count'];
                $count->close();
                
                $update = $mysqli->prepare("UPDATE posts SET like_count = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param('ii', $like_count, $post_id);
                    $update->execute();
                    $update->close();
                }
            }
        }
    }
    header('Location: user_artwork.php');
    exit();
}

// Handle report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_post_id'])) {
    if ($canLikeReport) {
        $post_id = (int)$_POST['report_post_id'];
        $user_id = (int)$_SESSION['user_id'];
        $reason = trim($_POST['report_reason'] ?? '');
        if ($reason === '') {
            $reason = 'Unspecified';
        }
        $rep = $mysqli->prepare("INSERT IGNORE INTO post_reports (post_id, reporter_id, reason) VALUES (?, ?, ?)");
        if ($rep) {
            $rep->bind_param('iis', $post_id, $user_id, $reason);
            $rep->execute();
            $rep->close();
            
            // Update report_count and flag status in posts table
            $count = $mysqli->prepare("SELECT COUNT(*) as report_count FROM post_reports WHERE post_id = ?");
            if ($count) {
                $count->bind_param('i', $post_id);
                $count->execute();
                $result = $count->get_result()->fetch_assoc();
                $report_count = (int)$result['report_count'];
                $count->close();
                
                $status = $report_count >= 1 ? 'flagged' : 'none';
                $update = $mysqli->prepare("UPDATE posts SET report_count = ?, reported_status = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param('isi', $report_count, $status, $post_id);
                    $update->execute();
                    $update->close();
                }
            }
        }
    }
    header('Location: user_artwork.php');
    exit();
}

// Handle post submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])
) {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? 'Untitled');
    $content = trim($_POST['content'] ?? '');
    $file_path = null;

    if (!empty($_FILES['post_file']['name'] ?? '')) {
        $uploads_dir = __DIR__ . '/../images/uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $original = basename($_FILES['post_file']['name']);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($original, PATHINFO_FILENAME));
        $target_name = $safe . '_' . time() . ($ext ? '.' . $ext : '');
        $target_path = $uploads_dir . '/' . $target_name;
        if (move_uploaded_file($_FILES['post_file']['tmp_name'], $target_path)) {
            $file_path = '/Security2/images/uploads/' . $target_name;
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO posts (user_id, title, content, file_path) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $user_id, $title, $content, $file_path);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: user_artwork.php');
    exit();
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Artwork - Artlab</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<header class="p-4 bg-gray-800 text-white flex justify-between items-center">
    <div class="flex items-center gap-3">
        <div class="text-lg font-bold">ARTLAB</div>
        <div class="text-sm text-gray-200">Artist Workspace</div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-sm text-gray-200">Logged in as <?php echo htmlspecialchars($username); ?></div>
        <a href="logOut.php" class="bg-yellow-600 px-3 py-1 rounded">Logout</a>
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
            <a href="user_artwork.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">Artwork</a>
            <a href="user_collaborations.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Collaborations</a>
            <a href="user_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Marketplace</a>
            <a href="user_activity_logs.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">My Activity Logs</a>
            <a href="user_settings.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Settings</a>
        </nav>
    </aside>
  <main class="flex-1 p-10 bg-gray-100 min-h-screen">

    <!-- Page Header -->
    <div class="mb-10">
        <h1 class="text-3xl font-semibold text-gray-800">Create Post</h1>
        <p class="text-gray-500 mt-1">Share your artwork or updates with the community</p>
    </div>

    <!-- Create Post Card -->
    <form method="POST" enctype="multipart/form-data"
          class="bg-white border border-gray-300 rounded-2xl p-6 shadow-md hover:shadow-lg mb-12 max-w-3xl transition-shadow">

        <input type="text" name="title" placeholder="Title" required
               class="w-full mb-4 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none transition">

        <textarea name="content" rows="5" placeholder="Write something..."
                  class="w-full mb-4 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none transition"></textarea>

        <div class="flex items-center justify-between">
            <input type="file" name="post_file" accept="image/*,video/*"
                   class="text-sm text-gray-500">

            <button type="submit" name="submit_post"
                    class="bg-yellow-600 hover:bg-yellow-500 text-white px-6 py-2 rounded-lg font-medium transition">
                Publish
            </button>
        </div>
    </form>

    <!-- Posts Header -->
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-2xl font-semibold text-gray-800">Latest Posts</h2>
    </div>

    <?php
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $q = "SELECT p.*, u.username,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM post_reports pr WHERE pr.post_id = p.id) AS report_count,
            (SELECT COUNT(*) FROM post_likes pl2 WHERE pl2.post_id = p.id AND pl2.user_id = ?) AS liked_by_me,
            (SELECT COUNT(*) FROM post_reports pr2 WHERE pr2.post_id = p.id AND pr2.reporter_id = ?) AS reported_by_me
          FROM posts p
          JOIN registered_users u ON p.user_id = u.id
          WHERE (SELECT COUNT(*) FROM post_reports pr3 WHERE pr3.post_id = p.id) < ?
          ORDER BY p.created_at DESC
          LIMIT 24";
    $stmt = $mysqli->prepare($q);
    if ($stmt) {
        $stmt->bind_param('iii', $currentUserId, $currentUserId, $reportThreshold);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = false;
    }

    if ($res && $res->num_rows):
        echo '<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">';
        while ($row = $res->fetch_assoc()) {

            echo '<article class="bg-white border border-gray-300 rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition duration-300">';

            if (!empty($row['file_path'])) {
                echo '<img src="'.htmlspecialchars($row['file_path']).'" class="w-full h-52 object-cover">';
            }

            echo '<div class="p-5">';
            echo '<h3 class="font-semibold text-lg text-gray-800">'.htmlspecialchars($row['title']).'</h3>';
            echo '<p class="text-sm text-gray-600 mt-2">'.
                 nl2br(htmlspecialchars(mb_strimwidth($row['content'],0,120,'...'))).'</p>';

            echo '<div class="flex justify-between items-center text-xs text-gray-400 mt-4">';
            echo '<span>By '.htmlspecialchars($row['username']).'</span>';
            echo '<span>'.htmlspecialchars($row['created_at']).'</span>';
            echo '</div>';

            $like_count = (int)($row['like_count'] ?? 0);
            $report_count = (int)($row['report_count'] ?? 0);
            $liked_by_me = (int)($row['liked_by_me'] ?? 0) > 0;
            $reported_by_me = (int)($row['reported_by_me'] ?? 0) > 0;

            echo '<div class="mt-4 flex items-center justify-between text-sm">';
            echo '<div class="text-gray-500">❤ '.htmlspecialchars((string)$like_count).' • ⚑ '.htmlspecialchars((string)$report_count).'</div>';

            if ($canLikeReport) {
                echo '<div class="flex items-center gap-2">';
                echo '<form method="POST">';
                echo '<input type="hidden" name="like_post_id" value="'.(int)$row['id'].'">';
                echo '<button type="submit" class="px-3 py-1 rounded text-xs '.($liked_by_me ? 'bg-gray-200 text-gray-700' : 'bg-yellow-600 text-white').'">'.($liked_by_me ? 'Unlike' : 'Like').'</button>';
                echo '</form>';

                echo '<form method="POST" class="flex items-center gap-2">';
                echo '<input type="hidden" name="report_post_id" value="'.(int)$row['id'].'">';
                echo '<select name="report_reason" class="border border-gray-300 rounded px-2 py-1 text-xs" '.($reported_by_me ? 'disabled' : '').'>';
                echo '<option value="Spam">Spam</option>';
                echo '<option value="Inappropriate">Inappropriate</option>';
                echo '<option value="Copyright">Copyright</option>';
                echo '<option value="Other">Other</option>';
                echo '</select>';
                echo '<button type="submit" class="px-3 py-1 rounded text-xs '.($reported_by_me ? 'bg-gray-200 text-gray-500' : 'bg-red-600 text-white').'" '.($reported_by_me ? 'disabled' : '').'>'.($reported_by_me ? 'Reported' : 'Report').'</button>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<span class="text-xs text-gray-400">Artists only</span>';
            }

            echo '</div>';

            echo '</div></article>';
        }
        echo '</div>';
    else:
        echo '<div class="bg-white border border-gray-300 p-10 rounded-2xl shadow-md text-center text-gray-500">
                No posts yet. Share your first artwork above.
              </div>';
    endif;
    ?>

</main>
</div>
</body>
</html>
