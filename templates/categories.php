<?php
/**
 * templates/categories.php
 *
 * This template generates the HTML for the list of categories.
 * It displays each category with a FontAwesome icon and the thread count.
 *
 * @param PDO $db The database connection.
 * @return string HTML output for the categories section.
 */
function generateCategoriesHTML($db) {
    $html = '<div class="categories">';
    $html .= '<h2>Categories</h2>';
    $html .= '<ul>';
    $stmt = $db->query("SELECT c.id, c.name, COUNT(p.id) AS thread_count 
                        FROM categories c 
                        LEFT JOIN posts p ON p.category_id = c.id AND p.thread_id = 0 
                        GROUP BY c.id, c.name 
                        ORDER BY thread_count DESC");
    $categories = $stmt->fetchAll();
    foreach ($categories as $cat) {
        $category_name_clean = htmlspecialchars(strtolower(str_replace('/', '', $cat['name'])), ENT_QUOTES, 'UTF-8');
        $html .= '<li><a href="/' . $category_name_clean . '">' 
               . (isset($GLOBALS['category_icons'][strtolower($cat['name'])]) 
                  ? '<i class="fa-solid ' . $GLOBALS['category_icons'][strtolower($cat['name'])] . '"></i> ' 
                  : '<i class="fa-solid fa-folder"></i> ') 
               . htmlspecialchars($cat['name']) . ' (' . $cat['thread_count'] . ')</a></li>';
    }
    $html .= '</ul>';
    $html .= '</div>';
    return $html;
}
?>