<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-frontend.php';

/**
 * The Typography settings now carry per-breakpoint sizes (desktop / laptop /
 * mobile). These tests pin down that laptop/mobile inherit desktop where blank,
 * that the emitted media-query CSS only contains the values that actually differ
 * from desktop, and that it is gated to override mode.
 */
class Test_DSF_Responsive_Typography extends TestCase {
	private $option = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->option = array();
		WP_Mock::userFunction( 'get_option', array( 'return' => function ( $key, $default = false ) {
			if ( 'dsf_typography' === $key ) {
				return $this->option;
			}
			return $default;
		} ) );
		// Frontend typography helpers touch these; keep them neutral.
		WP_Mock::userFunction( 'wp_get_global_styles', array( 'return' => array() ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_laptop_and_mobile_inherit_desktop_when_blank() {
		$this->option = array(
			'mode'    => 'override',
			'size_h1' => 48,
		);

		$maps = DSF_Frontend::get_responsive_typography_tokens();

		// Nothing set for laptop/mobile → they equal desktop for h1.
		$this->assertSame( $maps['desktop']['--dsf-theme-h1'], $maps['laptop']['--dsf-theme-h1'] );
		$this->assertSame( $maps['desktop']['--dsf-theme-h1'], $maps['mobile']['--dsf-theme-h1'] );
		$this->assertSame( '48px', $maps['desktop']['--dsf-theme-h1'] );
	}

	public function test_mobile_override_changes_only_mobile() {
		$this->option = array(
			'mode'           => 'override',
			'size_h1'        => 48,
			'size_h1_mobile' => 30,
		);

		$maps = DSF_Frontend::get_responsive_typography_tokens();

		$this->assertSame( '48px', $maps['desktop']['--dsf-theme-h1'] );
		$this->assertSame( '48px', $maps['laptop']['--dsf-theme-h1'] );
		$this->assertSame( '30px', $maps['mobile']['--dsf-theme-h1'] );
	}

	public function test_media_css_only_emits_differences_and_is_scoped() {
		$this->option = array(
			'mode'           => 'override',
			'size_h1'        => 48,
			'size_h1_mobile' => 30,
			'size_h2_laptop' => 33,
		);

		$css = DSF_Frontend::build_responsive_typography_css();

		$this->assertStringContainsString( '@media (max-width: 767px)', $css );
		$this->assertStringContainsString( '@media (max-width: 1024px)', $css );
		$this->assertStringContainsString( '.dsf-page-content{', $css );
		$this->assertStringContainsString( '--dsf-theme-h1:30px !important', $css );
		$this->assertStringContainsString( '--dsf-theme-h2:33px !important', $css );
		// h1 is unchanged on laptop, so it must not appear in the laptop block
		// (the slice between the laptop query and the following mobile query).
		$laptop_start = strpos( $css, '1024px' );
		$mobile_start = strpos( $css, '767px' );
		$laptop_block = substr( $css, $laptop_start, $mobile_start - $laptop_start );
		$this->assertStringNotContainsString( '--dsf-theme-h1:', $laptop_block );
	}

	public function test_media_css_empty_in_theme_mode() {
		$this->option = array(
			'mode'           => 'theme',
			'size_h1_mobile' => 30,
		);
		$this->assertSame( '', DSF_Frontend::build_responsive_typography_css() );
	}

	public function test_media_css_empty_when_no_responsive_overrides() {
		$this->option = array(
			'mode'    => 'override',
			'size_h1' => 48,
		);
		$this->assertSame( '', DSF_Frontend::build_responsive_typography_css() );
	}
}
