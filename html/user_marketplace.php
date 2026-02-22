<?php
session_start();
require 'connection.php';
include 'activity_logger.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$mysqli = new mysqli("localhost","root","","artlab_db");
if ($mysqli->connect_error) die('DB error');
$username = $_SESSION['username'] ?? 'User';

// Like/Report settings
$canLikeReport = ($_SESSION['role'] ?? '') === 'artist';
$reportThreshold = 3;
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

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
logPageView($conn, $_SESSION['user_id'], 'Marketplace', 'user_activity_logs');

// Create marketplace table if it doesn't exist
$mysqli->query("CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(9) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    image_filename VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_items (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure like/report tables for marketplace items
$mysqli->query("CREATE TABLE IF NOT EXISTS marketplace_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marketplace_like (item_id, user_id),
    INDEX idx_marketplace_like_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS marketplace_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marketplace_report (item_id, reporter_id),
    INDEX idx_marketplace_report_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure like/report tables for posts used in marketplace list
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

// Handle like/unlike for marketplace items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_marketplace_id'])) {
    if ($canLikeReport) {
        $item_id = (int)$_POST['like_marketplace_id'];
        $check = $mysqli->prepare("SELECT id FROM marketplace_likes WHERE item_id = ? AND user_id = ?");
        if ($check) {
            $check->bind_param('ii', $item_id, $currentUserId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                $del = $mysqli->prepare("DELETE FROM marketplace_likes WHERE item_id = ? AND user_id = ?");
                if ($del) {
                    $del->bind_param('ii', $item_id, $currentUserId);
                    $del->execute();
                    $del->close();
                }
            } else {
                $ins = $mysqli->prepare("INSERT INTO marketplace_likes (item_id, user_id) VALUES (?, ?)");
                if ($ins) {
                    $ins->bind_param('ii', $item_id, $currentUserId);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }
    header('Location: user_marketplace.php');
    exit();
}

// Handle report for marketplace items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_marketplace_id'])) {
    if ($canLikeReport) {
        $item_id = (int)$_POST['report_marketplace_id'];
        $reason = trim($_POST['report_reason'] ?? '');
        if ($reason === '') {
            $reason = 'Unspecified';
        }
        $rep = $mysqli->prepare("INSERT IGNORE INTO marketplace_reports (item_id, reporter_id, reason) VALUES (?, ?, ?)");
        if ($rep) {
            $rep->bind_param('iis', $item_id, $currentUserId, $reason);
            $rep->execute();
            $rep->close();
        }
    }
    header('Location: user_marketplace.php');
    exit();
}

// Handle like/unlike for posts shown in marketplace list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_post_id'])) {
    if ($canLikeReport) {
        $post_id = (int)$_POST['like_post_id'];
        $check = $mysqli->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        if ($check) {
            $check->bind_param('ii', $post_id, $currentUserId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                $del = $mysqli->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                if ($del) {
                    $del->bind_param('ii', $post_id, $currentUserId);
                    $del->execute();
                    $del->close();
                }
            } else {
                $ins = $mysqli->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                if ($ins) {
                    $ins->bind_param('ii', $post_id, $currentUserId);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }
    header('Location: user_marketplace.php');
    exit();
}

// Handle report for posts shown in marketplace list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_post_id'])) {
    if ($canLikeReport) {
        $post_id = (int)$_POST['report_post_id'];
        $reason = trim($_POST['report_reason'] ?? '');
        if ($reason === '') {
            $reason = 'Unspecified';
        }
        $rep = $mysqli->prepare("INSERT IGNORE INTO post_reports (post_id, reporter_id, reason) VALUES (?, ?, ?)");
        if ($rep) {
            $rep->bind_param('iis', $post_id, $currentUserId, $reason);
            $rep->execute();
            $rep->close();
        }
    }
    header('Location: user_marketplace.php');
    exit();
}

$uploadMessage = '';
$uploadError = '';

// Handle marketplace item upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_item'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $image_filename = null;
    
    if (empty($title)) {
        $uploadError = 'Title is required.';
    } else if (!empty($_FILES['image']['name'])) {
        $uploads_dir = __DIR__ . '/../images/uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        
        $file = $_FILES['image'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $maxFileSize = 5 * 1024 * 1024;
        
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if ($fileSize > $maxFileSize) {
            $uploadError = 'File size must not exceed 5MB.';
        } elseif (!in_array($fileExtension, $allowedExtensions)) {
            $uploadError = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);
            
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $uploadError = 'Invalid image file.';
            } else {
                $uniqueFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                $uploadPath = $uploads_dir . '/' . $uniqueFileName;
                
                if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                    $image_filename = $uniqueFileName;
                } else {
                    $uploadError = 'Failed to save the image.';
                }
            }
        }
    }
    
    if (empty($uploadError)) {
        $stmt = $mysqli->prepare("INSERT INTO marketplace_items (user_id, title, description, price, image_filename) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssds', $_SESSION['user_id'], $title, $description, $price, $image_filename);
            if ($stmt->execute()) {
                $uploadMessage = 'Item listed successfully!';
                // Log the marketplace activity
                logMarketplaceActivity($conn, $_SESSION['user_id'], 'listed item', $title);
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .image-preview-container {
            width: 100%;
            margin-top: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9fafb;
            box-sizing: border-box;
            display: none;
        }
        
        .image-preview-container.active {
            display: block;
        }
        
        .uploaded-image {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3b82f6;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .file-input-label:hover {
            background-color: #2563eb;
        }
        
        .file-name-display {
            display: inline-block;
            margin-left: 10px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-gray-50">
<header class="p-4 bg-gray-800 text-white flex justify-between items-center">
    <div class="flex items-center gap-3">
        <div class="text-lg font-bold">ARTLAB</div>
        <div class="text-sm text-gray-200">Marketplace</div>
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
        <a href="user_collaborations.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Collaborations</a>
        <a href="user_marketplace.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">Marketplace</a>
        <a href="user_activity_logs.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">My Activity Logs</a>
        <a href="user_settings.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Settings</a>
    </nav>
</aside>
<main class="flex-1 p-6">
    <h1 class="text-2xl font-bold mb-6">Marketplace</h1>
    
    <!-- Create Listing Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8 max-w-2xl">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">List an Item for Sale</h2>
        
        <?php if ($uploadMessage): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($uploadMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($uploadError): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($uploadError); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Item Title *</label>
                <input 
                    type="text" 
                    name="title" 
                    id="title" 
                    placeholder="What are you selling?"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            
            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea 
                    name="description" 
                    id="description" 
                    placeholder="Describe your item..."
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                ></textarea>
            </div>
            
            <!-- Price -->
            <div>
                <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Price ($)</label>
                <input 
                    type="number" 
                    name="price" 
                    id="price" 
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            
            <!-- Image Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Item Image</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition">
                    <div class="file-input-wrapper">
                        <input 
                            type="file" 
                            name="image" 
                            id="marketplaceImage" 
                            accept="image/*"
                        >
                        <label for="marketplaceImage" class="file-input-label">
                            Choose Image
                        </label>
                    </div>
                    <p class="file-name-display" id="marketplaceFileName">No file selected</p>
                    <p class="text-gray-500 text-sm mt-2">JPG, PNG, or GIF (Max 5MB)</p>
                </div>
                
                <!-- Image Preview -->
                <div id="marketplacePreviewContainer" class="image-preview-container">
                    <p class="text-gray-600 text-sm mb-2">Preview:</p>
                    <img id="marketplacePreviewImage" alt="Preview" class="uploaded-image" src="">
                </div>
            </div>
            
            <button type="submit" name="submit_item" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition">
                List Item
            </button>
        </form>
    </div>
    
    <!-- Marketplace Items -->
    <h2 class="text-xl font-semibold text-gray-800 mb-4">All Listings</h2>
    <!-- Marketplace Items -->
    <h2 class="text-xl font-semibold text-gray-800 mb-4">All Listings</h2>
    <?php
    // Fetch marketplace items
    $q = "SELECT id, user_id, title, description, price, image_filename, created_at FROM marketplace_items ORDER BY created_at DESC LIMIT 48";
    $res = $mysqli->query($q);
    
    $items = [];
    if ($res && $res->num_rows) {
        while ($row = $res->fetch_assoc()) {
            $row['source'] = 'marketplace';
            $items[] = $row;
        }
    }
    
    // Also fetch old posts from posts table
    $q_posts = "SELECT p.*, u.username, u.email FROM posts p JOIN registered_users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 48";
    $res_posts = $mysqli->query($q_posts);
    
    if ($res_posts && $res_posts->num_rows) {
        while ($row = $res_posts->fetch_assoc()) {
            $items[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'title' => $row['title'],
                'description' => $row['content'],
                'price' => null,
                'image_filename' => $row['file_path'],
                'created_at' => $row['created_at'],
                'username' => $row['username'],
                'email' => $row['email'],
                'source' => 'post'
            ];
        }
    }
    
    if (!empty($items)) {
        usort($items, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">';

        $likeCountMarketplace = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM marketplace_likes WHERE item_id = ?");
        $reportCountMarketplace = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM marketplace_reports WHERE item_id = ?");
        $likedByMeMarketplace = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM marketplace_likes WHERE item_id = ? AND user_id = ?");
        $reportedByMeMarketplace = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM marketplace_reports WHERE item_id = ? AND reporter_id = ?");

        $likeCountPost = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM post_likes WHERE post_id = ?");
        $reportCountPost = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM post_reports WHERE post_id = ?");
        $likedByMePost = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM post_likes WHERE post_id = ? AND user_id = ?");
        $reportedByMePost = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM post_reports WHERE post_id = ? AND reporter_id = ?");

        foreach ($items as $row) {
            $like_count = 0;
            $report_count = 0;
            $liked_by_me = false;
            $reported_by_me = false;

            if ($row['source'] === 'marketplace') {
                $item_id = (int)$row['id'];

                $likeCountMarketplace->bind_param('i', $item_id);
                $likeCountMarketplace->execute();
                $like_count = (int)($likeCountMarketplace->get_result()->fetch_assoc()['cnt'] ?? 0);

                $reportCountMarketplace->bind_param('i', $item_id);
                $reportCountMarketplace->execute();
                $report_count = (int)($reportCountMarketplace->get_result()->fetch_assoc()['cnt'] ?? 0);

                $likedByMeMarketplace->bind_param('ii', $item_id, $currentUserId);
                $likedByMeMarketplace->execute();
                $liked_by_me = (int)($likedByMeMarketplace->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;

                $reportedByMeMarketplace->bind_param('ii', $item_id, $currentUserId);
                $reportedByMeMarketplace->execute();
                $reported_by_me = (int)($reportedByMeMarketplace->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
            } else {
                $post_id = (int)$row['id'];

                $likeCountPost->bind_param('i', $post_id);
                $likeCountPost->execute();
                $like_count = (int)($likeCountPost->get_result()->fetch_assoc()['cnt'] ?? 0);

                $reportCountPost->bind_param('i', $post_id);
                $reportCountPost->execute();
                $report_count = (int)($reportCountPost->get_result()->fetch_assoc()['cnt'] ?? 0);

                $likedByMePost->bind_param('ii', $post_id, $currentUserId);
                $likedByMePost->execute();
                $liked_by_me = (int)($likedByMePost->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;

                $reportedByMePost->bind_param('ii', $post_id, $currentUserId);
                $reportedByMePost->execute();
                $reported_by_me = (int)($reportedByMePost->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
            }

            if ($report_count >= $reportThreshold) {
                continue;
            }

            echo '<div class="bg-white p-3 rounded shadow hover:shadow-md transition">';
            
            // Display image
            if (!empty($row['image_filename'])) {
                if (strpos($row['image_filename'], 'images/uploads/') !== false) {
                    // Old format from posts table
                    $imageSrc = '/' . htmlspecialchars($row['image_filename']);
                } else {
                    // New format from marketplace_items table
                    $imageSrc = '/Security2/images/uploads/' . htmlspecialchars($row['image_filename']);
                }
                echo '<div class="mb-3 overflow-hidden rounded"><img src="' . $imageSrc . '" class="w-full h-40 object-cover" alt="" /></div>';
            }
            
            echo '<h3 class="font-semibold">' . htmlspecialchars($row['title']) . '</h3>';
            
            if (!empty($row['price'])) {
                echo '<p class="text-lg font-bold text-green-600">$' . number_format($row['price'], 2) . '</p>';
            }
            
            echo '<p class="text-sm text-gray-700 mt-1">' . nl2br(htmlspecialchars(mb_strimwidth($row['description'] ?? '', 0, 150, '...'))) . '</p>';
            
            $username = $row['username'] ?? 'Unknown';
            echo '<div class="text-xs text-gray-500 mt-2">By ' . htmlspecialchars($username) . ' • ' . htmlspecialchars($row['created_at']) . '</div>';
            
            if (!empty($row['email'])) {
                echo '<div class="mt-3"><a href="mailto:' . htmlspecialchars($row['email']) . '?subject=Interest%20in%20' . rawurlencode($row['title']) . '" class="text-blue-600 underline">Contact seller</a></div>';
            }

            echo '<div class="mt-3 flex items-center justify-between text-sm">';
            echo '<div class="text-gray-500">❤ ' . htmlspecialchars((string)$like_count) . ' • ⚑ ' . htmlspecialchars((string)$report_count) . '</div>';

            if ($canLikeReport) {
                echo '<div class="flex items-center gap-2">';
                if ($row['source'] === 'marketplace') {
                    echo '<form method="POST">';
                    echo '<input type="hidden" name="like_marketplace_id" value="' . (int)$row['id'] . '">';
                    echo '<button type="submit" class="px-3 py-1 rounded text-xs ' . ($liked_by_me ? 'bg-gray-200 text-gray-700' : 'bg-yellow-600 text-white') . '">' . ($liked_by_me ? 'Unlike' : 'Like') . '</button>';
                    echo '</form>';

                    echo '<form method="POST" class="flex items-center gap-2">';
                    echo '<input type="hidden" name="report_marketplace_id" value="' . (int)$row['id'] . '">';
                    echo '<select name="report_reason" class="border border-gray-300 rounded px-2 py-1 text-xs" ' . ($reported_by_me ? 'disabled' : '') . '>';
                    echo '<option value="Spam">Spam</option>';
                    echo '<option value="Inappropriate">Inappropriate</option>';
                    echo '<option value="Copyright">Copyright</option>';
                    echo '<option value="Other">Other</option>';
                    echo '</select>';
                    echo '<button type="submit" class="px-3 py-1 rounded text-xs ' . ($reported_by_me ? 'bg-gray-200 text-gray-500' : 'bg-red-600 text-white') . '" ' . ($reported_by_me ? 'disabled' : '') . '>' . ($reported_by_me ? 'Reported' : 'Report') . '</button>';
                    echo '</form>';
                } else {
                    echo '<form method="POST">';
                    echo '<input type="hidden" name="like_post_id" value="' . (int)$row['id'] . '">';
                    echo '<button type="submit" class="px-3 py-1 rounded text-xs ' . ($liked_by_me ? 'bg-gray-200 text-gray-700' : 'bg-yellow-600 text-white') . '">' . ($liked_by_me ? 'Unlike' : 'Like') . '</button>';
                    echo '</form>';

                    echo '<form method="POST" class="flex items-center gap-2">';
                    echo '<input type="hidden" name="report_post_id" value="' . (int)$row['id'] . '">';
                    echo '<select name="report_reason" class="border border-gray-300 rounded px-2 py-1 text-xs" ' . ($reported_by_me ? 'disabled' : '') . '>';
                    echo '<option value="Spam">Spam</option>';
                    echo '<option value="Inappropriate">Inappropriate</option>';
                    echo '<option value="Copyright">Copyright</option>';
                    echo '<option value="Other">Other</option>';
                    echo '</select>';
                    echo '<button type="submit" class="px-3 py-1 rounded text-xs ' . ($reported_by_me ? 'bg-gray-200 text-gray-500' : 'bg-red-600 text-white') . '" ' . ($reported_by_me ? 'disabled' : '') . '>' . ($reported_by_me ? 'Reported' : 'Report') . '</button>';
                    echo '</form>';
                }
                echo '</div>';
            } else {
                echo '<span class="text-xs text-gray-400">Artists only</span>';
            }

            echo '</div>';
            
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="bg-white p-6 rounded shadow text-center text-gray-600">No marketplace listings yet. Create one above!</div>';
    }
    ?>
</main>
</div>

<script>
document.getElementById('marketplaceImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const previewContainer = document.getElementById('marketplacePreviewContainer');
    const previewImage = document.getElementById('marketplacePreviewImage');
    const fileNameDisplay = document.getElementById('marketplaceFileName');
    
    if (file) {
        fileNameDisplay.textContent = file.name;
        
        const reader = new FileReader();
        reader.onload = function(event) {
            previewImage.src = event.target.result;
            previewContainer.classList.add('active');
        };
        reader.readAsDataURL(file);
    } else {
        fileNameDisplay.textContent = 'No file selected';
        previewContainer.classList.remove('active');
    }
});
</script>
</body>
</html>