<?php
/**
 * AJAX handlers for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Ajax {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Save page
		add_action( 'wp_ajax_dsf_save_page', array( $this, 'save_page' ) );
		add_action( 'wp_ajax_dsf_history_list', array( $this, 'history_list' ) );
		add_action( 'wp_ajax_dsf_history_restore', array( $this, 'history_restore' ) );
		add_action( 'wp_ajax_dsf_history_settings_list', array( $this, 'history_settings_list' ) );
		add_action( 'wp_ajax_dsf_history_settings_restore', array( $this, 'history_settings_restore' ) );

		// Get products
		add_action( 'wp_ajax_dsf_get_products', array( $this, 'get_products' ) );
		add_action( 'wp_ajax_nopriv_dsf_get_products', array( $this, 'get_products' ) );

		// Search products
		add_action( 'wp_ajax_dsf_search_products', array( $this, 'search_products' ) );

		// Get categories
		add_action( 'wp_ajax_dsf_get_categories', array( $this, 'get_categories' ) );
		add_action( 'wp_ajax_nopriv_dsf_get_categories', array( $this, 'get_categories' ) );

		// Upload image
		add_action( 'wp_ajax_dsf_upload_image', array( $this, 'upload_image' ) );

		// Update page title
		add_action( 'wp_ajax_dsf_update_title', array( $this, 'update_title' ) );

		// Publish page
		add_action( 'wp_ajax_dsf_publish_page', array( $this, 'publish_page' ) );

		// Render shortcode for modal content
		add_action( 'wp_ajax_dsf_render_shortcode', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_nopriv_dsf_render_shortcode', array( $this, 'render_shortcode' ) );

		// List reusable popups for the page-settings picker.
		add_action( 'wp_ajax_dsf_list_popups', array( $this, 'list_popups' ) );

		// Saved Blocks — reusable block library.
		add_action( 'wp_ajax_dsf_save_block', array( $this, 'save_block' ) );
		add_action( 'wp_ajax_dsf_list_saved_blocks', array( $this, 'list_saved_blocks' ) );
		add_action( 'wp_ajax_dsf_delete_saved_block', array( $this, 'delete_saved_block' ) );
		add_action( 'wp_ajax_dsf_feature_saved_block', array( $this, 'feature_saved_block' ) );
		add_action( 'wp_ajax_dsf_import_saved_block', array( $this, 'import_saved_block' ) );

		// Templates — reusable groups of blocks (section / whole page).
		add_action( 'wp_ajax_dsf_save_template', array( $this, 'save_template' ) );
		add_action( 'wp_ajax_dsf_list_templates', array( $this, 'list_templates' ) );
		add_action( 'wp_ajax_dsf_delete_template', array( $this, 'delete_template' ) );
		add_action( 'wp_ajax_dsf_import_template', array( $this, 'import_template' ) );

		// Update a single saved block from its dedicated editor + sync every
		// instance that references it.
		add_action( 'wp_ajax_dsf_save_library_item', array( $this, 'save_library_item' ) );

		// Product templates — fetch a sample product's live data for the editor preview.
		add_action( 'wp_ajax_dsf_get_product_context', array( $this, 'get_product_context' ) );

		// Shop templates — fetch a sample archive's data for the editor preview.
		add_action( 'wp_ajax_dsf_get_shop_context', array( $this, 'get_shop_context' ) );

		// Blog templates — fetch a sample post archive for the editor preview.
		add_action( 'wp_ajax_dsf_get_blog_context', array( $this, 'get_blog_context' ) );

		// Header/footer templates — set (or clear) the site-wide default.
		add_action( 'wp_ajax_dsf_set_default_layout', array( $this, 'set_default_layout' ) );
	}

	/** Return the current object's safe history metadata. */
	public function history_list() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$history = DSF_History::get_instance();
		wp_send_json_success(
			array(
				'currentHash' => $history->current_post_hash( $post_id ),
				'records'     => $history->list_records( 'post', $post_id, '', $post->post_type ),
			)
		);
	}

	/** Restore one authorized history record. */
	public function history_restore() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();
		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$expected = isset( $_POST['current_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['current_hash'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			wp_send_json_error( array( 'message' => 'Invalid current state.' ), 400 );
		}
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$history = DSF_History::get_instance();
		$record  = $history->get_record( $record_id );
		if ( is_wp_error( $record ) ) {
			wp_send_json_error( array( 'message' => $record->get_error_message() ), 400 );
		}
		$result = $history->restore_post_record( $record, $post_id, $expected );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 409 );
		}
		wp_send_json_success( $result );
	}

	public function history_settings_list() {
		if ( ! check_ajax_referer( 'dsf_save_settings', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$key = isset( $_POST['settings_key'] ) ? sanitize_key( wp_unslash( $_POST['settings_key'] ) ) : '';
		$history = DSF_History::get_instance();
		wp_send_json_success( array( 'currentHash' => $history->current_settings_hash( $key ), 'records' => $history->list_records( 'settings', 0, $key, 'settings' ) ) );
	}

	public function history_settings_restore() {
		if ( ! check_ajax_referer( 'dsf_save_settings', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$key      = isset( $_POST['settings_key'] ) ? sanitize_key( wp_unslash( $_POST['settings_key'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$expected = isset( $_POST['current_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['current_hash'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			wp_send_json_error( array( 'message' => 'Invalid current state.' ), 400 );
		}
		$history = DSF_History::get_instance();
		$record  = $history->get_record( $record_id );
		if ( is_wp_error( $record ) ) {
			wp_send_json_error( array( 'message' => $record->get_error_message() ), 400 );
		}
		$result = $history->restore_settings_record( $record, $key, $expected );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 409 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Set or clear the site-wide default header/footer layout.
	 *
	 * Pages that do not pick their own header/footer fall back to this default.
	 */
	public function set_default_layout() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$layout_id = isset( $_POST['layout_id'] ) ? absint( $_POST['layout_id'] ) : 0;
		$type      = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$type      = 'footer' === $type ? 'footer' : 'header';
		$enabled   = ! empty( $_POST['enabled'] );

		$option_key = 'header' === $type ? 'dsf_default_header_id' : 'dsf_default_footer_id';

		if ( ! $enabled ) {
			// Only clear the default if this layout is the current one.
			if ( $layout_id && absint( get_option( $option_key, 0 ) ) === $layout_id ) {
				if ( class_exists( 'DSF_History' ) ) {
					$history_result = DSF_History::get_instance()->capture_before_settings_mutation( $option_key, absint( get_option( $option_key, 0 ) ), 0, 'default_layout' );
					if ( is_wp_error( $history_result ) ) {
						wp_send_json_error( array( 'message' => 'Could not create a Quick Restore point.' ), 500 );
					}
				}
				update_option( $option_key, 0 );
			}
			wp_send_json_success( array( 'defaultId' => absint( get_option( $option_key, 0 ) ) ) );
		}

		if ( ! $layout_id ) {
			wp_send_json_error( array( 'message' => 'Invalid layout ID' ) );
		}
		if ( ! current_user_can( 'edit_post', $layout_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$layout = get_post( $layout_id );
		if ( ! $layout || 'dsf_layout' !== $layout->post_type ) {
			wp_send_json_error( array( 'message' => 'Not a layout' ) );
		}

		$layout_type = get_post_meta( $layout_id, '_dsf_layout_type', true );
		$layout_type = 'footer' === $layout_type ? 'footer' : 'header';
		if ( $layout_type !== $type ) {
			wp_send_json_error( array( 'message' => 'Layout type mismatch' ) );
		}

		if ( class_exists( 'DSF_History' ) ) {
			$history_result = DSF_History::get_instance()->capture_before_settings_mutation( $option_key, absint( get_option( $option_key, 0 ) ), $layout_id, 'default_layout' );
			if ( is_wp_error( $history_result ) ) {
				wp_send_json_error( array( 'message' => 'Could not create a Quick Restore point.' ), 500 );
			}
		}
		update_option( $option_key, $layout_id );
		wp_send_json_success( array( 'defaultId' => $layout_id ) );
	}

	/**
	 * Return the live data payload for a product so the editor can preview product
	 * blocks against a real product. Editor-only: requires a valid editor nonce and
	 * page-editing capability.
	 */
	public function get_product_context() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		// Fall back to the most recent published product when none is chosen yet, so
		// the editor still shows representative data.
		if ( ! $product_id ) {
			$recent     = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$product_id = ! empty( $recent ) ? absint( $recent[0] ) : 0;
		}

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product not found' ), 404 );
		}

		$context = DSF_Product_Templates::build_product_context(
			$product_id,
			array(
				'related' => true,
				'upsells' => true,
			)
		);
		if ( empty( $context ) ) {
			wp_send_json_error( array( 'message' => 'Product not found' ), 404 );
		}

		wp_send_json_success( array( 'product' => $context ) );
	}

	/**
	 * Return a sample archive payload so the editor can preview shop blocks
	 * against real catalog data. Editor-only: requires a valid editor nonce and
	 * page-editing capability.
	 */
	public function get_shop_context() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$context = DSF_Shop_Templates::build_preview_context( $term_id );

		if ( empty( $context ) ) {
			wp_send_json_error( array( 'message' => 'Archive preview unavailable' ), 404 );
		}

		wp_send_json_success( array( 'archive' => $context ) );
	}

	/**
	 * Return a sample post-archive payload so the editor can preview blog blocks
	 * against real content. Editor-only: requires a valid editor nonce and
	 * page-editing capability.
	 */
	public function get_blog_context() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$context = DSF_Blog_Templates::build_preview_context( $term_id );

		if ( empty( $context ) ) {
			wp_send_json_error( array( 'message' => 'Archive preview unavailable' ), 404 );
		}

		wp_send_json_success( array( 'archive' => $context ) );
	}

	/**
	 * Return the list of reusable popups for the page-settings picker.
	 */
	public function list_popups() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		wp_send_json_success( array( 'popups' => DSF_Popup::get_popup_list() ) );
	}

	/* -----------------------------------------------------------------
	 * Saved Blocks (reusable block library)
	 * ----------------------------------------------------------------- */

	/**
	 * Save the current block (type + full settings) as a reusable saved block.
	 */
	public function save_block() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$type     = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$tags     = $this->sanitize_tag_list( isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '' );

		if ( '' === $type || ! DSF_Blocks::get_instance()->get_block( $type ) ) {
			wp_send_json_error( array( 'message' => 'Unknown block type' ), 400 );
		}

		$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';
		$settings     = json_decode( $settings_raw, true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Run through the same per-type sanitizer used when saving a page.
		$sanitized = $this->sanitize_known_block_settings( array( array( 'type' => $type, 'settings' => $settings ) ) );
		$settings  = isset( $sanitized[0]['settings'] ) && is_array( $sanitized[0]['settings'] ) ? $sanitized[0]['settings'] : array();

		if ( '' === $name ) {
			$def  = DSF_Blocks::get_instance()->get_block( $type );
			$name = isset( $def['name'] ) ? $def['name'] : __( 'Saved block', 'designstudio-flow' );
		}

		// An optional id updates an existing saved block in place (re-save), rather
		// than creating a duplicate.
		$update_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

		if ( $update_id ) {
			if ( 'dsf_saved_block' !== get_post_type( $update_id ) ) {
				wp_send_json_error( array( 'message' => 'Invalid saved block' ), 400 );
			}
			if ( ! current_user_can( 'edit_post', $update_id ) ) {
				wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
			}
			$post_id = wp_update_post(
				array(
					'ID'         => $update_id,
					'post_title' => $name,
				),
				true
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'dsf_saved_block',
					'post_status' => 'publish',
					'post_title'  => $name,
				),
				true
			);
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Could not save block' ), 500 );
		}

		update_post_meta( $post_id, '_dsf_block_type', $type );
		update_post_meta( $post_id, '_dsf_block_settings', $settings );
		update_post_meta( $post_id, '_dsf_block_category', $category );
		update_post_meta( $post_id, '_dsf_block_tags', $tags );

		wp_send_json_success(
			array(
				'id'        => $post_id,
				'name'      => $name,
				'type'      => $type,
				'settings'  => $settings,
				'category'  => $category,
				'tags'      => $tags,
				'author'    => $this->saved_block_author_name( $post_id ),
				'exportUrl' => $this->saved_block_export_url( $post_id ),
			)
		);
	}

	/**
	 * Parse a tag payload (JSON array or comma-separated string) into a clean,
	 * de-duplicated list (max 20).
	 */
	private function sanitize_tag_list( $raw ) {
		$items = json_decode( (string) $raw, true );
		if ( ! is_array( $items ) ) {
			$items = '' === trim( (string) $raw ) ? array() : explode( ',', (string) $raw );
		}
		$tags = array();
		foreach ( $items as $item ) {
			$tag = sanitize_text_field( (string) $item );
			if ( '' !== $tag && ! in_array( $tag, $tags, true ) ) {
				$tags[] = $tag;
			}
			if ( count( $tags ) >= 20 ) {
				break;
			}
		}
		return $tags;
	}

	/**
	 * Display name of a saved block's / template's author.
	 */
	private function saved_block_author_name( $post_id ) {
		$author_id = (int) get_post_field( 'post_author', $post_id );
		$name      = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
		return $name ? $name : '';
	}

	/**
	 * Return the site-wide library of saved blocks for the editor picker.
	 */
	public function list_saved_blocks() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$posts = get_posts(
			array(
				'post_type'      => 'dsf_saved_block',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$blocks = array();
		foreach ( $posts as $post ) {
			$type = (string) get_post_meta( $post->ID, '_dsf_block_type', true );
			if ( '' === $type ) {
				continue;
			}
			$settings = get_post_meta( $post->ID, '_dsf_block_settings', true );
			$blocks[] = array(
				'id'        => $post->ID,
				'name'      => $post->post_title,
				'type'      => $type,
				'settings'  => is_array( $settings ) ? $settings : array(),
				'category'  => (string) get_post_meta( $post->ID, '_dsf_block_category', true ),
				'tags'      => array_values( (array) get_post_meta( $post->ID, '_dsf_block_tags', true ) ),
				'author'    => $this->saved_block_author_name( $post->ID ),
				'featured'  => (bool) get_post_meta( $post->ID, '_dsf_block_featured', true ),
				'exportUrl' => $this->saved_block_export_url( $post->ID ),
			);
		}

		wp_send_json_success( array( 'savedBlocks' => $blocks ) );
	}

	/**
	 * Delete a saved block from the library.
	 */
	public function delete_saved_block() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( ! $id || 'dsf_saved_block' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid saved block' ), 400 );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		wp_trash_post( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * Promote/demote a saved block into the shared Presets library.
	 */
	public function feature_saved_block() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( ! $id || 'dsf_saved_block' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid saved block' ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$featured = ! empty( $_POST['featured'] ) && 'false' !== $_POST['featured'];
		update_post_meta( $id, '_dsf_block_featured', $featured ? 1 : 0 );

		wp_send_json_success(
			array(
				'id'       => $id,
				'featured' => $featured,
			)
		);
	}

	/**
	 * Import saved blocks from an exported JSON payload uploaded in the editor
	 * picker. Accepts the same file format the admin Tools export produces.
	 */
	public function import_saved_block() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is validated and per-field sanitized in parse_saved_block_import().
		$items   = $this->parse_saved_block_import( $payload );

		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => 'No importable saved blocks found in this file.' ), 400 );
		}

		$imported = array();
		foreach ( $items as $item ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'dsf_saved_block',
					'post_status' => 'publish',
					'post_title'  => $item['name'],
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_dsf_block_type', $item['type'] );
			update_post_meta( $post_id, '_dsf_block_settings', $item['settings'] );
			update_post_meta( $post_id, '_dsf_block_category', $item['category'] );
			update_post_meta( $post_id, '_dsf_block_tags', $item['tags'] );

			$imported[] = array(
				'id'        => $post_id,
				'name'      => $item['name'],
				'type'      => $item['type'],
				'settings'  => $item['settings'],
				'category'  => $item['category'],
				'tags'      => $item['tags'],
				'author'    => $this->saved_block_author_name( $post_id ),
				'featured'  => false,
				'exportUrl' => $this->saved_block_export_url( $post_id ),
			);
		}

		if ( empty( $imported ) ) {
			wp_send_json_error( array( 'message' => 'Could not import any blocks.' ), 500 );
		}

		wp_send_json_success( array( 'savedBlocks' => $imported ) );
	}

	/**
	 * Import templates from an exported JSON payload uploaded in the editor
	 * picker. Accepts the same file format the admin Tools export produces.
	 */
	public function import_template() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is validated + per-field sanitized below.
		$data    = $payload ? json_decode( $payload, true ) : null;

		if ( ! is_array( $data ) || empty( $data['_dsf_export'] ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			wp_send_json_error( array( 'message' => 'No importable templates found in this file.' ), 400 );
		}

		$imported = array();
		foreach ( $data['items'] as $item ) {
			if ( ! is_array( $item ) || 'dsf_template' !== ( $item['post_type'] ?? '' ) ) {
				continue;
			}
			$meta   = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
			$blocks = $this->sanitize_template_blocks( $meta['_dsf_template_blocks'] ?? array() );
			if ( empty( $blocks ) ) {
				continue;
			}
			$theme = isset( $meta['_dsf_template_theme'] ) && is_array( $meta['_dsf_template_theme'] )
				? $this->sanitize_theme_value( $meta['_dsf_template_theme'] )
				: array();
			$kind  = in_array( $meta['_dsf_template_kind'] ?? '', array( 'page', 'section' ), true ) ? $meta['_dsf_template_kind'] : 'page';
			$name  = sanitize_text_field( $item['title'] ?? '' );
			if ( '' === $name ) {
				$name = __( 'Imported template', 'designstudio-flow' );
			}

			$post_id = wp_insert_post(
				array( 'post_type' => 'dsf_template', 'post_status' => 'publish', 'post_title' => $name ),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			update_post_meta( $post_id, '_dsf_template_blocks', $blocks );
			update_post_meta( $post_id, '_dsf_template_theme', $theme );
			update_post_meta( $post_id, '_dsf_template_kind', $kind );

			$imported[] = $this->format_template( get_post( $post_id ) );
		}

		if ( empty( $imported ) ) {
			wp_send_json_error( array( 'message' => 'Could not import any templates.' ), 500 );
		}

		wp_send_json_success( array( 'templates' => $imported ) );
	}

	/**
	 * Save a single saved block from its dedicated editor and propagate the new
	 * settings to every page/layout/template instance that references it.
	 */
	public function save_library_item() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id || 'dsf_saved_block' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid saved block' ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( '' === $type ) {
			$type = get_post_meta( $post_id, '_dsf_block_type', true );
		}

		$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized per-type below.
		$settings     = json_decode( $settings_raw, true );
		$settings     = is_array( $settings ) ? $settings : array();
		$sanitized    = $this->sanitize_known_block_settings( array( array( 'type' => $type, 'settings' => $settings ) ) );
		$settings     = isset( $sanitized[0]['settings'] ) && is_array( $sanitized[0]['settings'] ) ? $sanitized[0]['settings'] : array();

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( class_exists( 'DSF_History' ) ) {
			$history = DSF_History::get_instance();
			$next    = $history->proposed_post_payload( $post_id, array( 'post_title' => '' !== $title ? $title : get_the_title( $post_id ), 'meta' => array( '_dsf_block_type' => $type, '_dsf_block_settings' => $settings ) ) );
			$history_result = $history->capture_before_post_mutation( $post_id, 'dsf_saved_block', $next, 'saved_block_save' );
			if ( is_wp_error( $history_result ) ) {
				wp_send_json_error( array( 'message' => 'Could not create a Quick Restore point.' ), 500 );
			}
		}
		if ( '' !== $title ) {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		}

		update_post_meta( $post_id, '_dsf_block_type', $type );
		update_post_meta( $post_id, '_dsf_block_settings', $settings );

		$synced = $this->sync_saved_block_instances( $post_id, $settings );
		if ( is_wp_error( $synced ) ) {
			wp_send_json_error( array( 'message' => 'Could not create all Quick Restore points; no linked pages were changed.' ), 500 );
		}

		wp_send_json_success(
			array(
				'id'       => $post_id,
				'type'     => $type,
				'settings' => $settings,
				'synced'   => $synced,
			)
		);
	}

	/**
	 * Rewrite every block whose `savedBlockId` matches this saved block with its
	 * new settings, across all Flow content. Returns the number of posts updated.
	 *
	 * @param int   $saved_block_id Saved block post ID.
	 * @param array $settings       New settings to apply.
	 * @return int
	 */
	private function sync_saved_block_instances( $saved_block_id, $settings ) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'dsf_layout', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_key'       => '_dsf_blocks', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$changes = array();
		foreach ( $posts as $pid ) {
			$blocks = get_post_meta( $pid, '_dsf_blocks', true );
			if ( ! is_array( $blocks ) ) {
				continue;
			}
			$changed = false;
			foreach ( $blocks as &$block ) {
				if ( is_array( $block ) && (int) ( $block['savedBlockId'] ?? 0 ) === (int) $saved_block_id ) {
					$block['settings'] = $settings;
					$changed           = true;
				}
			}
			unset( $block );

			if ( $changed ) {
				if ( class_exists( 'DSF_History' ) ) {
					$history = DSF_History::get_instance();
					$next    = $history->proposed_post_payload( $pid, array( 'meta' => array( '_dsf_blocks' => $blocks ) ) );
					$history_result = $history->capture_before_post_mutation( $pid, get_post_type( $pid ), $next, 'saved_block_sync' );
					if ( is_wp_error( $history_result ) ) {
						return $history_result;
					}
				}
				$changes[] = array( 'id' => absint( $pid ), 'blocks' => $blocks );
			}
		}
		foreach ( $changes as $change ) {
			update_post_meta( $change['id'], '_dsf_blocks', $change['blocks'] );
			delete_post_meta( $change['id'], '_dsf_html_snapshot' );
		}

		return count( $changes );
	}

	/**
	 * Parse and sanitize an exported JSON payload into clean saved-block items.
	 *
	 * Only `dsf_saved_block` items with a registered block type survive; every
	 * item is rebuilt from known keys and its settings run through the same
	 * per-type sanitizer used when saving a page.
	 *
	 * @param mixed $payload Raw JSON string from the upload.
	 * @return array[] List of { name, type, settings, category, tags }.
	 */
	private function parse_saved_block_import( $payload ) {
		if ( ! is_string( $payload ) || '' === $payload || strlen( $payload ) > 5242880 ) {
			return array();
		}

		$data = json_decode( $payload, true );
		if ( ! is_array( $data ) || empty( $data['_dsf_export'] ) || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array();
		}

		$registered = class_exists( 'DSF_Blocks' ) ? DSF_Blocks::get_instance()->get_registered_blocks() : array();
		$clean      = array();

		foreach ( array_slice( $data['items'], 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || 'dsf_saved_block' !== ( $item['post_type'] ?? '' ) ) {
				continue;
			}

			$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
			$type = sanitize_key( is_string( $meta['_dsf_block_type'] ?? null ) ? $meta['_dsf_block_type'] : '' );
			if ( '' === $type || ! isset( $registered[ $type ] ) ) {
				continue;
			}

			$settings  = is_array( $meta['_dsf_block_settings'] ?? null ) ? $meta['_dsf_block_settings'] : array();
			$sanitized = $this->sanitize_known_block_settings(
				array(
					array(
						'type'     => $type,
						'settings' => $settings,
					),
				)
			);
			$settings  = isset( $sanitized[0]['settings'] ) && is_array( $sanitized[0]['settings'] ) ? $sanitized[0]['settings'] : array();

			$name = sanitize_text_field( is_string( $item['title'] ?? null ) ? $item['title'] : '' );
			if ( '' === $name ) {
				$name = isset( $registered[ $type ]['name'] ) ? $registered[ $type ]['name'] : 'Imported block';
			}

			$clean[] = array(
				'name'     => $name,
				'type'     => $type,
				'settings' => $settings,
				'category' => sanitize_text_field( is_string( $meta['_dsf_block_category'] ?? null ) ? $meta['_dsf_block_category'] : '' ),
				'tags'     => $this->sanitize_tag_list( wp_json_encode( (array) ( $meta['_dsf_block_tags'] ?? array() ) ) ),
			);
		}

		return $clean;
	}

	/**
	 * Build the nonce'd admin-post URL that downloads a saved block as JSON,
	 * reusing the DSF_Import_Export single-export handler.
	 *
	 * @param int $post_id Saved block post ID.
	 * @return string
	 */
	private function saved_block_export_url( $post_id ) {
		if ( ! class_exists( 'DSF_Import_Export' ) ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => DSF_Import_Export::SINGLE_ACTION,
					'post_id' => (int) $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			DSF_Import_Export::SINGLE_ACTION . '_' . (int) $post_id
		);
	}

	/* -----------------------------------------------------------------
	 * Templates (reusable groups of blocks)
	 * ----------------------------------------------------------------- */

	/**
	 * Save a group of blocks (a section or a whole page) as a reusable template.
	 */
	public function save_template() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'page';
		if ( ! in_array( $kind, array( 'page', 'section' ), true ) ) {
			$kind = 'page';
		}

		$blocks_raw = isset( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : '[]';
		$blocks     = $this->sanitize_template_blocks( json_decode( $blocks_raw, true ) );
		if ( empty( $blocks ) ) {
			wp_send_json_error( array( 'message' => 'No blocks to save' ), 400 );
		}

		$theme_raw = isset( $_POST['theme'] ) ? wp_unslash( $_POST['theme'] ) : '';
		$theme     = $theme_raw ? json_decode( $theme_raw, true ) : array();
		$theme     = is_array( $theme ) ? $this->sanitize_theme_value( $theme ) : array();

		if ( '' === $name ) {
			$name = __( 'Untitled template', 'designstudio-flow' );
		}

		$update_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $update_id ) {
			if ( 'dsf_template' !== get_post_type( $update_id ) ) {
				wp_send_json_error( array( 'message' => 'Invalid template' ), 400 );
			}
			if ( ! current_user_can( 'edit_post', $update_id ) ) {
				wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
			}
			$post_id = wp_update_post( array( 'ID' => $update_id, 'post_title' => $name ), true );
		} else {
			$post_id = wp_insert_post( array( 'post_type' => 'dsf_template', 'post_status' => 'publish', 'post_title' => $name ), true );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Could not save template' ), 500 );
		}

		update_post_meta( $post_id, '_dsf_template_blocks', $blocks );
		update_post_meta( $post_id, '_dsf_template_theme', $theme );
		update_post_meta( $post_id, '_dsf_template_kind', $kind );

		wp_send_json_success( $this->format_template( get_post( $post_id ) ) );
	}

	/**
	 * Return the site-wide list of templates for the editor picker.
	 */
	public function list_templates() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$posts = get_posts(
			array(
				'post_type'      => 'dsf_template',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$templates = array();
		foreach ( $posts as $post ) {
			$templates[] = $this->format_template( $post );
		}

		wp_send_json_success( array( 'templates' => $templates ) );
	}

	/**
	 * Delete a template from the library.
	 */
	public function delete_template() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( ! $id || 'dsf_template' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid template' ), 400 );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		wp_trash_post( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}

	private function format_template( $post ) {
		if ( ! $post ) {
			return array();
		}
		$blocks = get_post_meta( $post->ID, '_dsf_template_blocks', true );
		$theme  = get_post_meta( $post->ID, '_dsf_template_theme', true );
		$kind   = get_post_meta( $post->ID, '_dsf_template_kind', true );
		$blocks = is_array( $blocks ) ? $blocks : array();

		return array(
			'id'         => $post->ID,
			'name'       => $post->post_title,
			'kind'       => $kind ? $kind : 'page',
			'blockCount' => count( $blocks ),
			'blocks'     => $blocks,
			'theme'      => is_array( $theme ) ? $theme : array(),
			// Reuses the generic single-export admin-post URL (works for any
			// supported post type, including templates).
			'exportUrl'  => $this->saved_block_export_url( $post->ID ),
		);
	}

	/**
	 * Validate + per-type sanitize a template's blocks (drops unknown types and
	 * client-side ids; ids are regenerated when the template is inserted).
	 */
	private function sanitize_template_blocks( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}
		$clean = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( '' === $type || ! DSF_Blocks::get_instance()->get_block( $type ) ) {
				continue;
			}
			$clean[] = array(
				'type'     => $type,
				'settings' => ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array(),
			);
		}
		return $this->sanitize_known_block_settings( $clean );
	}

	/**
	 * Recursively sanitize a stored theme/settings object (scalar leaves only),
	 * preserving camelCase keys produced by the editor.
	 */
	private function sanitize_theme_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ (string) $key ] = $this->sanitize_theme_value( $item );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Verify permissions for editor actions.
	 */
	private function verify_permissions() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
	}

	/**
	 * Re-sanitize a Quick Restore post payload through the current Flow contracts.
	 * History records are old database data and must not bypass save-time rules.
	 *
	 * @param string $post_type Post type.
	 * @param array  $payload   Stored history payload.
	 * @return array
	 */
	public function sanitize_history_post_payload( $post_type, $payload ) {
		$post_type = sanitize_key( $post_type );
		$payload   = is_array( $payload ) ? $payload : array();
		$allowed_types = array( 'page', 'dsf_layout', 'dsf_saved_block', 'dsf_template', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template', 'dsf_form', 'dsf_popup' );
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			return array();
		}
		$status = sanitize_key( $payload['post_status'] ?? 'draft' );
		$clean  = array(
			'post_type'   => $post_type,
			'post_title'  => mb_substr( sanitize_text_field( $payload['post_title'] ?? '' ), 0, 200 ),
			'post_name'   => mb_substr( sanitize_title( $payload['post_name'] ?? '' ), 0, 200 ),
			'post_parent' => absint( $payload['post_parent'] ?? 0 ),
			'post_status' => in_array( $status, array( 'draft', 'publish', 'private', 'pending', 'future' ), true ) ? $status : 'draft',
			'meta'        => array(),
		);
		$meta = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();
		$blocks = isset( $meta['_dsf_blocks'] ) && is_array( $meta['_dsf_blocks'] ) ? $this->sanitize_known_block_settings( $meta['_dsf_blocks'] ) : null;
		if ( null !== $blocks && in_array( $post_type, array( 'page', 'dsf_layout', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' ), true ) ) {
			$clean['meta']['_dsf_blocks'] = $blocks;
		}
		if ( isset( $meta['_dsf_settings'] ) && is_array( $meta['_dsf_settings'] ) ) {
			$settings           = $meta['_dsf_settings'];
			$settings['theme']  = $this->sanitize_page_theme_settings( $settings['theme'] ?? array() );
			$settings['layout'] = $this->sanitize_page_layout_settings( $settings['layout'] ?? array() );
			$settings['popup']  = $this->sanitize_popup_settings( $settings['popup'] ?? array() );
			$settings['popupId'] = absint( $settings['popupId'] ?? 0 );
			$settings['seo']    = $this->sanitize_page_seo_settings( $settings['seo'] ?? array() );
			$clean['meta']['_dsf_settings'] = $settings;
		}
		if ( 'dsf_template' === $post_type ) {
			$clean['meta']['_dsf_template_blocks'] = $this->sanitize_template_blocks( $meta['_dsf_template_blocks'] ?? array() );
			$clean['meta']['_dsf_template_theme']  = $this->sanitize_page_theme_settings( $meta['_dsf_template_theme'] ?? array() );
			$clean['meta']['_dsf_template_kind']   = in_array( sanitize_key( $meta['_dsf_template_kind'] ?? 'page' ), array( 'page', 'section' ), true ) ? sanitize_key( $meta['_dsf_template_kind'] ?? 'page' ) : 'page';
		}
		if ( 'dsf_form' === $post_type && class_exists( 'DSF_Forms' ) ) {
			$form = DSF_Forms::get_instance()->sanitize_imported_form( $meta['_dsf_form_rows'] ?? array(), $meta['_dsf_form_settings'] ?? array() );
			$clean['meta']['_dsf_form_rows']     = $form['rows'];
			$clean['meta']['_dsf_form_settings'] = $form['settings'];
		}
		if ( 'dsf_popup' === $post_type ) {
			$clean['meta']['_dsf_popup_settings'] = DSF_Popup::sanitize_settings( $meta['_dsf_popup_settings'] ?? array() );
		}
		if ( 'dsf_saved_block' === $post_type ) {
			$type = sanitize_key( $meta['_dsf_block_type'] ?? '' );
			$clean['meta']['_dsf_block_type']     = $type;
			$clean['meta']['_dsf_block_settings'] = self::sanitize_block_settings_by_schema( $meta['_dsf_block_settings'] ?? array(), $type );
			$clean['meta']['_dsf_block_category'] = mb_substr( sanitize_text_field( $meta['_dsf_block_category'] ?? '' ), 0, 100 );
			$clean['meta']['_dsf_block_tags']     = array_values( array_slice( array_map( 'sanitize_key', is_array( $meta['_dsf_block_tags'] ?? null ) ? $meta['_dsf_block_tags'] : array() ), 0, 30 ) );
			$clean['meta']['_dsf_block_featured'] = ! empty( $meta['_dsf_block_featured'] );
		}
		foreach ( array( '_dsf_layout_type', '_dsf_enabled', '_dsf_noindex', '_dsf_pt_active', '_dsf_st_active', '_dsf_bt_active' ) as $key ) {
			if ( array_key_exists( $key, $meta ) ) {
				$clean['meta'][ $key ] = in_array( $key, array( '_dsf_layout_type' ), true ) ? ( 'footer' === sanitize_key( $meta[ $key ] ) ? 'footer' : 'header' ) : ! empty( $meta[ $key ] );
			}
		}
		foreach ( array( '_dsf_pt_preview_product', '_dsf_st_preview_term', '_dsf_bt_preview_term' ) as $key ) {
			if ( array_key_exists( $key, $meta ) ) {
				$clean['meta'][ $key ] = absint( $meta[ $key ] );
			}
		}
		foreach ( array( '_dsf_pt_assignment', '_dsf_st_assignment', '_dsf_bt_assignment' ) as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}
			if ( '_dsf_pt_assignment' === $key ) {
				$clean['meta'][ $key ] = DSF_Product_Templates::sanitize_assignment( $meta[ $key ] );
			} elseif ( '_dsf_st_assignment' === $key ) {
				$clean['meta'][ $key ] = DSF_Shop_Templates::sanitize_assignment( $meta[ $key ] );
			} else {
				$clean['meta'][ $key ] = DSF_Blog_Templates::sanitize_assignment( $meta[ $key ] );
			}
		}
		return $clean;
	}

	/**
	 * Normalize request payloads that may arrive as scalars, arrays, or JSON strings.
	 *
	 * @param mixed $value Raw request value.
	 * @return int[]
	 */
	private function normalize_numeric_id_list( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				$value = array( $value );
			}
		}

		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		$ids = array();
		foreach ( $value as $item ) {
			$id = intval( $item );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/** Create a Quick Restore point before an editor mutation. */
	private function capture_history_before_editor_save( $post_id, $post_type, $blocks_data, $settings_data, $title, $slug, $status ) {
		$post = get_post( $post_id );
		if ( ! $post || ! class_exists( 'DSF_History' ) ) {
			return;
		}
		$meta = array(
			'_dsf_blocks'   => $blocks_data,
			'_dsf_settings' => $settings_data,
		);
		if ( 'dsf_template' === $post_type ) {
			$meta = array(
				'_dsf_template_blocks' => $blocks_data,
				'_dsf_template_theme'  => $settings_data['theme'] ?? array(),
			);
		} elseif ( 'dsf_saved_block' === $post_type ) {
			$first = isset( $blocks_data[0] ) && is_array( $blocks_data[0] ) ? $blocks_data[0] : array();
			$meta = array(
				'_dsf_block_type'     => $first['type'] ?? '',
				'_dsf_block_settings' => $first['settings'] ?? array(),
			);
		}
		$history = DSF_History::get_instance();
		$next    = $history->proposed_post_payload(
			$post_id,
			array(
				'post_title'  => '' !== $title ? $title : $post->post_title,
				'post_name'   => '' !== $slug ? $slug : $post->post_name,
				'post_status' => in_array( $status, array( 'draft', 'publish' ), true ) ? $status : $post->post_status,
				'meta'        => $meta,
			)
		);
		$result = $history->capture_before_post_mutation( $post_id, $post_type, $next, 'editor_save' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => 'Could not create a Quick Restore point.' ), 500 );
		}
	}

	/**
	 * Save page blocks and settings
	 */
	public function save_page() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id       = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$blocks        = isset( $_POST['blocks'] ) ? $_POST['blocks'] : '[]';
		$settings      = isset( $_POST['settings'] ) ? $_POST['settings'] : '{}';
		$html_snapshot = isset( $_POST['html_snapshot'] ) ? wp_unslash( $_POST['html_snapshot'] ) : '';
		$status        = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug          = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$parent_id     = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
		$layout_type   = isset( $_POST['layout_type'] ) ? sanitize_key( wp_unslash( $_POST['layout_type'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
		}

		// Per-object authorization: the general edit_pages gate is not enough — the
		// user must be allowed to edit this specific post (prevents writing Flow
		// data onto posts they do not own / arbitrary IDs).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		// Validate JSON
		$blocks_raw  = wp_unslash( $blocks );
		$blocks_data = json_decode( $blocks_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid blocks JSON data: ' . json_last_error_msg() ) );
		}
		$blocks_data = $this->sanitize_known_block_settings( $blocks_data );

		$settings_raw  = wp_unslash( $settings );
		$settings_data = json_decode( $settings_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid settings JSON data: ' . json_last_error_msg() ) );
		}
		$settings_data           = is_array( $settings_data ) ? $settings_data : array();
		$settings_data['theme']  = $this->sanitize_page_theme_settings( $settings_data['theme'] ?? array() );
		$settings_data['layout'] = $this->sanitize_page_layout_settings( $settings_data['layout'] ?? array() );
		if ( isset( $settings_data['popup'] ) && is_array( $settings_data['popup'] ) ) {
			$settings_data['popup'] = $this->sanitize_popup_settings( $settings_data['popup'] );
		}
		$settings_data['popupId'] = isset( $settings_data['popupId'] ) ? absint( $settings_data['popupId'] ) : 0;
		$settings_data['seo']     = $this->sanitize_page_seo_settings( $settings_data['seo'] ?? array() );

		$post_type = get_post_type( $post_id );
		$this->capture_history_before_editor_save( $post_id, $post_type, $blocks_data, $settings_data, $title, $slug, $status );

		// Saved blocks + templates are edited in Flow but persist to their own
		// meta (not _dsf_blocks). This sends the JSON response and exits.
		if ( in_array( $post_type, array( 'dsf_saved_block', 'dsf_template' ), true ) ) {
			$this->save_flow_library_item( $post_id, $post_type, $blocks_data, $settings_data, $title );
			return;
		}

		// Product templates store blocks/settings like a page, plus the assignment
		// rule, the live toggle, and the editor-only preview product. This sends the
		// JSON response and exits without touching any product's slug/parent/title.
		if ( 'dsf_product_template' === $post_type ) {
			$this->save_product_template_item( $post_id, $blocks_data, $settings_data, $title, $status );
			return;
		}

		// Shop templates mirror product templates: blocks/settings plus the archive
		// assignment rule, live toggle, and editor-only preview category.
		if ( 'dsf_shop_template' === $post_type ) {
			$this->save_shop_template_item( $post_id, $blocks_data, $settings_data, $title, $status );
			return;
		}

		// Blog templates: blocks/settings plus the blog-archive assignment rule,
		// live toggle, and editor-only preview category.
		if ( 'dsf_blog_template' === $post_type ) {
			$this->save_blog_template_item( $post_id, $blocks_data, $settings_data, $title, $status );
			return;
		}

		if ( 'dsf_layout' === $post_type ) {
			$current_layout_type = in_array( $layout_type, array( 'header', 'footer' ), true )
				? $layout_type
				: get_post_meta( $post_id, '_dsf_layout_type', true );
			if ( 'header' === $current_layout_type && count( $blocks_data ) > 1 ) {
				$blocks_data = array_slice( $blocks_data, 0, 1 );
			}
		}

		// Save meta as arrays (avoids JSON escaping issues)
		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );
		if ( '' !== $html_snapshot ) {
			update_post_meta( $post_id, '_dsf_html_snapshot', $this->sanitize_snapshot_html( $html_snapshot ) );
		}

		if ( 'page' === $post_type ) {
			update_post_meta( $post_id, '_dsf_enabled', true );

			// Mirror the SEO noindex switch to a top-level meta flag so the core
			// sitemap can cheaply exclude these pages (the nested seo.noindex in
			// _dsf_settings is not queryable). See DSF_SEO::exclude_noindex_from_sitemap.
			if ( ! empty( $settings_data['seo']['noindex'] ) ) {
				update_post_meta( $post_id, '_dsf_noindex', '1' );
			} else {
				delete_post_meta( $post_id, '_dsf_noindex' );
			}
		}

		if ( 'dsf_layout' === $post_type ) {
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = get_post_meta( $post_id, '_dsf_layout_type', true );
			}
			$layout_type = 'footer' === $layout_type ? 'footer' : 'header';
			update_post_meta( $post_id, '_dsf_layout_type', $layout_type );
		}

		// Update modified time and status (if requested)
		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}

		if ( 'page' === $post_type ) {
			$post_update['post_name']   = '' !== $slug ? $slug : sanitize_title( $title );
			$post_update['post_parent'] = $this->get_valid_page_parent_id( $parent_id, $post_id );
		}

		if ( 'page' === $post_type ) {
			$post_update['post_status'] = 'publish';
		} elseif ( 'draft' === $status ) {
			$post_update['post_status'] = 'draft';
		} elseif ( 'publish' === $status ) {
			$post_update['post_status'] = 'publish';
		}

		wp_update_post( $post_update );

		$post_status = get_post_status( $post_id );
		$post        = get_post( $post_id );
		$post_title  = get_the_title( $post_id );
		$post_type   = $post ? $post->post_type : get_post_type( $post_id );
		$permalink   = 'dsf_layout' !== $post_type ? get_permalink( $post_id ) : '';
		$preview_url = 'dsf_layout' !== $post_type ? get_preview_post_link( $post_id ) : '';

		wp_send_json_success(
			array(
				'message'     => 'Page saved successfully',
				'post_id'     => $post_id,
				'post_status' => $post_status,
				'post_title'  => $post_title,
				'post_name'   => $post ? $post->post_name : '',
				'post_parent' => $post ? (int) $post->post_parent : 0,
				'permalink'   => $permalink,
				'preview_url' => $preview_url,
			)
		);
	}

	/**
	 * Persist a saved block / template edited in the Flow editor back to its own
	 * meta, then send the JSON response. Blocks are already per-type sanitized.
	 */
	private function save_flow_library_item( $post_id, $post_type, $blocks_data, $settings_data, $title ) {
		if ( 'dsf_template' === $post_type ) {
			$tpl_blocks = array();
			foreach ( (array) $blocks_data as $block ) {
				if ( ! is_array( $block ) || empty( $block['type'] ) ) {
					continue;
				}
				$tpl_blocks[] = array(
					'type'     => sanitize_key( $block['type'] ),
					'settings' => ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array(),
				);
			}
			update_post_meta( $post_id, '_dsf_template_blocks', $tpl_blocks );
			$theme = ( isset( $settings_data['theme'] ) && is_array( $settings_data['theme'] ) ) ? $settings_data['theme'] : array();
			update_post_meta( $post_id, '_dsf_template_theme', $theme );
			$kind = get_post_meta( $post_id, '_dsf_template_kind', true );
			update_post_meta( $post_id, '_dsf_template_kind', $kind ? $kind : 'page' );
		} else { // dsf_saved_block — persist the first block only.
			$first = ( isset( $blocks_data[0] ) && is_array( $blocks_data[0] ) ) ? $blocks_data[0] : null;
			if ( $first && ! empty( $first['type'] ) ) {
				update_post_meta( $post_id, '_dsf_block_type', sanitize_key( $first['type'] ) );
				update_post_meta( $post_id, '_dsf_block_settings', ( isset( $first['settings'] ) && is_array( $first['settings'] ) ) ? $first['settings'] : array() );
			}
		}

		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}
		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message'     => 'Saved successfully',
				'post_id'     => $post_id,
				'post_status' => get_post_status( $post_id ),
				'post_title'  => get_the_title( $post_id ),
				'post_name'   => '',
				'post_parent' => 0,
				'permalink'   => '',
				'preview_url' => '',
			)
		);
	}

	/**
	 * Persist a product template edited in the Flow editor.
	 *
	 * Stores blocks/settings to the same meta a page uses, plus the product-template
	 * configuration (assignment rule, live toggle, editor-only preview product). It
	 * never touches any product's own post fields.
	 *
	 * @param int    $post_id       Product template post ID.
	 * @param array  $blocks_data   Sanitized blocks.
	 * @param array  $settings_data Sanitized page settings (may carry a productTemplate key).
	 * @param string $title         Submitted title.
	 * @param string $status        Requested status ('draft' | 'publish' | '').
	 */
	private function save_product_template_item( $post_id, $blocks_data, $settings_data, $title, $status ) {
		$config = ( isset( $settings_data['productTemplate'] ) && is_array( $settings_data['productTemplate'] ) )
			? $settings_data['productTemplate']
			: array();

		$assignment = DSF_Product_Templates::sanitize_assignment( $config['assignment'] ?? array() );
		$active     = empty( $config['active'] ) ? '' : '1';
		$preview_id = isset( $config['previewProduct'] ) ? absint( $config['previewProduct'] ) : 0;
		if ( $preview_id && 'product' !== get_post_type( $preview_id ) ) {
			$preview_id = 0;
		}

		update_post_meta( $post_id, '_dsf_pt_assignment', $assignment );
		update_post_meta( $post_id, '_dsf_pt_active', $active );
		update_post_meta( $post_id, '_dsf_pt_preview_product', $preview_id );

		// The product-template config is editor transport only — keep it out of the
		// stored page settings (theme/layout/popup remain).
		unset( $settings_data['productTemplate'] );

		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );

		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}
		if ( 'draft' === $status ) {
			$post_update['post_status'] = 'draft';
		} elseif ( 'publish' === $status ) {
			$post_update['post_status'] = 'publish';
		}
		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message'     => 'Product template saved',
				'post_id'     => $post_id,
				'post_status' => get_post_status( $post_id ),
				'post_title'  => get_the_title( $post_id ),
				'post_name'   => '',
				'post_parent' => 0,
				'permalink'   => '',
				'preview_url' => '',
			)
		);
	}

	/**
	 * Persist a shop template edited in the Flow editor.
	 *
	 * Stores blocks/settings to the same meta a page uses, plus the shop-template
	 * configuration (archive assignment rule, live toggle, editor-only preview
	 * category). It never touches any shop page or term.
	 *
	 * @param int    $post_id       Shop template post ID.
	 * @param array  $blocks_data   Sanitized blocks.
	 * @param array  $settings_data Sanitized page settings (may carry a shopTemplate key).
	 * @param string $title         Submitted title.
	 * @param string $status        Requested status ('draft' | 'publish' | '').
	 */
	private function save_shop_template_item( $post_id, $blocks_data, $settings_data, $title, $status ) {
		$config = ( isset( $settings_data['shopTemplate'] ) && is_array( $settings_data['shopTemplate'] ) )
			? $settings_data['shopTemplate']
			: array();

		$assignment = DSF_Shop_Templates::sanitize_assignment( $config['assignment'] ?? array() );
		$active     = empty( $config['active'] ) ? '' : '1';
		$term_id    = isset( $config['previewTerm'] ) ? absint( $config['previewTerm'] ) : 0;
		if ( $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				$term_id = 0;
			}
		}

		update_post_meta( $post_id, '_dsf_st_assignment', $assignment );
		update_post_meta( $post_id, '_dsf_st_active', $active );
		update_post_meta( $post_id, '_dsf_st_preview_term', $term_id );

		// The shop-template config is editor transport only — keep it out of the
		// stored page settings (theme/layout/popup remain).
		unset( $settings_data['shopTemplate'] );

		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );

		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}
		if ( 'draft' === $status ) {
			$post_update['post_status'] = 'draft';
		} elseif ( 'publish' === $status ) {
			$post_update['post_status'] = 'publish';
		}
		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message'     => 'Shop template saved',
				'post_id'     => $post_id,
				'post_status' => get_post_status( $post_id ),
				'post_title'  => get_the_title( $post_id ),
				'post_name'   => '',
				'post_parent' => 0,
				'permalink'   => '',
				'preview_url' => '',
			)
		);
	}

	/**
	 * Persist a blog template edited in the Flow editor.
	 *
	 * Stores blocks/settings to the same meta a page uses, plus the blog-template
	 * configuration (archive assignment rule, live toggle, editor-only preview
	 * category). It never touches any post or term.
	 *
	 * @param int    $post_id       Blog template post ID.
	 * @param array  $blocks_data   Sanitized blocks.
	 * @param array  $settings_data Sanitized page settings (may carry a blogTemplate key).
	 * @param string $title         Submitted title.
	 * @param string $status        Requested status ('draft' | 'publish' | '').
	 */
	private function save_blog_template_item( $post_id, $blocks_data, $settings_data, $title, $status ) {
		$config = ( isset( $settings_data['blogTemplate'] ) && is_array( $settings_data['blogTemplate'] ) )
			? $settings_data['blogTemplate']
			: array();

		$assignment = DSF_Blog_Templates::sanitize_assignment( $config['assignment'] ?? array() );
		$active     = empty( $config['active'] ) ? '' : '1';
		$term_id    = isset( $config['previewTerm'] ) ? absint( $config['previewTerm'] ) : 0;
		if ( $term_id ) {
			$term = get_term( $term_id, 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				$term_id = 0;
			}
		}

		update_post_meta( $post_id, '_dsf_bt_assignment', $assignment );
		update_post_meta( $post_id, '_dsf_bt_active', $active );
		update_post_meta( $post_id, '_dsf_bt_preview_term', $term_id );

		// The blog-template config is editor transport only — keep it out of the
		// stored page settings (theme/layout/popup remain).
		unset( $settings_data['blogTemplate'] );

		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );

		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}
		if ( 'draft' === $status ) {
			$post_update['post_status'] = 'draft';
		} elseif ( 'publish' === $status ) {
			$post_update['post_status'] = 'publish';
		}
		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message'     => 'Blog template saved',
				'post_id'     => $post_id,
				'post_status' => get_post_status( $post_id ),
				'post_title'  => get_the_title( $post_id ),
				'post_name'   => '',
				'post_parent' => 0,
				'permalink'   => '',
				'preview_url' => '',
			)
		);
	}

	/**
	 * Sanitize settings for blocks with dedicated save-time contracts.
	 *
	 * @param array $blocks Saved page blocks.
	 * @return array
	 */
	private function sanitize_known_block_settings( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		foreach ( $blocks as &$block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			// Optional editor-only custom label (shown in the structure panel so a
			// page full of "Content" blocks stays legible). Never output on the
			// frontend, but sanitize + length-cap it since it is stored.
			if ( isset( $block['label'] ) ) {
				$label = sanitize_text_field( (string) $block['label'] );
				if ( '' === $label ) {
					unset( $block['label'] );
				} else {
					$block['label'] = mb_substr( $label, 0, 80 );
				}
			}
			// Optional user "HTML anchor" so links like #pricing scroll to the
			// block. Stored block-level (rendered as the frontend wrapper id), so
			// normalize to a safe id here regardless of the block type.
			if ( isset( $block['anchorId'] ) ) {
				$anchor = self::sanitize_anchor_id( (string) $block['anchorId'] );
				if ( '' === $anchor ) {
					unset( $block['anchorId'] );
				} else {
					$block['anchorId'] = $anchor;
				}
			}
			if ( in_array( $block['type'] ?? '', array( 'landing-progress-header', 'landing-dock-header', 'landing-hero', 'landing-showcase-hero', 'landing-block-explorer', 'landing-block-ready', 'landing-product-story', 'landing-trust-workflow', 'landing-engagement-suite', 'landing-marketing-footer' ), true ) ) {
				$block['settings'] = $this->sanitize_landing_block_settings( $block['type'], $block['settings'] ?? array() );
				continue;
			}
			if ( 'faq' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_faq_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'pricing-tables' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_pricing_tables_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'breadcrumbs' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_breadcrumbs_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'content' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_content_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'text-image' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_text_image_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'countdown' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_countdown_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'expander-hero' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_expander_hero_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'card-columns' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_card_columns_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-summary' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_summary_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-gallery' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_gallery_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-description' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_description_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-specs' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_specs_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-tabs' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_tabs_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-add-to-cart' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_add_to_cart_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( in_array( $block['type'] ?? '', array( 'product-hero', 'product-details-split' ), true ) ) {
				$block['settings'] = $this->sanitize_product_hero_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-highlights' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_highlights_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-related' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_related_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-spotlight' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_spotlight_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-upsells' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_upsells_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-reviews' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_reviews_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'product-meta' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_product_meta_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( in_array( $block['type'] ?? '', array( 'store-cart', 'store-checkout', 'store-account', 'store-login' ), true ) ) {
				$block['settings'] = $this->sanitize_store_fragment_settings( $block['type'], $block['settings'] ?? array() );
				continue;
			}
			if ( 'store-steps' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_store_steps_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'shop-header' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_shop_header_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'shop-category-hero' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_shop_category_hero_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'shop-subcategory-grid' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_shop_subcategory_grid_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'shop-products' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_shop_products_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'shop-filters' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_shop_filters_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'blog-header' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_blog_header_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'post-loop' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_post_loop_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'store-mini-cart' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_store_mini_cart_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'store-thankyou' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_store_thankyou_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'site-login' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_site_login_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'site-search' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_site_search_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'user-dashboard' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_user_dashboard_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( in_array( $block['type'] ?? '', array( 'form-embed', 'form-with-content' ), true ) ) {
				$block['settings'] = $this->sanitize_form_block_settings( $block['type'], $block['settings'] ?? array() );
				continue;
			}
			if ( 'header-modern-mega' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_modern_mega_header_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'footer-commerce' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_footer_commerce_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'header-showcase-mega' !== ( $block['type'] ?? '' ) ) {
				// No built-in sanitizer matched this type. Add-on blocks registered
				// via `dsf_register_blocks` land here: give them a hook to sanitize
				// their own settings. The default is a pass-through so built-in
				// behaviour is unchanged; add-ons typically return
				// DSF_Ajax::sanitize_block_settings_by_schema( $settings, $type ).
				$type = $block['type'] ?? '';
				if ( '' !== $type ) {
					/**
					 * Filter an add-on block's settings before they are stored.
					 *
					 * @param array  $settings Raw settings from the client.
					 * @param string $type     Block type (id).
					 */
					$block['settings'] = apply_filters(
						'dsf_sanitize_block_settings',
						isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array(),
						$type
					);
				}
				continue;
			}
			$settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$text_keys = array( 'promoText', 'logoText', 'logoAlt', 'specialButtonText', 'mobileLocationsLabel', 'mobileCallLabel' );
			$url_keys  = array( 'promoUrl', 'homeUrl', 'specialButtonUrl', 'searchUrl' );
			foreach ( $text_keys as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$settings[ $key ] = sanitize_text_field( $settings[ $key ] );
				}
			}
			foreach ( $url_keys as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$settings[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] );
				}
			}
			foreach ( array( 'utilityBackground', 'utilityTextColor', 'navBackground', 'navTextColor', 'accentColor', 'panelBackground', 'panelTextColor', 'mobileBackground', 'mobileTextColor' ) as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$color             = sanitize_hex_color( $settings[ $key ] );
					$settings[ $key ] = $color ? $color : '';
				}
			}
			if ( isset( $settings['logoWidth'] ) ) {
				$settings['logoWidth'] = max( 80, min( 380, absint( $settings['logoWidth'] ) ) );
			}
			if ( isset( $settings['mobileShowSearch'] ) ) {
				$settings['mobileShowSearch'] = (bool) $settings['mobileShowSearch'];
			}
			if ( isset( $settings['logoImage'] ) ) {
				$settings['logoImage'] = esc_url_raw( $settings['logoImage'], array( 'http', 'https' ) );
			}
			if ( isset( $settings['navigation'] ) ) {
				$settings['navigation'] = $this->sanitize_showcase_navigation( $settings['navigation'] );
			}
			$block['settings'] = $settings;
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Sanitize Content block settings.
	 *
	 * The `content` field intentionally allows raw HTML (an edit_pages-gated,
	 * trusted authoring surface with allowRawHtml in its schema), so it is left
	 * as-is; only the presentation fields — the optional full-bleed background
	 * color and the numeric widths/paddings — are sanitized/clamped in place.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_content_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		if ( array_key_exists( 'backgroundColor', $settings ) ) {
			$settings['backgroundColor'] = $this->sanitize_card_columns_color( $settings['backgroundColor'] );
		}
		if ( array_key_exists( 'maxWidth', $settings ) ) {
			$settings['maxWidth'] = max( 320, min( 1400, (int) $settings['maxWidth'] ) );
		}
		if ( array_key_exists( 'padding', $settings ) ) {
			$settings['padding'] = max( 0, min( 200, (int) $settings['padding'] ) );
		}
		if ( array_key_exists( 'paddingX', $settings ) ) {
			$settings['paddingX'] = max( 0, min( 200, (int) $settings['paddingX'] ) );
		}

		return $settings;
	}

	/**
	 * Sanitize Breadcrumbs block settings. Presentation-only — the trail itself
	 * is built server-side from the real page hierarchy at render time.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_breadcrumbs_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$separator = isset( $settings['separator'] ) ? sanitize_key( $settings['separator'] ) : 'chevron';
		if ( ! in_array( $separator, array( 'chevron', 'slash', 'dot', 'arrow' ), true ) ) {
			$separator = 'chevron';
		}
		$align = isset( $settings['align'] ) ? sanitize_key( $settings['align'] ) : 'left';
		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'left';
		}

		$clamp = static function ( $value, $min, $max, $default ) {
			$value = is_numeric( $value ) ? (int) $value : $default;
			return max( $min, min( $max, $value ) );
		};

		return array(
			'homeLabel'   => mb_substr( sanitize_text_field( $settings['homeLabel'] ?? 'Home' ), 0, 40 ),
			'separator'   => $separator,
			'showCurrent' => ! empty( $settings['showCurrent'] ),
			'align'       => $align,
			'textColor'   => sanitize_hex_color( $settings['textColor'] ?? '' ) ? sanitize_hex_color( $settings['textColor'] ) : '',
			'linkColor'   => sanitize_hex_color( $settings['linkColor'] ?? '' ) ? sanitize_hex_color( $settings['linkColor'] ) : '',
			'fontSize'    => $clamp( $settings['fontSize'] ?? 14, 11, 20, 14 ),
			'maxWidth'    => $clamp( $settings['maxWidth'] ?? 1100, 480, 1400, 1100 ),
			'paddingY'    => $clamp( $settings['paddingY'] ?? 16, 0, 80, 16 ),
			'paddingX'    => $clamp( $settings['paddingX'] ?? 24, 0, 120, 24 ),
		);
	}

	/**
	 * Normalize a user-typed block anchor into a safe HTML id.
	 *
	 * Mirrors the editor's normalizeAnchorId() so the stored id matches what the
	 * author saw: lowercase, spaces/underscores → hyphen, only [a-z0-9-] kept,
	 * collapsed/trimmed hyphens, a leading digit prefixed, and length-capped.
	 * Returns '' when nothing usable remains.
	 *
	 * @param string $raw Raw anchor value.
	 * @return string
	 */
	public static function sanitize_anchor_id( $raw ) {
		$id = strtolower( trim( (string) $raw ) );
		$id = preg_replace( '/[\s_]+/', '-', $id );
		$id = preg_replace( '/[^a-z0-9-]/', '', (string) $id );
		$id = preg_replace( '/-+/', '-', (string) $id );
		$id = trim( (string) $id, '-' );
		if ( '' === $id ) {
			return '';
		}
		if ( preg_match( '/^[0-9]/', $id ) ) {
			$id = 's-' . $id;
		}
		return substr( $id, 0, 80 );
	}

	/**
	 * Sanitize an add-on block's settings using its registered schema.
	 *
	 * A convenience for `dsf_sanitize_block_settings` callbacks: each value is
	 * sanitized according to the control `type` declared in the block's `settings`
	 * schema, and any key not present in the schema is dropped. This is the safe
	 * default for third-party blocks — an add-on that needs custom handling can
	 * ignore it and sanitize the array itself.
	 *
	 * @param array  $settings Raw settings from the client.
	 * @param string $type     Block type (id).
	 * @return array Sanitized settings limited to schema keys.
	 */
	public static function sanitize_block_settings_by_schema( $settings, $type ) {
		if ( ! is_array( $settings ) || ! class_exists( 'DSF_Blocks' ) ) {
			return array();
		}

		$block  = DSF_Blocks::get_instance()->get_block( $type );
		$schema = ( is_array( $block ) && isset( $block['settings'] ) && is_array( $block['settings'] ) )
			? $block['settings']
			: array();
		if ( empty( $schema ) ) {
			return array();
		}

		$clean = array();
		foreach ( $schema as $key => $control ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}
			$control_type  = is_array( $control ) && isset( $control['type'] ) ? $control['type'] : 'text';
			$clean[ $key ] = self::sanitize_schema_value( $settings[ $key ], $control_type, is_array( $control ) ? $control : array() );
		}

		return $clean;
	}

	/** Sanitize the bounded three-card Modern Pricing Tables block. */
	private function sanitize_pricing_tables_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$plans = array();
		$raw_plans = is_array( $settings['plans'] ?? null ) ? array_slice( $settings['plans'], 0, 3 ) : array();
		foreach ( $raw_plans as $plan ) {
			if ( ! is_array( $plan ) ) {
				continue;
			}
			$features = is_array( $plan['features'] ?? null ) ? $plan['features'] : preg_split( '/\r?\n/', (string) ( $plan['features'] ?? '' ) );
			$features = is_array( $features ) ? array_slice( $features, 0, 12 ) : array();
			$features = array_values( array_filter( array_map( static function ( $feature ) { return mb_substr( sanitize_text_field( (string) $feature ), 0, 160 ); }, $features ) ) );
			$plans[] = array(
				'name'         => mb_substr( sanitize_text_field( $plan['name'] ?? '' ), 0, 80 ),
				'description'  => mb_substr( sanitize_text_field( $plan['description'] ?? '' ), 0, 240 ),
				'monthlyPrice' => mb_substr( sanitize_text_field( $plan['monthlyPrice'] ?? '' ), 0, 32 ),
				'pricePrefix'  => mb_substr( sanitize_text_field( $plan['pricePrefix'] ?? '' ), 0, 8 ),
				'priceSuffix'  => mb_substr( sanitize_text_field( $plan['priceSuffix'] ?? '' ), 0, 32 ),
				'buttonText'   => mb_substr( sanitize_text_field( $plan['buttonText'] ?? '' ), 0, 80 ),
				'buttonUrl'    => esc_url_raw( $plan['buttonUrl'] ?? '' ),
				'popular'      => ! empty( $plan['popular'] ),
				'badgeText'    => mb_substr( sanitize_text_field( $plan['badgeText'] ?? '' ), 0, 80 ),
				'features'     => $features,
			);
		}
		$defaults = array( 'Starter', 'Growth', 'Scale' );
		while ( count( $plans ) < 3 ) {
			$plans[] = array( 'name' => $defaults[ count( $plans ) ], 'description' => '', 'monthlyPrice' => '', 'pricePrefix' => '', 'priceSuffix' => '', 'buttonText' => '', 'buttonUrl' => '', 'popular' => false, 'badgeText' => '', 'features' => array() );
		}
		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );
		$background = sanitize_hex_color( $settings['backgroundColor'] ?? '' );
		return array(
			'eyebrow' => mb_substr( sanitize_text_field( $settings['eyebrow'] ?? 'Plans for every stage' ), 0, 100 ),
			'title' => mb_substr( sanitize_text_field( $settings['title'] ?? 'Straightforward pricing' ), 0, 160 ),
			'description' => mb_substr( sanitize_textarea_field( $settings['description'] ?? '' ), 0, 400 ),
			'plans' => $plans,
			'accentColor' => $accent ? $accent : '#5B3DF5',
			'backgroundColor' => $background ? $background : '#F7F7FC',
			'maxWidth' => max( 760, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding' => max( 0, min( 180, absint( $settings['padding'] ?? 80 ) ) ),
		);
	}

	/**
	 * Sanitize a single settings value by its declared control type.
	 *
	 * @param mixed  $value        Raw value.
	 * @param string $control_type Control type from the schema.
	 * @param array  $control      Full control schema (for min/max/options).
	 * @return mixed
	 */
	private static function sanitize_schema_value( $value, $control_type, $control ) {
		switch ( $control_type ) {
			case 'wysiwyg':
				return wp_kses_post( is_string( $value ) ? $value : '' );

			case 'color':
				$color = sanitize_hex_color( is_string( $value ) ? $value : '' );
				return $color ? $color : '';

			case 'url':
			case 'link':
				return esc_url_raw( is_string( $value ) ? $value : '', array( 'http', 'https', 'mailto', 'tel' ) );

			case 'image':
				return esc_url_raw( is_string( $value ) ? $value : '', array( 'http', 'https' ) );

			case 'number':
			case 'slider':
				$num = is_numeric( $value ) ? $value + 0 : 0;
				if ( isset( $control['min'] ) && is_numeric( $control['min'] ) ) {
					$num = max( $control['min'] + 0, $num );
				}
				if ( isset( $control['max'] ) && is_numeric( $control['max'] ) ) {
					$num = min( $control['max'] + 0, $num );
				}
				return $num;

			case 'toggle':
			case 'checkbox':
			case 'boolean':
				return (bool) $value;

			case 'select':
				$value   = is_scalar( $value ) ? (string) $value : '';
				$options = isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array();
				if ( ! empty( $options ) ) {
					// Options may be a flat list or a value => label map.
					$allowed = array_keys( $options ) === range( 0, count( $options ) - 1 )
						? array_map( 'strval', $options )
						: array_map( 'strval', array_keys( $options ) );
					if ( ! in_array( $value, $allowed, true ) ) {
						return '';
					}
				}
				return sanitize_text_field( $value );

			case 'textarea':
				return sanitize_textarea_field( is_string( $value ) ? $value : '' );

			default:
				if ( is_array( $value ) ) {
					// Repeater/group: sanitize each leaf as plain text. Add-ons
					// needing richer nested handling should sanitize themselves.
					return map_deep( $value, 'sanitize_text_field' );
				}
				return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		}
	}

	/**
	 * Sanitize the Product Summary block settings.
	 *
	 * Only presentation options are saved — the product data itself is read live
	 * from the current product at render time and is never trusted from the client.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_summary_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showTitle'            => ! isset( $settings['showTitle'] ) || ! empty( $settings['showTitle'] ),
			'headingTag'           => in_array( $settings['headingTag'] ?? '', array( 'h1', 'h2' ), true ) ? $settings['headingTag'] : 'h1',
			'showPrice'            => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'showShortDescription' => ! isset( $settings['showShortDescription'] ) || ! empty( $settings['showShortDescription'] ),
			'showSku'              => ! empty( $settings['showSku'] ),
			'showStock'            => ! isset( $settings['showStock'] ) || ! empty( $settings['showStock'] ),
			'showRating'           => ! isset( $settings['showRating'] ) || ! empty( $settings['showRating'] ),
			'alignment'            => in_array( $settings['alignment'] ?? '', array( 'left', 'center' ), true ) ? $settings['alignment'] : 'left',
			'maxWidth'             => max( 320, min( 1200, absint( $settings['maxWidth'] ?? 640 ) ) ),
			'padding'              => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'             => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'              => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'titleColor', 'priceColor', 'textColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values                             = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array();
			if ( isset( $values['padding'] ) ) {
				$clean['responsive'][ $breakpoint ]['padding'] = max( 0, min( 160, absint( $values['padding'] ) ) );
			}
			if ( isset( $values['paddingX'] ) ) {
				$clean['responsive'][ $breakpoint ]['paddingX'] = max( 0, min( 120, absint( $values['paddingX'] ) ) );
			}
			if ( isset( $values['marginY'] ) ) {
				$clean['responsive'][ $breakpoint ]['marginY'] = max( 0, min( 100, absint( $values['marginY'] ) ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize the Add to Cart block settings (presentation only — the cart form
	 * itself is rendered server-side from the current product).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_add_to_cart_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'alignment'  => in_array( $settings['alignment'] ?? '', array( 'left', 'center' ), true ) ? $settings['alignment'] : 'left',
			'showPrice'  => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'maxWidth'   => max( 280, min( 900, absint( $settings['maxWidth'] ?? 460 ) ) ),
			'padding'    => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'   => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'    => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive' => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'priceColor', 'buttonColor', 'buttonTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Hero block settings (presentation only — product data
	 * and the cart form are rendered server-side from the current product).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_hero_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'imageSide'            => 'right' === ( $settings['imageSide'] ?? '' ) ? 'right' : 'left',
			'eyebrowText'          => sanitize_text_field( $settings['eyebrowText'] ?? '' ),
			'showRating'           => ! isset( $settings['showRating'] ) || ! empty( $settings['showRating'] ),
			'showPrice'            => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'showShortDescription' => ! isset( $settings['showShortDescription'] ) || ! empty( $settings['showShortDescription'] ),
			'showStock'            => ! isset( $settings['showStock'] ) || ! empty( $settings['showStock'] ),
			'showSku'              => ! empty( $settings['showSku'] ),
			'showAddToCart'        => ! isset( $settings['showAddToCart'] ) || ! empty( $settings['showAddToCart'] ),
			'showSaleBadge'        => ! isset( $settings['showSaleBadge'] ) || ! empty( $settings['showSaleBadge'] ),
			'saleBadgeText'        => sanitize_text_field( $settings['saleBadgeText'] ?? 'Sale' ),
			'maxWidth'             => max( 720, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'              => max( 0, min( 160, absint( $settings['padding'] ?? 48 ) ) ),
			'paddingX'             => max( 0, min( 120, absint( $settings['paddingX'] ?? 24 ) ) ),
			'marginY'              => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'           => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'titleColor', 'priceColor', 'buttonColor', 'buttonTextColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Highlights block settings. Items reuse the landing
	 * icon-item contract (allowlisted icon, plain-text title/description, cap 8).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_highlights_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'items'      => $this->sanitize_landing_icon_items( $settings['items'] ?? array() ),
			'layout'     => 'grid' === ( $settings['layout'] ?? '' ) ? 'grid' : 'row',
			'columns'    => max( 2, min( 4, absint( $settings['columns'] ?? 3 ) ) ),
			'cardStyle'  => ! isset( $settings['cardStyle'] ) || ! empty( $settings['cardStyle'] ),
			'maxWidth'   => max( 480, min( 1400, absint( $settings['maxWidth'] ?? 1100 ) ) ),
			'padding'    => max( 0, min( 160, absint( $settings['padding'] ?? 24 ) ) ),
			'paddingX'   => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'    => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive' => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Related Products block settings (the product cards themselves
	 * come from the server at render time, never from the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_related_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showHeading' => ! isset( $settings['showHeading'] ) || ! empty( $settings['showHeading'] ),
			'headingText' => sanitize_text_field( $settings['headingText'] ?? 'You may also like' ),
			'count'       => max( 2, min( 8, absint( $settings['count'] ?? 4 ) ) ),
			'columns'     => max( 2, min( 4, absint( $settings['columns'] ?? 4 ) ) ),
			'showPrice'   => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'maxWidth'    => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 40 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);
		foreach ( array( 'headingColor', 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Spotlight block settings (presentation only — product
	 * data and the cart form are rendered server-side from the current product).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_spotlight_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'imageSide'            => 'right' === ( $settings['imageSide'] ?? '' ) ? 'right' : 'left',
			'backdrop'             => 'none' === ( $settings['backdrop'] ?? '' ) ? 'none' : 'soft',
			'eyebrowText'          => sanitize_text_field( $settings['eyebrowText'] ?? '' ),
			'showRating'           => ! isset( $settings['showRating'] ) || ! empty( $settings['showRating'] ),
			'showShortDescription' => ! isset( $settings['showShortDescription'] ) || ! empty( $settings['showShortDescription'] ),
			'showStock'            => ! isset( $settings['showStock'] ) || ! empty( $settings['showStock'] ),
			'showSku'              => ! empty( $settings['showSku'] ),
			'showAddToCart'        => ! isset( $settings['showAddToCart'] ) || ! empty( $settings['showAddToCart'] ),
			'showSaleBadge'        => ! isset( $settings['showSaleBadge'] ) || ! empty( $settings['showSaleBadge'] ),
			'saleBadgeText'        => sanitize_text_field( $settings['saleBadgeText'] ?? 'Sale' ),
			'maxWidth'             => max( 720, min( 1600, absint( $settings['maxWidth'] ?? 1240 ) ) ),
			'padding'              => max( 0, min( 160, absint( $settings['padding'] ?? 56 ) ) ),
			'paddingX'             => max( 0, min( 120, absint( $settings['paddingX'] ?? 24 ) ) ),
			'marginY'              => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'           => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'titleColor', 'priceColor', 'buttonColor', 'buttonTextColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Upsells block settings (the upsell cards themselves
	 * come from the server at render time, never from the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_upsells_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showHeading' => ! isset( $settings['showHeading'] ) || ! empty( $settings['showHeading'] ),
			'headingText' => sanitize_text_field( $settings['headingText'] ?? 'Pairs well with' ),
			'count'       => max( 2, min( 8, absint( $settings['count'] ?? 4 ) ) ),
			'columns'     => max( 2, min( 4, absint( $settings['columns'] ?? 4 ) ) ),
			'showPrice'   => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'maxWidth'    => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 40 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'headingColor', 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Reviews block settings (presentation only — the reviews
	 * themselves are WooCommerce's own template captured and sanitized server-side).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_reviews_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showHeading' => ! isset( $settings['showHeading'] ) || ! empty( $settings['showHeading'] ),
			'headingText' => sanitize_text_field( $settings['headingText'] ?? 'Customer Reviews' ),
			'showSummary' => ! isset( $settings['showSummary'] ) || ! empty( $settings['showSummary'] ),
			'maxWidth'    => max( 320, min( 1400, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'headingColor', 'accentColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Meta block settings (presentation only — SKU, category,
	 * and tag values are read live from the current product at render time).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_meta_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showSku'        => ! isset( $settings['showSku'] ) || ! empty( $settings['showSku'] ),
			'showCategories' => ! isset( $settings['showCategories'] ) || ! empty( $settings['showCategories'] ),
			'showTags'       => ! isset( $settings['showTags'] ) || ! empty( $settings['showTags'] ),
			'layout'         => 'inline' === ( $settings['layout'] ?? '' ) ? 'inline' : 'stacked',
			'alignment'      => in_array( $settings['alignment'] ?? '', array( 'left', 'center' ), true ) ? $settings['alignment'] : 'left',
			'maxWidth'       => max( 320, min( 1200, absint( $settings['maxWidth'] ?? 640 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'labelColor', 'linkColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the page/template SEO settings (title, description, social image,
	 * canonical, noindex). Variables like {title} are plain-text tokens resolved
	 * server-side at render time (DSF_SEO), never client HTML.
	 *
	 * @param mixed $seo Submitted SEO settings.
	 * @return array
	 */
	private function sanitize_page_seo_settings( $seo ) {
		$seo = is_array( $seo ) ? $seo : array();

		$canonical = isset( $seo['canonical'] ) ? esc_url_raw( trim( (string) $seo['canonical'] ) ) : '';
		if ( $canonical && ! preg_match( '#^https?://#i', $canonical ) ) {
			$canonical = '';
		}

		return array(
			'title'       => mb_substr( sanitize_text_field( $seo['title'] ?? '' ), 0, 200 ),
			'description' => mb_substr( sanitize_text_field( $seo['description'] ?? '' ), 0, 300 ),
			'socialImage' => esc_url_raw( (string) ( $seo['socialImage'] ?? '' ) ),
			'canonical'   => $canonical,
			'noindex'     => ! empty( $seo['noindex'] ),
			'nofollow'    => ! empty( $seo['nofollow'] ),
		);
	}

	/**
	 * Sanitize the Blog Header block settings (presentation only — the archive
	 * title/description are read live from the viewed archive at render time).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_blog_header_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showTitle'       => ! isset( $settings['showTitle'] ) || ! empty( $settings['showTitle'] ),
			'showDescription' => ! isset( $settings['showDescription'] ) || ! empty( $settings['showDescription'] ),
			'showCount'       => ! isset( $settings['showCount'] ) || ! empty( $settings['showCount'] ),
			'alignment'       => in_array( $settings['alignment'] ?? '', array( 'left', 'center' ), true ) ? $settings['alignment'] : 'left',
			'maxWidth'        => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1100 ) ) ),
			'padding'         => max( 0, min( 160, absint( $settings['padding'] ?? 40 ) ) ),
			'paddingX'        => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'         => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'      => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'titleColor', 'textColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Post Loop block settings (the post cards and pagination come
	 * from the server-built archive context, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_post_loop_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$layouts  = array( 'grid', 'list' );

		$clean = array(
			'layout'          => in_array( $settings['layout'] ?? '', $layouts, true ) ? $settings['layout'] : 'grid',
			'columns'         => max( 1, min( 4, absint( $settings['columns'] ?? 3 ) ) ),
			'featuredFirst'   => ! isset( $settings['featuredFirst'] ) || ! empty( $settings['featuredFirst'] ),
			'showImage'       => ! isset( $settings['showImage'] ) || ! empty( $settings['showImage'] ),
			'showExcerpt'     => ! isset( $settings['showExcerpt'] ) || ! empty( $settings['showExcerpt'] ),
			'showDate'        => ! isset( $settings['showDate'] ) || ! empty( $settings['showDate'] ),
			'showAuthor'      => ! isset( $settings['showAuthor'] ) || ! empty( $settings['showAuthor'] ),
			'showCategories'  => ! isset( $settings['showCategories'] ) || ! empty( $settings['showCategories'] ),
			'showReadingTime' => ! isset( $settings['showReadingTime'] ) || ! empty( $settings['showReadingTime'] ),
			'showPagination'  => ! isset( $settings['showPagination'] ) || ! empty( $settings['showPagination'] ),
			'readMoreText'    => sanitize_text_field( $settings['readMoreText'] ?? 'Read article' ),
			'maxWidth'        => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'         => max( 0, min( 160, absint( $settings['padding'] ?? 24 ) ) ),
			'paddingX'        => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'         => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'      => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'cardBackground' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Filters block settings (filter values and category
	 * links come from the server-built archive context, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_shop_filters_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );

		return array(
			'showPrice'      => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'showCategories' => ! isset( $settings['showCategories'] ) || ! empty( $settings['showCategories'] ),
			'showCounts'     => ! isset( $settings['showCounts'] ) || ! empty( $settings['showCounts'] ),
			'layout'         => 'panel' === ( $settings['layout'] ?? '' ) ? 'panel' : 'bar',
			'accentColor'    => $accent ? $accent : '',
			'maxWidth'       => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 12 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	/**
	 * Sanitize the Mini Cart block settings (live counts come from WooCommerce's
	 * cart session and fragments, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_store_mini_cart_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'placement'     => 'inline' === ( $settings['placement'] ?? '' ) ? 'inline' : 'floating',
			'corner'        => 'bottom-left' === ( $settings['corner'] ?? '' ) ? 'bottom-left' : 'bottom-right',
			'showSubtotal'  => ! isset( $settings['showSubtotal'] ) || ! empty( $settings['showSubtotal'] ),
			'hideWhenEmpty' => ! isset( $settings['hideWhenEmpty'] ) || ! empty( $settings['hideWhenEmpty'] ),
			'marginY'       => max( 0, min( 100, absint( $settings['marginY'] ?? 0 ) ) ),
			'paddingX'      => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'responsive'    => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'buttonColor', 'buttonTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Thank You Banner block settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_store_thankyou_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'headingText'  => sanitize_text_field( $settings['headingText'] ?? 'Thank you for your order!' ),
			'messageText'  => sanitize_textarea_field( $settings['messageText'] ?? '' ),
			'showConfetti' => ! isset( $settings['showConfetti'] ) || ! empty( $settings['showConfetti'] ),
			'maxWidth'     => max( 480, min( 1400, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'      => max( 0, min( 160, absint( $settings['padding'] ?? 40 ) ) ),
			'paddingX'     => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'      => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'   => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Login block settings (the form posts to wp-login.php; endpoints
	 * and redirects come from the server-built site context, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_site_login_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'headingText'    => sanitize_text_field( $settings['headingText'] ?? 'Welcome back' ),
			'subheadingText' => sanitize_text_field( $settings['subheadingText'] ?? '' ),
			'showRemember'   => ! isset( $settings['showRemember'] ) || ! empty( $settings['showRemember'] ),
			'showLinks'      => ! isset( $settings['showLinks'] ) || ! empty( $settings['showLinks'] ),
			'maxWidth'       => max( 320, min( 720, absint( $settings['maxWidth'] ?? 440 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 48 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Search block settings (queries and results are built
	 * server-side per request, never trusted from the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_site_search_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );

		return array(
			'headingText'    => sanitize_text_field( $settings['headingText'] ?? 'Search' ),
			'placeholder'    => sanitize_text_field( $settings['placeholder'] ?? '' ),
			'showTypeBadges' => ! isset( $settings['showTypeBadges'] ) || ! empty( $settings['showTypeBadges'] ),
			'showImages'     => ! isset( $settings['showImages'] ) || ! empty( $settings['showImages'] ),
			'accentColor'    => $accent ? $accent : '',
			'maxWidth'       => max( 420, min( 1200, absint( $settings['maxWidth'] ?? 760 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 32 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	/**
	 * Sanitize the User Dashboard block settings (user identity, links, and
	 * orders come from the server-built site context, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_user_dashboard_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'welcomeText'     => sanitize_text_field( $settings['welcomeText'] ?? 'Welcome back,' ),
			'showOrders'      => ! isset( $settings['showOrders'] ) || ! empty( $settings['showOrders'] ),
			'showQuickLinks'  => ! isset( $settings['showQuickLinks'] ) || ! empty( $settings['showQuickLinks'] ),
			'loginPromptText' => sanitize_text_field( $settings['loginPromptText'] ?? 'Sign in to see your dashboard.' ),
			'maxWidth'        => max( 480, min( 1400, absint( $settings['maxWidth'] ?? 1000 ) ) ),
			'padding'         => max( 0, min( 160, absint( $settings['padding'] ?? 32 ) ) ),
			'paddingX'        => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'         => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'      => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Shop Header block settings (presentation only — the archive
	 * title/description are read live from the viewed archive at render time).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_shop_header_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showTitle'       => ! isset( $settings['showTitle'] ) || ! empty( $settings['showTitle'] ),
			'showDescription' => ! isset( $settings['showDescription'] ) || ! empty( $settings['showDescription'] ),
			'showCount'       => ! isset( $settings['showCount'] ) || ! empty( $settings['showCount'] ),
			'alignment'       => in_array( $settings['alignment'] ?? '', array( 'left', 'center' ), true ) ? $settings['alignment'] : 'left',
			'maxWidth'        => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'         => max( 0, min( 160, absint( $settings['padding'] ?? 32 ) ) ),
			'paddingX'        => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'         => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'      => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'titleColor', 'textColor', 'backgroundColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Shop Products block settings (the product cards, sorting, and
	 * pagination all come from the server-built archive context, never the client).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_shop_category_hero_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$overlay = sanitize_hex_color( $settings['overlayColor'] ?? '' );
		$text = sanitize_hex_color( $settings['textColor'] ?? '' );
		return array(
			'showImage' => ! isset( $settings['showImage'] ) || ! empty( $settings['showImage'] ),
			'showDescription' => ! isset( $settings['showDescription'] ) || ! empty( $settings['showDescription'] ),
			'showParentLink' => ! isset( $settings['showParentLink'] ) || ! empty( $settings['showParentLink'] ),
			'alignment' => 'center' === ( $settings['alignment'] ?? '' ) ? 'center' : 'left',
			'overlayColor' => $overlay ? $overlay : '',
			'textColor' => $text ? $text : '',
			'maxWidth' => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1280 ) ) ),
			'padding' => max( 0, min( 200, absint( $settings['padding'] ?? 56 ) ) ),
			'responsive' => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	private function sanitize_shop_subcategory_grid_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );
		return array(
			'showDescription' => ! isset( $settings['showDescription'] ) || ! empty( $settings['showDescription'] ),
			'showCount' => ! isset( $settings['showCount'] ) || ! empty( $settings['showCount'] ),
			'columns' => max( 2, min( 4, absint( $settings['columns'] ?? 3 ) ) ),
			'imageAspect' => in_array( $settings['imageAspect'] ?? '', array( 'square', 'landscape', 'portrait' ), true ) ? $settings['imageAspect'] : 'landscape',
			'accentColor' => $accent ? $accent : '',
			'maxWidth' => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding' => max( 0, min( 160, absint( $settings['padding'] ?? 32 ) ) ),
			'responsive' => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	private function sanitize_shop_products_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'columns'        => max( 2, min( 5, absint( $settings['columns'] ?? 4 ) ) ),
			'imageAspect'    => in_array( $settings['imageAspect'] ?? '', array( 'square', 'portrait', 'landscape' ), true ) ? $settings['imageAspect'] : 'square',
			'showPrice'      => ! isset( $settings['showPrice'] ) || ! empty( $settings['showPrice'] ),
			'showRating'     => ! isset( $settings['showRating'] ) || ! empty( $settings['showRating'] ),
			'showAddToCart'  => ! isset( $settings['showAddToCart'] ) || ! empty( $settings['showAddToCart'] ),
			'showSorting'    => ! isset( $settings['showSorting'] ) || ! empty( $settings['showSorting'] ),
			'showCount'      => ! isset( $settings['showCount'] ) || ! empty( $settings['showCount'] ),
			'showPagination' => ! isset( $settings['showPagination'] ) || ! empty( $settings['showPagination'] ),
			'maxWidth'       => max( 480, min( 1600, absint( $settings['maxWidth'] ?? 1200 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 24 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'accentColor', 'buttonColor', 'buttonTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the store fragment blocks' settings (cart / checkout / account).
	 *
	 * Presentation only — the fragment HTML itself is WooCommerce's own shortcode
	 * output, captured and sanitized server-side at render time (DSF_Store_Pages);
	 * nothing the client submits here is ever rendered as HTML.
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_store_fragment_settings( $type, $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'maxWidth'   => max( 720, min( 1600, absint( $settings['maxWidth'] ?? ( 'store-checkout' === $type ? 1140 : 1100 ) ) ) ),
			'padding'    => max( 0, min( 160, absint( $settings['padding'] ?? 24 ) ) ),
			'paddingX'   => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'    => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive' => $this->sanitize_product_responsive_spacing( $settings ),
		);

		$colors = array( 'accentColor' );

		if ( 'store-cart' === $type ) {
			$clean['showCrossSells'] = ! isset( $settings['showCrossSells'] ) || ! empty( $settings['showCrossSells'] );
			$colors[]                = 'buttonColor';
			$colors[]                = 'buttonTextColor';
		} elseif ( 'store-checkout' === $type ) {
			$clean['layout'] = 'stacked' === ( $settings['layout'] ?? '' ) ? 'stacked' : 'split';
			$colors[]        = 'buttonColor';
			$colors[]        = 'buttonTextColor';
		} elseif ( 'store-account' === $type ) {
			$clean['navStyle'] = 'top' === ( $settings['navStyle'] ?? '' ) ? 'top' : 'side';
		} elseif ( 'store-login' === $type ) {
			$clean['maxWidth']         = max( 360, min( 800, absint( $settings['maxWidth'] ?? 520 ) ) );
			$clean['heading']          = mb_substr( sanitize_text_field( $settings['heading'] ?? 'Welcome back' ), 0, 120 );
			$clean['subheading']       = mb_substr( sanitize_text_field( $settings['subheading'] ?? 'Sign in to view your orders and saved details.' ), 0, 240 );
			$clean['showRegisterLink'] = ! isset( $settings['showRegisterLink'] ) || ! empty( $settings['showRegisterLink'] );
			$colors[]                  = 'buttonColor';
			$colors[]                  = 'buttonTextColor';
		}

		foreach ( $colors as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Checkout Steps block settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_store_steps_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$steps    = array( 'auto', 'cart', 'checkout', 'complete' );

		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );

		return array(
			'labelCart'     => sanitize_text_field( $settings['labelCart'] ?? 'Cart' ),
			'labelCheckout' => sanitize_text_field( $settings['labelCheckout'] ?? 'Checkout' ),
			'labelComplete' => sanitize_text_field( $settings['labelComplete'] ?? 'Order Complete' ),
			'currentStep'   => in_array( $settings['currentStep'] ?? '', $steps, true ) ? $settings['currentStep'] : 'auto',
			'linkSteps'     => ! isset( $settings['linkSteps'] ) || ! empty( $settings['linkSteps'] ),
			'accentColor'   => $accent ? $accent : '',
			'maxWidth'      => max( 320, min( 1200, absint( $settings['maxWidth'] ?? 720 ) ) ),
			'padding'       => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'      => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'       => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'    => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	/**
	 * Bound the per-breakpoint spacing overrides shared by the product blocks.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_responsive_spacing( $settings ) {
		$raw        = ( isset( $settings['responsive'] ) && is_array( $settings['responsive'] ) ) ? $settings['responsive'] : array();
		$responsive = array();

		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values                    = is_array( $raw[ $breakpoint ] ?? null ) ? $raw[ $breakpoint ] : array();
			$responsive[ $breakpoint ] = array();
			if ( isset( $values['padding'] ) ) {
				$responsive[ $breakpoint ]['padding'] = max( 0, min( 160, absint( $values['padding'] ) ) );
			}
			if ( isset( $values['paddingX'] ) ) {
				$responsive[ $breakpoint ]['paddingX'] = max( 0, min( 120, absint( $values['paddingX'] ) ) );
			}
			if ( isset( $values['marginY'] ) ) {
				$responsive[ $breakpoint ]['marginY'] = max( 0, min( 100, absint( $values['marginY'] ) ) );
			}
		}

		return $responsive;
	}

	/**
	 * Sanitize the Product Gallery block settings (presentation only).
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_gallery_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$layouts  = array( 'thumbs-bottom', 'thumbs-left', 'grid', 'carousel', 'single' );
		$aspects  = array( 'square', 'portrait', 'landscape', 'natural' );

		return array(
			'layout'         => in_array( $settings['layout'] ?? '', $layouts, true ) ? $settings['layout'] : 'thumbs-bottom',
			'aspectRatio'    => in_array( $settings['aspectRatio'] ?? '', $aspects, true ) ? $settings['aspectRatio'] : 'square',
			'enableLightbox' => ! isset( $settings['enableLightbox'] ) || ! empty( $settings['enableLightbox'] ),
			'showThumbs'     => ! isset( $settings['showThumbs'] ) || ! empty( $settings['showThumbs'] ),
			'thumbColumns'   => max( 2, min( 8, absint( $settings['thumbColumns'] ?? 5 ) ) ),
			'gap'            => max( 0, min( 40, absint( $settings['gap'] ?? 12 ) ) ),
			'maxWidth'       => max( 320, min( 1200, absint( $settings['maxWidth'] ?? 640 ) ) ),
			'padding'        => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'       => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'        => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'     => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	/**
	 * Sanitize the Product Description block settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_description_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'showHeading' => ! isset( $settings['showHeading'] ) || ! empty( $settings['showHeading'] ),
			'headingText' => sanitize_text_field( $settings['headingText'] ?? 'Description' ),
			'maxWidth'    => max( 320, min( 1400, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);

		foreach ( array( 'headingColor', 'textColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Specs block settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_specs_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$layouts  = array( 'striped', 'cards', 'inline', 'bordered' );

		$clean = array(
			'layout'      => in_array( $settings['layout'] ?? '', $layouts, true ) ? $settings['layout'] : 'striped',
			'showHeading' => ! isset( $settings['showHeading'] ) || ! empty( $settings['showHeading'] ),
			'headingText' => sanitize_text_field( $settings['headingText'] ?? 'Specifications' ),
			'columns'     => max( 1, min( 3, absint( $settings['columns'] ?? 1 ) ) ),
			'maxWidth'    => max( 320, min( 1200, absint( $settings['maxWidth'] ?? 760 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);
		$keys = preg_split( '/\s*,\s*/', (string) ( $settings['customFieldKeys'] ?? '' ) );
		$keys = is_array( $keys ) ? $keys : array();
		$keys = array_filter( array_map( 'sanitize_key', array_slice( $keys, 0, 12 ) ) );
		$clean['customFieldKeys'] = implode( ',', array_unique( $keys ) );

		foreach ( array( 'headingColor', 'labelColor', 'valueColor', 'accentColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Product Tabs block settings, including per-tab custom content.
	 *
	 * Tabs are capped, labels are plain text, sources are allowlisted, and only
	 * custom tabs keep rich content (wp_kses_post). Live tab data (description /
	 * specs) is read from the current product at render time, never from the client.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_product_tabs_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$styles   = array( 'underline', 'pills', 'boxed' );
		$sources  = array( 'description', 'specs', 'reviews', 'custom' );

		$raw_tabs = is_array( $settings['tabs'] ?? null ) ? $settings['tabs'] : array();
		$tabs     = array();
		foreach ( array_slice( $raw_tabs, 0, 10 ) as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$source = in_array( $tab['source'] ?? '', $sources, true ) ? $tab['source'] : 'description';
			$tabs[] = array(
				'label'   => sanitize_text_field( $tab['label'] ?? '' ),
				'source'  => $source,
				'content' => 'custom' === $source ? wp_kses_post( (string) ( $tab['content'] ?? '' ) ) : '',
			);
		}

		$accent = sanitize_hex_color( $settings['accentColor'] ?? '' );

		return array(
			'style'       => in_array( $settings['style'] ?? '', $styles, true ) ? $settings['style'] : 'underline',
			'tabs'        => $tabs,
			'accentColor' => $accent ? $accent : '',
			'maxWidth'    => max( 320, min( 1400, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'     => max( 0, min( 160, absint( $settings['padding'] ?? 0 ) ) ),
			'paddingX'    => max( 0, min( 120, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			'responsive'  => $this->sanitize_product_responsive_spacing( $settings ),
		);
	}

	/**
	 * Sanitize form block settings, including bounded raw embed content.
	 *
	 * @param string $type Block type.
	 * @param array  $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_form_block_settings( $type, $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		if ( 'form-embed' === $type ) {
			return array(
				'formId'        => absint( $settings['formId'] ?? 0 ) ? (string) absint( $settings['formId'] ) : '',
				'showTitle'     => ! empty( $settings['showTitle'] ),
				'title'         => sanitize_text_field( $settings['title'] ?? '' ),
				'formMaxWidth'  => max( 300, min( 1200, absint( $settings['formMaxWidth'] ?? 600 ) ) ),
				'formAlignment' => in_array( $settings['formAlignment'] ?? '', array( 'left', 'center', 'right' ), true ) ? $settings['formAlignment'] : 'center',
				'marginY'       => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
			);
		}

		$clean = array(
			'sectionTitle'    => sanitize_text_field( $settings['sectionTitle'] ?? '' ),
			'showDivider'     => ! empty( $settings['showDivider'] ),
			'formSource'      => 'embed' === ( $settings['formSource'] ?? '' ) ? 'embed' : 'dsf',
			'formId'          => absint( $settings['formId'] ?? 0 ) ? (string) absint( $settings['formId'] ) : '',
			'embedCode'       => $this->sanitize_form_embed_code( $settings['embedCode'] ?? '' ),
			'formSide'        => 'left' === ( $settings['formSide'] ?? '' ) ? 'left' : 'right',
			'columnRatio'     => in_array( $settings['columnRatio'] ?? '', array( '1-1', '3-2', '2-3' ), true ) ? $settings['columnRatio'] : '1-1',
			'content'         => wp_kses_post( $settings['content'] ?? '' ),
			'mediaType'       => 'image' === ( $settings['mediaType'] ?? '' ) ? 'image' : 'video',
			'image'           => esc_url_raw( $settings['image'] ?? '', array( 'http', 'https' ) ),
			'logo'            => esc_url_raw( $settings['logo'] ?? '', array( 'http', 'https' ) ),
			'logoPadding'     => ! empty( $settings['logoPadding'] ),
			'video'           => esc_url_raw( $settings['video'] ?? '', array( 'http', 'https' ) ),
			'videoFile'       => esc_url_raw( $settings['videoFile'] ?? '', array( 'http', 'https' ) ),
			'padding'         => max( 0, min( 120, absint( $settings['padding'] ?? 60 ) ) ),
			'paddingX'        => max( 0, min( 100, absint( $settings['paddingX'] ?? 24 ) ) ),
			'marginY'         => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'dividerColor', 'backgroundColor', 'contentBg', 'formBg', 'textColor', 'titleColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Keep shortcode text and safe embed HTML while removing executable markup.
	 *
	 * @param mixed $embed_code Submitted shortcode/embed content.
	 * @return string
	 */
	private function sanitize_form_embed_code( $embed_code ) {
		$embed_code = is_string( $embed_code ) ? trim( $embed_code ) : '';
		if ( '' === $embed_code ) {
			return '';
		}

		$allowed = function_exists( 'wp_kses_allowed_html' ) ? wp_kses_allowed_html( 'post' ) : array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
		$allowed['iframe'] = array(
			'src'             => true,
			'title'           => true,
			'width'           => true,
			'height'          => true,
			'class'           => true,
			'id'              => true,
			'loading'         => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'frameborder'     => true,
			'referrerpolicy'  => true,
			'sandbox'         => true,
			'style'           => true,
		);

		return wp_kses( $embed_code, $allowed );
	}

	/**
	 * Sanitize the bounded text, URL, enum, and layout contract for landing blocks.
	 *
	 * @param string $type Block type.
	 * @param array  $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_landing_block_settings( $type, $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'paddingX' => max( 0, min( 80, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'  => max( 0, min( 80, absint( $settings['marginY'] ?? 0 ) ) ),
		);
		foreach ( array( 'backgroundColor', 'textColor', 'accentColor', 'eyebrowColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		if ( 'landing-progress-header' === $type ) {
			$clean['variant']          = in_array( $settings['variant'] ?? '', array( 'progress', 'minimal', 'centered', 'transparent' ), true ) ? $settings['variant'] : 'progress';
			$clean['showAnnouncement'] = ! empty( $settings['showAnnouncement'] );
			foreach ( array( 'brandText', 'announcementText', 'announcementLinkText', 'docsText', 'ctaText' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'announcementUrl', 'homeUrl', 'docsUrl', 'ctaUrl' ) as $key ) {
				$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
			}
			$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
			$clean['navLinks']  = $this->sanitize_landing_links( $settings['navLinks'] ?? array(), 10 );
			return $clean;
		}

		if ( 'landing-dock-header' === $type ) {
			$clean['brandText'] = sanitize_text_field( $settings['brandText'] ?? '' );
			$clean['homeUrl']   = $this->sanitize_showcase_url( $settings['homeUrl'] ?? '' );
			$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
			$clean['navLinks']  = $this->sanitize_dock_nav_links( $settings['navLinks'] ?? array(), 16 );
			return $clean;
		}

		if ( 'landing-hero' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'primaryText', 'secondaryText', 'note' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description']   = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['primaryUrl']    = $this->sanitize_showcase_url( $settings['primaryUrl'] ?? '' );
			$clean['secondaryUrl']  = $this->sanitize_showcase_url( $settings['secondaryUrl'] ?? '' );
			$clean['align']         = in_array( $settings['align'] ?? '', array( 'left', 'center' ), true ) ? $settings['align'] : 'left';
			$clean['mediaPosition'] = in_array( $settings['mediaPosition'] ?? '', array( 'right', 'left' ), true ) ? $settings['mediaPosition'] : 'right';

			$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, 'media' ) );
			return $clean;
		}

		if ( 'landing-showcase-hero' === $type ) {
			$text_limits = array(
				'eyebrow'       => 96,
				'title'         => 96,
				'tagline'       => 240,
				'primaryText'   => 80,
				'secondaryText' => 80,
				'chip1'         => 96,
				'chip2'         => 96,
				'chip3'         => 96,
			);
			foreach ( $text_limits as $key => $limit ) {
				$clean[ $key ] = $this->sanitize_bounded_landing_text( $settings[ $key ] ?? '', $limit );
			}
			$clean['rotatingWords'] = $this->sanitize_showcase_rotating_words( $settings['rotatingWords'] ?? '' );
			$clean['primaryUrl']    = $this->sanitize_showcase_url( $settings['primaryUrl'] ?? '' );
			$clean['secondaryUrl']  = $this->sanitize_showcase_url( $settings['secondaryUrl'] ?? '' );
			$clean['tiles']         = $this->sanitize_dock_nav_links( $settings['tiles'] ?? array(), 6 );
			foreach ( array( 'eyebrowLineColor', 'buttonColor', 'buttonTextColor' ) as $key ) {
				$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
				$clean[ $key ] = $color ? $color : '';
			}
			return $clean;
		}

		if ( 'landing-block-explorer' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'footnote' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['items']       = $this->sanitize_landing_gallery_items( $settings['items'] ?? array() );
			return $clean;
		}

		if ( 'landing-product-story' === $type ) {
			$clean['variant']       = in_array( $settings['variant'] ?? '', array( 'editor', 'theme', 'commerce', 'layouts', 'campaigns' ), true ) ? $settings['variant'] : 'editor';
			$clean['reverseLayout'] = ! empty( $settings['reverseLayout'] );
			foreach ( array( 'eyebrow', 'title', 'featureOne', 'featureTwo', 'featureThree' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );

			$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, 'media' ) );
			return $clean;
		}

		if ( 'landing-trust-workflow' === $type ) {
			$clean['variant'] = in_array( $settings['variant'] ?? '', array( 'seo', 'security', 'audience', 'workflow' ), true ) ? $settings['variant'] : 'seo';
			$clean['layout']  = in_array( $settings['layout'] ?? '', array( '', 'pipeline', 'grid-dark', 'grid-light', 'numbered' ), true ) ? $settings['layout'] : '';
			foreach ( array( 'eyebrow', 'title', 'caption' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['items']       = $this->sanitize_landing_icon_items( $settings['items'] ?? array() );
			return $clean;
		}

		if ( 'landing-block-ready' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'note', 'step1Title', 'step2Title', 'step3Title', 'demoEyebrow', 'demoTitle', 'demoButton' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'description', 'step1Text', 'step2Text', 'step3Text', 'demoText' ) as $key ) {
				$clean[ $key ] = sanitize_textarea_field( $settings[ $key ] ?? '' );
			}
			return $clean;
		}

		if ( 'landing-engagement-suite' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'formsLabel', 'formsTitle', 'popupLabel', 'popupTitle', 'notificationLabel', 'notificationTitle' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'description', 'formsDescription', 'formsBullets', 'popupDescription', 'notificationDescription' ) as $key ) {
				$clean[ $key ] = sanitize_textarea_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'formsIcon', 'popupIcon', 'notificationIcon' ) as $key ) {
				$clean[ $key ] = $this->sanitize_landing_icon( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'forms', 'popup', 'notification' ) as $prefix ) {
				$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, $prefix ) );
			}
			return $clean;
		}

		$clean['variant'] = in_array( $settings['variant'] ?? '', array( 'bigcta', 'centered', 'simple', 'columns' ), true ) ? $settings['variant'] : 'bigcta';
		foreach ( array( 'eyebrow', 'title', 'primaryText', 'secondaryText', 'brandText', 'col1Title', 'col2Title', 'col3Title', 'copyright', 'tagline' ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
		}
		foreach ( array( 'description', 'brandStatement' ) as $key ) {
			$clean[ $key ] = sanitize_textarea_field( $settings[ $key ] ?? '' );
		}
		foreach ( array( 'primaryUrl', 'secondaryUrl', 'homeUrl', 'docsUrl' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
		}
		$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
		foreach ( array( 1, 2, 3 ) as $column ) {
			$clean[ 'col' . $column . 'Links' ] = $this->sanitize_landing_links( $settings[ 'col' . $column . 'Links' ] ?? array(), 10 );
		}
		foreach ( array( 'buttonBgColor', 'buttonLabelColor', 'linksColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize and length-bound one landing-page text value.
	 *
	 * @param mixed $value Candidate value.
	 * @param int   $limit Maximum characters.
	 * @return string
	 */
	private function sanitize_bounded_landing_text( $value, $limit ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return mb_substr( sanitize_text_field( (string) $value ), 0, max( 0, (int) $limit ) );
	}

	/**
	 * Sanitize the Showcase Hero's legacy comma-separated phrase setting.
	 *
	 * The storage shape stays a string for editor/backward compatibility, while
	 * each phrase is independently cleaned, bounded, limited to two words,
	 * de-duplicated, and capped.
	 *
	 * @param mixed $value Candidate comma-separated phrases.
	 * @return string
	 */
	private function sanitize_showcase_rotating_words( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$phrases = array();
		$seen    = array();
		$raw     = mb_substr( (string) $value, 0, 768 );
		foreach ( explode( ',', $raw ) as $phrase ) {
			$phrase = $this->sanitize_bounded_landing_text( $phrase, 64 );
			if ( '' === $phrase ) {
				continue;
			}
			$phrase = preg_replace( '/\s+/u', ' ', trim( $phrase ) );
			$parts  = preg_split( '/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
				continue;
			}
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $phrase ) : strtolower( $phrase );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$phrases[]    = $phrase;
			if ( 6 === count( $phrases ) ) {
				break;
			}
		}

		return implode( ', ', $phrases );
	}

	/**
	 * Sanitize one landing block media control group.
	 *
	 * @param array  $settings Submitted settings.
	 * @param string $prefix Setting key prefix.
	 * @return array
	 */
	private function sanitize_landing_media( $settings, $prefix ) {
		$type_key  = $prefix . 'Type';
		$image_key = $prefix . 'Image';
		$video_key = $prefix . 'Video';
		$type      = in_array( $settings[ $type_key ] ?? '', array( 'mockup', 'image', 'video' ), true ) ? $settings[ $type_key ] : 'mockup';

		return array(
			$type_key  => $type,
			$image_key => esc_url_raw( $settings[ $image_key ] ?? '', array( 'http', 'https' ) ),
			$video_key => esc_url_raw( $settings[ $video_key ] ?? '', array( 'http', 'https' ) ),
		);
	}

	/**
	 * Dock header nav links: label + url plus an optional preset icon name or a
	 * custom media-library image URL.
	 *
	 * @param mixed $links Raw links.
	 * @param int   $limit Max links.
	 * @return array
	 */
	private function sanitize_dock_nav_links( $links, $limit ) {
		$clean = array();
		foreach ( array_slice( is_array( $links ) ? $links : array(), 0, $limit ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			// icon is a kebab-case preset name; unknown names fall back to a default
			// glyph on render, so sanitize_key is a safe, permissive filter.
			$icon       = isset( $link['icon'] ) && is_scalar( $link['icon'] ) ? mb_substr( sanitize_key( (string) $link['icon'] ), 0, 64 ) : '';
			$icon_image = isset( $link['iconImage'] ) && is_string( $link['iconImage'] ) ? mb_substr( $link['iconImage'], 0, 2048 ) : '';
			$clean[]    = array(
				'label'     => $this->sanitize_bounded_landing_text( $link['label'] ?? '', 100 ),
				'url'       => $this->sanitize_showcase_url( $link['url'] ?? '' ),
				'icon'      => $icon,
				'iconImage' => esc_url_raw( $icon_image, array( 'http', 'https' ) ),
			);
		}
		return $clean;
	}

	private function sanitize_landing_links( $links, $limit ) {
		$clean = array();
		foreach ( array_slice( is_array( $links ) ? $links : array(), 0, $limit ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$clean[] = array(
				'label' => sanitize_text_field( $link['label'] ?? '' ),
				'url'   => $this->sanitize_showcase_url( $link['url'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Sanitize editable carousel cards.
	 *
	 * @param mixed $items Submitted cards.
	 * @return array
	 */
	private function sanitize_landing_gallery_items( $items ) {
		$kinds = array( 'hero', 'bento', 'spotlight', 'duo', 'expander', 'content', 'faq', 'text-image', 'features', 'testimonials', 'countdown', 'pricing', 'product-grid', 'featured-promo', 'cta-banner', 'form', 'mega-menu', 'footer', 'generic' );
		$clean = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 24 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean[] = array(
				'category'    => sanitize_text_field( $item['category'] ?? '' ),
				'title'       => sanitize_text_field( $item['title'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'image'       => esc_url_raw( $item['image'] ?? '', array( 'http', 'https' ) ),
				'kind'        => in_array( $item['kind'] ?? '', $kinds, true ) ? $item['kind'] : 'generic',
				'url'         => $this->sanitize_showcase_url( $item['url'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Sanitize editable icon cards.
	 *
	 * @param mixed $items Submitted cards.
	 * @return array
	 */
	private function sanitize_landing_icon_items( $items ) {
		$clean = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 8 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean[] = array(
				'icon'        => $this->sanitize_landing_icon( $item['icon'] ?? '' ),
				'title'       => sanitize_text_field( $item['title'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'note'        => sanitize_text_field( $item['note'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Allow only icons supported by the landing icon renderer.
	 *
	 * @param mixed $icon Submitted icon name.
	 * @return string
	 */
	private function sanitize_landing_icon( $icon ) {
		$allowed = array( 'sparkles', 'shield-check', 'lock', 'fingerprint', 'code', 'paintbrush', 'palette', 'layers', 'layout', 'columns', 'grid', 'briefcase', 'store', 'users', 'mail', 'form-input', 'bell', 'megaphone', 'clock', 'calendar', 'search', 'filter', 'zap', 'rocket', 'check', 'star', 'heart', 'globe', 'monitor', 'smartphone', 'file-text', 'settings', 'mouse-pointer', 'panel-top', 'wand', 'gauge', 'boxes' );
		return in_array( $icon, $allowed, true ) ? $icon : 'sparkles';
	}

	/**
	 * Sanitize FAQ rich answers, colors, and bounded layout settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_faq_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'title'    => sanitize_text_field( $settings['title'] ?? '' ),
			'items'    => array(),
			'maxWidth' => max( 600, min( 1200, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'  => max( 20, min( 160, absint( $settings['padding'] ?? 80 ) ) ),
			'paddingX' => max( 0, min( 120, absint( $settings['paddingX'] ?? 24 ) ) ),
			'marginY'  => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array_slice( is_array( $settings['items'] ?? null ) ? $settings['items'] : array(), 0, 12 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean['items'][] = array(
				'question' => sanitize_text_field( $item['question'] ?? '' ),
				'answer'   => wp_kses_post( $item['answer'] ?? '' ),
			);
		}

		foreach ( array( 'backgroundColor', 'titleColor', 'questionColor', 'answerColor', 'dividerColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Text & Image settings, including responsive dimensions.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_text_image_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'title'                  => sanitize_text_field( $settings['title'] ?? '' ),
			'content'                => sanitize_textarea_field( $settings['content'] ?? '' ),
			'descriptionSize'        => 'normal' === ( $settings['descriptionSize'] ?? '' ) ? 'normal' : 'large',
			'showButton'             => ! empty( $settings['showButton'] ),
			'buttonText'             => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonUrl'              => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'buttonAction'           => 'modal' === ( $settings['buttonAction'] ?? '' ) ? 'modal' : 'link',
			'buttonModalLayout'      => 'drawer' === ( $settings['buttonModalLayout'] ?? '' ) ? 'drawer' : 'center',
			'buttonModalContentType' => in_array( $settings['buttonModalContentType'] ?? '', array( 'wysiwyg', 'html', 'shortcode' ), true ) ? $settings['buttonModalContentType'] : 'wysiwyg',
			'buttonModalContent'     => wp_kses_post( $settings['buttonModalContent'] ?? '' ),
			'buttonModalHtml'        => wp_kses_post( $settings['buttonModalHtml'] ?? '' ),
			'buttonModalShortcode'   => sanitize_text_field( $settings['buttonModalShortcode'] ?? '' ),
			'image'                  => esc_url_raw( $settings['image'] ?? '', array( 'http', 'https' ) ),
			'imagePosition'          => 'left' === ( $settings['imagePosition'] ?? '' ) ? 'left' : 'right',
			'height'                 => max( 100, min( 800, absint( $settings['height'] ?? 400 ) ) ),
			'padding'                => max( 0, min( 120, absint( $settings['padding'] ?? 60 ) ) ),
			'paddingX'               => max( 0, min( 100, absint( $settings['paddingX'] ?? 20 ) ) ),
			'marginY'                => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'backgroundColor', 'titleColor', 'textColor', 'buttonColor', 'buttonTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array(
				'height'   => max( 100, min( 800, absint( $values['height'] ?? $clean['height'] ) ) ),
				'padding'  => max( 0, min( 120, absint( $values['padding'] ?? $clean['padding'] ) ) ),
				'paddingX' => max( 0, min( 100, absint( $values['paddingX'] ?? $clean['paddingX'] ) ) ),
				'marginY'  => max( 0, min( 100, absint( $values['marginY'] ?? $clean['marginY'] ) ) ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize Countdown content, media, deadline, and CTA settings.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_countdown_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'eyebrow'               => sanitize_text_field( $settings['eyebrow'] ?? '' ),
			'title'                 => sanitize_text_field( $settings['title'] ?? '' ),
			'description'           => sanitize_textarea_field( $settings['description'] ?? '' ),
			'buttonText'            => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonAction'          => 'modal' === ( $settings['buttonAction'] ?? '' ) ? 'modal' : 'link',
			'buttonUrl'             => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'buttonModalLayout'     => 'drawer' === ( $settings['buttonModalLayout'] ?? '' ) ? 'drawer' : 'center',
			'buttonModalContentType' => in_array( $settings['buttonModalContentType'] ?? '', array( 'wysiwyg', 'html', 'shortcode' ), true ) ? $settings['buttonModalContentType'] : 'wysiwyg',
			'buttonModalContent'    => wp_kses_post( $settings['buttonModalContent'] ?? '' ),
			'buttonModalHtml'       => wp_kses_post( $settings['buttonModalHtml'] ?? '' ),
			'buttonModalShortcode'  => sanitize_text_field( $settings['buttonModalShortcode'] ?? '' ),
			'targetDate'            => $this->sanitize_countdown_datetime( $settings['targetDate'] ?? '' ),
			'expiredMessage'        => sanitize_text_field( $settings['expiredMessage'] ?? '' ),
			'noticeText'            => sanitize_text_field( $settings['noticeText'] ?? '' ),
			'mediaType'             => 'video' === ( $settings['mediaType'] ?? '' ) ? 'video' : 'image',
			'image'                 => esc_url_raw( $settings['image'] ?? '', array( 'http', 'https' ) ),
			'video'                 => esc_url_raw( $settings['video'] ?? '', array( 'http', 'https' ) ),
			'mediaPosition'         => 'left' === ( $settings['mediaPosition'] ?? '' ) ? 'left' : 'right',
			'padding'               => max( 20, min( 160, absint( $settings['padding'] ?? 64 ) ) ),
			'paddingX'              => max( 0, min( 140, absint( $settings['paddingX'] ?? 40 ) ) ),
			'gap'                   => max( 16, min( 120, absint( $settings['gap'] ?? 56 ) ) ),
			'marginY'               => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'backgroundColor', 'textColor', 'accentColor', 'buttonColor', 'buttonTextColor', 'noticeColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array(
				'padding'  => max( 20, min( 160, absint( $values['padding'] ?? $clean['padding'] ) ) ),
				'paddingX' => max( 0, min( 140, absint( $values['paddingX'] ?? $clean['paddingX'] ) ) ),
				'gap'      => max( 16, min( 120, absint( $values['gap'] ?? $clean['gap'] ) ) ),
				'marginY'  => max( 0, min( 100, absint( $values['marginY'] ?? $clean['marginY'] ) ) ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize Expander Hero content, links, layout options, and dimensions.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_expander_hero_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'layoutStyle' => 'row' === ( $settings['layoutStyle'] ?? '' ) ? 'row' : 'split-bar',
			'barPosition' => in_array( $settings['barPosition'] ?? '', array( 'top', 'bottom' ), true ) ? $settings['barPosition'] : 'middle',
			'cards'       => array(),
			'barTitle'    => sanitize_text_field( $settings['barTitle'] ?? '' ),
			'showButton'  => ! empty( $settings['showButton'] ),
			'buttonText'  => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonUrl'   => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'cardHeight'  => max( 160, min( 520, absint( $settings['cardHeight'] ?? 280 ) ) ),
			'barHeight'   => max( 70, min( 220, absint( $settings['barHeight'] ?? 110 ) ) ),
			'gap'         => max( 0, min( 48, absint( $settings['gap'] ?? 16 ) ) ),
			'paddingX'    => max( 0, min( 80, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array_slice( is_array( $settings['cards'] ?? null ) ? $settings['cards'] : array(), 0, 6 ) as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$clean['cards'][] = array(
				'title' => sanitize_text_field( $card['title'] ?? '' ),
				'image' => esc_url_raw( $card['image'] ?? '', array( 'http', 'https' ) ),
				'url'   => $this->sanitize_showcase_url( $card['url'] ?? '' ),
			);
		}

		foreach ( array( 'barColor', 'barTextColor', 'buttonColor', 'buttonTextColor', 'cardTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		if ( isset( $settings['height'] ) ) {
			$clean['height'] = max( 200, min( 1000, absint( $settings['height'] ) ) );
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array();
			if ( isset( $values['height'] ) ) {
				$clean['responsive'][ $breakpoint ]['height'] = max( 200, min( 1000, absint( $values['height'] ) ) );
			}
			if ( isset( $values['gap'] ) ) {
				$clean['responsive'][ $breakpoint ]['gap'] = max( 0, min( 48, absint( $values['gap'] ) ) );
			}
			if ( isset( $values['paddingX'] ) ) {
				$clean['responsive'][ $breakpoint ]['paddingX'] = max( 0, min( 80, absint( $values['paddingX'] ) ) );
			}
			if ( isset( $values['marginY'] ) ) {
				$clean['responsive'][ $breakpoint ]['marginY'] = max( 0, min( 100, absint( $values['marginY'] ) ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize Card Columns header, cards, colors, and bounded dimensions.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_card_columns_settings( $settings ) {
		$settings   = is_array( $settings ) ? $settings : array();
		$directions = array( 'left-right', 'top-bottom', 'radial' );
		$clean      = array(
			'headerLayout'      => 'split' === ( $settings['headerLayout'] ?? '' ) ? 'split' : 'centered',
			'title'             => sanitize_text_field( $settings['title'] ?? '' ),
			'description'       => sanitize_textarea_field( $settings['description'] ?? '' ),
			'cards'             => array(),
			'columns'           => in_array( $settings['columns'] ?? '', array( '2', '3', '4', '5', '6' ), true ) ? $settings['columns'] : '3',
			'cardLayout'        => 'overlay' === ( $settings['cardLayout'] ?? '' ) ? 'overlay' : 'standard',
			'contentAlign'      => 'left' === ( $settings['contentAlign'] ?? '' ) ? 'left' : 'center',
			'buttonStyle'       => in_array( $settings['buttonStyle'] ?? '', array( 'arrow', 'text', 'text-arrow' ), true ) ? $settings['buttonStyle'] : 'arrow',
			'imageFit'          => 'contain' === ( $settings['imageFit'] ?? '' ) ? 'contain' : 'cover',
			'overlayStrength'   => max( 0, min( 100, absint( $settings['overlayStrength'] ?? 60 ) ) ),
			'overlayHeight'     => max( 20, min( 100, absint( $settings['overlayHeight'] ?? 50 ) ) ),
			'backgroundType'    => 'gradient' === ( $settings['backgroundType'] ?? '' ) ? 'gradient' : 'solid',
			'gradientDirection' => in_array( $settings['gradientDirection'] ?? '', $directions, true ) ? $settings['gradientDirection'] : 'top-bottom',
			'cardMinHeight'     => max( 200, min( 720, absint( $settings['cardMinHeight'] ?? 380 ) ) ),
			'cardPadding'       => max( 8, min( 48, absint( $settings['cardPadding'] ?? 24 ) ) ),
			'cardRadius'        => max( 0, min( 40, absint( $settings['cardRadius'] ?? 16 ) ) ),
			'imageHeight'       => max( 80, min( 420, absint( $settings['imageHeight'] ?? 220 ) ) ),
			'padding'           => max( 0, min( 160, absint( $settings['padding'] ?? 60 ) ) ),
			'paddingX'          => max( 0, min( 120, absint( $settings['paddingX'] ?? 24 ) ) ),
			'gap'               => max( 0, min( 64, absint( $settings['gap'] ?? 24 ) ) ),
			'marginY'           => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'backgroundColor', 'gradientStart', 'gradientEnd', 'titleColor', 'descriptionColor', 'cardTitleColor', 'cardDescriptionColor', 'cardIconColor', 'buttonColor', 'buttonTextColor', 'overlayTextColor' ) as $key ) {
			$clean[ $key ] = $this->sanitize_card_columns_color( $settings[ $key ] ?? '' );
		}

		// Mirrors LANDING_ICON_NAMES in src/utils/landingIcons.js.
		$icons = array( 'sparkles', 'shield-check', 'lock', 'fingerprint', 'code', 'file-code', 'file-search', 'paintbrush', 'palette', 'layers', 'layout', 'columns', 'grid', 'briefcase', 'store', 'users', 'mail', 'form-input', 'bell', 'megaphone', 'clock', 'calendar', 'search', 'filter', 'zap', 'rocket', 'check', 'star', 'heart', 'globe', 'monitor', 'smartphone', 'file-text', 'settings', 'mouse-pointer', 'panel-top', 'wand', 'gauge', 'boxes' );

		foreach ( array_slice( is_array( $settings['cards'] ?? null ) ? $settings['cards'] : array(), 0, 8 ) as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$icon      = in_array( $card['icon'] ?? '', $icons, true ) ? $card['icon'] : '';
			$icon_type = in_array( $card['iconType'] ?? '', array( 'none', 'preset', 'custom' ), true ) ? $card['iconType'] : ( '' !== $icon ? 'preset' : 'none' );

			$clean['cards'][] = array(
				'icon'              => $icon,
				'iconType'          => $icon_type,
				'customIcon'        => esc_url_raw( $card['customIcon'] ?? '', array( 'http', 'https' ) ),
				'title'             => sanitize_text_field( $card['title'] ?? '' ),
				'description'       => sanitize_textarea_field( $card['description'] ?? '' ),
				'image'             => esc_url_raw( $card['image'] ?? '', array( 'http', 'https' ) ),
				'backgroundType'    => in_array( $card['backgroundType'] ?? '', array( 'transparent', 'solid', 'gradient' ), true ) ? $card['backgroundType'] : 'solid',
				'backgroundColor'   => $this->sanitize_card_columns_color( $card['backgroundColor'] ?? '' ),
				'gradientStart'     => $this->sanitize_card_columns_color( $card['gradientStart'] ?? '' ),
				'gradientEnd'       => $this->sanitize_card_columns_color( $card['gradientEnd'] ?? '' ),
				'gradientDirection' => in_array( $card['gradientDirection'] ?? '', $directions, true ) ? $card['gradientDirection'] : 'top-bottom',
				'showButton'        => ! empty( $card['showButton'] ),
				'buttonText'        => sanitize_text_field( $card['buttonText'] ?? '' ),
				'buttonUrl'         => $this->sanitize_showcase_url( $card['buttonUrl'] ?? '' ),
			);
		}

		if ( isset( $settings['height'] ) ) {
			$clean['height'] = max( 200, min( 1000, absint( $settings['height'] ) ) );
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values                             = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array();
			if ( isset( $values['height'] ) ) {
				$clean['responsive'][ $breakpoint ]['height'] = max( 200, min( 1000, absint( $values['height'] ) ) );
			}
			if ( isset( $values['padding'] ) ) {
				$clean['responsive'][ $breakpoint ]['padding'] = max( 0, min( 160, absint( $values['padding'] ) ) );
			}
			if ( isset( $values['paddingX'] ) ) {
				$clean['responsive'][ $breakpoint ]['paddingX'] = max( 0, min( 120, absint( $values['paddingX'] ) ) );
			}
			if ( isset( $values['gap'] ) ) {
				$clean['responsive'][ $breakpoint ]['gap'] = max( 0, min( 64, absint( $values['gap'] ) ) );
			}
			if ( isset( $values['marginY'] ) ) {
				$clean['responsive'][ $breakpoint ]['marginY'] = max( 0, min( 100, absint( $values['marginY'] ) ) );
			}
		}

		return $clean;
	}

	/**
	 * Allow a hex color or a strict rgb()/rgba() value (the color picker emits
	 * rgba when opacity is lowered). Anything else becomes ''.
	 *
	 * @param mixed $value Submitted color value.
	 * @return string
	 */
	private function sanitize_card_columns_color( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}
		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d{1,4})\s*)?\)$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Validate a local calendar date and time from datetime-local.
	 *
	 * @param mixed $value Submitted datetime value.
	 * @return string
	 */
	private function sanitize_countdown_datetime( $value ) {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches ) ) {
			return '';
		}
		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return '';
		}
		if ( (int) $matches[4] > 23 || (int) $matches[5] > 59 || ( isset( $matches[6] ) && (int) $matches[6] > 59 ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Sanitize and cap nested showcase navigation collections.
	 *
	 * @param array $navigation Navigation configuration.
	 * @return array
	 */
	private function sanitize_showcase_navigation( $navigation ) {
		$navigation = is_array( $navigation ) ? $navigation : array();
		$clean      = array( 'utility' => array(), 'menu' => array(), 'locations' => array(), 'calls' => array() );
		$kinds      = array( 'link', 'dropdown', 'mega', 'locations', 'calls' );
		$icons      = array( 'settings', 'book', 'map-pin', 'phone' );

		foreach ( array_slice( is_array( $navigation['utility'] ?? null ) ? $navigation['utility'] : array(), 0, 4 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$kind  = in_array( $item['kind'] ?? '', $kinds, true ) ? $item['kind'] : 'link';
			$icon  = in_array( $item['icon'] ?? '', $icons, true ) ? $item['icon'] : 'settings';
			$links = array();
			foreach ( array_slice( is_array( $item['links'] ?? null ) ? $item['links'] : array(), 0, 6 ) as $link ) {
				if ( is_array( $link ) ) {
					$links[] = array( 'label' => sanitize_text_field( $link['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $link['url'] ?? '' ) );
				}
			}
			$clean['utility'][] = array( 'label' => sanitize_text_field( $item['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $item['url'] ?? '' ), 'icon' => $icon, 'kind' => $kind, 'links' => $links, 'panel' => $this->sanitize_showcase_panel( $item['panel'] ?? array() ) );
		}

		foreach ( array_slice( is_array( $navigation['menu'] ?? null ) ? $navigation['menu'] : array(), 0, 8 ) as $item ) {
			if ( is_array( $item ) ) {
				$clean['menu'][] = array( 'label' => sanitize_text_field( $item['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $item['url'] ?? '' ), 'hasMega' => ! empty( $item['hasMega'] ), 'panel' => $this->sanitize_showcase_panel( $item['panel'] ?? array() ) );
			}
		}

		foreach ( array_slice( is_array( $navigation['locations'] ?? null ) ? $navigation['locations'] : array(), 0, 6 ) as $location ) {
			if ( is_array( $location ) ) {
				$clean['locations'][] = array( 'name' => sanitize_text_field( $location['name'] ?? '' ), 'image' => esc_url_raw( $location['image'] ?? '', array( 'http', 'https' ) ), 'address' => sanitize_textarea_field( $location['address'] ?? '' ), 'hours' => sanitize_textarea_field( $location['hours'] ?? '' ), 'phone' => sanitize_text_field( $location['phone'] ?? '' ), 'phoneUrl' => $this->sanitize_showcase_url( $location['phoneUrl'] ?? '' ), 'directionsUrl' => $this->sanitize_showcase_url( $location['directionsUrl'] ?? '' ) );
			}
		}

		foreach ( array_slice( is_array( $navigation['calls'] ?? null ) ? $navigation['calls'] : array(), 0, 8 ) as $call ) {
			if ( is_array( $call ) ) {
				$clean['calls'][] = array( 'label' => sanitize_text_field( $call['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $call['url'] ?? '' ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize one editorial mega panel.
	 *
	 * @param array $panel Panel configuration.
	 * @return array
	 */
	private function sanitize_showcase_panel( $panel ) {
		$panel = is_array( $panel ) ? $panel : array();
		$clean = array();
		foreach ( array( 'introTitle', 'buttonText', 'accentText', 'promoTitle', 'promoSubtitle' ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $panel[ $key ] ?? '' );
		}
		$clean['introText']  = sanitize_textarea_field( $panel['introText'] ?? '' );
		$clean['promoImage'] = esc_url_raw( $panel['promoImage'] ?? '', array( 'http', 'https' ) );
		foreach ( array( 'buttonUrl', 'accentUrl', 'promoUrl' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $panel[ $key ] ?? '' );
		}
		$clean['cards'] = array();
		foreach ( array_slice( is_array( $panel['cards'] ?? null ) ? $panel['cards'] : array(), 0, 6 ) as $card ) {
			if ( is_array( $card ) ) {
				$clean['cards'][] = array( 'eyebrow' => sanitize_text_field( $card['eyebrow'] ?? '' ), 'title' => sanitize_text_field( $card['title'] ?? '' ), 'url' => $this->sanitize_showcase_url( $card['url'] ?? '' ), 'image' => esc_url_raw( $card['image'] ?? '', array( 'http', 'https' ) ) );
			}
		}
		return $clean;
	}

	/**
	 * Keep only public web and contact URL protocols.
	 *
	 * @param mixed $value Candidate URL.
	 * @return string
	 */
	private function sanitize_showcase_url( $value ) {
		$value = is_string( $value ) ? mb_substr( trim( $value ), 0, 2048 ) : '';
		if ( '#' === $value || preg_match( '/^#[A-Za-z][A-Za-z0-9_:.-]*$/', $value ) ) {
			return $value;
		}
		if ( 0 === strpos( $value, '//' ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Sanitize the Modern Mega header (single-row header with rich mega menus).
	 *
	 * @param array $settings Raw settings from the client.
	 * @return array
	 */
	private function sanitize_modern_mega_header_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array();

		$clean['logoText']      = sanitize_text_field( $settings['logoText'] ?? '' );
		$clean['logoAlt']       = sanitize_text_field( $settings['logoAlt'] ?? '' );
		$clean['logoImage']     = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
		$clean['logoImageSize'] = max( 30, min( 100, absint( $settings['logoImageSize'] ?? 100 ) ) );

		foreach ( array( 'homeUrl', 'searchUrl', 'accountUrl', 'cartUrl' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
		}

		foreach ( array( 'sticky', 'shrinkOnScroll', 'showSearch', 'showAccount', 'showCart' ) as $key ) {
			$clean[ $key ] = ! empty( $settings[ $key ] );
		}
		$clean['cartCount'] = max( 0, min( 99, absint( $settings['cartCount'] ?? 0 ) ) );

		foreach ( array( 'navBackground', 'navTextColor', 'accentColor', 'panelBackground', 'panelHeadingColor', 'panelLinkColor', 'mobileBackground', 'mobileTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['menuItems'] = $this->sanitize_modern_mega_menu_items( $settings['menuItems'] ?? array() );

		return $clean;
	}

	/**
	 * Sanitize the Modern Mega header's menu items (label/url + hasMega + columns
	 * with a layout, links, and a featured card). Counts are capped and each
	 * value is filtered by kind.
	 *
	 * @param mixed $items Raw menu items.
	 * @return array
	 */
	private function sanitize_modern_mega_menu_items( $items ) {
		$items   = is_array( $items ) ? $items : array();
		$layouts = array( 'links', 'cards', 'icons' );
		$icons   = array( 'sparkles', 'shield-check', 'lock', 'fingerprint', 'code', 'file-code', 'file-search', 'paintbrush', 'palette', 'layers', 'layout', 'columns', 'grid', 'briefcase', 'store', 'users', 'mail', 'form-input', 'bell', 'megaphone', 'clock', 'calendar', 'search', 'filter', 'zap', 'rocket', 'check', 'star', 'heart', 'globe', 'monitor', 'smartphone', 'file-text', 'settings', 'mouse-pointer', 'panel-top', 'wand', 'gauge', 'boxes' );

		$clean = array();
		foreach ( array_slice( $items, 0, 12 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$columns = array();
			foreach ( array_slice( is_array( $item['columns'] ?? null ) ? $item['columns'] : array(), 0, 6 ) as $column ) {
				if ( ! is_array( $column ) ) {
					continue;
				}
				$layout = in_array( $column['layout'] ?? '', $layouts, true ) ? $column['layout'] : 'links';
				$links  = array();
				foreach ( array_slice( is_array( $column['links'] ?? null ) ? $column['links'] : array(), 0, 12 ) as $link ) {
					if ( ! is_array( $link ) ) {
						continue;
					}
					$links[] = array(
						'label' => sanitize_text_field( $link['label'] ?? '' ),
						'url'   => $this->sanitize_showcase_url( $link['url'] ?? '' ),
						'image' => esc_url_raw( $link['image'] ?? '', array( 'http', 'https' ) ),
						'icon'  => in_array( $link['icon'] ?? '', $icons, true ) ? $link['icon'] : 'sparkles',
					);
				}
				$columns[] = array(
					'heading'      => sanitize_text_field( $column['heading'] ?? '' ),
					'layout'       => $layout,
					'imageLinks'   => 'cards' === $layout,
					'imageColumns' => max( 1, min( 4, absint( $column['imageColumns'] ?? 2 ) ) ),
					'links'        => $links,
				);
			}

			$banner_raw = is_array( $item['banner'] ?? null ) ? $item['banner'] : array();
			$banner     = array(
				'title'       => sanitize_text_field( $banner_raw['title'] ?? '' ),
				'text'        => sanitize_text_field( $banner_raw['text'] ?? '' ),
				'buttonLabel' => sanitize_text_field( $banner_raw['buttonLabel'] ?? '' ),
				'image'       => esc_url_raw( $banner_raw['image'] ?? '', array( 'http', 'https' ) ),
				'url'         => $this->sanitize_showcase_url( $banner_raw['url'] ?? '' ),
			);

			$clean[] = array(
				'label'   => sanitize_text_field( $item['label'] ?? '' ),
				'url'     => $this->sanitize_showcase_url( $item['url'] ?? '' ),
				'hasMega' => ! empty( $item['hasMega'] ),
				'columns' => $columns,
				'banner'  => $banner,
			);
		}

		return $clean;
	}

	/**
	 * Sanitize the Footer Commerce block (features bar, brand + social, two link
	 * columns, newsletter, and a bottom bar with locale, copyright, and payments).
	 *
	 * @param array $settings Raw settings from the client.
	 * @return array
	 */
	private function sanitize_footer_commerce_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array();

		foreach ( array( 'showFeatures', 'showNewsletter', 'showLocale', 'showPayments' ) as $key ) {
			$clean[ $key ] = ! empty( $settings[ $key ] );
		}

		foreach ( array( 'logoText', 'socialLabel', 'column1Heading', 'column2Heading', 'newsletterHeading', 'newsletterText', 'newsletterPlaceholder', 'newsletterButton', 'copyrightText', 'localeText', 'currencyText' ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
		}
		$clean['brandText'] = sanitize_textarea_field( $settings['brandText'] ?? '' );
		$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );

		foreach ( array( 'newsletterAction' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
		}

		// Newsletter form source: a simple email field, a DSF form, or an embedded
		// shortcode (e.g. a Gravity Form). The embed code is filtered through the
		// same shortcode/embed sanitizer the Form blocks use.
		$clean['newsletterSource']    = in_array( $settings['newsletterSource'] ?? '', array( 'inline', 'dsf', 'embed' ), true ) ? $settings['newsletterSource'] : 'inline';
		$clean['newsletterFormId']    = absint( $settings['newsletterFormId'] ?? 0 ) ? (string) absint( $settings['newsletterFormId'] ) : '';
		$clean['newsletterEmbedCode'] = $this->sanitize_form_embed_code( $settings['newsletterEmbedCode'] ?? '' );

		$clean['features']     = $this->sanitize_landing_icon_items( $settings['features'] ?? array() );
		$clean['socialLinks']  = $this->sanitize_footer_links( $settings['socialLinks'] ?? array() );
		$clean['column1Links'] = $this->sanitize_footer_links( $settings['column1Links'] ?? array() );
		$clean['column2Links'] = $this->sanitize_footer_links( $settings['column2Links'] ?? array() );
		$clean['payments']     = $this->sanitize_footer_payments( $settings['payments'] ?? array() );

		foreach ( array( 'background', 'textColor', 'headingColor', 'linkColor', 'accentColor', 'borderColor', 'bottomBackground' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize a simple {label,url} link list (capped).
	 *
	 * @param mixed $links Raw links.
	 * @return array
	 */
	private function sanitize_footer_links( $links ) {
		$clean = array();
		foreach ( array_slice( is_array( $links ) ? $links : array(), 0, 12 ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$clean[] = array(
				'label' => sanitize_text_field( $link['label'] ?? '' ),
				'url'   => $this->sanitize_showcase_url( $link['url'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Sanitize payment badges: a {name, logo, url} repeater (capped).
	 *
	 * @param mixed $payments Raw payment badges.
	 * @return array
	 */
	private function sanitize_footer_payments( $payments ) {
		$clean = array();
		foreach ( array_slice( is_array( $payments ) ? $payments : array(), 0, 12 ) as $pay ) {
			if ( ! is_array( $pay ) ) {
				continue;
			}
			$clean[] = array(
				'name' => sanitize_text_field( $pay['name'] ?? '' ),
				'logo' => esc_url_raw( $pay['logo'] ?? '', array( 'http', 'https' ) ),
				'url'  => $this->sanitize_showcase_url( $pay['url'] ?? '' ),
			);
		}
		return $clean;
	}

	private function sanitize_popup_settings( $popup ) {
		return DSF_Popup::sanitize_settings( $popup );
	}

	private function sanitize_page_theme_settings( $theme ) {
		$theme    = is_array( $theme ) ? $theme : array();
		$defaults = DSF_Frontend::get_default_theme_settings();
		$clean    = array();

		foreach ( array( 'primaryColor', 'secondaryColor', 'textColor', 'backgroundColor' ) as $key ) {
			$clean[ $key ] = sanitize_hex_color( $theme[ $key ] ?? '' ) ?: $defaults[ $key ];
		}
		$clean['headingFont'] = DSF_Frontend::sanitize_font_family( $theme['headingFont'] ?? $defaults['headingFont'] );
		$clean['bodyFont']    = DSF_Frontend::sanitize_font_family( $theme['bodyFont'] ?? $defaults['bodyFont'] );

		return $clean;
	}

	private function sanitize_page_layout_settings( $layout ) {
		$layout = is_array( $layout ) ? $layout : array();

		return array(
			'containerWidth'   => min( 1800, max( 1000, absint( $layout['containerWidth'] ?? 1800 ) ) ),
			'contentPadding'   => min( 64, absint( $layout['contentPadding'] ?? 10 ) ),
			'showHeader'       => ! isset( $layout['showHeader'] ) || (bool) $layout['showHeader'],
			'showFooter'       => ! isset( $layout['showFooter'] ) || (bool) $layout['showFooter'],
			'headerTemplateId' => absint( $layout['headerTemplateId'] ?? 0 ),
			'footerTemplateId' => absint( $layout['footerTemplateId'] ?? 0 ),
			'template'         => 'fullwidth' === ( $layout['template'] ?? '' ) ? 'fullwidth' : 'default',
		);
	}

	private function get_valid_page_parent_id( $parent_id, $post_id ) {
		$parent_id = (int) $parent_id;
		$post_id   = (int) $post_id;

		if ( $parent_id <= 0 || $parent_id === $post_id ) {
			return 0;
		}

		if ( 'page' !== get_post_type( $parent_id ) ) {
			return 0;
		}

		$ancestor_ids = get_post_ancestors( $parent_id );
		if ( in_array( $post_id, array_map( 'intval', $ancestor_ids ), true ) ) {
			return 0;
		}

		return $parent_id;
	}

	private function sanitize_snapshot_html( $html ) {
		// Strip every HTML comment before sanitization. The snapshot is a
		// first-paint placeholder that Vue replaces on mount, so comments serve
		// no purpose — and a stray "-->" inside user WYSIWYG content can
		// prematurely close a Vue placeholder comment, leaking unclosed tags
		// that corrupt the DOM tree and produce phantom unstyled duplicates
		// of the last blocks after #dsf-frontend-app is closed early.
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', (string) $html );

		$allowed = wp_kses_allowed_html( 'post' );
		$extra   = array(
			'svg'      => array(
				'class'           => true,
				'viewBox'         => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'role'            => true,
				'data-*'          => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'data-*'          => true,
			),
			'circle'   => array(
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'rect'     => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'line'     => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
				'data-*' => true,
			),
			'polyline' => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'polygon'  => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'g'        => array(
				'class'  => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'div'      => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'section'  => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'span'     => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'p'        => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h1'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h2'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h3'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h4'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h5'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h6'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'a'        => array(
				'class'      => true,
				'style'      => true,
				'href'       => true,
				'target'     => true,
				'rel'        => true,
				'aria-label' => true,
				'data-*'     => true,
			),
			'img'      => array(
				'class'   => true,
				'style'   => true,
				'src'     => true,
				'alt'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
				'data-*'  => true,
			),
			'button'   => array(
				'class'      => true,
				'style'      => true,
				'type'       => true,
				'aria-label' => true,
				'data-*'     => true,
			),
			'input'    => array(
				'class'       => true,
				'style'       => true,
				'type'        => true,
				'value'       => true,
				'placeholder' => true,
				'name'        => true,
				'data-*'      => true,
			),
		);

		foreach ( $extra as $tag => $attrs ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ] = array_merge( $allowed[ $tag ], $attrs );
		}

		return wp_kses( $html, $allowed );
	}

	/**
	 * Render a shortcode for modal content (frontend)
	 */
	public function render_shortcode() {
		if ( ! check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$shortcode = isset( $_POST['shortcode'] ) ? wp_unslash( $_POST['shortcode'] ) : '';

		if ( ! is_string( $shortcode ) || '' === $shortcode ) {
			wp_send_json_error( array( 'message' => 'Missing shortcode' ) );
		}

		// Bound the payload — this endpoint is reachable unauthenticated.
		if ( strlen( $shortcode ) > 20000 ) {
			wp_send_json_error( array( 'message' => 'Shortcode is too long.' ), 400 );
		}

		// This endpoint runs do_shortcode() on request input and is callable by
		// unauthenticated visitors, so restrict execution to a curated allowlist of
		// display/embed shortcodes. Site owners can extend it via the filter. Only
		// registered shortcodes execute, so we only need to vet the ones present.
		$allowed = apply_filters(
			'dsf_render_shortcode_allowed_tags',
			array(
				'dsform',
				'gravityform',
				'gravityforms',
				'contact-form-7',
				'wpforms',
				'ninja_form',
				'ninja_forms',
				'formidable',
				'caldera_form',
				'fluentform',
				'forminator_form',
				'wpforms_selector',
			)
		);

		if ( is_array( $allowed ) ) {
			preg_match_all( '/' . get_shortcode_regex() . '/', $shortcode, $matches );
			$used = array_filter( array_unique( isset( $matches[2] ) ? $matches[2] : array() ) );
			foreach ( $used as $tag ) {
				if ( ! in_array( $tag, $allowed, true ) ) {
					wp_send_json_error( array( 'message' => 'This shortcode is not allowed.' ), 403 );
				}
			}
		}

		$html = do_shortcode( $shortcode );
		$html = wp_kses_post( $html );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Get products by category or IDs (Hybrid Logic: Pinned First)
	 */
	public function get_products() {
		$editor_nonce_ok   = check_ajax_referer( 'dsf_editor_nonce', 'nonce', false );
		$frontend_nonce_ok = check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false );

		if ( ! $editor_nonce_ok && ! $frontend_nonce_ok ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( $editor_nonce_ok ) {
			$this->verify_permissions();
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$category_ids = isset( $_POST['category_ids'] ) ? $this->normalize_numeric_id_list( $_POST['category_ids'] ) : array();
		$source       = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : 'category';

		if ( empty( $category_ids ) && isset( $_POST['category_id'] ) ) {
			$category_ids = $this->normalize_numeric_id_list( $_POST['category_id'] );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? $this->normalize_numeric_id_list( $_POST['product_ids'] ) : array();

		$products = array();

		// If Manual Source OR we have Pinned products to show first
		if ( ! empty( $product_ids ) ) {
			$pinned_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'post__in'       => $product_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1, // Get all pinned
			);

			if ( 'manual' !== $source && ! empty( $category_ids ) ) {
				$pinned_args['tax_query'] = array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $category_ids,
						'include_children' => true,
					),
				);
			}

			$pinned_posts = get_posts( $pinned_args );

			// If Manual Source, we ONLY show pinned products (filtered by what exists)
			if ( 'manual' === $source ) {
				$products = $pinned_posts;
			} else {
				// If Category Source, Pinned products come first, then fill with category
				$products = $pinned_posts;
			}
		}

		// If Category Source fetch all remaining products
		if ( 'manual' !== $source ) {
			$seen_product_ids = array_map( 'intval', wp_list_pluck( $products, 'ID' ) );

			if ( empty( $category_ids ) ) {
				$cat_posts = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'post__not_in'   => $product_ids, // Exclude pinned to avoid duplicates
					)
				);

				$products = array_merge( $products, $cat_posts );
			} else {
				foreach ( $category_ids as $category_id ) {
					$cat_posts = get_posts(
						array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'post__not_in'   => array_values( array_unique( array_merge( $product_ids, $seen_product_ids ) ) ),
							'tax_query'      => array(
								array(
									'taxonomy'         => 'product_cat',
									'field'            => 'term_id',
									'terms'            => $category_id,
									'include_children' => true,
								),
							),
						)
					);

					if ( empty( $cat_posts ) ) {
						continue;
					}

					$products         = array_merge( $products, $cat_posts );
					$seen_product_ids = array_values(
						array_unique(
							array_merge(
								$seen_product_ids,
								array_map( 'intval', wp_list_pluck( $cat_posts, 'ID' ) )
							)
						)
					);
				}
			}
		}

		// Format Result
		$result = array();
		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			// Ensure Image URL
			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
			$price     = $product->get_price();
			$regular   = $product->get_regular_price();
			$sale      = $product->get_sale_price();

			$price_display   = '' !== $price ? html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) ) : '';
			$regular_display = '' !== $regular ? html_entity_decode( wp_strip_all_tags( wc_price( $regular ) ) ) : '';
			$sale_display    = '' !== $sale ? html_entity_decode( wp_strip_all_tags( wc_price( $sale ) ) ) : '';

			$cat_terms    = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			$cat_term_ids = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			$tag_terms    = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );

			$result[] = array(
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'price'           => $price_display,
				'regularPrice'    => $regular_display,
				'salePrice'       => $sale_display,
				'price_html'      => $product->get_price_html(),
				'regular_price'   => $regular_display,
				'sale_price'      => $sale_display,
				'image'           => $image_url,
				'permalink'       => $product->get_permalink(),
				'add_to_cart_url' => $product->add_to_cart_url(),
				'product_type'    => $product->get_type(),
				'stock_status'    => $product->get_stock_status(),
				'price_num'       => (float) $product->get_price(),
				'rating'          => round( (float) $product->get_average_rating(), 1 ),
				'categories'      => is_wp_error( $cat_terms ) ? array() : $cat_terms,
				'category_ids'    => is_wp_error( $cat_term_ids ) ? array() : array_map( 'intval', (array) $cat_term_ids ),
				'tags'            => is_wp_error( $tag_terms ) ? array() : $tag_terms,
				'attributes'      => $this->get_product_filter_attributes( $product ),
			);
		}

		wp_send_json_success( array( 'products' => $result ) );
	}

	/**
	 * Build a normalized attribute map for client-side filtering.
	 * Keys are lowercase attribute labels (e.g. "brand", "material", "color").
	 *
	 * @param WC_Product $product
	 * @return array<string, string[]>
	 */
	private function get_product_filter_attributes( $product ) {
		$attrs = array();

		// Standard WooCommerce product attributes (pa_* taxonomies + custom)
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$label  = wc_attribute_label( $attribute->get_name() );
				$terms  = wp_get_post_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
				$values = is_wp_error( $terms ) ? array() : (array) $terms;
			} else {
				$label  = $attribute->get_name();
				$values = array_map( 'trim', $attribute->get_options() );
			}

			if ( ! empty( $values ) ) {
				$key           = strtolower( str_replace( ' ', '_', $label ) );
				$attrs[ $key ] = array_values( array_filter( $values ) );
			}
		}

		// WooCommerce Brands plugin (product_brand taxonomy).
		// Merge into the 'brand' key so the Vue filter picks them up automatically.
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brand_terms = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
				$existing       = isset( $attrs['brand'] ) ? $attrs['brand'] : array();
				$attrs['brand'] = array_values( array_unique( array_merge( $existing, (array) $brand_terms ) ) );
			}
		}

		return $attrs;
	}

	/**
	 * Search products
	 */
	public function search_products() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$search       = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category_ids = isset( $_POST['category_ids'] ) ? $this->normalize_numeric_id_list( $_POST['category_ids'] ) : array();

		if ( empty( $category_ids ) && isset( $_POST['category_id'] ) ) {
			$category_ids = $this->normalize_numeric_id_list( $_POST['category_id'] );
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $search,
		);

		if ( ! empty( $category_ids ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $category_ids,
					'include_children' => true,
				),
			);
		}

		$products = get_posts( $args );
		$result   = array();

		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			$result[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => $product->get_price_html(),
				'image' => wp_get_attachment_url( $product->get_image_id() ),
			);
		}

		wp_send_json_success( array( 'products' => $result ) );
	}

	/**
	 * Get WooCommerce categories
	 */
	public function get_categories() {
		$editor_nonce_ok   = check_ajax_referer( 'dsf_editor_nonce', 'nonce', false );
		$frontend_nonce_ok = check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false );

		if ( ! $editor_nonce_ok && ! $frontend_nonce_ok ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( $editor_nonce_ok ) {
			$this->verify_permissions();
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $categories ) ) {
			wp_send_json_error( array( 'message' => $categories->get_error_message() ) );
		}

		$result = array_map(
			function ( $cat ) {
				$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );

				return array(
					'id'       => $cat->term_id,
					'name'     => $cat->name,
					'slug'     => $cat->slug,
					'url'      => get_term_link( $cat ),
					'count'    => $cat->count,
					'image'    => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
					'imageAlt' => $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
				);
			},
			$categories
		);

		wp_send_json_success( array( 'categories' => $result ) );
	}

	/**
	 * Handle image upload
	 */
	public function upload_image() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		// Uploading media requires its own capability beyond editing pages.
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * Update page title
	 */
	public function update_title() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';

		if ( ! $post_id || ! $title ) {
			wp_send_json_error( array( 'message' => 'Invalid data' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);

		wp_send_json_success( array( 'message' => 'Title updated' ) );
	}

	/**
	 * Publish page
	 */
	public function publish_page() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
		}

		if ( ! current_user_can( 'publish_post', $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		wp_send_json_success(
			array(
				'message'   => 'Page published',
				'permalink' => get_permalink( $post_id ),
			)
		);
	}
}
