<?php
/**
 * WooCommerce store-page fragments for DesignStudio Flow.
 *
 * Store blocks (cart, checkout, my-account) let a DSF-designed page embed
 * WooCommerce's own shortcode output instead of reimplementing it, so coupons,
 * shipping, payment gateways, and the order-received screen keep working and
 * stay update-safe. The fragments are captured server-side at request time
 * (never snapshotted — a cart is per-visitor), sanitized with the shared
 * Woo-form allowlist, and printed in a hidden container OUTSIDE the Vue mount
 * root. The Vue store blocks then ADOPT the live DOM node into position on
 * mount: the element is moved, not re-rendered, so event bindings made by
 * WooCommerce's scripts (checkout, payment gateways) survive intact. Without
 * JavaScript the container is revealed via <noscript>, keeping the page usable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Store_Pages {

	/** Block type => fragment key for blocks that embed a Woo fragment. */
	const FRAGMENT_BLOCKS = array(
		'store-cart'     => 'cart',
		'store-checkout' => 'checkout',
		'store-account'  => 'account',
		'store-login'    => 'login',
	);

	/**
	 * Decide which Woo store fragments a page's blocks actually need, so the
	 * shortcodes only run when a block embeds them.
	 *
	 * @param array $blocks Raw page blocks.
	 * @return array{cart:bool,checkout:bool,account:bool,login:bool,steps:bool,any:bool}
	 */
	public static function blocks_need_fragments( $blocks ) {
		$needs = array(
			'cart'     => false,
			'checkout' => false,
			'account'  => false,
			'login'    => false,
			'steps'    => false,
			'minicart' => false,
			'any'      => false,
		);

		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';

			if ( isset( self::FRAGMENT_BLOCKS[ $type ] ) ) {
				$needs[ self::FRAGMENT_BLOCKS[ $type ] ] = true;
				$needs['any']                            = true;
			}

			// The steps indicator and the thank-you banner both read only the slim
			// context (current step + URLs) — no Woo fragment is captured for them.
			if ( 'store-steps' === $type || 'store-thankyou' === $type ) {
				$needs['steps'] = true;
			}

			if ( 'store-mini-cart' === $type ) {
				$needs['minicart'] = true;
			}
		}

		return $needs;
	}

	/**
	 * Build the store context for the current request: the requested fragments
	 * (sanitized HTML) plus the store URLs and the visitor's current step.
	 *
	 * @param array $needs Needs from blocks_need_fragments().
	 * @return array
	 */
	public static function build_store_context( $needs ) {
		$context = array(
			'urls'      => self::get_store_urls(),
			'step'      => self::detect_step(),
			'fragments' => array(),
			'miniCart'  => null,
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return $context;
		}

		if ( ! empty( $needs['minicart'] ) ) {
			$context['miniCart'] = self::build_mini_cart_state();
		}

		$shortcodes = array(
			'cart'     => '[woocommerce_cart]',
			'checkout' => '[woocommerce_checkout]',
			'account'  => '[woocommerce_my_account]',
		);

		foreach ( $shortcodes as $key => $shortcode ) {
			if ( empty( $needs[ $key ] ) ) {
				continue;
			}
			$html = self::capture_fragment( $shortcode );
			if ( '' !== $html ) {
				$context['fragments'][ $key ] = $html;
			}
		}
		if ( ! empty( $needs['login'] ) ) {
			$html = self::capture_login_fragment();
			if ( '' !== $html ) {
				$context['fragments']['login'] = $html;
			}
		}

		return $context;
	}

	/**
	 * Run a Woo shortcode and sanitize its output with the shared Woo-form
	 * allowlist plus the structural tags cart/checkout/account markup uses.
	 *
	 * @param string $shortcode Shortcode to render.
	 * @return string
	 */
	private static function capture_fragment( $shortcode ) {
		$html = do_shortcode( $shortcode );
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		$common = array_fill_keys( array( 'class', 'id', 'style', 'title', 'role', 'hidden', 'data-*', 'aria-*' ), true );

		$extra = array(
			'fieldset' => $common,
			'legend'   => $common,
			'optgroup' => array_merge( $common, array( 'label' => true ) ),
			'dl'       => $common,
			'dt'       => $common,
			'dd'       => $common,
			'address'  => $common,
			'h1'       => $common,
			'h4'       => $common,
			'h5'       => $common,
			'h6'       => $common,
			'caption'  => $common,
			'del'      => $common,
			'ins'      => $common,
			'mark'     => $common,
			'nav'      => $common,
			'header'   => $common,
			'footer'   => $common,
		);

		return DSF_Product_Templates::sanitize_woo_form_html( $html, $extra );
	}

	/** Capture WooCommerce's native login form without exposing custom HTML settings. */
	private static function capture_login_fragment() {
		if ( ! function_exists( 'woocommerce_login_form' ) ) {
			return '';
		}
		ob_start();
		woocommerce_login_form();
		$html = ob_get_clean();
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}
		return DSF_Product_Templates::sanitize_woo_form_html( $html );
	}

	/**
	 * The public store URLs (for the steps block and store navigation).
	 *
	 * @return array{cart:string,checkout:string,account:string,shop:string}
	 */
	public static function get_store_urls() {
		$urls = array(
			'cart'     => '',
			'checkout' => '',
			'account'  => '',
			'shop'     => '',
		);

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $urls;
		}

		foreach ( array( 'cart', 'checkout', 'myaccount', 'shop' ) as $page ) {
			$page_id = wc_get_page_id( $page );
			$url     = $page_id > 0 ? get_permalink( $page_id ) : '';
			$key     = 'myaccount' === $page ? 'account' : $page;
			if ( $url ) {
				$urls[ $key ] = esc_url_raw( $url );
			}
		}

		return $urls;
	}

	/**
	 * Which purchase step the current request is on (drives the steps block).
	 *
	 * @return string 'cart' | 'checkout' | 'complete' | ''
	 */
	public static function detect_step() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return 'complete';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'checkout';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}

		return '';
	}

	/**
	 * Print markup for the hidden fragments container.
	 *
	 * Lives OUTSIDE the Vue mount root so mounting never destroys the live nodes;
	 * each Vue store block adopts its fragment into position on mount. The
	 * <noscript> style reveals the fragments for no-JS visitors so the store
	 * stays usable (the designed page around it simply stays static).
	 *
	 * @param array $context Context from build_store_context().
	 * @return string
	 */
	public static function render_fragments_container( $context ) {
		$fragments = ( isset( $context['fragments'] ) && is_array( $context['fragments'] ) ) ? $context['fragments'] : array();
		if ( empty( $fragments ) ) {
			return '';
		}

		$output = '<noscript><style>.dsf-store-fragments[hidden]{display:block}</style></noscript>';

		$output .= '<div class="dsf-store-fragments" hidden>';
		foreach ( $fragments as $key => $html ) {
			// Fragment HTML is sanitized in capture_fragment() with the Woo allowlist.
			$output .= '<div class="dsf-store-fragment" data-dsf-store-fragment="' . esc_attr( $key ) . '">' . $html . '</div>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * The mini-cart's current state (item count + subtotal) for the initial render.
	 * Live updates after AJAX add-to-cart come through the cart-fragments filter.
	 *
	 * @return array{count:int,subtotalHtml:string}
	 */
	public static function build_mini_cart_state() {
		$cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;

		return array(
			'count'        => $cart ? (int) $cart->get_cart_contents_count() : 0,
			'subtotalHtml' => $cart ? wp_kses_post( (string) $cart->get_cart_subtotal() ) : '',
		);
	}

	/**
	 * Keep the mini-cart block in sync after WooCommerce's AJAX add-to-cart: Woo
	 * replaces every element matching these selectors with the fragment HTML.
	 *
	 * @param array $fragments Existing cart fragments.
	 * @return array
	 */
	public static function add_mini_cart_fragments( $fragments ) {
		$state = self::build_mini_cart_state();

		$fragments['.dsf-store-mini-cart__count'] = '<span class="dsf-store-mini-cart__count">' . (int) $state['count'] . '</span>';
		// Subtotal HTML is Woo's own price markup, kses'd in build_mini_cart_state().
		$fragments['.dsf-store-mini-cart__subtotal'] = '<span class="dsf-store-mini-cart__subtotal">' . $state['subtotalHtml'] . '</span>';

		return $fragments;
	}

	/**
	 * Enqueue the WooCommerce scripts the embedded fragments rely on.
	 *
	 * On the canonical cart/checkout/account pages WooCommerce enqueues these
	 * itself (its is_cart()/is_checkout() checks match the page IDs), so this
	 * only fills the gap when a store block is placed on some other DSF page.
	 *
	 * @param array $needs Needs from blocks_need_fragments().
	 */
	public static function enqueue_store_scripts( $needs ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! empty( $needs['cart'] ) ) {
			wp_enqueue_script( 'wc-cart' );
		}
		if ( ! empty( $needs['checkout'] ) ) {
			wp_enqueue_script( 'wc-checkout' );
		}
		if ( ! empty( $needs['account'] ) ) {
			wp_enqueue_script( 'woocommerce' );
		}
		if ( ! empty( $needs['minicart'] ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	}
}
