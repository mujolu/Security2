<?php
/**
 * Email Configuration for PHPMailer
 * Supports: Gmail SMTP, Mailtrap, or other SMTP services
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Determine which email service to use
$mailService = $_ENV['MAIL_SERVICE'] ?? 'gmail';

// Get common settings
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'Security2 System';
$mailFromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@security2.com';
$otpExpiry = (int)($_ENV['OTP_EXPIRY_MINUTES'] ?? 2);

// Configuration based on service
if ($mailService === 'gmail_oauth2') {
    // Gmail OAuth2 configuration
    define('EMAIL_CONFIG', [
        'from_email'    => $mailFromEmail,
        'from_name'     => $mailFromName,
        'otp_expiry'    => $otpExpiry,
        'service'       => 'gmail_oauth2',
        'google_client_id'     => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
        'google_client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
        'google_redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI'] ?? ''
    ]);
} elseif ($mailService === 'mailtrap') {
    // Mailtrap configuration
    define('EMAIL_CONFIG', [
        'smtp_host'     => $_ENV['MAILTRAP_HOST'] ?? 'smtp.mailtrap.io',
        'smtp_port'     => (int)($_ENV['MAILTRAP_PORT'] ?? 2525),
        'smtp_username' => $_ENV['MAILTRAP_USERNAME'] ?? '',
        'smtp_password' => $_ENV['MAILTRAP_PASSWORD'] ?? '',
        'from_email'    => $mailFromEmail,
        'from_name'     => $mailFromName,
        'smtp_secure'   => 'tls',
        'otp_expiry'    => $otpExpiry,
        'service'       => 'mailtrap'
    ]);
} else {
    // Gmail configuration (default)
    define('EMAIL_CONFIG', [
        'smtp_host'     => 'smtp.gmail.com',
        'smtp_port'     => 587,
        'smtp_username' => $_ENV['GMAIL_EMAIL'] ?? '',
        'smtp_password' => $_ENV['GMAIL_APP_PASSWORD'] ?? '',
        'from_email'    => $mailFromEmail,
        'from_name'     => $mailFromName,
        'smtp_secure'   => 'tls',
        'otp_expiry'    => $otpExpiry,
        'service'       => 'gmail'
    ]);
}

// Validate configuration
if (empty(EMAIL_CONFIG['smtp_username']) || empty(EMAIL_CONFIG['smtp_password'])) {
    error_log('WARNING: Email credentials not configured in .env file');
}

?>
