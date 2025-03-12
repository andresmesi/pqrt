<?php
/**
 * login.php
 *
 * This file handles Google OAuth authentication.
 * It uses Composer to load dependencies and connects to the database via db.php.
 * On successful authentication, the user's name and email are stored in the session.
 */

// Include Composer's autoloader.
require_once 'vendor/autoload.php';
// Include the configuration file.
require_once 'db.php';

session_start();

// Initialize the Google Client.
$client = new Google_Client();
$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri($google_redirect_uri);
// Request the required scopes.
$client->addScope('email');
$client->addScope('profile');
$client->addScope('openid');

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token.
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        error_log("Google OAuth Error: " . json_encode($token));
        die('Authentication error: ' . $token['error_description']);
    } else {
        $_SESSION['access_token'] = $token;
        $client->setAccessToken($token);

        // Retrieve user information.
        $oauth = new Google_Service_Oauth2($client);
        $userInfo = $oauth->userinfo->get();
        $_SESSION['user'] = [
            'name'  => $userInfo->name,
            'email' => $userInfo->email
        ];

        // Redirect to the post page after login.
        header('Location: post.php');
        exit();
    }
} else {
    // Redirect to Google for OAuth authentication.
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}
?>