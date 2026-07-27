<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/** @coversNothing */
final class Test_DSF_Ajax_Brand_Showcase_Grid extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return' => static function ( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? $value : null; } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $value, $protocols = null ) {
			$value = (string) $value;
			if ( preg_match( '#^([a-z][a-z0-9+.\\-]*):#i', $value, $matches ) && ! in_array( strtolower( $matches[1] ), is_array( $protocols ) ? $protocols : array( 'http', 'https', 'mailto', 'tel' ), true ) ) return '';
			return $value;
		} ) );
	}

	public function tearDown(): void { WP_Mock::tearDown(); parent::tearDown(); }

	public function test_cards_are_bounded_and_rebuilt_from_safe_fields(): void {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_brand_showcase_grid_settings' );
		$method->setAccessible( true );
		$clean = $method->invoke( $ajax, array(
			'title' => '<script>Dreams</script>',
			'cards' => array_merge( array( array( 'title' => '<b>Brand</b>', 'subtitle' => '<script>Copy</script>', 'image' => 'javascript:alert(1)', 'url' => 'javascript:alert(2)', 'backgroundColor' => 'red', 'unknown' => 'drop' ) ), array_fill( 0, 8, array( 'title' => 'Extra' ) ) ),
			'cardGap' => 999,
			'unknown' => 'drop',
		) );

		$this->assertSame( 'Dreams', $clean['title'] );
		$this->assertCount( 8, $clean['cards'] );
		$this->assertSame( '', $clean['cards'][0]['image'] );
		$this->assertSame( '', $clean['cards'][0]['url'] );
		$this->assertSame( '#F3F4F6', $clean['cards'][0]['backgroundColor'] );
		$this->assertSame( 48, $clean['cardGap'] );
		$this->assertArrayNotHasKey( 'unknown', $clean );
		$this->assertArrayNotHasKey( 'unknown', $clean['cards'][0] );
	}
}
