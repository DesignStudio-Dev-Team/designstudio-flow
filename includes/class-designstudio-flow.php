<?php
/**
 * Main DesignStudio Flow Plugin Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesignStudio_Flow {

	/**
	 * Single instance of the class
	 *
	 * @var DesignStudio_Flow|null
	 */
	private static $instance = null;

	/**
	 * Get single instance
	 *
	 * @return DesignStudio_Flow
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
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
		// Core classes.
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-post-type.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-admin.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-editor.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-ajax.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-frontend.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-blocks.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-forms.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-update-checker.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Activation/Deactivation.
		register_activation_hook( DSF_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( DSF_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize components after plugins loaded.
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Initialize post type.
		DSF_Post_Type::get_instance();

		// Initialize admin.
		if ( is_admin() ) {
			DSF_Admin::get_instance();
			DSF_Editor::get_instance();
			DSF_Ajax::get_instance();
		}

		// Initialize frontend (always needed for rendering).
		DSF_Frontend::get_instance();
		DSF_Forms::get_instance();

		// Initialize blocks.
		DSF_Blocks::get_instance();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create custom tables if needed.
		$this->create_tables();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set default options.
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

		// Table for page layouts.
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
		dbDelta( $sql );
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		$defaults = array(
			'dsf_version'            => DSF_VERSION,
			'dsf_default_colors'     => array(
				'primary'    => '#3B82F6',
				'secondary'  => '#1E40AF',
				'text'       => '#1F2937',
				'background' => '#FFFFFF',
			),
			'dsf_enabled_post_types' => array( 'page', 'dsf_page' ),
			'dsf_recaptcha_enabled'  => false,
			'dsf_recaptcha_site_key' => '',
			'dsf_recaptcha_secret_key' => '',
			'dsf_recaptcha_threshold' => 0.5,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
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
			dirname( DSF_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
