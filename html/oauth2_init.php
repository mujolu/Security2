<?php
/**
 * Gmail OAuth2 Authorization Initialization
 * User visits this page to authorize the application to send emails on their behalf
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'email_config.php';

use Google\Client;

// Initialize Google Client
$client = new Client();
$client->setClientId(EMAIL_CONFIG['google_client_id']);
$client->setClientSecret(EMAIL_CONFIG['google_client_secret']);
$client->setRedirectUri(EMAIL_CONFIG['google_redirect_uri']);
$client->setScopes(['https://www.googleapis.com/auth/gmail.send']);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force consent screen

// Generate authorization URL
$authUrl = $client->createAuthUrl();

// Redirect to Google authorization
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
?>
