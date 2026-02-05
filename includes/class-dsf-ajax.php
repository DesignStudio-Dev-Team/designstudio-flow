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

		// Get products
		add_action( 'wp_ajax_dsf_get_products', array( $this, 'get_products' ) );

		// Search products
		add_action( 'wp_ajax_dsf_search_products', array( $this, 'search_products' ) );

		// Get categories
		add_action( 'wp_ajax_dsf_get_categories', array( $this, 'get_categories' ) );

		// Upload image
		add_action( 'wp_ajax_dsf_upload_image', array( $this, 'upload_image' ) );

		// Update page title
		add_action( 'wp_ajax_dsf_update_title', array( $this, 'update_title' ) );

		// Publish page
		add_action( 'wp_ajax_dsf_publish_page', array( $this, 'publish_page' ) );

		// Render shortcode for modal content
		add_action( 'wp_ajax_dsf_render_shortcode', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_nopriv_dsf_render_shortcode', array( $this, 'render_shortcode' ) );
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

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
		}

		// Validate JSON
		$blocks_raw  = wp_unslash( $blocks );
		$blocks_data = json_decode( $blocks_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid blocks JSON data: ' . json_last_error_msg() ) );
		}

		$settings_raw  = wp_unslash( $settings );
		$settings_data = json_decode( $settings_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid settings JSON data: ' . json_last_error_msg() ) );
		}

		// Save meta as arrays (avoids JSON escaping issues)
		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );
		if ( '' !== $html_snapshot ) {
			update_post_meta( $post_id, '_dsf_html_snapshot', $this->sanitize_snapshot_html( $html_snapshot ) );
		}

		$post_type = get_post_type( $post_id );
		if ( 'page' === $post_type ) {
			update_post_meta( $post_id, '_dsf_enabled', true );
		}

		// Update modified time and status (if requested)
		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		if ( 'draft' === $status ) {
			$current_status = get_post_status( $post_id );
			if ( $current_status && 'publish' !== $current_status ) {
				$post_update['post_status'] = 'draft';
			}
		}

		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message' => 'Page saved successfully',
				'post_id' => $post_id,
			)
		);
	}

	private function sanitize_snapshot_html( $html ) {
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
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'circle'   => array(
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
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
			),
			'line'     => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
			),
			'polyline' => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			),
			'polygon'  => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			),
			'g'        => array(
				'class'  => true,
				'fill'   => true,
				'stroke' => true,
			),
			'div'      => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'section'  => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'span'     => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'p'        => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h1'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h2'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h3'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h4'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h5'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'h6'       => array(
				'class' => true,
				'style' => true,
				'id'    => true,
			),
			'a'        => array(
				'class'      => true,
				'style'      => true,
				'href'       => true,
				'target'     => true,
				'rel'        => true,
				'aria-label' => true,
			),
			'img'      => array(
				'class'   => true,
				'style'   => true,
				'src'     => true,
				'alt'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
			),
			'button'   => array(
				'class'      => true,
				'style'      => true,
				'type'       => true,
				'aria-label' => true,
			),
			'input'    => array(
				'class'       => true,
				'style'       => true,
				'type'        => true,
				'value'       => true,
				'placeholder' => true,
				'name'        => true,
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

		if ( ! $shortcode ) {
			wp_send_json_error( array( 'message' => 'Missing shortcode' ) );
		}

		$html = do_shortcode( $shortcode );
		$html = wp_kses_post( $html );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Get products by category or IDs (Hybrid Logic: Pinned First)
	 */
	public function get_products() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
		$limit       = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 12;
		$source      = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : 'category';

		// Handle product_ids sent as JSON string or Array
		$product_ids = array();
		if ( isset( $_POST['product_ids'] ) ) {
			$raw_ids = $_POST['product_ids'];
			if ( is_string( $raw_ids ) ) {
				$decoded = json_decode( wp_unslash( $raw_ids ), true );
				if ( is_array( $decoded ) ) {
					$product_ids = array_map( 'intval', $decoded );
				}
			} elseif ( is_array( $raw_ids ) ) {
				$product_ids = array_map( 'intval', $raw_ids );
			}
		}

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

			$pinned_posts = get_posts( $pinned_args );

			// If Manual Source, we ONLY show pinned products (filtered by what exists)
			if ( 'manual' === $source ) {
				$products = $pinned_posts;
			} else {
				// If Category Source, Pinned products come first, then fill with category
				$products = $pinned_posts;
			}
		}

		// If Category Source and we need more products (or have no pins)
		if ( 'manual' !== $source && count( $products ) < $limit ) {
			$remaining = $limit - count( $products );

			if ( $remaining > 0 ) {
				$cat_args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $remaining,
					'post__not_in'   => $product_ids, // Exclude pinned to avoid duplicates
				);

				if ( $category_id ) {
					$cat_args['tax_query'] = array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $category_id,
						),
					);
				}

				$cat_posts = get_posts( $cat_args );
				$products  = array_merge( $products, $cat_posts );
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

			$result[] = array(
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'price'           => $product->get_price(),
				'price_html'      => $product->get_price_html(),
				'regular_price'   => $product->get_regular_price(),
				'sale_price'      => $product->get_sale_price(),
				'image'           => $image_url,
				'permalink'       => $product->get_permalink(),
				'add_to_cart_url' => $product->add_to_cart_url(),
				'stock_status'    => $product->get_stock_status(),
			);
		}

		wp_send_json_success( array( 'products' => $result ) );
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

		$search      = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $search,
		);

		if ( $category_id ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_id,
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
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

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
					'id'    => $cat->term_id,
					'name'  => $cat->name,
					'slug'  => $cat->slug,
					'url'   => get_term_link( $cat ),
					'count' => $cat->count,
					'image' => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
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
