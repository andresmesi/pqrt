<?php
/**
 * post.php
 *
 * This file handles both displaying posts and processing new post submissions.
 * It supports:
 *  - Viewing a specific thread based on thread hash and category.
 *  - Paginated display of posts (index and category pages).
 *  - Processing form submissions for new threads and replies.
 *  - Image uploads with validation.
 *  - Cache regeneration for updated content.
 */

// Enable error reporting.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: text/html; charset=UTF-8');
ob_start();

require_once 'db.php';
require_once 'functions.php';
require_once 'templates/index.php';
require_once 'templates/category.php';
require_once 'templates/thread.php';

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("SET NAMES 'utf8mb4'");
$index_file = 'index.html';

// If a thread is specified via thread_hash and category.
if (isset($_GET['thread_hash']) && isset($_GET['category'])) {
    $thread_hash = $_GET['thread_hash'];
    $category_name = htmlspecialchars(trim($_GET['category']), ENT_QUOTES, 'UTF-8');

    $stmt = $db->prepare("SELECT id, category_id FROM posts WHERE image LIKE :hash AND thread_id = 0");
    $stmt->execute([':hash' => $thread_hash . '%']);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($thread) {
        $thread_id = $thread['id'];
        $category_id = $thread['category_id'];

        $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
        $stmt->execute([':id' => $category_id]);
        $db_category_name = $stmt->fetchColumn();

        if ($db_category_name && strtolower($db_category_name) === strtolower($category_name)) {
            echo generateThreadHTML($db, $thread_id, $category_name, $category_id);
            exit();
        } else {
            die("Category mismatch for thread: $thread_hash");
        }
    } else {
        die("Thread not found for hash: $thread_hash");
    }
} elseif (isset($_GET['page'])) {
    // Serve a paginated index page.
    try {
        $page = max(1, (int)$_GET['page']);
        $index_file = $page == 1 ? 'index.html' : "cache/categories/page-$page.html";
        error_log("Attempting to serve page $page: $index_file");

        if (!file_exists($index_file) || needsRegeneration($index_file, $db)) {
            error_log("Generating cache for page $page: $index_file");
            $html = generateIndexHTML($db, $page);
            if ($html === false || $html === null) {
                throw new Exception("generateIndexHTML returned an invalid value for page $page");
            }
            $dir = dirname($index_file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $result = file_put_contents($index_file, $html);
            if ($result === false) {
                throw new Exception("Could not write cache file: $index_file");
            }
            error_log("Cache generated successfully for $index_file");
        } else {
            error_log("Cache exists and is up-to-date: $index_file");
        }

        if (file_exists($index_file)) {
            echo file_get_contents($index_file);
        } else {
            throw new Exception("Cache file not found: $index_file");
        }
    } catch (Exception $e) {
        error_log("Error generating page $page: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        die("Internal error generating page $page: " . $e->getMessage());
    }
    exit();
} elseif (isset($_GET['category']) && !isset($_GET['thread_hash'])) {
    // Serve posts for a specific category.
    $category_name = htmlspecialchars(trim($_GET['category']), ENT_QUOTES, 'UTF-8');
    if ($category_name === 'index.html') {
        echo file_get_contents('index.html');
        exit();
    }
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $stmt = $db->prepare("SELECT id FROM categories WHERE name = :name");
        $stmt->execute([':name' => $category_name]);
        $category_id = $stmt->fetchColumn();

        if ($category_id === false) {
            header("HTTP/1.1 404 Not Found");
            die("Category not found: $category_name");
        }

        $category_file = getCategoryCachePath($category_name, $page);
        error_log("Attempting to serve category $category_name, page $page: $category_file");

        if (!file_exists($category_file) || needsRegeneration($category_file, $db)) {
            error_log("Generating cache for category $category_name, page $page: $category_file");
            $html = generateCategoryHTML($db, $category_id, $category_name, $page);
            if ($html === false || $html === null) {
                throw new Exception("generateCategoryHTML returned an invalid value for category $category_name, page $page");
            }
            $dir = dirname($category_file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $result = file_put_contents($category_file, $html);
            if ($result === false) {
                throw new Exception("Could not write cache file: $category_file");
            }
            error_log("Cache generated successfully for $category_file");
        } else {
            error_log("Cache exists and is up-to-date: $category_file");
        }

        if (file_exists($category_file)) {
            echo file_get_contents($category_file);
        } else {
            throw new Exception("Cache file not found: $category_file");
        }
    } catch (Exception $e) {
        error_log("Error generating category $category_name, page $page: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        die("Internal error generating category $category_name: " . $e->getMessage());
    }
    exit();
}

// Process POST requests for new posts (threads or replies).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit();
    }

    try {
        // Retrieve user details and form input.
        $name = $_SESSION['user']['name'] ?? 'Anonymous';
        $email = $_SESSION['user']['email'] ?? 'nomail';
        $comment = trim($_POST['comment'] ?? '');
        $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
        $category_name = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (empty($comment)) {
            throw new Exception("The comment cannot be empty.");
        }
        if (empty($category_name) && $thread_id == 0) {
            throw new Exception("The category is required to start a new thread.");
        }

        $comment_with_previews = processCommentForPreviews($comment);
        $image = '';
        $category_id = null;

        if ($thread_id == 0 && !empty($category_name)) {
            // Insert a new category if creating a new thread.
            $stmt = $db->prepare("INSERT IGNORE INTO categories (name) VALUES (:name)");
            $stmt->execute([':name' => $category_name]);
            $stmt = $db->prepare("SELECT id FROM categories WHERE name = :name");
            $stmt->execute([':name' => $category_name]);
            $category_id = $stmt->fetchColumn();
        } elseif ($thread_id > 0) {
            // For replies, get the category from the parent thread.
            $stmt = $db->prepare("SELECT category_id FROM posts WHERE id = :id");
            $stmt->execute([':id' => $thread_id]);
            $category_id = $stmt->fetchColumn();
            if ($category_id === false) {
                throw new Exception("Parent thread not found (ID: $thread_id).");
            }
            $stmt = $db->prepare("SELECT name FROM categories WHERE id = :id");
            $stmt->execute([':id' => $category_id]);
            $category_name = $stmt->fetchColumn();
        }

        // Process image upload if an image file is provided.
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            if (validateImage($_FILES['image'])) {
                $image = getUploadPath($_FILES['image']);
                error_log("Upload path: $image");
            } else {
                throw new Exception("The file is not a valid image (only JPEG/PNG/GIF/WEBP/BMP, max 2MB).");
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
            throw new Exception("Image upload failed with error code: " . $_FILES['image']['error']);
        }

        // Insert the post into the database.
        $stmt = $db->prepare("INSERT INTO posts (thread_id, category_id, image, comment, name, mail) VALUES (:thread_id, :category_id, :image, :comment, :name, :mail)");
        $stmt->execute([
            ':thread_id' => $thread_id,
            ':category_id' => $category_id,
            ':image' => $image,
            ':comment' => $comment_with_previews,
            ':name' => $name,
            ':mail' => $email
        ]);
        $post_id = $db->lastInsertId();

        // Remove the main index cache file to force regeneration.
        $index_file = 'index.html';
        if (file_exists($index_file)) {
            unlink($index_file);
            error_log("index.html deleted after new post publication");
        }

        // Update the parent thread's bump time if replying.
        if ($thread_id > 0) {
            $stmt = $db->prepare("UPDATE posts SET last_bump = NOW() WHERE id = :id OR thread_id = :thread_id");
            $stmt->execute([':id' => $thread_id, ':thread_id' => $thread_id]);
        }

        // Determine the thread hash for redirection.
        $thread_hash = $thread_id == 0 && $image ? pathinfo($image, PATHINFO_FILENAME) : getThreadHash($db, $thread_id);
        $redirect_url = $domain . '/' . $category_name . '/thread/' . $thread_hash;
        header("Location: $redirect_url");
        exit();
    } catch (Exception $e) {
        error_log("Error in post.php: " . $e->getMessage());
        die("Error: " . $e->getMessage());
    }
} else {
    // For GET requests, serve the index page.
    if (!file_exists($index_file) || needsRegeneration($index_file, $db)) {
        file_put_contents($index_file, generateIndexHTML($db, 1));
        error_log("Cache generated successfully for $index_file by default");
    }
    echo file_get_contents($index_file);
    exit();
}

ob_end_flush();
?>