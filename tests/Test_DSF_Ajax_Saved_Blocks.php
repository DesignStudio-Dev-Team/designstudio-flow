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
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
			)
		);
		WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => static function ( $value ) {
					return json_encode( $value );
				},
			)
		);
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

	public function test_import_rejects_malformed_or_unflagged_payloads() {
		$this->assertSame( array(), $this->parse_import( 'not-json{' ) );
		$this->assertSame( array(), $this->parse_import( json_encode( array( 'items' => array() ) ) ) );
		$this->assertSame( array(), $this->parse_import( json_encode( array( '_dsf_export' => true ) ) ) );
		$this->assertSame( array(), $this->parse_import( 12345 ) );
	}

	public function test_import_keeps_only_saved_blocks_with_registered_types() {
		$payload = json_encode(
			array(
				'_dsf_export' => true,
				'items'       => array(
					array( 'post_type' => 'page', 'title' => 'A Page', 'meta' => array() ),
					array(
						'post_type' => 'dsf_saved_block',
						'title'     => 'Ghost',
						'meta'      => array( '_dsf_block_type' => 'not-a-real-block', '_dsf_block_settings' => array() ),
					),
					array(
						'post_type' => 'dsf_saved_block',
						'title'     => 'My Features',
						'meta'      => array(
							'_dsf_block_type'     => 'features-grid',
							'_dsf_block_settings' => array( 'title' => 'Hello' ),
							'_dsf_block_category' => 'Homepage',
							'_dsf_block_tags'     => array( 'promo', 'promo', 'launch' ),
						),
					),
				),
			)
		);

		$items = $this->parse_import( $payload );

		$this->assertCount( 1, $items );
		$this->assertSame( 'My Features', $items[0]['name'] );
		$this->assertSame( 'features-grid', $items[0]['type'] );
		$this->assertSame( 'Hello', $items[0]['settings']['title'] );
		$this->assertSame( 'Homepage', $items[0]['category'] );
		$this->assertSame( array( 'promo', 'launch' ), $items[0]['tags'] );
	}

	public function test_import_falls_back_to_the_block_name_and_caps_item_count() {
		$item  = array(
			'post_type' => 'dsf_saved_block',
			'meta'      => array( '_dsf_block_type' => 'features-grid', '_dsf_block_settings' => array() ),
		);
		$items = $this->parse_import(
			json_encode(
				array(
					'_dsf_export' => true,
					'items'       => array_fill( 0, 25, $item ),
				)
			)
		);

		$this->assertCount( 20, $items );
		$this->assertSame( 'Features Grid', $items[0]['name'] );
	}

	public function test_import_sanitizes_settings_through_the_per_type_contract() {
		$payload = json_encode(
			array(
				'_dsf_export' => true,
				'items'       => array(
					array(
						'post_type' => 'dsf_saved_block',
						'title'     => 'Imported Landing Hero',
						'meta'      => array(
							'_dsf_block_type'     => 'landing-hero',
							'_dsf_block_settings' => array(
								'textColor'   => '#123456',
								'unknownHtml' => '<iframe src="bad"></iframe>',
							),
						),
					),
				),
			)
		);

		$items = $this->parse_import( $payload );

		$this->assertCount( 1, $items );
		$this->assertSame( '#123456', $items[0]['settings']['textColor'] );
		$this->assertArrayNotHasKey( 'unknownHtml', $items[0]['settings'] );
	}

	private function parse_import( $payload ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'parse_saved_block_import' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $payload ) );
	}

	private function sanitize_known( $blocks ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_known_block_settings' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $blocks ) );
	}
}
