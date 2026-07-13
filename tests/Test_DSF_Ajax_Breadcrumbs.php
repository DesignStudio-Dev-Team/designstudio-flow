<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Breadcrumbs block settings are presentation-only and are rebuilt from known
 * keys with bounded values and enum allowlists.
 */
class Test_DSF_Ajax_Breadcrumbs extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
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
		$method     = $reflection->getMethod( 'sanitize_breadcrumbs_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}

	public function test_enums_fall_back_and_values_clamp() {
		$clean = $this->sanitize(
			array(
				'homeLabel'   => 'Start',
				'separator'   => 'lasers',
				'align'       => 'justify',
				'showCurrent' => 1,
				'textColor'   => '#123456',
				'linkColor'   => 'javascript:alert(1)',
				'fontSize'    => 99,
				'maxWidth'    => 10,
				'paddingY'    => -5,
				'paddingX'    => 500,
			)
		);

		$this->assertSame( 'Start', $clean['homeLabel'] );
		$this->assertSame( 'chevron', $clean['separator'] );
		$this->assertSame( 'left', $clean['align'] );
		$this->assertTrue( $clean['showCurrent'] );
		$this->assertSame( '#123456', $clean['textColor'] );
		$this->assertSame( '', $clean['linkColor'] );
		$this->assertSame( 20, $clean['fontSize'] );
		$this->assertSame( 480, $clean['maxWidth'] );
		$this->assertSame( 0, $clean['paddingY'] );
		$this->assertSame( 120, $clean['paddingX'] );
	}

	public function test_valid_enums_pass_through() {
		$clean = $this->sanitize(
			array(
				'separator'   => 'slash',
				'align'       => 'center',
				'showCurrent' => 0,
			)
		);

		$this->assertSame( 'slash', $clean['separator'] );
		$this->assertSame( 'center', $clean['align'] );
		$this->assertFalse( $clean['showCurrent'] );
	}
}
