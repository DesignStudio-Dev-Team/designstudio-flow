<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * The Add to Cart block stores presentation options only — the cart form itself
 * is WooCommerce's own server-rendered markup. These tests pin down the bounded,
 * allowlisted settings the save path keeps.
 */
class Test_DSF_Ajax_Product_AddToCart extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null; } )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_product_add_to_cart_settings' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $settings ) );
	}

	public function test_defaults() {
		$clean = $this->sanitize( array() );
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertSame( 460, $clean['maxWidth'] );
		$this->assertSame( '', $clean['buttonColor'] );
		$this->assertSame( 25, $clean['marginY'] );
	}

	public function test_alignment_enum_and_clamps() {
		$clean = $this->sanitize(
			array(
				'alignment' => 'justify',
				'maxWidth'  => 99999,
				'padding'   => 9000,
			)
		);
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertSame( 900, $clean['maxWidth'] );
		$this->assertSame( 160, $clean['padding'] );
	}

	public function test_button_and_price_colors_validated() {
		$clean = $this->sanitize(
			array(
				'buttonColor'     => '#112233',
				'buttonTextColor' => 'javascript:alert(1)',
				'priceColor'      => '#abcdef',
			)
		);
		$this->assertSame( '#112233', $clean['buttonColor'] );
		$this->assertSame( '', $clean['buttonTextColor'] );
		$this->assertSame( '#abcdef', $clean['priceColor'] );
	}

	public function test_show_price_defaults_true_and_toggles() {
		$this->assertTrue( $this->sanitize( array() )['showPrice'] );
		$this->assertFalse( $this->sanitize( array( 'showPrice' => false ) )['showPrice'] );
		$this->assertTrue( $this->sanitize( array( 'showPrice' => 1 ) )['showPrice'] );
	}

	public function test_unknown_keys_dropped() {
		$clean = $this->sanitize( array( 'evil' => '<script>x</script>', 'alignment' => 'center' ) );
		$this->assertArrayNotHasKey( 'evil', $clean );
		$this->assertSame( 'center', $clean['alignment'] );
	}
}
