<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Save-time contracts for the second wave of product blocks (hero, highlights,
 * related). Presentation-only settings must be rebuilt as clean bounded arrays;
 * product data itself is always read server-side at render time.
 */
class Test_DSF_Ajax_Product_Blocks_V2 extends TestCase {
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

	public function test_hero_defaults_and_enums() {
		$clean = $this->sanitize( 'sanitize_product_hero_settings', array( 'imageSide' => 'top' ) );
		$this->assertSame( 'left', $clean['imageSide'] );
		$this->assertTrue( $clean['showAddToCart'] );
		$this->assertSame( 1200, $clean['maxWidth'] );
		$this->assertSame( 'Sale', $clean['saleBadgeText'] );
	}

	public function test_hero_strips_html_from_text_fields_and_validates_colors() {
		$clean = $this->sanitize(
			'sanitize_product_hero_settings',
			array(
				'eyebrowText'   => '<script>x</script>New Season',
				'saleBadgeText' => '<b>-50%</b>',
				'accentColor'   => 'expression(alert(1))',
				'titleColor'    => '#0a0b0c',
			)
		);
		// sanitize_text_field strips the tags themselves; inner text remains.
		$this->assertStringNotContainsString( '<script', $clean['eyebrowText'] );
		$this->assertStringContainsString( 'New Season', $clean['eyebrowText'] );
		$this->assertSame( '-50%', $clean['saleBadgeText'] );
		$this->assertSame( '', $clean['accentColor'] );
		$this->assertSame( '#0a0b0c', $clean['titleColor'] );
	}

	public function test_hero_unknown_keys_dropped() {
		$clean = $this->sanitize( 'sanitize_product_hero_settings', array( 'injected' => '<iframe>' ) );
		$this->assertArrayNotHasKey( 'injected', $clean );
	}

	public function test_highlights_items_capped_and_sanitized() {
		$items = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$items[] = array(
				'icon'        => 'not-a-real-icon',
				'title'       => "<em>Item $i</em>",
				'description' => 'desc',
				'evil'        => 'x',
			);
		}
		$clean = $this->sanitize( 'sanitize_product_highlights_settings', array( 'items' => $items ) );

		$this->assertCount( 8, $clean['items'] );
		$this->assertSame( 'Item 0', $clean['items'][0]['title'] );
		$this->assertArrayNotHasKey( 'evil', $clean['items'][0] );
		// Unknown icons fall back to the sanitizer's safe default, never raw input.
		$this->assertNotSame( 'not-a-real-icon', $clean['items'][0]['icon'] );
	}

	public function test_highlights_layout_enum_and_columns_clamped() {
		$clean = $this->sanitize(
			'sanitize_product_highlights_settings',
			array(
				'layout'  => 'masonry',
				'columns' => 99,
			)
		);
		$this->assertSame( 'row', $clean['layout'] );
		$this->assertSame( 4, $clean['columns'] );
	}

	public function test_related_counts_clamped_and_heading_plain_text() {
		$clean = $this->sanitize(
			'sanitize_product_related_settings',
			array(
				'count'       => 50,
				'columns'     => 1,
				'headingText' => '<h1>More</h1>',
			)
		);
		$this->assertSame( 8, $clean['count'] );
		$this->assertSame( 2, $clean['columns'] );
		$this->assertSame( 'More', $clean['headingText'] );
	}

	public function test_related_unknown_keys_dropped() {
		$clean = $this->sanitize( 'sanitize_product_related_settings', array( 'productsHtml' => '<script>' ) );
		$this->assertArrayNotHasKey( 'productsHtml', $clean );
	}
}
