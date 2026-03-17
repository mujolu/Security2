<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? '';
$username = $_SESSION['username'] ?? 'Admin';

logActivity($conn, $user_id, 'Accessed Marketplace module (Admin)', 'moderator_activity_logs');

$selectedArtist = trim($_GET['artist_id'] ?? '');
$artists = [];
$posts = [];
$listings = [];

function normalizeImagePathModerator($path) {
    $imagePath = (string)$path;
    $imagePath = preg_replace('#^https?://[^/]+/#i', '', $imagePath);
    $imagePath = ltrim($imagePath, '/');
    if (strpos($imagePath, 'Security2/') === 0) {
        $imagePath = substr($imagePath, strlen('Security2/'));
    }
    if (strpos($imagePath, 'images/uploads/') !== 0) {
        $imagePath = 'images/uploads/' . basename($imagePath);
    }
    return '/Security2/' . $imagePath;
}

try {
    $artists_stmt = $conn->prepare("SELECT
            u.id,
            u.username,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS full_name,
            (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS posts_count,
            (SELECT COUNT(*) FROM marketplace_items m WHERE m.user_id = u.id) AS listings_count
        FROM registered_users u
        WHERE u.role = 'artist'
        ORDER BY u.username ASC");
    $artists_stmt->execute();
    $artists = $artists_stmt->fetchAll(PDO::FETCH_ASSOC);

    $posts_sql = "SELECT p.id, p.user_id, p.title, p.content, p.file_path, p.created_at, u.username
                  FROM posts p
                  JOIN registered_users u ON u.id = p.user_id";
    $posts_params = [];
    if ($selectedArtist !== '') {
        $posts_sql .= " WHERE p.user_id = ?";
        $posts_params[] = $selectedArtist;
    }
    $posts_sql .= " ORDER BY p.created_at DESC LIMIT 100";
    $posts_stmt = $conn->prepare($posts_sql);
    $posts_stmt->execute($posts_params);
    $posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

    $listings_sql = "SELECT m.id, m.user_id, m.title, m.description, m.price, m.image_filename, m.created_at, u.username
                     FROM marketplace_items m
                     JOIN registered_users u ON u.id = m.user_id";
    $listings_params = [];
    if ($selectedArtist !== '') {
        $listings_sql .= " WHERE m.user_id = ?";
        $listings_params[] = $selectedArtist;
    }
    $listings_sql .= " ORDER BY m.created_at DESC LIMIT 100";
    $listings_stmt = $conn->prepare($listings_sql);
    $listings_stmt->execute($listings_params);
    $listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Marketplace - Admin</title>
</head>
<body class="bg-gray-100 min-h-screen">
<div class="layout flex w-full min-h-screen">

<?php include 'moderator_sidebar.php'; ?>

<main class="flex-1 p-6">
    <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
        <h3 class="text-xl font-bold">Marketplace</h3>
        <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
    </header>

    <section class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4">Artist Directory</h2>
        <form method="GET" class="mb-4 flex gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Artist</label>
                <select name="artist_id" class="border rounded px-3 py-2 min-w-72">
                    <option value="">All Artists</option>
                    <?php foreach ($artists as $artist): ?>
                        <option value="<?= htmlspecialchars($artist['id']) ?>" <?= $selectedArtist === $artist['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($artist['full_name'] ?: $artist['username']) . ' (' . $artist['username'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Apply</button>
            <a href="moderator_marketplace.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Reset</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-3 text-left">Artist</th>
                        <th class="p-3 text-left">Username</th>
                        <th class="p-3 text-left">Posts</th>
                        <th class="p-3 text-left">Listings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artists as $artist): ?>
                        <tr class="border-b">
                            <td class="p-3"><?= htmlspecialchars($artist['full_name'] ?: 'N/A') ?></td>
                            <td class="p-3"><?= htmlspecialchars($artist['username']) ?></td>
                            <td class="p-3"><?= (int)$artist['posts_count'] ?></td>
                            <td class="p-3"><?= (int)$artist['listings_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-xl font-bold mb-4">Posts</h3>
        <?php if (!empty($posts)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($posts as $post): ?>
                    <div class="border rounded-lg p-4">
                        <?php
                        $attachmentPath = trim((string)($post['file_path'] ?? ''));
                        $isImageAttachment = false;
                        if ($attachmentPath !== '') {
                            $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
                            $isImageAttachment = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        }
                        ?>
                        <?php if ($attachmentPath !== '' && $isImageAttachment): ?>
                            <img src="<?= htmlspecialchars($attachmentPath) ?>" class="w-full h-40 object-cover rounded mb-3" alt="post preview">
                        <?php endif; ?>
                        <div class="text-xs text-gray-500 mb-1">By <?= htmlspecialchars($post['username']) ?> • <?= htmlspecialchars($post['created_at']) ?></div>
                        <h4 class="font-semibold mb-2"><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></h4>
                        <p class="text-sm text-gray-700 mb-2"><?= nl2br(htmlspecialchars(mb_strimwidth($post['content'] ?? '', 0, 160, '...'))) ?></p>
                        <?php if ($attachmentPath !== ''): ?>
                            <a class="text-blue-600 underline text-sm" target="_blank" href="<?= htmlspecialchars($attachmentPath) ?>">View Attachment</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No posts found for the selected filter.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold mb-4">Marketplace Listings</h3>
        <?php if (!empty($listings)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($listings as $item): ?>
                    <div class="border rounded-lg p-4">
                        <?php if (!empty($item['image_filename'])): ?>
                            <img src="<?= htmlspecialchars(normalizeImagePathModerator($item['image_filename'])) ?>" class="w-full h-40 object-cover rounded mb-3" alt="listing image">
                        <?php endif; ?>
                        <div class="text-xs text-gray-500 mb-1">By <?= htmlspecialchars($item['username']) ?> • <?= htmlspecialchars($item['created_at']) ?></div>
                        <h4 class="font-semibold mb-2"><?= htmlspecialchars($item['title'] ?? 'Untitled') ?></h4>
                        <p class="text-sm text-gray-700 mb-2"><?= nl2br(htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 160, '...'))) ?></p>
                        <p class="font-bold text-green-700">₱<?= number_format((float)($item['price'] ?? 0), 2) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No listings found for the selected filter.</p>
        <?php endif; ?>
    </section>
</main>
</div>
</body>
</html>
