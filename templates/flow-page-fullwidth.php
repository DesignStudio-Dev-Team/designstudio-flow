<?php
/**
 * Flow Page Full Width Template
 * Attempts to render Flow blocks edge-to-edge while still using the active theme header/footer.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_queried_object_id();
$frontend = DSF_Frontend::get_instance();
$page_settings = $frontend->get_page_settings($post_id);
$layout = $page_settings['layout'] ?? [];

$show_header = !array_key_exists('showHeader', $layout) || $layout['showHeader'];
$show_footer = !array_key_exists('showFooter', $layout) || $layout['showFooter'];

$show_header = apply_filters('dsf_flow_show_header', $show_header, $post_id);
$show_footer = apply_filters('dsf_flow_show_footer', $show_footer, $post_id);

if ($show_header) {
    get_header();
}

echo '<main class="dsf-flow-root dsf-flow-root--fullwidth">';
echo $frontend->render_flow_blocks($post_id);
echo '</main>';

if ($show_footer) {
    get_footer();
}
