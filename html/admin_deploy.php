<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$username = $_SESSION['username'] ?? 'platform admin';

// Include the logging function
function createAdminActivityLogsTable($conn) {
    try {
        // Create table with correct schema - IPv4 only (VARCHAR 15)
        // Use IF NOT EXISTS so we don't drop existing logs
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(9) NOT NULL,
            activity VARCHAR(500) NOT NULL,
            ip_address VARCHAR(15) NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist with proper schema
    }
}

function logAdminActivity($conn, $actionType, $details = '') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'platform_admin') return;

    $user_id = $_SESSION['user_id'];
    // Convert IP to IPv4 only
    $ip = $_SERVER['REMOTE_ADDR'];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // If IPv6, try to extract IPv4 or use localhost equivalent
        $ip = '127.0.0.1';
    }
    $ip = substr($ip, 0, 15); // Truncate to IPv4 length for VARCHAR(15)

    $activity = match($actionType) {
        'view' => "Viewed page: $details",
        'delete_user' => "Deleted user ID: $details",
        'delete_moderator' => "Deleted moderator ID: $details",
        'add_moderator' => "Added moderator: $details",
        'approve_artwork' => "Approved artwork ID: $details",
        'edit_user' => "Edited user ID: $details",
        'ban_user' => "Banned user ID: $details",
        'logout' => "Logged out",
        default => $details
    };

    try {
        // Just insert the activity log - table should already exist from createAdminActivityLogsTable()
        $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $activity, $ip]);
    } catch (Exception $e) {
        // If insert fails, try creating table first then insert
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(9) NOT NULL,
                activity VARCHAR(500) NOT NULL,
                ip_address VARCHAR(15) NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Try insert again
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $activity, $ip]);
        } catch (Exception $e2) {
            // Silent fail - don't break the main operation
        }
    }
}

// Create table if it doesn't exist
createAdminActivityLogsTable($conn);

// Track if moderator was just added (from form submission)
$success_message = '';
$error_message = '';

