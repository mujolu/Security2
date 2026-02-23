<?php
session_start();
require 'connection.php';

// Initialize step if not set
if (!isset($_SESSION['recovery_step'])) {
    $_SESSION['recovery_step'] = 1;
}

if (isset($_POST['back_step']) && $_SESSION['recovery_step'] > 1) {
    $_SESSION['recovery_step']--;
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
        // Check if user exists (PDO)
        $query = "SELECT * FROM registered_users WHERE email = :val OR username = :val LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':val' => $email_or_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['recovery_user_id'] = $user['id'];
            $_SESSION['recovery_email'] = $user['email'];
            $_SESSION['recovery_step'] = 2;
            unset($_SESSION['otp_verified']);

            // Determine if account has security questions set in user_security_settings
            $hasSecurity = false;
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS user_security_settings (
                    user_id VARCHAR(9) NOT NULL,
                    question_number TINYINT NOT NULL,
                    security_question VARCHAR(255) NOT NULL,
                    security_answer VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, question_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $sqStmt = $conn->prepare("SELECT question_number, security_question, security_answer FROM user_security_settings WHERE user_id = :id ORDER BY question_number ASC");
                $sqStmt->execute([':id' => $user['id']]);
                $rows = $sqStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows) && count($rows) >= 3) {
                    $hasSecurity = true;
                    $sq = [];
                    foreach ($rows as $row) {
                        $index = (int)$row['question_number'];
                        $sq[$index] = [
                            'security_question' => $row['security_question'],
                            'security_answer' => $row['security_answer']
                        ];
                    }
                    $_SESSION['security_questions'] = $sq;
                }
            } catch (Exception $e) {
                // ignore; treat as no security questions
            }
            $_SESSION['has_security'] = $hasSecurity;
           


            // Generate OTP and send via Mailtrap/email helper
            $otp = random_int(100000, 999999);
            // $_SESSION['recovery_otp'] = $otp;
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + 60); // 1 minute
         
            $insert = $conn->prepare("
                INSERT INTO reset_password (user_id, email, otp_hash, expires_at)
                VALUES (:uid, :email, :otp, :exp)
            ");
            $insert->execute([
                 ':uid'   => $user['id'],
                 ':email'=> $user['email'],
                 ':otp'  => $otpHash,
                 ':exp'  => $expiresAt
            ]);

            $_SESSION['otp_expires'] = $expiresAt;

            // Send OTP via Gmail OAuth2 or email helper
            require_once 'email_helper.php';
            require_once 'gmail_oauth2_helper.php';
            
            // Use Gmail OAuth2 if configured
            $mailService = $_ENV['MAIL_SERVICE'] ?? 'mailtrap';
            if ($mailService === 'gmail_oauth2') {
                $result = sendOTPViaGmail($user['email'], $otp, 1); // 10 minutes expiry
            } else {
                $result = sendOTPEmail($user['email'], $otp, 1); // Fallback to email_helper
            }

            if ($result['success']) {
                $success = "OTP has been sent to your email.";
            } else {
                $success = "Error sending OTP: " . $result['message'];
            }
        } else {
            $error = "Email or username not found.";
        }
    }
}

// Resend OTP handler
// RESEND OTP (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {

    if (!isset($_SESSION['recovery_email'], $_SESSION['recovery_user_id'])) {
        $error = "Session expired. Please start again.";
    } else {

        // Generate new OTP
        $otp = random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + 60); // 1 minute

        // Save OTP
        $insert = $conn->prepare("
            INSERT INTO reset_password (user_id, email, otp_hash, expires_at)
            VALUES (:uid, :email, :otp, :exp)
        ");
        $insert->execute([
            ':uid'   => $_SESSION['recovery_user_id'],
            ':email' => $_SESSION['recovery_email'],
            ':otp'   => $otpHash,
            ':exp'   => $expiresAt
        ]);

        $_SESSION['otp_expires'] = $expiresAt;
        unset($_SESSION['otp_verified']);

        // Send email
        require_once 'email_helper.php';
        require_once 'gmail_oauth2_helper.php';

        $mailService = $_ENV['MAIL_SERVICE'] ?? 'mailtrap';
        $result = ($mailService === 'gmail_oauth2')
            ? sendOTPViaGmail($_SESSION['recovery_email'], $otp, 1)
            : sendOTPEmail($_SESSION['recovery_email'], $otp, 1);

        if ($result['success']) {
            $success = "A new OTP has been sent to your email.";
            $_SESSION['recovery_step'] = 2; // stay on OTP step
        } else {
            $error = "Error sending OTP: " . $result['message'];
        }
    }
}

            

// Step 2: OTP verification (DATABASE-BASED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2_submit'])) {
    $entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if (empty($entered_otp)) {
        $error = "Please enter the OTP.";
    } else {

        // Fetch latest OTP for this user/email
        $stmt = $conn->prepare("
            SELECT id, otp_hash, expires_at, used
            FROM reset_password
            WHERE email = :email
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $_SESSION['recovery_email']
        ]);

        $otpRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRow) {
            $error = "No OTP request found.";
        } elseif ($otpRow['used'] == 1) {
            $error = "This OTP has already been used.";
        } elseif (strtotime($otpRow['expires_at']) < time()) {
            $error = "OTP has expired.";
        } elseif (!password_verify($entered_otp, $otpRow['otp_hash'])) {
            $error = "Invalid OTP. Please try again.";
        } else {
            // OTP is valid — mark as used
            $update = $conn->prepare("
                UPDATE reset_password
                SET used = 1
                WHERE id = :id
            ");
            $update->execute([':id' => $otpRow['id']]);

            $_SESSION['otp_verified'] = true;

            // Go to next step
            if (!empty($_SESSION['has_security'])) {
                $_SESSION['recovery_step'] = 3;
                $success = "OTP verified! Please answer the security questions.";
            } else {
                $_SESSION['recovery_step'] = 4;
                $success = "OTP verified! You may reset your password.";
            }
        }
    }
}


