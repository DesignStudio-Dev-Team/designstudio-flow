<?php
/**
 * Plugin Name: DesignStudio Flow
 * Plugin URI: https://designstudio.com/flow
 * Description: Build your WordPress pages with drag-and-drop pre-coded blocks. A lightweight alternative to Elementor and Divi.
 * Version: 1.0.0
 * Author: DesignStudio
 * Author URI: https://designstudio.com
 * Text Domain: designstudio-flow
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('DSF_VERSION', '1.0.0');
define('DSF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main DesignStudio Flow Plugin Class
 */
final class DesignStudio_Flow {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-post-type.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-admin.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-editor.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-ajax.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-frontend.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-blocks.php';
        require_once DSF_PLUGIN_DIR . 'includes/class-dsf-update-checker.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Initialize components after plugins loaded
        add_action('plugins_loaded', [$this, 'init_components']);
        
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize post type
        DSF_Post_Type::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            DSF_Admin::get_instance();
            DSF_Editor::get_instance();
            DSF_Ajax::get_instance();
        }
        
        // Initialize frontend (always needed for rendering)
        DSF_Frontend::get_instance();
        
        // Initialize blocks
        DSF_Blocks::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create custom tables if needed
        $this->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for page layouts
        $table_name = $wpdb->prefix . 'dsf_layouts';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            blocks longtext NOT NULL,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'dsf_version' => DSF_VERSION,
            'dsf_default_colors' => [
                'primary' => '#3B82F6',
                'secondary' => '#1E40AF',
                'text' => '#1F2937',
                'background' => '#FFFFFF',
            ],
            'dsf_enabled_post_types' => ['page', 'dsf_page'],
        ];
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'designstudio-flow',
            false,
            dirname(DSF_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

// Initialize the plugin
function designstudio_flow() {
    return DesignStudio_Flow::get_instance();
}

// Start the plugin
designstudio_flow();
