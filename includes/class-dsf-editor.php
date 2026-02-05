<?php
/**
 * Editor functionality for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Editor {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_editor_redirect' ) );
		add_action( 'admin_menu', array( $this, 'add_editor_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
		add_filter( 'admin_body_class', array( $this, 'add_editor_body_class' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
	}

	/**
	 * Add type="module" to Vite scripts
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ) {
		unset( $src );
		// Add type="module" for Vite dev scripts
		if ( in_array( $handle, array( 'dsf-editor-vite', 'dsf-editor' ), true ) ) {
			// Check if it's already a module
			if ( false !== strpos( $tag, 'type="module"' ) ) {
				return $tag;
			}
			// Replace the script tag to add type="module"
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Handle redirect to editor when editing a Flow Page
	 */
	public function handle_editor_redirect() {
		global $pagenow;

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$action  = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action  = $action ? sanitize_key( $action ) : '';

		if ( 'post.php' === $pagenow && $post_id && 'edit' === $action ) {
			$post_id   = intval( $post_id );
			$post_type = get_post_type( $post_id );

			if ( 'dsf_page' === $post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dsf-editor&post_id=' . $post_id ) );
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
			__( 'Edit with Flow', 'designstudio-flow' ),
			__( 'Edit with Flow', 'designstudio-flow' ),
			'edit_pages',
			'dsf-editor',
			array( $this, 'render_editor_page' )
		);
	}

	/**
	 * Enqueue editor scripts and styles
	 */
	public function enqueue_editor_scripts( $hook ) {
		if ( 'admin_page_dsf-editor' !== $hook ) {
			return;
		}

		// Remove all other styles and scripts for clean editor
		global $wp_styles, $wp_scripts;

		// Keep only essential WordPress scripts
		$allowed_scripts = array( 'jquery', 'wp-api', 'wp-util', 'media-upload', 'thickbox' );
		$allowed_styles  = array( 'thickbox', 'media-views', 'imgareaselect' );

		// Enqueue media library
		wp_enqueue_media();

		// Production or development mode
		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		if ( $is_dev ) {
			// Development - load from Vite dev server
			wp_enqueue_script(
				'dsf-editor-vite',
				'http://localhost:5173/@vite/client',
				array(),
				DSF_VERSION,
				true
			);
			wp_enqueue_script(
				'dsf-editor',
				'http://localhost:5173/src/main.js',
				array( 'dsf-editor-vite' ),
				DSF_VERSION,
				true
			);
		} else {
			// Production - load built assets
			wp_enqueue_style(
				'dsf-editor',
				DSF_PLUGIN_URL . 'assets/css/editor.css',
				array(),
				DSF_VERSION
			);

			wp_enqueue_script(
				'dsf-editor',
				DSF_PLUGIN_URL . 'assets/js/editor.js',
				array(),
				DSF_VERSION,
				true
			);
		}

		// Pass data to JavaScript
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		wp_localize_script(
			'dsf-editor',
			'dsfEditorData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'dsf/v1/' ),
				'nonce'       => wp_create_nonce( 'dsf_editor_nonce' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'postId'      => $post_id,
				'postTitle'   => $post ? $post->post_title : '',
				'pageData'    => $this->get_page_data( $post_id ),
				'blocks'      => DSF_Blocks::get_instance()->get_registered_blocks(),
				'categories'  => $this->get_wc_categories(),
				'pluginUrl'   => DSF_PLUGIN_URL,
				'homeUrl'     => home_url(),
				'adminUrl'    => admin_url(),
				'previewUrl'  => $post_id ? get_preview_post_link( $post_id ) : '',
				'isWooActive' => class_exists( 'WooCommerce' ),
			)
		);
	}

	/**
	 * Add body class for editor page
	 */
	public function add_editor_body_class( $classes ) {
		$screen = get_current_screen();

		if ( $screen && 'admin_page_dsf-editor' === $screen->id ) {
			$classes .= ' dsf-editor-page';
		}

		return $classes;
	}

	/**
	 * Render editor page
	 */
	public function render_editor_page() {
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;

		// Create new page if no ID
		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'dsf_page',
					'post_status' => 'draft',
					'post_title'  => __( 'Untitled Page', 'designstudio-flow' ),
				)
			);

			wp_safe_redirect( admin_url( 'admin.php?page=dsf-editor&post_id=' . $post_id ) );
			exit;
		}

		include DSF_PLUGIN_DIR . 'templates/editor-page.php';
	}

	/**
	 * Get page data (blocks and settings)
	 */
	private function get_page_data( $post_id ) {
		if ( ! $post_id ) {
			return array(
				'blocks'   => array(),
				'settings' => $this->get_default_settings(),
			);
		}

		$blocks_meta   = get_post_meta( $post_id, '_dsf_blocks', true );
		$settings_meta = get_post_meta( $post_id, '_dsf_settings', true );

		if ( is_array( $blocks_meta ) ) {
			$blocks_data  = $blocks_meta;
			$blocks_error = JSON_ERROR_NONE;
		} else {
			$blocks_data  = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks_error = json_last_error();
			if ( JSON_ERROR_NONE !== $blocks_error && $blocks_meta ) {
				$blocks_data  = json_decode( wp_unslash( $blocks_meta ), true );
				$blocks_error = json_last_error();
			}
		}

		if ( is_array( $settings_meta ) ) {
			$settings_data  = $settings_meta;
			$settings_error = JSON_ERROR_NONE;
		} else {
			$settings_data  = $settings_meta ? json_decode( $settings_meta, true ) : $this->get_default_settings();
			$settings_error = json_last_error();
			if ( JSON_ERROR_NONE !== $settings_error && $settings_meta ) {
				$settings_data  = json_decode( wp_unslash( $settings_meta ), true );
				$settings_error = json_last_error();
			}
		}

		if ( JSON_ERROR_NONE !== $blocks_error ) {
			$blocks_data = array();
		}
		if ( JSON_ERROR_NONE !== $settings_error || ! is_array( $settings_data ) ) {
			$settings_data = $this->get_default_settings();
		}

		return array(
			'blocks'   => $blocks_data,
			'settings' => $settings_data,
		);
	}

	/**
	 * Get default page settings
	 */
	private function get_default_settings() {
		return array(
			'theme'  => array(
				'primaryColor'    => '#2C5F5D',
				'secondaryColor'  => '#1E40AF',
				'textColor'       => '#1F2937',
				'backgroundColor' => '#FFFFFF',
			),
			'layout' => array(
				'containerWidth' => 1800,
				'contentPadding' => 10,
				'showHeader'     => true,
				'showFooter'     => true,
				'template'       => 'default',
			),
		);
	}

	/**
	 * Get WooCommerce categories
	 */
	private function get_wc_categories() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		return array_map(
			function ( $cat ) {
				$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );

				return array(
					'id'    => $cat->term_id,
					'name'  => $cat->name,
					'slug'  => $cat->slug,
					'count' => $cat->count,
					'image' => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
				);
			},
			$categories
		);
	}
}
