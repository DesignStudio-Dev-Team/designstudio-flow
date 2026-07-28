<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

class Test_DSF_Ajax_Anchor_Gallery extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return' => static function ( $value ) { return is_string( $value ) && preg_match( '/^#[0-9A-Fa-f]{3,6}$/', $value ) ? $value : null; } ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
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

	public function test_tiles_are_capped_and_video_urls_are_restricted_to_media_files(): void {
		$clean = $this->sanitize( array(
			'layout' => 'not-real',
			'titlePosition' => 'not-real',
			'textAlign' => 'right',
			'items' => array_merge( array(
				array( 'title' => 'Feature', 'mediaType' => 'video', 'video' => 'https://cdn.example.test/feature.mp4', 'url' => '/feature', 'unknown' => 'discard' ),
				array( 'title' => 'Unsafe', 'mediaType' => 'video', 'video' => 'javascript:alert(1)', 'url' => 'javascript:alert(2)' ),
			), array_fill( 0, 7, array( 'title' => 'Extra' ) ) ),
		) );

		$this->assertSame( 'anchor', $clean['layout'] );
		$this->assertTrue( $clean['showEyebrow'] );
		$this->assertSame( 'below', $clean['titlePosition'] );
		$this->assertSame( 'right', $clean['textAlign'] );
		$this->assertCount( 5, $clean['items'] );
		$this->assertSame( 'https://cdn.example.test/feature.mp4', $clean['items'][0]['video'] );
		$this->assertSame( '', $clean['items'][1]['video'] );
		$this->assertSame( '', $clean['items'][1]['url'] );
		$this->assertArrayNotHasKey( 'unknown', $clean['items'][0] );

		$hidden = $this->sanitize( array( 'showEyebrow' => false ) );
		$this->assertFalse( $hidden['showEyebrow'] );

		$grid = $this->sanitize( array( 'layout' => 'grid', 'items' => array_fill( 0, 10, array( 'title' => 'Grid Tile' ) ) ) );
		$this->assertCount( 8, $grid['items'] );
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_anchor_gallery_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}
}
