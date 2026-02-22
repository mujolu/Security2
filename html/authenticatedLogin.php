<?php
// simple redirect to artwork module
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

header('Location: user_artwork.php');
exit();

// --- User activity & posts setup ---
// Use mysqli here to match the rest of this file
$mysqli = new mysqli("localhost","root","","artlab_db");
if ($mysqli->connect_error) {
    // DB unavailable - proceed without blocking UI
} else {
    // Create per-user activity log table and posts table if they don't exist
    $mysqli->query("CREATE TABLE IF NOT EXISTS user_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
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

    $mysqli->query("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        file_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_posts (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // collaborations table for proposals
    $mysqli->query("CREATE TABLE IF NOT EXISTS collaborations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_collab_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function logUserActivity($mysqli, $user_id, $activity, $time_in = null, $time_out = null) {
    if (!$mysqli || $mysqli->connect_error) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $ip = '127.0.0.1';
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
        $login_result = $mysqli->query("SELECT login_time FROM login_logs WHERE login_id = " . intval($_SESSION['login_log_id']));
        if ($login_result && $login_row = $login_result->fetch_assoc()) {
            $time_in = $login_row['login_time'];
        }
    }
    
    $stmt = $mysqli->prepare("INSERT INTO user_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('issssss', $user_id, $activity, $ip, $device, $os, $time_in, $time_out);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle user post submission (upload or text post)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? 'Untitled');
    $content = trim($_POST['content'] ?? '');
    $file_path = null;

    if (!empty($_FILES['post_file']['name'] ?? '')) {
        $uploads_dir = dirname(__DIR__) . '/images/uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $original = basename($_FILES['post_file']['name']);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($original, PATHINFO_FILENAME));
        $target_name = $safe . '_' . time() . ($ext ? '.' . $ext : '');
        $target_path = $uploads_dir . '/' . $target_name;

        if (move_uploaded_file($_FILES['post_file']['tmp_name'], $target_path)) {
            // save relative path for serving
            $file_path = 'images/uploads/' . $target_name;
        }
    }

    if ($mysqli && !$mysqli->connect_error) {
        $stmt = $mysqli->prepare("INSERT INTO posts (user_id, title, content, file_path) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $user_id, $title, $content, $file_path);
            $stmt->execute();
            $stmt->close();
            logUserActivity($mysqli, $user_id, "Created post: $title");
        }
    }

    header('Location: authenticatedLogin.php');
    exit();
}

// Handle collaboration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_collab'])) {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['collab_title'] ?? 'Proposal');
    $message = trim($_POST['collab_message'] ?? '');

    if ($mysqli && !$mysqli->connect_error) {
        $cstmt = $mysqli->prepare("INSERT INTO collaborations (user_id, title, message) VALUES (?, ?, ?)");
        if ($cstmt) {
            $cstmt->bind_param('iss', $user_id, $title, $message);
            $cstmt->execute();
            $cstmt->close();
            logUserActivity($mysqli, $user_id, "Submitted collaboration: $title");
        }
    }

    header('Location: authenticatedLogin.php');
    exit();
}


// Get the username safely
$username = $_SESSION['username'];
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
    <!-- Sidebar -->

    <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
        <h2 class="text-2xl font-bold text-white mb-5">ARTLAB</h2>

        <div class="flex flex-col items-center text-center mt-8">
            <img src="/Security2/images/profilepic.jpg" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-yellow-500 shadow-md mb-4">
            <h4 class="text-white font-semibold mb-3">Welcome, <?php echo htmlspecialchars($username); ?>!</h4>
        </div>

        <nav class="flex flex-col gap-4 mt-8">
                <a href="#" data-section="artworkgallery"
                    class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">
                Artwork Gallery
            </a>
                <a href="#" data-section="createPost"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Create Post
            </a>
                <a href="#" data-section="collaborations"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Collaborations
            </a>
                <a href="#" data-section="myactivity"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                My Activity
            </a>
                <a href="#" data-section="marketplace"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Marketplace
            </a>
                <a href="#" data-section="sales"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Sales and Earnings
            </a>
                <a href="#" data-section="collectorRequests"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Collector Account Request
            </a>
                <a href="#" data-section="settings"
                    class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Settings
            </a>
        </nav>
    </aside>


<!-- Main Content -->
    <main class="flex-1 p-6 bg-gray-100">

        <!-- Header -->
        <header class="bg-gray-800 text-white rounded-xl shadow-md p-8 mb-5 relative flex justify-between items-center mt-10">
            <h3 class="text-xl font-bold z-10 relative">
                Welcome to <span class="text-yellow-500 brand-sketchy">ARTLAB!</span>
            </h3>
            <a href="logOut.php" class="bg-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-yellow-700 transition-colors duration-300 z-10 relative">
                Logout
            </a>
            <img src="/Security2/images/welcome png.png" alt="Welcoming artist"
                 class="absolute left-1/2 -translate-x-[98%] -bottom-14 h-48 md:h-56 lg:h-64 opacity-95 drop-shadow-xl z-0 pointer-events-none"/>
        </header>

        <!--ArtworkGallery Section -->
        <section id="artworkgallery" class="">
            <?php
            if (isset($mysqli) && !$mysqli->connect_error) {
                $q = "SELECT p.id, p.title, p.content, p.file_path, p.created_at, u.username, u.id as author_id
                      FROM posts p
                      JOIN registered_users u ON p.user_id = u.id
                      ORDER BY p.created_at DESC
                      LIMIT 24";
                $res = $mysqli->query($q);
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-8">';
                while ($row = $res->fetch_assoc()) {
                    $img = $row['file_path'] ? '/'.htmlspecialchars($row['file_path']) : '';
                    echo '<div class="bg-white rounded-xl shadow p-4 hover:shadow-lg cursor-pointer post-card" data-id="'.htmlspecialchars($row['id']).'">';
                    if ($img) echo '<img src="'.htmlspecialchars($img).'" class="w-full h-40 object-cover rounded mb-3" alt="">';
                    echo '<h4 class="font-semibold">'.htmlspecialchars($row['title']).'</h4>';
                    echo '<p class="text-sm text-gray-700">'.nl2br(htmlspecialchars(mb_strimwidth($row['content'],0,200,'...'))).'</p>';
                    echo '<div class="text-xs text-gray-500 mt-2">By '.htmlspecialchars($row['username']).' • '.htmlspecialchars($row['created_at']).'</div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p class="text-gray-600">No posts to show.</p>';
            }
            ?>
        </section>

        <!-- Collaborations Section -->
        <section id="collaborations" class="hidden bg-white p-6 rounded-xl shadow-lg mx-auto max-w-3xl mb-8">
            <h2 class="text-2xl font-bold mb-4">Collaborations</h2>
            <form method="POST" class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Proposal Title</label>
                    <input type="text" name="collab_title" required class="mt-1 block w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="collab_message" rows="5" class="mt-1 block w-full border rounded px-3 py-2"></textarea>
                </div>
                <div>
                    <button type="submit" name="submit_collab" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">Send Proposal</button>
                </div>
            </form>

            <h3 class="text-lg font-semibold mb-3">My Proposals</h3>
            <?php
            if (isset($mysqli) && !$mysqli->connect_error) {
                $uid = $_SESSION['user_id'];
                $cstmt = $mysqli->prepare("SELECT title, message, status, created_at FROM collaborations WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
                if ($cstmt) {
                    $cstmt->bind_param('i', $uid);
                    $cstmt->execute();
                    $cres = $cstmt->get_result();
                    while ($crow = $cres->fetch_assoc()) {
                        echo '<div class="p-3 border rounded mb-3">';
                        echo '<div class="font-semibold">'.htmlspecialchars($crow['title']).' <span class="text-xs text-gray-500">('.htmlspecialchars($crow['status']).')</span></div>';
                        echo '<div class="text-sm text-gray-700">'.nl2br(htmlspecialchars($crow['message'])).'</div>';
                        echo '<div class="text-xs text-gray-500 mt-2">'.htmlspecialchars($crow['created_at']).'</div>';
                        echo '</div>';
                    }
                    $cstmt->close();
                }
            }
            ?>
        </section>

        <!-- marketplace Section -->
        <section id="marketplace" class="hidden bg-white p-6 rounded-xl shadow-lg mx-auto max-w-5xl mb-8">
            <h2 class="text-2xl font-bold mb-4">Marketplace</h2>
            <?php
            if (isset($mysqli) && !$mysqli->connect_error) {
                $q = "SELECT p.id, p.title, p.content, p.file_path, p.created_at, u.username, u.email FROM posts p JOIN registered_users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 48";
                $res = $mysqli->query($q);
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">';
                while ($row = $res->fetch_assoc()) {
                    $img = $row['file_path'] ? '/'.htmlspecialchars($row['file_path']) : '';
                    echo '<div class="bg-white rounded-xl shadow p-4">';
                    if ($img) echo '<img src="'.htmlspecialchars($img).'" class="w-full h-40 object-cover rounded mb-3" alt="">';
                    echo '<h4 class="font-semibold">'.htmlspecialchars($row['title']).'</h4>';
                    echo '<p class="text-sm text-gray-700">'.nl2br(htmlspecialchars(mb_strimwidth($row['content'],0,150,'...'))).'</p>';
                    echo '<div class="text-xs text-gray-500 mt-2">By '.htmlspecialchars($row['username']).' • '.htmlspecialchars($row['created_at']).'</div>';
                    echo '<div class="mt-3"><a href="mailto:'.htmlspecialchars($row['email']).'?subject=Interest%20in%20your%20art%20('.rawurlencode($row['title']).')" class="text-blue-600 underline">Contact seller</a></div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p class="text-gray-600">Marketplace not available.</p>';
            }
            ?>
        </section>

        <!-- Sales and Earnings Section (hidden) -->
        <section id="sales" class="hidden">
            <h2 class="text-2xl font-bold mb-4">Sales and Earnings</h2>
            <p>Sales and Earnings section coming soon...</p>
        </section>
        
        <!-- Create Post Section -->
        <section id="createPost" class="hidden bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">
            <h2 class="text-2xl font-bold mb-4">Create a Post</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" required class="mt-1 block w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea name="content" rows="6" class="mt-1 block w-full border rounded px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Attach File (optional)</label>
                    <input type="file" name="post_file" accept="image/*,video/*,audio/*" class="mt-1" />
                </div>
                <div>
                    <button type="submit" name="submit_post" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">Publish</button>
                </div>
            </form>
        </section>

        <!-- My Activity Section -->
        <section id="myactivity" class="hidden bg-white p-6 rounded-xl shadow">
            <h2 class="text-2xl font-bold mb-4">My Activity</h2>
            <?php
            if (isset($mysqli) && !$mysqli->connect_error) {
                $uid = $_SESSION['user_id'];
                $logs = $mysqli->prepare("SELECT activity, ip_address, timestamp FROM user_activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
                if ($logs) {
                    $logs->bind_param('i', $uid);
                    $logs->execute();
                    $res = $logs->get_result();
                    echo '<div class="space-y-3">';
                    while ($row = $res->fetch_assoc()) {
                        echo '<div class="p-3 border rounded">';
                        echo '<div class="text-sm text-gray-700">' . htmlspecialchars($row['activity']) . '</div>';
                        echo '<div class="text-xs text-gray-500">' . htmlspecialchars($row['ip_address']) . ' • ' . $row['timestamp'] . '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    $logs->close();
                } else {
                    echo '<p class="text-sm text-gray-600">No activity yet.</p>';
                }

                echo '<h3 class="text-xl font-semibold mt-6 mb-3">My Posts</h3>';
                $pstmt = $mysqli->prepare("SELECT title, content, file_path, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                if ($pstmt) {
                    $pstmt->bind_param('i', $uid);
                    $pstmt->execute();
                    $pres = $pstmt->get_result();
                    while ($prow = $pres->fetch_assoc()) {
                        echo '<div class="p-3 border rounded mb-3">';
                        echo '<h4 class="font-semibold">' . htmlspecialchars($prow['title']) . '</h4>';
                        echo '<p class="text-sm text-gray-700">' . nl2br(htmlspecialchars($prow['content'])) . '</p>';
                        if (!empty($prow['file_path'])) {
                            echo '<div class="mt-2"><a href="/' . htmlspecialchars($prow['file_path']) . '" target="_blank" class="text-blue-600 underline">View attachment</a></div>';
                        }
                        echo '<div class="text-xs text-gray-500 mt-2">' . $prow['created_at'] . '</div>';
                        echo '</div>';
                    }
                    $pstmt->close();
                }
            } else {
                echo '<p class="text-sm text-gray-600">Activity logging not available.</p>';
            }
            ?>
        </section>
<section id="collectorRequests" class="hidden bg-white p-6 rounded-xl shadow">
    <h2 class="text-2xl font-bold mb-4">Request Collector Account</h2>
    <p>Request a Collector account. Admin will verify your details and send login credentials.</p>

    <?php
    $conn = new mysqli("localhost","root","","artlab_db");
    if($conn->connect_error) die("DB error: " . $conn->connect_error);

    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT status FROM role_requests WHERE user_id=$user_id ORDER BY request_id DESC LIMIT 1");
    $status = $result->fetch_assoc()['status'] ?? null;
    $showButton = !$status || $status === 'rejected';

    if($status){
        echo "<p class='mb-4 font-semibold'>Status: <span class='text-yellow-700'>" . ucfirst($status) . "</span></p>";
        if($status=='approved') echo "<p class='text-green-600 font-medium'>You are now a Collector! Check your email for credentials.</p>";
        if($status=='pending') echo "<p class='text-blue-600 font-medium'>Your request is pending admin approval.</p>";
        if($status=='rejected') echo "<p class='text-red-600 font-medium'>Your request was rejected. You can submit again.</p>";
    }

    if($showButton){
        echo '<form method="POST">
                <button name="request_collector" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">
                    Request Collector Account
                </button>
              </form>';
    }
    $conn->close();
    ?>
</section>


        <!-- Settings Section (hidden) -->
        <section id="settings" class="hidden bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">
           <?php include 'user_settings.php'; ?>
        </section>

    </main>
</div>
    <!-- Footer Section -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Magdasal. All rights reserved.</p>
    </footer>


  <script>
// Define a global showSection so inline onclicks or other scripts can call it
window.showSection = function(sectionId, link) {
    const sections = [
        'artworkgallery',
        'createPost',
        'collaborations',
        'marketplace',
        'sales',
        'myactivity',
        'settings',
        'collectorRequests'
    ];

    sections.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });

    const active = document.getElementById(sectionId);
    if (active) active.classList.remove('hidden');

    // Update sidebar link classes
    document.querySelectorAll('.sidebar-link').forEach(l => {
        l.classList.remove('bg-yellow-700');
        l.classList.add('bg-gray-700');
    });

    if (link) {
        link.classList.remove('bg-gray-700');
        link.classList.add('bg-yellow-700');
    }
};

document.addEventListener("DOMContentLoaded", function () {
    // Attach event listeners to sidebar links so clicks call showSection
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const target = link.dataset.section;
        if (!target) return;
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.showSection(target, link);
        });
    });

    // Show default section
    window.showSection('artworkgallery');

    // Simple post-card click handler to show details in a modal-like overlay
    document.addEventListener('click', function(e) {
        const card = e.target.closest('.post-card');
        if (!card) return;
        const title = card.querySelector('h4')?.innerText || '';
        const body = card.querySelector('p')?.innerHTML || '';
        const meta = card.querySelector('.text-xs')?.innerText || '';

        // create overlay
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.left = 0;
        overlay.style.top = 0;
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.background = 'rgba(0,0,0,0.6)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = 9999;

        const cardBox = document.createElement('div');
        cardBox.style.background = '#fff';
        cardBox.style.padding = '20px';
        cardBox.style.borderRadius = '10px';
        cardBox.style.maxWidth = '800px';
        cardBox.style.maxHeight = '80%';
        cardBox.style.overflow = 'auto';
        cardBox.innerHTML = '<h3 style="font-size:20px;margin-bottom:10px">'+title+'</h3>' + body + '<div style="margin-top:12px;color:#666;font-size:12px">'+meta+'</div>';

        overlay.appendChild(cardBox);
        overlay.addEventListener('click', function() { overlay.remove(); });
        document.body.appendChild(overlay);
    });
});
</script>


</body>

</html>
