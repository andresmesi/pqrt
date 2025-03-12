<?php
/**
 * functions.php
 *
 * This file contains helper functions for the application.
 * Functions include image upload handling, thread hash retrieval, image validation,
 * random avatar icon selection, HTML minification, cache regeneration, comment processing,
 * email encoding/decoding, share link generation, and more.
 */

/**
 * Generates the upload path for an image file and moves it to the correct directory.
 *
 * @param array $file The uploaded file (from $_FILES).
 * @return string The generated filename with extension.
 */
function getUploadPath($file) {
    // Generate a unique hash for the file.
    $hash = md5_file($file['tmp_name']);
    // Determine the file extension.
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $base_dir = 'uploads/';
    
    // Create a multi-level directory structure based on the hash.
    $subdir1 = substr($hash, 0, 1);
    $subdir2 = substr($hash, 1, 1);
    $subdir3 = substr($hash, 2, 1);
    $subdir4 = substr($hash, 3, 1);
    $subdir5 = substr($hash, 4, 1);
    $subdir6 = substr($hash, 5, 1);
    
    $path = $base_dir . $subdir1 . '/' . $subdir2 . '/' . $subdir3 . '/' . 
            $subdir4 . '/' . $subdir5 . '/' . $subdir6 . '/';
    $filename = $hash . '.' . $ext;
    $full_path = $path . $filename;

    // Create the directory if it doesn't exist.
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            error_log("Could not create upload directory: $path");
            die("Could not create upload directory: $path");
        }
    }

    // Move the uploaded file to the directory.
    if (!file_exists($full_path)) {
        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            error_log("Could not move file to: $full_path");
            die("Could not move file to: $full_path");
        }
    }

    error_log("Upload path: $full_path");
    return $filename;
}

/**
 * Constructs the full path for an image file.
 *
 * @param string $filename The image filename.
 * @return string The full image path.
 */
function getFullImagePath($filename) {
    $hash = pathinfo($filename, PATHINFO_FILENAME);
    $base_dir = 'uploads/';
    $subdir1 = substr($hash, 0, 1);
    $subdir2 = substr($hash, 1, 1);
    $subdir3 = substr($hash, 2, 1);
    $subdir4 = substr($hash, 3, 1);
    $subdir5 = substr($hash, 4, 1);
    $subdir6 = substr($hash, 5, 1);
    return $base_dir . $subdir1 . '/' . $subdir2 . '/' . $subdir3 . '/' . 
           $subdir4 . '/' . $subdir5 . '/' . $subdir6 . '/' . $filename;
}

/**
 * Retrieves the thread hash based on the parent thread's image.
 *
 * @param PDO $db The database connection.
 * @param int $thread_id The thread ID.
 * @return string The thread hash or thread ID if no image.
 */
function getThreadHash($db, $thread_id) {
    $stmt = $db->prepare("SELECT image FROM posts WHERE id = :id");
    $stmt->execute([':id' => $thread_id]);
    $image = $stmt->fetchColumn();
    return $image ? pathinfo($image, PATHINFO_FILENAME) : $thread_id;
}

/**
 * Validates an uploaded image file.
 *
 * @param array $file The uploaded file.
 * @return bool True if valid; otherwise, false.
 */
function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['size'] > $max_size || !in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type or size: " . $file['type'] . ", Size: " . $file['size']);
        return false;
    }

    $image_info = @getimagesize($file['tmp_name']);
    return $image_info !== false;
}

/**
 * Returns a random FontAwesome icon class for user avatars.
 *
 * @return string A FontAwesome icon class.
 */
function getRandomAvatarIcon() {
    $icons = ['fa-user', 'fa-cat', 'fa-dog', 'fa-star', 'fa-heart', 'fa-robot', 'fa-ghost'];
    return $icons[array_rand($icons)];
}

/**
 * Retrieves replies for a given thread.
 *
 * @param PDO $db The database connection.
 * @param int $thread_id The parent thread ID.
 * @param int|null $limit Optional limit for pagination.
 * @param int $offset The offset for pagination.
 * @return array The array of reply posts.
 */
