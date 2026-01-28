<?php
// Start the session
session_start();

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if the user is authenticated
if (!isset($_SESSION['username'])) {
    // If the user is not logged in, redirect them to the login page
    header("Location: login.php");
    exit();
}

// Get the user's name from the session
$username = $_SESSION['username']; 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Artlab</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #333;
            color: white;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
        }
        header h1 {
            margin: 0;
        }
        .logout-btn {
            padding: 10px 20px;
            background-color:darkgoldenrod;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .logout-btn:hover {
            background-color:gray;
        }
        .container {
            text-align: center;
            padding: 50px;
        }
        h2 {
            color: #333;
        }
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            padding: 10px;
            background-color: #333;
            color: white;
        }
    </style>
</head>

<body>

    <!-- Header Section -->
    <header>
        <h1>Welcome to ARTLAB</h1>
        <a id="logoutLink" href="logOut.php" class="logout-btn">Logout</a>
    </header>

    <!-- Main Content Section -->
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
        <p>You have successfully logged in.</p>
    </div>

    <!-- Footer Section -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Magdasal. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Block the back button if the user is logged in
            if (<?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>) {
                // Push a new state to block the user from navigating back to the previous pages
                window.history.pushState(null, null, window.location.href);

                // Periodically push state to block back navigation
                setInterval(function () {
                    window.history.pushState(null, null, window.location.href);
                }, 100);

                // Prevent navigating back when the user tries to use the back button
                window.onpopstate = function () {
                    window.history.pushState(null, null, window.location.href);
                };
            }
        });
    </script>

</body>

</html>
