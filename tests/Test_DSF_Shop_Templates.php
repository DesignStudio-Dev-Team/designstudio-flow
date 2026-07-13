<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-shop-templates.php';

/**
 * Shop templates: the assignment-rule contract, orderby allowlist, the
 * WooCommerce-inactive fallbacks, and the save-time sanitizers for the
 * shop-header / shop-products blocks. Archive data (products, pagination,
 * sorting) is always built server-side and never trusted from the client.
 */
class Test_DSF_Shop_Templates extends TestCase {
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

	// ---- Assignment rule ----

	public function test_assignment_defaults_to_all() {
		$clean = DSF_Shop_Templates::sanitize_assignment( 'garbage' );
		$this->assertSame( 'all', $clean['mode'] );
		$this->assertSame( array(), $clean['categoryIds'] );
	}

	public function test_assignment_categories_cleaned_capped_and_deduped() {
		$clean = DSF_Shop_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array( 5, '5', -2, 'x', 9, 0 ),
			)
		);
		$this->assertSame( 'categories', $clean['mode'] );
		// -2 passes absint as 2; strings and zeros are dropped.
		$this->assertSame( array( 5, 2, 9 ), $clean['categoryIds'] );

		$many  = array_map( static function ( $i ) { return $i + 1; }, range( 0, 150 ) );
		$clean = DSF_Shop_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => $many,
			)
		);
		$this->assertCount( 100, $clean['categoryIds'] );
	}

	public function test_assignment_category_mode_without_categories_falls_back_to_all() {
		$clean = DSF_Shop_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array(),
			)
		);
		$this->assertSame( 'all', $clean['mode'] );
	}

	public function test_assignment_unknown_mode_falls_back_to_all() {
		$clean = DSF_Shop_Templates::sanitize_assignment( array( 'mode' => 'everything' ) );
		$this->assertSame( 'all', $clean['mode'] );
	}

	// ---- Orderby + Woo-inactive fallbacks ----

	public function test_orderby_options_are_the_known_woo_values() {
		$values = array_map(
			static function ( $option ) { return $option['value']; },
			DSF_Shop_Templates::get_orderby_options()
		);
		$this->assertSame( array( 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc' ), $values );
	}

	public function test_preview_context_without_woocommerce_is_null() {
		$this->assertNull( DSF_Shop_Templates::build_preview_context( 5 ) );
	}

	public function test_is_product_archive_without_woocommerce_is_false() {
		$this->assertFalse( DSF_Shop_Templates::is_product_archive() );
	}

	// ---- shop-header sanitizer ----

	public function test_shop_header_defaults_and_enum() {
		$clean = $this->sanitize( 'sanitize_shop_header_settings', array() );
		$this->assertTrue( $clean['showTitle'] );
		$this->assertTrue( $clean['showDescription'] );
		$this->assertTrue( $clean['showCount'] );
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertSame( 1200, $clean['maxWidth'] );
		$this->assertSame( 32, $clean['padding'] );

		$clean = $this->sanitize( 'sanitize_shop_header_settings', array( 'alignment' => 'justify' ) );
		$this->assertSame( 'left', $clean['alignment'] );
	}

	public function test_shop_header_never_accepts_client_archive_data() {
		$clean = $this->sanitize(
			'sanitize_shop_header_settings',
			array(
				'title'           => '<script>x</script>',
				'descriptionHtml' => '<iframe>',
				'titleColor'      => 'red',
				'textColor'       => '#123456',
			)
		);
		$this->assertArrayNotHasKey( 'title', $clean );
		$this->assertArrayNotHasKey( 'descriptionHtml', $clean );
		$this->assertSame( '', $clean['titleColor'] );
		$this->assertSame( '#123456', $clean['textColor'] );
	}

	// ---- shop-products sanitizer ----

	public function test_shop_products_defaults_and_clamps() {
		$clean = $this->sanitize( 'sanitize_shop_products_settings', array() );
		$this->assertSame( 4, $clean['columns'] );
		$this->assertSame( 'square', $clean['imageAspect'] );
		$this->assertTrue( $clean['showSorting'] );
		$this->assertTrue( $clean['showPagination'] );
		$this->assertTrue( $clean['showAddToCart'] );

		$clean = $this->sanitize(
			'sanitize_shop_products_settings',
			array(
				'columns'     => 99,
				'imageAspect' => 'circle',
				'maxWidth'    => 9999,
				'padding'     => 999,
			)
		);
		$this->assertSame( 5, $clean['columns'] );
		$this->assertSame( 'square', $clean['imageAspect'] );
		$this->assertSame( 1600, $clean['maxWidth'] );
		$this->assertSame( 160, $clean['padding'] );
	}

	public function test_shop_products_never_accepts_client_products_or_pagination() {
		$clean = $this->sanitize(
			'sanitize_shop_products_settings',
			array(
				'products'    => array( array( 'name' => '<script>x</script>' ) ),
				'pagination'  => array( array( 'url' => 'javascript:alert(1)' ) ),
				'accentColor' => 'javascript:alert(1)',
				'buttonColor' => '#0a0b0c',
			)
		);
		$this->assertArrayNotHasKey( 'products', $clean );
		$this->assertArrayNotHasKey( 'pagination', $clean );
		$this->assertSame( '', $clean['accentColor'] );
		$this->assertSame( '#0a0b0c', $clean['buttonColor'] );
	}

	public function test_shop_products_non_array_input_returns_safe_defaults() {
		$clean = $this->sanitize( 'sanitize_shop_products_settings', 'not-an-array' );
		$this->assertSame( 4, $clean['columns'] );
		$this->assertTrue( $clean['showPrice'] );
	}
}
