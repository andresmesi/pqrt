<?php
/**
 * templates/post.php
 *
 * This template generates HTML for an individual post (thread or reply).
 * It includes the post image, comment, user information, and social share links.
 *
 * @param PDO $db The database connection.
 * @param array $post Associative array with post data.
 * @param string $category_name The name of the category.
 * @param string $url The URL to the post.
 * @param bool $is_reply True if the post is a reply.
 * @param bool $link_image True to make the image a clickable link.
 * @return string The HTML content for the post.
 */

require_once './functions.php';

function generatePostHTML($db, $post, $category_name, $url, $is_reply = false, $link_image = true) {
    global $category_icons, $domain;
 
    $html = '<div class="' . ($is_reply ? 'reply' : 'thread') . '">';
    $html .= '<strong>' . (isset($category_icons[strtolower($category_name)]) ? '<i class="fa-solid ' . $category_icons[strtolower($category_name)] . '"></i> ' : '<i class="fa-solid fa-' . htmlspecialchars(strtolower($category_name), ENT_QUOTES, 'UTF-8') . '"></i> ') . htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') . '</strong><br>';
    if ($post['image']) {
        $image_path = getFullImagePath($post['image']);
        if ($link_image) {
            $html .= '<a href="' . $url . '"><img src="/' . htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') . '" ></a><br>';
        } else {
            $html .= '<img src="/' . htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') . '" ><br>';
        }
    }
    $html .= '<p>' . nl2br($post['comment']) . '</p>';
    $user_url = $domain . '/user/' . urlencode(encodeEmail($post['mail']));
    $html .= '<small><i class="fa-solid ' . getRandomAvatarIcon() . '"></i> ' 
           . '<a href="' . $user_url . '">' . (empty($post['name']) ? 'Anonymous' : htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8')) . '</a>'
           . ' | ID: ' . $post['id'] 
           . ' | ' . htmlspecialchars($post['timestamp'], ENT_QUOTES, 'UTF-8') 
           . '</small>';
 
    $html .= generateShareHTML($url);
    $html .= '</div>';

    return $html;
}
?>