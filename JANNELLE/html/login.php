<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to Artlab</title>
    <link rel="stylesheet" href="../html/script.php?dir=css&file=login.css">
    <style>
        <style>
    #overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
    }

    .disabled-input {
        pointer-events: none;
        opacity: 0.5;
    }
</style>
    </style>
</head>

<body>
    
    <nav class="navbar">
        <div class="navbar-logo">
            <a>Artlab</a>
        </div>
        <div class="navbar-auth">
            <a href="../html/index.php" class="btn-login">Home</a>
            <a href="../html/register.php" class="btn-register">Register</a>
        </div>
    </nav>

    <div class="form-wrapper sign-in">
        <form id="loginForm" method="POST" action="processLogin.php">
            <h2>Login to <span>Artlab</span></h2>

            <div class="input-group">
                <input type="text" name="username" id="username" required>
                <label for="username">Enter Username</label>
                <div id="usernameError" class="error-message"></div> <!-- This must exist -->
            </div>

            <div class="input-group" style="position: relative;">
    <button 
        type="button" id="togglePassword" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; z-index: 2; width: 50px; padding: 0; margin-left: 200px">
        Show
    </button>
    <input 
        type="password" 
        name="password" 
        id="password" 
        required 
        style="padding-left: 10px;">
    <label for="password">Enter Password</label>
    <div id="passwordError" class="error-message"></div> <!-- This must exist -->
</div>

            <p id="timerDisplay" style="color: red;"></p>
            <br>
            <button type="submit">Login</button>
            <br>
            <button type="button" onclick="window.location.href='forgot_pass.html'">Forgot Password?</button>
            <br>
            <div class="forgot-password">
                <a href="resetpass.php" id="forgotPasswordLink" style="display: none;">Forgot Password? Reset Here</a>
                <div id="lockoutMessage" style="display: none; color: red;"></div>
            </div>
            
        </form>
    </div>
    <div id="overlay"></div>

    
    <!-- <div id="lockoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
        <div style="background: #fff; padding: 20px; border-radius: 8px; text-align: center; width: 300px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
            <div id="modalTimerDisplay" style="font-size: 16px; color: #333; margin-bottom: 10px; margin-top: 10px"></div>
        </div>
    </div> -->


    <footer>
        <p>&copy; 2024 Magdasal. All rights reserved.</p>
    </footer>
    <script src="../html/script.php?dir=js&file=personal_Information.js" defer ></script>
    <script src="../html/script.php?dir=js&file=validateLogin.js" defer ></script>
</body>

</html>