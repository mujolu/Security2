<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'platform_admin') {
    header("Location: login.php");
    exit();

}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
} catch (Exception $e) {
}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN ban_reason VARCHAR(255) NULL");
} catch (Exception $e) {
}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN status_reason VARCHAR(255) NULL");
} catch (Exception $e) {
}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN status_updated_at TIMESTAMP NULL");
} catch (Exception $e) {
}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
} catch (Exception $e) {
}

try {
    $conn->exec("ALTER TABLE registered_users ADD COLUMN ban_reason VARCHAR(255) NULL");
} catch (Exception $e) {
}

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
            device VARCHAR(50) NULL,
            os VARCHAR(50) NULL,
            time_in TIMESTAMP NULL,
            time_out TIMESTAMP NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD COLUMN device VARCHAR(50) NULL AFTER ip_address");
        } catch (Exception $e) {
        }

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD COLUMN os VARCHAR(50) NULL AFTER device");
        } catch (Exception $e) {
        }

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD COLUMN time_in TIMESTAMP NULL AFTER os");
        } catch (Exception $e) {
        }

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD COLUMN time_out TIMESTAMP NULL AFTER time_in");
        } catch (Exception $e) {
        }

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD COLUMN timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        } catch (Exception $e) {
        }

        try {
            $conn->exec("ALTER TABLE admin_activity_logs ADD INDEX idx_timestamp (timestamp)");
        } catch (Exception $e) {
        }
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

function createDeleteUserRequestsTable($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS delete_user_requests (
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

function logAdminActivity($conn, $actionType, $details = '', $time_in = null, $time_out = null) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'platform_admin') return;

    $user_id = $_SESSION['user_id'];
    // Convert IP to IPv4 only
    $ip = $_SERVER['REMOTE_ADDR'];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // If IPv6, try to extract IPv4 or use localhost equivalent
        $ip = '127.0.0.1';
    }
    $ip = substr($ip, 0, 15); // Truncate to IPv4 length for VARCHAR(15)

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
        'delete_user' => "Deleted user ID: $details",
        'delete_moderator' => "Deleted admin ID: $details",
        'add_moderator' => "Added admin: $details",
        'approve_artwork' => "Approved artwork ID: $details",
        'edit_user' => "Edited user ID: $details",
        'ban_user' => "Banned user ID: $details",
        'unban_user' => "Unbanned user ID: $details",
        'restore_user' => "Restored user ID: $details",
        'enable_user' => "Enabled user ID: $details",
        'change_role' => "Changed user role: $details",
        'approve_unban_request' => "Approved unban request: $details",
        'deny_unban_request' => "Denied unban request: $details",
        'approve_delete_request' => "Approved delete request: $details",
        'deny_delete_request' => "Denied delete request: $details",
        'disable_admin' => "Disabled admin: $details",
        'enable_admin' => "Enabled admin: $details",
        'restore_admin' => "Restored admin: $details",
        'logout' => "Logged out",
        default => $details
    };

    try {
        // Just insert the activity log - table should already exist from createAdminActivityLogsTable()
        $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $activity, $ip, $device, $os, $time_in, $time_out]);
    } catch (Exception $e) {
        // If insert fails, try creating table first then insert
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
                FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Try insert again
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (user_id, activity, ip_address, device, os, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $activity, $ip, $device, $os, $time_in, $time_out]);
        } catch (Exception $e2) {
            // Silent fail - don't break the main operation
        }
    }
}

