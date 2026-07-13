<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-store-pages.php';

/**
 * Store blocks: the fragment-needs scan, step detection, and the save-time
 * sanitizer contracts for cart/checkout/account/steps settings. The fragment
 * HTML itself is server-captured Woo output — nothing client-submitted is HTML.
 */
class Test_DSF_Store_Pages extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null; } )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $method, ...$args ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, $args );
	}

	// ---- Fragment-needs scan ----

	public function test_needs_scan_flags_only_used_fragments() {
		$needs = DSF_Store_Pages::blocks_need_fragments(
			array(
				array( 'type' => 'content' ),
				array( 'type' => 'store-cart' ),
				array( 'type' => 'store-steps' ),
				'garbage',
				null,
			)
		);

		$this->assertTrue( $needs['cart'] );
		$this->assertTrue( $needs['steps'] );
		$this->assertTrue( $needs['any'] );
		$this->assertFalse( $needs['checkout'] );
		$this->assertFalse( $needs['account'] );
	}

	public function test_needs_scan_empty_page() {
		$needs = DSF_Store_Pages::blocks_need_fragments( array( array( 'type' => 'hero' ) ) );
		$this->assertFalse( $needs['any'] );
		$this->assertFalse( $needs['steps'] );

		$needs = DSF_Store_Pages::blocks_need_fragments( 'not-an-array' );
		$this->assertFalse( $needs['any'] );
	}

	public function test_steps_block_alone_does_not_force_fragments() {
		$needs = DSF_Store_Pages::blocks_need_fragments( array( array( 'type' => 'store-steps' ) ) );
		$this->assertTrue( $needs['steps'] );
		$this->assertFalse( $needs['any'] );
	}

	// ---- Step detection (WooCommerce inactive → always '') ----

	public function test_detect_step_without_woocommerce_is_empty() {
		$this->assertSame( '', DSF_Store_Pages::detect_step() );
	}

	public function test_build_store_context_without_woocommerce_has_no_fragments() {
		$context = DSF_Store_Pages::build_store_context(
			array(
				'cart'     => true,
				'checkout' => true,
				'account'  => true,
			)
		);
		$this->assertSame( array(), $context['fragments'] );
		$this->assertSame( '', $context['step'] );
		$this->assertArrayHasKey( 'urls', $context );
	}

	// ---- Fragment block sanitizers ----

	public function test_cart_settings_defaults_and_toggle() {
		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-cart', array() );
		$this->assertTrue( $clean['showCrossSells'] );
		$this->assertSame( 1100, $clean['maxWidth'] );
		$this->assertSame( 24, $clean['padding'] );
		$this->assertArrayHasKey( 'buttonColor', $clean );

		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-cart', array( 'showCrossSells' => false ) );
		$this->assertFalse( $clean['showCrossSells'] );
	}

	public function test_checkout_layout_enum_and_defaults() {
		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-checkout', array( 'layout' => 'diagonal' ) );
		$this->assertSame( 'split', $clean['layout'] );
		$this->assertSame( 1140, $clean['maxWidth'] );

		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-checkout', array( 'layout' => 'stacked' ) );
		$this->assertSame( 'stacked', $clean['layout'] );
	}

	public function test_account_nav_enum_and_no_button_colors() {
		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-account', array( 'navStyle' => 'floating' ) );
		$this->assertSame( 'side', $clean['navStyle'] );
		$this->assertArrayNotHasKey( 'buttonColor', $clean );

		$clean = $this->sanitize( 'sanitize_store_fragment_settings', 'store-account', array( 'navStyle' => 'top' ) );
		$this->assertSame( 'top', $clean['navStyle'] );
	}

	public function test_fragment_settings_never_accept_client_html_or_unknown_keys() {
		$clean = $this->sanitize(
			'sanitize_store_fragment_settings',
			'store-checkout',
			array(
				'checkoutHtml' => '<script>steal()</script>',
				'fragments'    => array( 'cart' => '<iframe>' ),
				'accentColor'  => 'url(javascript:x)',
				'buttonColor'  => '#112233',
			)
		);
		$this->assertArrayNotHasKey( 'checkoutHtml', $clean );
		$this->assertArrayNotHasKey( 'fragments', $clean );
		$this->assertSame( '', $clean['accentColor'] );
		$this->assertSame( '#112233', $clean['buttonColor'] );
	}

	public function test_fragment_settings_dimensions_clamped() {
		$clean = $this->sanitize(
			'sanitize_store_fragment_settings',
			'store-cart',
			array(
				'maxWidth' => 5,
				'padding'  => 999,
				'marginY'  => 999,
			)
		);
		$this->assertSame( 720, $clean['maxWidth'] );
		$this->assertSame( 160, $clean['padding'] );
		$this->assertSame( 100, $clean['marginY'] );
	}

	// ---- Steps sanitizer ----

	public function test_steps_labels_plain_text_and_enum() {
		$clean = $this->sanitize(
			'sanitize_store_steps_settings',
			array(
				'labelCart'   => '<b>Basket</b>',
				'currentStep' => 'shipping',
				'linkSteps'   => false,
			)
		);
		$this->assertSame( 'Basket', $clean['labelCart'] );
		$this->assertSame( 'Checkout', $clean['labelCheckout'] );
		$this->assertSame( 'auto', $clean['currentStep'] );
		$this->assertFalse( $clean['linkSteps'] );

		$clean = $this->sanitize( 'sanitize_store_steps_settings', array( 'currentStep' => 'complete' ) );
		$this->assertSame( 'complete', $clean['currentStep'] );
	}

	public function test_steps_unknown_keys_dropped() {
		$clean = $this->sanitize( 'sanitize_store_steps_settings', array( 'stepsHtml' => '<script>' ) );
		$this->assertArrayNotHasKey( 'stepsHtml', $clean );
	}
}
