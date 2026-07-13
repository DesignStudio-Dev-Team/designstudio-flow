<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Save-time contracts for the third wave of product blocks (upsells, reviews,
 * meta). Presentation-only settings must be rebuilt as clean bounded arrays;
 * product data itself (upsell cards, reviews HTML, SKU/terms) is always read
 * server-side at render time and never trusted from the client.
 */
class Test_DSF_Ajax_Product_Blocks_V3 extends TestCase {
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

	private function sanitize( $method, $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( $settings ) );
	}

	public function test_upsells_defaults() {
		$clean = $this->sanitize( 'sanitize_product_upsells_settings', array() );
		$this->assertTrue( $clean['showHeading'] );
		$this->assertSame( 'Pairs well with', $clean['headingText'] );
		$this->assertSame( 4, $clean['count'] );
		$this->assertSame( 4, $clean['columns'] );
		$this->assertTrue( $clean['showPrice'] );
		$this->assertSame( 1200, $clean['maxWidth'] );
	}

	public function test_upsells_counts_clamped_and_heading_plain_text() {
		$clean = $this->sanitize(
			'sanitize_product_upsells_settings',
			array(
				'count'       => 50,
				'columns'     => 1,
				'headingText' => '<h1>Add these</h1>',
				'maxWidth'    => 9999,
			)
		);
		$this->assertSame( 8, $clean['count'] );
		$this->assertSame( 2, $clean['columns'] );
		$this->assertSame( 'Add these', $clean['headingText'] );
		$this->assertSame( 1600, $clean['maxWidth'] );
	}

	public function test_upsells_colors_validated_and_unknown_keys_dropped() {
		$clean = $this->sanitize(
			'sanitize_product_upsells_settings',
			array(
				'accentColor' => 'javascript:alert(1)',
				'headingColor' => '#123abc',
				'cardsHtml'   => '<script>x</script>',
			)
		);
		$this->assertSame( '', $clean['accentColor'] );
		$this->assertSame( '#123abc', $clean['headingColor'] );
		$this->assertArrayNotHasKey( 'cardsHtml', $clean );
	}

	public function test_upsells_non_array_input_returns_safe_defaults() {
		$clean = $this->sanitize( 'sanitize_product_upsells_settings', 'not-an-array' );
		$this->assertSame( 4, $clean['count'] );
		$this->assertTrue( $clean['showHeading'] );
	}

	public function test_reviews_defaults_and_toggles() {
		$clean = $this->sanitize( 'sanitize_product_reviews_settings', array() );
		$this->assertTrue( $clean['showHeading'] );
		$this->assertTrue( $clean['showSummary'] );
		$this->assertSame( 'Customer Reviews', $clean['headingText'] );
		$this->assertSame( 900, $clean['maxWidth'] );

		$off = $this->sanitize(
			'sanitize_product_reviews_settings',
			array(
				'showHeading' => false,
				'showSummary' => 0,
			)
		);
		$this->assertFalse( $off['showHeading'] );
		$this->assertFalse( $off['showSummary'] );
	}

	public function test_reviews_never_accepts_client_reviews_html() {
		$clean = $this->sanitize(
			'sanitize_product_reviews_settings',
			array(
				'reviewsHtml' => '<script>steal()</script>',
				'headingText' => '<em>Reviews</em>',
			)
		);
		$this->assertArrayNotHasKey( 'reviewsHtml', $clean );
		$this->assertSame( 'Reviews', $clean['headingText'] );
	}

	public function test_reviews_spacing_clamped() {
		$clean = $this->sanitize(
			'sanitize_product_reviews_settings',
			array(
				'padding'  => 999,
				'paddingX' => -5,
				'marginY'  => 500,
			)
		);
		$this->assertSame( 160, $clean['padding'] );
		$this->assertSame( 5, $clean['paddingX'] ); // absint(-5) = 5, within bounds.
		$this->assertSame( 100, $clean['marginY'] );
	}

	public function test_meta_defaults_and_enums() {
		$clean = $this->sanitize( 'sanitize_product_meta_settings', array() );
		$this->assertTrue( $clean['showSku'] );
		$this->assertTrue( $clean['showCategories'] );
		$this->assertTrue( $clean['showTags'] );
		$this->assertSame( 'stacked', $clean['layout'] );
		$this->assertSame( 'left', $clean['alignment'] );

		$clean = $this->sanitize(
			'sanitize_product_meta_settings',
			array(
				'layout'    => 'diagonal',
				'alignment' => 'justify',
			)
		);
		$this->assertSame( 'stacked', $clean['layout'] );
		$this->assertSame( 'left', $clean['alignment'] );

		$clean = $this->sanitize(
			'sanitize_product_meta_settings',
			array(
				'layout'    => 'inline',
				'alignment' => 'center',
			)
		);
		$this->assertSame( 'inline', $clean['layout'] );
		$this->assertSame( 'center', $clean['alignment'] );
	}

	public function test_meta_never_accepts_client_terms_or_sku() {
		$clean = $this->sanitize(
			'sanitize_product_meta_settings',
			array(
				'sku'        => 'FAKE-SKU',
				'categories' => array( array( 'name' => '<script>x</script>' ) ),
				'tags'       => array( 'injected' ),
			)
		);
		$this->assertArrayNotHasKey( 'sku', $clean );
		$this->assertArrayNotHasKey( 'categories', $clean );
		$this->assertArrayNotHasKey( 'tags', $clean );
	}

	public function test_meta_colors_validated() {
		$clean = $this->sanitize(
			'sanitize_product_meta_settings',
			array(
				'labelColor' => 'red',
				'linkColor'  => '#00ff00',
			)
		);
		$this->assertSame( '', $clean['labelColor'] );
		$this->assertSame( '#00ff00', $clean['linkColor'] );
	}
}
