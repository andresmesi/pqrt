<?php
/**
 * templates/pagination.php
 *
 * This template generates pagination links.
 *
 * @param string $base_url The base URL for pagination links.
 * @param int $total_pages The total number of pages.
 * @param int $current_page The current page number.
 * @return string The HTML content for pagination.
 */
function generatePaginationHTML($base_url, $total_pages, $current_page) {
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        // For the first page, do not append a page number.
        $page_url = $base_url . ($i == 1 ? '' : (strpos($base_url, '?') === false ? "/page-$i" : "&page=$i"));
        $html .= '<a href="' . $page_url . '" class="' . ($i == $current_page ? 'active' : '') . '">' . $i . '</a> ';
    }
    $html .= '</div>';
    return $html;
}
?>