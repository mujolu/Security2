<?php
// Start the session
session_start();

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if the user is logged in and is an artist
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'artist') {
    header("Location: login.php");
    exit();
}

// Get the username safely
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

<body class="bg-gray-100 min-h-screen">
    <div class="layout flex w-full min-h-screen">
    <!-- Sidebar -->

    <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col">
        <h2 class="text-2xl font-bold text-white mb-5">ARTLAB</h2>

        <div class="flex flex-col items-center text-center mt-8">
            <img src="/Security2/images/profilepic.jpg" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-yellow-500 shadow-md mb-4">
            <h4 class="text-white font-semibold mb-3">Welcome, <?php echo htmlspecialchars($username); ?>!</h4>
        </div>

        <nav class="flex flex-col gap-4 mt-8">
            <a href="#" onclick="showSection('artworkgallery', this)" 
               class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">
                Artwork Gallery
            </a>
            <a href="#" onclick="showSection('collaborations', this)" 
               class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Collaborations
            </a>
            <a href="#" onclick="showSection('marketplace', this)" 
               class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Marketplace
            </a>
                <a href="#" onclick="showSection('sales', this)" 
               class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Sales and Earnings
            </a>
            <a href="#" onclick="showSection('settings', this)" 
               class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">
                Settings
            </a>
        </nav>
    </aside>


<!-- Main Content -->
    <main class="flex-1 p-6 bg-gray-100">

        <!-- Header -->
        <header class="bg-gray-800 text-white rounded-xl shadow-md p-8 mb-5 relative flex justify-between items-center mt-10">
            <h3 class="text-xl font-bold z-10 relative">
                Welcome to <span class="text-yellow-500 brand-sketchy">ARTLAB!</span>
            </h3>
            <a href="logOut.php" class="bg-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-yellow-700 transition-colors duration-300 z-10 relative">
                Logout
            </a>
            <img src="/Security2/images/welcome png.png" alt="Welcoming artist"
                 class="absolute left-1/2 -translate-x-[98%] -bottom-14 h-48 md:h-56 lg:h-64 opacity-95 drop-shadow-xl z-0 pointer-events-none"/>
        </header>

        <!--ArtworkGallery Section -->
        <section id="artworkgallery" class="">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-indigo-300 text-indigo-800 shadow-lg rounded-xl p-6 flex items-center gap-4 hover:scale-105 transform transition-all duration-300">
                    <div class="bg-indigo-200 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4-4-4-4m12 8l4-4-4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium">Artworks</h4>
                        <p class="text-2xl font-bold mt-1">12</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Collaborations Section (hidden) -->
        <section id="collaborations" class="hidden">
            <h2 class="text-2xl font-bold mb-4">Collaborations</h2>
            <p>Collaborations section coming soon...</p>
        </section>

        <!-- marketplace Section (hidden) -->
        <section id="marketplace" class="hidden">
            <h2 class="text-2xl font-bold mb-4">Marketplace</h2>
            <p>Marketplace section coming soon...</p>
        </section>

        <!-- Sales and Earnings Section (hidden) -->
        <section id="sales" class="hidden">
            <h2 class="text-2xl font-bold mb-4">Sales and Earnings</h2>
            <p>Sales and Earnings section coming soon...</p>
        </section>

        <!-- Settings Section (hidden) -->
        <section id="settings" class="hidden bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">
           <?php include 'user_settings.php'; ?>
        </section>

    </main>
    <script>
    function showSection(sectionId, link) {
    // Hide all sections
    ['artworkgallery','collaborations','marketplace','sales','settings'].forEach(id=>{
        document.getElementById(id).classList.add('hidden');
    });

    // Show selected section
    document.getElementById(sectionId).classList.remove('hidden');

    // Update sidebar active link
    document.querySelectorAll('.sidebar-link').forEach(l=>{
        l.classList.remove('bg-yellow-700');
        l.classList.add('bg-gray-700');
    });

    if(link) {
        link.classList.remove('bg-gray-700');
        link.classList.add('bg-yellow-700');
    }
}

    // Set default visible section
<?php
$defaultSection = 'artworkgallery';
if (isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1) {
    $defaultSection = 'settings';
}
?>
    showSection('<?= $defaultSection ?>');
    </script>


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
    <script>
        window.onload = function() {
            // When user logs out, prevent back navigation
            if (window.history && window.history.pushState) {
                window.history.pushState(null, null, window.location.href);
                window.onpopstate = function () {
                    window.location.href = 'login.php';
                };
            }
        };
        </script>



</body>

</html>
