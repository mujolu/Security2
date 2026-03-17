<?php
/**
 * Gmail OAuth2 Callback Handler
 * Google redirects users here after authorization
 * Exchanges authorization code for refresh token
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'email_config.php';
require_once 'gmail_oauth2_helper.php';

use Google\Client;

function applyGoogleClientSslConfig(Client $client): void {
    $caCandidates = [
        __DIR__ . '/../certs/cacert.pem',
        'D:/xammp/apache/bin/curl-ca-bundle.crt',
        'C:/xammp/apache/bin/curl-ca-bundle.crt',
        $_ENV['CURL_CA_BUNDLE'] ?? '',
        ini_get('curl.cainfo') ?: '',
        ini_get('openssl.cafile') ?: ''
    ];

    foreach ($caCandidates as $caFile) {
        if (!empty($caFile) && is_file($caFile) && is_readable($caFile)) {
            $client->setHttpClient(new \GuzzleHttp\Client([
                'verify' => $caFile,
                'timeout' => 30,
            ]));
            return;
        }
    }
}

// Check for authorization code
if (!isset($_GET['code'])) {
    $error = $_GET['error'] ?? 'Unknown error';
    die("Authorization failed: $error");
}

$code = $_GET['code'];

try {
    // Initialize Google Client
    $client = new Client();
    $client->setClientId(EMAIL_CONFIG['google_client_id']);
    $client->setClientSecret(EMAIL_CONFIG['google_client_secret']);
    $client->setRedirectUri(EMAIL_CONFIG['google_redirect_uri']);
    applyGoogleClientSslConfig($client);
    
    // Exchange authorization code for tokens
    $token = $client->fetchAccessTokenWithAuthCode($code);
    
    if (isset($token['error'])) {
        throw new Exception('Token exchange failed: ' . $token['error']);
    }
    
    // Use helper to save the refresh token
    $oauth2Service = new GmailOAuth2Service();
    $oauth2Service->saveRefreshToken($token['refresh_token'] ?? null);
    
    // Prepare success message
    $successMessage = 'Gmail OAuth2 authorization successful!<br>';
    $successMessage .= 'Your application is now authorized to send emails via Gmail.<br>';
    $successMessage .= 'You can now use the OTP functionality to send emails to user Gmail accounts.';
    
    $displaySuccess = true;
    
} catch (Exception $e) {
    $errorMessage = 'Authorization error: ' . $e->getMessage();
    $displaySuccess = false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail OAuth2 Authorization</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 30px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .info {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }
        .info h3 {
            margin-top: 0;
            color: #0c5aa0;
        }
        .info p {
            margin: 8px 0;
            font-size: 14px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($displaySuccess): ?>
            <div class="icon">✓</div>
            <h1>Authorization Successful</h1>
            <div class="success">
                <strong>✓ Gmail OAuth2 Connected</strong><br>
                <?php echo $successMessage; ?>
            </div>
            
            <div class="info">
                <h3>What's Next?</h3>
                <p>✓ Your application can now send emails via Gmail</p>
                <p>✓ OTP codes will be sent directly to user Gmail accounts</p>
                <p>✓ No more rate limiting from SMTP relays</p>
                <p>✓ Token is securely stored for future use</p>
            </div>
            
            <button class="button" onclick="window.location.href='index.php'">Return to Application</button>
        <?php else: ?>
            <div class="icon">✗</div>
            <h1>Authorization Failed</h1>
            <div class="error">
                <strong>✗ Error:</strong><br>
                <?php echo $errorMessage; ?>
            </div>
            
            <div class="info">
                <h3>Troubleshooting</h3>
                <p>1. Ensure your Google Cloud project is properly configured</p>
                <p>2. Check that Gmail API is enabled</p>
                <p>3. Verify OAuth2 credentials in .env file</p>
                <p>4. Make sure GOOGLE_REDIRECT_URI matches your current URL</p>
            </div>
            
            <button class="button" onclick="window.history.back()">Go Back</button>
        <?php endif; ?>
    </div>
</body>
</html>
