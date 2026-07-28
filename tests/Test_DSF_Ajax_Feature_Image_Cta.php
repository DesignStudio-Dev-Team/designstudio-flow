<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * @coversNothing
 */
final class Test_DSF_Ajax_Feature_Image_Cta extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return' => static function ( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? $value : null; } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $value, $protocols = null ) {
			$value = (string) $value;
			if ( preg_match( '#^([a-z][a-z0-9+.\\-]*):#i', $value, $matches ) ) {
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

	public function test_settings_are_rebuilt_with_safe_urls_and_bounded_feature_data(): void {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_feature_image_cta_settings' );
		$method->setAccessible( true );
		$clean = $method->invoke( $ajax, array(
			'title'           => '<script>Comfort</script>',
			'description'     => '<b>Safe copy</b>',
			'buttonUrl'       => 'javascript:alert(1)',
			'image'           => 'javascript:alert(2)',
			'imagePosition'   => 'unexpected',
			'imageInset'      => 999,
			'borderRadius'    => -10,
			'paddingY'        => 999,
			'backgroundColor' => 'red; background: url(javascript:alert(3))',
			'features'        => array_merge(
				array( array( 'icon' => 'zap', 'title' => '<b>Efficient</b>', 'description' => '<script>ignore</script>', 'unknown' => 'drop' ) ),
				array_fill( 0, 8, array( 'icon' => 'unknown-icon', 'title' => 'Extra' ) )
			),
			'unknown'         => 'drop',
		) );

		$this->assertSame( 'Comfort', $clean['title'] );
		$this->assertSame( 'Safe copy', $clean['description'] );
		$this->assertSame( '', $clean['buttonUrl'] );
		$this->assertSame( '', $clean['image'] );
		$this->assertSame( 'right', $clean['imagePosition'] );
		$this->assertSame( 200, $clean['imageInset'] );
		$this->assertSame( 0, $clean['borderRadius'] );
		$this->assertSame( 160, $clean['paddingY'] );
		$this->assertSame( '', $clean['backgroundColor'] );
		$this->assertCount( 8, $clean['features'] );
		$this->assertSame( 'zap', $clean['features'][0]['icon'] );
		$this->assertSame( 'Efficient', $clean['features'][0]['title'] );
		$this->assertArrayNotHasKey( 'unknown', $clean );
		$this->assertArrayNotHasKey( 'unknown', $clean['features'][0] );
	}
}
