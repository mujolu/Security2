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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <title>Welcome to Artlab</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #515151;
        }
        header {
            background-color: #333;
            color: white;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            border-radius: 12px;

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
            border-radius: 12px;
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
        .brand-sketchy {
            font-family: 'Permanent Marker', cursive;
            color: #ff8614;
            font-weight: 500;
            font-size:30px;
            letter-spacing: 2px;
            text-shadow:
             
                0 0 6px rgba(247, 220, 111, 0.6),
                1px 1px 0 #000;
        }


        /* Layout */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background-color:#333
            border-right: 1px solid #eee;
            padding: 20px;
        }

        .logo {
            font-size: 20px;
            margin-bottom: 30px;
            letter-spacing: 2px;
        }

        /* Navigation */
        .sidebar nav a {
            display: block;
            padding: 10px 0;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }

        .sidebar nav a:hover {
            color: #000;
        }

        .sidebar nav a.active {
            font-weight: bold;
            border-left: 3px solid #000;
            padding-left: 10px;
        }

        /* Main content */
        .main-content {
            flex: 1;
            padding: 30px;
            background-color: #ffffff;
        }
 

    </style>
</head>

<body>
    <div class="layout">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 min-h-screen p-6">
        <h2 class="text-2xl font-bold text-white mb-5">ARTLAB</h2>
        <div class="flex flex-col items-center justify-center text-center mt-8">
            
            <!-- Circle Image -->
            <img
                src="/Security2/images/profilepic.jpg"
                alt="Profile"
                class="w-24 h-24 rounded-full object-cover border-4 border-yellow-500 shadow-md mb-4"
            >

            <!-- Welcome Text -->
            <h4 class="text-white font-semibold mb-3">
                Welcome, <?php echo htmlspecialchars($username); ?>!
            </h4>
        </div>

<nav class="flex flex-col gap-4">
            <!-- Dashboard -->
            <a href="#" class="bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Dashboard
            </a>

            <!-- Projects -->
            <a href="#" class="bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Projects
            </a>

            <!-- Gallery -->
            <a href="#" class="bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Gallery
            </a>

            <!-- Settings -->
            <a href="#" class="bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Settings
            </a>
        </nav>
    </aside>


    <!-- Main Content -->
    <main class="main-content">

    <header class="bg-gray-800 text-white rounded-xl shadow-md p-8 mb-5 relative overflow-visible flex justify-between items-center mt-10">        
        <!-- Header Text -->
        <h3 class="text-xl font-bold z-10 relative">
            Welcome to <span class="text-yellow-500 brand-sketchy">ARTLAB!</span>
        </h3>

        <!-- Logout Button -->
        <a href="logOut.php" class="bg-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-yellow-700 transition-colors duration-300 z-10 relative">
            Logout
        </a>

        <!-- Cartoon Character Image -->
       <img
        src="/Security2/images/welcome png.png"
        alt="Welcoming artist"
        class="absolute left-1/2 -translate-x-[98%] -bottom-14
                h-48 md:h-56 lg:h-64
                opacity-95 drop-shadow-xl z-0 pointer-events-none"
        />
        </header>


    <!-- Main Content Section -->
    <div class="container">
        <p>You have successfully logged in.</p>
    </div>

    <!-- Dashboard -->
    <section class="dashboard">

        <!-- Stats -->
        <!-- Stats Section -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-8">

        <!-- Artworks Card -->
        <div class="bg-indigo-300 text-indigo-800 shadow-lg rounded-xl p-6 flex items-center gap-4 hover:scale-105 transform transition-all duration-300">
            <!-- Icon -->
            <div class="bg-indigo-200 p-3 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4-4-4-4m12 8l4-4-4-4" />
            </svg>
            </div>
            <!-- Text -->
            <div>
            <h4 class="text-sm font-medium">Artworks</h4>
            <p class="text-2xl font-bold mt-1">12</p>
            </div>
        </div>

        <!-- Collaborations Card -->
        <div class="bg-green-200 text-green-800 shadow-lg rounded-xl p-6 flex items-center gap-4 hover:scale-105 transform transition-all duration-300">
            <!-- Icon -->
            <div class="bg-green-200 p-3 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-8 0v2M12 7a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
            </div>
            <!-- Text -->
            <div>
            <h4 class="text-sm font-medium">Collaborations</h4>
            <p class="text-2xl font-bold mt-1">5</p>
            </div>
        </div>

        <!-- Sales Card -->
        <div class="bg-yellow-200 text-yellow-800 shadow-lg rounded-xl p-6 flex items-center gap-4 hover:scale-105 transform transition-all duration-300">
            <!-- Icon -->
            <div class="bg-yellow-200 p-3 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.866 0-7 3.134-7 7h14c0-3.866-3.134-7-7-7z" />
            </svg>
            </div>
            <!-- Text -->
            <div>
            <h4 class="text-sm font-medium">Sales</h4>
            <p class="text-2xl font-bold mt-1">₱2,500</p>
            </div>
        </div>

        </div>



        <!-- Collaboration / Artwork Area -->
<!-- Art / Collaboration Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">

        <!-- Add Artwork Card -->
        <div class="flex flex-col items-center justify-center h-48 bg-white border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-indigo-500 hover:scale-105 transform transition-all duration-300">
            <span class="text-5xl text-gray-400">+</span>
            <p class="mt-2 text-gray-500 font-medium">Add Artwork</p>
        </div>

        <!-- Artwork Card 1 -->
        <div class="bg-white rounded-xl shadow-lg p-4 h-48 flex flex-col justify-end hover:shadow-2xl transition-shadow duration-300">
            <p class="font-bold text-lg text-gray-900">Abstract Thoughts</p>
            <p class="text-gray-500 text-sm mt-1">by You</p>
        </div>

        <!-- Artwork Card 2 -->
        <div class="bg-white rounded-xl shadow-lg p-4 h-48 flex flex-col justify-end hover:shadow-2xl transition-shadow duration-300">
            <p class="font-bold text-lg text-gray-900">Collab: Night City</p>
            <p class="text-gray-500 text-sm mt-1">3 artists</p>
        </div>

        </div>


    </section>
   
    </main>
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
