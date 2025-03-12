<?php
/**
 * templates/header.php
 *
 * This template generates the HTML header section.
 * It includes the DOCTYPE, meta tags, title (customized per page type),
 * links to stylesheets (including FontAwesome), and scripts.
 *
 * @param string $type The type of page ('index', 'category', 'thread', etc.).
 * @param int|null $category_id Optional category ID.
 * @param string|null $category_name Optional category name.
 * @param int|null $thread_id Optional thread ID.
 * @return string The HTML header.
 */
function generateHeaderHTML($type = 'index', $category_id = null, $category_name = null, $thread_id = null) {
    global $domain, $category_icons;
    
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<title>Pqrt' . ($type == 'category' ? ' - ' . htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') : '') . ($type == 'thread' ? ' - ' . htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') . ' Thread' : '') . '</title>';
    $html .= '<link rel="stylesheet" href="/fontawesome/css/all.min.css">';
    $html .= '<link rel="stylesheet" href="/styles.css">';
    $html .= '<script src="/js/toggle-mode.js"></script>';
    $html .= '</head><body>';

    // Generate logo and breadcrumbs.
    $html .= '<h1>';
    $html .= '<a href="/" class="logo"><i class="fa-solid fa-p"></i><i class="fa-solid fa-q"></i><i class="fa-solid fa-r"></i><i class="fa-solid fa-t"></i></a>';

    if ($type == 'category' && $category_name) {
        $category_clean = htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8');
        $html .= ' / ';
        $html .= '<a href="/' . $category_clean . '/">' 
              . (isset($category_icons[strtolower($category_name)]) 
                 ? '<i class="fa-solid ' . $category_icons[strtolower($category_name)] . '"></i> ' 
                 : '<i class="fa-solid fa-' . $category_clean . '"></i> ')
              . htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') 
              . '</a>';
    }

    if ($type == 'thread' && $category_name && $thread_id) {
        $category_clean = htmlspecialchars(strtolower(str_replace('/', '', $category_name)), ENT_QUOTES, 'UTF-8');
        $thread_hash = getThreadHashForHeader($thread_id);
        $html .= ' / ';
        $html .= '<a href="/' . $category_clean . '/">' 
              . (isset($category_icons[strtolower($category_name)]) 
                 ? '<i class="fa-solid ' . $category_icons[strtolower($category_name)] . '"></i> ' 
                 : '<i class="fa-solid fa-' . $category_clean . '"></i> ')
              . htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') 
              . '</a>';
        $html .= ' / ';
        $html .= '<a href="/' . $category_clean . '/thread/' . $thread_hash . '">' . $thread_hash . '</a>';
    }

    $html .= '</h1>';

    return $html;
}
?>