<?php
session_start();
require 'connection.php';
include 'activity_logger.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$username = $_SESSION['username'] ?? 'User';
$mysqli = new mysqli("localhost","root","","artlab_db");
if ($mysqli->connect_error) die('DB error');

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
logPageView($conn, $_SESSION['user_id'], 'Collaborations', 'user_activity_logs');

$mysqli->query("CREATE TABLE IF NOT EXISTS collaborations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_collab_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_collab'])) {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['collab_title'] ?? 'Proposal');
    $message = trim($_POST['collab_message'] ?? '');
    $cstmt = $mysqli->prepare("INSERT INTO collaborations (user_id, title, message) VALUES (?, ?, ?)");
    if ($cstmt) { $cstmt->bind_param('iss', $user_id, $title, $message); $cstmt->execute(); $cstmt->close(); }
    header('Location: user_collaborations.php'); exit();
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Collaborations</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50">
<header class="p-4 bg-gray-800 text-white flex justify-between items-center">
    <div class="flex items-center gap-3">
        <div class="text-lg font-bold">ARTLAB</div>
        <div class="text-sm text-gray-200">Collaborations</div>
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
        <a href="user_artwork.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Artwork</a>
        <a href="user_collaborations.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">Collaborations</a>
        <a href="user_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Marketplace</a>
        <a href="user_activity_logs.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">My Activity Logs</a>
        <a href="user_settings.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Settings</a>
    </nav>
</aside>
<main class="flex-1 p-10 bg-gray-100 min-h-screen">

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-gray-800">Propose a Collaboration</h1>
        <p class="text-gray-500 mt-1">Propose projects and find collaborators.</p>
    </div>

    <!-- Collaboration Form -->
    <form method="POST" class="bg-white border border-gray-300 rounded-2xl p-6 shadow-md hover:shadow-lg mb-10 max-w-3xl transition-shadow">

        <input name="collab_title" required placeholder="Title"
               class="w-full mb-4 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none transition">

        <textarea name="collab_message" rows="5" placeholder="Describe your idea..."
                  class="w-full mb-4 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 outline-none transition"></textarea>

        <button name="submit_collab"
                class="bg-yellow-600 hover:bg-yellow-500 text-white px-6 py-2 rounded-lg font-medium transition">
            Send Proposal
        </button>
    </form>

    <!-- Proposals List -->
    <h2 class="text-2xl font-semibold text-gray-800 mb-4">My Proposals</h2>

    <div class="space-y-6">
    <?php
    $uid = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT title,message,status,created_at FROM collaborations WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    if ($stmt) {
        $stmt->bind_param('i',$uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            while ($r = $res->fetch_assoc()) {
                echo '<div class="bg-white border border-gray-300 p-5 rounded-2xl shadow-md hover:shadow-xl transition">';
                echo '<div class="font-semibold text-gray-800">'.htmlspecialchars($r['title']).' <span class="text-xs text-gray-500">('.htmlspecialchars($r['status']).')</span></div>';
                echo '<div class="text-gray-700 text-sm mt-2">'.nl2br(htmlspecialchars($r['message'])).'</div>';
                echo '<div class="text-xs text-gray-400 mt-3">'.htmlspecialchars($r['created_at']).'</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="bg-white border border-gray-300 p-6 rounded-2xl shadow text-center text-gray-500">
                    No proposals yet. Create one using the form above.
                  </div>';
        }
        $stmt->close();
    }
    ?>
    </div>

</main>
</div>
</body>
</html>