function getThreadReplies($db, $thread_id, $limit = null, $offset = 0) {
    $query = "SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name
              FROM posts p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.thread_id = :thread_id
              ORDER BY p.timestamp ASC";
    if ($limit !== null) {
        $query .= " LIMIT :limit OFFSET :offset";
    }
    $stmt = $db->prepare($query);
    $stmt->bindValue(':thread_id', (int)$thread_id, PDO::PARAM_INT);
    if ($limit !== null) {
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Minifies HTML by removing extra whitespace.
 *
 * @param string $html The HTML content.
 * @return string The minified HTML.
 */
function minifyHTML($html) {
    return preg_replace(['/\s+/', '/>\s+</'], [' ', '><'], $html);
}

/**
 * Checks whether a cached file needs to be regenerated.
 *
 * @param string $file The cache file path.
 * @param PDO $db The database connection.
 * @param int|null $category_id Optional category ID.
 * @param int|null $thread_id Optional thread ID.
 * @return bool True if regeneration is needed; otherwise, false.
 */
function needsRegeneration($file, $db, $category_id = null, $thread_id = null) {
    if (!file_exists($file)) return true;
    $last_modified = filemtime($file);
    
    if ($thread_id) {
        $stmt = $db->prepare("SELECT MAX(last_bump) FROM posts WHERE id = :thread_id OR thread_id = :thread_id");
        $stmt->execute([':thread_id' => $thread_id]);
    } elseif ($category_id) {
        $stmt = $db->prepare("SELECT MAX(last_bump) FROM posts WHERE category_id = :category_id AND thread_id = 0");
        $stmt->execute([':category_id' => $category_id]);
    } else {
        $stmt = $db->prepare("SELECT MAX(last_bump) FROM posts WHERE thread_id = 0");
        $stmt->execute();
    }
    
    $latest_bump = strtotime($stmt->fetchColumn() ?: '1970-01-01');
    return $latest_bump > $last_modified;
}

/**
 * Forces cache regeneration by deleting the specified file.
 *
 * @param string $file The cache file path.
 */
function forceRegeneration($file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Processes a comment for previews (e.g., URLs and icons).
 *
 * @param string $comment The raw comment text.
 * @return string The processed comment with HTML previews.
 */
function processCommentForPreviews($comment) {
    $escaped_comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
    $pattern_smilie = '/<i class=\'fas fa-[a-z0-9-]+\'><\/i>/';
    preg_match_all($pattern_smilie, $comment, $smilie_matches);
    foreach ($smilie_matches[0] as $smilie) {
        $escaped_smilie = htmlspecialchars($smilie, ENT_QUOTES, 'UTF-8');
        $escaped_comment = str_replace($escaped_smilie, $smilie, $escaped_comment);
    }
    $pattern = '/(https?:\/\/[^\s]+|magnet:\?[^\s]+)/';
    return preg_replace_callback($pattern, function($matches) {
        $url = htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8');
        if (preg_match('/youtube\.com\/watch\?v=([^\s&]+)/i', $url, $yt_matches) || preg_match('/youtu\.be\/([^\s?]+)/i', $url, $yt_matches)) {
            return '<a href="' . $url . '" target="_blank">[YouTube Video]</a>';
        } elseif (preg_match('/reddit\.com\/r\/[^\/]+\/comments\/([^\s\/]+)/i', $url, $reddit_matches)) {
            return '<a href="' . $url . '" target="_blank">[Reddit Post]</a>';
        } elseif (preg_match('/^magnet:\?/', $url)) {
            return '<a href="' . $url . '" target="_blank"><i class="fa-solid fa-bracket-square"></i><i class="fa-solid fa-magnet"></i><i class="fa-solid fa-bracket-square-right"></i></a>';
        }
        return '<a href="' . $url . '" target="_blank">' . $url . '</a>';
    }, $escaped_comment);
}

/**
 * Retrieves the thread hash for header display.
 *
 * @param int $thread_id The thread ID.
 * @return string The thread hash.
 */
function getThreadHashForHeader($thread_id) {
    global $db;
    $stmt = $db->prepare("SELECT image FROM posts WHERE id = :id");
    $stmt->execute([':id' => $thread_id]);
    $image = $stmt->fetchColumn();
    return $image ? pathinfo($image, PATHINFO_FILENAME) : $thread_id;
}

/**
 * Encodes an email address for safe URL usage.
 *
 * @param string $email The email address.
 * @return string The encoded email.
 */
function encodeEmail($email) {
    return base64_encode(strrev($email));
}

/**
 * Decodes an encoded email address.
 *
 * @param string $mailcryp The encoded email.
 * @return string The decoded email.
 */
function decodeEmail($mailcryp) {
    return strrev(base64_decode($mailcryp));
}

/**
 * Generates HTML for social sharing of a URL.
 *
 * @param string $url The URL to share.
 * @return string The HTML snippet for sharing.
 */
function generateShareHTML($url) {
    $html = '<div class="share">';
    $html .= '<input type="text" value="' . $url . '" readonly onclick="this.select();" class="input-field">';
    $html .= '<a href="https://twitter.com/intent/tweet?url=' . urlencode($url) . '" target="_blank"><i class="fab fa-x-twitter"></i></a> ';
    $html .= '<a href="https://www.reddit.com/submit?url=' . urlencode($url) . '" target="_blank"><i class="fab fa-reddit-alien"></i></a> ';
    $html .= '<button onclick="navigator.clipboard.writeText(\'' . $url . '\')" class="btn"><i class="fas fa-copy"></i></button>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generates the HTML for displaying a user's posts.
 *
 * @param PDO $db The database connection.
 * @param string $mailcryp The encoded user email.
 * @param int $page The current page number.
 * @return string The minified HTML content.
 */
function generateUserPostsHTML($db, $mailcryp, $page = 1) {
    global $domain, $posts_per_page;

    $email = decodeEmail($mailcryp);
    $html = generateHeaderHTML('user');
    $html .= '<h2>Posts by User</h2>';

    $offset = ($page - 1) * $posts_per_page;

    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE mail = :mail");
    $stmt->execute([':mail' => $email]);
    $total_posts = $stmt->fetchColumn();
    $total_pages = ceil($total_posts / $posts_per_page);

    $stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name 
                          FROM posts p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.mail = :mail 
                          ORDER BY p.timestamp DESC 
                          LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':mail', $email, PDO::PARAM_STR);
    $stmt->bindValue(':limit', (int)$posts_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();

    if (empty($posts)) {
        $html .= '<p>No posts found for this user.</p>';
    } else {
        foreach ($posts as $post) {
            $category_name = $post['category_name'] ?? 'Uncategorized';
            $thread_hash = $post['image'] ? pathinfo($post['image'], PATHINFO_FILENAME) : $post['id'];
            $post_url = $domain . '/' . htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8') . '/thread/' . $thread_hash;
            $html .= generatePostHTML($db, $post, $category_name, $post_url, $post['thread_id'] > 0, true);
        }
    }

    $html .= generatePaginationHTML($domain . '/user/' . urlencode($mailcryp), $total_pages, $page);
    $html .= generateFooterHTML();
    return minifyHTML($html);
}

// Directory for category cache files.
$cache_dir = 'cache/categories/';

/**
 * Returns the cache file path for a category page.
 *
 * @param string $category_name The name of the category.
 * @param int $page The page number.
 * @return string The path to the cache file.
 */
function getCategoryCachePath($category_name, $page = 1) {
    global $cache_dir;
    $clean_name = strtolower(str_replace('/', '', $category_name));
    $path = $cache_dir . $clean_name . '/';
    if (!is_dir($path) && !mkdir($path, 0777, true)) {
        die("Could not create category cache directory: $path");
    }
    return $page == 1 ? $path . 'index.html' : $path . "page-$page.html";
}

/**
 * Returns the cache file path for a thread page.
 *
 * @param string $category_name The name of the category.
 * @param mixed $thread_hash The thread hash or ID.
 * @return string The path to the thread cache file.
 */
function getThreadCachePath($category_name, $thread_hash) {
    global $cache_dir;
    $clean_name = strtolower(str_replace('/', '', $category_name));
    $path = $cache_dir . $clean_name . '/thread/';
    if (!is_dir($path) && !mkdir($path, 0777, true)) {
        die("Could not create thread cache directory: $path");
    }
    return $path . $thread_hash . '.html';
}

/**
 * Returns the cache file path for a user's posts.
 *
 * @param string $mailcryp The encoded email.
 * @param int $page The page number.
 * @return string The path to the user cache file.
 */
function getUserCachePath($mailcryp, $page = 1) {
    $user_cache_dir = 'cache/user/'; // Directory for user caches.
    $path = $user_cache_dir . $mailcryp . '/';
    if (!is_dir($path) && !mkdir($path, 0777, true)) {
        die("Could not create user cache directory: $path");
    }
    return $page == 1 ? $path . 'index.html' : $path . "page-$page.html";
}

// Define category icons mapping.
$category_icons = [
    'images' => 'fa-images',
    'tech' => 'fa-laptop',
    'b' => 'fa-random',
    'house' => 'fa-house',
    'music' => 'fa-music',
    'video' => 'fa-video',
    'games' => 'fa-gamepad',
    'news' => 'fa-newspaper',
    'food' => 'fa-utensils',
    'travel' => 'fa-plane',
    'sports' => 'fa-football-ball',
    'art' => 'fa-paint-brush',
    'science' => 'fa-flask',
    'code' => 'fa-code',
    'chat' => 'fa-comments',
];
?>