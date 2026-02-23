<?php
// Migration tool: Check admins (moderator role) in both tables and optionally migrate them

session_start();

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "artlab_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$migration_status = '';
$migration_error = '';

// Handle migration from old moderators table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'migrate_all') {
        try {
            // Get all moderators from old moderators table
            $stmt = $conn->prepare("SELECT firstname, middlename, lastname, email, username, password FROM moderators");
            $stmt->execute();
            $moderators = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($moderators) > 0) {
                $count = 0;
                foreach ($moderators as $mod) {
                    try {
                        // Check if already exists
                        $check = $conn->prepare("SELECT id FROM registered_users WHERE username = ?");
                        $check->execute([$mod['username']]);
                        
                        if ($check->rowCount() === 0) {
                            // Insert into registered_users
                            $insert = $conn->prepare("INSERT INTO registered_users (first_name, middle_initial, last_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $insert->execute([
                                $mod['firstname'],
                                substr($mod['middlename'] ?? '', 0, 1) ?: null,
                                $mod['lastname'],
                                $mod['username'],
                                $mod['email'],
                                $mod['password'],
                                'moderator'
                            ]);
                            $count++;
                        }
                    } catch (Exception $e) {
                        // Skip duplicates
                    }
                }
                $migration_status = "✓ Successfully migrated $count admin(s) (moderator role) to registered_users table!";
            } else {
                $migration_status = "No admins found in old moderators table";
            }
        } catch (Exception $e) {
            $migration_error = "Error during migration: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Migration Tool</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        h2 { color: #555; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        button.danger { background: #f44336; }
        button.danger:hover { background: #da190b; }
        .info { background: #e7f3fe; border-left: 4px solid #2196F3; padding: 10px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Admin Migration Tool</h1>

        <?php if ($migration_status): ?>
            <div class="section">
                <p class="success"><?php echo $migration_status; ?></p>
            </div>
        <?php endif; ?>

        <?php if ($migration_error): ?>
            <div class="section">
                <p class="error"><?php echo $migration_error; ?></p>
            </div>
        <?php endif; ?>

        <!-- Check tables -->
        <div class="section">
            <h2>📊 Current Status</h2>
            
            <h3>Old moderators Table (legacy admins):</h3>
            <?php
            try {
                $stmt = $conn->prepare("SELECT id, firstname, middlename, lastname, username, email FROM moderators ORDER BY id");
                $stmt->execute();
                $old_mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($old_mods) > 0) {
                    echo '<p class="warning">Found ' . count($old_mods) . ' admin(s) in old table:</p>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th></tr>';
                    foreach ($old_mods as $mod) {
                        $name = $mod['firstname'] . ' ' . ($mod['middlename'] ? $mod['middlename'] . ' ' : '') . $mod['lastname'];
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($mod['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($name) . '</td>';
                        echo '<td>' . htmlspecialchars($mod['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($mod['email']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    
                    // Migration form
                    echo '<div class="info">';
                    echo '<strong>⚠️ These admins are NOT in registered_users!</strong><br>';
                    echo 'They cannot login until migrated. Click the button below to migrate them.';
                    echo '</div>';
                    
                    echo '<form method="POST">';
                    echo '<input type="hidden" name="action" value="migrate_all">';
                    echo '<button type="submit">Migrate All Admins to registered_users</button>';
                    echo '</form>';
                } else {
                    echo '<p>✓ No admins in old table</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">Error checking old table: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>

            <h3>New registered_users Table (role=moderator):</h3>
            <?php
            try {
                $stmt = $conn->prepare("SELECT id, first_name, middle_initial, last_name, username, email, role FROM registered_users WHERE role = 'moderator' ORDER BY id");
                $stmt->execute();
                $new_mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($new_mods) > 0) {
                    echo '<p class="success">Found ' . count($new_mods) . ' admin(s) in registered_users:</p>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th></tr>';
                    foreach ($new_mods as $mod) {
                        $name = $mod['first_name'] . ' ' . ($mod['middle_initial'] ? $mod['middle_initial'] . ' ' : '') . $mod['last_name'];
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($mod['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($name) . '</td>';
                        echo '<td>' . htmlspecialchars($mod['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($mod['email']) . '</td>';
                        echo '<td>' . htmlspecialchars($mod['role']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">⚠️ No admins in registered_users table!</p>';
                    echo '<p>Use the super admin panel to add admins, or migrate them from the old table above.</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">Error checking registered_users: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <!-- Quick Test -->
        <div class="section">
            <h2>✅ Test Login</h2>
            <p>Once you have admins in the registered_users table, test their login credentials:</p>
            <p><a href="debug_moderator_login.php" target="_blank" style="color: #2196F3; text-decoration: none;">
                <button>Open Login Debug Tool</button>
            </a></p>
        </div>

        <!-- Instructions -->
        <div class="section">
            <h2>📋 What to Do</h2>
            <h3>Option 1: Migrate Existing Admins (Recommended if you already added them)</h3>
            <ol>
                <li>Look at "Old moderators Table" above - if it shows admins, click the migration button</li>
                <li>After migrating, the admin should appear in "New registered_users Table"</li>
                <li>The admin can now login with their username and password</li>
            </ol>

            <h3>Option 2: Add New Admins via Super Admin Panel</h3>
            <ol>
                <li>Go to: <a href="admin_dashboard.php" target="_blank">Admin Dashboard</a></li>
                <li>Click "Deploy Admins"</li>
                <li>Click "Create Admin" button</li>
                <li>Fill in admin details (First Name, Last Name, Email, Username, Password)</li>
                <li>Click Submit</li>
                <li>The new admin will be added to registered_users automatically</li>
                <li>The admin can now login with their username and password</li>
            </ol>
        </div>
    </div>
</body>
</html>