// Step 3: Security questions verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3_submit'])) {
    $answers_correct = 0;

    // If user has stored security answers, use them; otherwise fall back to demo questions
    if (!empty($_SESSION['has_security']) && !empty($_SESSION['security_questions'])) {
        $sq = $_SESSION['security_questions'];
        for ($i = 1; $i <= 3; $i++) {
            $field = 'answer' . $i;
            $submitted = isset($_POST[$field]) ? trim($_POST[$field]) : '';
            $correct = isset($sq[$i]['security_answer']) ? $sq[$i]['security_answer'] : '';
            $matched = false;

            if ($submitted !== '' && $correct !== '') {
                // Preferred: hashed answers
                $matched = password_verify($submitted, $correct);

                // Backward compatibility for old plain-text answers
                if (!$matched) {
                    $matched = strcasecmp($submitted, $correct) === 0;
                }
            }

            if ($matched) {
                $answers_correct++;
            }
        }
    } else {
        // Demo fallback (same as before)
        $security_answers = array(
            1 => ['correct' => 'blue'],
            2 => ['correct' => 'paris'],
            3 => ['correct' => 'pizza']
        );
        for ($i = 1; $i <= 3; $i++) {
            $answer = isset($_POST['answer' . $i]) ? trim($_POST['answer' . $i]) : '';
            if (strtolower($answer) === strtolower($security_answers[$i]['correct'])) {
                $answers_correct++;
            }
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
        // Update password in database (PDO)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['recovery_user_id'];

        $update_query = "UPDATE registered_users SET password = :pw WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $ok = $update_stmt->execute([':pw' => $hashed_password, ':id' => $user_id]);

        if ($ok) {
            // Clear recovery session
            unset($_SESSION['recovery_step']);
            unset($_SESSION['recovery_user_id']);
            unset($_SESSION['recovery_email']);
            unset($_SESSION['recovery_otp']);
            unset($_SESSION['otp_verified']);
            unset($_SESSION['has_security']);
            unset($_SESSION['security_questions']);

            $success = "Password reset successfully! Redirecting to login...";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>";
        } else {
            $error = "Failed to update password. Please try again.";
        }
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
    <?php if ($_SESSION['recovery_step'] == 2 && !isset($_SESSION['otp_verified'])): ?>

    <form method="POST">
        <div class="form-group">
            <label for="otp">Enter OTP</label>
            <p style="font-size: 12px; color: #999;">
                An OTP has been sent to <?= htmlspecialchars($_SESSION['recovery_email']) ?>
            </p>

            <p id="otpTimer" style="color:#d9534f;font-size:13px;">
                Loading timer...
            </p>

            <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6">
        </div>

        <div class="button-group">
            <button type="submit" name="back_step" class="btn-back">Back</button>
            <button type="submit" name="step2_submit" class="btn-submit">Verify OTP</button>
        </div>

        <!-- Resend button (hidden initially) -->
        <button
            type="submit"
            name="resend_otp"
            id="resendBox"
            class="btn-secondary"
            style="display:none;"
            formnovalidate
        >
            Resend OTP
        </button>
    </form>


    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const expiryTime = <?= strtotime($_SESSION['otp_expires'] ?? 'now') ?> * 1000;
        const timerEl = document.getElementById('otpTimer');
        const resendBox = document.getElementById('resendBox');

        function updateTimer() {
            const now = Date.now();
            const diff = expiryTime - now;

            if (diff <= 0) {
                timerEl.textContent = "OTP expired.";
                resendBox.style.display = "inline-block";
                return;
            }

            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);

            timerEl.textContent =
                `OTP expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;

            setTimeout(updateTimer, 1000);
        }

        updateTimer();
    });
    </script>


        <?php if (isset($_SESSION['otp_expires'])): ?>
            <script>
                const otpExpiryTime = <?= strtotime($_SESSION['otp_expires']) ?> * 1000; // convert to ms
            </script>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Step 3: Security Questions -->
    <?php if ($_SESSION['recovery_step'] == 3): ?>

        <form method="POST">
            <p style="margin-bottom: 20px; color: #666; text-align: center;">Answer at least 2 out of 3 security questions correctly</p>

            <?php
                $recoveryQuestions = $_SESSION['security_questions'] ?? [];
                for ($i = 1; $i <= 3; $i++):
                    $questionText = $recoveryQuestions[$i]['security_question'] ?? "Security Question $i";
            ?>
                <div class="question-group">
                    <p><strong>Question <?= $i ?>:</strong> <?= htmlspecialchars($questionText) ?></p>
                    <label for="answer<?= $i ?>">Your answer:</label>
                    <input type="text" id="answer<?= $i ?>" name="answer<?= $i ?>" placeholder="Enter your answer" required>
                </div>
            <?php endfor; ?>

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
