<?php
/**
 * templates/thread.php
 *
 * This template generates the HTML for a thread page, including the main post,
 * its replies, a reply form, and pagination for replies.
 *
 * @param PDO $db The database connection.
 * @param int $thread_id The thread ID.
 * @param string $category_name The category name.
 * @param int|null $category_id Optional category ID.
 * @return string Minified HTML content for the thread page.
 */

require_once './db.php';
require_once './functions.php';
require_once './templates/header.php';
require_once './templates/footer.php';
require_once './templates/form.php';
require_once './templates/post.php';
require_once './templates/pagination.php';
require_once './templates/categories.php';

function generateThreadHTML($db, $thread_id, $category_name, $category_id = null) {
    global $domain, $posts_per_page;

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $posts_per_page;

    $html = generateHeaderHTML('thread', $category_id, $category_name, $thread_id);

    $stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name
                          FROM posts p
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = :thread_id");
    $stmt->execute([':thread_id' => $thread_id]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($thread) {
        $category_name = $thread['category_name'] ?? 'Uncategorized';
        $clean_category = htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8');
        $thread_hash = $thread['image'] ? pathinfo($thread['image'], PATHINFO_FILENAME) : $thread['id'];
        $thread_url = $domain . '/' . $clean_category . '/thread/' . $thread_hash;

        $html .= generatePostHTML($db, $thread, $category_name, $thread_url, false, false);

        $replies = getThreadReplies($db, $thread['id'], $posts_per_page, $offset);

        $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = :thread_id");
        $stmt->execute([':thread_id' => $thread['id']]);
        $total_replies = $stmt->fetchColumn();
        $total_pages = ceil($total_replies / $posts_per_page);

        foreach ($replies as $reply) {
            $reply_url = $domain . '/' . $clean_category . '/thread/' . $thread_hash;
            $html .= generatePostHTML($db, $reply, $category_name, $reply_url, true, false);
        }

        $html .= generateFormHTML($thread['id'], true);
        $html .= generatePaginationHTML($domain . '/' . $clean_category . '/thread/' . $thread_hash, $total_pages, $page);
    } else {
        $html .= '<p>Thread not found.</p>';
    }

    $html .= generateFooterHTML();
    return minifyHTML($html);
}
?>