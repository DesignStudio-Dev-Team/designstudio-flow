<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * @coversNothing
 */
final class Test_DSF_Ajax_Tabbed_Product_Showcase extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return' => static function ( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? $value : null; } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $value, $protocols = null ) {
			$value = (string) $value;
			if ( preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $value, $matches ) ) {
				$allowed = is_array( $protocols ) ? $protocols : array( 'http', 'https', 'mailto', 'tel' );
				if ( ! in_array( strtolower( $matches[1] ), $allowed, true ) ) {
					return '';
				}
			}
			return $value;
		} ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_tabbed_showcase_sanitizer_bounds_nested_tabs_and_urls(): void {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_tabbed_product_showcase_settings' );
		$method->setAccessible( true );

		$clean = $method->invoke( $ajax, array(
			'title' => '<script>Featured</script>',
			'tabs' => array(
				array(
					'label' => '<b>Luxury</b>',
					'source' => 'images',
					'supportingText' => '<script>Optional line</script>', // Legacy field must be discarded.
					'images' => array(
						array(
							'image' => 'javascript:alert(1)',
							'title' => '<img onerror="x">Tahiti',
							'subtitle' => '6 Seats',
							'secondarySubtitle' => '<b>48 Jets</b>',
							'url' => 'javascript:alert(1)',
						),
					),
				),
				array( 'label' => 'Products', 'source' => 'invalid', 'productIds' => array( 12, 'bad', -5 ) ),
			),
			'primaryUrl' => 'javascript:alert(1)',
			'style' => 'tabs',
			'description' => '<p>Explore the collection</p>',
			'showDescription' => false,
			'backgroundColor' => 'not-a-color',
			'primaryButtonColor' => '#123456',
			'primaryButtonTextColor' => 'not-a-color',
			'secondaryButtonColor' => '#654321',
			'secondaryButtonTextColor' => '#ABCDEF',
			'tabTextColor' => '#112233',
			'activeTabTextColor' => 'not-a-color',
			'modernTabsBackgroundColor' => '#AABBCC',
			'modernActiveTabBackgroundColor' => '#DDEEFF',
			'padding' => 999,
		) );

		$this->assertSame( 'Featured', $clean['title'] );
		$this->assertSame( 'tabs', $clean['style'] );
		$this->assertFalse( $clean['showDescription'] );
		$this->assertSame( 'Explore the collection', $clean['description'] );
		$this->assertSame( 'Luxury', $clean['tabs'][0]['label'] );
		$this->assertArrayNotHasKey( 'supportingText', $clean['tabs'][0] );
		$this->assertSame( '', $clean['tabs'][0]['images'][0]['image'] );
		$this->assertSame( '', $clean['tabs'][0]['images'][0]['url'] );
		$this->assertSame( '48 Jets', $clean['tabs'][0]['images'][0]['secondarySubtitle'] );
		$this->assertSame( array( 12 ), $clean['tabs'][1]['productIds'] );
		$this->assertSame( '', $clean['primaryUrl'] );
		$this->assertSame( '#FFFFFF', $clean['backgroundColor'] );
		$this->assertSame( '#123456', $clean['primaryButtonColor'] );
		$this->assertSame( '#FFFFFF', $clean['primaryButtonTextColor'] );
		$this->assertSame( '#654321', $clean['secondaryButtonColor'] );
		$this->assertSame( '#ABCDEF', $clean['secondaryButtonTextColor'] );
		$this->assertSame( '#112233', $clean['tabTextColor'] );
		$this->assertSame( '#111111', $clean['activeTabTextColor'] );
		$this->assertSame( '#AABBCC', $clean['modernTabsBackgroundColor'] );
		$this->assertSame( '#DDEEFF', $clean['modernActiveTabBackgroundColor'] );
		$this->assertSame( 160, $clean['padding'] );
	}
}
