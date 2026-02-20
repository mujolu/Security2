<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "artlab_db"; // Replace with your database name

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(["error" => "Database connection failed."]);
    exit;
}

// Initialize the response array
$response = array();

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if 'username' is provided
        if (isset($_POST['username']) && !empty($_POST['username'])) {
            $username = trim($_POST['username']);

            // Validate username length
            if (strlen($username) < 3 || strlen($username) > 12) {
                $response['usernameValid'] = false;
                $response['usernameExists'] = false;
                $response['error'] = 'Username must be between 3 and 12 characters.';
            } else {
                // Check if username exists in the database
                $stmt = $conn->prepare("SELECT id, password FROM registered_users WHERE username = :username");
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $response['usernameExists'] = true;
                    $response['usernameValid'] = true;

                    // If password is also provided, validate it
                    if (isset($_POST['password']) && !empty($_POST['password'])) {
                        $password = $_POST['password'];

                        // Verify the password using password_verify()
                        if (password_verify($password, $user['password'])) {
                            $response['validCredentials'] = true;
                            $response['userId'] = $user['id']; // Return user ID if needed
                        } else {
                            $response['validCredentials'] = false;
                            $response['error'] = 'Invalid password.';
                        }
                    }
                } else {
                    // Not found in registered_users; check moderators table as fallback
                    $mod_stmt = $conn->prepare("SELECT id, password FROM moderators WHERE username = :username LIMIT 1");
                    $mod_stmt->bindParam(':username', $username, PDO::PARAM_STR);
                    $mod_stmt->execute();
                    $mod = $mod_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($mod) {
                        $response['usernameExists'] = true;
                        $response['usernameValid'] = true;
                        if (isset($_POST['password']) && !empty($_POST['password'])) {
                            $password = $_POST['password'];
                            if (password_verify($password, $mod['password'])) {
                                $response['validCredentials'] = true;
                                $response['userId'] = $mod['id'];
                            } else {
                                $response['validCredentials'] = false;
                                $response['error'] = 'Invalid password.';
                            }
                        }
                    } else {
                        $response['usernameExists'] = false;
                        $response['usernameValid'] = false;
                        $response['error'] = 'Username does not exist.';
                    }
                }
            }
        } else {
            $response['error'] = 'Username is required.';
        }
    } else {
        $response['error'] = 'Invalid request method.';
    }
} catch (PDOException $e) {
    // Handle database errors
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// Return the JSON response
echo json_encode($response);
?>