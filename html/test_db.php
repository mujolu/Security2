<?php
// Test database connection and show tables

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "artlab_db";

try {
    // Create connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Database Connection: SUCCESS</h2>";
    
    // Check if registered_users table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'registered_users'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<h3>Table 'registered_users': EXISTS</h3>";
        
        // Get table structure
        $stmt = $conn->prepare("DESCRIBE registered_users");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1'><tr>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            foreach ($col as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<h3 style='color:red;'>Table 'registered_users': DOES NOT EXIST</h3>";
    }
    
    // List all tables in the database
    echo "<h3>All Tables in artlab_db:</h3>";
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange;'>No tables found in database</p>";
    }
    
} catch(PDOException $e) {
    echo "<h2 style='color:red;'>Connection Error:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn = null;
?>
