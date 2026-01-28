<?php
// Include the database connection file
include 'connection.php';  // Use the correct file path if it's in a different directory
?>



<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Artlab</title>
    <link rel="stylesheet" href="../html/script.php?dir=css&file=login.css">
    
  </head>
  <body>
  
    <nav class="navbar">
      <div class="navbar-logo">
        <a>Artlab</a>
      </div>
      <div class="navbar-auth">
        <a href="../html/login.php" class="btn-register">Login</a>
        <a href="../html/register.php" class="btn-register">Register</a>
      </div>
    </nav>

    <header class="hero-section">
      <h1>Go to the World and create ART</h1>
      <p>Collaborate with other artist online to create your masterpiece.</p>
      <!-- <a href="#" class="btn-cta">Get Started</a> -->
    </header>

    <footer>
      <p>&copy; 2024 Magdasal. All rights reserved.</p>
    </footer>
  </body>
</html>