// Create table if it doesn't exist
createAdminActivityLogsTable($conn);
createUnbanRequestsTable($conn);
createDeleteUserRequestsTable($conn);

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) { // prevent self-delete
        header("Location: admin_dashboard.php");
        exit();
    }
    
    // Get user info before deleting for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username, email FROM registered_users WHERE id = ?");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $user_details = $user_info['first_name'] . ' ' . $user_info['last_name'] . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')';
        }
    } catch (Exception $e) {
        $user_details = "ID: $id";
    }
    
    $status_reason = 'Deleted by Super Admin';
    $stmt = $conn->prepare("UPDATE registered_users SET status='deleted', status_reason = ?, status_updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status_reason, $id]);
    
    // Log the deletion
    logAdminActivity($conn, 'delete_user', ($user_details ?? "ID: $id") . " | Page: User Management");
    
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['ban'])) {
    $id = $_GET['ban'];
    // Debug log
    error_log("Ban handler called with id=$id, ban_reason=" . ($_GET['ban_reason'] ?? 'NOT SET'));
    
    if ($id == $_SESSION['user_id']) { // prevent self-ban
        header("Location: admin_dashboard.php");
        exit();
    }

    $ban_reason = trim($_GET['ban_reason'] ?? '');
    if ($ban_reason === '') {
        $ban_reason = 'Violation of platform rules.';
    }
    $ban_reason = substr($ban_reason, 0, 255);
    
    // Get user info before banning for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username FROM registered_users WHERE id = ?");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $user_details = ($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '') . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')';
        } else {
            $user_details = "ID: $id";
        }
    } catch (Exception $e) {
        error_log("Error fetching user info: " . $e->getMessage());
        $user_details = "ID: $id";
    }
    
    $status_reason = 'Banned by Super Admin';
    try {
        $stmt = $conn->prepare("UPDATE registered_users SET status='banned', ban_reason = ?, status_reason = ?, status_updated_at = NOW() WHERE id=?");
        $result = $stmt->execute([$ban_reason, $status_reason, $id]);
        error_log("Ban update executed. Rows affected: " . $stmt->rowCount());
        
        // Log the ban
        $log_details = ($user_details ?? "ID: $id") . " | Reason: " . $ban_reason . " | Page: User Management";
        logAdminActivity($conn, 'ban_user', $log_details);
    } catch (Exception $e) {
        error_log("Ban error: " . $e->getMessage());
        echo "<script>alert('Ban error: " . addslashes($e->getMessage()) . "');</script>";
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['unban'])) {
    $id = $_GET['unban'];
    $stmt = $conn->prepare("UPDATE registered_users SET status='active', ban_reason = NULL, status_reason = NULL, status_updated_at = NOW() WHERE id=?");
    $stmt->execute([$id]);
    logAdminActivity($conn, 'unban_user', "ID: $id | Page: User Management");
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $stmt = $conn->prepare("UPDATE registered_users SET status='active', ban_reason = NULL, status_reason = NULL, status_updated_at = NOW() WHERE id=?");
    $stmt->execute([$id]);
    logAdminActivity($conn, 'restore_user', "ID: $id | Page: User Management");
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['enable'])) {
    $id = $_GET['enable'];
    $stmt = $conn->prepare("UPDATE registered_users SET status='active', ban_reason = NULL, status_reason = NULL, status_updated_at = NOW() WHERE id=?");
    $stmt->execute([$id]);
    logAdminActivity($conn, 'enable_user', "ID: $id | Page: User Management");
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['approve_unban_request'])) {
    $request_id = (int)$_GET['approve_unban_request'];
    try {
        $stmt = $conn->prepare("UPDATE unban_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        logAdminActivity($conn, 'approve_unban_request', "Request ID: $request_id | Page: User Management");
    } catch (Exception $e) {
        // ignore
    }
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['deny_unban_request'])) {
    $request_id = (int)$_GET['deny_unban_request'];
    try {
        $stmt = $conn->prepare("UPDATE unban_requests SET status = 'denied', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        logAdminActivity($conn, 'deny_unban_request', "Request ID: $request_id | Page: User Management");
    } catch (Exception $e) {
        // ignore
    }
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['approve_delete_request'])) {
    $request_id = (int)$_GET['approve_delete_request'];
    try {
        $req_stmt = $conn->prepare("SELECT user_id FROM delete_user_requests WHERE id = ? AND status = 'pending' LIMIT 1");
        $req_stmt->execute([$request_id]);
        $target_user_id = $req_stmt->fetchColumn();

        if ($target_user_id) {
            $approve_stmt = $conn->prepare("UPDATE delete_user_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $approve_stmt->execute([$_SESSION['user_id'], $request_id]);

            $status_reason = 'Deleted by Super Admin (Approved Request)';
            $delete_stmt = $conn->prepare("UPDATE registered_users SET status='deleted', status_reason = ?, status_updated_at = NOW() WHERE id = ? AND role != 'platform_admin'");
            $delete_stmt->execute([$status_reason, $target_user_id]);

            logAdminActivity($conn, 'approve_delete_request', "Request ID: $request_id | User ID: $target_user_id | Page: User Management");
        }
    } catch (Exception $e) {
        // ignore
    }
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['deny_delete_request'])) {
    $request_id = (int)$_GET['deny_delete_request'];
    try {
        $stmt = $conn->prepare("UPDATE delete_user_requests SET status = 'denied', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        logAdminActivity($conn, 'deny_delete_request', "Request ID: $request_id | Page: User Management");
    } catch (Exception $e) {
        // ignore
    }
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['change_role'])) {
    $id = $_GET['change_role'];
    $newRoleInput = $_GET['new_role'] ?? 'user';

    $roleMap = [
        'superadmin' => 'platform_admin',
        'admin' => 'moderator',
        'user' => 'artist',
    ];

    $newRoleInput = strtolower(trim($newRoleInput));
    $newRole = $roleMap[$newRoleInput] ?? 'artist';
    
    // Prevent changing own role
    if ($id == $_SESSION['user_id']) {
        header("Location: admin_dashboard.php");
        exit();
    }
    
    // Get user info before changing role for logging
    try {
        $info_stmt = $conn->prepare("SELECT first_name, last_name, username, role FROM registered_users WHERE id = ?");
        $info_stmt->execute([$id]);
        $user_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $oldRole = $user_info['role'] ?? 'artist';
            $user_details = ($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '') . ' (ID:' . $id . ', Username: ' . $user_info['username'] . ')';
            
            // Change role
            try {
                $stmt = $conn->prepare("UPDATE registered_users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $id]);
                
                // Log the role change
                $toLabelMap = [
                    'platform_admin' => 'Superadmin',
                    'moderator' => 'Admin',
                    'artist' => 'User',
                ];
                $roleLabel = $toLabelMap[$newRole] ?? ucfirst($newRole);
                $oldRoleLabel = $toLabelMap[$oldRole] ?? ucfirst($oldRole);
                $log_details = $user_details . " | Changed from $oldRoleLabel to $roleLabel | Page: User Management";
                logAdminActivity($conn, 'change_role', $log_details);
            } catch (Exception $e) {
                // ignore
            }
        }
    } catch (Exception $e) {
        // ignore
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

header("Pragma: no-cache");

// Get current user's full name and role
$user_id = $_SESSION['user_id'] ?? '';
$user_full_name = 'Super Admin';
$user_role = 'Super Admin';

if ($user_id) {
    try {
        $user_stmt = $conn->prepare("SELECT first_name, last_name, role FROM registered_users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
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

$username = $_SESSION['username'] ?? 'super admin';
$current_page = basename($_SERVER['PHP_SELF']);

// Log page view
logAdminActivity($conn, 'view', 'User Management Dashboard');

$sql = "SELECT u.username, l.*
        FROM login_logs l
        JOIN registered_users u ON l.user_id = u.id
        ORDER BY l.login_time DESC";

$stmt = $conn->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current_admin_id = $_SESSION['user_id'];

$users = $conn->prepare("
        SELECT * 
        FROM registered_users 
        WHERE (status IS NULL OR status != 'deleted')
            AND role != 'platform_admin'
            AND id != :current_id
");
$users->bindParam(':current_id', $current_admin_id, PDO::PARAM_INT);
$users->execute();
$users = $users->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Welcome to Artlab</title>

    <script>
    function doBan(userId, username) {
        var reason = prompt('Enter ban reason for ' + username + ':');
        if (reason === null) return false;
        var finalReason = reason.trim().length > 0 ? reason.trim() : 'Violation of platform rules.';
        window.location.href = '?ban=' + userId + '&ban_reason=' + encodeURIComponent(finalReason);
        return false;
    }
    </script>

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

        <a href="admin_dashboard.php" class="sidebar-link <?php echo $current_page === 'admin_dashboard.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">User Management</a>
        <a href="admin_flag_review.php" class="sidebar-link <?php echo $current_page === 'admin_flag_review.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Flag Audit</a>
        <a href="admin_deploy.php" class="sidebar-link <?php echo $current_page === 'admin_deploy.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Deploy Admins</a>
        <a href="admin_marketplace.php" class="sidebar-link <?php echo $current_page === 'admin_marketplace.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Marketplace</a>
        <a href="admin_collab.php" class="sidebar-link <?php echo $current_page === 'admin_collab.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Collaboration Oversight</a>
        <a href="admin_activity.php" class="sidebar-link <?php echo $current_page === 'admin_activity.php' ? 'bg-yellow-700' : 'bg-gray-700'; ?> text-white rounded-lg px-4 py-3">Activity Logs</a>

    </nav>
</aside>

<!-- 🧠 MAIN CONTENT -->
        <main class="flex-1 p-6">

        <header class="bg-gray-800 text-white rounded-xl p-6 mb-6 flex justify-between">
            <h3 class="text-xl font-bold">
                Super Admin Panel
            </h3>

            <a href="logout.php"
            class="bg-gray-700 px-4 py-2 rounded hover:bg-yellow-700">
            Logout
            </a>
        </header>

<!-- USER MANAGEMENT -->

            <section id="users" class="bg-white rounded-xl shadow-lg p-6">

            <h2 class="text-2xl font-bold mb-6">User Management</h2>

            <table class="w-full border-collapse">
            <thead class="bg-gray-800 text-white">
            <tr>
            <th class="p-3">ID</th>
            <th class="p-3">Username</th>
            <th class="p-3">Role</th>
            <th class="p-3">Status</th>
            <th class="p-3">Actions</th>
            <th class="p-3">Role Mgmt</th>
            <th class="p-3">Logs</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($users as $user): ?>
            <tr class="border-b text-center">
            <td class="p-3"><?= $user['id'] ?></td>
            <td class="p-3"><?= htmlspecialchars($user['username']) ?></td>
            <td class="p-3"><?php
                $displayRole = $user['role'] ?? 'artist';
                if ($displayRole === 'platform_admin') echo 'Superadmin';
                elseif ($displayRole === 'moderator') echo 'Admin';
                else echo 'User';
            ?></td>
            <td class="p-3"><?= $user['status'] ?? 'active' ?></td>

            <td class="p-3 space-x-2">

            <a href="?delete=<?= $user['id'] ?>"
            class="bg-red-600 text-white px-3 py-1 rounded"
            onclick="return confirm('Delete this user?')">
            Delete
            </a>

            <?php if (($user['status'] ?? 'active') === 'banned'): ?>
                <a href="?unban=<?= $user['id'] ?>"
                class="bg-green-600 text-white px-3 py-1 rounded"
                onclick="return confirm('Unban this user?')">
                Unban
                </a>
            <?php elseif (($user['status'] ?? 'active') === 'disabled'): ?>
                <a href="?enable=<?= $user['id'] ?>"
                class="bg-green-600 text-white px-3 py-1 rounded"
                onclick="return confirm('Enable this user?')">
                Enable
                </a>
            <?php else: ?>
                <a href="#"
                class="bg-yellow-500 text-white px-3 py-1 rounded"
                onclick="return doBan('<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                Ban
                </a>
            <?php endif; ?>

            </td>

            <td class="p-3">
                <?php $currentRole = $user['role'] ?? 'artist'; ?>
                <form method="GET" class="flex items-center justify-center gap-2">
                    <input type="hidden" name="change_role" value="<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>">
                    <select name="new_role" class="border rounded px-2 py-1 text-sm">
                        <option value="superadmin" <?= $currentRole === 'platform_admin' ? 'selected' : '' ?>>Superadmin</option>
                        <option value="admin" <?= $currentRole === 'moderator' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $currentRole === 'artist' ? 'selected' : '' ?>>User</option>
                    </select>
                    <button type="submit"
                    class="bg-indigo-700 text-white px-2 py-1 rounded text-sm"
                    onclick="return confirm('Set selected role for this user?')">
                    Set Role
                    </button>
                </form>
            </td>

            <td class="p-3">
                <a href="view_logs.php?user_id=<?= urlencode($user['id']) ?>" 
                class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                View Logs
                </a>
            </td>

            </tr>

            <?php endforeach; ?>
            </tbody>
            </table>

            <section class="bg-gray-50 rounded-xl shadow-inner p-5 mt-6">
                <h3 class="text-lg font-semibold mb-4">User Action History (Ban/Delete/Disable)</h3>
                <?php
                $history_stmt = $conn->prepare("\n                    SELECT id, username, role, status, ban_reason, status_reason, status_updated_at\n                    FROM registered_users\n                    WHERE status IN ('banned', 'deleted', 'disabled')\n                      AND role != 'platform_admin'\n                    ORDER BY status_updated_at DESC\n                    LIMIT 50\n                ");
                $history_stmt->execute();
                $history_rows = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($history_rows)): ?>
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="p-3 text-left">ID</th>
                                <th class="p-3 text-left">Username</th>
                                <th class="p-3 text-left">Role</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Reason</th>
                                <th class="p-3 text-left">Updated</th>
                                <th class="p-3 text-left">Undo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_rows as $row): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?= htmlspecialchars($row['id']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['username'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['role'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['ban_reason'] ?? $row['status_reason'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['status_updated_at'] ?? '') ?></td>
                                    <td class="p-3">
                                        <?php if ($row['status'] === 'banned'): ?>
                                            <a class="bg-green-600 text-white px-3 py-1 rounded" href="?unban=<?= $row['id'] ?>">Unban</a>
                                        <?php elseif ($row['status'] === 'disabled'): ?>
                                            <a class="bg-green-600 text-white px-3 py-1 rounded" href="?enable=<?= $row['id'] ?>">Enable</a>
                                        <?php elseif ($row['status'] === 'deleted'): ?>
                                            <a class="bg-blue-600 text-white px-3 py-1 rounded" href="?restore=<?= $row['id'] ?>">Restore</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-500">No recent ban/disable/delete actions found.</p>
                <?php endif; ?>
            </section>

            <section class="bg-gray-50 rounded-xl shadow-inner p-5 mt-6">
                <h3 class="text-lg font-semibold mb-4">Unban Requests (Pending Approval)</h3>
                <?php
                $requests_stmt = $conn->prepare("\n                    SELECT ur.id, ur.user_id, ur.reason, ur.created_at,
                           u.username AS user_username,
                           u.first_name AS user_first_name,
                           u.last_name AS user_last_name,
                           req.username AS requester_username
                    FROM unban_requests ur
                    JOIN registered_users u ON u.id = ur.user_id
                    LEFT JOIN registered_users req ON req.id = ur.requested_by
                    WHERE ur.status = 'pending'
                    ORDER BY ur.created_at DESC
                ");
                $requests_stmt->execute();
                $requests_rows = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($requests_rows)): ?>
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="p-3 text-left">User</th>
                                <th class="p-3 text-left">Requested By</th>
                                <th class="p-3 text-left">Reason</th>
                                <th class="p-3 text-left">Requested At</th>
                                <th class="p-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests_rows as $row): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?php echo htmlspecialchars(trim(($row['user_first_name'] ?? '') . ' ' . ($row['user_last_name'] ?? '')) ?: ($row['user_username'] ?? $row['user_id'])); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['requester_username'] ?? 'Admin'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                                    <td class="p-3 space-x-2">
                                        <a class="bg-green-600 text-white px-3 py-1 rounded" href="?approve_unban_request=<?php echo (int)$row['id']; ?>">Approve</a>
                                        <a class="bg-red-600 text-white px-3 py-1 rounded" href="?deny_unban_request=<?php echo (int)$row['id']; ?>">Deny</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-500">No pending unban requests.</p>
                <?php endif; ?>
            </section>

            <section class="bg-gray-50 rounded-xl shadow-inner p-5 mt-6">
                <h3 class="text-lg font-semibold mb-4">Delete Requests (Pending Approval)</h3>
                <?php
                $delete_requests_stmt = $conn->prepare("\n                    SELECT dr.id, dr.user_id, dr.reason, dr.created_at,
                           u.username AS user_username,
                           u.first_name AS user_first_name,
                           u.last_name AS user_last_name,
                           req.username AS requester_username
                    FROM delete_user_requests dr
                    JOIN registered_users u ON u.id = dr.user_id
                    LEFT JOIN registered_users req ON req.id = dr.requested_by
                    WHERE dr.status = 'pending'
                    ORDER BY dr.created_at DESC
                ");
                $delete_requests_stmt->execute();
                $delete_requests_rows = $delete_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($delete_requests_rows)): ?>
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="p-3 text-left">User</th>
                                <th class="p-3 text-left">Requested By</th>
                                <th class="p-3 text-left">Reason</th>
                                <th class="p-3 text-left">Requested At</th>
                                <th class="p-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($delete_requests_rows as $row): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?php echo htmlspecialchars(trim(($row['user_first_name'] ?? '') . ' ' . ($row['user_last_name'] ?? '')) ?: ($row['user_username'] ?? $row['user_id'])); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['requester_username'] ?? 'Admin'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                                    <td class="p-3 space-x-2">
                                        <a class="bg-green-600 text-white px-3 py-1 rounded" href="?approve_delete_request=<?php echo (int)$row['id']; ?>" onclick="return confirm('Approve delete request? This will mark the user as deleted.');">Approve</a>
                                        <a class="bg-red-600 text-white px-3 py-1 rounded" href="?deny_delete_request=<?php echo (int)$row['id']; ?>">Deny</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-500">No pending delete requests.</p>
                <?php endif; ?>
            </section>
            
            <section id="userlogs" class="hidden bg-white rounded-xl shadow-lg w-full max-w-5xl p-6 mx-auto mb-10">
                <?php 
                if (isset($_GET['user_id'])) {
                    $user_id = $_GET['user_id']; // pass this to view_logs.php
                    include 'view_logs.php';
                } else {
                    echo '<p class="text-gray-600">Select a user to view login history.</p>';
                }
                ?>
            </section>



            </section>

            <!-- REPORTS -->
            <section id="reports" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Reports & Flags</h2>
                <p>Review reported artworks and users.</p>
            </section>

            <!-- MARKETPLACE -->
            <section id="marketplace" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Marketplace Oversight</h2>
                <p>Monitor listings and transactions.</p>
            </section>

            <!-- COLLABORATION -->
            <section id="collaboration" class="hidden">
                <h2 class="text-2xl font-bold mb-4">Collaboration Oversight</h2>
                <p>Handle disputes and collaborations.</p>
            </section>

            <!-- SYSTEM SETTINGS -->
            <section id="usersettings" class="hidden bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-4">System Settings</h2>
                <p>Configure platform rules and security.</p>
                 <?php include 'user_settings.php'; ?>
            </section>

</main>
</div>

<!-- 🔁 SECTION SWITCH SCRIPT -->
        <?php
        $defaultSection = $_GET['section'] ?? 'users';
        ?>
        
        <script>
        function showSection(sectionId, link) {
            ['users','reports','marketplace','collaboration','usersettings']
                .forEach(id => {
                    document.getElementById(id).classList.add('hidden');
                });

            document.getElementById(sectionId).classList.remove('hidden');

            // If no link element was provided (page load), try to find the sidebar
            // link that matches the current filename and highlight it.
            if (!link) {
                var currentFilename = window.location.pathname.split('/').pop();
                link = document.querySelector('.sidebar-link[href$="' + currentFilename + '"]');
            }

            document.querySelectorAll('.sidebar-link').forEach(l=>{
                l.classList.remove('bg-yellow-700');
                l.classList.add('bg-gray-700');
            });

            if(link){
                link.classList.remove('bg-gray-700');
                link.classList.add('bg-yellow-700');
            }
        }
                    // default section from URL
            const urlParams = new URLSearchParams(window.location.search);
            const defaultSection = urlParams.get('section') || 'users';


        // default section from URL or fallback
        <?php
        $defaultSection = $_GET['section'] ?? 'users';
        ?>
        showSection('<?= $defaultSection ?>');

        </script>

            <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Block the back button if the user is logged in
            if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
                // Push a new state to block the user from navigating back to the previous pages
                window.history.pushState(null, null, window.location.href);

                // Periodically push state to block back navigation
                setInterval(function () {
                    window.history.pushState(null, null, window.location.href);
                }, 100);

                // Prevent navigating back when the user tries to use the back button
                window.onpopstate = function () {
                    window.history.pushState(null, null, window.location.href);
                };
            }
        });

</body>
</html>