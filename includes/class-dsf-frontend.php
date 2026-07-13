<?php
/**
 * Frontend rendering for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Frontend {

	private static $instance = null;

	/**
	 * Track which posts have had their Flow blocks rendered,
	 * so the_content filter does not output native content a second time.
	 */
	private $rendered_posts = array();

	/** Per-request cache for get_page_settings() results. */
	private $settings_cache = array();

	/** Whether the current request is a Flow frontend page (set during enqueue). */
	private $is_flow_page = false;

	/** Whether the current Flow page uses landing blocks (loader-gated render). */
	private $current_is_landing = false;

	/** Current Flow post ID (set during enqueue, for head hints/filters). */
	private $current_post_id = 0;

	/** Resolved product template ID for the current product request (set in load_flow_template). */
	private $current_product_template_id = 0;

	/** Whether the current request is a non-DSF page using whole-site header/footer mode. */
	private $is_global_hf_page = false;

	/** First image URL found in the page blocks, preloaded as the LCP candidate. */
	private $hero_image_url = '';

	/** Store context (Woo cart/checkout/account fragments) built during enqueue. */
	private $store_context = null;

	/** Resolved shop template ID for the current archive request. */
	private $current_shop_template_id = 0;

	/** Archive context (products, pagination, sorting) built during enqueue. */
	private $current_archive_context = array();

	/** Resolved blog template ID for the current archive request. */
	private $current_blog_template_id = 0;

	/** Blog archive context (posts, pagination) built during enqueue. */
	private $current_blog_archive_context = array();

	/** Site context (login/search/dashboard payload) built during enqueue. */
	private $site_context = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'the_content', array( $this, 'render_flow_content' ), 20 );
		// Keep the DSF mini-cart block in sync after Woo's AJAX add-to-cart.
		add_filter( 'woocommerce_add_to_cart_fragments', array( 'DSF_Store_Pages', 'add_mini_cart_fragments' ) );
		// Use priority 20 to ensure the main query is fully set up before we check for Flow pages.
		// This fixes asset loading for non-logged-in users where get_queried_object_id() may return 0
		// at the default priority (10) due to the query not being initialized yet.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
		// Strip WordPress/theme defaults a self-contained Flow page never uses.
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_default_bloat' ), 100 );
		add_filter( 'template_include', array( $this, 'load_flow_template' ), 99 );
		add_action( 'template_redirect', array( $this, 'redirect_legacy_flow_urls' ), 1 );
		add_filter( 'body_class', array( $this, 'add_flow_theme_body_classes' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
		add_filter( 'style_loader_tag', array( $this, 'make_styles_non_blocking' ), 10, 4 );
		// Print resource hints and critical CSS early in <head> on Flow pages.
		add_action( 'wp_head', array( $this, 'print_performance_hints' ), 2 );
		add_filter( 'dsf_flow_show_header', array( $this, 'filter_show_header' ), 10, 2 );
		add_filter( 'dsf_flow_show_footer', array( $this, 'filter_show_footer' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_edit_link' ), 80 );
	}

	/**
	 * Add Flow-specific theme markers so targeted CSS fixes can be scoped to a
	 * known active theme instead of becoming global form behavior.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_flow_theme_body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();

		if ( $this->is_gravity_form_theme_repair_active() ) {
			$classes[] = 'dsf-theme-form-repair-active';
			$classes[] = 'dsf-theme-dsnshowcase-active';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Whether the current site needs targeted Gravity Forms theme-repair CSS.
	 *
	 * @return bool
	 */
	private function is_gravity_form_theme_repair_active() {
		$template    = function_exists( 'get_template' ) ? get_template() : '';
		$stylesheet  = function_exists( 'get_stylesheet' ) ? get_stylesheet() : '';
		$theme_slugs = array( 'dsnshowcase', 'dsnshowcase-child' );

		if ( function_exists( 'apply_filters' ) ) {
			$theme_slugs = apply_filters( 'dsf_flow_gravity_form_theme_repair_slugs', $theme_slugs );
		}

		$theme_slugs = array_filter( array_map( array( $this, 'sanitize_theme_slug' ), (array) $theme_slugs ) );

		return in_array( $this->sanitize_theme_slug( $template ), $theme_slugs, true )
			|| in_array( $this->sanitize_theme_slug( $stylesheet ), $theme_slugs, true );
	}

	/**
	 * Normalize a theme slug without relying on WordPress helpers in isolated tests.
	 *
	 * @param mixed $slug Theme slug.
	 * @return string
	 */
	private function sanitize_theme_slug( $slug ) {
		$slug = strtolower( (string) $slug );
		return preg_replace( '/[^a-z0-9_\-]/', '', $slug );
	}

	/**
	 * Add a frontend admin bar shortcut into the DS Flow editor.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WordPress admin bar instance.
	 */
	public function add_admin_bar_edit_link( $wp_admin_bar ) {
		if ( is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		// Product pages: link to the DSF product template that styles this product.
		if ( is_singular( 'product' ) ) {
			$product_id  = get_queried_object_id();
			$template_id = $product_id ? $this->resolve_product_template_for_post( $product_id ) : 0;
			if ( ! $template_id || ! current_user_can( 'edit_pages' ) || ! current_user_can( 'edit_post', $template_id ) ) {
				return;
			}

			$wp_admin_bar->add_node(
				array(
					'id'    => 'dsf-edit-with-flow',
					'title' => __( 'DS Flow', 'designstudio-flow' ),
					'href'  => admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $template_id ) ),
					'meta'  => array(
						'title' => __( 'Edit Product Template with DesignStudio Flow', 'designstudio-flow' ),
					),
				)
			);
			return;
		}

		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || ! current_user_can( 'edit_pages' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'dsf-edit-with-flow',
				'title' => __( 'DS Flow', 'designstudio-flow' ),
				'href'  => admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $post_id ) ),
				'meta'  => array(
					'title' => __( 'Edit with DesignStudio Flow', 'designstudio-flow' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Try get_queried_object_id() first, then fall back to global $post.
		// This ensures we load assets correctly for all users, including when
		// caching plugins or certain themes don't initialize the query fully.
		$post_id = get_queried_object_id();

		// Fallback to global $post if get_queried_object_id() returns 0
		if ( ! $post_id ) {
			global $post;
			if ( $post instanceof WP_Post ) {
				$post_id = $post->ID;
			}
		}

		// Shop flow: a Woo product archive (shop page, category, tag) that resolves
		// to an active DSF shop template. On taxonomy archives the queried object is
		// a term, so this is decided before any post checks.
		$shop_template_id = DSF_Shop_Templates::get_instance()->resolve_template_for_current_archive();
		$is_shop_flow     = ( 0 !== $shop_template_id );

		// Blog flow: the posts page or a post archive (category, tag, author, date)
		// that resolves to an active DSF blog template.
		$blog_template_id = DSF_Blog_Templates::get_instance()->resolve_template_for_current_archive();
		$is_blog_flow     = ( ! $is_shop_flow && 0 !== $blog_template_id );

		if ( ! $post_id && ! $is_shop_flow && ! $is_blog_flow ) {
			return;
		}

		$current_post = $post_id ? get_post( $post_id ) : null;
		if ( ! $current_post && ! $is_shop_flow && ! $is_blog_flow ) {
			return;
		}

		// Page flow: a Flow-enabled WordPress page. Product flow: a single-product
		// page that resolves to an active DSF product template. Product blocks read
		// their data from the current product; their layout comes from the template.
		$is_page_flow        = ! $is_shop_flow && ! $is_blog_flow && $current_post && ( 'page' === $current_post->post_type ) && get_post_meta( $post_id, '_dsf_enabled', true );
		$product_template_id = ( $is_page_flow || $is_shop_flow || $is_blog_flow || ! $current_post ) ? 0 : $this->resolve_product_template_for_post( $post_id );
		$is_product_flow     = ( 0 !== $product_template_id );
		// Whole-site header/footer: a non-DSF page/post that should still get the
		// site header/footer (no DSF page blocks — just the header/footer apps).
		$is_global_hf = ( ! $is_page_flow && ! $is_product_flow && ! $is_shop_flow && ! $is_blog_flow && $this->should_apply_global_hf( $post_id ) );

		if ( ! $is_page_flow && ! $is_product_flow && ! $is_global_hf && ! $is_shop_flow && ! $is_blog_flow ) {
			return;
		}

		// Blocks/settings come from the resolved template on product/archive
		// requests, the page itself otherwise.
		$blocks_source_id = $post_id;
		if ( $is_product_flow ) {
			$blocks_source_id = $product_template_id;
		} elseif ( $is_shop_flow ) {
			$blocks_source_id = $shop_template_id;
		} elseif ( $is_blog_flow ) {
			$blocks_source_id = $blog_template_id;
		}

		// Production or development mode
		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		$main_css_version       = $this->get_asset_version( 'assets/css/main.css' );
		$frontend_theme_version = $this->get_asset_version( 'assets/css/frontend.css' );
		$frontend_js_version    = $this->get_asset_version( 'assets/js/frontend.js' );

		$blocks_meta = get_post_meta( $blocks_source_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$blocks = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			if ( ! is_array( $blocks ) ) {
				$blocks = array();
			}
		}

		$current_product = array();
		if ( $is_product_flow ) {
			$fragment_needs  = $this->product_blocks_need_fragments( $blocks );
			$current_product = DSF_Product_Templates::build_product_context(
				$post_id,
				array(
					'add_to_cart' => $fragment_needs['add_to_cart'],
					'reviews'     => $fragment_needs['reviews'],
					'related'     => $fragment_needs['related'],
					'upsells'     => $fragment_needs['upsells'],
				)
			);
			$this->enqueue_woo_product_scripts( $fragment_needs, $post_id );
		}

		// Store blocks (cart / checkout / account / steps) on a DSF page: capture
		// Woo's fragments now — running the shortcodes during enqueue also lets
		// payment gateways enqueue their own scripts — and never let this response
		// be page-cached (a cart is per-visitor).
		$store_needs = array(
			'any'   => false,
			'steps' => false,
		);
		if ( $is_page_flow ) {
			$store_needs = DSF_Store_Pages::blocks_need_fragments( $blocks );
			if ( $store_needs['any'] || $store_needs['steps'] ) {
				$this->store_context = DSF_Store_Pages::build_store_context( $store_needs );
				DSF_Store_Pages::enqueue_store_scripts( $store_needs );

				if ( $store_needs['any'] ) {
					if ( ! defined( 'DONOTCACHEPAGE' ) ) {
						define( 'DONOTCACHEPAGE', true );
					}
					if ( ! headers_sent() ) {
						nocache_headers();
					}
				}
			}
		}

		// Site blocks (login / search / dashboard) on a DSF page: build their
		// per-request payload. These are personalized (or query-specific), so the
		// response must never be page-cached.
		if ( $is_page_flow ) {
			$site_needs = DSF_Site_Pages::blocks_need_context( $blocks );
			if ( $site_needs['any'] ) {
				$this->site_context = DSF_Site_Pages::build_site_context( $site_needs, $post_id );
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', true );
				}
				if ( ! headers_sent() ) {
					nocache_headers();
				}
			}
		}

		// Shop flow: build the archive payload from the main query WooCommerce has
		// already prepared for this request (products, ordering, pagination).
		$current_archive = array();
		if ( $is_shop_flow ) {
			$this->current_shop_template_id = $shop_template_id;
			$current_archive                = DSF_Shop_Templates::build_archive_context_from_main_query();
			$this->current_archive_context  = $current_archive;
		}

		// Blog flow: build the archive payload from the main query WordPress has
		// already prepared for this request (posts, ordering, pagination).
		$current_blog_archive = array();
		if ( $is_blog_flow ) {
			$this->current_blog_template_id     = $blog_template_id;
			$current_blog_archive               = DSF_Blog_Templates::build_archive_context_from_main_query();
			$this->current_blog_archive_context = $current_blog_archive;
		}

		// Record state for the style/head filters (non-blocking CSS, hints).
		$this->is_flow_page       = true;
		$this->is_global_hf_page  = $is_global_hf;
		$this->current_post_id    = $post_id;
		$this->current_is_landing = ( $is_product_flow || $is_shop_flow || $is_blog_flow ) ? false : $this->blocks_use_landing( $blocks );
		if ( $is_product_flow ) {
			$this->hero_image_url = isset( $current_product['gallery'][0]['full'] ) ? $current_product['gallery'][0]['full'] : '';
		} elseif ( $is_shop_flow ) {
			$this->hero_image_url = isset( $current_archive['products'][0]['image'] ) ? $current_archive['products'][0]['image'] : '';
		} elseif ( $is_blog_flow ) {
			$this->hero_image_url = isset( $current_blog_archive['posts'][0]['image'] ) ? $current_blog_archive['posts'][0]['image'] : '';
		} else {
			$this->hero_image_url = $this->find_hero_image_url( $blocks );
		}

		$blocks        = $this->prepare_blocks_for_frontend( $blocks );
		$page_settings = $this->get_page_settings( $blocks_source_id );

		$layout_templates = $this->get_assigned_layout_templates_data( $blocks_source_id );
		$layout_templates = $this->prepare_layout_templates_for_frontend( $layout_templates );

		wp_enqueue_style(
			'dsf-main',
			DSF_PLUGIN_URL . 'assets/css/main.css',
			array(),
			$main_css_version
		);

		wp_enqueue_style(
			'dsf-frontend',
			DSF_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'dsf-main' ),
			$frontend_theme_version
		);

		if ( $is_dev ) {
			wp_enqueue_script(
				'dsf-frontend-vite',
				'http://localhost:5173/@vite/client',
				array(),
				DSF_VERSION,
				true
			);
			wp_enqueue_script(
				'dsf-frontend-app',
				'http://localhost:5173/src/frontend/main.js',
				array( 'dsf-frontend-vite' ),
				DSF_VERSION,
				true
			);
		} else {
			wp_enqueue_script(
				'dsf-frontend-app',
				DSF_PLUGIN_URL . 'assets/js/frontend.js',
				array(),
				$frontend_js_version,
				true
			);
		}

		$frontend_data = array(
			'postId'             => $post_id,
			'pluginUrl'          => DSF_PLUGIN_URL,
			'blocks'             => $blocks,
			'blockCatalog'       => $this->get_frontend_block_catalog(),
			'popup'              => DSF_Popup::resolve_page_popup( $page_settings ),
			'layoutTemplates'    => $layout_templates,
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'dsf_frontend_nonce' ),
			'categories'         => $this->get_wc_categories(),
			'productTags'        => $this->get_wc_product_tags(),
			'isWooActive'        => class_exists( 'WooCommerce' ),
			'wcAjaxUrl'          => class_exists( 'WooCommerce' ) ? \WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
			'wcCartNonce'        => class_exists( 'WooCommerce' ) ? wp_create_nonce( 'woocommerce-process_checkout' ) : '',
			'currentProduct'     => $is_product_flow ? $current_product : null,
			'productTemplateId'  => $is_product_flow ? $product_template_id : 0,
			'currentArchive'     => $is_shop_flow ? $current_archive : null,
			'shopTemplateId'     => $is_shop_flow ? $shop_template_id : 0,
			'currentBlogArchive' => $is_blog_flow ? $current_blog_archive : null,
			'blogTemplateId'     => $is_blog_flow ? $blog_template_id : 0,
			'storeContext'       => $this->build_store_context_for_js(),
			'siteContext'        => $this->site_context,
			'breadcrumbs'        => class_exists( 'DSF_SEO' ) ? DSF_SEO::get_instance()->get_current_breadcrumb_trail() : array(),
		);

		wp_localize_script( 'dsf-frontend-app', 'dsfFrontendData', $frontend_data );

		wp_add_inline_script(
			'dsf-frontend-app',
			'window.dsfEditorData = window.dsfEditorData || window.dsfFrontendData || {};',
			'before'
		);

		// Load any add-on block scripts after the frontend bundle so their runtime
		// preview components register against window.dsfFlow before/after mount.
		if ( class_exists( 'DSF_Blocks' ) ) {
			DSF_Blocks::enqueue_addon_block_assets( 'dsf-frontend-app' );
		}

		// Enqueue Google Fonts if custom fonts are set
		$this->enqueue_google_fonts( $blocks_source_id );
	}

	/**
	 * Return the page-scope block summaries used by the public library.
	 *
	 * The editor and the landing library must share one source of truth, but
	 * the frontend should never receive full block schemas or settings.
	 *
	 * @return array
	 */
	private function get_frontend_block_catalog() {
		$catalog = array();
		$blocks  = DSF_Blocks::get_instance()->get_registered_blocks();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || 'page' !== ( $block['template_scope'] ?? 'page' ) || ! empty( $block['preset_only'] ) ) {
				continue;
			}

			$id = sanitize_key( $block['id'] ?? '' );
			if ( ! $id ) {
				continue;
			}

			$catalog[] = array(
				'id'             => $id,
				'name'           => sanitize_text_field( $block['name'] ?? $id ),
				'category'       => sanitize_key( $block['category'] ?? 'content' ),
				'description'    => substr( wp_trim_words( wp_strip_all_tags( (string) ( $block['description'] ?? '' ) ), 32, '…' ), 0, 240 ),
				'icon'           => sanitize_key( $block['icon'] ?? 'layout-template' ),
				'template_scope' => 'page',
			);
		}

		return $catalog;
	}

	/**
	 * The slim store context localized for the Vue store blocks: URLs, the current
	 * purchase step, and WHICH fragments exist. The fragment HTML itself is never
	 * localized — it is printed once as live DOM and adopted in place on mount.
	 *
	 * @return array|null
	 */
	private function build_store_context_for_js() {
		if ( null === $this->store_context ) {
			return null;
		}

		return array(
			'urls'      => isset( $this->store_context['urls'] ) ? $this->store_context['urls'] : array(),
			'step'      => isset( $this->store_context['step'] ) ? $this->store_context['step'] : '',
			'fragments' => array_keys( isset( $this->store_context['fragments'] ) && is_array( $this->store_context['fragments'] ) ? $this->store_context['fragments'] : array() ),
			'miniCart'  => isset( $this->store_context['miniCart'] ) ? $this->store_context['miniCart'] : null,
		);
	}

	/**
	 * Decide which heavier Woo fragments the template's blocks actually need, so we
	 * only render the add-to-cart form / reviews when a block uses them.
	 *
	 * @param array $blocks Raw template blocks.
	 * @return array{add_to_cart:bool,reviews:bool,related:bool,upsells:bool}
	 */
	private function product_blocks_need_fragments( $blocks ) {
		$needs = array(
			'add_to_cart' => false,
			'reviews'     => false,
			'related'     => false,
			'upsells'     => false,
		);

		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type     = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			$settings = ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array();

			if ( 'product-add-to-cart' === $type ) {
				$needs['add_to_cart'] = true;
			}

			// The hero and spotlight embed the add-to-cart form unless it is toggled off.
			if ( in_array( $type, array( 'product-hero', 'product-spotlight' ), true ) && false !== ( $settings['showAddToCart'] ?? true ) ) {
				$needs['add_to_cart'] = true;
			}

			if ( 'product-related' === $type ) {
				$needs['related'] = true;
			}

			if ( 'product-upsells' === $type ) {
				$needs['upsells'] = true;
			}

			if ( 'product-reviews' === $type ) {
				$needs['reviews'] = true;
			}

			if ( 'product-tabs' === $type ) {
				$tabs = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();
				foreach ( $tabs as $tab ) {
					if ( is_array( $tab ) && 'reviews' === ( $tab['source'] ?? '' ) ) {
						$needs['reviews'] = true;
						break;
					}
				}
			}
		}

		return $needs;
	}

	/**
	 * Enqueue the WooCommerce frontend scripts the product blocks rely on.
	 *
	 * @param array $needs      Fragment needs from product_blocks_need_fragments().
	 * @param int   $product_id Current product ID.
	 */
	private function enqueue_woo_product_scripts( $needs, $product_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! empty( $needs['add_to_cart'] ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			wp_enqueue_script( 'wc-single-product' );

			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( $product && $product->is_type( 'variable' ) ) {
				wp_enqueue_script( 'wc-add-to-cart-variation' );
			}
		}

		if ( ! empty( $needs['reviews'] ) ) {
			wp_enqueue_script( 'wc-single-product' );
			if ( get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
		}
	}

	/**
	 * Whether whole-site header/footer mode should wrap the current request.
	 *
	 * Opt-in (Settings → Theme). Applies to singular Pages and Posts the theme
	 * would normally render — NOT DSF pages (handled elsewhere), products,
	 * WooCommerce utility pages, or non-singular views (archives/search/404).
	 *
	 * @param int $post_id Queried post ID.
	 * @return bool
	 */
	public function should_apply_global_hf( $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id || is_admin() || ! is_singular() ) {
			return false;
		}
		if ( ! (bool) get_option( 'dsf_global_header_footer', false ) ) {
			return false;
		}
		// Need at least one default header/footer configured.
		if ( ! self::get_default_layout_id( 'header' ) && ! self::get_default_layout_id( 'footer' ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return false;
		}
		// DSF-enabled pages already render the header/footer via their own template.
		if ( 'page' === $post_type && get_post_meta( $post_id, '_dsf_enabled', true ) ) {
			return false;
		}
		// WooCommerce cart/checkout/account/shop need their own templates.
		if ( $this->is_woocommerce_utility_page( $post_id ) ) {
			return false;
		}

		/**
		 * Filter whether whole-site header/footer mode applies to a given request.
		 *
		 * @param bool $apply   Whether to wrap this request.
		 * @param int  $post_id Queried post ID.
		 */
		return (bool) apply_filters( 'dsf_apply_global_header_footer', true, $post_id );
	}

	/**
	 * Whether a post is a WooCommerce cart/checkout/account/shop page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_woocommerce_utility_page( $post_id ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}
		$ids = array_map(
			'intval',
			array(
				wc_get_page_id( 'cart' ),
				wc_get_page_id( 'checkout' ),
				wc_get_page_id( 'myaccount' ),
				wc_get_page_id( 'shop' ),
			)
		);
		return in_array( (int) $post_id, $ids, true );
	}

	/**
	 * Get the whole-site header/footer flag for the current request (for the template).
	 *
	 * @return bool
	 */
	public function is_global_hf_request() {
		return (bool) $this->is_global_hf_page;
	}

	/**
	 * Resolve the active DSF product template for a single-product request.
	 *
	 * @param int $post_id Queried post ID.
	 * @return int Product template ID, or 0.
	 */
	private function resolve_product_template_for_post( $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id || ! class_exists( 'WooCommerce' ) || 'product' !== get_post_type( $post_id ) ) {
			return 0;
		}

		return DSF_Product_Templates::get_instance()->resolve_template_for_product( $post_id );
	}

	/**
	 * Prepare registered blocks for frontend runtime.
	 */
	private function prepare_blocks_for_frontend( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		return array_map(
			array( $this, 'prepare_single_block_for_frontend' ),
			$blocks
		);
	}

	/**
	 * Prepare layout templates payload for frontend runtime.
	 */
	private function prepare_layout_templates_for_frontend( $layout_templates ) {
		if ( ! is_array( $layout_templates ) ) {
			return array();
		}

		foreach ( array( 'header', 'footer' ) as $type ) {
			if ( empty( $layout_templates[ $type ]['blocks'] ) || ! is_array( $layout_templates[ $type ]['blocks'] ) ) {
				continue;
			}
			$layout_templates[ $type ]['blocks'] = $this->prepare_blocks_for_frontend( $layout_templates[ $type ]['blocks'] );
		}

		return $layout_templates;
	}

	/**
	 * Prepare one block payload for frontend rendering.
	 */
	private function prepare_single_block_for_frontend( $block ) {
		if ( ! is_array( $block ) ) {
			return array();
		}

		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( ! in_array( $type, array( 'form-embed', 'form-with-content' ), true ) ) {
			return $block;
		}

		$settings    = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$form_source = isset( $settings['formSource'] ) ? sanitize_key( $settings['formSource'] ) : 'dsf';

		if ( 'form-with-content' === $type && 'embed' === $form_source ) {
			$embed_code                       = isset( $settings['embedCode'] ) ? (string) $settings['embedCode'] : '';
			$embed_payload                    = $this->render_embed_code( $embed_code );
			$settings['formSource']           = 'embed';
			$settings['renderedFormHtml']     = '';
			$settings['renderedEmbedHtml']    = $embed_payload['html'];
			$settings['renderedEmbedScripts'] = $embed_payload['scripts'];
			$block['settings']                = $settings;

			return $block;
		}

		$form_id = isset( $settings['formId'] ) ? absint( $settings['formId'] ) : 0;
		$form    = $form_id ? get_post( $form_id ) : null;

		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			$form_id = 0;
		}

		$settings['formId']           = $form_id ? (string) $form_id : '';
		$settings['formTitle']        = ( $form_id && $form && $form->post_title ) ? $form->post_title : '';
		$settings['renderedFormHtml'] = $form_id ? DSF_Forms::get_instance()->render_form_shortcode( array( 'id' => $form_id ) ) : '';
		$block['settings']            = $settings;

		return $block;
	}

	/**
	 * Render shortcode/embed content for frontend block output.
	 */
	private function render_embed_code( $embed_code ) {
		if ( '' === trim( $embed_code ) ) {
			return array(
				'html'    => '',
				'scripts' => array(),
			);
		}

		$this->enqueue_gravity_form_assets_from_embed_code( $embed_code );

		$html    = do_shortcode( $embed_code );
		$scripts = array();
		$html    = $this->mark_hidden_gravity_form_pages( $html );
		$html    = $this->mark_gravity_form_ajax_iframes( $html );
		$html    = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			function ( $matches ) use ( &$scripts ) {
				$code = isset( $matches[2] ) ? trim( $matches[2] ) : '';

				if ( '' !== $code ) {
					$scripts[] = array(
						'code' => $code,
					);
				}

				return '';
			},
			$html
		);

		$preserved_styles = array();
		$html             = preg_replace_callback(
			'#<style\b[^>]*>.*?</style>#is',
			function ( $matches ) use ( &$preserved_styles ) {
				$placeholder        = '<!--dsf-style-' . count( $preserved_styles ) . '-->';
				$preserved_styles[] = $matches[0];
				return $placeholder;
			},
			$html
		);

		$allowed      = wp_kses_allowed_html( 'post' );
		$common_attrs = array(
			'class'  => true,
			'id'     => true,
			'style'  => true,
			'title'  => true,
			'role'   => true,
			'hidden' => true,
			'data-*' => true,
			'aria-*' => true,
		);
		foreach ( array( 'div', 'section', 'span', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'small' ) as $tag ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $common_attrs ) : $common_attrs;
		}
		$allowed['a']        = isset( $allowed['a'] ) ? array_merge(
			$allowed['a'],
			$common_attrs,
			array(
				'href'       => true,
				'target'     => true,
				'rel'        => true,
				'onclick'    => true,
				'onkeypress' => true,
			)
		) : array_merge(
			$common_attrs,
			array(
				'href'       => true,
				'target'     => true,
				'rel'        => true,
				'onclick'    => true,
				'onkeypress' => true,
			)
		);
		$allowed['form']     = array(
			'action'     => true,
			'method'     => true,
			'enctype'    => true,
			'target'     => true,
			'class'      => true,
			'id'         => true,
			'name'       => true,
			'novalidate' => true,
			'data-*'     => true,
			'aria-*'     => true,
		);
		$allowed['fieldset'] = array(
			'class'  => true,
			'id'     => true,
			'data-*' => true,
			'aria-*' => true,
		);
		$allowed['legend']   = array(
			'class'  => true,
			'id'     => true,
			'data-*' => true,
			'aria-*' => true,
		);
		$allowed['label']    = array(
			'for'    => true,
			'class'  => true,
			'id'     => true,
			'style'  => true,
			'data-*' => true,
			'aria-*' => true,
		);
		$allowed['input']    = array(
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'id'           => true,
			'class'        => true,
			'style'        => true,
			'placeholder'  => true,
			'checked'      => true,
			'disabled'     => true,
			'readonly'     => true,
			'required'     => true,
			'autocomplete' => true,
			'min'          => true,
			'max'          => true,
			'step'         => true,
			'maxlength'    => true,
			'tabindex'     => true,
			'onclick'      => true,
			'onkeypress'   => true,
			'data-*'       => true,
			'aria-*'       => true,
		);
		$allowed['select']   = array(
			'name'     => true,
			'id'       => true,
			'class'    => true,
			'style'    => true,
			'multiple' => true,
			'disabled' => true,
			'required' => true,
			'tabindex' => true,
			'data-*'   => true,
			'aria-*'   => true,
		);
		$allowed['option']   = array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
			'class'    => true,
			'data-*'   => true,
		);
		$allowed['textarea'] = array(
			'name'        => true,
			'id'          => true,
			'class'       => true,
			'style'       => true,
			'placeholder' => true,
			'rows'        => true,
			'cols'        => true,
			'disabled'    => true,
			'readonly'    => true,
			'required'    => true,
			'maxlength'   => true,
			'tabindex'    => true,
			'data-*'      => true,
			'aria-*'      => true,
		);
		$allowed['button']   = array(
			'type'       => true,
			'name'       => true,
			'value'      => true,
			'id'         => true,
			'class'      => true,
			'style'      => true,
			'disabled'   => true,
			'aria-label' => true,
			'onclick'    => true,
			'onkeypress' => true,
			'data-*'     => true,
			'aria-*'     => true,
		);
		$allowed['svg']      = array(
			'class'       => true,
			'viewBox'     => true,
			'xmlns'       => true,
			'width'       => true,
			'height'      => true,
			'fill'        => true,
			'focusable'   => true,
			'aria-hidden' => true,
			'role'        => true,
			'data-*'      => true,
		);
		$allowed['path']     = array(
			'd'         => true,
			'fill'      => true,
			'fill-rule' => true,
			'clip-rule' => true,
			'data-*'    => true,
		);
		$allowed['iframe']   = array(
			'src'             => true,
			'id'              => true,
			'name'            => true,
			'title'           => true,
			'width'           => true,
			'height'          => true,
			'class'           => true,
			'style'           => true,
			'loading'         => true,
			'frameborder'     => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'referrerpolicy'  => true,
			'sandbox'         => true,
			'data-*'          => true,
		);

		$sanitized_html = wp_kses( $html, $allowed );

		if ( ! empty( $preserved_styles ) ) {
			foreach ( $preserved_styles as $index => $style_block ) {
				$sanitized_html = str_replace(
					'<!--dsf-style-' . $index . '-->',
					$style_block,
					$sanitized_html
				);
			}
		}

		$sanitized_html = str_replace(
			'data-dsf-gform-page-hidden="1"',
			'data-dsf-gform-page-hidden="1" style="display:none;"',
			$sanitized_html
		);
		$sanitized_html = preg_replace_callback(
			'#<iframe\b[^>]*\bdata-dsf-gform-ajax-frame="([^"]+)"[^>]*>#i',
			function ( $matches ) {
				$frame_id = esc_attr( $matches[1] );
				return '<iframe name="' . $frame_id . '" id="' . $frame_id . '" src="about:blank" style="display:none;width:0px;height:0px;" title="This iframe contains the logic required to handle Ajax powered Gravity Forms.">';
			},
			$sanitized_html
		);

		return array(
			'html'    => $sanitized_html,
			'scripts' => $scripts,
		);
	}

	/**
	 * Preserve Gravity Forms multipage hidden state through wp_kses style filtering.
	 */
	private function mark_hidden_gravity_form_pages( $html ) {
		return preg_replace_callback(
			'#<div\b[^>]*class=(["\'])(?=[^"\']*\bgform_page\b)[^"\']*\1[^>]*style=(["\'])(?=[^"\']*display\s*:\s*none)[^"\']*\2[^>]*>#i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== strpos( $tag, 'data-dsf-gform-page-hidden' ) ) {
					return $tag;
				}
				return preg_replace( '/>$/', ' data-dsf-gform-page-hidden="1">', $tag );
			},
			$html
		);
	}

	/**
	 * Preserve Gravity Forms AJAX target iframes through wp_kses protocol/style filtering.
	 */
	private function mark_gravity_form_ajax_iframes( $html ) {
		return preg_replace_callback(
			'#<iframe\b[^>]*(?:name|id)=(["\'])(gform_ajax_frame_\d+)\1[^>]*>#i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== strpos( $tag, 'data-dsf-gform-ajax-frame' ) ) {
					return $tag;
				}
				return preg_replace( '/>$/', ' data-dsf-gform-ajax-frame="' . esc_attr( $matches[2] ) . '">', $tag );
			},
			$html
		);
	}

	/**
	 * Ask Gravity Forms to enqueue frontend assets for embedded shortcodes.
	 */
	private function enqueue_gravity_form_assets_from_embed_code( $embed_code ) {
		if ( ! function_exists( 'gravity_form_enqueue_scripts' ) || ! function_exists( 'shortcode_parse_atts' ) ) {
			return;
		}

		if ( ! preg_match_all( '/\[gravityforms?\b([^\]]*)\]/i', $embed_code, $matches ) ) {
			return;
		}

		foreach ( $matches[1] as $attributes ) {
			$atts = shortcode_parse_atts( $attributes );
			if ( ! is_array( $atts ) ) {
				continue;
			}

			$form_id = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
			if ( ! $form_id ) {
				continue;
			}

			$ajax = isset( $atts['ajax'] ) && in_array( strtolower( (string) $atts['ajax'] ), array( 'true', '1', 'yes' ), true );
			gravity_form_enqueue_scripts( $form_id, $ajax );
		}
	}

	/**
	 * Enqueue Google Fonts for custom theme fonts
	 */
	private function enqueue_google_fonts( $post_id ) {
		$settings = $this->get_page_settings( $post_id );
		$theme    = array_merge( self::get_default_theme_settings(), $settings['theme'] ?? array() );

		$fonts_to_load = array();
		$google_fonts  = array(
			'Inter',
			'Roboto',
			'Open Sans',
			'Lato',
			'Montserrat',
			'Poppins',
			'Outfit',
			'Source Sans 3',
			'Nunito',
			'Raleway',
			'Playfair Display',
			'Merriweather',
			'Lora',
			'DM Sans',
			'Work Sans',
			'Oswald',
			'Ubuntu',
			'Rubik',
			'Manrope',
			'Space Grotesk',
		);

		// Google presets are loaded here; WordPress theme fonts are already enqueued by the theme.
		foreach ( array( 'headingFont', 'bodyFont' ) as $font_key ) {
			if ( ! empty( $theme[ $font_key ] ) ) {
				$first_family = trim( explode( ',', $theme[ $font_key ] )[0], " \t\n\r\0\x0B'\"" );
				if ( in_array( $first_family, $google_fonts, true ) ) {
					$fonts_to_load[ $first_family ] = true;
				}
			}
		}

		if ( empty( $fonts_to_load ) ) {
			return;
		}

		// Build Google Fonts URL
		$font_families = array();
		foreach ( array_keys( $fonts_to_load ) as $font_name ) {
			$font_families[] = str_replace( ' ', '+', $font_name ) . ':wght@400;500;600;700';
		}

		$google_fonts_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_families ) . '&display=swap';

		wp_enqueue_style(
			'dsf-google-fonts',
			$google_fonts_url,
			array(),
			null // No version for external resource
		);
	}

	/**
	 * Get version string for cache busting based on file mtime.
	 */
	private function get_asset_version( $relative_path ) {
		$relative_path = ltrim( $relative_path, '/' );
		$path          = DSF_PLUGIN_DIR . $relative_path;
		if ( file_exists( $path ) ) {
			return (string) filemtime( $path );
		}
		return DSF_VERSION;
	}

	/**
	 * Add type="module" to frontend Vite scripts
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ) {
		unset( $src );
		if ( in_array( $handle, array( 'dsf-frontend-vite', 'dsf-frontend-app', 'dsf-notification-bar' ), true ) ) {
			if ( false === strpos( $tag, 'type="module"' ) ) {
				$tag = str_replace( '<script ', '<script type="module" ', $tag );
			}
		}
		return $tag;
	}

	/**
	 * Detect whether a block list contains any landing-* block.
	 *
	 * @param array $blocks Raw blocks.
	 * @return bool
	 */
	private function blocks_use_landing( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return false;
		}
		foreach ( $blocks as $block ) {
			$type = is_array( $block ) && isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( 0 === strpos( $type, 'landing-' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Take the heavy stylesheet off the critical render path.
	 *
	 * The Google Fonts request is always loaded asynchronously (font-display:swap
	 * keeps text visible). The bundle CSS is only deferred on landing pages, where
	 * the snapshot is intentionally hidden behind a loader until Vue mounts — so
	 * there is no content to flash unstyled. Other Flow pages paint the snapshot
	 * immediately, so their CSS stays render-blocking to avoid a FOUC.
	 *
	 * @param string $tag    Stylesheet link tag.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute.
	 * @return string
	 */
	public function make_styles_non_blocking( $tag, $handle, $href, $media ) {
		if ( ! $this->is_flow_page ) {
			return $tag;
		}

		$always       = array( 'dsf-google-fonts' );
		$landing_only = array( 'dsf-main', 'dsf-frontend' );

		$should_defer = in_array( $handle, $always, true )
			|| ( $this->current_is_landing && in_array( $handle, $landing_only, true ) );

		if ( ! $should_defer ) {
			return $tag;
		}

		$media = $media ? $media : 'all';

		$preload  = sprintf(
			'<link rel="preload" as="style" href="%1$s" media="%2$s" onload="this.onload=null;this.rel=\'stylesheet\'">',
			esc_url( $href ),
			esc_attr( $media )
		);
		$noscript = sprintf(
			// phpcs:ignore WordPress.WP.EnqueuedStylesheet.NotInFooter, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- no-JS fallback for an already-enqueued, filter-deferred style.
			'<noscript><link rel="stylesheet" href="%1$s" media="%2$s"></noscript>',
			esc_url( $href ),
			esc_attr( $media )
		);

		return $preload . $noscript . "\n";
	}

	/**
	 * Output resource hints and critical CSS early in the document head.
	 *
	 * - Preconnects to the Google Fonts origins (saves a DNS + TLS round-trip).
	 * - Module-preloads the frontend JS bundle so Vue can mount sooner (the
	 *   landing loader clears as soon as it does), instead of waterfalling the
	 *   large chunks after the entry script is parsed.
	 * - Inlines just enough loader CSS so it renders correctly while the deferred
	 *   bundle stylesheet is still downloading.
	 */
	public function print_performance_hints() {
		if ( ! $this->is_flow_page ) {
			return;
		}

		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

		// Preload the likely LCP image so it is ready the moment it paints.
		if ( '' !== $this->hero_image_url ) {
			printf(
				'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
				esc_url( $this->hero_image_url )
			);
		}

		foreach ( $this->get_frontend_module_preload_urls() as $url ) {
			printf( '<link rel="modulepreload" href="%s">' . "\n", esc_url( $url ) );
		}

		if ( $this->current_is_landing ) {
			echo '<style id="dsf-loader-critical">' . $this->get_loader_critical_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS string.
		}

		// Responsive typography: override the inline desktop tokens at laptop/mobile.
		$responsive_typography = self::build_responsive_typography_css();
		if ( '' !== $responsive_typography ) {
			echo '<style id="dsf-responsive-typography">' . $responsive_typography . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated numeric CSS variables.
		}
	}

	/**
	 * Resolve the frontend entry's JS chunks for module preloading.
	 *
	 * @return string[] Absolute URLs.
	 */
	private function get_frontend_module_preload_urls() {
		static $urls = null;
		if ( null !== $urls ) {
			return $urls;
		}

		$urls          = array();
		$manifest_path = DSF_PLUGIN_DIR . 'assets/.vite/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return $urls;
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $manifest ) || empty( $manifest['src/frontend/main.js']['imports'] ) ) {
			return $urls;
		}

		foreach ( (array) $manifest['src/frontend/main.js']['imports'] as $key ) {
			if ( isset( $manifest[ $key ]['file'] ) ) {
				$urls[] = DSF_PLUGIN_URL . 'assets/' . ltrim( $manifest[ $key ]['file'], '/' );
			}
		}

		return $urls;
	}

	/**
	 * Minimal critical CSS for the landing loader, inlined so it looks right
	 * before the deferred bundle stylesheet finishes loading.
	 *
	 * @return string
	 */
	private function get_loader_critical_css() {
		return '.dsf-page-content--loading{position:relative;min-height:60vh}'
			. '.dsf-page-content--loading #dsf-frontend-app>*{visibility:hidden}'
			. '.dsf-landing-loader{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;background:#f7f4ed;color:#17212b;font-family:var(--dsf-theme-heading-font,"Manrope",sans-serif)}'
			. '.dsf-page-content--ready .dsf-landing-loader{opacity:0;visibility:hidden;transition:opacity .26s,visibility .26s}'
			. '.dsf-landing-loader__content{display:grid;justify-items:center;gap:15px;font-size:13px;font-weight:800;letter-spacing:.08em}'
			. '.dsf-landing-loader__mark{display:grid;grid-template-columns:repeat(2,13px);gap:4px}'
			. '.dsf-landing-loader__mark i{width:13px;height:13px;border-radius:3px;background:var(--dsf-theme-primary,#0091ff);animation:dsf-loader-pulse .9s ease-in-out infinite alternate}'
			. '.dsf-landing-loader__mark i:nth-child(2),.dsf-landing-loader__mark i:nth-child(3){background:var(--dsf-theme-secondary,#ff7100);animation-delay:.16s}'
			. '.dsf-landing-loader__mark i:nth-child(4){animation-delay:.32s}'
			. '@keyframes dsf-loader-pulse{from{opacity:.35;transform:scale(.85)}to{opacity:1;transform:scale(1)}}';
	}

	/**
	 * Drop WordPress/theme defaults a self-contained Flow page does not use.
	 *
	 * Runs late on wp_enqueue_scripts (after other plugins/theme have enqueued)
	 * so we can remove the emoji detector, the oEmbed host script, and the
	 * Gutenberg / global block stylesheets. Each removal is filterable.
	 */
	public function dequeue_default_bloat() {
		if ( ! $this->is_flow_page ) {
			return;
		}
		// Whole-site header/footer pages still render the theme's post content, which
		// may rely on Gutenberg/theme styles — keep them.
		if ( $this->is_global_hf_page ) {
			return;
		}

		// Emoji detector + styles (wp_head priority 7 / wp_print_styles run later).
		if ( apply_filters( 'dsf_flow_disable_emoji', true, $this->current_post_id ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_print_styles', 'wp_enqueue_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		}

		// oEmbed host JavaScript — Flow renders its own embeds.
		if ( apply_filters( 'dsf_flow_disable_embed', true, $this->current_post_id ) ) {
			wp_dequeue_script( 'wp-embed' );
		}

		// Gutenberg / global block CSS — Flow ships its own design system.
		if ( apply_filters( 'dsf_flow_dequeue_default_styles', true, $this->current_post_id ) ) {
			foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Find the first image URL in the page blocks (the LCP candidate).
	 *
	 * @param array $blocks Raw blocks.
	 * @return string
	 */
	private function find_hero_image_url( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return '';
		}

		$scanned = 0;
		foreach ( $blocks as $block ) {
			if ( $scanned++ >= 4 ) {
				break; // Only the first few blocks can hold the above-the-fold hero.
			}
			$url = $this->search_image_url( $block );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Recursively pull the first raster image URL out of a block value.
	 *
	 * @param mixed $data Block data.
	 * @return string
	 */
	private function search_image_url( $data ) {
		if ( is_string( $data ) ) {
			if ( preg_match( '#https?://[^\s"\'<>]+\.(?:jpe?g|png|webp|avif)(?:\?[^\s"\'<>]*)?#i', $data, $match ) ) {
				return $match[0];
			}
			return '';
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$found = $this->search_image_url( $value );
				if ( '' !== $found ) {
					return $found;
				}
			}
		}

		return '';
	}

	/**
	 * Render Flow page content
	 */
	public function render_flow_content( $content ) {
		global $post;

		if ( ! $post || ! is_singular() ) {
			return $content;
		}

		// If the custom flow template already called render_flow_blocks() directly,
		// suppress this second the_content invocation to avoid a duplicate render.
		if ( isset( $this->rendered_posts[ $post->ID ] ) ) {
			return '';
		}

		// Only for Flow pages or Flow-enabled pages
		$is_flow = ( 'page' === $post->post_type ) && get_post_meta( $post->ID, '_dsf_enabled', true );
		if ( ! $is_flow ) {
			return $content;
		}

		$rendered = $this->render_flow_blocks( $post->ID );
		return $rendered ? $rendered : $content;
	}

	/**
	 * Use a clean template for Flow pages
	 */
	public function load_flow_template( $template ) {
		if ( is_admin() ) {
			return $template;
		}

		// Woo product archives (shop page, category, tag) with an active DSF shop
		// template render through our own template. Archives with no template stay
		// on the native WooCommerce/theme template untouched. Checked before the
		// singular gate because archives are not singular.
		if ( DSF_Shop_Templates::is_product_archive() ) {
			$shop_template_id = DSF_Shop_Templates::get_instance()->resolve_template_for_current_archive();
			if ( $shop_template_id ) {
				$this->current_shop_template_id = $shop_template_id;

				if ( is_user_logged_in() && current_user_can( 'edit_pages' ) && ! headers_sent() ) {
					nocache_headers();
				}

				$shop_template = DSF_PLUGIN_DIR . 'templates/flow-shop.php';
				return file_exists( $shop_template ) ? $shop_template : $template;
			}
		}

		// Blog archives (posts page, category, tag, author, date) with an active
		// DSF blog template render through our own template. Archives with no
		// template stay on the native theme template untouched.
		if ( DSF_Blog_Templates::is_blog_archive() ) {
			$blog_template_id = DSF_Blog_Templates::get_instance()->resolve_template_for_current_archive();
			if ( $blog_template_id ) {
				$this->current_blog_template_id = $blog_template_id;

				if ( is_user_logged_in() && current_user_can( 'edit_pages' ) && ! headers_sent() ) {
					nocache_headers();
				}

				$blog_template = DSF_PLUGIN_DIR . 'templates/flow-blog.php';
				return file_exists( $blog_template ) ? $blog_template : $template;
			}
		}

		if ( ! is_singular() ) {
			return $template;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $template;
		}

		$post_type = get_post_type( $post_id );

		// Single-product pages that resolve to an active DSF product template are
		// rendered through our own template. Products with no template stay on the
		// native WooCommerce template untouched.
		if ( 'product' === $post_type && is_singular( 'product' ) ) {
			$product_template_id = $this->resolve_product_template_for_post( $post_id );
			if ( ! $product_template_id ) {
				return $template;
			}

			$this->current_product_template_id = $product_template_id;

			if ( is_user_logged_in() && current_user_can( 'edit_pages' ) && ! headers_sent() ) {
				nocache_headers();
			}

			$product_template = DSF_PLUGIN_DIR . 'templates/flow-product.php';
			return file_exists( $product_template ) ? $product_template : $template;
		}

		$is_flow = ( 'page' === $post_type ) && get_post_meta( $post_id, '_dsf_enabled', true );
		if ( ! $is_flow ) {
			// Whole-site header/footer: wrap a normal page/post with the DSF header
			// and footer (its content still renders via the_content).
			if ( $this->should_apply_global_hf( $post_id ) ) {
				$this->is_global_hf_page = true;
				$universal               = DSF_PLUGIN_DIR . 'templates/flow-universal.php';
				return file_exists( $universal ) ? $universal : $template;
			}
			return $template;
		}

		// While an editor is logged in, never let the browser serve a stale cached
		// copy of a Flow page — they need to see edits immediately. Anonymous
		// visitors are unaffected (this keeps the perf/caching benefits for them).
		if ( is_user_logged_in() && current_user_can( 'edit_pages' ) && ! headers_sent() ) {
			nocache_headers();
		}

		$settings        = $this->get_page_settings( $post_id );
		$layout          = $settings['layout'] ?? array();
		$template_choice = $layout['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $post_id );
		$template_file   = 'fullwidth' === $template_choice ? 'flow-page-fullwidth.php' : 'flow-page.php';
		$custom_template = DSF_PLUGIN_DIR . 'templates/' . $template_file;
		if ( file_exists( $custom_template ) ) {
			// Custom template calls render_flow_blocks() directly.
			// Remove the_content filter to prevent a second render if any theme/plugin
			// hook fires the_content before or after the template's direct call.
			remove_filter( 'the_content', array( $this, 'render_flow_content' ), 20 );
			return $custom_template;
		}

		return $template;
	}

	/**
	 * Redirect legacy /flow/... URLs to the migrated WordPress page permalink.
	 */
	public function redirect_legacy_flow_urls() {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path        = $path ? trailingslashit( $path ) : '';
		if ( '' === $path ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_dsf_legacy_flow_path',
				'meta_value'     => $path,
			)
		);

		if ( empty( $posts ) ) {
			return;
		}

		$target_url = get_permalink( $posts[0]->ID );
		if ( ! $target_url ) {
			return;
		}

		wp_safe_redirect( $target_url, 301 );
		exit;
	}

	/**
	 * Render blocks for a given post
	 */
	public function render_flow_blocks( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		// Mark that blocks have been rendered so the_content filter skips them.
		$this->rendered_posts[ $post_id ] = true;

		$blocks_json = get_post_meta( $post_id, '_dsf_blocks', true );
		if ( ! $blocks_json ) {
			return '';
		}
		$blocks          = is_array( $blocks_json ) ? $blocks_json : json_decode( $blocks_json, true );
		$blocks          = is_array( $blocks ) ? $blocks : array();
		$is_landing_page = false;
		foreach ( $blocks as $block ) {
			$type = is_array( $block ) && isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( 0 === strpos( $type, 'landing-' ) ) {
				$is_landing_page = true;
				break;
			}
		}

		$page_settings = $this->get_page_settings( $post_id );
		$theme_style   = $this->build_theme_style( $page_settings );

		$outer_class     = 'dsf-page-content';
		$inner_class     = 'dsf-page-content__inner';
		$template_choice = $page_settings['layout']['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $post_id );
		if ( 'fullwidth' === $template_choice ) {
			$outer_class .= ' dsf-page-content--fullwidth';
			$inner_class .= ' dsf-page-content__inner--fullwidth';
		}
		if ( $is_landing_page ) {
			$outer_class .= ' dsf-page-content--loading';
		}

		$snapshot = get_post_meta( $post_id, '_dsf_html_snapshot', true );

		$output  = '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div class="' . esc_attr( $inner_class ) . '">';
		if ( $is_landing_page ) {
			$output .= '<div class="dsf-landing-loader" role="status" aria-live="polite"><div class="dsf-landing-loader__content"><span class="dsf-landing-loader__mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span>' . esc_html__( 'Loading DesignStudio Flow', 'designstudio-flow' ) . '</span></div></div>';
		}
		$output .= '<div id="dsf-frontend-app" class="dsf-wrapper" data-post-id="' . intval( $post_id ) . '">';
		if ( ! empty( $snapshot ) ) {
			// Add loading="lazy"/decoding="async"/fetchpriority to snapshot images.
			$output .= function_exists( 'wp_filter_content_tags' ) ? wp_filter_content_tags( $snapshot, 'dsf-flow' ) : $snapshot;
		}
		$output .= '</div>';

		// Live Woo store fragments (cart/checkout/account) sit OUTSIDE the Vue
		// mount root — mounting replaces the root's children, and these nodes must
		// survive so Woo's script bindings stay attached. Vue store blocks adopt
		// them into position on mount; <noscript> reveals them for no-JS visitors.
		if ( null !== $this->store_context ) {
			// Fragments are sanitized at capture; the container escapes its own attributes.
			$output .= DSF_Store_Pages::render_fragments_container( $this->store_context );
		}

		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Render the DSF product-template blocks mount for the current product.
	 *
	 * Layout/theme come from the template; the visible data is the current product.
	 * The product blocks are rendered server-side (real content, real add-to-cart
	 * form) so the page is meaningful and purchasable for crawlers and no-JS
	 * visitors; Vue then hydrates the same content for the interactive experience.
	 *
	 * @param int $template_id Product template post ID.
	 * @param int $product_id  Current product post ID.
	 * @return string
	 */
	public function render_product_flow_blocks( $template_id, $product_id ) {
		$template_id = intval( $template_id );
		$product_id  = intval( $product_id );
		if ( ! $template_id || ! $product_id ) {
			return '';
		}

		$page_settings = $this->get_page_settings( $template_id );
		$theme_style   = $this->build_theme_style( $page_settings );

		$outer_class     = 'dsf-page-content dsf-product-content';
		$inner_class     = 'dsf-page-content__inner';
		$template_choice = $page_settings['layout']['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $template_id );
		if ( 'fullwidth' === $template_choice ) {
			$outer_class .= ' dsf-page-content--fullwidth';
			$inner_class .= ' dsf-page-content__inner--fullwidth';
		}

		$blocks_meta = get_post_meta( $template_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$decoded = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}

		$needs   = $this->product_blocks_need_fragments( $blocks );
		$context = DSF_Product_Templates::build_product_context(
			$product_id,
			array(
				'add_to_cart' => $needs['add_to_cart'],
				'reviews'     => $needs['reviews'],
				'related'     => $needs['related'],
				'upsells'     => $needs['upsells'],
			)
		);

		$server_html = $this->render_product_blocks_server( $blocks, $context );
		if ( '' === $server_html ) {
			$server_html = $this->build_product_seo_fallback( $product_id );
		}

		$output  = '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div class="' . esc_attr( $inner_class ) . '">';
		$output .= '<div id="dsf-frontend-app" class="dsf-wrapper" data-post-id="' . intval( $product_id ) . '" data-product-template="' . intval( $template_id ) . '">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped/kses'd fragments in the render_product_*_server() helpers.
		$output .= $server_html;
		$output .= '</div>';
		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Render the DSF shop-template blocks mount for the current product archive.
	 *
	 * Layout/theme come from the template; the visible data is the archive's main
	 * query. The shop blocks are rendered server-side (real product links, real
	 * pagination) so category pages stay crawlable; Vue hydrates the same content.
	 *
	 * @param int $template_id Shop template post ID.
	 * @return string
	 */
	public function render_shop_flow_blocks( $template_id ) {
		$template_id = intval( $template_id );
		if ( ! $template_id ) {
			return '';
		}

		$page_settings = $this->get_page_settings( $template_id );
		$theme_style   = $this->build_theme_style( $page_settings );

		$outer_class     = 'dsf-page-content dsf-shop-content';
		$inner_class     = 'dsf-page-content__inner';
		$template_choice = $page_settings['layout']['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $template_id );
		if ( 'fullwidth' === $template_choice ) {
			$outer_class .= ' dsf-page-content--fullwidth';
			$inner_class .= ' dsf-page-content__inner--fullwidth';
		}

		$blocks_meta = get_post_meta( $template_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$decoded = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}

		$context = $this->current_archive_context;
		if ( empty( $context ) ) {
			$context                       = DSF_Shop_Templates::build_archive_context_from_main_query();
			$this->current_archive_context = $context;
		}

		$server_html = '';
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type         = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			$settings     = ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array();
			$server_html .= $this->render_one_shop_block_server( $type, $settings, $context );
		}
		if ( '' !== $server_html ) {
			$server_html = '<div class="dsf-product-ssr">' . $server_html . '</div>';
		}

		$output  = '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div class="' . esc_attr( $inner_class ) . '">';
		$output .= '<div id="dsf-frontend-app" class="dsf-wrapper" data-shop-template="' . intval( $template_id ) . '">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped/kses'd fragments in render_one_shop_block_server().
		$output .= $server_html;
		$output .= '</div>';
		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Server-render a single shop block for crawlers/no-JS visitors.
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Block settings (already sanitized at save time).
	 * @param array  $context  Current archive context.
	 * @return string
	 */
	private function render_one_shop_block_server( $type, $settings, $context ) {
		if ( 'shop-header' === $type ) {
			$out = '<header class="dsf-ssr-shop-header">';
			if ( false !== ( $settings['showTitle'] ?? true ) && ! empty( $context['title'] ) ) {
				$out .= '<h1>' . esc_html( $context['title'] ) . '</h1>';
			}
			if ( false !== ( $settings['showDescription'] ?? true ) && ! empty( $context['descriptionHtml'] ) ) {
				$out .= '<div class="dsf-ssr-shop-desc">' . wp_kses_post( $context['descriptionHtml'] ) . '</div>';
			}
			return $out . '</header>';
		}

		if ( 'shop-products' === $type ) {
			$cards = ( isset( $context['products'] ) && is_array( $context['products'] ) ) ? $context['products'] : array();
			if ( empty( $cards ) ) {
				return '';
			}

			$out = '<div class="dsf-ssr-shop-products"><ul>';
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) ) {
					continue;
				}
				$out .= '<li><a href="' . esc_url( $card['permalink'] ?? '' ) . '">';
				if ( ! empty( $card['image'] ) ) {
					$out .= '<img src="' . esc_url( $card['image'] ) . '" alt="' . esc_attr( $card['imageAlt'] ?? '' ) . '" loading="lazy" />';
				}
				$out .= '<span>' . esc_html( $card['name'] ?? '' ) . '</span>';
				if ( ! empty( $card['priceHtml'] ) ) {
					$out .= '<span class="dsf-ssr-price">' . wp_kses_post( $card['priceHtml'] ) . '</span>';
				}
				$out .= '</a></li>';
			}
			$out .= '</ul>';

			$pagination = ( isset( $context['pagination'] ) && is_array( $context['pagination'] ) ) ? $context['pagination'] : array();
			if ( $pagination && false !== ( $settings['showPagination'] ?? true ) ) {
				$out .= '<nav class="dsf-ssr-shop-pagination" aria-label="' . esc_attr__( 'Products pagination', 'designstudio-flow' ) . '">';
				foreach ( $pagination as $link ) {
					if ( ! is_array( $link ) ) {
						continue;
					}
					$out .= ! empty( $link['current'] )
						? '<span aria-current="page">' . esc_html( $link['label'] ?? '' ) . '</span>'
						: '<a href="' . esc_url( $link['url'] ?? '' ) . '">' . esc_html( $link['label'] ?? '' ) . '</a>';
				}
				$out .= '</nav>';
			}

			return $out . '</div>';
		}

		return '';
	}

	/**
	 * Server-render the product blocks of a template for the current product.
	 *
	 * Only the product blocks are rendered (other block types hydrate via Vue). Each
	 * fragment is escaped or already sanitized server-side, so the assembled string
	 * is safe to echo.
	 *
	 * @param array $blocks  Template blocks.
	 * @param array $context Current product context.
	 * @return string
	 */
	private function render_product_blocks_server( $blocks, $context ) {
		if ( empty( $context ) || ! is_array( $blocks ) ) {
			return '';
		}

		$html = '';
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type     = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			$settings = ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array();
			$html    .= $this->render_one_product_block_server( $type, $settings, $context );
		}

		return '' === $html ? '' : '<div class="dsf-product-ssr">' . $html . '</div>';
	}

	/**
	 * Server-render a single product block.
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Block settings (already sanitized at save time).
	 * @param array  $context  Current product context.
	 * @return string
	 */
	private function render_one_product_block_server( $type, $settings, $context ) {
		switch ( $type ) {
			case 'product-gallery':
				$image = isset( $context['gallery'][0] ) && is_array( $context['gallery'][0] ) ? $context['gallery'][0] : null;
				if ( ! $image || empty( $image['full'] ) ) {
					return '';
				}
				return '<figure class="dsf-ssr-gallery"><img src="' . esc_url( $image['full'] ) . '" alt="' . esc_attr( $image['alt'] ?? '' ) . '" fetchpriority="high" /></figure>';

			case 'product-spotlight':
				$out   = '<div class="dsf-ssr-spotlight">';
				$image = isset( $context['gallery'][0] ) && is_array( $context['gallery'][0] ) ? $context['gallery'][0] : null;
				if ( $image && ! empty( $image['full'] ) ) {
					$out .= '<img src="' . esc_url( $image['full'] ) . '" alt="' . esc_attr( $image['alt'] ?? '' ) . '" fetchpriority="high" />';
				}
				$out .= '<h1>' . esc_html( $context['name'] ?? '' ) . '</h1>';
				if ( ! empty( $context['priceHtml'] ) ) {
					$out .= '<div class="dsf-ssr-price">' . wp_kses_post( $context['priceHtml'] ) . '</div>';
				}
				if ( false !== ( $settings['showShortDescription'] ?? true ) && ! empty( $context['shortDescriptionHtml'] ) ) {
					$out .= '<div class="dsf-ssr-excerpt">' . wp_kses_post( $context['shortDescriptionHtml'] ) . '</div>';
				}
				if ( false !== ( $settings['showAddToCart'] ?? true ) && ! empty( $context['addToCartHtml'] ) ) {
					// Already sanitized with the Woo-form allowlist.
					$out .= '<div class="dsf-ssr-cart">' . $context['addToCartHtml'] . '</div>';
				}
				return $out . '</div>';

			case 'product-hero':
				$out   = '<div class="dsf-ssr-hero">';
				$image = isset( $context['gallery'][0] ) && is_array( $context['gallery'][0] ) ? $context['gallery'][0] : null;
				if ( $image && ! empty( $image['full'] ) ) {
					$out .= '<img src="' . esc_url( $image['full'] ) . '" alt="' . esc_attr( $image['alt'] ?? '' ) . '" fetchpriority="high" />';
				}
				$out .= '<h1>' . esc_html( $context['name'] ?? '' ) . '</h1>';
				if ( false !== ( $settings['showPrice'] ?? true ) && ! empty( $context['priceHtml'] ) ) {
					$out .= '<div class="dsf-ssr-price">' . wp_kses_post( $context['priceHtml'] ) . '</div>';
				}
				if ( false !== ( $settings['showShortDescription'] ?? true ) && ! empty( $context['shortDescriptionHtml'] ) ) {
					$out .= '<div class="dsf-ssr-excerpt">' . wp_kses_post( $context['shortDescriptionHtml'] ) . '</div>';
				}
				if ( false !== ( $settings['showAddToCart'] ?? true ) && ! empty( $context['addToCartHtml'] ) ) {
					// Already sanitized with the Woo-form allowlist.
					$out .= '<div class="dsf-ssr-cart">' . $context['addToCartHtml'] . '</div>';
				}
				return $out . '</div>';

			case 'product-highlights':
				$items = ( isset( $settings['items'] ) && is_array( $settings['items'] ) ) ? $settings['items'] : array();
				if ( empty( $items ) ) {
					return '';
				}
				$out = '<ul class="dsf-ssr-highlights">';
				foreach ( array_slice( $items, 0, 8 ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$title       = sanitize_text_field( $item['title'] ?? '' );
					$description = sanitize_text_field( $item['description'] ?? '' );
					if ( '' === $title && '' === $description ) {
						continue;
					}
					$out .= '<li><strong>' . esc_html( $title ) . '</strong> ' . esc_html( $description ) . '</li>';
				}
				return $out . '</ul>';

			case 'product-related':
				$cards = ( isset( $context['relatedProducts'] ) && is_array( $context['relatedProducts'] ) ) ? $context['relatedProducts'] : array();
				return $this->render_product_cards_server( $settings, $cards, 'You may also like', 'dsf-ssr-related' );

			case 'product-upsells':
				$cards = ( isset( $context['upsellProducts'] ) && is_array( $context['upsellProducts'] ) ) ? $context['upsellProducts'] : array();
				return $this->render_product_cards_server( $settings, $cards, 'Pairs well with', 'dsf-ssr-upsells' );

			case 'product-reviews':
				$out = '<div class="dsf-ssr-reviews">';
				if ( false !== ( $settings['showHeading'] ?? true ) ) {
					$out .= '<h2>' . esc_html( sanitize_text_field( $settings['headingText'] ?? 'Customer Reviews' ) ) . '</h2>';
				}
				if ( false !== ( $settings['showSummary'] ?? true ) && ! empty( $context['ratingCount'] ) ) {
					$average = number_format( (float) ( $context['averageRating'] ?? 0 ), 1 );
					$count   = (int) ( $context['reviewCount'] ?? 0 );
					/* translators: 1: average rating, 2: review count. */
					$out .= '<p class="dsf-ssr-reviews__summary">' . esc_html( sprintf( __( 'Rated %1$s out of 5 based on %2$d reviews', 'designstudio-flow' ), $average, $count ) ) . '</p>';
				}
				if ( ! empty( $context['reviewsHtml'] ) ) {
					// Already sanitized with the Woo-form allowlist in build_reviews_html().
					$out .= $context['reviewsHtml'];
				}
				return $out . '</div>';

			case 'product-meta':
				$rows = '';
				if ( false !== ( $settings['showSku'] ?? true ) && ! empty( $context['sku'] ) ) {
					$rows .= '<li>' . esc_html__( 'SKU:', 'designstudio-flow' ) . ' ' . esc_html( $context['sku'] ) . '</li>';
				}
				foreach ( array(
					'showCategories' => array( 'categories', __( 'Categories:', 'designstudio-flow' ) ),
					'showTags'       => array( 'tags', __( 'Tags:', 'designstudio-flow' ) ),
				) as $toggle => $meta ) {
					list( $context_key, $label ) = $meta;
					$terms                       = ( isset( $context[ $context_key ] ) && is_array( $context[ $context_key ] ) ) ? $context[ $context_key ] : array();
					if ( false === ( $settings[ $toggle ] ?? true ) || empty( $terms ) ) {
						continue;
					}
					$links = array();
					foreach ( $terms as $term ) {
						if ( ! is_array( $term ) || '' === (string) ( $term['name'] ?? '' ) ) {
							continue;
						}
						$links[] = empty( $term['url'] )
							? esc_html( $term['name'] )
							: '<a href="' . esc_url( $term['url'] ) . '">' . esc_html( $term['name'] ) . '</a>';
					}
					if ( $links ) {
						$rows .= '<li>' . esc_html( $label ) . ' ' . implode( ', ', $links ) . '</li>';
					}
				}
				return '' === $rows ? '' : '<ul class="dsf-ssr-meta">' . $rows . '</ul>';

			case 'product-summary':
				$tag = ( 'h2' === ( $settings['headingTag'] ?? 'h1' ) ) ? 'h2' : 'h1';
				$out = '<div class="dsf-ssr-summary">';
				if ( false !== ( $settings['showTitle'] ?? true ) ) {
					$out .= '<' . $tag . '>' . esc_html( $context['name'] ?? '' ) . '</' . $tag . '>';
				}
				if ( false !== ( $settings['showPrice'] ?? true ) && ! empty( $context['priceHtml'] ) ) {
					$out .= '<div class="dsf-ssr-price">' . wp_kses_post( $context['priceHtml'] ) . '</div>';
				}
				if ( false !== ( $settings['showShortDescription'] ?? true ) && ! empty( $context['shortDescriptionHtml'] ) ) {
					$out .= '<div class="dsf-ssr-excerpt">' . wp_kses_post( $context['shortDescriptionHtml'] ) . '</div>';
				}
				return $out . '</div>';

			case 'product-add-to-cart':
				// Already sanitized with the Woo-form allowlist; echoing makes the page
				// purchasable without JavaScript.
				return ! empty( $context['addToCartHtml'] ) ? '<div class="dsf-ssr-cart">' . $context['addToCartHtml'] . '</div>' : '';

			case 'product-description':
				if ( empty( $context['descriptionHtml'] ) ) {
					return '';
				}
				$out = '<div class="dsf-ssr-description">';
				if ( false !== ( $settings['showHeading'] ?? true ) ) {
					$out .= '<h2>' . esc_html( sanitize_text_field( $settings['headingText'] ?? 'Description' ) ) . '</h2>';
				}
				return $out . wp_kses_post( $context['descriptionHtml'] ) . '</div>';

			case 'product-specs':
				return $this->render_specs_table_server( $settings, $context );

			case 'product-tabs':
				return $this->render_tabs_server( $settings, $context );

			default:
				return '';
		}
	}

	/**
	 * Server-render a product-card grid fragment (related / upsell products).
	 *
	 * @param array  $settings        Block settings.
	 * @param array  $cards           Sanitized card payloads from the product context.
	 * @param string $default_heading Fallback heading text.
	 * @param string $css_class       Wrapper CSS class.
	 * @return string
	 */
	private function render_product_cards_server( $settings, $cards, $default_heading, $css_class ) {
		if ( empty( $cards ) ) {
			return '';
		}

		$count = max( 2, min( 8, absint( $settings['count'] ?? 4 ) ) );
		$out   = '<div class="' . esc_attr( $css_class ) . '">';
		if ( false !== ( $settings['showHeading'] ?? true ) ) {
			$out .= '<h2>' . esc_html( sanitize_text_field( $settings['headingText'] ?? $default_heading ) ) . '</h2>';
		}
		$out .= '<ul>';
		foreach ( array_slice( $cards, 0, $count ) as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$out .= '<li><a href="' . esc_url( $card['permalink'] ?? '' ) . '">';
			if ( ! empty( $card['image'] ) ) {
				$out .= '<img src="' . esc_url( $card['image'] ) . '" alt="' . esc_attr( $card['imageAlt'] ?? '' ) . '" loading="lazy" />';
			}
			$out .= '<span>' . esc_html( $card['name'] ?? '' ) . '</span>';
			if ( ! empty( $card['priceHtml'] ) ) {
				$out .= '<span class="dsf-ssr-price">' . wp_kses_post( $card['priceHtml'] ) . '</span>';
			}
			$out .= '</a></li>';
		}
		return $out . '</ul></div>';
	}

	/**
	 * Server-render the specs table fragment.
	 *
	 * @param array $settings Block settings.
	 * @param array $context  Current product context.
	 * @return string
	 */
	private function render_specs_table_server( $settings, $context ) {
		$specs = ( isset( $context['specs'] ) && is_array( $context['specs'] ) ) ? $context['specs'] : array();
		if ( empty( $specs ) ) {
			return '';
		}

		$out = '<div class="dsf-ssr-specs">';
		if ( false !== ( $settings['showHeading'] ?? true ) ) {
			$out .= '<h2>' . esc_html( sanitize_text_field( $settings['headingText'] ?? 'Specifications' ) ) . '</h2>';
		}
		$out .= '<table><tbody>';
		foreach ( $specs as $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}
			$out .= '<tr><th scope="row">' . esc_html( $spec['name'] ?? '' ) . '</th><td>' . esc_html( $spec['value'] ?? '' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * Server-render the tabs fragment (all panels stacked under their labels so the
	 * content is fully crawlable without JavaScript).
	 *
	 * @param array $settings Block settings.
	 * @param array $context  Current product context.
	 * @return string
	 */
	private function render_tabs_server( $settings, $context ) {
		$tabs = ( isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) ? $settings['tabs'] : array();
		if ( empty( $tabs ) ) {
			$tabs = array(
				array(
					'label'  => 'Description',
					'source' => 'description',
				),
			);
		}

		$out = '<div class="dsf-ssr-tabs">';
		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$label  = sanitize_text_field( $tab['label'] ?? '' );
			$source = $tab['source'] ?? 'description';
			$out   .= '<section class="dsf-ssr-tab">';
			if ( '' !== $label ) {
				$out .= '<h2>' . esc_html( $label ) . '</h2>';
			}
			if ( 'description' === $source ) {
				$out .= wp_kses_post( $context['descriptionHtml'] ?? '' );
			} elseif ( 'specs' === $source ) {
				$out .= $this->render_specs_table_server( array( 'showHeading' => false ), $context );
			} elseif ( 'reviews' === $source ) {
				// Already sanitized with the Woo-form allowlist in build_reviews_html().
				$out .= $context['reviewsHtml'] ?? '';
			} elseif ( 'custom' === $source ) {
				$out .= wp_kses_post( $tab['content'] ?? '' );
			}
			$out .= '</section>';
		}

		return $out . '</div>';
	}

	/**
	 * Build a minimal sanitized server-side fallback for the current product.
	 *
	 * @param int $product_id Product post ID.
	 * @return string
	 */
	private function build_product_seo_fallback( $product_id ) {
		$context = DSF_Product_Templates::build_product_context( $product_id );
		if ( empty( $context ) ) {
			return '';
		}

		$html = '<div class="dsf-product-fallback">';

		if ( ! empty( $context['gallery'][0]['full'] ) ) {
			$html .= '<img class="dsf-product-fallback__image" src="' . esc_url( $context['gallery'][0]['full'] ) . '" alt="' . esc_attr( $context['gallery'][0]['alt'] ) . '" fetchpriority="high" />';
		}

		$html .= '<h1 class="dsf-product-fallback__title">' . esc_html( $context['name'] ) . '</h1>';

		if ( ! empty( $context['priceHtml'] ) ) {
			$html .= '<div class="dsf-product-fallback__price">' . wp_kses_post( $context['priceHtml'] ) . '</div>';
		}
		if ( ! empty( $context['shortDescriptionHtml'] ) ) {
			$html .= '<div class="dsf-product-fallback__excerpt">' . wp_kses_post( $context['shortDescriptionHtml'] ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get the resolved product template ID for the current request (for the template file).
	 *
	 * @return int
	 */
	public function get_current_product_template_id() {
		return intval( $this->current_product_template_id );
	}

	/**
	 * Get the resolved shop template ID for the current request (for the template file).
	 *
	 * @return int
	 */
	public function get_current_shop_template_id() {
		return intval( $this->current_shop_template_id );
	}

	/**
	 * Get the resolved blog template ID for the current request (for the template file).
	 *
	 * @return int
	 */
	public function get_current_blog_template_id() {
		return intval( $this->current_blog_template_id );
	}

	/**
	 * The LCP/hero image detected for the current request (used as the social
	 * share image fallback by DSF_SEO).
	 *
	 * @return string
	 */
	public function get_hero_image_url() {
		return (string) $this->hero_image_url;
	}

	/**
	 * Render the DSF blog-template blocks mount for the current post archive.
	 *
	 * Layout/theme come from the template; the visible data is the archive's main
	 * query. The blog blocks are rendered server-side (real post links, real
	 * pagination) so archives stay crawlable; Vue hydrates the same content.
	 *
	 * @param int $template_id Blog template post ID.
	 * @return string
	 */
	public function render_blog_flow_blocks( $template_id ) {
		$template_id = intval( $template_id );
		if ( ! $template_id ) {
			return '';
		}

		$page_settings = $this->get_page_settings( $template_id );
		$theme_style   = $this->build_theme_style( $page_settings );

		$outer_class     = 'dsf-page-content dsf-blog-content';
		$inner_class     = 'dsf-page-content__inner';
		$template_choice = $page_settings['layout']['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $template_id );
		if ( 'fullwidth' === $template_choice ) {
			$outer_class .= ' dsf-page-content--fullwidth';
			$inner_class .= ' dsf-page-content__inner--fullwidth';
		}

		$blocks_meta = get_post_meta( $template_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$decoded = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}

		$context = $this->current_blog_archive_context;
		if ( empty( $context ) ) {
			$context                            = DSF_Blog_Templates::build_archive_context_from_main_query();
			$this->current_blog_archive_context = $context;
		}

		$server_html = '';
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type         = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			$settings     = ( isset( $block['settings'] ) && is_array( $block['settings'] ) ) ? $block['settings'] : array();
			$server_html .= $this->render_one_blog_block_server( $type, $settings, $context );
		}
		if ( '' !== $server_html ) {
			$server_html = '<div class="dsf-product-ssr">' . $server_html . '</div>';
		}

		$output  = '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div class="' . esc_attr( $inner_class ) . '">';
		$output .= '<div id="dsf-frontend-app" class="dsf-wrapper" data-blog-template="' . intval( $template_id ) . '">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped/kses'd fragments in render_one_blog_block_server().
		$output .= $server_html;
		$output .= '</div>';
		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Server-render a single blog block for crawlers/no-JS visitors.
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Block settings (already sanitized at save time).
	 * @param array  $context  Current archive context.
	 * @return string
	 */
	private function render_one_blog_block_server( $type, $settings, $context ) {
		if ( 'blog-header' === $type ) {
			$out = '<header class="dsf-ssr-blog-header">';
			if ( false !== ( $settings['showTitle'] ?? true ) && ! empty( $context['title'] ) ) {
				$out .= '<h1>' . esc_html( $context['title'] ) . '</h1>';
			}
			if ( false !== ( $settings['showDescription'] ?? true ) && ! empty( $context['descriptionHtml'] ) ) {
				$out .= '<div class="dsf-ssr-blog-desc">' . wp_kses_post( $context['descriptionHtml'] ) . '</div>';
			}
			return $out . '</header>';
		}

		if ( 'post-loop' === $type ) {
			$cards = ( isset( $context['posts'] ) && is_array( $context['posts'] ) ) ? $context['posts'] : array();
			if ( empty( $cards ) ) {
				return '';
			}

			$out = '<div class="dsf-ssr-post-loop">';
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) ) {
					continue;
				}
				$out .= '<article><a href="' . esc_url( $card['url'] ?? '' ) . '">';
				if ( ! empty( $card['image'] ) ) {
					$out .= '<img src="' . esc_url( $card['image'] ) . '" alt="' . esc_attr( $card['imageAlt'] ?? '' ) . '" loading="lazy" />';
				}
				$out .= '<h2>' . esc_html( $card['title'] ?? '' ) . '</h2></a>';
				if ( false !== ( $settings['showExcerpt'] ?? true ) && ! empty( $card['excerpt'] ) ) {
					$out .= '<p>' . esc_html( $card['excerpt'] ) . '</p>';
				}
				if ( ! empty( $card['dateIso'] ) && ! empty( $card['date'] ) ) {
					$out .= '<time datetime="' . esc_attr( $card['dateIso'] ) . '">' . esc_html( $card['date'] ) . '</time>';
				}
				$out .= '</article>';
			}

			$pagination = ( isset( $context['pagination'] ) && is_array( $context['pagination'] ) ) ? $context['pagination'] : array();
			if ( $pagination && false !== ( $settings['showPagination'] ?? true ) ) {
				$out .= '<nav class="dsf-ssr-blog-pagination" aria-label="' . esc_attr__( 'Posts pagination', 'designstudio-flow' ) . '">';
				foreach ( $pagination as $link ) {
					if ( ! is_array( $link ) ) {
						continue;
					}
					$out .= ! empty( $link['current'] )
						? '<span aria-current="page">' . esc_html( $link['label'] ?? '' ) . '</span>'
						: '<a href="' . esc_url( $link['url'] ?? '' ) . '">' . esc_html( $link['label'] ?? '' ) . '</a>';
				}
				$out .= '</nav>';
			}

			return $out . '</div>';
		}

		return '';
	}

	/**
	 * Resolve page setting for showing the active theme header.
	 */
	public function filter_show_header( $show, $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id ) {
			return $show;
		}

		$settings = $this->get_page_settings( $post_id );
		if ( isset( $settings['layout']['showHeader'] ) && false === $settings['layout']['showHeader'] ) {
			return false;
		}

		return $show;
	}

	/**
	 * Resolve page setting for showing the active theme footer.
	 */
	public function filter_show_footer( $show, $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id ) {
			return $show;
		}

		$settings = $this->get_page_settings( $post_id );
		if ( isset( $settings['layout']['showFooter'] ) && false === $settings['layout']['showFooter'] ) {
			return false;
		}

		return $show;
	}

	/**
	 * Render the assigned custom header/footer template for a Flow page.
	 */
	public function render_assigned_layout_template( $post_id, $type ) {
		$layout_data = $this->get_assigned_layout_template_data( $post_id, $type );
		if ( empty( $layout_data ) || empty( $layout_data['id'] ) ) {
			return '';
		}

		$settings    = $this->get_page_settings( $post_id );
		$type        = 'footer' === sanitize_key( $type ) ? 'footer' : 'header';
		$template_id = intval( $layout_data['id'] );
		$snapshot    = $layout_data['snapshot'] ?? '';

		$theme_style = $this->build_theme_style( $settings );
		$app_id      = 'dsf-layout-' . $type . '-app';

		$output  = '<div class="dsf-layout-template dsf-layout-template--' . esc_attr( $type ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div id="' . esc_attr( $app_id ) . '" class="dsf-wrapper" data-dsf-layout-id="' . intval( $template_id ) . '" data-dsf-layout-type="' . esc_attr( $type ) . '">';
		if ( ! empty( $snapshot ) ) {
			$output .= $snapshot;
		}
		$output .= '</div>';
		$output .= '</div>';
		return $output;
	}

	/**
	 * Collect assigned header/footer template data for frontend bootstrapping.
	 */
	private function get_assigned_layout_templates_data( $post_id ) {
		$data = array(
			'header' => $this->get_assigned_layout_template_data( $post_id, 'header' ),
			'footer' => $this->get_assigned_layout_template_data( $post_id, 'footer' ),
		);

		return $data;
	}

	/**
	 * Resolve one assigned template and return sanitized data for rendering/mounting.
	 */
	private function get_assigned_layout_template_data( $post_id, $type ) {
		$post_id = intval( $post_id );
		$type    = sanitize_key( $type );
		if ( ! $post_id || ! in_array( $type, array( 'header', 'footer' ), true ) ) {
			return array();
		}

		$settings    = $this->get_page_settings( $post_id );
		$layout_key  = 'header' === $type ? 'headerTemplateId' : 'footerTemplateId';
		$template_id = absint( $settings['layout'][ $layout_key ] ?? 0 );
		if ( ! $template_id ) {
			// The page hasn't picked one — fall back to the site-wide default
			// header/footer chosen in DesignStudio Flow → Settings.
			$template_id = self::get_default_layout_id( $type );
		}
		if ( ! $template_id ) {
			return array();
		}

		$template_post = get_post( $template_id );
		if ( ! $template_post || 'dsf_layout' !== $template_post->post_type ) {
			return array();
		}

		$template_type = get_post_meta( $template_id, '_dsf_layout_type', true );
		$template_type = 'footer' === $template_type ? 'footer' : 'header';
		if ( $type !== $template_type ) {
			return array();
		}

		if ( 'publish' !== $template_post->post_status ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $template_id ) ) {
				return array();
			}
		}

		$blocks_meta = get_post_meta( $template_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$decoded = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}

		$snapshot = get_post_meta( $template_id, '_dsf_html_snapshot', true );

		return array(
			'id'       => $template_id,
			'status'   => $template_post->post_status,
			'blocks'   => $blocks,
			'snapshot' => is_string( $snapshot ) ? $snapshot : '',
		);
	}

	/**
	 * Resolve the site-wide default header/footer layout ID from options.
	 *
	 * Validates the stored ID still points to an existing layout of the right type
	 * (a deleted/retyped template falls back to "none"). Publish-status visibility
	 * is intentionally NOT enforced here — the caller applies the same status check
	 * used for explicitly-assigned templates, so a draft default still previews for
	 * logged-in editors while staying hidden from the public.
	 *
	 * @param string $type 'header' or 'footer'.
	 * @return int Layout post ID, or 0.
	 */
	public static function get_default_layout_id( $type ) {
		$type = 'footer' === $type ? 'footer' : 'header';
		$id   = absint( get_option( 'header' === $type ? 'dsf_default_header_id' : 'dsf_default_footer_id', 0 ) );
		if ( ! $id ) {
			return 0;
		}

		$post = get_post( $id );
		if ( ! $post || 'dsf_layout' !== $post->post_type || 'trash' === $post->post_status ) {
			return 0;
		}

		$stored_type = get_post_meta( $id, '_dsf_layout_type', true );
		$stored_type = 'footer' === $stored_type ? 'footer' : 'header';

		return $stored_type === $type ? $id : 0;
	}

	/**
	 * Apply a header/footer layout to every DSF page + product template.
	 *
	 * Used when the site-wide default is saved in Settings so all existing pages
	 * switch to the chosen header/footer (future pages inherit it via the default
	 * fallback). Writes the explicit assignment so it renders through the same,
	 * proven per-page path — independent of the fallback.
	 *
	 * @param string $type      'header' or 'footer'.
	 * @param int    $layout_id Layout post ID (0 = no-op).
	 * @return int Number of posts updated.
	 */
	public static function apply_layout_to_all_flow_content( $type, $layout_id ) {
		$type      = 'footer' === $type ? 'footer' : 'header';
		$key       = 'header' === $type ? 'headerTemplateId' : 'footerTemplateId';
		$layout_id = absint( $layout_id );
		if ( ! $layout_id ) {
			return 0;
		}

		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );

		$page_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => $statuses,
				'posts_per_page' => 3000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one-off admin bulk apply.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_dsf_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$template_ids = get_posts(
			array(
				'post_type'      => 'dsf_product_template',
				'post_status'    => $statuses,
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one-off admin bulk apply.
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$updated = 0;
		foreach ( array_merge( (array) $page_ids, (array) $template_ids ) as $post_id ) {
			$post_id  = absint( $post_id );
			$settings = get_post_meta( $post_id, '_dsf_settings', true );
			if ( ! is_array( $settings ) ) {
				$decoded  = $settings ? json_decode( $settings, true ) : array();
				$settings = is_array( $decoded ) ? $decoded : array();
			}
			if ( ! isset( $settings['layout'] ) || ! is_array( $settings['layout'] ) ) {
				$settings['layout'] = array();
			}
			if ( absint( $settings['layout'][ $key ] ?? 0 ) === $layout_id ) {
				continue;
			}
			$settings['layout'][ $key ] = $layout_id;
			update_post_meta( $post_id, '_dsf_settings', $settings );
			++$updated;
		}

		return $updated;
	}

	private function get_default_settings() {
		return array(
			'theme'  => self::get_default_theme_settings(),
			'layout' => array(
				'containerWidth'   => self::get_typography_option()['container_width'],
				'contentPadding'   => 10,
				'showHeader'       => true,
				'showFooter'       => true,
				'headerTemplateId' => 0,
				'footerTemplateId' => 0,
				'template'         => 'default',
			),
			'popup'  => array(
				'enabled'         => false,
				'type'            => 'content',
				'headline'        => 'Limited time offer',
				'body'            => '<p>Add your popup message here.</p>',
				'image'           => '',
				'imageAlt'        => '',
				'imagePosition'   => 'top',
				'buttonText'      => 'Learn more',
				'buttonUrl'       => '#',
				'openNewTab'      => false,
				'width'           => 'medium',
				'position'        => 'center',
				'delaySeconds'    => 3,
				'startDate'       => '',
				'endDate'         => '',
				'cookieDuration'  => 24,
				'cookieUnit'      => 'hours',
				'showOverlay'     => true,
				'closeOnOverlay'  => true,
				'showClose'       => true,
				'backgroundColor' => '#FFFFFF',
				'textColor'       => '#1F2937',
				'accentColor'     => '#2C5F5D',
			),
		);
	}

	public function get_page_settings( $post_id ) {
		if ( isset( $this->settings_cache[ $post_id ] ) ) {
			return $this->settings_cache[ $post_id ];
		}

		$raw_settings = get_post_meta( $post_id, '_dsf_settings', true );
		if ( is_array( $raw_settings ) ) {
			$settings = $raw_settings;
		} else {
			$decoded  = $raw_settings ? json_decode( $raw_settings, true ) : array();
			$settings = is_array( $decoded ) ? $decoded : array();
		}

		$defaults           = $this->get_default_settings();
		$settings['theme']  = array_merge( $defaults['theme'], $settings['theme'] ?? array() );
		$settings['layout'] = array_merge( $defaults['layout'], $settings['layout'] ?? array() );
		$settings['popup']  = array_merge( $defaults['popup'], $settings['popup'] ?? array() );

		$this->settings_cache[ $post_id ] = $settings;
		return $settings;
	}

	private function build_theme_style( $page_settings ) {
		$defaults = $this->get_default_settings();
		$theme    = array_merge( $defaults['theme'], $page_settings['theme'] ?? array() );
		$layout   = array_merge( $defaults['layout'], $page_settings['layout'] ?? array() );

		$primary         = $theme['primaryColor'] ?? $defaults['theme']['primaryColor'];
		$secondary       = $theme['secondaryColor'] ?? $defaults['theme']['secondaryColor'];
		$text            = $theme['textColor'] ?? $defaults['theme']['textColor'];
		$background      = $theme['backgroundColor'] ?? $defaults['theme']['backgroundColor'];
		$heading_font    = self::sanitize_font_family( $theme['headingFont'] ?? '' );
		$body_font       = self::sanitize_font_family( $theme['bodyFont'] ?? '' );
		$container_width = intval( $layout['containerWidth'] ?? $defaults['layout']['containerWidth'] );
		$content_padding = intval( $layout['contentPadding'] ?? $defaults['layout']['contentPadding'] );

		// Fall back to admin Typography option when per-page font is empty.
		$typography_option = self::get_typography_option();
		if ( '' === $heading_font && '' !== $typography_option['heading_font'] ) {
			$heading_font = $typography_option['heading_font'];
		}
		if ( '' === $body_font && '' !== $typography_option['body_font'] ) {
			$body_font = $typography_option['body_font'];
		}

		$style = sprintf(
			'--dsf-theme-primary:%s; --dsf-theme-secondary:%s; --dsf-theme-text:%s; --dsf-theme-background:%s; --dsf-theme-container-width:%dpx; --dsf-theme-content-padding:%dpx; --dsf-primary-500:%s; --dsf-primary-600:%s; --dsf-primary-700:%s;',
			esc_attr( $primary ),
			esc_attr( $secondary ),
			esc_attr( $text ),
			esc_attr( $background ),
			$container_width,
			$content_padding,
			esc_attr( $primary ),
			esc_attr( $primary ),
			esc_attr( $primary )
		);

		// Add font CSS variables if set
		if ( ! empty( $heading_font ) ) {
			$style .= sprintf( ' --dsf-theme-heading-font:%s;', esc_attr( $heading_font ) );
		}
		if ( ! empty( $body_font ) ) {
			$style .= sprintf( ' --dsf-theme-body-font:%s;', esc_attr( $body_font ) );
		}

		// Typography scale: base size + modular scale → size tokens, with any
		// explicit per-element size overrides from the admin Typography settings.
		$typography = self::get_default_typography();
		$overrides  = self::get_typography_size_overrides();
		foreach ( self::compute_typography_tokens( $typography['base'], $typography['scale'], $overrides ) as $name => $value ) {
			$style .= sprintf( ' %s:%s;', $name, esc_attr( $value ) );
		}

		return $style;
	}

	/**
	 * Resolve the active typography defaults.
	 * Admin override wins when configured; otherwise reads the base body size from
	 * theme.json (block themes) when available; otherwise hard defaults.
	 *
	 * @return array{base:float,scale:float}
	 */
	public static function get_default_typography() {
		$option = self::get_typography_option();

		if ( 'override' === $option['mode'] ) {
			return array(
				'base'  => $option['base'],
				'scale' => $option['scale'],
			);
		}

		$base  = 16.0;
		$scale = 1.25;

		if ( function_exists( 'wp_get_global_styles' ) ) {
			$styles = wp_get_global_styles( array( 'typography' ) );
			if ( ! empty( $styles['fontSize'] ) ) {
				$parsed = self::parse_css_length_to_px( $styles['fontSize'] );
				if ( $parsed > 0 ) {
					$base = $parsed;
				}
			}
		}

		return array(
			'base'  => $base,
			'scale' => $scale,
		);
	}

	/**
	 * Read and normalize the admin Typography option.
	 * Returns a stable shape so callers can rely on all keys being present.
	 *
	 * @return array{mode:string,heading_font:string,body_font:string,base:float,scale:float}
	 */
	public static function get_typography_option() {
		$option = function_exists( 'get_option' ) ? get_option( 'dsf_typography', array() ) : array();
		if ( ! is_array( $option ) ) {
			$option = array();
		}
		$clamp_size = static function ( $value ) {
			$value = floatval( $value );
			if ( $value <= 0 ) {
				return 0.0; // 0 = "automatic" (derive from scale).
			}
			return max( 8.0, min( 200.0, $value ) );
		};
		// Per-breakpoint base body size (0 = inherit desktop). Bounded like the base.
		$clamp_base = static function ( $value ) {
			$value = floatval( $value );
			return $value > 0 ? max( 12.0, min( 22.0, $value ) ) : 0.0;
		};

		$normalized = array(
			'mode'            => ( ( $option['mode'] ?? '' ) === 'override' ) ? 'override' : 'theme',
			'heading_font'    => isset( $option['heading_font'] ) ? (string) $option['heading_font'] : '',
			'body_font'       => isset( $option['body_font'] ) ? (string) $option['body_font'] : '',
			'base'            => isset( $option['base'] ) ? floatval( $option['base'] ) : 16.0,
			'scale'           => isset( $option['scale'] ) ? floatval( $option['scale'] ) : 1.25,
			'size_p'          => $clamp_size( $option['size_p'] ?? 0 ),
			'size_h1'         => $clamp_size( $option['size_h1'] ?? 0 ),
			'size_h2'         => $clamp_size( $option['size_h2'] ?? 0 ),
			'size_h3'         => $clamp_size( $option['size_h3'] ?? 0 ),
			'size_h4'         => $clamp_size( $option['size_h4'] ?? 0 ),
			'container_width' => isset( $option['container_width'] ) && intval( $option['container_width'] ) > 0
				? max( 320, min( 3000, intval( $option['container_width'] ) ) )
				: 1800,
		);

		// Laptop + mobile overrides (0 = inherit the desktop value for that element).
		foreach ( array( 'laptop', 'mobile' ) as $bp ) {
			$normalized[ 'base_' . $bp ] = $clamp_base( $option[ 'base_' . $bp ] ?? 0 );
			foreach ( array( 'size_p', 'size_h1', 'size_h2', 'size_h3', 'size_h4' ) as $key ) {
				$normalized[ $key . '_' . $bp ] = $clamp_size( $option[ $key . '_' . $bp ] ?? 0 );
			}
		}

		return $normalized;
	}

	/**
	 * Compute the typography CSS-variable token maps for each breakpoint.
	 *
	 * Desktop is the baseline; laptop/mobile inherit each unspecified value from
	 * desktop, so authors only override what changes at smaller widths.
	 *
	 * @return array{desktop:array,laptop:array,mobile:array}
	 */
	public static function get_responsive_typography_tokens() {
		$defaults = self::get_default_typography();
		$option   = self::get_typography_option();
		$scale    = $defaults['scale'];
		$d_base   = $defaults['base'];
		$d_over   = self::get_typography_size_overrides();

		$maps = array(
			'desktop' => self::compute_typography_tokens( $d_base, $scale, $d_over ),
		);

		foreach ( array( 'laptop', 'mobile' ) as $bp ) {
			$bp_base   = floatval( $option[ 'base_' . $bp ] ?? 0 );
			$base      = $bp_base > 0 ? $bp_base : $d_base;
			$overrides = array();
			foreach ( array( 'p' => 'size_p', 'h1' => 'size_h1', 'h2' => 'size_h2', 'h3' => 'size_h3', 'h4' => 'size_h4' ) as $el => $key ) {
				$value            = floatval( $option[ $key . '_' . $bp ] ?? 0 );
				$overrides[ $el ] = $value > 0 ? $value : ( $d_over[ $el ] ?? 0 );
			}
			$maps[ $bp ] = self::compute_typography_tokens( $base, $scale, $overrides );
		}

		return $maps;
	}

	/**
	 * Build the `<style>` CSS that overrides typography tokens at laptop/mobile
	 * widths. Only tokens that differ from desktop are emitted (with !important so
	 * they beat the desktop values set inline on the page wrapper).
	 *
	 * @return string CSS (without the surrounding <style> tag).
	 */
	public static function build_responsive_typography_css() {
		if ( 'override' !== self::get_typography_option()['mode'] ) {
			return '';
		}

		$maps    = self::get_responsive_typography_tokens();
		$desktop = $maps['desktop'];
		$queries = array(
			'laptop' => '(max-width: 1024px)',
			'mobile' => '(max-width: 767px)',
		);

		$css = '';
		foreach ( $queries as $bp => $query ) {
			$declarations = '';
			foreach ( $maps[ $bp ] as $var => $value ) {
				if ( ! isset( $desktop[ $var ] ) || $desktop[ $var ] === $value ) {
					continue;
				}
				// Var names come from a fixed internal set; values are formatted px.
				$declarations .= $var . ':' . $value . ' !important;';
			}
			if ( '' !== $declarations ) {
				$css .= '@media ' . $query . '{.dsf-page-content{' . $declarations . '}}';
			}
		}

		return $css;
	}

	/**
	 * Explicit per-element size overrides (px) from the admin Typography option.
	 * Keys: p, h1, h2, h3, h4. A value of 0 means "use the automatic size".
	 *
	 * @return array<string,float>
	 */
	public static function get_typography_size_overrides() {
		$option = self::get_typography_option();
		return array(
			'p'  => $option['size_p'],
			'h1' => $option['size_h1'],
			'h2' => $option['size_h2'],
			'h3' => $option['size_h3'],
			'h4' => $option['size_h4'],
		);
	}

	/**
	 * Sanitize a CSS font-family value without permitting additional declarations.
	 *
	 * @param mixed $value Candidate font-family stack.
	 * @return string
	 */
	public static function sanitize_font_family( $value ) {
		$raw_value = trim( (string) $value );
		$value     = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $raw_value ) : $raw_value;
		$value     = is_string( $value ) ? $value : $raw_value;
		if ( '' === $value ) {
			return '';
		}

		return preg_match( '/^[a-zA-Z0-9\s,\-_"\'().:]+$/', $value ) ? $value : '';
	}

	/**
	 * Return the site-wide DSFlow theme defaults shared by admin, editor, and frontend.
	 *
	 * @return array{primaryColor:string,secondaryColor:string,textColor:string,backgroundColor:string,headingFont:string,bodyFont:string}
	 */
	public static function get_default_theme_settings() {
		$fallbacks = array(
			'primary'    => '#2C5F5D',
			'secondary'  => '#1E40AF',
			'text'       => '#1F2937',
			'background' => '#FFFFFF',
		);
		$colors    = function_exists( 'get_option' ) ? get_option( 'dsf_default_colors', $fallbacks ) : $fallbacks;
		$colors    = is_array( $colors ) ? array_merge( $fallbacks, $colors ) : $fallbacks;
		$font      = function_exists( 'get_option' )
			? self::get_typography_option()
			: array(
				'heading_font' => '',
				'body_font'    => '',
			);

		$sanitize_color = static function ( $value, $fallback ) {
			$sanitized = sanitize_hex_color( (string) $value );
			return $sanitized ? $sanitized : $fallback;
		};

		return array(
			'primaryColor'    => $sanitize_color( $colors['primary'], $fallbacks['primary'] ),
			'secondaryColor'  => $sanitize_color( $colors['secondary'], $fallbacks['secondary'] ),
			'textColor'       => $sanitize_color( $colors['text'], $fallbacks['text'] ),
			'backgroundColor' => $sanitize_color( $colors['background'], $fallbacks['background'] ),
			'headingFont'     => self::sanitize_font_family( $font['heading_font'] ),
			'bodyFont'        => self::sanitize_font_family( $font['body_font'] ),
		);
	}

	/**
	 * Compute the CSS variable map for a given base size + modular scale ratio.
	 * Returns an ordered associative array of var name => CSS value (with units).
	 */
	public static function compute_typography_tokens( $base_px, $scale, $overrides = array() ) {
		$base  = max( 8.0, (float) $base_px );
		$scale = max( 1.05, (float) $scale );

		$sizes = array(
			'xs'   => $base / ( $scale * $scale ),
			'sm'   => $base / $scale,
			'base' => $base,
			'lg'   => $base * $scale,
			'xl'   => $base * pow( $scale, 2 ),
			'2xl'  => $base * pow( $scale, 3 ),
			'3xl'  => $base * pow( $scale, 4 ),
			'4xl'  => $base * pow( $scale, 5 ),
		);

		$tokens = array(
			'--dsf-theme-text-base'  => sprintf( '%.4gpx', $base ),
			'--dsf-theme-text-scale' => sprintf( '%.4g', $scale ),
		);
		foreach ( $sizes as $key => $px ) {
			$tokens[ '--dsf-theme-text-' . $key ] = sprintf( '%.4gpx', $px );
		}

		// Keep authored content typography consistent across themes and scale settings.
		$tokens['--dsf-theme-text-base'] = '20px';
		$tokens['--dsf-theme-p-size']    = '20px';

		// Heading aliases — semantic shortcuts for block CSS.
		$headings = array(
			'h1' => 42.0,
			'h2' => 37.0,
			'h3' => $sizes['2xl'],
			'h4' => $sizes['xl'],
			'h5' => $sizes['lg'],
			'h6' => $sizes['base'],
		);
		foreach ( $headings as $key => $px ) {
			$tokens[ '--dsf-theme-' . $key ] = sprintf( '%.4gpx', $px );
		}
		$tokens['--dsf-theme-h1-size'] = '42px';
		$tokens['--dsf-theme-h2-size'] = '37px';

		// Explicit per-element size overrides from the admin Typography settings
		// win over the derived/default sizes. A value of 0 keeps the automatic size.
		$overrides = is_array( $overrides ) ? $overrides : array();
		$size      = static function ( $value ) {
			$value = floatval( $value );
			return $value > 0 ? sprintf( '%.4gpx', $value ) : '';
		};

		$p = $size( $overrides['p'] ?? 0 );
		if ( '' !== $p ) {
			$tokens['--dsf-theme-p-size']   = $p;
			$tokens['--dsf-theme-text-base'] = $p;
		}
		foreach ( array( 'h1', 'h2', 'h3', 'h4' ) as $tag ) {
			$value = $size( $overrides[ $tag ] ?? 0 );
			if ( '' === $value ) {
				continue;
			}
			$tokens[ '--dsf-theme-' . $tag ] = $value;
			if ( 'h1' === $tag || 'h2' === $tag ) {
				$tokens[ '--dsf-theme-' . $tag . '-size' ] = $value;
			}
		}

		return $tokens;
	}

	/**
	 * Convert a CSS length string (px/rem/em) to a pixel float. Returns 0 if unparseable.
	 * Assumes 1rem = 16px (the browser default and what theme.json authors target).
	 */
	private static function parse_css_length_to_px( $value ) {
		if ( ! is_string( $value ) ) {
			return 0.0;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return 0.0;
		}
		if ( ! preg_match( '/^(-?\d*\.?\d+)\s*(px|rem|em)?$/i', $value, $m ) ) {
			return 0.0;
		}
		$num  = (float) $m[1];
		$unit = strtolower( $m[2] ?? 'px' );
		switch ( $unit ) {
			case 'rem':
			case 'em':
				return $num * 16.0;
			case 'px':
			default:
				return $num;
		}
	}

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

		// Build an id→direct_count map and a parent→children map.
		$direct_counts = array();
		$children_map  = array();
		foreach ( $categories as $cat ) {
			$direct_counts[ $cat->term_id ] = (int) $cat->count;
			if ( ! isset( $children_map[ $cat->parent ] ) ) {
				$children_map[ $cat->parent ] = array();
			}
			$children_map[ $cat->parent ][] = $cat->term_id;
		}

		// Recursively sum a category's count plus all descendants.
		$get_total = null;
		$get_total = function ( $id, $visited = array() ) use ( &$get_total, &$direct_counts, &$children_map ) {
			if ( isset( $visited[ $id ] ) ) {
				return 0; // Guard against malformed self-referencing terms.
			}
			$visited[ $id ] = true;
			$total          = isset( $direct_counts[ $id ] ) ? $direct_counts[ $id ] : 0;
			if ( ! empty( $children_map[ $id ] ) ) {
				foreach ( $children_map[ $id ] as $child_id ) {
					$total += $get_total( $child_id, $visited );
				}
			}
			return $total;
		};

		$result = array();
		foreach ( $categories as $cat ) {
			$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
			$term_link    = get_term_link( $cat );

			$result[] = array(
				'id'          => $cat->term_id,
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'parent'      => (int) $cat->parent,
				'count'       => (int) $cat->count,
				'total_count' => $get_total( $cat->term_id ),
				'url'         => is_wp_error( $term_link ) ? '' : $term_link,
				'image'       => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
				'imageAlt'    => $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			);
		}

		return $result;
	}

	private function get_wc_product_tags() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $tags ) ) {
			return array();
		}

		return array_map(
			function ( $tag ) {
				return array(
					'id'    => $tag->term_id,
					'name'  => $tag->name,
					'slug'  => $tag->slug,
					'count' => (int) $tag->count,
				);
			},
			$tags
		);
	}
}
