<?php
/**
 * Admin functionality for DesignStudio Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('plugin_action_links_' . DSF_PLUGIN_BASENAME, [$this, 'add_plugin_links']);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('DesignStudio Flow', 'designstudio-flow'),
            __('DSF Pages', 'designstudio-flow'),
            'edit_pages',
            'designstudio-flow',
            [$this, 'render_dashboard_page'],
            'dashicons-layout',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'designstudio-flow',
            __('Dashboard', 'designstudio-flow'),
            __('Dashboard', 'designstudio-flow'),
            'edit_pages',
            'designstudio-flow',
            [$this, 'render_dashboard_page']
        );
        
        // All Flow Pages
        add_submenu_page(
            'designstudio-flow',
            __('All Pages', 'designstudio-flow'),
            __('All Pages', 'designstudio-flow'),
            'edit_pages',
            'edit.php?post_type=dsf_page'
        );
        
        // Add New
        add_submenu_page(
            'designstudio-flow',
            __('Add New', 'designstudio-flow'),
            __('Add New', 'designstudio-flow'),
            'edit_pages',
            'post-new.php?post_type=dsf_page'
        );
        
        // Settings
        add_submenu_page(
            'designstudio-flow',
            __('Settings', 'designstudio-flow'),
            __('Settings', 'designstudio-flow'),
            'manage_options',
            'dsf-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only on our pages
        if (strpos($hook, 'designstudio-flow') === false && 
            strpos($hook, 'dsf_page') === false) {
            return;
        }
        
        wp_enqueue_style(
            'dsf-admin',
            DSF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            DSF_VERSION
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $pages = get_posts([
            'post_type' => 'dsf_page',
            'posts_per_page' => 10,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
        
        include DSF_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include DSF_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_links($links) {
        $custom_links = [
            '<a href="' . admin_url('admin.php?page=designstudio-flow') . '">' . 
                __('Dashboard', 'designstudio-flow') . '</a>',
            '<a href="' . admin_url('admin.php?page=dsf-settings') . '">' . 
                __('Settings', 'designstudio-flow') . '</a>',
        ];
        
        return array_merge($custom_links, $links);
    }
}
