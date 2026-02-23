<?php
// Debug script to test admin (moderator role) login
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

// Test username
$test_username = isset($_POST['username']) ? trim($_POST['username']) : '';
$test_password = isset($_POST['password']) ? trim($_POST['password']) : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Admin Login</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Admin Login Debug</h1>
    
    <div class="section">
        <h2>Test Admin Login</h2>
        <form method="POST">
            <div>
                <label>Username: <input type="text" name="username" value="<?php echo htmlspecialchars($test_username); ?>" placeholder="Enter admin username"></label>
            </div>
            <div>
                <label>Password: <input type="password" name="password" placeholder="Enter password"></label>
            </div>
            <button type="submit">Test Login</button>
        </form>
    </div>

    <?php if ($test_username): ?>
    <div class="section">
        <h2>Step 1: Check registered_users Table</h2>
        <?php
        try {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM registered_users WHERE username = :username");
            $stmt->bindParam(':username', $test_username, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo '<p class="success">✓ Found in registered_users</p>';
                echo '<pre>';
                echo "ID: " . htmlspecialchars($user['id']) . "\n";
                echo "Username: " . htmlspecialchars($user['username']) . "\n";
                echo "Role: " . htmlspecialchars($user['role']) . "\n";
                echo "Password Hash: " . htmlspecialchars($user['password']) . "\n";
                echo "Hash Algorithm: ";
                if (preg_match('/^\$2[aby]\$/', $user['password'])) {
                    echo "BCrypt (hashed)\n";
                } else {
                    echo "Plaintext or unknown\n";
                }
                echo '</pre>';
                
                if (isset($_POST['password'])) {
                    echo '<p><strong>Password Verification:</strong></p>';
                    $verify = password_verify($test_password, $user['password']);
                    echo '<pre>';
                    if ($verify) {
                        echo '<span class="success">✓ password_verify() = TRUE (password matches hash)</span>';
                    } else {
                        echo '<span class="error">✗ password_verify() = FALSE</span>';
                        // Try plaintext
                        if ($test_password === $user['password']) {
                            echo "\n✓ Plaintext match (password stored as plaintext)";
                        } else {
                            echo "\n✗ Plaintext match also failed";
                        }
                    }
                    echo '</pre>';
                }
            } else {
                echo '<p class="error">✗ Not found in registered_users</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>Step 2: Check moderators Table (legacy admin storage)</h2>
        <?php
        try {
            $stmt = $conn->prepare("SELECT id, username, password FROM moderators WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $test_username, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $mod = $stmt->fetch(PDO::FETCH_ASSOC);
                echo '<p class="success">✓ Found in moderators table (legacy)</p>';
                echo '<pre>';
                echo "ID: " . htmlspecialchars($mod['id']) . "\n";
                echo "Username: " . htmlspecialchars($mod['username']) . "\n";
                echo "Password Hash: " . htmlspecialchars($mod['password']) . "\n";
                echo "Hash Algorithm: ";
                if (preg_match('/^\$2[aby]\$/', $mod['password'])) {
                    echo "BCrypt (hashed)\n";
                } else {
                    echo "Plaintext or unknown\n";
                }
                echo '</pre>';
                
                if (isset($_POST['password'])) {
                    echo '<p><strong>Password Verification:</strong></p>';
                    $verify = password_verify($test_password, $mod['password']);
                    echo '<pre>';
                    if ($verify) {
                        echo '<span class="success">✓ password_verify() = TRUE (password matches hash)</span>';
                    } else {
                        echo '<span class="error">✗ password_verify() = FALSE</span>';
                        // Try plaintext
                        if ($test_password === $mod['password']) {
                            echo "\n✓ Plaintext match (password stored as plaintext)";
                        } else {
                            echo "\n✗ Plaintext match also failed";
                        }
                    }
                    echo '</pre>';
                }
            } else {
                echo '<p class="error">✗ Not found in moderators table</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>Available Admins (moderator role)</h2>
        <?php
        try {
            // Check both tables
            echo '<h3>In registered_users (role=moderator):</h3>';
            $stmt = $conn->prepare("SELECT id, username, role FROM registered_users WHERE role = 'moderator' ORDER BY id");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                echo '<pre>';
                foreach ($rows as $row) {
                    echo "ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}\n";
                }
                echo '</pre>';
            } else {
                echo '<p>No admins (moderator role) in registered_users</p>';
            }
            
            echo '<h3>In moderators table (legacy):</h3>';
            $stmt = $conn->prepare("SELECT id, username FROM moderators ORDER BY id");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                echo '<pre>';
                foreach ($rows as $row) {
                    echo "ID: {$row['id']}, Username: {$row['username']}\n";
                }
                echo '</pre>';
            } else {
                echo '<p>No admins in legacy moderators table</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <?php endif; ?>
</body>
</html>
