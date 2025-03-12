<?php
/**
 * templates/category.php
 *
 * This template generates the HTML for a category page.
 * It displays all main threads within a category along with their replies and pagination.
 *
 * @param PDO $db The database connection.
 * @param int $category_id The category ID.
 * @param string $category_name The category name.
 * @param int $page The current page number.
 * @return string Minified HTML for the category page.
 */

require_once './db.php';
require_once './functions.php';
require_once './templates/header.php';
require_once './templates/footer.php';
require_once './templates/form.php';
require_once './templates/post.php';
require_once './templates/pagination.php';

function generateCategoryHTML($db, $category_id, $category_name, $page = 1) {
    global $domain, $posts_per_page;

    $html = generateHeaderHTML('category', $category_id, $category_name);
    $category_name_clean = htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8');
    $html .= '<h2><a href="/' . $category_name_clean . '">' . htmlspecialchars($category_name) . '</a></h2>';

    $offset = ($page - 1) * $posts_per_page;

    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = 0 AND category_id = :category_id");
    $stmt->execute([':category_id' => $category_id]);
    $total_threads = $stmt->fetchColumn();
    $total_pages = ceil($total_threads / $posts_per_page);

    $stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name 
                          FROM posts p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.thread_id = 0 AND p.category_id = :category_id 
                          ORDER BY p.last_bump DESC 
                          LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':category_id', (int)$category_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$posts_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $threads = $stmt->fetchAll();

    foreach ($threads as $thread) {
        $thread_hash = $thread['image'] ? pathinfo($thread['image'], PATHINFO_FILENAME) : $thread['id'];
        $thread_url = $domain . '/' . $category_name_clean . '/thread/' . $thread_hash;
        $html .= generatePostHTML($db, $thread, $category_name, $thread_url, false, true);

        $replies = getThreadReplies($db, $thread['id']);
        foreach ($replies as $reply) {
            $reply_url = $domain . '/' . $category_name_clean . '/thread/' . $thread_hash;
            $html .= generatePostHTML($db, $reply, $category_name, $reply_url, true, false);
        }
    }

    $html .= generatePaginationHTML($domain . '/' . $category_name_clean, $total_pages, $page);
    $html .= generateFooterHTML();
    return minifyHTML($html);
}
?>