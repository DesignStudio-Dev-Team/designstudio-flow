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
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-crypto.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-post-type.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-admin.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-editor.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-ajax.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-frontend.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-popup.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-notification-bar.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-blocks.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-block-presets.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-forms.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-connections.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-entries.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-import-export.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-redirects.php';
		require_once DSF_PLUGIN_DIR . 'includes/class-dsf-mail-smtp.php';
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
		add_action( 'init', array( $this, 'handle_pending_rewrite_flush' ), 99 );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Initialize post type.
		DSF_Post_Type::get_instance();
		$this->migrate_legacy_flow_pages();

		// Initialize admin.
		if ( is_admin() ) {
			DSF_Admin::get_instance();
			DSF_Editor::get_instance();
			DSF_Ajax::get_instance();
			DSF_Popup::get_instance();
		}

		// Initialize frontend (always needed for rendering).
		DSF_Frontend::get_instance();
		DSF_Notification_Bar::get_instance();
		DSF_Forms::get_instance();
		DSF_Connections::get_instance();
		// Redirects run on the frontend (template_redirect) and admin (admin-post).
		DSF_Redirects::get_instance();
		// Mail / SMTP hooks phpmailer_init, which fires anywhere wp_mail() is used.
		DSF_Mail_SMTP::get_instance();

		if ( is_admin() ) {
			DSF_Entries::get_instance();
			DSF_Import_Export::get_instance();
		}

		// Initialize blocks.
		DSF_Blocks::get_instance();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create custom tables if needed.
		$this->create_tables();

		update_option( 'dsf_needs_rewrite_flush', 1 );

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set default options.
		$this->set_default_options();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Stop the email-log pruning cron.
		if ( class_exists( 'DSF_Mail_SMTP' ) ) {
			wp_clear_scheduled_hook( DSF_Mail_SMTP::CLEANUP_HOOK );
		}

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
			'dsf_version'              => DSF_VERSION,
			'dsf_default_colors'       => array(
				'primary'    => '#3B82F6',
				'secondary'  => '#1E40AF',
				'text'       => '#1F2937',
				'background' => '#FFFFFF',
			),
			'dsf_enabled_post_types'   => array( 'page' ),
			'dsf_recaptcha_enabled'    => false,
			'dsf_recaptcha_site_key'   => '',
			'dsf_recaptcha_secret_key' => '',
			'dsf_recaptcha_threshold'  => 0.5,
			'dsf_notification_bar'     => DSF_Notification_Bar::get_defaults(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Convert legacy Flow CPT pages into normal WordPress pages once.
	 */
	private function migrate_legacy_flow_pages() {
		if ( get_option( 'dsf_legacy_flow_pages_migrated' ) ) {
			return;
		}

		global $wpdb;

		$post_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'dsf_page'"
		);

		if ( empty( $post_ids ) ) {
			update_option( 'dsf_legacy_flow_pages_migrated', 1, false );
			update_option( 'dsf_needs_rewrite_flush', 1, false );
			return;
		}

		$legacy_paths = array();
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}
			$legacy_paths[ $post_id ] = $this->build_legacy_flow_path( $post_id );
		}

		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}

			$legacy_path = $legacy_paths[ $post_id ] ?? '';
			if ( $legacy_path ) {
				update_post_meta( $post_id, '_dsf_legacy_flow_path', $legacy_path );
			}

			update_post_meta( $post_id, '_dsf_enabled', true );
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_type' => 'page',
				)
			);
		}

		update_option( 'dsf_legacy_flow_pages_migrated', 1, false );
		update_option( 'dsf_needs_rewrite_flush', 1, false );
	}

	/**
	 * Flush rewrite rules after WordPress has finished registering routes.
	 */
	public function handle_pending_rewrite_flush() {
		$this->maybe_flush_rewrite_rules();
	}

	/**
	 * Build the old /flow/... request path for migrated pages.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function build_legacy_flow_path( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$segments = array();
		$current  = $post;

		while ( $current instanceof WP_Post && 'dsf_page' === $current->post_type ) {
			if ( $current->post_name ) {
				array_unshift( $segments, $current->post_name );
			}

			if ( empty( $current->post_parent ) ) {
				break;
			}

			$current = get_post( $current->post_parent );
		}

		if ( empty( $segments ) ) {
			return '';
		}

		return wp_parse_url( home_url( trailingslashit( 'flow/' . implode( '/', $segments ) ) ), PHP_URL_PATH );
	}

	/**
	 * Flush rewrite rules once after route changes.
	 *
	 * @param bool $set_flag Whether to set the pending-flush flag first.
	 */
	private function maybe_flush_rewrite_rules( $set_flag = false ) {
		if ( $set_flag ) {
			update_option( 'dsf_needs_rewrite_flush', 1, false );
		}

		if ( ! get_option( 'dsf_needs_rewrite_flush' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( 'dsf_needs_rewrite_flush' );
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
