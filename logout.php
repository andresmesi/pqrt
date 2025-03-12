<?php
/**
 * logout.php
 *
 * This file logs the user out by revoking the Google OAuth token (if available)
 * and destroying the local session. After logout, the user is redirected to index.html.
 */

session_start();
require_once 'vendor/autoload.php';

// If an access token exists, revoke it via Google Client.
if (isset($_SESSION['access_token'])) {
    $client = new Google_Client();
    $client->setAccessToken($_SESSION['access_token']);
    $client->revokeToken();
}

// Destroy the session.
session_destroy();

// Redirect the user to the main page.
header('Location: index.html');
exit();
?>