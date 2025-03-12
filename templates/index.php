<?php
/**
 * templates/index.php
 *
 * This template generates the main index page.
 * It includes the new thread form, list of categories, and recent threads with pagination.
 */

require_once './db.php';
require_once './functions.php';
require_once './templates/header.php';
require_once './templates/footer.php';
require_once './templates/form.php';
require_once './templates/post.php';
require_once './templates/pagination.php';
require_once './templates/categories.php';

if (!isset($_SESSION)) {
    session_start();
}

/**
 * Generates the HTML for the index page.
 *
 * @param PDO $db The database connection.
 * @param int $page The current page number.
 * @return string Minified HTML content.
 */
function generateIndexHTML($db, $page = 1) {
    global $domain, $posts_per_page;
    
    $html = generateHeaderHTML('index');
    
    // Display the form to create a new thread.
    $html .= '<div class="post-form"><h2>Create a New Thread</h2>';
    $html .= generateFormHTML(0, false);
    $html .= '</div>';

    // Display the categories section.
    $html .= generateCategoriesHTML($db);

    $offset = ($page - 1) * $posts_per_page;

    // Retrieve total number of main threads.
    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = 0");
    $stmt->execute();
    $total_threads = $stmt->fetchColumn();
    $total_pages = ceil($total_threads / $posts_per_page);

    // Retrieve threads for the current page.
    $stmt = $db->prepare("SELECT p.*, COALESCE(c.name, 'Uncategorized') AS category_name 
                          FROM posts p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.thread_id = 0 
                          ORDER BY p.last_bump DESC 
                          LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$posts_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $threads = $stmt->fetchAll();

    foreach ($threads as $thread) {
        $category_name = $thread['category_name'] ?? 'Uncategorized';
        $thread_hash = $thread['image'] ? pathinfo($thread['image'], PATHINFO_FILENAME) : $thread['id'];
        $thread_url = $domain . '/' . htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8') . '/thread/' . $thread_hash;
        $html .= generatePostHTML($db, $thread, $category_name, $thread_url, false, true);
    }

    $html .= generatePaginationHTML($domain, $total_pages, $page);
    $html .= generateFooterHTML();
    return minifyHTML($html);
}
?>