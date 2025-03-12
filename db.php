<?php
/**
 * db.php
 *
 * This file contains the configuration settings for the application.
 * It includes the database connection details and Google OAuth credentials.
 * All settings (including sensitive ones) are stored here.
 *
 * Note:
 * - We use db.php (not config.php) for configuration.
 */

// Optional: Set your custom domain here (e.g., "https://yourdomain.com").
$custom_domain = 'https://example.com';

// Determine the domain (use custom_domain if set; otherwise, use the HTTP host).
$domain = isset($custom_domain) ? $custom_domain : 'https://' . $_SERVER['HTTP_HOST'];

// Database configuration.
$db_host = 'localhost';
$db_name = '';
$db_user = '';
$db_pass = ''; // Remove sensitive information before sharing publicly.

// Google OAuth configuration.
$google_client_id = '';
$google_client_secret = '';
$google_redirect_uri = $domain . '/login.php';

// Application settings.
$posts_per_page = 20; // Number of posts per page.
$use_preload_cache = true; // Set to true for manual cache regeneration.

// Establish a PDO connection to the MySQL database.
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage() . " (Line: " . $e->getLine() . ")");
}
?>