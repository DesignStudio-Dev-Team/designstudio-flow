<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * @coversNothing
 */
final class Test_DSF_Ajax_Image_Logo_Grid extends TestCase {

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
				if ( ! in_array( strtolower( $matches[1] ), $allowed, true ) ) return '';
			}
			return $value;
		} ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_cards_are_capped_and_urls_are_restricted(): void {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_image_logo_grid_settings' );
		$method->setAccessible( true );
		$clean = $method->invoke( $ajax, array(
			'title' => '<script>Brands</script>',
			'linkUrl' => 'javascript:alert(1)',
			'items' => array_merge(
				array( array( 'image' => 'javascript:alert(1)', 'logo' => 'https://cdn.example.test/logo.png', 'url' => 'javascript:alert(2)', 'unknown' => 'drop' ) ),
				array_fill( 0, 8, array( 'image' => 'https://cdn.example.test/image.jpg', 'logo' => 'https://cdn.example.test/logo.png' ) )
			),
			'padding' => 999,
		) );

		$this->assertSame( 'Brands', $clean['title'] );
		$this->assertCount( 8, $clean['items'] );
		$this->assertSame( '', $clean['items'][0]['image'] );
		$this->assertSame( '', $clean['items'][0]['url'] );
		$this->assertArrayNotHasKey( 'unknown', $clean['items'][0] );
		$this->assertSame( 160, $clean['padding'] );
	}
}
