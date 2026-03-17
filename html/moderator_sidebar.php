<?php
// Moderator Sidebar Component
// Include this in all moderator pages for consistent sidebar display

// Determine current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB ADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4 object-cover">
        <h4 class="text-white font-semibold"><?php echo htmlspecialchars($username ?? 'Admin'); ?></h4>
        <p class="text-gray-400 text-sm">Admin</p>
    </div>

    <nav class="flex flex-col gap-4 mt-8">
        <a href="moderator_dashboard.php" class="sidebar-link <?php echo ($current_page === 'moderator_dashboard.php') ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">User Management</a>
        <a href="moderator_flag_review.php" class="sidebar-link <?php echo ($current_page === 'moderator_flag_review.php') ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Flag Review</a>
        <a href="moderator_marketplace.php" class="sidebar-link <?php echo ($current_page === 'moderator_marketplace.php') ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Marketplace</a>
        <a href="moderator_activity_logs.php" class="sidebar-link <?php echo ($current_page === 'moderator_activity_logs.php') ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">My Activity Logs</a>
        <a href="moderator_settings.php" class="sidebar-link <?php echo ($current_page === 'moderator_settings.php') ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Settings</a>
    </nav>
</aside>
