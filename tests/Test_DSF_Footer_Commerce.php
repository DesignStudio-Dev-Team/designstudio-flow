<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Server-side sanitization for the Footer Commerce block: toggles become
 * booleans, colors fall back, link/payment repeaters are capped and per-field
 * filtered, feature icons fall back to a known value, and protocol-relative
 * URLs are dropped.
 */
class Test_DSF_Footer_Commerce extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static fn( $v ) => ( is_string( $v ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ) ? $v : null )
		);
		WP_Mock::userFunction( 'absint', array( 'return' => static fn( $v ) => abs( (int) $v ) ) );
		WP_Mock::userFunction( 'wp_kses_allowed_html', array( 'return' => array() ) );
		WP_Mock::userFunction( 'wp_kses', array( 'return_arg' => 0 ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_footer_commerce_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}

	public function test_toggles_scalars_and_colors() {
		$clean = $this->sanitize(
			array(
				'showFeatures'  => 1,
				'showNewsletter' => 0,
				'logoText'      => 'Acme',
				'brandText'     => 'Hello',
				'headingColor'  => '#0f172a',
				'accentColor'   => 'nope',
			)
		);

		$this->assertTrue( $clean['showFeatures'] );
		$this->assertFalse( $clean['showNewsletter'] );
		$this->assertSame( 'Acme', $clean['logoText'] );
		$this->assertSame( 'Hello', $clean['brandText'] );
		$this->assertSame( '#0f172a', $clean['headingColor'] );
		$this->assertSame( '', $clean['accentColor'], 'a bad hex color becomes empty' );
	}

	public function test_link_columns_are_capped_and_urls_filtered() {
		$links = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$links[] = array( 'label' => "L$i", 'url' => '#' );
		}
		$links[] = array( 'label' => 'Bad', 'url' => '//evil.example.com' );

		$clean = $this->sanitize( array( 'column1Links' => $links ) );

		$this->assertCount( 12, $clean['column1Links'], 'no more than 12 links per column' );
		$this->assertSame( '#', $clean['column1Links'][0]['url'] );
	}

	public function test_protocol_relative_link_url_is_dropped() {
		$clean = $this->sanitize(
			array( 'socialLinks' => array( array( 'label' => 'X', 'url' => '//evil.example.com' ) ) )
		);
		$this->assertSame( '', $clean['socialLinks'][0]['url'] );
	}

	public function test_features_reuse_landing_icon_sanitizer() {
		$clean = $this->sanitize(
			array(
				'features' => array(
					array( 'icon' => 'zap', 'title' => 'A', 'description' => 'd' ),
					array( 'icon' => 'totally-made-up', 'title' => 'B', 'description' => 'd' ),
				),
			)
		);

		$this->assertSame( 'zap', $clean['features'][0]['icon'] );
		$this->assertSame( 'sparkles', $clean['features'][1]['icon'], 'unknown icon falls back to sparkles' );
	}

	public function test_newsletter_source_form_and_embed_fields() {
		$dsf = $this->sanitize(
			array(
				'newsletterSource'    => 'dsf',
				'newsletterFormId'    => '7',
				'newsletterEmbedCode' => '[gravityform id="1"]',
			)
		);
		$this->assertSame( 'dsf', $dsf['newsletterSource'] );
		$this->assertSame( '7', $dsf['newsletterFormId'], 'form id is normalized to a string' );
		$this->assertSame( '[gravityform id="1"]', $dsf['newsletterEmbedCode'] );

		$bad = $this->sanitize( array( 'newsletterSource' => 'evil', 'newsletterFormId' => '0' ) );
		$this->assertSame( 'inline', $bad['newsletterSource'], 'unknown source falls back to inline' );
		$this->assertSame( '', $bad['newsletterFormId'], 'a zero form id becomes empty' );
	}

	public function test_payments_keep_name_logo_url() {
		$clean = $this->sanitize(
			array(
				'payments' => array(
					array( 'name' => 'Visa', 'logo' => 'https://example.com/v.png', 'url' => '/pay' ),
					array( 'name' => 'PayPal' ),
				),
			)
		);

		$this->assertSame( 'Visa', $clean['payments'][0]['name'] );
		$this->assertSame( 'https://example.com/v.png', $clean['payments'][0]['logo'] );
		$this->assertSame( 'PayPal', $clean['payments'][1]['name'] );
		$this->assertSame( '', $clean['payments'][1]['logo'] );
	}
}
