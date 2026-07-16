<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * The Content block keeps its raw HTML (allowRawHtml, trusted authoring) while
 * its presentation fields — the optional full-bleed background color and the
 * numeric widths/paddings — are sanitized/clamped.
 */
class Test_DSF_Ajax_Content extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
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
		$method     = $reflection->getMethod( 'sanitize_content_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}

	public function test_raw_html_content_is_preserved() {
		$html  = '<p>Hello <a href="https://x.test">link</a></p><script>ok()</script>';
		$clean = $this->sanitize( array( 'content' => $html ) );
		$this->assertSame( $html, $clean['content'] );
	}

	public function test_background_color_accepts_hex_and_rgba_but_rejects_junk() {
		$this->assertSame( '#112233', $this->sanitize( array( 'backgroundColor' => '#112233' ) )['backgroundColor'] );
		$this->assertSame( 'rgba(0, 0, 0, 0.5)', $this->sanitize( array( 'backgroundColor' => 'rgba(0, 0, 0, 0.5)' ) )['backgroundColor'] );
		$this->assertSame( '', $this->sanitize( array( 'backgroundColor' => 'red; }body{display:none' ) )['backgroundColor'] );
	}

	public function test_widths_and_padding_clamp() {
		$clean = $this->sanitize(
			array(
				'maxWidth' => 99999,
				'padding'  => -20,
				'paddingX' => 9999,
			)
		);
		$this->assertSame( 1400, $clean['maxWidth'] );
		$this->assertSame( 0, $clean['padding'] );
		$this->assertSame( 200, $clean['paddingX'] );
	}
}
