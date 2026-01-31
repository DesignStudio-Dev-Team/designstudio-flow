<?php
/**
 * Editor functionality for DesignStudio Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Editor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', [$this, 'handle_editor_redirect']);
        add_action('admin_menu', [$this, 'add_editor_page'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_filter('admin_body_class', [$this, 'add_editor_body_class']);
        add_filter('script_loader_tag', [$this, 'add_module_type_to_scripts'], 10, 3);
    }
    
    /**
     * Add type="module" to Vite scripts
     */
    public function add_module_type_to_scripts($tag, $handle, $src) {
        // Add type="module" for Vite dev scripts
        if (in_array($handle, ['dsf-editor-vite', 'dsf-editor'])) {
            // Check if it's already a module
            if (strpos($tag, 'type="module"') !== false) {
                return $tag;
            }
            // Replace the script tag to add type="module"
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }
    
    /**
     * Handle redirect to editor when editing a Flow Page
     */
    public function handle_editor_redirect() {
        global $pagenow;
        
        if ($pagenow === 'post.php' && isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $post_id = intval($_GET['post']);
            $post_type = get_post_type($post_id);
            
            if ($post_type === 'dsf_page') {
                wp_redirect(admin_url('admin.php?page=dsf-editor&post_id=' . $post_id));
                exit;
            }
        }
    }
    
    /**
     * Add hidden editor page
     */
    public function add_editor_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Edit with Flow', 'designstudio-flow'),
            __('Edit with Flow', 'designstudio-flow'),
            'edit_pages',
            'dsf-editor',
            [$this, 'render_editor_page']
        );
    }
    
    /**
     * Enqueue editor scripts and styles
     */
    public function enqueue_editor_scripts($hook) {
        if ($hook !== 'admin_page_dsf-editor') {
            return;
        }
        
        // Remove all other styles and scripts for clean editor
        global $wp_styles, $wp_scripts;
        
        // Keep only essential WordPress scripts
        $allowed_scripts = ['jquery', 'wp-api', 'wp-util', 'media-upload', 'thickbox'];
        $allowed_styles = ['thickbox', 'media-views', 'imgareaselect'];
        
        // Enqueue media library
        wp_enqueue_media();
        
        // Production or development mode
        $is_dev = defined('DSF_DEV_MODE') && DSF_DEV_MODE;
        
        if ($is_dev) {
            // Development - load from Vite dev server
            wp_enqueue_script(
                'dsf-editor-vite',
                'http://localhost:5173/@vite/client',
                [],
                null,
                true
            );
            wp_enqueue_script(
                'dsf-editor',
                'http://localhost:5173/src/main.js',
                ['dsf-editor-vite'],
                null,
                true
            );
        } else {
            // Production - load built assets
            wp_enqueue_style(
                'dsf-editor',
                DSF_PLUGIN_URL . 'assets/css/editor.css',
                [],
                DSF_VERSION
            );
            
            wp_enqueue_script(
                'dsf-editor',
                DSF_PLUGIN_URL . 'assets/js/editor.js',
                [],
                DSF_VERSION,
                true
            );
        }
        
        // Pass data to JavaScript
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $post = $post_id ? get_post($post_id) : null;
        
        wp_localize_script('dsf-editor', 'dsfEditorData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('dsf/v1/'),
            'nonce' => wp_create_nonce('dsf_editor_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'postId' => $post_id,
            'postTitle' => $post ? $post->post_title : '',
            'pageData' => $this->get_page_data($post_id),
            'blocks' => DSF_Blocks::get_instance()->get_registered_blocks(),
            'categories' => $this->get_wc_categories(),
            'pluginUrl' => DSF_PLUGIN_URL,
            'homeUrl' => home_url(),
            'adminUrl' => admin_url(),
            'previewUrl' => $post_id ? get_permalink($post_id) : '',
            'isWooActive' => class_exists('WooCommerce'),
        ]);
    }
    
    /**
     * Add body class for editor page
     */
    public function add_editor_body_class($classes) {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'admin_page_dsf-editor') {
            $classes .= ' dsf-editor-page';
        }
        
        return $classes;
    }
    
    /**
     * Render editor page
     */
    public function render_editor_page() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        // Create new page if no ID
        if (!$post_id) {
            $post_id = wp_insert_post([
                'post_type' => 'dsf_page',
                'post_status' => 'draft',
                'post_title' => __('Untitled Page', 'designstudio-flow'),
            ]);
            
            wp_redirect(admin_url('admin.php?page=dsf-editor&post_id=' . $post_id));
            exit;
        }
        
        include DSF_PLUGIN_DIR . 'templates/editor-page.php';
    }
    
    /**
     * Get page data (blocks and settings)
     */
    private function get_page_data($post_id) {
        if (!$post_id) {
            return [
                'blocks' => [],
                'settings' => $this->get_default_settings(),
            ];
        }
        
        $blocks = get_post_meta($post_id, '_dsf_blocks', true);
        $settings = get_post_meta($post_id, '_dsf_settings', true);
        
        return [
            'blocks' => $blocks ? json_decode($blocks, true) : [],
            'settings' => $settings ? json_decode($settings, true) : $this->get_default_settings(),
        ];
    }
    
    /**
     * Get default page settings
     */
    private function get_default_settings() {
        return [
            'theme' => [
                'primaryColor' => '#3B82F6',
                'secondaryColor' => '#1E40AF',
                'textColor' => '#1F2937',
                'backgroundColor' => '#FFFFFF',
            ],
            'layout' => [
                'containerWidth' => 1200,
                'contentPadding' => 24,
            ],
        ];
    }
    
    /**
     * Get WooCommerce categories
     */
    private function get_wc_categories() {
        if (!class_exists('WooCommerce')) {
            return [];
        }
        
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        
        if (is_wp_error($categories)) {
            return [];
        }
        
        return array_map(function($cat) {
            $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
            
            return [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'count' => $cat->count,
                'image' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
            ];
        }, $categories);
    }
}
