<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Server-side sanitization for the Modern Mega header block: counts are capped,
 * the column layout and per-link icon fall back to known values, numeric ranges
 * are clamped, toggles become booleans, and protocol-relative URLs are dropped.
 */
class Test_DSF_Modern_Mega_Header extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static fn( $v ) => abs( (int) $v ) ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static fn( $v ) => ( is_string( $v ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ) ? $v : null )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_modern_mega_header_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings );
	}

	public function test_scalars_are_clamped_and_typed() {
		$clean = $this->sanitize(
			array(
				'logoImageSize' => 999,
				'cartCount'     => 999,
				'sticky'        => 1,
				'showSearch'    => 0,
				'showAccount'   => true,
				'navBackground' => '#ffffff',
				'accentColor'   => 'not-a-color',
			)
		);

		$this->assertSame( 100, $clean['logoImageSize'], 'logo size clamps to 100' );
		$this->assertSame( 99, $clean['cartCount'], 'cart count clamps to 99' );
		$this->assertTrue( $clean['sticky'] );
		$this->assertFalse( $clean['showSearch'] );
		$this->assertTrue( $clean['showAccount'] );
		$this->assertSame( '#ffffff', $clean['navBackground'] );
		$this->assertSame( '', $clean['accentColor'], 'a bad hex color becomes empty' );
	}

	public function test_protocol_relative_url_is_dropped_but_anchor_kept() {
		$clean = $this->sanitize(
			array(
				'homeUrl'   => '//evil.example.com',
				'searchUrl' => '#',
			)
		);
		$this->assertSame( '', $clean['homeUrl'] );
		$this->assertSame( '#', $clean['searchUrl'] );
	}

	public function test_menu_item_column_and_link_counts_are_capped() {
		$items = array();
		for ( $i = 0; $i < 15; $i++ ) {
			$columns = array();
			for ( $c = 0; $c < 8; $c++ ) {
				$links = array();
				for ( $l = 0; $l < 15; $l++ ) {
					$links[] = array( 'label' => "L$l", 'url' => '#' );
				}
				$columns[] = array( 'heading' => "C$c", 'layout' => 'links', 'links' => $links );
			}
			$items[] = array( 'label' => "Item $i", 'url' => '#', 'hasMega' => true, 'columns' => $columns );
		}

		$clean = $this->sanitize( array( 'menuItems' => $items ) );

		$this->assertCount( 12, $clean['menuItems'], 'no more than 12 menu items' );
		$this->assertCount( 6, $clean['menuItems'][0]['columns'], 'no more than 6 columns' );
		$this->assertCount( 12, $clean['menuItems'][0]['columns'][0]['links'], 'no more than 12 links' );
	}

	public function test_layout_and_icon_fall_back_to_known_values() {
		$clean = $this->sanitize(
			array(
				'menuItems' => array(
					array(
						'label'   => 'X',
						'hasMega' => true,
						'columns' => array(
							array(
								'layout'       => 'wild',
								'imageColumns' => 9,
								'links'        => array( array( 'label' => 'A', 'icon' => 'not-real' ) ),
							),
							array(
								'layout' => 'cards',
								'links'  => array( array( 'label' => 'B' ) ),
							),
						),
					),
				),
			)
		);

		$col0 = $clean['menuItems'][0]['columns'][0];
		$col1 = $clean['menuItems'][0]['columns'][1];
		$this->assertSame( 'links', $col0['layout'], 'unknown layout falls back to links' );
		$this->assertFalse( $col0['imageLinks'], 'links layout is not image links' );
		$this->assertSame( 4, $col0['imageColumns'], 'imageColumns clamps to 4' );
		$this->assertSame( 'sparkles', $col0['links'][0]['icon'], 'unknown icon falls back to sparkles' );
		$this->assertSame( 'cards', $col1['layout'] );
		$this->assertTrue( $col1['imageLinks'], 'cards layout derives imageLinks=true' );
	}

	public function test_featured_card_fields_are_preserved() {
		$clean = $this->sanitize(
			array(
				'menuItems' => array(
					array(
						'label'   => 'X',
						'hasMega' => true,
						'columns' => array(),
						'banner'  => array(
							'title'       => 'Deal',
							'text'        => 'Save big',
							'buttonLabel' => 'Shop',
							'url'         => '/deal',
						),
					),
				),
			)
		);

		$banner = $clean['menuItems'][0]['banner'];
		$this->assertSame( 'Deal', $banner['title'] );
		$this->assertSame( 'Save big', $banner['text'] );
		$this->assertSame( 'Shop', $banner['buttonLabel'] );
		$this->assertTrue( $clean['menuItems'][0]['hasMega'] );
	}
}
