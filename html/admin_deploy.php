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
            device VARCHAR(50) NULL,
            os VARCHAR(50) NULL,
            time_in TIMESTAMP NULL,
            time_out TIMESTAMP NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist with proper schema
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

// Function to generate unique ID in format ####-####
function generateUniqueID($conn) {
    while (true) {
        $part1 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $id = $part1 . '-' . $part2;
        
        // Check if ID already exists
        $check_stmt = $conn->prepare("SELECT id FROM registered_users WHERE id = ?");
        $check_stmt->execute([$id]);
        
        if ($check_stmt->rowCount() === 0) {
            return $id;
        }
    }
}

// Track if moderator was just added (from form submission)
$success_message = '';
$error_message = '';

// Handle add moderator form submission - integrate into registered_users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_moderator') {
    // Auto-generate ID
    $moderator_id = generateUniqueID($conn);
    
    // Personal Information
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $extension_name = trim($_POST['extension_name'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    
    // Address Information
    $purok = trim($_POST['purok'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    
    // Credentials
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (!$first_name || !$last_name || !$email || !$username || !$password || !$birthdate || 
        !$sex || !$purok || !$barangay || !$city || !$province || !$country || !$zip_code) {
        $error_message = "Error: All required fields must be filled in.";
    }
    // Validate passwords match
    elseif ($password !== $confirm_password) {
        $error_message = "Error: Passwords do not match.";
    }
    else {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if username or email already exists
        try {
            $check_stmt = $conn->prepare("SELECT id FROM registered_users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Error: Username or email already exists. Please use different values.";
            } else {
                // Calculate age from birthdate
                try {
                    $birthDate = new DateTime($birthdate);
                    $currentDate = new DateTime();
                    $age = $currentDate->diff($birthDate)->y;
                } catch (Exception $e) {
                    $age = 0;
                }
                
                // Insert moderator into registered_users with ALL fields
                try {
                    $role = 'moderator';
                    $stmt = $conn->prepare("INSERT INTO registered_users 
                        (id, first_name, middle_initial, last_name, extension_name, username, email, password, sex, purok, barangay, city, province, country, zip_code, birthdate, age, role) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $moderator_id,
                        $first_name,
                        $middle_initial ?: null,
                        $last_name,
                        $extension_name ?: null,
                        $username,
                        $email,
                        $password_hash,
                        $sex,
                        $purok,
                        $barangay,
                        $city,
                        $province,
                        $country,
                        $zip_code,
                        $birthdate,
                        $age,
                        $role
                    ]);
                    
                    // Log the moderator addition
                    $moderator_info = "$first_name $last_name (ID: $moderator_id, Username: $username, Email: $email)";
                    logAdminActivity($conn, 'add_moderator', $moderator_info);
                    
                    $success_message = "✓ Moderator '$first_name $last_name' (ID: $moderator_id) has been successfully added!";
                } catch (Exception $e) {
                    $error_message = "Error adding moderator: " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $error_message = "Error checking moderator: " . $e->getMessage();
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
                    reg_date AS date_created
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
                $mods_stmt = $conn->prepare("SELECT id, first_name AS firstname, middle_initial AS middlename, last_name AS lastname, email, username, reg_date AS date_created FROM registered_users WHERE role = 'moderator' ORDER BY id DESC");
                $mods_stmt->execute();
                $moderators = $mods_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .error-message { color: red; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
    <!-- Validation Scripts -->
    <script src="../html/script.php?dir=js&file=personal_Information.js" defer></script>
    <script src="../html/script.php?dir=js&file=passwordValidation.js" defer></script>
    <script src="../html/script.php?dir=js&file=checkCredentials.js" defer></script>
    <script src="../html/script.php?dir=js&file=addressValidation.js" defer></script>
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
        <a href="admin_flag_review.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3">Flag Audit</a>
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

        <div id="addForm" class="hidden mb-6 bg-gray-50 rounded p-6 max-w-4xl">
            <h3 class="text-xl font-bold mb-4">Create New Moderator</h3>
            <form id="moderatorForm" method="post" action="admin_deploy.php" class="space-y-6" novalidate>
                <input type="hidden" name="action" value="add_moderator">
                
                <!-- Personal Information Section -->
                <div>
                    <h4 class="text-lg font-semibold mb-3 text-gray-700">Personal Information</h4>
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">ID</label>
                                <!-- ID is auto-generated and displayed as readonly -->
                                <input id="id" name="id" type="text" readonly placeholder="Will be auto-generated"
                                    class="p-2 border border-gray-300 rounded w-full bg-gray-100 text-gray-600 cursor-not-allowed" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                                <input id="first_name" name="first_name" type="text" placeholder="First name" required class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Middle Initial <span class="text-gray-500">(Optional)</span></label>
                                <input id="middle_initial" name="middle_initial" type="text" placeholder="M" maxlength="1" class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                                <input id="last_name" name="last_name" type="text" placeholder="Last name" required class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Extension <span class="text-gray-500">(Optional)</span></label>
                                <input id="extension_name" name="extension_name" type="text" placeholder="Jr., Sr., etc" maxlength="3" class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Sex <span class="text-red-500">*</span></label>
                                <select id="sex" name="sex" required class="p-2 border border-gray-300 rounded w-full">
                                    <option value="">Select Sex</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Birthdate <span class="text-red-500">*</span></label>
                                <input id="birthdate" name="birthdate" type="date" required onchange="calculateAge()" class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Age <span class="text-red-500">*</span></label>
                                <input id="age" name="age" type="text" readonly class="p-2 border border-gray-300 rounded w-full bg-gray-100" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Section -->
                <div>
                    <h4 class="text-lg font-semibold mb-3 text-gray-700">Address</h4>
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Purok <span class="text-red-500">*</span></label>
                                <input id="purok" name="purok" type="text" placeholder="Purok" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="purokError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Barangay <span class="text-red-500">*</span></label>
                                <input id="barangay" name="barangay" type="text" placeholder="Barangay" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="barangayError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                                <input id="city" name="city" type="text" placeholder="City" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="cityError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Province <span class="text-red-500">*</span></label>
                                <input id="province" name="province" type="text" placeholder="Province" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="provinceError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                                <input id="country" name="country" type="text" placeholder="Country" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="countryError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Zip Code <span class="text-red-500">*</span></label>
                                <input id="zip_code" name="zip_code" type="text" placeholder="Zip code" maxlength="5" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="zip_codeError" class="error-message"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Credentials Section -->
                <div>
                    <h4 class="text-lg font-semibold mb-3 text-gray-700">Credentials</h4>
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                                <input id="email" name="email" type="email" placeholder="Email" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="emailError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                                <input id="username" name="username" type="text" placeholder="Username" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="usernameError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                                <input id="password" name="password" type="password" placeholder="Password" required class="p-2 border border-gray-300 rounded w-full" />
                                <div id="passwordError" class="error-message"></div>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                                <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password" required class="p-2 border border-gray-300 rounded w-full" />
                            </div>
                            <div id="passwordStrengthMessage" class="text-sm mt-1"></div>
                            <div id="passwordMatchMessage" class="text-sm mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelAdd" class="bg-gray-300 text-black px-6 py-2 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Moderator</button>
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
    var moderatorForm = document.getElementById('moderatorForm');
    
    if(showBtn){
        showBtn.addEventListener('click', function(){ addForm.classList.remove('hidden'); window.scrollTo({top: addForm.offsetTop-20, behavior:'smooth'}); });
    }
    if(cancelBtn){
        cancelBtn.addEventListener('click', function(){ addForm.classList.add('hidden'); });
    }
    
    // Add form submission validation
    if(moderatorForm) {
        moderatorForm.addEventListener('submit', function(event) {
            // Validate required fields (excluding id and age which are auto-generated/calculated)
            let isValid = true;
            const requiredFields = ['first_name', 'last_name', 'sex', 'birthdate', 'purok', 'barangay', 'city', 'province', 'country', 'zip_code', 'email', 'username', 'password', 'confirm_password'];
            
            for (let field of requiredFields) {
                const input = document.getElementById(field);
                if (input && (!input.value || input.value.trim() === '')) {
                    input.style.borderColor = 'red';
                    isValid = false;
                } else if (input) {
                    input.style.borderColor = '';
                }
            }
            
            // Validate password match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordMatchMessage = document.getElementById('passwordMatchMessage');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                passwordMatchMessage.textContent = 'Passwords do not match!';
                passwordMatchMessage.style.color = 'red';
                isValid = false;
            } else if (passwordMatchMessage) {
                passwordMatchMessage.textContent = '';
            }
            
            // Validate email format
            const email = document.getElementById('email');
            const emailError = document.getElementById('emailError');
            if (email && !isValidEmail(email.value)) {
                email.style.borderColor = 'red';
                emailError.textContent = 'Invalid email format';
                emailError.style.color = 'red';
                isValid = false;
            } else if (email && emailError) {
                email.style.borderColor = '';
                emailError.textContent = '';
            }
            
            // Validate zip code is numeric
            const zipCode = document.getElementById('zip_code');
            const zipError = document.getElementById('zip_codeError');
            if (zipCode && !/^\d+$/.test(zipCode.value)) {
                zipCode.style.borderColor = 'red';
                zipError.textContent = 'Zip code must contain only numbers';
                zipError.style.color = 'red';
                isValid = false;
            } else if (zipCode && zipError) {
                zipCode.style.borderColor = '';
                zipError.textContent = '';
            }
            
            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
    }
});

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Calculate age from birthdate
function calculateAge() {
    const birthdateInput = document.querySelector('input[name="birthdate"]');
    const ageInput = document.getElementById('age');
    
    if (birthdateInput && birthdateInput.value) {
        const birthDate = new Date(birthdateInput.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        if (ageInput) {
            ageInput.value = age;
        }
    }
}

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
