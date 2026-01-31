<?php
/**
 * Gmail OAuth2 Email Helper
 * Sends emails via Gmail API using OAuth2 tokens
 */

use Google\Client;
use Google\Service\Gmail;
use PHPMailer\PHPMailer\PHPMailer;

class GmailOAuth2Service {
    private $client;
    private $refreshToken;
    
    public function __construct() {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/email_config.php';
        
        $this->client = new Client();
        $this->client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
        $this->client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        $this->client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
        $this->client->setScopes(['https://www.googleapis.com/auth/gmail.send']);
        
        // Load refresh token from storage
        $this->loadRefreshToken();
    }
    
    /**
     * Load refresh token from storage (file or database)
     */
    private function loadRefreshToken() {
        $tokenFile = __DIR__ . '/../.gmail_oauth_token.json';
        
        if (file_exists($tokenFile)) {
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            $this->refreshToken = $tokenData['refresh_token'] ?? null;
            
            // Set the refresh token to client
            if ($this->refreshToken) {
                $this->client->setAccessType('offline');
                $this->client->refreshToken($this->refreshToken);
            }
        }
    }
    
    /**
     * Save refresh token for future use
     */
    public function saveRefreshToken($refreshToken) {
        $this->refreshToken = $refreshToken;
        $tokenFile = __DIR__ . '/../.gmail_oauth_token.json';
        
        file_put_contents($tokenFile, json_encode([
            'refresh_token' => $refreshToken,
            'created_at' => date('Y-m-d H:i:s')
        ]), LOCK_EX);
    }
    
    /**
     * Get authorization URL for user to authorize
     */
    public function getAuthorizationUrl() {
        return $this->client->createAuthUrl();
    }
    
    /**
     * Exchange authorization code for tokens
     */
    public function handleAuthorizationCode($code) {
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (!isset($token['error'])) {
                $this->client->setAccessToken($token);
                
                // Save refresh token
                if (isset($token['refresh_token'])) {
                    $this->saveRefreshToken($token['refresh_token']);
                }
                
                return ['success' => true, 'message' => 'Authorization successful'];
            } else {
                return ['success' => false, 'message' => 'Authorization failed: ' . $token['error']];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send OTP email via Gmail API
     */
    public function sendOTPEmail($recipientEmail, $otp, $expiryMinutes = 2) {
        try {
            // Ensure client has valid token
            if (!$this->client->getAccessToken()) {
                return ['success' => false, 'message' => 'No valid Gmail token. Please authorize first.'];
            }
            
            // Create Gmail service
            $gmail = new Gmail($this->client);
            
            // Build email message
            $mail = new PHPMailer(true);
            $mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($recipientEmail);
            $mail->Subject = 'Your OTP Code - Security2 System';
            $mail->isHTML(true);
            
            // HTML body
            $mail->Body = $this->getOTPEmailTemplate($otp, $expiryMinutes);
            $mail->AltBody = "Your OTP code is: $otp\nThis code will expire in $expiryMinutes minutes.";
            
            // Build the raw MIME message (preSend builds headers and body)
            if (!$mail->preSend()) {
                throw new Exception('Failed to build email MIME: ' . $mail->ErrorInfo);
            }
            $message = $mail->getSentMIMEMessage();

            // Send via Gmail API
            $gMessage = new Gmail\Message();
            $gMessage->setRaw(rtrim(strtr(base64_encode($message), '+/', '-_'), '='));

            $gmail->users_messages->send('me', $gMessage);
            
            return [
                'success' => true,
                'message' => 'OTP sent successfully via Gmail'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gmail API Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * OTP Email Template
     */
    private function getOTPEmailTemplate($otp, $expiryMinutes) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #007bff; margin: 0; }
                .content { text-align: center; }
                .otp-box { background-color: #f0f0f0; border: 2px dashed #007bff; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .otp-code { font-size: 36px; font-weight: bold; color: #007bff; letter-spacing: 5px; margin: 0; }
                .info { color: #666; font-size: 14px; margin-top: 10px; }
                .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 20px 0; color: #856404; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Security2 System</h1>
                </div>
                <div class='content'>
                    <h2>Your One-Time Password (OTP)</h2>
                    <p>Your OTP code for authentication is:</p>
                    <div class='otp-box'>
                        <p class='otp-code'>$otp</p>
                    </div>
                    <p class='info'><strong>Expiry Time:</strong> $expiryMinutes minutes</p>
                    <div class='warning'>
                        <strong>⚠️ Security Warning:</strong> Do not share this code with anyone.
                    </div>
                    <p style='color: #666; font-size: 14px;'>If you did not request this OTP, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; 2026 Security2 System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

// Helper function for easy usage
function sendOTPViaGmail($email, $otp, $expiryMinutes = 2) {
    try {
        $service = new GmailOAuth2Service();
        return $service->sendOTPEmail($email, $otp, $expiryMinutes);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Gmail OAuth2 Error: ' . $e->getMessage()
        ];
    }
}
?>
