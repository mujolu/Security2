<?php
// This is a debug version to test form submission
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "artlab_db";

echo "<h1>Debug: Register Form Submission Test</h1>";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✓ Database connection successful</p>";
    
    // Check if registered_users table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'registered_users'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✓ Table 'registered_users' exists</p>";
    } else {
        echo "<p style='color:red;'>✗ Table 'registered_users' does NOT exist</p>";
        echo "<p>Need to create the table. Here's the CREATE TABLE statement:</p>";
        echo "<pre><code>
CREATE TABLE registered_users (
    id VARCHAR(9) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_initial VARCHAR(1),
    last_name VARCHAR(50) NOT NULL,
    extension_name VARCHAR(3),
    username VARCHAR(12) UNIQUE NOT NULL,
    email VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    sex VARCHAR(10) NOT NULL,
    purok VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    zip_code VARCHAR(5) NOT NULL,
    birthdate DATE NOT NULL,
    age INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
        </code></pre>";
    }
} catch(PDOException $e) {
    echo "<p style='color:red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<h2>Form Submission Debug Info</h2>";
    echo "<h3>Posted Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}
?>
