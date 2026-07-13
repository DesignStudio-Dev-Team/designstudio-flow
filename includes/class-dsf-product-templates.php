<?php
/**
 * Reusable WooCommerce single-product templates for DesignStudio Flow.
 *
 * A Product Template is a site-wide single-product page design (theme-builder
 * style). Each template is assigned to all products or to specific product
 * categories; its blocks bind to whichever product is being viewed. This class
 * resolves the active template for a product and builds the live product data
 * payload that product blocks render from. WooCommerce's own template files are
 * never modified — product data is read through the stable wc_get_product() API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Product_Templates {

	const POST_TYPE = 'dsf_product_template';

	private static $instance = null;

	/** Per-request cache of product_id => resolved template_id. */
	private $resolved_cache = array();

	/** Per-request cache of the active-template query (null until first use). */
	private $active_templates = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Whether the DesignStudio Flow editor is enabled for WooCommerce products.
	 *
	 * Controlled by the "Products" toggle in Settings → General. Defaults to on so
	 * existing product-template setups keep working after an update.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( 'dsf_products_enabled', true );
	}

	private function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_edit_link' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Resolve the best matching active, published product template for a product.
	 *
	 * Category-targeted templates win over "all products" templates; newest wins
	 * ties. Returns 0 when WooCommerce is inactive, the post is not a product, or
	 * no template matches. Filterable via `dsf_product_template_id`.
	 *
	 * @param int $product_id Product post ID.
	 * @return int Template post ID, or 0.
	 */
	public function resolve_template_for_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return 0;
		}

		if ( isset( $this->resolved_cache[ $product_id ] ) ) {
			return $this->resolved_cache[ $product_id ];
		}

		$template_id = 0;
		if ( self::is_enabled() && class_exists( 'WooCommerce' ) && 'product' === get_post_type( $product_id ) ) {
			$template_id = $this->find_matching_template( $product_id );
		}

		/**
		 * Filter the resolved product template ID for a product.
		 *
		 * @param int $template_id Resolved template post ID (0 = none).
		 * @param int $product_id  Product post ID.
		 */
		$template_id = (int) apply_filters( 'dsf_product_template_id', $template_id, $product_id );

		$this->resolved_cache[ $product_id ] = $template_id;
		return $template_id;
	}

	/**
	 * Find the active template whose assignment matches the product.
	 *
	 * @param int $product_id Product post ID.
	 * @return int
	 */
	private function find_matching_template( $product_id ) {
		if ( null === $this->active_templates ) {
			$this->active_templates = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					'meta_key'       => '_dsf_pt_active', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
		}
		$templates = $this->active_templates;

		if ( empty( $templates ) ) {
			return 0;
		}

		$product_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$product_cats = is_wp_error( $product_cats ) ? array() : array_map( 'intval', $product_cats );

		$category_match = 0;
		$all_match      = 0;

		foreach ( $templates as $template ) {
			$assignment = self::get_assignment( $template->ID );

			if ( 'categories' === $assignment['mode'] ) {
				if ( ! $category_match && ! empty( $assignment['categoryIds'] ) && array_intersect( $assignment['categoryIds'], $product_cats ) ) {
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
		$raw  = get_post_meta( $template_id, '_dsf_pt_assignment', true );
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
				// Cap at a sane number of categories and keep only positive ints.
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

	/** Per-request cache of built product contexts, keyed by id + fragment flags. */
	private static $context_cache = array();

	/**
	 * Build the live product data payload that product blocks render from.
	 *
	 * All HTML fields are passed through wp_kses_post() (or a Woo-form-aware
	 * allowlist for the cart/reviews fragments) before they reach the browser;
	 * plain-text fields are sanitized. Returns an empty array when WooCommerce is
	 * inactive or the product cannot be loaded.
	 *
	 * @param int   $product_id Product post ID.
	 * @param array $args       { 'add_to_cart' => bool, 'reviews' => bool, 'related' => bool,
	 *                          'upsells' => bool } — include the heavier server-rendered
	 *                          Woo fragments / card lists.
	 * @return array
	 */
	public static function build_product_context( $product_id, $args = array() ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$args = array_merge(
			array(
				'add_to_cart' => true,
				'reviews'     => false,
				'related'     => false,
				'upsells'     => false,
			),
			is_array( $args ) ? $args : array()
		);

		$cache_key = $product_id . '|' . ( $args['add_to_cart'] ? '1' : '0' ) . '|' . ( $args['reviews'] ? '1' : '0' ) . '|' . ( $args['related'] ? '1' : '0' ) . '|' . ( $args['upsells'] ? '1' : '0' );
		if ( isset( self::$context_cache[ $cache_key ] ) ) {
			return self::$context_cache[ $cache_key ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$short_description = function_exists( 'wc_format_content' )
			? wc_format_content( (string) $product->get_short_description() )
			: wpautop( do_shortcode( (string) $product->get_short_description() ) );

		$description = function_exists( 'wc_format_content' )
			? wc_format_content( (string) $product->get_description() )
			: wpautop( do_shortcode( (string) $product->get_description() ) );

		$context = array(
			'id'                   => $product->get_id(),
			'name'                 => sanitize_text_field( $product->get_name() ),
			'permalink'            => get_permalink( $product->get_id() ),
			'sku'                  => sanitize_text_field( (string) $product->get_sku() ),
			'type'                 => sanitize_key( $product->get_type() ),
			'priceHtml'            => wp_kses_post( (string) $product->get_price_html() ),
			'shortDescriptionHtml' => wp_kses_post( (string) apply_filters( 'woocommerce_short_description', $short_description ) ),
			'descriptionHtml'      => wp_kses_post( (string) $description ),
			'gallery'              => self::build_gallery( $product ),
			'specs'                => self::build_specs( $product ),
			'stockStatus'          => sanitize_key( (string) $product->get_stock_status() ),
			'stockQuantity'        => null === $product->get_stock_quantity() ? null : (int) $product->get_stock_quantity(),
			'isInStock'            => (bool) $product->is_in_stock(),
			'onSale'               => (bool) $product->is_on_sale(),
			'isPurchasable'        => (bool) $product->is_purchasable(),
			'averageRating'        => (float) $product->get_average_rating(),
			'ratingCount'          => (int) $product->get_rating_count(),
			'reviewCount'          => (int) $product->get_review_count(),
			'categories'           => self::build_term_list( $product->get_id(), 'product_cat' ),
			'tags'                 => self::build_term_list( $product->get_id(), 'product_tag' ),
			'addToCartHtml'        => $args['add_to_cart'] ? self::build_add_to_cart_html( $product ) : '',
			'reviewsHtml'          => $args['reviews'] ? self::build_reviews_html( $product ) : '',
			'relatedProducts'      => $args['related'] ? self::build_related_products( $product ) : array(),
			'upsellProducts'       => $args['upsells'] ? self::build_upsell_products( $product ) : array(),
		);

		self::$context_cache[ $cache_key ] = $context;
		return $context;
	}

	/**
	 * Build a lightweight card payload for the product's related products.
	 *
	 * @param WC_Product $product Product object.
	 * @return array[]
	 */
	private static function build_related_products( $product ) {
		if ( ! function_exists( 'wc_get_related_products' ) ) {
			return array();
		}

		return self::build_product_cards( wc_get_related_products( $product->get_id(), 8 ) );
	}

	/**
	 * Build a lightweight card payload for the product's upsells ("You may also
	 * like" products chosen by the merchant on the product edit screen).
	 *
	 * @param WC_Product $product Product object.
	 * @return array[]
	 */
	private static function build_upsell_products( $product ) {
		return self::build_product_cards( array_slice( (array) $product->get_upsell_ids(), 0, 8 ) );
	}

	/**
	 * Build sanitized product cards (name, price, image, permalink, rating, cart
	 * link) from a list of product IDs. Hidden/missing products are skipped.
	 * Public so the shop/archive templates reuse the same card contract.
	 *
	 * @param array $product_ids Candidate product IDs.
	 * @return array[]
	 */
	public static function build_product_cards( $product_ids ) {
		$cards = array();

		foreach ( array_map( 'absint', (array) $product_ids ) as $card_id ) {
			$card_product = $card_id ? wc_get_product( $card_id ) : null;
			if ( ! $card_product || ! $card_product->is_visible() ) {
				continue;
			}

			$image_id  = (int) $card_product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
			if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
				$image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}

			// A "quick add" only makes sense when adding needs no option choices.
			$quick_add = $card_product->is_purchasable()
				&& $card_product->is_in_stock()
				&& $card_product->is_type( 'simple' );

			$cards[] = array(
				'id'            => $card_product->get_id(),
				'name'          => sanitize_text_field( $card_product->get_name() ),
				'permalink'     => get_permalink( $card_product->get_id() ),
				'priceHtml'     => wp_kses_post( (string) $card_product->get_price_html() ),
				'image'         => esc_url_raw( (string) $image_url ),
				'imageAlt'      => $image_id ? sanitize_text_field( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) : '',
				'onSale'        => (bool) $card_product->is_on_sale(),
				'averageRating' => (float) $card_product->get_average_rating(),
				'ratingCount'   => (int) $card_product->get_rating_count(),
				'addToCartUrl'  => $quick_add ? esc_url_raw( (string) $card_product->add_to_cart_url() ) : '',
			);
		}

		return $cards;
	}

	/**
	 * Build a sanitized {name, url} list of the product's terms in a taxonomy
	 * (categories or tags), capped at a sane count.
	 *
	 * @param int    $product_id Product post ID.
	 * @param string $taxonomy   'product_cat' or 'product_tag'.
	 * @return array[]
	 */
	private static function build_term_list( $product_id, $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$list = array();
		foreach ( array_slice( $terms, 0, 20 ) as $term ) {
			if ( ! isset( $term->name ) ) {
				continue;
			}
			$link   = get_term_link( $term );
			$list[] = array(
				'name' => sanitize_text_field( (string) $term->name ),
				'url'  => is_wp_error( $link ) ? '' : esc_url_raw( (string) $link ),
			);
		}

		return $list;
	}

	/**
	 * Capture WooCommerce's native single add-to-cart form for a product.
	 *
	 * Rendering Woo's own template (rather than reimplementing the form) keeps
	 * quantity, variation selectors, stock, nonces, and AJAX behavior intact and
	 * update-safe. The output is first-party Woo markup, but it is still run through
	 * a Woo-form-aware wp_kses() allowlist as defense in depth before it reaches the
	 * browser. Calling the template during wp_enqueue_scripts also enqueues the
	 * variation script when the product is variable.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private static function build_add_to_cart_html( $product ) {
		if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
			return '';
		}

		// Swap the global $product so Woo's template renders this product, then
		// restore it. Capturing the template also enqueues the variation script.
		$previous           = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		$GLOBALS['product'] = $product;

		ob_start();
		woocommerce_template_single_add_to_cart();
		$html = (string) ob_get_clean();

		$GLOBALS['product'] = $previous;

		return self::sanitize_woo_form_html( $html );
	}

	/**
	 * Capture WooCommerce's product reviews (list + review form) for a product.
	 *
	 * Only meaningful on the frontend single-product request (comments_template
	 * relies on the queried object); the editor passes reviews => false and shows a
	 * placeholder instead. Output is sanitized with the same Woo-form allowlist.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private static function build_reviews_html( $product ) {
		if ( ! function_exists( 'comments_template' ) || ! comments_open( $product->get_id() ) ) {
			return '';
		}

		$previous_product   = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		$GLOBALS['product'] = $product;

		ob_start();
		// WooCommerce ships its own reviews template; fall back to the theme's.
		$template = locate_template( 'single-product-reviews.php' );
		comments_template( $template ? '/single-product-reviews.php' : '/comments.php' );
		$html = (string) ob_get_clean();

		$GLOBALS['product'] = $previous_product;

		return self::sanitize_woo_form_html( $html );
	}

	/**
	 * Sanitize captured WooCommerce form/markup with a bounded allowlist that keeps
	 * the controls (inputs, selects, the variation data attribute) intact.
	 *
	 * Public so other server-captured Woo fragments (cart, checkout, account —
	 * see DSF_Store_Pages) reuse the same allowlist. `$extra_tags` merges
	 * additional tag => attribute-array entries for those richer fragments;
	 * script/style tags are never accepted.
	 *
	 * @param string $html       Raw captured markup.
	 * @param array  $extra_tags Additional kses tag definitions to allow.
	 * @return string
	 */
	public static function sanitize_woo_form_html( $html, $extra_tags = array() ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}

		$common = array_fill_keys( array( 'class', 'id', 'style', 'title', 'role', 'hidden', 'data-*', 'aria-*' ), true );

		// Each tag's allowed attributes = the common set plus its own (as a list of
		// names). array_fill_keys keeps this readable and standards-compliant.
		$attr = static function ( array $extra ) use ( $common ) {
			return array_merge( $common, array_fill_keys( $extra, true ) );
		};

		$allowed = array(
			'form'     => $attr( array( 'action', 'method', 'enctype', 'name', 'novalidate' ) ),
			'input'    => $attr( array( 'type', 'name', 'value', 'placeholder', 'min', 'max', 'step', 'size', 'maxlength', 'pattern', 'inputmode', 'required', 'readonly', 'disabled', 'checked', 'autocomplete' ) ),
			'select'   => $attr( array( 'name', 'multiple', 'required', 'disabled' ) ),
			'option'   => $attr( array( 'value', 'selected', 'disabled' ) ),
			'textarea' => $attr( array( 'name', 'rows', 'cols', 'placeholder', 'required', 'maxlength' ) ),
			'label'    => $attr( array( 'for' ) ),
			'button'   => $attr( array( 'type', 'name', 'value', 'disabled' ) ),
			'table'    => $common,
			'tbody'    => $common,
			'thead'    => $common,
			'tfoot'    => $common,
			'tr'       => $common,
			'td'       => $attr( array( 'colspan', 'rowspan' ) ),
			'th'       => $attr( array( 'colspan', 'rowspan', 'scope' ) ),
			'div'      => $common,
			'section'  => $common,
			'span'     => $common,
			'p'        => $common,
			'small'    => $common,
			'strong'   => $common,
			'em'       => $common,
			'b'        => $common,
			'i'        => $common,
			'br'       => array(),
			'ul'       => $common,
			'ol'       => $attr( array( 'reversed', 'start' ) ),
			'li'       => $common,
			'h2'       => $common,
			'h3'       => $common,
			'a'        => $attr( array( 'href', 'target', 'rel' ) ),
			'img'      => $attr( array( 'src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding' ) ),
			'time'     => $attr( array( 'datetime' ) ),
			'abbr'     => $attr( array( 'title' ) ),
		);

		if ( is_array( $extra_tags ) && $extra_tags ) {
			// Never allow executable tags through, whatever the caller passes.
			unset( $extra_tags['script'], $extra_tags['style'], $extra_tags['iframe'], $extra_tags['object'], $extra_tags['embed'] );
			$allowed = array_merge( $allowed, $extra_tags );
		}

		return wp_kses( $html, $allowed, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Build the gallery image list (featured image first).
	 *
	 * @param WC_Product $product Product object.
	 * @return array[]
	 */
	private static function build_gallery( $product ) {
		$image_ids = array();

		$featured = (int) $product->get_image_id();
		if ( $featured ) {
			$image_ids[] = $featured;
		}
		foreach ( (array) $product->get_gallery_image_ids() as $gid ) {
			$gid = (int) $gid;
			if ( $gid && ! in_array( $gid, $image_ids, true ) ) {
				$image_ids[] = $gid;
			}
		}

		$gallery = array();
		foreach ( $image_ids as $img_id ) {
			$full = wp_get_attachment_image_url( $img_id, 'full' );
			if ( ! $full ) {
				continue;
			}
			$large  = wp_get_attachment_image_url( $img_id, 'large' );
			$thumb  = wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
			$srcset = wp_get_attachment_image_srcset( $img_id, 'large' );

			$gallery[] = array(
				'id'     => $img_id,
				'full'   => esc_url_raw( $full ),
				'large'  => esc_url_raw( $large ? $large : $full ),
				'thumb'  => esc_url_raw( $thumb ? $thumb : ( $large ? $large : $full ) ),
				'srcset' => $srcset ? esc_attr( $srcset ) : '',
				'alt'    => sanitize_text_field( (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ),
			);
		}

		if ( empty( $gallery ) && function_exists( 'wc_placeholder_img_src' ) ) {
			$placeholder = wc_placeholder_img_src( 'large' );
			$gallery[]   = array(
				'id'     => 0,
				'full'   => esc_url_raw( $placeholder ),
				'large'  => esc_url_raw( $placeholder ),
				'thumb'  => esc_url_raw( $placeholder ),
				'srcset' => '',
				'alt'    => '',
			);
		}

		return $gallery;
	}

	/**
	 * Build the specs list from visible product attributes plus weight/dimensions.
	 *
	 * @param WC_Product $product Product object.
	 * @return array[]
	 */
	private static function build_specs( $product ) {
		$specs = array();

		foreach ( (array) $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
				continue;
			}

			$label = function_exists( 'wc_attribute_label' )
				? wc_attribute_label( $attribute->get_name(), $product )
				: $attribute->get_name();

			if ( $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ) {
				$terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
				$value = implode( ', ', array_map( 'sanitize_text_field', (array) $terms ) );
			} else {
				$value = implode( ', ', array_map( 'sanitize_text_field', (array) $attribute->get_options() ) );
			}

			if ( '' === trim( $value ) ) {
				continue;
			}

			$specs[] = array(
				'name'  => sanitize_text_field( $label ),
				'value' => $value,
			);
		}

		if ( $product->has_weight() && function_exists( 'wc_format_weight' ) ) {
			$specs[] = array(
				'name'  => __( 'Weight', 'designstudio-flow' ),
				'value' => sanitize_text_field( wp_strip_all_tags( wc_format_weight( $product->get_weight() ) ) ),
			);
		}
		if ( $product->has_dimensions() && function_exists( 'wc_format_dimensions' ) ) {
			$specs[] = array(
				'name'  => __( 'Dimensions', 'designstudio-flow' ),
				'value' => sanitize_text_field( wp_strip_all_tags( wc_format_dimensions( $product->get_dimensions( false ) ) ) ),
			);
		}

		return $specs;
	}

	/**
	 * Add "Edit with DesignStudio Flow" to the product-template row actions.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_edit_link( $actions, $post ) {
		if ( ! $post || ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		if ( self::POST_TYPE === $post->post_type ) {
			$url                 = admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $post->ID ) );
			$actions['dsf_edit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</a>';
			return $actions;
		}

		// Product rows: shortcut to the DSF product template that styles this
		// product (products are designed via a shared template, not per product).
		if ( 'product' === $post->post_type && class_exists( 'WooCommerce' ) ) {
			$template_id = $this->resolve_template_for_product( $post->ID );
			if ( $template_id && current_user_can( 'edit_post', $template_id ) ) {
				$url                          = admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $template_id ) );
				$actions['dsf_edit_template'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit Product Template (DS Flow)', 'designstudio-flow' ) . '</a>';
			}
		}

		return $actions;
	}

	/**
	 * Add Status + Applies-to columns to the product-template list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$reordered = array();
		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$reordered['dsf_pt_status']  = __( 'Status', 'designstudio-flow' );
				$reordered['dsf_pt_applies'] = __( 'Applies To', 'designstudio-flow' );
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
		if ( 'dsf_pt_status' === $column ) {
			$active = '1' === (string) get_post_meta( $post_id, '_dsf_pt_active', true );
			echo $active
				? '<span class="dsf-pt-active">' . esc_html__( 'Live', 'designstudio-flow' ) . '</span>'
				: '<span aria-hidden="true">' . esc_html__( 'Inactive', 'designstudio-flow' ) . '</span>';
			return;
		}

		if ( 'dsf_pt_applies' === $column ) {
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
				echo esc_html__( 'All products', 'designstudio-flow' );
			}
		}
	}
}
