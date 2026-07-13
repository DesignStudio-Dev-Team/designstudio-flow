<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-site-pages.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-store-pages.php';

/**
 * Site blocks (login / search / dashboard) + the second wave of store/shop
 * blocks (mini-cart, thank-you, filters): the needs scans and the save-time
 * sanitizer contracts. All personalized/query data is built server-side per
 * request and never trusted from the client.
 */
class Test_DSF_Site_Pages extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);
		WP_Mock::userFunction(
			'sanitize_textarea_field',
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

	private function sanitize( $method, $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( $settings ) );
	}

	// ---- Needs scans ----

	public function test_site_needs_scan_flags_only_used_features() {
		$needs = DSF_Site_Pages::blocks_need_context(
			array(
				array( 'type' => 'site-login' ),
				array( 'type' => 'content' ),
				'garbage',
			)
		);
		$this->assertTrue( $needs['login'] );
		$this->assertTrue( $needs['any'] );
		$this->assertFalse( $needs['search'] );
		$this->assertFalse( $needs['dashboard'] );

		$none = DSF_Site_Pages::blocks_need_context( array( array( 'type' => 'hero' ) ) );
		$this->assertFalse( $none['any'] );
	}

	public function test_store_scan_covers_mini_cart_and_thankyou() {
		$needs = DSF_Store_Pages::blocks_need_fragments(
			array(
				array( 'type' => 'store-mini-cart' ),
				array( 'type' => 'store-thankyou' ),
			)
		);
		$this->assertTrue( $needs['minicart'] );
		$this->assertTrue( $needs['steps'] );
		// Neither captures a heavy Woo fragment.
		$this->assertFalse( $needs['any'] );
	}

	// ---- Sanitizers ----

	public function test_mini_cart_enums_and_colors() {
		$clean = $this->sanitize( 'sanitize_store_mini_cart_settings', array() );
		$this->assertSame( 'floating', $clean['placement'] );
		$this->assertSame( 'bottom-right', $clean['corner'] );
		$this->assertTrue( $clean['hideWhenEmpty'] );

		$clean = $this->sanitize(
			'sanitize_store_mini_cart_settings',
			array(
				'placement'   => 'sticky-top',
				'corner'      => 'bottom-left',
				'buttonColor' => 'expression(x)',
				'cartHtml'    => '<script>',
			)
		);
		$this->assertSame( 'floating', $clean['placement'] );
		$this->assertSame( 'bottom-left', $clean['corner'] );
		$this->assertSame( '', $clean['buttonColor'] );
		$this->assertArrayNotHasKey( 'cartHtml', $clean );
	}

	public function test_thankyou_text_plain_and_clamped() {
		$clean = $this->sanitize(
			'sanitize_store_thankyou_settings',
			array(
				'headingText' => '<h1>Yay</h1>',
				'messageText' => "<script>x</script>Line one\nLine two",
				'maxWidth'    => 9999,
			)
		);
		$this->assertSame( 'Yay', $clean['headingText'] );
		$this->assertStringNotContainsString( '<script', $clean['messageText'] );
		$this->assertSame( 1400, $clean['maxWidth'] );
		$this->assertTrue( $clean['showConfetti'] );
	}

	public function test_shop_filters_defaults_and_enum() {
		$clean = $this->sanitize( 'sanitize_shop_filters_settings', array( 'layout' => 'sidebar' ) );
		$this->assertSame( 'bar', $clean['layout'] );
		$this->assertTrue( $clean['showPrice'] );
		$this->assertTrue( $clean['showCategories'] );

		$clean = $this->sanitize(
			'sanitize_shop_filters_settings',
			array(
				'layout'     => 'panel',
				'categories' => array( '<script>' ),
			)
		);
		$this->assertSame( 'panel', $clean['layout'] );
		$this->assertArrayNotHasKey( 'categories', $clean );
	}

	public function test_login_settings_plain_text_and_never_accepts_endpoints() {
		$clean = $this->sanitize(
			'sanitize_site_login_settings',
			array(
				'headingText' => '<em>Hi</em>',
				'loginAction' => 'https://evil.example/steal',
				'redirectTo'  => 'https://evil.example',
				'maxWidth'    => 9999,
			)
		);
		$this->assertSame( 'Hi', $clean['headingText'] );
		$this->assertArrayNotHasKey( 'loginAction', $clean );
		$this->assertArrayNotHasKey( 'redirectTo', $clean );
		$this->assertSame( 720, $clean['maxWidth'] );
		$this->assertTrue( $clean['showRemember'] );
	}

	public function test_search_settings_contract() {
		$clean = $this->sanitize(
			'sanitize_site_search_settings',
			array(
				'headingText' => '<b>Find</b>',
				'placeholder' => '<i>Type…</i>',
				'results'     => array( 'injected' ),
				'accentColor' => '#abcdef',
			)
		);
		$this->assertSame( 'Find', $clean['headingText'] );
		$this->assertSame( 'Type…', $clean['placeholder'] );
		$this->assertArrayNotHasKey( 'results', $clean );
		$this->assertSame( '#abcdef', $clean['accentColor'] );
	}

	public function test_dashboard_settings_contract() {
		$clean = $this->sanitize( 'sanitize_user_dashboard_settings', array() );
		$this->assertSame( 'Welcome back,', $clean['welcomeText'] );
		$this->assertTrue( $clean['showOrders'] );

		$clean = $this->sanitize(
			'sanitize_user_dashboard_settings',
			array(
				'welcomeText'  => '<script>x</script>Hello',
				'recentOrders' => array( array( 'total' => '<script>' ) ),
				'user'         => array( 'displayName' => 'fake' ),
			)
		);
		$this->assertStringNotContainsString( '<script', $clean['welcomeText'] );
		$this->assertArrayNotHasKey( 'recentOrders', $clean );
		$this->assertArrayNotHasKey( 'user', $clean );
	}
}
