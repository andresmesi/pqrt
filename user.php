<?php
/**
 * user.php
 *
 * This file displays a public view of a user's posts based on an encoded email.
 * It supports pagination and utilizes caching if enabled.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';
require_once 'functions.php';
require_once 'templates/header.php';
require_once 'templates/footer.php';
require_once 'templates/post.php';
require_once 'templates/pagination.php';

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::PARAM_INT);
$db->exec("SET NAMES 'utf8mb4'");

$mailcryp = isset($_GET['mailcryp']) ? trim($_GET['mailcryp']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if (!$mailcryp) {
    die("User not specified.");
}

$user_cache_file = getUserCachePath($mailcryp, $page);

if (!$use_preload_cache) {
    if (!file_exists($user_cache_file) || needsRegeneration($user_cache_file, $db)) {
        file_put_contents($user_cache_file, generateUserPostsHTML($db, $mailcryp, $page));
    }
    echo file_get_contents($user_cache_file);
} else {
    echo generateUserPostsHTML($db, $mailcryp, $page);
}

ob_end_flush();
?>