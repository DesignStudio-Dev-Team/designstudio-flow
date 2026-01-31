<?php
/**
 * Custom Post Type for DesignStudio Flow Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Post_Type {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
    }
    
    /**
     * Register the DSF Page post type
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Flow Pages', 'Post type general name', 'designstudio-flow'),
            'singular_name'         => _x('Flow Page', 'Post type singular name', 'designstudio-flow'),
            'menu_name'             => _x('Flow Pages', 'Admin Menu text', 'designstudio-flow'),
            'name_admin_bar'        => _x('Flow Page', 'Add New on Toolbar', 'designstudio-flow'),
            'add_new'               => __('Add New', 'designstudio-flow'),
            'add_new_item'          => __('Add New Flow Page', 'designstudio-flow'),
            'new_item'              => __('New Flow Page', 'designstudio-flow'),
            'edit_item'             => __('Edit Flow Page', 'designstudio-flow'),
            'view_item'             => __('View Flow Page', 'designstudio-flow'),
            'all_items'             => __('All Flow Pages', 'designstudio-flow'),
            'search_items'          => __('Search Flow Pages', 'designstudio-flow'),
            'parent_item_colon'     => __('Parent Flow Pages:', 'designstudio-flow'),
            'not_found'             => __('No flow pages found.', 'designstudio-flow'),
            'not_found_in_trash'    => __('No flow pages found in Trash.', 'designstudio-flow'),
        ];
        
        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it to our custom menu
            'query_var'          => true,
            'rewrite'            => ['slug' => 'flow'],
            'capability_type'    => 'page',
            'has_archive'        => false,
            'hierarchical'       => true,
            'menu_position'      => null,
            'supports'           => ['title', 'author', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'       => true,
        ];
        
        register_post_type('dsf_page', $args);
    }
    
    /**
     * Register post meta for storing block data
     */
    public function register_meta() {
        register_post_meta('dsf_page', '_dsf_blocks', [
            'type'          => 'string',
            'description'   => 'JSON encoded blocks data',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_post_meta('dsf_page', '_dsf_settings', [
            'type'          => 'string',
            'description'   => 'JSON encoded page settings',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_post_meta('dsf_page', '_dsf_theme_colors', [
            'type'          => 'string',
            'description'   => 'JSON encoded theme colors',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        // Also register for regular pages if enabled
        register_post_meta('page', '_dsf_blocks', [
            'type'          => 'string',
            'description'   => 'JSON encoded blocks data',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_post_meta('page', '_dsf_enabled', [
            'type'          => 'boolean',
            'description'   => 'Whether DSF is enabled for this page',
            'single'        => true,
            'show_in_rest'  => true,
            'default'       => false,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
