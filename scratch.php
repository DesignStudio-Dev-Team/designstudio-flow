<?php
define('WP_USE_THEMES', false);
require_once('../../../../wp-load.php');

$args = array(
  'post_type' => 'dsf_page',
  'posts_per_page' => 1
);
$flow_pages = get_posts($args);

if (!empty($flow_pages)) {
    $post = $flow_pages[0];
    echo "Post ID: " . $post->ID . "\n";
    echo "Content Length: " . strlen($post->post_content) . "\n";
    
    // Simulate flow-page.php calling the blocks
    $frontend = DSF_Frontend::get_instance();
    $rendered = $frontend->render_flow_blocks($post->ID);
    echo "Rendered Length: " . strlen($rendered) . "\n";
    
    // Now trigger the_content to see what it outputs
    $filtered = apply_filters('the_content', $post->post_content);
    echo "Filtered the_content Length: " . strlen($filtered) . "\n";
    
    // Check if post_content has anything inside it!
    echo "\nPost Content Snippet:\n";
    echo substr($post->post_content, 0, 500);
} else {
    echo "No flow pages found.";
}
