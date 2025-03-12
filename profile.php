<?php
/**
 * profile.php
 *
 * This file displays the profile page for the logged-in user.
 * It allows the user to view and delete their posts.
 * Cache files are regenerated when posts are deleted.
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
require_once 'templates/form.php';
require_once 'templates/post.php';
require_once 'templates/pagination.php';
require_once 'templates/index.php';
require_once 'templates/category.php';
require_once 'templates/thread.php';

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("SET NAMES 'utf8mb4'");

$user_email = $_SESSION['user']['email'];
$mailcryp = encodeEmail($user_email);

// Handle post deletion from the profile page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    $post_id = (int)$_POST['delete_post_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT image, thread_id, category_id FROM posts WHERE id = :id AND mail = :mail");
        $stmt->execute([':id' => $post_id, ':mail' => $user_email]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
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

            $placeholders = implode(',', array_fill(0, count($related_ids), '?'));
            $stmt = $db->prepare("SELECT image, category_id FROM posts WHERE id IN ($placeholders)");
            $stmt->execute($related_ids);
            $post_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $images = array_column($post_data, 'image');
            $category_ids = array_unique(array_column($post_data, 'category_id'));

            $stmt = $db->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
            $stmt->execute($related_ids);

            foreach ($images as $image) {
                if ($image && file_exists($image)) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE image = :image");
                    $stmt->execute([':image' => $image]);
                    if ($stmt->fetchColumn() == 0) {
                        unlink($image);
                        error_log("Image deleted: $image");
                    }
                }
            }

            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE category_id = :cat_id");
                    $stmt->execute([':cat_id' => $cat_id]);
                    $post_count = $stmt->fetchColumn();
                    if ($post_count == 0) {
                        $stmt = $db->prepare("DELETE FROM categories WHERE id = :cat_id");
                        $stmt->execute([':cat_id' => $cat_id]);
                        error_log("Category $cat_id deleted as it has no posts.");
                    }
                }
            }

            $db->commit();

            if (!$use_preload_cache) {
                $index_file = 'index.html';
                if (file_exists($index_file)) {
                    unlink($index_file);
                    error_log("index.html deleted after post deletion $post_id");
                }

                foreach ($category_ids as $cat_id) {
                    if ($cat_id) {
                        $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                        $stmt->execute([':id' => $cat_id]);
                        $cat_name = $stmt->fetchColumn();
                        if ($cat_name) {
                            $category_file = getCategoryCachePath($cat_name, 1);
                            forceRegeneration($category_file);
                            $cat_html = generateCategoryHTML($db, $cat_id, $cat_name, 1);
                            if ($cat_html === false || $cat_html === null || empty($cat_html)) {
                                error_log("Error: generateCategoryHTML returned an invalid value for category $cat_name");
                            } else {
                                if (file_put_contents($category_file, $cat_html) === false) {
                                    error_log("Error: Could not write $category_file for category $cat_name");
                                } else {
                                    error_log("Cache regenerated successfully for $category_file");
                                }
                            }
                        }
                    }
                }

                if ($post['thread_id'] == 0) {
                    $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $post['category_id']]);
                    $cat_name = $stmt->fetchColumn();
                    if ($cat_name) {
                        $thread_file = getThreadCachePath($cat_name, $post_id);
                        if (file_exists($thread_file)) {
                            unlink($thread_file);
                            error_log("Cache for thread $thread_file deleted");
                        }
                    }
                }

                $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE mail = :mail");
                $stmt->execute([':mail' => $user_email]);
                $total_posts = $stmt->fetchColumn();
                $total_pages = ceil($total_posts / $posts_per_page);
                for ($p = 1; $p <= $total_pages; $p++) {
                    $user_cache_file = getUserCachePath($mailcryp, $p);
                    forceRegeneration($user_cache_file);
                    $user_html = generateUserPostsHTML($db, $mailcryp, $p);
                    if ($user_html === false || $user_html === null || empty($user_html)) {
                        error_log("Error: generateUserPostsHTML returned an invalid value for user $mailcryp, page $p");
                    } else {
                        if (file_put_contents($user_cache_file, $user_html) === false) {
                            error_log("Error: Could not write $user_cache_file for user $mailcryp, page $p");
                        } else {
                            error_log("Cache regenerated successfully for $user_cache_file");
                        }
                    }
                }

                $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = 0");
                $stmt->execute();
                $total_threads = $stmt->fetchColumn();
                $total_index_pages = ceil($total_threads / $posts_per_page);
                for ($p = 2; $p <= $total_index_pages; $p++) {
                    $index_page_file = "cache/categories/page-$p.html";
                    forceRegeneration($index_page_file);
                    $index_page_html = generateIndexHTML($db, $p);
                    if ($index_page_html === false || $index_page_html === null || empty($index_page_html)) {
                        error_log("Error: generateIndexHTML returned an invalid value for index page $p");
                    } else {
                        if (file_put_contents($index_page_file, $index_page_html) === false) {
                            error_log("Error: Could not write $index_page_file for index page $p");
                        } else {
                            error_log("Cache regenerated successfully for $index_page_file");
                        }
                    }
                }
            }

            error_log("Post $post_id and its replies deleted by user $user_email.");
            header("Location: profile.php?page=$page");
            exit();
        } else {
            $db->rollBack();
            throw new Exception("Post not found or not owned by user.");
        }
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error deleting post: " . $e->getMessage());
        die("Error deleting the post: " . $e->getMessage());
    }
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $posts_per_page;

$stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE mail = :mail");
$stmt->execute([':mail' => $user_email]);
$total_posts = $stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

error_log("Profile page: $page, Offset: $offset, Total posts: $total_posts, Total pages: $total_pages");

$stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name 
                      FROM posts p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.mail = :mail 
                      ORDER BY p.timestamp DESC 
                      LIMIT :limit OFFSET :offset");
$stmt->bindParam(':mail', $user_email, PDO::PARAM_STR);
$stmt->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = generateHeaderHTML('profile');
$html .= '<div class="profile-posts">';
$html .= '<h2>Your Posts (Page ' . $page . ' of ' . $total_pages . ')</h2>';

if (empty($posts)) {
    $html .= '<p>No posts found on this page.</p>';
} else {
    foreach ($posts as $post) {
        $category_name = $post['category_name'] ?? 'Uncategorized';
        $thread_hash = $post['image'] ? pathinfo($post['image'], PATHINFO_FILENAME) : $post['id'];
        $thread_url = $domain . '/' . htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8') . '/thread/' . $thread_hash;
        $html .= '<div class="post-container">';
        $html .= generatePostHTML($db, $post, $category_name, $thread_url, $post['thread_id'] > 0, $post['thread_id'] == 0);
        $html .= '<form method="POST" action="profile.php?page=' . $page . '" style="display:inline;">';
        $html .= '<input type="hidden" name="delete_post_id" value="' . $post['id'] . '">';
        $html .= '<button type="submit" class="delete-btn">Delete</button>';
        $html .= '</form>';
        $html .= '</div>';
    }
}

$html .= generatePaginationHTML($domain . '/profile.php', $total_pages, $page);
$html .= '</div>';
$html .= generateFooterHTML();

echo minifyHTML($html);

ob_end_flush();
?>