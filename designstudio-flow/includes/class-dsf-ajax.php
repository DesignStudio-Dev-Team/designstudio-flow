<?php
/**
 * AJAX handlers for DesignStudio Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Save page
        add_action('wp_ajax_dsf_save_page', [$this, 'save_page']);
        
        // Get products
        add_action('wp_ajax_dsf_get_products', [$this, 'get_products']);
        
        // Search products
        add_action('wp_ajax_dsf_search_products', [$this, 'search_products']);
        
        // Get categories
        add_action('wp_ajax_dsf_get_categories', [$this, 'get_categories']);
        
        // Upload image
        add_action('wp_ajax_dsf_upload_image', [$this, 'upload_image']);
        
        // Update page title
        add_action('wp_ajax_dsf_update_title', [$this, 'update_title']);
        
        // Publish page
        add_action('wp_ajax_dsf_publish_page', [$this, 'publish_page']);
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!check_ajax_referer('dsf_editor_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }
    }
    
    /**
     * Save page blocks and settings
     */
    public function save_page() {
        $this->verify_nonce();
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $blocks = isset($_POST['blocks']) ? $_POST['blocks'] : '[]';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : '{}';
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        // Validate JSON
        $blocks_data = json_decode(stripslashes($blocks), true);
        $settings_data = json_decode(stripslashes($settings), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Invalid JSON data']);
        }
        
        // Save meta
        update_post_meta($post_id, '_dsf_blocks', wp_json_encode($blocks_data));
        update_post_meta($post_id, '_dsf_settings', wp_json_encode($settings_data));
        
        // Update modified time
        wp_update_post([
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ]);
        
        wp_send_json_success([
            'message' => 'Page saved successfully',
            'post_id' => $post_id,
        ]);
    }
    
    /**
     * Get products by category or IDs (Hybrid Logic: Pinned First)
     */
    public function get_products() {
        $this->verify_nonce();
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 12;
        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'category';
        
        // Handle product_ids sent as JSON string or Array
        $product_ids = [];
        if (isset($_POST['product_ids'])) {
            $raw_ids = $_POST['product_ids'];
            if (is_string($raw_ids)) {
                $decoded = json_decode(stripslashes($raw_ids), true);
                if (is_array($decoded)) {
                    $product_ids = array_map('intval', $decoded);
                }
            } elseif (is_array($raw_ids)) {
                $product_ids = array_map('intval', $raw_ids);
            }
        }
        
        $products = [];
        
        // If Manual Source OR we have Pinned products to show first
        if (!empty($product_ids)) {
            $pinned_args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'post__in' => $product_ids,
                'orderby' => 'post__in',
                'posts_per_page' => -1, // Get all pinned
            ];
            
            $pinned_posts = get_posts($pinned_args);
            
            // If Manual Source, we ONLY show pinned products (filtered by what exists)
            if ($source === 'manual') {
                $products = $pinned_posts;
            } else {
                // If Category Source, Pinned products come first, then fill with category
                $products = $pinned_posts;
            }
        }
        
        // If Category Source and we need more products (or have no pins)
        if ($source !== 'manual' && count($products) < $limit) {
            $remaining = $limit - count($products);
            
            if ($remaining > 0) {
                $cat_args = [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => $remaining,
                    'post__not_in' => $product_ids, // Exclude pinned to avoid duplicates
                ];
                
                if ($category_id) {
                    $cat_args['tax_query'] = [
                        [
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category_id,
                        ],
                    ];
                }
                
                $cat_posts = get_posts($cat_args);
                $products = array_merge($products, $cat_posts);
            }
        }
        
        // Format Result
        $result = [];
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            // Ensure Image URL
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url($image_id) : wc_placeholder_img_src();
            
            $result[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'price_html' => $product->get_price_html(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'image' => $image_url,
                'permalink' => $product->get_permalink(),
                'add_to_cart_url' => $product->add_to_cart_url(),
                'stock_status' => $product->get_stock_status(),
            ];
        }
        
        wp_send_json_success(['products' => $result]);
    }
    
    /**
     * Search products
     */
    public function search_products() {
        $this->verify_nonce();
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            's' => $search,
        ];
        
        if ($category_id) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ],
            ];
        }
        
        $products = get_posts($args);
        $result = [];
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $result[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price_html(),
                'image' => wp_get_attachment_url($product->get_image_id()),
            ];
        }
        
        wp_send_json_success(['products' => $result]);
    }
    
    /**
     * Get WooCommerce categories
     */
    public function get_categories() {
        $this->verify_nonce();
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }
        
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
        ]);
        
        if (is_wp_error($categories)) {
            wp_send_json_error(['message' => $categories->get_error_message()]);
        }
        
        $result = array_map(function($cat) {
            $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
            
            return [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'url' => get_term_link($cat),
                'count' => $cat->count,
                'image' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
            ];
        }, $categories);
        
        wp_send_json_success(['categories' => $result]);
    }
    
    /**
     * Handle image upload
     */
    public function upload_image() {
        $this->verify_nonce();
        
        if (empty($_FILES['image'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }
        
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $attachment_id = media_handle_upload('image', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        }
        
        wp_send_json_success([
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
        ]);
    }
    
    /**
     * Update page title
     */
    public function update_title() {
        $this->verify_nonce();
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        
        if (!$post_id || !$title) {
            wp_send_json_error(['message' => 'Invalid data']);
        }
        
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
        ]);
        
        wp_send_json_success(['message' => 'Title updated']);
    }
    
    /**
     * Publish page
     */
    public function publish_page() {
        $this->verify_nonce();
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish',
        ]);
        
        wp_send_json_success([
            'message' => 'Page published',
            'permalink' => get_permalink($post_id),
        ]);
    }
}