// Handle add moderator form submission - integrate into registered_users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_moderator') {
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username_m = trim($_POST['username'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    if ($firstname && $lastname && $email && $username_m && $password_raw) {
        $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
        $added = false;
        // Try to insert into registered_users with role='moderator'. Fill only commonly used columns; other columns left to DB defaults.
        try {
            $role = 'moderator';
            $stmt = $conn->prepare("INSERT INTO registered_users (first_name, middle_initial, last_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstname, $middlename ?: null, $lastname, $username_m, $email, $password_hash, $role]);
            
            // Log the moderator addition with credentials
            $moderator_info = "$firstname $lastname (Username: $username_m, Email: $email)";
            logAdminActivity($conn, 'add_moderator', $moderator_info);
            $added = true;
        } catch (Exception $e) {
            // If insert fails (schema mismatch), fallback to creating in the moderators table if it exists
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS moderators (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    firstname VARCHAR(100) NOT NULL,
                    middlename VARCHAR(100) DEFAULT NULL,
                    lastname VARCHAR(100) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $alt = $conn->prepare("INSERT INTO moderators (firstname, middlename, lastname, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
                $alt->execute([$firstname, $middlename ?: null, $lastname, $email, $username_m, $password_hash]);
                
                // Log the moderator addition
                $moderator_info = "$firstname $lastname (Username: $username_m, Email: $email)";
                logAdminActivity($conn, 'add_moderator', $moderator_info);
                $added = true;
            } catch (Exception $e2) {
                // give up silently; admin can see DB error logs
                $error_message = "Error adding moderator: " . $e2->getMessage();
            }
        }
        
        if ($added) {
            $success_message = "✓ Moderator '$firstname $lastname' has been successfully added!";
        }
    }
    // Don't redirect - stay on page to show message
}

// Handle moderator deletion - prefer deleting from registered_users role=moderator
if (isset($_GET['delete_moderator'])) {
    $modId = (int)$_GET['delete_moderator'];
    
    // Get moderator info before deleting for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username, email FROM registered_users WHERE id = ? AND role = 'moderator'");
        $info_stmt->execute([$modId]);
        $mod_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mod_info) {
            $mod_details = $mod_info['first_name'] . ' ' . $mod_info['last_name'] . ' (ID:' . $modId . ', Username: ' . $mod_info['username'] . ')';
        }
    } catch (Exception $e) {
        $mod_details = "ID: $modId";
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM registered_users WHERE id = ? AND role = 'moderator'");
        $stmt->execute([$modId]);
        
        // Log the deletion
        logAdminActivity($conn, 'delete_moderator', $mod_details ?? "ID: $modId");
        
        // fallback: if not deleted (maybe stored in moderators table), try there
        if ($stmt->rowCount() === 0) {
            $stmt2 = $conn->prepare("DELETE FROM moderators WHERE id = ?");
            $stmt2->execute([$modId]);
            logAdminActivity($conn, 'delete_moderator', $mod_details ?? "ID: $modId");
        }
    } catch (Exception $e) {
        // ignore
    }
    header('Location: admin_deploy.php');
    exit();
}

// Log page view
logAdminActivity($conn, 'view', 'Deploy Moderators Page');

// Fetch existing moderators from registered_users
$moderators = [];
$debug_message = '';

try {
    // First, check how many moderators exist with role='moderator'
    $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM registered_users WHERE role = 'moderator'");
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $role_count = $count_result['cnt'] ?? 0;
    $debug_message = "Found $role_count moderators in registered_users table";
    
    if ($role_count > 0) {
        // Try fetching from registered_users - use flexible column selection
        try {
            // Try to get all moderators with flexible field mapping
            $mods_stmt = $conn->prepare("
                SELECT 
                    id, 
                    first_name AS firstname, 
                    COALESCE(middle_initial, '') AS middlename, 
                    last_name AS lastname, 
                    email, 
                    username,
                    COALESCE(date_created, CURRENT_TIMESTAMP) AS date_created
                FROM registered_users 
                WHERE role = 'moderator' 
                ORDER BY id DESC
            ");
            $mods_stmt->execute();
            $moderators = $mods_stmt->fetchAll(PDO::FETCH_ASSOC);
            $debug_message .= " | Retrieved " . count($moderators) . " moderators";
        } catch (Exception $e_fetch) {
            // If flexible query fails, try basic query
            try {
                $mods_stmt = $conn->prepare("SELECT id, first_name AS firstname, last_name AS lastname, email, username FROM registered_users WHERE role = 'moderator' ORDER BY id DESC");
                $mods_stmt->execute();
                $moderators = $mods_stmt->fetchAll(PDO::FETCH_ASSOC);
                // Add missing fields with defaults
                foreach ($moderators as &$mod) {
                    $mod['middlename'] = '';
                    $mod['date_created'] = 'N/A';
                }
                $debug_message .= " | Retrieved " . count($moderators) . " moderators (basic query)";
            } catch (Exception $e_basic) {
                $debug_message .= " | Fetch error: " . $e_basic->getMessage();
            }
        }
    } else {
        // Check if there's a separate moderators table
        try {
            $mods_stmt = $conn->query("SELECT COUNT(*) as cnt FROM moderators");
            $mod_count = $mods_stmt->fetchColumn();
            if ($mod_count > 0) {
                $mods_stmt = $conn->query("SELECT id, firstname, middlename, lastname, email, username, date_created FROM moderators ORDER BY id DESC");
                $moderators = $mods_stmt->fetchAll(PDO::FETCH_ASSOC);
                $debug_message .= " | Found $mod_count moderators in moderators table";
            }
        } catch (Exception $e) {
            // No moderators table or other error
        }
    }
} catch (Exception $e) {
    $debug_message = "Database error: " . $e->getMessage();
    $moderators = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Deploy Moderators - Artlab Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #515151; }
        header { background-color: #333; color: white; padding: 10px 0; display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-radius:12px; }
        .logout-btn { padding: 10px 20px; background-color:darkgoldenrod; color:white; text-decoration:none; border-radius:12px; }
        .logout-btn:hover { background-color:gray; }
        .layout { display:flex; min-height:100vh; }
        .sidebar { width:220px; background-color:#333; padding:20px; }
        .logo { font-size:20px; margin-bottom:30px; letter-spacing:2px; }
        .main-content { flex:1; padding:30px; background-color:#ffffff; }
        .brand-sketchy { font-family: 'Permanent Marker', cursive; color: #ff8614; font-weight:500; font-size:30px; letter-spacing:2px; }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<div class="layout flex w-full min-h-screen">

<aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
    <h2 class="text-2xl font-bold text-white mb-5">ARTLAB ADMIN</h2>

    <div class="flex flex-col items-center text-center mt-8">
        <img src="/Security2/images/profilepic.jpg" class="w-24 h-24 rounded-full border-4 border-yellow-500 mb-4">
         <h4 class="text-white font-semibold"><?php echo htmlspecialchars($username); ?></h4>
        <p class="text-gray-400 text-sm">Platform Admin</p>
    </div>

    <nav class="flex flex-col gap-4 mt-8">
        <a href="admin_dashboard.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_deploy.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3">Deploy Moderators</a>
        <a href="admin_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Marketplace Art </a>
        <a href="admin_collab.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Activity Logs</a>
    </nav>
</aside>

<main class="flex-1 p-6">
    <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
        <h3 class="text-xl font-bold">Deploy Moderators</h3>
        <a href="logout.php" class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">Logout</a>
    </header>

    <section class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold">Deploy Moderators</h2>
            <div>
                <button id="showAddBtn" class="bg-green-600 text-white px-4 py-2 rounded mr-2">Add Moderator</button>
                <button onclick="location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded">Refresh List</button>
            </div>
        </div>
        
        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
            <p><?= htmlspecialchars($success_message) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($debug_message)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-3 mb-4 rounded text-sm">
            <p><strong>Debug:</strong> <?= htmlspecialchars($debug_message) ?></p>
        </div>
        <?php endif; ?>

        <div id="addForm" class="hidden mb-6 bg-gray-50 rounded p-4">
            <form method="post" action="admin_deploy.php" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input type="hidden" name="action" value="add_moderator">
                <input name="firstname" placeholder="First name" required class="p-2 border rounded" />
                <input name="middlename" placeholder="Middle name" class="p-2 border rounded" />
                <input name="lastname" placeholder="Last name" required class="p-2 border rounded" />
                <input type="email" name="email" placeholder="Email" required class="p-2 border rounded" />
                <input name="username" placeholder="Username" required class="p-2 border rounded" />
                <input type="password" name="password" placeholder="Password" required class="p-2 border rounded" />
                <div class="md:col-span-3 text-right">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Moderator</button>
                    <button type="button" id="cancelAdd" class="bg-gray-300 text-black px-4 py-2 rounded ml-2">Cancel</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <?php if (!empty($moderators)): ?>
            <table class="w-full border-collapse">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-3 text-left">First name</th>
                        <th class="p-3 text-left">Middle name</th>
                        <th class="p-3 text-left">Last name</th>
                        <th class="p-3 text-left">Email</th>
                        <th class="p-3 text-left">Username</th>
                        <th class="p-3 text-left">Date Created</th>
                        <th class="p-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($moderators as $mod): ?>
                        <tr class="border-b text-sm align-top hover:bg-gray-50">
                            <td class="p-3"><?= htmlspecialchars($mod['firstname'] ?? '') ?></td>
                            <td class="p-3"><?= htmlspecialchars($mod['middlename'] ?? '') ?></td>
                            <td class="p-3"><?= htmlspecialchars($mod['lastname'] ?? '') ?></td>
                            <td class="p-3"><?= htmlspecialchars($mod['email'] ?? '') ?></td>
                            <td class="p-3"><span class="font-mono text-sm"><?= htmlspecialchars($mod['username'] ?? '') ?></span></td>
                            <td class="p-3 text-gray-600"><?= htmlspecialchars($mod['date_created'] ?? 'N/A') ?></td>
                            <td class="p-3">
                                <a href="?delete_moderator=<?= $mod['id'] ?>" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700" onclick="return confirm('Delete this moderator?\n\n<?= htmlspecialchars($mod['firstname'] . ' ' . $mod['lastname']) ?>')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-3 text-sm text-gray-600">
                Total moderators: <strong><?= count($moderators) ?></strong>
            </div>
            <?php else: ?>
            <div class="bg-gray-50 p-8 rounded text-center">
                <p class="text-gray-600 mb-2">No moderators currently displayed.</p>
                <?php if (!empty($debug_message)): ?>
                <p class="text-blue-600 text-sm mb-4">Status: <?= htmlspecialchars($debug_message) ?></p>
                <?php endif; ?>
                <p class="text-gray-500 text-sm mb-4">If moderators were added but don't appear, click "Refresh List" to reload the data.</p>
                <button id="showAddBtn" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Add Moderator</button>
            </div>
            <?php endif; ?>
        </div>
    </section>

</main>
</div>
<script>
// Toggle Add Moderator form
document.addEventListener('DOMContentLoaded', function(){
    var showBtn = document.getElementById('showAddBtn');
    var addForm = document.getElementById('addForm');
    var cancelBtn = document.getElementById('cancelAdd');
    if(showBtn){
        showBtn.addEventListener('click', function(){ addForm.classList.remove('hidden'); window.scrollTo({top: addForm.offsetTop-20, behavior:'smooth'}); });
    }
    if(cancelBtn){
        cancelBtn.addEventListener('click', function(){ addForm.classList.add('hidden'); });
    }
});

// end toggle

//
// simple prevention of back navigation when logged in
if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function () { window.history.pushState(null, null, window.location.href); };
}
</script>

</body>
</html>
