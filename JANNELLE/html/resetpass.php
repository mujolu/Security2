<?php
session_start();
require 'connection.php';

// Initialize step if not set
if (!isset($_SESSION['recovery_step'])) {
    $_SESSION['recovery_step'] = 1;
}

// Handle form submissions
$error = '';
$success = '';

// Step 1: Email/Username verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1_submit'])) {
    $email_or_username = isset($_POST['email_username']) ? trim($_POST['email_username']) : '';
    
    if (empty($email_or_username)) {
        $error = "Please enter your email or username.";
    } else {
        // Check if user exists
        $query = "SELECT id, email FROM users WHERE email = ? OR username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['recovery_user_id'] = $user['id'];
            $_SESSION['recovery_email'] = $user['email'];
            $_SESSION['recovery_step'] = 2;
            // In real scenario, send OTP to email
            $_SESSION['recovery_otp'] = rand(100000, 999999); // Temporary OTP for demo
            $success = "OTP has been sent to your email.";
        } else {
            $error = "Email or username not found.";
        }
        $stmt->close();
    }
}

// Step 2: OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2_submit'])) {
    $entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP.";
    } elseif ($entered_otp == $_SESSION['recovery_otp']) {
        $_SESSION['recovery_step'] = 3;
        $_SESSION['otp_verified'] = true;
        $success = "OTP verified! Please answer the security questions.";
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}

// Step 3: Security questions verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3_submit'])) {
    // For demo purposes, we'll use predefined answers
    // In production, fetch from database and compare
    $security_answers = array(
        1 => ['correct' => 'blue', 'question' => 'What is your favorite color?'],
        2 => ['correct' => 'paris', 'question' => 'What is the capital of France?'],
        3 => ['correct' => 'pizza', 'question' => 'What is your favorite food?']
    );
    
    $answers_correct = 0;
    for ($i = 1; $i <= 3; $i++) {
        $answer = isset($_POST['answer' . $i]) ? trim($_POST['answer' . $i]) : '';
        if (strtolower($answer) === strtolower($security_answers[$i]['correct'])) {
            $answers_correct++;
        }
    }
    
    if ($answers_correct >= 2) { // At least 2 correct answers
        $_SESSION['recovery_step'] = 4;
        $success = "Security questions verified! You can now reset your password.";
    } else {
        $error = "Incorrect answers. Please try again.";
    }
}

// Step 4: Password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step4_submit'])) {
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    // Validation
    if (empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one digit.";
    } elseif (!preg_match('/[!@#$%^&*]/', $password)) {
        $error = "Password must contain at least one special character (!@#$%^&*).";
    } else {
        // Update password in database
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['recovery_user_id'];
        
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // Clear recovery session
            unset($_SESSION['recovery_step']);
            unset($_SESSION['recovery_user_id']);
            unset($_SESSION['recovery_email']);
            unset($_SESSION['recovery_otp']);
            unset($_SESSION['otp_verified']);
            
            $success = "Password reset successfully! Redirecting to login...";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>";
        } else {
            $error = "Failed to update password. Please try again.";
        }
        $update_stmt->close();
    }
}

