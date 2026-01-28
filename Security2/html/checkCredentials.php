<?php
// checkCredentials.php

header('Content-Type: application/json');

// Include the database connection file
include 'connection.php';

if (!$conn) {
    error_log("Database connection failed.");
    $response['error'] = 'Database connection failed.';
    echo json_encode($response);
    exit;
}

// Initialize the response array
$response = array();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if 'username' is provided for username validation
        if (isset($_POST['username'])) {
            $username = trim($_POST['username']);

            // Optional: Additional validation for username length
            if (strlen($username) < 3 || strlen($username) > 12) {
                $response['usernameValid'] = false;
                $response['usernameExists'] = false;
                $response['error'] = 'Username must be between 3 and 12 characters.';
            } else {
                // Check if username exists in the database
                $stmt = $conn->prepare("SELECT COUNT(*) FROM registered_users WHERE username = :username");
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->execute();
                $count = $stmt->fetchColumn();

                $response['usernameExists'] = $count > 0;
                $response['usernameValid'] = true;
            }
        }

        // Check if 'email' is provided for email validation
        if (isset($_POST['email'])) {
            $email = trim($_POST['email']);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['emailValid'] = false;
                $response['emailExists'] = false;
                $response['error'] = 'Invalid email format.';
            } else {
                // Check if email exists in the database
                $stmt = $conn->prepare("SELECT COUNT(*) FROM registered_users WHERE email = :email");
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $count = $stmt->fetchColumn();

                $response['emailExists'] = $count > 0;
                $response['emailValid'] = true;
            }
        }
    } catch (PDOException $e) {
        // Handle database errors
        $response['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    // If not a POST request, return an error
    $response['error'] = 'Invalid request method.';
}

// Return the JSON response
echo json_encode($response);
?>
