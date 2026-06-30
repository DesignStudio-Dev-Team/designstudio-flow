<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * The Product Tabs block lets an author define tabs whose content comes from the
 * live product (description / specs) or their own rich text. These tests pin down
 * that the save-time sanitizer allowlists sources, caps the tab count, keeps only
 * sanitized custom content, and never lets non-custom tabs carry client HTML.
 */
class Test_DSF_Ajax_Product_Tabs extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null; } )
		);
		WP_Mock::userFunction(
			'wp_kses_post',
			array(
				'return' => static function ( $html ) {
					$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
					return preg_replace( '/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_product_tabs_settings' );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, array( $settings ) );
	}

	public function test_unknown_source_falls_back_to_description() {
		$clean = $this->sanitize(
			array(
				'tabs' => array(
					array( 'label' => 'Bad', 'source' => 'reviews_or_evil', 'content' => 'x' ),
				),
			)
		);

		$this->assertSame( 'description', $clean['tabs'][0]['source'] );
	}

	public function test_custom_tab_content_is_sanitized() {
		$clean = $this->sanitize(
			array(
				'tabs' => array(
					array(
						'label'   => 'Care',
						'source'  => 'custom',
						'content' => '<p onclick="evil()">Hand wash<script>alert(1)</script></p>',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $clean['tabs'][0]['content'] );
		$this->assertStringNotContainsString( 'onclick', $clean['tabs'][0]['content'] );
		$this->assertStringContainsString( 'Hand wash', $clean['tabs'][0]['content'] );
	}

	public function test_non_custom_tabs_drop_any_content() {
		$clean = $this->sanitize(
			array(
				'tabs' => array(
					array( 'label' => 'Specs', 'source' => 'specs', 'content' => '<p>smuggled</p>' ),
				),
			)
		);

		$this->assertSame( '', $clean['tabs'][0]['content'] );
	}

	public function test_tab_count_is_capped_at_ten() {
		$tabs = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$tabs[] = array( 'label' => "Tab $i", 'source' => 'custom', 'content' => '<p>x</p>' );
		}

		$clean = $this->sanitize( array( 'tabs' => $tabs ) );
		$this->assertCount( 10, $clean['tabs'] );
	}

	public function test_labels_are_plain_text_and_unknown_keys_dropped() {
		$clean = $this->sanitize(
			array(
				'style' => 'pills',
				'tabs'  => array(
					array( 'label' => '<b>Care</b>', 'source' => 'custom', 'content' => '<p>ok</p>', 'evil' => 'x' ),
				),
			)
		);

		$this->assertSame( 'Care', $clean['tabs'][0]['label'] );
		$this->assertArrayNotHasKey( 'evil', $clean['tabs'][0] );
		$this->assertSame( 'pills', $clean['style'] );
	}

	public function test_invalid_style_falls_back_to_underline() {
		$clean = $this->sanitize( array( 'style' => 'fancy', 'tabs' => array() ) );
		$this->assertSame( 'underline', $clean['style'] );
		$this->assertSame( array(), $clean['tabs'] );
	}

	public function test_non_array_tabs_yield_empty_list() {
		$clean = $this->sanitize( array( 'tabs' => 'nope' ) );
		$this->assertSame( array(), $clean['tabs'] );
	}
}
