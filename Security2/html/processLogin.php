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
    $sql = "SELECT id, username, password FROM registered_users WHERE username = :username";
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

        // Debugging output
        // echo "Database Password: " . $db_password . "<br>";  // This should be a hashed password
        // echo "Input Password: " . $input_password . "<br>";  // This should be the plain input password

        // Verify the password
        if (password_verify($input_password, $db_password)) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;

            // Verify the user_id exists before inserting into the login table
            $user_check_sql = "SELECT COUNT(*) FROM registered_users WHERE id = :user_id";
            $user_check_stmt = $conn->prepare($user_check_sql);
            $user_check_stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
            $user_check_stmt->execute();
            $user_exists = $user_check_stmt->fetchColumn(); // Returns count of matching rows

            if ($user_exists) {
                // Insert login record if user_id exists
                $login_sql = "INSERT INTO login (user_id) VALUES (:user_id)";
                $login_stmt = $conn->prepare($login_sql);
                $login_stmt->bindParam(':user_id', $id, PDO::PARAM_STR);
                if ($login_stmt->execute()) {
                    header("Location: authenticatedLogin.php");
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
        // Username does not exist
        echo "<script>alert('Incorrect username or password.');</script>";
    }
}

// Close the connection (optional, PDO automatically closes when script ends)
$conn = null;
?>
