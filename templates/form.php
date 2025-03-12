<?php
/**
 * templates/form.php
 *
 * This template generates the HTML form for creating new threads or replies.
 * It includes fields for image uploads, text comments, and a dropdown to add smilies.
 *
 * @param int $thread_id The thread ID (0 for new threads).
 * @param bool $is_reply True if this form is for a reply.
 * @return string The HTML form.
 */
function generateFormHTML($thread_id = 0, $is_reply = false) {
    global $domain;
    
    $html = '<form method="POST" action="/post.php" enctype="multipart/form-data">';
    $html .= '<input type="hidden" name="thread_id" value="' . $thread_id . '">';
    if ($thread_id == 0) {
        $html .= '<input type="text" name="category" placeholder="Category (e.g., /b/, /tech/)" required class="input-field"><br>';
    }
    $html .= '<input type="file" name="image" accept="image/*" class="input-field"><br>';
    $html .= '<textarea name="comment" required placeholder="' . ($is_reply ? 'What\'s your reply?' : 'What\'s on your mind?') . '" maxlength="4096" class="textarea-field" style="height: 160px;"></textarea><br>';
    // Dropdown for smilies.
    $html .= '<select name="smilie" class="input-field" onchange="this.form.comment.value += this.value; this.selectedIndex = 0;">';
    $html .= '<option value="">Add a Smilie</option>';
    $html .= '<option value=" <i class=\'fas fa-smile\'></i> ">😊</option>';
    $html .= '<option value=" <i class=\'fas fa-frown\'></i> ">😞</option>';
    $html .= '<option value=" <i class=\'fas fa-laugh\'></i> ">😂</option>';
    $html .= '<option value=" <i class=\'fas fa-angry\'></i> ">😠</option>';
    $html .= '<option value=" <i class=\'fas fa-heart\'></i> ">❤️</option>';
    $html .= '</select><br>';
    $html .= '<button type="submit" class="btn">' . ($is_reply ? 'Reply' : 'Post') . '</button>';
    $html .= '</form>';
    
    return $html;
}
?>