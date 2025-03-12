<?php
/**
 * preload_cache.php
 *
 * This script preloads and generates static cache files for:
 *  - Index pages
 *  - Category pages
 *  - Recent threads
 *  - User profiles
 *
 * Preloading caches improves performance by serving static HTML.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/templates/index.php';
require_once __DIR__ . '/templates/category.php';
require_once __DIR__ . '/templates/thread.php';

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("SET NAMES 'utf8mb4'");

$index_file = __DIR__ . '/index.html';

// Preload index pages.
$stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = 0");
$stmt->execute();
$total_threads = $stmt->fetchColumn();
$total_pages = ceil($total_threads / $posts_per_page);

for ($page = 1; $page <= $total_pages; $page++) {
    $index_file = $page == 1 ? __DIR__ . '/index.html' : __DIR__ . "/cache/categories/page-$page.html";
    error_log("Preloading index page $page: $index_file...");
    file_put_contents($index_file, generateIndexHTML($db, $page));
    error_log("Index page $page preloaded.");
}

// Preload popular categories (top 50 by thread count).
$stmt = $db->query("SELECT c.id, c.name FROM categories c LEFT JOIN posts p ON p.category_id = c.id GROUP BY c.id, c.name ORDER BY COUNT(p.id) DESC LIMIT 50");
$categories = $stmt->fetchAll();
foreach ($categories as $cat) {
    $category_file = getCategoryCachePath($cat['name'], 1);
    error_log("Preloading category {$cat['name']}...");
    file_put_contents($category_file, generateCategoryHTML($db, $cat['id'], $cat['name'], 1));
    error_log("Category {$cat['name']} preloaded.");
}

// Preload recent threads (top 50).
$stmt = $db->query("SELECT p.id, c.name FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.thread_id = 0 ORDER BY p.timestamp DESC LIMIT 50");
$recent_threads = $stmt->fetchAll();
foreach ($recent_threads as $thread) {
    $thread_file = getThreadCachePath($thread['name'], $thread['id']);
    error_log("Preloading thread {$thread['id']}...");
    file_put_contents($thread_file, generateThreadHTML($db, $thread['id'], $thread['name']));
    error_log("Thread {$thread['id']} preloaded.");
}

// Preload active user profiles (top 50 by number of posts).
$stmt = $db->query("SELECT mail, COUNT(*) as post_count FROM posts GROUP BY mail ORDER BY post_count DESC LIMIT 50");
$active_users = $stmt->fetchAll();
foreach ($active_users as $user) {
    $mailcryp = encodeEmail($user['mail']);
    error_log("Preloading user profile for {$mailcryp}...");

    $total_posts = $user['post_count'];
    $total_pages = ceil($total_posts / $GLOBALS['posts_per_page']);

    for ($page = 1; $page <= $total_pages; $page++) {
        $user_cache_file = getUserCachePath($mailcryp, $page);
        error_log("Preloading user page $page for {$mailcryp}: $user_cache_file...");
        file_put_contents($user_cache_file, generateUserPostsHTML($db, $mailcryp, $page));
        error_log("User page $page for {$mailcryp} preloaded.");
    }
}

error_log("Cache preload completed.");
?>