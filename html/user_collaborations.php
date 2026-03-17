<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// User collaboration feature removed.
header('Location: user_artwork.php');
exit();
