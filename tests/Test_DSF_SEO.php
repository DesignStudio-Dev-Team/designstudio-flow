<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-seo.php';

/**
 * SEO settings: the variable-template contract (pure string work) and the
 * save-time sanitizer. Values are gathered and escaped server-side at render
 * time; nothing client-submitted is output unescaped.
 */
class Test_DSF_SEO extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);
		WP_Mock::userFunction(
			'esc_url_raw',
			array( 'return' => static function ( $v ) { return preg_match( '#^https?://#i', (string) $v ) ? (string) $v : ''; } )
		);
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

	private function sanitize( $seo ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( 'sanitize_page_seo_settings' );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( $seo ) );
	}

	// ---- Variable templates ----

	public function test_apply_template_replaces_known_variables() {
		$out = DSF_SEO::apply_template(
			'{title} {sep} {site_name}',
			array(
				'title'     => 'Alpine Boots',
				'sep'       => '–',
				'site_name' => 'Trail Co',
			)
		);
		$this->assertSame( 'Alpine Boots – Trail Co', $out );
	}

	public function test_apply_template_drops_unknown_tokens_and_tidies() {
		$out = DSF_SEO::apply_template(
			'{title} {bogus} {sep} {site_name}',
			array(
				'title'     => 'Boots',
				'sep'       => '–',
				'site_name' => 'Trail Co',
			)
		);
		$this->assertSame( 'Boots – Trail Co', $out );
	}

	public function test_apply_template_trims_separators_orphaned_by_empty_values() {
		$out = DSF_SEO::apply_template(
			'{category} {sep} {title}',
			array(
				'category' => '',
				'sep'      => '–',
				'title'    => 'Boots',
			)
		);
		$this->assertSame( 'Boots', $out );

		$this->assertSame( '', DSF_SEO::apply_template( '   ', array() ) );
		$this->assertSame( '', DSF_SEO::apply_template( '{gone}', array() ) );
	}

	public function test_apply_template_is_plain_string_work() {
		// Values are already sanitized where gathered; apply_template must not
		// mangle legitimate text.
		$out = DSF_SEO::apply_template( '{title}', array( 'title' => "O'Neill & Sons 50% off" ) );
		$this->assertSame( "O'Neill & Sons 50% off", $out );
	}

	// ---- Plugin defer ----

	public function test_no_seo_plugin_detected_by_default() {
		$this->assertFalse( DSF_SEO::has_seo_plugin() );
	}

	// ---- Sanitizer ----

	public function test_sanitizer_defaults_and_length_caps() {
		$clean = $this->sanitize( array() );
		$this->assertSame( '', $clean['title'] );
		$this->assertSame( '', $clean['description'] );
		$this->assertFalse( $clean['noindex'] );

		$clean = $this->sanitize(
			array(
				'title'       => str_repeat( 'a', 400 ),
				'description' => str_repeat( 'b', 400 ),
				'noindex'     => '1',
			)
		);
		$this->assertSame( 200, mb_strlen( $clean['title'] ) );
		$this->assertSame( 300, mb_strlen( $clean['description'] ) );
		$this->assertTrue( $clean['noindex'] );
	}

	public function test_sanitizer_strips_html_and_validates_urls() {
		$clean = $this->sanitize(
			array(
				'title'       => '<script>alert(1)</script>{title} {sep} {site_name}',
				'description' => '<b>Great</b> products',
				'socialImage' => 'javascript:alert(1)',
				'canonical'   => 'not-a-url',
			)
		);
		$this->assertStringNotContainsString( '<script', $clean['title'] );
		$this->assertStringContainsString( '{title} {sep} {site_name}', $clean['title'] );
		$this->assertSame( 'Great products', $clean['description'] );
		$this->assertSame( '', $clean['socialImage'] );
		$this->assertSame( '', $clean['canonical'] );
	}

	public function test_sanitizer_accepts_valid_urls_and_rejects_relative_canonical() {
		$clean = $this->sanitize(
			array(
				'socialImage' => 'https://example.com/share.jpg',
				'canonical'   => 'https://example.com/page/',
			)
		);
		$this->assertSame( 'https://example.com/share.jpg', $clean['socialImage'] );
		$this->assertSame( 'https://example.com/page/', $clean['canonical'] );

		$clean = $this->sanitize( array( 'canonical' => '/relative/path' ) );
		$this->assertSame( '', $clean['canonical'] );
	}

	public function test_sanitizer_non_array_input_returns_safe_defaults() {
		$clean = $this->sanitize( 'garbage' );
		$this->assertSame( '', $clean['title'] );
		$this->assertFalse( $clean['noindex'] );
	}
}
