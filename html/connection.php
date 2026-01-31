

<?php
// connection.php

$servername = "localhost";
$db_username = "root"; // Replace with your MySQL username
$db_password = "";     // Replace with your MySQL password
$dbname = "artlab_db"; // Replace with your actual database name

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Handle connection errors
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit();
}
?>
