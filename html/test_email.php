<?php
/**
 * Email System Test Script
 * 
 * Run this file to test if your PHPMailer + Gmail setup is working correctly.
 * Visit: http://localhost/Security2/html/test_email.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email System Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-item {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        .pass {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .fail {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background-color: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #007bff;
            margin-top: 30px;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Email System Test Suite</h1>

        <h2>1️⃣ Configuration Checks</h2>
        
        <?php
        // Check 1: .env file exists
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            echo '<div class="test-item pass">✅ .env file exists</div>';
        } else {
            echo '<div class="test-item fail">❌ .env file not found at ' . $envPath . '</div>';
        }
        ?>

        <?php
        // Check 2: Composer autoload exists
        $composerPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($composerPath)) {
            echo '<div class="test-item pass">✅ Composer autoload.php exists</div>';
        } else {
            echo '<div class="test-item fail">❌ vendor/autoload.php not found. Run: composer install</div>';
        }
        ?>

        <?php
        // Check 3: email_config.php exists
        $configPath = __DIR__ . '/email_config.php';
        if (file_exists($configPath)) {
            echo '<div class="test-item pass">✅ email_config.php exists</div>';
        } else {
            echo '<div class="test-item fail">❌ email_config.php not found</div>';
        }
        ?>

        <?php
        // Check 4: email_helper.php exists
        $helperPath = __DIR__ . '/email_helper.php';
        if (file_exists($helperPath)) {
            echo '<div class="test-item pass">✅ email_helper.php exists</div>';
        } else {
            echo '<div class="test-item fail">❌ email_helper.php not found</div>';
        }
        ?>

        <h2>2️⃣ Environment Variables</h2>

        <?php
        require_once __DIR__ . '/../vendor/autoload.php';
        
        use Dotenv\Dotenv;
        
        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            
            $gmailEmail = $_ENV['GMAIL_EMAIL'] ?? 'NOT SET';
            $gmailPass = $_ENV['GMAIL_APP_PASSWORD'] ?? 'NOT SET';
            
            if ($gmailEmail !== 'NOT SET' && $gmailEmail !== 'your_gmail@gmail.com') {
                echo '<div class="test-item pass">✅ GMAIL_EMAIL: ' . $gmailEmail . '</div>';
            } else {
                echo '<div class="test-item fail">❌ GMAIL_EMAIL not configured properly</div>';
            }
            
            if ($gmailPass !== 'NOT SET' && $gmailPass !== 'your_app_password_here') {
                echo '<div class="test-item pass">✅ GMAIL_APP_PASSWORD: Configured (' . strlen($gmailPass) . ' characters)</div>';
            } else {
                echo '<div class="test-item fail">❌ GMAIL_APP_PASSWORD not configured properly</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-item fail">❌ Error loading .env: ' . $e->getMessage() . '</div>';
        }
        ?>

        <h2>3️⃣ PHP Extensions</h2>

        <?php
        if (extension_loaded('openssl')) {
            echo '<div class="test-item pass">✅ OpenSSL extension loaded</div>';
        } else {
            echo '<div class="test-item fail">❌ OpenSSL extension not loaded (required for Gmail SMTP)</div>';
        }
        ?>

        <h2>4️⃣ Send Test Email</h2>

        <form method="POST" action="">
            <div class="form-group">
                <label for="test_email">Test Email Address:</label>
                <input type="email" id="test_email" name="test_email" value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>" required>
                <small>Enter your email address to receive a test OTP</small>
            </div>

            <button type="submit" name="send_test">Send Test OTP Email</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
            $testEmail = $_POST['test_email'] ?? '';
            
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                echo '<div class="test-item fail result">❌ Invalid email address</div>';
            } else {
                try {
                    require_once 'email_helper.php';
                    
                    $testOtp = '123456';
                    $result = sendOTPEmail($testEmail, $testOtp, 2);
                    
                    if ($result['success']) {
                        echo '<div class="test-item pass result">✅ Test email sent successfully!</div>';
                        echo '<div style="margin-top: 10px; font-size: 12px; color: #666;">
                            Check your email (' . htmlspecialchars($testEmail) . ') for the test OTP email.
                            <br><strong>Test OTP: ' . $testOtp . '</strong>
                        </div>';
                    } else {
                        echo '<div class="test-item fail result">❌ Failed to send email</div>';
                        echo '<div style="margin-top: 10px;"><strong>Error:</strong> ' . htmlspecialchars($result['message']) . '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="test-item fail result">❌ Exception occurred</div>';
                    echo '<div style="margin-top: 10px;"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        ?>

        <h2>5️⃣ Troubleshooting</h2>

        <div class="test-item warning">
            <strong>🔍 Common Issues:</strong>
            <ul>
                <li><strong>SMTP Connection Failed:</strong> Check if port 587 is not blocked by firewall</li>
                <li><strong>Invalid Credentials:</strong> Verify Gmail App Password in .env (use 16-char password from Google)</li>
                <li><strong>Email not sent:</strong> Check if 2FA is enabled on Gmail account</li>
                <li><strong>.env not found:</strong> Create .env in project root with Gmail credentials</li>
                <li><strong>vendor/autoload.php missing:</strong> Run <code>composer install</code></li>
            </ul>
        </div>

        <h2>6️⃣ Next Steps</h2>

        <div class="test-item">
            <p>✅ If all tests pass:</p>
            <ul>
                <li>OTP emails will now be sent to real Gmail addresses</li>
                <li>Update your registration flow to use <code>sendWelcomeEmail()</code></li>
                <li>Update password reset to use <code>sendPasswordResetEmail()</code></li>
                <li>Remove any browser-based OTP display code</li>
            </ul>
        </div>
    </div>
</body>
</html>
