<?php
// Database connection details
$servername = "localhost";
$db_username = "root"; // Replace with your database username
$db_password = ""; // Replace with your database password
$dbname = "artlab_db"; // Replace with your database name

// Start the session at the top of the script
session_start();

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $db_password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, show error message
    die("Connection failed: " . $e->getMessage());
}

// Create login_logs table if it doesn't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS login_logs (
        login_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(9) NOT NULL,
        device VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        ip_address VARCHAR(15) NULL,
        username VARCHAR(255) NOT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        logout_time TIMESTAMP NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_login_time (login_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

function normalizeIPv4($ip_address) {
    if (!empty($ip_address) && filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return substr($ip_address, 0, 15);
    }
    return '127.0.0.1';
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $input_username = trim($_POST['username']);
    $input_password = trim($_POST['password']);

    // Prepare and execute the query to check if the username exists
    $sql = "SELECT id, username, password, role 
        FROM registered_users 
        WHERE username = :username";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $input_username, PDO::PARAM_STR);
    $stmt->execute();

    // Check if a row is returned
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $user['id'];
        $db_username = $user['username'];
        $db_password = $user['password'];

        // Verify the password
        $stored_password = trim($db_password); // Remove any whitespace
        
        // Try password_verify first (for hashed passwords)
        $password_correct = password_verify($input_password, $stored_password);
        
        // Fallback: if password_verify fails, check if password is stored as plaintext
        if (!$password_correct && !preg_match('/^\$2[aby]\$/', $stored_password)) {
            // Password doesn't look like a bcrypt hash, try plaintext comparison
            $password_correct = ($input_password === $stored_password);
            
            // If plaintext match works, hash and update the password for security
            if ($password_correct) {
                try {
                    $new_hash = password_hash($input_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE registered_users SET password = :password WHERE id = :id");
                    $update_stmt->bindParam(':password', $new_hash);
                    $update_stmt->bindParam(':id', $id);
                    $update_stmt->execute();
                } catch (Exception $e) {
                    // Silently fail password update, login proceeds
                }
            }
        }
        
        if ($password_correct) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;
            $_SESSION['role'] = $user['role'];

            // Verify the user_id exists before inserting into the login table
            $user_check_sql = "SELECT COUNT(*) FROM registered_users WHERE id = :user_id";
            $user_check_stmt = $conn->prepare($user_check_sql);
            $user_check_stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
            $user_check_stmt->execute();
            $user_exists = $user_check_stmt->fetchColumn(); // Returns count of matching rows

            if ($user_exists) {
                // Insert login record if user_id exists
                            /* ===============================
            COLLECT DEVICE + IP INFO
            ================================= */

            $ip_address = normalizeIPv4($_SERVER['REMOTE_ADDR'] ?? '');
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $device = strpos($user_agent, 'Mobile') !== false ? 'Mobile' : 'Desktop';
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
            // Use username column for login log; some schemas store username instead of email_used
            $username_used = $db_username;

            /* ===============================
            INSERT LOGIN LOG
            ================================= */

            $login_sql = "INSERT INTO login_logs 
            (user_id, device, os, ip_address, username, login_time)
            VALUES (:user_id, :device, :os, :ip, :username, NOW())";

            $login_stmt = $conn->prepare($login_sql);
            $login_stmt->bindParam(':user_id', $id);
            $login_stmt->bindParam(':device', $device);
            $login_stmt->bindParam(':os', $os);
            $login_stmt->bindParam(':ip', $ip_address);
            $login_stmt->bindParam(':username', $username_used);

            if ($login_stmt->execute()) {

                // Save login log ID for logout tracking
                $_SESSION['login_log_id'] = $conn->lastInsertId();

                switch ($user['role']) {

                    case 'platform_admin':
                        header("Location: admin_dashboard.php");
                        break;

                    case 'moderator':
                        header("Location: moderator_dashboard.php");
                        break;

                    case 'artist':
                        header("Location: authenticatedLogin.php");
                        break;

                    case 'collector':
                        header("Location: collector_dashboard.php");
                        break;

                    default:
                        header("Location: authenticatedLogin.php");
                }

                exit();



                } else {
                    echo "<script>alert('Failed to log the login event.');</script>";
                }
            } else {
                echo "<script>alert('Invalid user ID or user does not exist.');</script>";
            }
        } else {
            // Incorrect password
            echo "<script>alert('Incorrect username or password.');</script>";
        }
    } else {
        // Username not found in registered_users
        echo "<script>alert('Incorrect username or password.');</script>";
    }
}

// Close the connection (optional, PDO automatically closes when script ends)
$conn = null;
?>
