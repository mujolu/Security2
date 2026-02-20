<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "artlab_db";

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the 'id' parameter is sent via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $id = trim($_POST['id']); // Trim to remove extra spaces

        // Validate ID format
        if (!preg_match('/^\d{4}-\d{4}$/', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID format. Expected ####-####.']);
            exit;
        }

        // Prepare a query to check if the ID exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM registered_users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->execute();

        $count = $stmt->fetchColumn();

        // Respond with JSON
        if ($count > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID number is already taken.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'ID number is available.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID number is required.']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
