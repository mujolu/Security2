<?php
/**
 * Email Helper - PHPMailer Wrapper Functions
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'email_config.php';

class EmailService {
    private $mail;
    private $config;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->config = EMAIL_CONFIG;
        $this->setupMailer();
    }

    /**
     * Setup PHPMailer configuration
     */
    private function setupMailer() {
        try {
            // Enable SMTP
            $this->mail->isSMTP();
            
            // SMTP Server settings
            $this->mail->Host       = $this->config['smtp_host'];
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $this->config['smtp_username'];
            $this->mail->Password   = $this->config['smtp_password'];
            $this->mail->SMTPSecure = $this->config['smtp_secure'];
            $this->mail->Port       = $this->config['smtp_port'];
            
            // From address
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            // Additional settings
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
        } catch (Exception $e) {
            throw new Exception("Mail configuration error: {$e->getMessage()}");
        }
    }

    /**
     * Send OTP via email
     * 
     * @param string $recipientEmail The recipient's email address
     * @param string $otp The OTP code
     * @param int $expiryMinutes OTP expiry time in minutes
     * @return array Response array with status and message
     */
    public function sendOTP($recipientEmail, $otp, $expiryMinutes = 2) {
        try {
            // Validate email
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address provided.'
                ];
            }

            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($recipientEmail);
            
            // Email subject and body
            $this->mail->Subject = 'Your OTP Code - Security2 System';
            
            // HTML email body
            $emailBody = $this->getOTPEmailTemplate($otp, $expiryMinutes);
            $this->mail->Body = $emailBody;
            
            // Plain text alternative
            $this->mail->AltBody = "Your OTP code is: $otp\nThis code will expire in $expiryMinutes minutes.\n\nDo not share this code with anyone.";
            
            // Send email
            if ($this->mail->send()) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully to your email.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP email.'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Email error: {$this->mail->ErrorInfo}"
            ];
        }
    }

    /**
     * Send password reset email
     * 
     * @param string $recipientEmail The recipient's email address
     * @param string $resetLink The password reset link
     * @return array Response array with status and message
     */
    public function sendPasswordResetEmail($recipientEmail, $resetLink) {
        try {
            // Validate email
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address provided.'
                ];
            }

            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($recipientEmail);
            
            // Email subject and body
            $this->mail->Subject = 'Password Reset Request - Security2 System';
            
            // HTML email body
            $emailBody = $this->getPasswordResetEmailTemplate($resetLink);
            $this->mail->Body = $emailBody;
            
            // Plain text alternative
            $this->mail->AltBody = "Click the link below to reset your password:\n$resetLink\n\nIf you did not request this, please ignore this email.";
            
            // Send email
            if ($this->mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Password reset email sent successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send password reset email.'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Email error: {$this->mail->ErrorInfo}"
            ];
        }
    }

    /**
     * Send welcome email to new user
     * 
     * @param string $recipientEmail The recipient's email address
     * @param string $userName The user's name
     * @return array Response array with status and message
     */
    public function sendWelcomeEmail($recipientEmail, $userName) {
        try {
            // Validate email
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address provided.'
                ];
            }

            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($recipientEmail);
            
            // Email subject and body
            $this->mail->Subject = 'Welcome to Security2 System';
            
            // HTML email body
            $emailBody = $this->getWelcomeEmailTemplate($userName);
            $this->mail->Body = $emailBody;
            
            // Plain text alternative
            $this->mail->AltBody = "Welcome $userName to Security2 System!\n\nYour account has been successfully created.";
            
            // Send email
            if ($this->mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Welcome email sent successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send welcome email.'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Email error: {$this->mail->ErrorInfo}"
            ];
        }
    }

    /**
     * Get OTP email HTML template
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
                        <strong>⚠️ Security Warning:</strong> Do not share this code with anyone. Security2 System staff will never ask for this code.
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

    /**
     * Get password reset email HTML template
     */
    private function getPasswordResetEmailTemplate($resetLink) {
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
                .button { background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .button:hover { background-color: #0056b3; }
                .info { color: #666; font-size: 14px; margin-top: 10px; }
                .warning { background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 20px 0; color: #721c24; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Security2 System</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Click the button below to proceed:</p>
                    <a href='$resetLink' class='button'>Reset Password</a>
                    <p class='info'>This link will expire in 24 hours.</p>
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong> If you did not request this password reset, please ignore this email and your password will remain unchanged.
                    </div>
                    <p style='color: #666; font-size: 14px;'>Or copy and paste this link in your browser:<br><code>$resetLink</code></p>
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

    /**
     * Get welcome email HTML template
     */
    private function getWelcomeEmailTemplate($userName) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #28a745; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #28a745; margin: 0; }
                .content { text-align: center; }
                .button { background-color: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .button:hover { background-color: #218838; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Security2 System!</h1>
                </div>
                <div class='content'>
                    <h2>Welcome, $userName! 👋</h2>
                    <p>Your account has been successfully created on Security2 System.</p>
                    <p>You can now log in with your credentials and start using our services.</p>
                    <a href='#' class='button'>Log In to Your Account</a>
                    <p style='color: #666; font-size: 14px; margin-top: 20px;'>If you have any questions or need assistance, please contact our support team.</p>
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

// Helper function to send OTP
function sendOTPEmail($recipientEmail, $otp, $expiryMinutes = 2) {
    try {
        $emailService = new EmailService();
        return $emailService->sendOTP($recipientEmail, $otp, $expiryMinutes);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email service error: ' . $e->getMessage()
        ];
    }
}

// Helper function to send password reset email
function sendPasswordResetEmail($recipientEmail, $resetLink) {
    try {
        $emailService = new EmailService();
        return $emailService->sendPasswordResetEmail($recipientEmail, $resetLink);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email service error: ' . $e->getMessage()
        ];
    }
}

// Helper function to send welcome email
function sendWelcomeEmail($recipientEmail, $userName) {
    try {
        $emailService = new EmailService();
        return $emailService->sendWelcomeEmail($recipientEmail, $userName);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email service error: ' . $e->getMessage()
        ];
    }
}

?>
