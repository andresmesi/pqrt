<?php
/**
 * admin.php
 *
 * This file is used by the administrator to manage posts.
 * It checks for a valid admin session (by comparing the logged-in user's email)
 * and enables deletion of posts as well as searching posts by email.
 *
 * IMPORTANT:
 * - Change the hardcoded admin email in the condition below to your admin email.
 *
 * Detailed inline comments explain each step.
 */

// Enable error reporting for development.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session to access authentication details.
session_start();

// Check for a valid admin session.
if (!isset($_SESSION['user']) || $_SESSION['user']['email'] !== 'example@gmail.com') { // Update this email to your admin email.
    // Redirect to the login page if the session is invalid.
    header('Location: login.php');
    exit();
}

// Include configuration, helper functions, and template files.
require_once 'db.php';
require_once 'functions.php';
require_once 'templates/header.php';
require_once 'templates/footer.php';
require_once 'templates/form.php';
require_once 'templates/post.php';
require_once 'templates/pagination.php';

// Configure PDO for error reporting and set the character set.
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("SET NAMES 'utf8mb4'");

// Process a POST request when deleting a post.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    // Cast the incoming post ID to an integer for safety.
    $post_id = (int)$_POST['delete_post_id'];
    try {
        // Retrieve the post data (image, thread_id, and category_id) for deletion.
        $stmt = $db->prepare("SELECT image, thread_id, category_id FROM posts WHERE id = :id");
        $stmt->execute([':id' => $post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            // Build a list of all related posts (main thread and its replies).
            $related_ids = [$post_id];
            $to_check = [$post_id];
            while (!empty($to_check)) {
                $placeholders = implode(',', array_fill(0, count($to_check), '?'));
                $stmt = $db->prepare("SELECT id FROM posts WHERE thread_id IN ($placeholders)");
                $stmt->execute($to_check);
                $new_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $related_ids = array_merge($related_ids, $new_ids);
                $to_check = $new_ids;
            }

            // Retrieve images and category IDs for all posts being deleted.
            $placeholders = implode(',', array_fill(0, count($related_ids), '?'));
            $stmt = $db->prepare("SELECT image, category_id FROM posts WHERE id IN ($placeholders)");
            $stmt->execute($related_ids);
            $post_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $images = array_column($post_data, 'image');
            $category_ids = array_unique(array_column($post_data, 'category_id'));

            // Delete posts from the database.
            $stmt = $db->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
            $stmt->execute($related_ids);

            // Remove associated image files if no other post references them.
            foreach ($images as $image) {
                if ($image) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE image = :image");
                    $stmt->execute([':image' => $image]);
                    if ($stmt->fetchColumn() == 0 && file_exists($image)) {
                        unlink($image);
                        error_log("Image deleted: $image");
                    }
                }
            }

            // Regenerate cache files if the application is set to automatically generate caches.
            if (!$use_preload_cache) {
                $index_file = 'index.html';
                forceRegeneration($index_file);
                file_put_contents($index_file, generateIndexHTML($db));
                
                // Regenerate caches for affected categories.
                foreach ($category_ids as $cat_id) {
                    if ($cat_id) {
                        $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                        $stmt->execute([':id' => $cat_id]);
                        $cat_name = $stmt->fetchColumn();
                        if ($cat_name) {
                            $category_file = getCategoryCachePath($cat_name, 1);
                            forceRegeneration($category_file);
                            file_put_contents($category_file, generateCategoryHTML($db, $cat_id, $cat_name, 1));
                        }
                    }
                }

                // If the deleted post is a main thread, regenerate its thread cache.
                if ($post['thread_id'] == 0) {
                    $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $post['category_id']]);
                    $cat_name = $stmt->fetchColumn() ?? 'Uncategorized';
                    $thread_file = getThreadCachePath($cat_name, $post_id);
                    forceRegeneration($thread_file);
                }
            }

            error_log("Post $post_id and its replies deleted by admin.");
        }
    } catch (Exception $e) {
        error_log("Error deleting post: " . $e->getMessage());
        die("Error deleting the post.");
    }
}

/**
 * Generates the HTML for the admin page with post search and management.
 *
 * @param PDO $db The database connection.
 * @param string|null $search_email Email address to filter posts.
 * @param int $page The current page number.
 * @return string Minified HTML content.
 */
function generateAdminHTML($db, $search_email = null, $page = 1) {
    global $domain, $posts_per_page;

    // Generate header content.
    $html = generateHeaderHTML('admin', null, null);
    $html .= '<h2>Post Management</h2>';
    
    // Form to search posts by email.
    $html .= '<form method="GET" class="post-form">';
    $html .= '<input type="email" name="email" placeholder="Search by email" value="' . ($search_email ? htmlspecialchars($search_email) : '') . '" class="input-field" required>';
    $html .= '<button type="submit" class="btn">Search</button>';
    $html .= '</form>';

    // If an email is provided, search and display matching posts.
    if ($search_email) {
        $offset = ($page - 1) * $posts_per_page;

        $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE mail = :mail");
        $stmt->execute([':mail' => $search_email]);
        $total_posts = $stmt->fetchColumn();
        $total_pages = ceil($total_posts / $posts_per_page);

        $stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name 
                              FROM posts p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              WHERE p.mail = :mail 
                              ORDER BY p.timestamp DESC 
                              LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':mail', $search_email, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$posts_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        if ($posts) {
            $html .= '<h3>Posts by ' . htmlspecialchars($search_email) . '</h3>';
            foreach ($posts as $post) {
                $category_name = $post['category_name'] ?? 'Uncategorized';
                // Construct the post URL.
                $post_url = $domain . '/' . htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8') . '/thread/' . $post['id'];
                $html .= generatePostHTML($db, $post, $category_name, $post_url, $post['thread_id'] > 0, false);
                
                // Form for deleting the post.
                $html .= '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this post?\');">';
                $html .= '<input type="hidden" name="delete_post_id" value="' . $post['id'] . '">';
                $html .= '<button type="submit" class="btn" style="background: #ff4444; margin-top: 10px;">Delete</button>';
                $html .= '</form>';
            }
            // Add pagination links.
            $html .= generatePaginationHTML($domain . '/admin.php?email=' . urlencode($search_email), $total_pages, $page);
        } else {
            $html .= '<p>No posts found for this email.</p>';
        }
    }

    $html .= generateFooterHTML();
    return minifyHTML($html);
}

// Get query parameters for search and pagination.
$search_email = isset($_GET['email']) ? trim($_GET['email']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
echo generateAdminHTML($db, $search_email, $page);

// Flush the output buffer.
ob_end_flush();
?>