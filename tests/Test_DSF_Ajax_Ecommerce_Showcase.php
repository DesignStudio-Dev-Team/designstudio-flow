<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Ecommerce Showcase settings are rebuilt from known, bounded fields so product
 * records and executable values cannot be smuggled into saved block data.
 */
class Test_DSF_Ajax_Ecommerce_Showcase extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? $value : null; } )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_ecommerce_showcase_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}

	public function test_product_controls_are_sanitized_and_preserved() {
		$clean = $this->sanitize(
			array(
				'displayMode'     => 'products',
				'countText'       => 'Browse {count} <b>products</b>',
				'countColor'      => '#334155',
				'showAddToCart'   => true,
				'buttonText'      => 'Buy <script>alert(1)</script> now',
				'buttonColor'     => '#112233',
				'buttonTextColor' => '#F8FAFC',
				'priceColor'      => '#445566',
				'imageFit'        => 'scale-down',
			)
		);

		$this->assertSame( 'products', $clean['displayMode'] );
		$this->assertSame( 'Browse {count} products', $clean['countText'] );
		$this->assertSame( '#334155', $clean['countColor'] );
		$this->assertTrue( $clean['showAddToCart'] );
		$this->assertStringNotContainsString( '<script>', $clean['buttonText'] );
		$this->assertSame( '#112233', $clean['buttonColor'] );
		$this->assertSame( '#F8FAFC', $clean['buttonTextColor'] );
		$this->assertSame( '#445566', $clean['priceColor'] );
		$this->assertSame( 'scale-down', $clean['imageFit'] );
	}

	public function test_invalid_values_are_bounded_and_unknown_keys_are_dropped() {
		$clean = $this->sanitize(
			array(
				'displayMode'      => 'javascript:alert(1)',
				'limit'            => 999,
				'categoryIds'      => array_merge( range( 1, 70 ), array( 1, -4, 'bad' ) ),
				'pinnedProductIds' => range( 1, 40 ),
				'buttonColor'      => 'red; background:url(javascript:alert(1))',
				'clientProducts'   => array( '<script>alert(1)</script>' ),
				'imageFit'         => 'zoom-and-crop',
			)
		);

		$this->assertSame( 'categories', $clean['displayMode'] );
		$this->assertSame( 20, $clean['limit'] );
		$this->assertCount( 50, $clean['categoryIds'] );
		$this->assertCount( 20, $clean['pinnedProductIds'] );
		$this->assertSame( '#2C5F5D', $clean['buttonColor'] );
		$this->assertSame( 'contain', $clean['imageFit'] );
		$this->assertArrayNotHasKey( 'clientProducts', $clean );
	}
}
