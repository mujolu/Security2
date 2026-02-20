<?php
/**
 * Gmail OAuth2 Setup & Status Dashboard
 * Check authorization status and manage OAuth2 settings
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'email_config.php';

$tokenFile = __DIR__ . '/../.gmail_oauth_token.json';
$authorized = file_exists($tokenFile);
$tokenInfo = null;

if ($authorized) {
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $tokenInfo = [
        'created_at' => $tokenData['created_at'] ?? 'Unknown',
        'has_refresh_token' => !empty($tokenData['refresh_token'])
    ];
}

// Handle revoke authorization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke') {
    if (file_exists($tokenFile)) {
        unlink($tokenFile);
        $authorized = false;
        $revokeMessage = 'Authorization revoked successfully.';
    }
}

$mailService = $_ENV['MAIL_SERVICE'] ?? 'gmail';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail OAuth2 Setup - Security2</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .status-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .status-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .status-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        .status-header h2 {
            font-size: 18px;
            color: #333;
        }
        .status-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        .authorized {
            border-left: 4px solid #28a745;
            background-color: #f0f9f6;
        }
        .unauthorized {
            border-left: 4px solid #ffc107;
            background-color: #fff9f0;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
            color: #0c5aa0;
        }
        .info-box h3 {
            margin-bottom: 10px;
            color: #0056b3;
        }
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .button-primary {
            background-color: #007bff;
            color: white;
        }
        .button-primary:hover {
            background-color: #0056b3;
        }
        .button-danger {
            background-color: #dc3545;
            color: white;
        }
        .button-danger:hover {
            background-color: #c82333;
        }
        .button-secondary {
            background-color: #6c757d;
            color: white;
        }
        .button-secondary:hover {
            background-color: #5a6268;
        }
        .divider {
            border-top: 1px solid #dee2e6;
            margin: 30px 0;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .checklist {
            list-style: none;
        }
        .checklist li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .checklist li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        .checklist li.pending:before {
            content: "○ ";
            color: #ffc107;
        }
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .footer {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 20px 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Gmail OAuth2 Setup</h1>
            <p>Security2 System - Email Authorization Dashboard</p>
        </div>
        
        <div class="content">
            <?php if (isset($revokeMessage)): ?>
                <div class="success-message">✓ <?php echo $revokeMessage; ?></div>
            <?php endif; ?>
            
            <!-- Status Section -->
            <div class="section">
                <div class="section-title">Authorization Status</div>
                
                <?php if ($authorized): ?>
                    <div class="status-card authorized">
                        <div class="status-header">
                            <div class="status-icon">✓</div>
                            <h2>Gmail OAuth2 Authorized</h2>
                        </div>
                        <div class="status-text">
                            Your application is authorized to send emails via Gmail API.
                            <br><strong>Authorization created:</strong> <?php echo $tokenInfo['created_at']; ?>
                            <br><strong>Refresh token:</strong> <?php echo $tokenInfo['has_refresh_token'] ? '✓ Available' : '✗ Missing'; ?>
                        </div>
                        <div class="button-group">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="revoke">
                                <button type="submit" class="button button-danger" onclick="return confirm('Revoke Gmail authorization?')">
                                    Revoke Authorization
                                </button>
                            </form>
                            <a href="index.php" class="button button-secondary">Return to App</a>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h3>✓ Ready to Send Emails</h3>
                        Your OTP emails will now be sent via Gmail API:
                        <ul style="margin: 10px 0 0 20px;">
                            <li>Sent from: <?php echo EMAIL_CONFIG['from_email']; ?></li>
                            <li>Service: Gmail OAuth2</li>
                            <li>Status: Active</li>
                        </ul>
                    </div>
                    
                <?php else: ?>
                    <div class="status-card unauthorized">
                        <div class="status-header">
                            <div class="status-icon">⚠</div>
                            <h2>Gmail OAuth2 Not Authorized</h2>
                        </div>
                        <div class="status-text">
                            Click the button below to authorize your Gmail account and enable email sending.
                        </div>
                        <div class="button-group">
                            <a href="oauth2_init.php" class="button button-primary">
                                Authorize with Gmail
                            </a>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h3>How it works</h3>
                        <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Click "Authorize with Gmail" button</li>
                            <li>Sign in with your Google account</li>
                            <li>Grant permission to send emails</li>
                            <li>You'll be redirected back automatically</li>
                            <li>OTP emails will be sent to users immediately</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="divider"></div>
            
            <!-- Setup Section -->
            <div class="section">
                <div class="section-title">Setup Instructions</div>
                
                <h3 style="color: #333; margin: 20px 0 10px 0;">Prerequisites</h3>
                <ul class="checklist">
                    <li>Google Cloud Project created</li>
                    <li>Gmail API enabled in project</li>
                    <li>OAuth2 credentials generated (Client ID & Secret)</li>
                    <li>Credentials added to .env file</li>
                </ul>
                
                <h3 style="color: #333; margin: 20px 0 10px 0;">Required .env Variables</h3>
                <div class="code-block">
MAIL_SERVICE=gmail_oauth2
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost/Security2/html/oauth2_callback.php
MAIL_FROM_EMAIL=your-email@gmail.com
MAIL_FROM_NAME="Security2 System"
                </div>
                
                <h3 style="color: #333; margin: 20px 0 10px 0;">Current Configuration</h3>
                <div class="code-block">
MAIL_SERVICE: <strong><?php echo $mailService; ?></strong><br>
FROM_EMAIL: <strong><?php echo EMAIL_CONFIG['from_email']; ?></strong><br>
FROM_NAME: <strong><?php echo EMAIL_CONFIG['from_name']; ?></strong><br>
OAUTH2_CONFIGURED: <strong><?php echo (!empty(EMAIL_CONFIG['google_client_id']) ? '✓ Yes' : '✗ No'); ?></strong>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <!-- Testing Section -->
            <div class="section">
                <div class="section-title">Testing</div>
                <div class="info-box">
                    <p>After authorization, OTP emails will automatically be sent to registered user emails when they request password recovery or sign up.</p>
                    <p style="margin-top: 10px;"><strong>Test the flow:</strong></p>
                    <ol style="margin: 10px 0 0 20px;">
                        <li>Go to password reset page</li>
                        <li>Enter your registered email</li>
                        <li>Check your email for OTP</li>
                        <li>Verify OTP on the platform</li>
                    </ol>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <!-- Troubleshooting Section -->
            <div class="section">
                <div class="section-title">Troubleshooting</div>
                
                <div style="margin: 15px 0;">
                    <h4 style="color: #333; margin-bottom: 8px;">Authorization button redirects to error page</h4>
                    <p style="color: #666; font-size: 14px;">Check that GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are correct and GOOGLE_REDIRECT_URI matches your current URL exactly.</p>
                </div>
                
                <div style="margin: 15px 0;">
                    <h4 style="color: #333; margin-bottom: 8px;">Emails not sending after authorization</h4>
                    <p style="color: #666; font-size: 14px;">Verify MAIL_SERVICE is set to 'gmail_oauth2' in .env file and MAIL_FROM_EMAIL is valid.</p>
                </div>
                
                <div style="margin: 15px 0;">
                    <h4 style="color: #333; margin-bottom: 8px;">Need to re-authorize?</h4>
                    <p style="color: #666; font-size: 14px;">Click "Revoke Authorization" above, then "Authorize with Gmail" to start fresh.</p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Gmail OAuth2 Dashboard | Security2 System | © 2026 All Rights Reserved</p>
        </div>
    </div>
</body>
</html>
