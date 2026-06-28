<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

/**
 * Saved Blocks store a block's type + full settings for reuse on other pages.
 * The save handler runs those settings through the same per-type sanitizer used
 * when saving a page, by wrapping the single block in the array shape that
 * sanitize_known_block_settings() expects. These tests cover that wrap/extract.
 */
class Test_DSF_Ajax_Saved_Blocks extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return' => 'safe-answer' ) );
		WP_Mock::userFunction(
			'wp_kses',
			array(
				'return' => static function ( $html, $allowed = null ) {
					$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
					$html = preg_replace( '/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html );
					return $html;
				},
			)
		);
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-06-22 12:00:00' ) ) );
		WP_Mock::userFunction(
			'esc_url_raw',
			array(
				'return' => static function ( $value ) {
					return 0 === strpos( $value, 'javascript:' ) ? '' : $value;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_single_landing_block_keeps_shape_and_whitelists_fields() {
		$result = $this->sanitize_known(
			array(
				array(
					'type'     => 'landing-hero',
					'settings' => array(
						'textColor'   => '#123456',
						'unknownHtml' => '<iframe src="bad"></iframe>',
					),
				),
			)
		);

		// The single-block wrap/extract preserves type + returns a settings array.
		$this->assertArrayHasKey( 0, $result );
		$this->assertSame( 'landing-hero', $result[0]['type'] );
		$settings = $result[0]['settings'];
		$this->assertIsArray( $settings );
		// Unknown fields are dropped; recognised colour fields survive.
		$this->assertArrayNotHasKey( 'unknownHtml', $settings );
		$this->assertSame( '#123456', $settings['textColor'] );
	}

	private function sanitize_known( $blocks ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_known_block_settings' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $blocks ) );
	}
}
