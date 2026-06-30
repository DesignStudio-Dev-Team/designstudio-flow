<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * The Product Summary block stores presentation options only — the product data
 * itself is read live and never trusted from the client. These tests pin down
 * that the save-time sanitizer rebuilds a clean, bounded settings array and
 * drops anything unexpected, so a hostile payload can't smuggle values through.
 */
class Test_DSF_Ajax_Product_Summary extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array(
				'return' => static function ( $v ) {
					return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_product_summary_settings' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $settings ) );
	}

	public function test_defaults_are_applied_for_empty_settings() {
		$clean = $this->sanitize( array() );

		$this->assertTrue( $clean['showTitle'] );
		$this->assertSame( 'h1', $clean['headingTag'] );
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertTrue( $clean['showPrice'] );
		$this->assertFalse( $clean['showSku'] );
		$this->assertSame( 640, $clean['maxWidth'] );
		$this->assertSame( 25, $clean['marginY'] );
	}

	public function test_invalid_enums_fall_back_to_safe_values() {
		$clean = $this->sanitize(
			array(
				'headingTag' => 'h7"><script>',
				'alignment'  => 'justify',
			)
		);

		$this->assertSame( 'h1', $clean['headingTag'] );
		$this->assertSame( 'left', $clean['alignment'] );
	}

	public function test_numeric_controls_are_clamped() {
		$clean = $this->sanitize(
			array(
				'maxWidth' => 999999,
				'padding'  => -10, // absint() makes this 10 before clamping.
				'paddingX' => 99999,
				'marginY'  => 5000,
			)
		);

		$this->assertSame( 1200, $clean['maxWidth'] );
		$this->assertSame( 10, $clean['padding'] );
		$this->assertSame( 120, $clean['paddingX'] );
		$this->assertSame( 100, $clean['marginY'] );
	}

	public function test_colors_are_validated_and_rejected_when_malformed() {
		$clean = $this->sanitize(
			array(
				'titleColor' => '#1a2b3c',
				'priceColor' => 'red; background:url(x)',
				'textColor'  => '#GGGGGG',
			)
		);

		$this->assertSame( '#1a2b3c', $clean['titleColor'] );
		$this->assertSame( '', $clean['priceColor'] );
		$this->assertSame( '', $clean['textColor'] );
	}

	public function test_unknown_keys_are_discarded() {
		$clean = $this->sanitize(
			array(
				'maliciousHtml' => '<script>alert(1)</script>',
				'showPrice'     => true,
			)
		);

		$this->assertArrayNotHasKey( 'maliciousHtml', $clean );
	}

	public function test_responsive_overrides_are_bounded() {
		$clean = $this->sanitize(
			array(
				'responsive' => array(
					'mobile' => array( 'padding' => 9999, 'paddingX' => -4, 'marginY' => 7000 ),
					'tablet' => 'not-an-array',
				),
			)
		);

		$this->assertSame( 160, $clean['responsive']['mobile']['padding'] );
		$this->assertSame( 4, $clean['responsive']['mobile']['paddingX'] ); // absint(-4) = 4.
		$this->assertSame( 100, $clean['responsive']['mobile']['marginY'] );
		$this->assertSame( array(), $clean['responsive']['tablet'] );
	}
}
