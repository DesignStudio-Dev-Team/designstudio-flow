<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Save-time contract for the Product Spotlight block. Presentation-only settings
 * are rebuilt as a clean bounded array; the product data and cart form are always
 * rendered server-side from the current product, never trusted from the client.
 */
class Test_DSF_Ajax_Product_Spotlight extends TestCase {
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

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( 'sanitize_product_spotlight_settings' );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( $settings ) );
	}

	public function test_defaults() {
		$clean = $this->sanitize( array() );
		$this->assertSame( 'left', $clean['imageSide'] );
		$this->assertSame( 'soft', $clean['backdrop'] );
		$this->assertTrue( $clean['showRating'] );
		$this->assertTrue( $clean['showAddToCart'] );
		$this->assertTrue( $clean['showSaleBadge'] );
		$this->assertFalse( $clean['showSku'] );
		$this->assertSame( 'Sale', $clean['saleBadgeText'] );
		$this->assertSame( 1240, $clean['maxWidth'] );
		$this->assertSame( 56, $clean['padding'] );
	}

	public function test_enums_fall_back_to_safe_values() {
		$clean = $this->sanitize(
			array(
				'imageSide' => 'top',
				'backdrop'  => 'party-mode',
			)
		);
		$this->assertSame( 'left', $clean['imageSide'] );
		$this->assertSame( 'soft', $clean['backdrop'] );

		$clean = $this->sanitize(
			array(
				'imageSide' => 'right',
				'backdrop'  => 'none',
			)
		);
		$this->assertSame( 'right', $clean['imageSide'] );
		$this->assertSame( 'none', $clean['backdrop'] );
	}

	public function test_text_fields_stripped_and_colors_validated() {
		$clean = $this->sanitize(
			array(
				'eyebrowText'   => '<script>x</script>New Season',
				'saleBadgeText' => '<b>-50%</b>',
				'accentColor'   => 'expression(alert(1))',
				'buttonColor'   => '#0a0b0c',
			)
		);
		$this->assertStringNotContainsString( '<script', $clean['eyebrowText'] );
		$this->assertStringContainsString( 'New Season', $clean['eyebrowText'] );
		$this->assertSame( '-50%', $clean['saleBadgeText'] );
		$this->assertSame( '', $clean['accentColor'] );
		$this->assertSame( '#0a0b0c', $clean['buttonColor'] );
	}

	public function test_dimensions_clamped_and_unknown_keys_dropped() {
		$clean = $this->sanitize(
			array(
				'maxWidth'    => 9999,
				'padding'     => 999,
				'paddingX'    => 999,
				'marginY'     => 999,
				'cartHtml'    => '<script>steal()</script>',
				'galleryHtml' => '<iframe>',
			)
		);
		$this->assertSame( 1600, $clean['maxWidth'] );
		$this->assertSame( 160, $clean['padding'] );
		$this->assertSame( 120, $clean['paddingX'] );
		$this->assertSame( 100, $clean['marginY'] );
		$this->assertArrayNotHasKey( 'cartHtml', $clean );
		$this->assertArrayNotHasKey( 'galleryHtml', $clean );
	}

	public function test_non_array_input_returns_safe_defaults() {
		$clean = $this->sanitize( 'not-an-array' );
		$this->assertSame( 'left', $clean['imageSide'] );
		$this->assertSame( 1240, $clean['maxWidth'] );
	}

	public function test_responsive_spacing_clamped() {
		$clean = $this->sanitize(
			array(
				'responsive' => array(
					'mobile' => array(
						'padding' => 999,
						'marginY' => 500,
					),
					'tablet' => 'garbage',
				),
			)
		);
		$this->assertSame( 160, $clean['responsive']['mobile']['padding'] );
		$this->assertSame( 100, $clean['responsive']['mobile']['marginY'] );
		$this->assertSame( array(), $clean['responsive']['tablet'] );
	}
}
