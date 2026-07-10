<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

class Test_DSF_Ajax_Card_Columns extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array(
				'return' => static function ( $color ) {
					if ( is_string( $color ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', trim( $color ) ) ) {
						return trim( $color );
					}
					return null;
				},
			)
		);
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'esc_url_raw',
			array(
				'return' => static function ( $value, $protocols = null ) {
					$value = (string) $value;
					if ( preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $value, $matches ) ) {
						$allowed = is_array( $protocols ) ? $protocols : array( 'http', 'https', 'mailto', 'tel' );
						if ( ! in_array( strtolower( $matches[1] ), $allowed, true ) ) {
							return '';
						}
					}
					return $value;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_unknown_enum_values_fall_back_to_safe_defaults() {
		$clean = $this->sanitize(
			array(
				'headerLayout'      => 'sideways',
				'columns'           => '9',
				'cardLayout'        => 'floating',
				'contentAlign'      => 'justify',
				'buttonStyle'       => 'blink',
				'imageFit'          => 'stretch',
				'backgroundType'    => 'video',
				'gradientDirection' => 'diagonal',
			)
		);

		$this->assertSame( 'centered', $clean['headerLayout'] );
		$this->assertSame( '3', $clean['columns'] );
		$this->assertSame( 'standard', $clean['cardLayout'] );
		$this->assertSame( 'center', $clean['contentAlign'] );
		$this->assertSame( 'arrow', $clean['buttonStyle'] );
		$this->assertSame( 'cover', $clean['imageFit'] );
		$this->assertSame( 'solid', $clean['backgroundType'] );
		$this->assertSame( 'top-bottom', $clean['gradientDirection'] );
	}

	public function test_overlay_layout_and_six_columns_are_accepted_with_clamped_scrim() {
		$clean = $this->sanitize(
			array(
				'columns'          => '6',
				'cardLayout'       => 'overlay',
				'overlayTextColor' => '#FAFAFA',
				'overlayStrength'  => 500,
				'overlayHeight'    => 5,
			)
		);

		$this->assertSame( '6', $clean['columns'] );
		$this->assertSame( 'overlay', $clean['cardLayout'] );
		$this->assertSame( '#FAFAFA', $clean['overlayTextColor'] );
		$this->assertSame( 100, $clean['overlayStrength'] );
		$this->assertSame( 20, $clean['overlayHeight'] );
	}

	public function test_cards_are_capped_and_rebuilt_from_known_keys_only() {
		$cards = array_fill(
			0,
			12,
			array(
				'icon'        => 'star',
				'title'       => 'Card',
				'unknownHtml' => '<script>alert(1)</script>',
			)
		);

		$clean = $this->sanitize( array( 'cards' => $cards ) );

		$this->assertCount( 8, $clean['cards'] );
		$this->assertArrayNotHasKey( 'unknownHtml', $clean['cards'][0] );
		$this->assertSame( 'star', $clean['cards'][0]['icon'] );
	}

	public function test_malformed_cards_and_unknown_icons_are_neutralized() {
		$clean = $this->sanitize(
			array(
				'cards' => array(
					'not-an-array',
					array( 'icon' => 'not-a-real-icon', 'title' => 'Card' ),
				),
			)
		);

		$this->assertCount( 1, $clean['cards'] );
		$this->assertSame( '', $clean['cards'][0]['icon'] );
		$this->assertSame( 'none', $clean['cards'][0]['iconType'] );
	}

	public function test_icon_type_enum_and_custom_icon_url_are_enforced() {
		$clean = $this->sanitize(
			array(
				'cards' => array(
					array(
						'iconType'   => 'custom',
						'customIcon' => 'javascript:alert(1)',
					),
					array(
						'iconType'   => 'sparkly',
						'icon'       => 'star',
						'customIcon' => 'https://example.com/icon.png',
					),
				),
			)
		);

		$this->assertSame( 'custom', $clean['cards'][0]['iconType'] );
		$this->assertSame( '', $clean['cards'][0]['customIcon'] );
		// Unknown iconType falls back to preset because a valid icon exists.
		$this->assertSame( 'preset', $clean['cards'][1]['iconType'] );
		$this->assertSame( 'https://example.com/icon.png', $clean['cards'][1]['customIcon'] );
	}

	public function test_dangerous_urls_are_rejected() {
		$clean = $this->sanitize(
			array(
				'cards' => array(
					array(
						'title'      => 'Card',
						'image'      => 'javascript:alert(1)',
						'showButton' => true,
						'buttonUrl'  => 'javascript:alert(2)',
					),
					array(
						'title'     => 'Card 2',
						'image'     => 'https://example.com/tub.png',
						'buttonUrl' => '/relative/path',
					),
				),
			)
		);

		$this->assertSame( '', $clean['cards'][0]['image'] );
		$this->assertSame( '', $clean['cards'][0]['buttonUrl'] );
		$this->assertSame( 'https://example.com/tub.png', $clean['cards'][1]['image'] );
		$this->assertSame( '/relative/path', $clean['cards'][1]['buttonUrl'] );
	}

	public function test_colors_accept_hex_and_strict_rgba_but_reject_css_injection() {
		$clean = $this->sanitize(
			array(
				'titleColor'      => '#112233',
				'backgroundColor' => 'rgba(255, 0, 0, 0.5)',
				'buttonColor'     => 'red;background:url(evil)',
				'cards'           => array(
					array( 'backgroundColor' => 'expression(alert(1))', 'gradientStart' => '#ABC' ),
				),
			)
		);

		$this->assertSame( '#112233', $clean['titleColor'] );
		$this->assertSame( 'rgba(255, 0, 0, 0.5)', $clean['backgroundColor'] );
		$this->assertSame( '', $clean['buttonColor'] );
		$this->assertSame( '', $clean['cards'][0]['backgroundColor'] );
		$this->assertSame( '#ABC', $clean['cards'][0]['gradientStart'] );
	}

	public function test_dimensions_and_responsive_values_are_clamped() {
		$clean = $this->sanitize(
			array(
				'padding'       => 9999,
				'paddingX'      => -50,
				'gap'           => 500,
				'marginY'       => 101,
				'cardMinHeight' => 10,
				'imageHeight'   => 10000,
				'height'        => 5000,
				'responsive'    => array(
					'mobile'  => array(
						'padding'  => 9999,
						'paddingX' => 10,
						'unknown'  => 'discard-me',
					),
					'desktop' => 'malformed',
				),
			)
		);

		$this->assertSame( 160, $clean['padding'] );
		$this->assertSame( 50, $clean['paddingX'] );
		$this->assertSame( 64, $clean['gap'] );
		$this->assertSame( 100, $clean['marginY'] );
		$this->assertSame( 200, $clean['cardMinHeight'] );
		$this->assertSame( 420, $clean['imageHeight'] );
		$this->assertSame( 1000, $clean['height'] );
		$this->assertSame( 160, $clean['responsive']['mobile']['padding'] );
		$this->assertSame( 10, $clean['responsive']['mobile']['paddingX'] );
		$this->assertArrayNotHasKey( 'unknown', $clean['responsive']['mobile'] );
		$this->assertSame( array(), $clean['responsive']['desktop'] );
	}

	public function test_non_array_settings_produce_safe_defaults() {
		$clean = $this->sanitize( 'garbage' );

		$this->assertSame( array(), $clean['cards'] );
		$this->assertSame( 'centered', $clean['headerLayout'] );
		$this->assertSame( 60, $clean['padding'] );
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_card_columns_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}
}
