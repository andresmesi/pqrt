<?php
/**
 * templates/footer.php
 *
 * This template generates the footer section.
 * It includes links to Privacy Policy and Terms of Service, plus top-right buttons for toggling mode,
 * login, profile, and logout.
 *
 * @return string HTML for the footer.
 */
function generateFooterHTML() {
    $html = '<footer>';
    $html .= '<p>By using this site, you acknowledge and accept our <a href="privacy.html">Privacy Policy</a> and <a href="terms.html">Terms of Service</a>.</p>';
    $html .= '</footer>';
    
    // Top-right buttons.
    $html .= '<div class="top-buttons">';
    $html .= '<button class="mode-toggle btn" onclick="toggleMode()"><i class="fas fa-sun"></i><i class="fas fa-moon" style="display:none;"></i></button>';
    $html .= '<a href="/login.php" class="btn"><i class="fas fa-plug"></i></a>';
    $html .= '<a href="/profile.php" class="btn"><i class="fas fa-user"></i></a>';
    $html .= '<a href="/logout.php" class="btn"><i class="fas fa-power-off"></i></a>';
    $html .= '</div>';
    $html .= '<p class="footer-description">pqrt is a platform where users create threads by uploading images and adding comments with tags, fostering interactive, nested discussions.</p>';
    $html .= '</body></html>';
    return $html;
}
?>