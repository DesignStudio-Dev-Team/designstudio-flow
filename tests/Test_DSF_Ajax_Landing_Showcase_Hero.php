<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Save-time contract for the landing Showcase Hero: plain-text copy, safe
 * anchor/https URLs, capped dock-style tiles, validated colors. Tile scenes are
 * derived from the preset icon at render time — nothing visual is client HTML.
 */
class Test_DSF_Ajax_Landing_Showcase_Hero extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null; } )
		);
		WP_Mock::userFunction(
			'esc_url_raw',
			array( 'return' => static function ( $v ) { return preg_match( '#^(https?:|mailto:|tel:)#i', (string) $v ) ? (string) $v : ''; } )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( 'sanitize_landing_block_settings' );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( 'landing-showcase-hero', $settings ) );
	}

	public function test_copy_fields_are_plain_text_and_length_bounded() {
		$clean = $this->sanitize(
			array(
				'eyebrow'       => str_repeat( 'E', 120 ),
				'title'         => '<script>x</script>' . str_repeat( 'T', 120 ),
				'rotatingWords' => '<em>online store</em>, visual pages, secure site',
				'tagline'       => '<em>One</em> builder ' . str_repeat( 'x', 260 ),
				'primaryText'   => str_repeat( 'P', 100 ),
				'chip1'         => '<b>60+</b> blocks ' . str_repeat( 'c', 110 ),
			)
		);
		$this->assertStringNotContainsString( '<script', $clean['title'] );
		$this->assertSame( 96, mb_strlen( $clean['eyebrow'] ) );
		$this->assertSame( 96, mb_strlen( $clean['title'] ) );
		$this->assertSame( 240, mb_strlen( $clean['tagline'] ) );
		$this->assertSame( 80, mb_strlen( $clean['primaryText'] ) );
		$this->assertSame( 96, mb_strlen( $clean['chip1'] ) );
		$this->assertStringNotContainsString( '<em', $clean['rotatingWords'] );
		$this->assertStringContainsString( 'online store, visual pages, secure site', $clean['rotatingWords'] );
	}

	public function test_rotating_words_are_individually_cleaned_unique_and_capped_at_six() {
		$long  = 'word ' . str_repeat( 'g', 90 );
		$clean = $this->sanitize(
			array(
				'rotatingWords' => " <b>Online store</b>, online store, , $long, Visual page, Next campaign, Secure site, Client site, Extra item",
			)
		);
		$words = explode( ', ', $clean['rotatingWords'] );

		$this->assertCount( 6, $words );
		$this->assertSame( 'Online store', $words[0] );
		$this->assertSame( 64, mb_strlen( $words[1] ) );
		$this->assertSame( array( 'Online store', $words[1], 'Visual page', 'Next campaign', 'Secure site', 'Client site' ), $words );
	}

	public function test_rotating_words_must_each_contain_exactly_two_words() {
		$clean = $this->sanitize(
			array(
				'rotatingWords' => 'storefront, whole WordPress site, online    store, page visually, next campaign, site securely, client site',
			)
		);

		$this->assertSame( 'online store, page visually, next campaign, site securely, client site', $clean['rotatingWords'] );
	}

	public function test_non_scalar_copy_and_phrase_values_are_rejected_without_coercion() {
		$clean = $this->sanitize(
			array(
				'title'         => array( 'not', 'text' ),
				'rotatingWords' => array( 'not', 'text' ),
				'chip1'         => new stdClass(),
			)
		);

		$this->assertSame( '', $clean['title'] );
		$this->assertSame( '', $clean['rotatingWords'] );
		$this->assertSame( '', $clean['chip1'] );
	}

	public function test_urls_allow_anchors_and_https_but_reject_javascript() {
		$clean = $this->sanitize(
			array(
				'primaryUrl'   => '#get-dsflow',
				'secondaryUrl' => 'javascript:alert(1)',
			)
		);
		$this->assertSame( '#get-dsflow', $clean['primaryUrl'] );
		$this->assertSame( '', $clean['secondaryUrl'] );
	}

	public function test_tiles_capped_at_six_cleaned_bounded_and_unknown_keys_dropped() {
		$tiles = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$tiles[] = array(
				'label'     => "<i>Tile $i</i>" . str_repeat( 'l', 120 ),
				'url'       => '#section',
				'icon'      => 'Shield-Check!!' . str_repeat( 'x', 90 ),
				'iconImage' => 'javascript:x',
				'evil'      => '<script>',
			);
		}
		$tiles[] = 'not-an-array';

		$clean = $this->sanitize( array( 'tiles' => $tiles ) );

		$this->assertCount( 6, $clean['tiles'] );
		$this->assertSame( 100, mb_strlen( $clean['tiles'][0]['label'] ) );
		$this->assertSame( '#section', $clean['tiles'][0]['url'] );
		$this->assertSame( 64, mb_strlen( $clean['tiles'][0]['icon'] ) );
		$this->assertStringStartsWith( 'shield-check', $clean['tiles'][0]['icon'] );
		$this->assertSame( '', $clean['tiles'][0]['iconImage'] );
		$this->assertArrayNotHasKey( 'evil', $clean['tiles'][0] );
	}

	public function test_non_scalar_nested_tile_fields_are_rejected() {
		$clean = $this->sanitize(
			array(
				'tiles' => array(
					array(
						'label'     => array( 'bad' ),
						'url'       => array( '#bad' ),
						'icon'      => array( 'bad' ),
						'iconImage' => array( 'bad' ),
					),
				),
			)
		);

		$this->assertSame( '', $clean['tiles'][0]['label'] );
		$this->assertSame( '', $clean['tiles'][0]['url'] );
		$this->assertSame( '', $clean['tiles'][0]['icon'] );
		$this->assertSame( '', $clean['tiles'][0]['iconImage'] );
	}

	public function test_colors_validated_and_base_landing_keys_present() {
		$clean = $this->sanitize(
			array(
				'buttonColor'      => 'expression(x)',
				'eyebrowLineColor' => '#0091ff',
				'eyebrowColor'     => '#654321',
				'accentColor'      => '#123456',
			)
		);
		$this->assertSame( '', $clean['buttonColor'] );
		$this->assertSame( '#0091ff', $clean['eyebrowLineColor'] );
		$this->assertSame( '#654321', $clean['eyebrowColor'] );
		$this->assertSame( '#123456', $clean['accentColor'] );
		$this->assertArrayHasKey( 'paddingX', $clean );
		$this->assertArrayHasKey( 'marginY', $clean );
	}

	public function test_client_scene_or_html_keys_never_survive() {
		$clean = $this->sanitize(
			array(
				'sceneHtml' => '<iframe>',
				'mosaic'    => array( '<script>' ),
			)
		);
		$this->assertArrayNotHasKey( 'sceneHtml', $clean );
		$this->assertArrayNotHasKey( 'mosaic', $clean );
	}
}
