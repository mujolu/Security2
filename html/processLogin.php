<?php
// Database connection details
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "artlab_db"; // Replace with your database name

// Start the session at the top of the script
session_start();

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, show error message
    die("Connection failed: " . $e->getMessage());
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

        ini_set('display_errors', 1);
        error_reporting(E_ALL);


        // Verify the password
        if (password_verify($input_password, $db_password)) {
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

            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $device = strpos($user_agent, 'Mobile') !== false ? 'Mobile' : 'Desktop';
            $email_used = $db_username; // or use email if available

            /* ===============================
            INSERT LOGIN LOG
            ================================= */

            $login_sql = "INSERT INTO login_logs 
            (user_id, device, ip_address, email_used, login_time)
            VALUES (:user_id, :device, :ip, :email, NOW())";

            $login_stmt = $conn->prepare($login_sql);
            $login_stmt->bindParam(':user_id', $id);
            $login_stmt->bindParam(':device', $device);
            $login_stmt->bindParam(':ip', $ip_address);
            $login_stmt->bindParam(':email', $email_used);

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
        // Username not found in registered_users - check moderators table as a fallback
        try {
            $mod_stmt = $conn->prepare("SELECT id, username, password FROM moderators WHERE username = :username LIMIT 1");
            $mod_stmt->bindParam(':username', $input_username, PDO::PARAM_STR);
            $mod_stmt->execute();
            if ($mod_stmt->rowCount() > 0) {
                $mod = $mod_stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($input_password, $mod['password'])) {
                    // Successful moderator login
                    $_SESSION['user_id'] = $mod['id'];
                    $_SESSION['username'] = $mod['username'];
                    $_SESSION['role'] = 'moderator';
                    // Redirect to moderator dashboard
                    header("Location: moderator_dashboard.php");
                    exit();
                } else {
                    echo "<script>alert('Incorrect username or password.');</script>";
                }
            } else {
                // Username does not exist anywhere
                echo "<script>alert('Incorrect username or password.');</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('Incorrect username or password.');</script>";
        }
    }
}

// Close the connection (optional, PDO automatically closes when script ends)
$conn = null;
?>