// Handle back button
if (isset($_POST['back_step'])) {
    $_SESSION['recovery_step'] = max(1, $_SESSION['recovery_step'] - 1);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .step-indicator {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .question-group {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .question-group label {
            margin-bottom: 10px;
            color: #555;
        }

        .question-group p {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .error {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }

        .success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #388e3c;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: #e0e0e0;
            color: #333;
        }

        .btn-back:hover {
            background: #d0d0d0;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #ddd;
            border-radius: 4px;
        }

        .requirement {
            margin: 5px 0;
        }

        .requirement.met {
            color: #388e3c;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Account Recovery</h1>
    <div class="step-indicator">Step <?php echo $_SESSION['recovery_step']; ?> of 4</div>

    <?php if ($error): ?>
        <div class="message error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Step 1: Email/Username -->
    <?php if ($_SESSION['recovery_step'] == 1): ?>
        <form method="POST">
            <div class="form-group">
                <label for="email_username">Enter Your Email or Username</label>
                <input type="text" id="email_username" name="email_username" placeholder="example@email.com or username" required>
            </div>

            <button type="submit" name="step1_submit" class="btn-submit">Next</button>
        </form>
    <?php endif; ?>

    <!-- Step 2: OTP Verification -->
    <?php if ($_SESSION['recovery_step'] >= 2 && isset($_SESSION['recovery_email'])): ?>
        <form method="POST">
            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <p style="font-size: 12px; color: #999; margin-bottom: 10px;">An OTP has been sent to <?php echo htmlspecialchars($_SESSION['recovery_email']); ?></p>
                <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
            </div>

            <div class="button-group">
                <button type="submit" name="back_step" class="btn-back">Back</button>
                <button type="submit" name="step2_submit" class="btn-submit">Verify OTP</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- Step 3: Security Questions -->
    <?php if ($_SESSION['recovery_step'] >= 3 && isset($_SESSION['otp_verified'])): ?>
        <form method="POST">
            <p style="margin-bottom: 20px; color: #666; text-align: center;">Answer at least 2 out of 3 security questions correctly</p>

            <div class="question-group">
                <p><strong>Question 1:</strong> What is your favorite color?</p>
                <label for="answer1">Choose an answer:</label>
                <select id="answer1" name="answer1" required>
                    <option value="">-- Select an answer --</option>
                    <option value="red">Red</option>
                    <option value="blue">Blue</option>
                    <option value="green">Green</option>
                </select>
            </div>

            <div class="question-group">
                <p><strong>Question 2:</strong> What is the capital of France?</p>
                <label for="answer2">Choose an answer:</label>
                <select id="answer2" name="answer2" required>
                    <option value="">-- Select an answer --</option>
                    <option value="london">London</option>
                    <option value="paris">Paris</option>
                    <option value="berlin">Berlin</option>
                </select>
            </div>

            <div class="question-group">
                <p><strong>Question 3:</strong> What is your favorite food?</p>
                <label for="answer3">Choose an answer:</label>
                <select id="answer3" name="answer3" required>
                    <option value="">-- Select an answer --</option>
                    <option value="pizza">Pizza</option>
                    <option value="salad">Salad</option>
                    <option value="sushi">Sushi</option>
                </select>
            </div>

            <div class="button-group">
                <button type="submit" name="back_step" class="btn-back">Back</button>
                <button type="submit" name="step3_submit" class="btn-submit">Verify Answers</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- Step 4: Password Reset -->
    <?php if ($_SESSION['recovery_step'] == 4): ?>
        <form method="POST">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Enter new password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
            </div>

            <div class="password-requirements">
                <strong>Password must contain:</strong>
                <div class="requirement" id="length">✗ At least 8 characters</div>
                <div class="requirement" id="uppercase">✗ At least one uppercase letter (A-Z)</div>
                <div class="requirement" id="lowercase">✗ At least one lowercase letter (a-z)</div>
                <div class="requirement" id="number">✗ At least one digit (0-9)</div>
                <div class="requirement" id="special">✗ At least one special character (!@#$%^&*)</div>
            </div>

            <div class="button-group">
                <button type="submit" name="back_step" class="btn-back">Back</button>
                <button type="submit" name="step4_submit" class="btn-submit">Reset Password</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        const requirementsList = {
            length: /^.{8,}$/,
            uppercase: /[A-Z]/,
            lowercase: /[a-z]/,
            number: /[0-9]/,
            special: /[!@#$%^&*]/
        };

        passwordInput.addEventListener('input', function() {
            Object.keys(requirementsList).forEach(key => {
                const element = document.getElementById(key);
                if (element && requirementsList[key].test(this.value)) {
                    element.classList.add('met');
                    element.textContent = '✓ ' + element.textContent.substring(2);
                } else if (element) {
                    element.classList.remove('met');
                    element.textContent = '✗ ' + element.textContent.substring(2);
                }
            });
        });
    }
</script>

</body>
</html>
