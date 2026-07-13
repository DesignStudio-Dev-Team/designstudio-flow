<?php
/**
 * Reusable WooCommerce shop / product-archive templates for DesignStudio Flow.
 *
 * A Shop Template is a site-wide design for the shop page and product
 * category/tag archives (theme-builder style). Each template applies to the
 * whole catalog or to specific product categories; its blocks bind to whichever
 * archive is being viewed, reading the products from the main query WooCommerce
 * has already prepared (visibility, ordering, pagination all respected).
 * WooCommerce's own template files are never modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Shop_Templates {

	const POST_TYPE = 'dsf_shop_template';

	private static $instance = null;

	/** Per-request cache of the active-template query (null until first use). */
	private $active_templates = null;

	/** Per-request cache of the resolved template for the current archive. */
	private $resolved_current = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_edit_link' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Resolve the active, published shop template for the current archive request.
	 *
	 * Category-targeted templates win on matching category archives; "entire
	 * catalog" templates cover the shop page, tag archives, and the rest. Returns
	 * 0 when WooCommerce is inactive, products are disabled in settings, or no
	 * template matches. Filterable via `dsf_shop_template_id`.
	 *
	 * @return int Template post ID, or 0.
	 */
	public function resolve_template_for_current_archive() {
		if ( null !== $this->resolved_current ) {
			return $this->resolved_current;
		}

		$template_id = 0;
		if ( DSF_Product_Templates::is_enabled() && class_exists( 'WooCommerce' ) && self::is_product_archive() ) {
			$template_id = $this->find_matching_template();
		}

		/**
		 * Filter the resolved shop template ID for the current archive.
		 *
		 * @param int $template_id Resolved template post ID (0 = none).
		 */
		$template_id = (int) apply_filters( 'dsf_shop_template_id', $template_id );

		$this->resolved_current = $template_id;
		return $template_id;
	}

	/**
	 * Whether the current request is a WooCommerce product archive.
	 *
	 * @return bool
	 */
	public static function is_product_archive() {
		return ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
	}

	/**
	 * Find the active template whose assignment matches the current archive.
	 *
	 * @return int
	 */
	private function find_matching_template() {
		if ( null === $this->active_templates ) {
			$this->active_templates = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					'meta_key'       => '_dsf_st_active', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
		}
		$templates = $this->active_templates;

		if ( empty( $templates ) ) {
			return 0;
		}

		$current_cat = 0;
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term        = get_queried_object();
			$current_cat = ( $term && isset( $term->term_id ) ) ? (int) $term->term_id : 0;
		}

		$category_match = 0;
		$all_match      = 0;

		foreach ( $templates as $template ) {
			$assignment = self::get_assignment( $template->ID );

			if ( 'categories' === $assignment['mode'] ) {
				if ( ! $category_match && $current_cat && in_array( $current_cat, $assignment['categoryIds'], true ) ) {
					$category_match = (int) $template->ID;
				}
			} elseif ( ! $all_match ) {
				$all_match = (int) $template->ID;
			}
		}

		return $category_match ? $category_match : $all_match;
	}

	/**
	 * Read and normalize a template's assignment rule.
	 *
	 * @param int $template_id Template post ID.
	 * @return array{mode:string,categoryIds:int[]}
	 */
	public static function get_assignment( $template_id ) {
		$raw  = get_post_meta( $template_id, '_dsf_st_assignment', true );
		$mode = 'all';
		$cats = array();

		if ( is_array( $raw ) ) {
			$mode = ( isset( $raw['mode'] ) && 'categories' === $raw['mode'] ) ? 'categories' : 'all';
			if ( isset( $raw['categoryIds'] ) && is_array( $raw['categoryIds'] ) ) {
				$cats = array_values( array_unique( array_filter( array_map( 'absint', $raw['categoryIds'] ) ) ) );
			}
		}

		return array(
			'mode'        => $mode,
			'categoryIds' => $cats,
		);
	}

	/**
	 * Sanitize an assignment rule submitted from the editor.
	 *
	 * @param mixed $raw Submitted assignment.
	 * @return array{mode:string,categoryIds:int[]}
	 */
	public static function sanitize_assignment( $raw ) {
		$mode = 'all';
		$cats = array();

		if ( is_array( $raw ) ) {
			$mode = ( isset( $raw['mode'] ) && 'categories' === $raw['mode'] ) ? 'categories' : 'all';
			if ( isset( $raw['categoryIds'] ) && is_array( $raw['categoryIds'] ) ) {
				$cats = array_slice(
					array_values( array_unique( array_filter( array_map( 'absint', $raw['categoryIds'] ) ) ) ),
					0,
					100
				);
			}
		}

		if ( 'categories' === $mode && empty( $cats ) ) {
			// A category rule with no categories can never match — treat as "all".
			$mode = 'all';
		}

		return array(
			'mode'        => $mode,
			'categoryIds' => $cats,
		);
	}

	/**
	 * The catalog sort options offered by the shop-products block (WooCommerce's
	 * standard catalog ordering values — handled by WC_Query on the main query).
	 *
	 * @return array[] {value,label} pairs.
	 */
	public static function get_orderby_options() {
		return array(
			array(
				'value' => 'menu_order',
				'label' => __( 'Default sorting', 'designstudio-flow' ),
			),
			array(
				'value' => 'popularity',
				'label' => __( 'Sort by popularity', 'designstudio-flow' ),
			),
			array(
				'value' => 'rating',
				'label' => __( 'Sort by average rating', 'designstudio-flow' ),
			),
			array(
				'value' => 'date',
				'label' => __( 'Sort by latest', 'designstudio-flow' ),
			),
			array(
				'value' => 'price',
				'label' => __( 'Sort by price: low to high', 'designstudio-flow' ),
			),
			array(
				'value' => 'price-desc',
				'label' => __( 'Sort by price: high to low', 'designstudio-flow' ),
			),
		);
	}

	/**
	 * Build the live archive payload the shop blocks render from, using the main
	 * query WooCommerce has already run for this archive request.
	 *
	 * @return array
	 */
	public static function build_archive_context_from_main_query() {
		global $wp_query;

		if ( ! class_exists( 'WooCommerce' ) || ! $wp_query instanceof WP_Query ) {
			return array();
		}

		$product_ids = array();
		foreach ( (array) $wp_query->posts as $post ) {
			if ( $post instanceof WP_Post && 'product' === $post->post_type ) {
				$product_ids[] = (int) $post->ID;
			}
		}

		$title = function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : get_the_archive_title();

		$description = '';
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$description = term_description();
		}

		$per_page     = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
		$current_page = max( 1, (int) get_query_var( 'paged' ) );
		$total_pages  = max( 1, (int) $wp_query->max_num_pages );

		// The current catalog ordering (validated against the known option values).
		$requested = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only catalog ordering.
		$allowed   = wp_list_pluck( self::get_orderby_options(), 'value' );
		if ( ! in_array( $requested, $allowed, true ) ) {
			$requested = (string) get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );
			$requested = in_array( $requested, $allowed, true ) ? $requested : 'menu_order';
		}

		return array(
			'title'           => sanitize_text_field( (string) $title ),
			'descriptionHtml' => wp_kses_post( (string) $description ),
			'products'        => DSF_Product_Templates::build_product_cards( $product_ids ),
			'total'           => (int) $wp_query->found_posts,
			'perPage'         => $per_page,
			'currentPage'     => $current_page,
			'totalPages'      => $total_pages,
			'pagination'      => self::build_pagination( $current_page, $total_pages ),
			'orderby'         => $requested,
			'orderbyOptions'  => self::get_orderby_options(),
			'categories'      => self::build_category_links(),
			'priceFilter'     => self::build_price_filter_state(),
		);
	}

	/**
	 * Product-category links for the filters block (top-level categories, capped).
	 *
	 * @return array[] {name,url,count,current} entries.
	 */
	private static function build_category_links() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => 20,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$current_id = 0;
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term       = get_queried_object();
			$current_id = ( $term && isset( $term->term_id ) ) ? (int) $term->term_id : 0;
		}

		$links = array();
		foreach ( $terms as $term ) {
			$link    = get_term_link( $term );
			$links[] = array(
				'name'    => sanitize_text_field( (string) $term->name ),
				'url'     => is_wp_error( $link ) ? '' : esc_url_raw( (string) $link ),
				'count'   => (int) $term->count,
				'current' => (int) $term->term_id === $current_id,
			);
		}

		return $links;
	}

	/**
	 * The current min/max price filter values (WooCommerce's own layered-nav GET
	 * params, handled by WC_Query on the main query) plus the form action.
	 *
	 * @return array{min:string,max:string,action:string}
	 */
	private static function build_price_filter_state() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only catalog filter.
		$min = isset( $_GET['min_price'] ) ? (float) wp_unslash( $_GET['min_price'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only catalog filter.
		$max = isset( $_GET['max_price'] ) ? (float) wp_unslash( $_GET['max_price'] ) : null;

		$action = remove_query_arg( array( 'min_price', 'max_price', 'paged' ) );

		return array(
			'min'    => ( null !== $min && $min >= 0 && is_finite( $min ) ) ? (string) $min : '',
			'max'    => ( null !== $max && $max >= 0 && is_finite( $max ) ) ? (string) $max : '',
			'action' => esc_url_raw( home_url( (string) $action ) ),
		);
	}

	/**
	 * Build sanitized pagination links for the current archive (capped so a huge
	 * catalog cannot bloat the payload).
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_pages  Total pages.
	 * @return array[] {label,url,current} entries.
	 */
	private static function build_pagination( $current_page, $total_pages ) {
		$total_pages = min( 50, max( 1, (int) $total_pages ) );
		if ( $total_pages < 2 ) {
			return array();
		}

		$links = array();
		for ( $page = 1; $page <= $total_pages; $page++ ) {
			$links[] = array(
				'label'   => (string) $page,
				'url'     => esc_url_raw( get_pagenum_link( $page ) ),
				'current' => $page === (int) $current_page,
			);
		}

		return $links;
	}

	/**
	 * Build a sample archive payload for the editor preview (the editor is not an
	 * archive request, so this queries a representative product set directly).
	 *
	 * @param int $term_id Preview product-category term ID (0 = whole catalog).
	 * @return array|null
	 */
	public static function build_preview_context( $term_id = 0 ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
			return null;
		}

		$term_id = absint( $term_id );
		$title   = __( 'Shop', 'designstudio-flow' );
		$args    = array(
			'status'  => 'publish',
			'limit'   => 12,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'ids',
		);

		$description = '';
		if ( $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$title            = $term->name;
				$description      = term_description( $term );
				$args['category'] = array( $term->slug );
			}
		}

		$product_ids = wc_get_products( $args );
		$cards       = DSF_Product_Templates::build_product_cards( is_array( $product_ids ) ? $product_ids : array() );

		return array(
			'title'           => sanitize_text_field( (string) $title ),
			'descriptionHtml' => wp_kses_post( (string) $description ),
			'products'        => $cards,
			'total'           => count( $cards ),
			'perPage'         => 12,
			'currentPage'     => 1,
			'totalPages'      => 1,
			'pagination'      => array(),
			'orderby'         => 'menu_order',
			'orderbyOptions'  => self::get_orderby_options(),
			'categories'      => self::build_category_links(),
			'priceFilter'     => array(
				'min'    => '',
				'max'    => '',
				'action' => '',
			),
		);
	}

	/**
	 * Add "Edit with DesignStudio Flow" to the shop-template row actions.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_edit_link( $actions, $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		$url                 = admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $post->ID ) );
		$actions['dsf_edit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</a>';
		return $actions;
	}

	/**
	 * Add Status + Applies-to columns to the shop-template list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$reordered = array();
		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$reordered['dsf_st_status']  = __( 'Status', 'designstudio-flow' );
				$reordered['dsf_st_applies'] = __( 'Applies To', 'designstudio-flow' );
			}
		}
		return $reordered;
	}

	/**
	 * Render the Status / Applies-to column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'dsf_st_status' === $column ) {
			$active = '1' === (string) get_post_meta( $post_id, '_dsf_st_active', true );
			echo $active
				? '<span class="dsf-pt-active">' . esc_html__( 'Live', 'designstudio-flow' ) . '</span>'
				: '<span aria-hidden="true">' . esc_html__( 'Inactive', 'designstudio-flow' ) . '</span>';
			return;
		}

		if ( 'dsf_st_applies' === $column ) {
			$assignment = self::get_assignment( $post_id );
			if ( 'categories' === $assignment['mode'] ) {
				$names = array();
				foreach ( $assignment['categoryIds'] as $cat_id ) {
					$term = get_term( $cat_id, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				echo $names
					? esc_html( implode( ', ', $names ) )
					: esc_html__( 'Selected categories', 'designstudio-flow' );
			} else {
				echo esc_html__( 'Entire catalog', 'designstudio-flow' );
			}
		}
	}
}
