<?php
/**
 * Gmail OAuth2 Authorization Initialization
 * User visits this page to authorize the application to send emails on their behalf
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'email_config.php';

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

// Initialize Google Client
$client = new Client();
$client->setClientId(EMAIL_CONFIG['google_client_id']);
$client->setClientSecret(EMAIL_CONFIG['google_client_secret']);
$client->setRedirectUri(EMAIL_CONFIG['google_redirect_uri']);
$client->setScopes(['https://www.googleapis.com/auth/gmail.send']);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force consent screen
applyGoogleClientSslConfig($client);

// Generate authorization URL
$authUrl = $client->createAuthUrl();

// Redirect to Google authorization
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
?>
