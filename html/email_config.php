<?php
/**
 * Email Configuration for PHPMailer with Gmail SMTP
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get environment variables with fallbacks
$gmailEmail = $_ENV['GMAIL_EMAIL'] ?? getenv('GMAIL_EMAIL');
$gmailPassword = $_ENV['GMAIL_APP_PASSWORD'] ?? getenv('GMAIL_APP_PASSWORD');
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME');
$mailFromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? getenv('MAIL_FROM_EMAIL');
$otpExpiry = (int)($_ENV['OTP_EXPIRY_MINUTES'] ?? getenv('OTP_EXPIRY_MINUTES') ?? 2);

// Email configuration constants
define('EMAIL_CONFIG', [
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_username' => $gmailEmail,
    'smtp_password' => $gmailPassword,
    'from_email'    => $mailFromEmail,
    'from_name'     => $mailFromName,
    'smtp_secure'   => 'tls',
    'otp_expiry'    => $otpExpiry
]);

// Validate configuration
if (empty(EMAIL_CONFIG['smtp_username']) || empty(EMAIL_CONFIG['smtp_password'])) {
    error_log('WARNING: Gmail credentials not configured in .env file');
}

?>
