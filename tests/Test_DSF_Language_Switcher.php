<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-context.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-switcher.php';

/** Routing double returning fixed sibling links. */
class DSF_Switcher_Test_Routing {
	public $links = array();

	public function get_translation_links( $kind, $subtype, $id ) {
		unset( $kind, $subtype, $id );
		return $this->links;
	}
}

/**
 * Covers the shared switcher: which languages are offered at all, and how the
 * markup is rendered and escaped.
 */
class Test_DSF_Language_Switcher extends TestCase {

	/** @var DSF_Switcher_Test_Routing */
	private $routing;

	/** @var DSF_Language_Switcher */
	private $switcher;

	/** @var DSF_Language_Context */
	private $context;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_rtl', array( 'return' => false ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'sanitize_html_class', array( 'return' => static function ( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); } ) );
		WP_Mock::userFunction( 'esc_attr', array( 'return' => static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } ) );
		WP_Mock::userFunction( 'esc_html', array( 'return' => static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } ) );
		WP_Mock::userFunction( 'esc_url', array( 'return' => static function ( $v ) { return str_replace( array( '"', '<', '>' ), '', (string) $v ); } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $v ) {
			$v = (string) $v;
			return preg_match( '#^https?://#', $v ) ? $v : '';
		} ) );
		WP_Mock::userFunction( 'home_url', array( 'return' => static function ( $path = '' ) { return 'https://example.test' . ( '' === $path ? '/' : $path ); } ) );
		WP_Mock::userFunction( 'user_trailingslashit', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'trailingslashit', array( 'return' => static function ( $v ) { return rtrim( (string) $v, '/' ) . '/'; } ) );
		WP_Mock::userFunction( 'get_queried_object', array( 'return' => (object) array( 'ID' => 20, 'post_type' => 'page' ) ) );
		WP_Mock::userFunction( 'shortcode_atts', array( 'return' => static function ( $defaults, $atts ) {
			return array_merge( $defaults, is_array( $atts ) ? array_intersect_key( $atts, $defaults ) : array() );
		} ) );

		$settings = DSF_Multilingual_Settings::sanitize_settings(
			array(
				'enabled'           => true,
				'main_language'     => 'en-US',
				'migration_state'   => 'complete',
				'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
				'languages'         => array(
					array( 'code' => 'en-US' ),
					array( 'code' => 'es-MX', 'prefix' => 'es' ),
					array( 'code' => 'ar', 'prefix' => 'ar' ),
				),
			)
		);

		$this->context = new DSF_Language_Context(
			array(
				'settings_reader'   => static function () use ( $settings ) {
					return $settings;
				},
				'conflict_detector' => static function () {
					return false;
				},
			)
		);
		$this->routing = new DSF_Switcher_Test_Routing();
		$this->routing->links = array(
			'en-US' => array( 'url' => 'https://example.test/about/' ),
			'es-MX' => array( 'url' => 'https://example.test/es/acerca-de/' ),
		);

		$this->switcher = new DSF_Language_Switcher(
			array(
				'context' => $this->context,
				'routing' => $this->routing,
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_only_languages_with_a_published_sibling_are_offered() {
		$items = $this->switcher->get_items();

		$this->assertSame( array( 'en-US', 'es-MX' ), array_column( $items, 'code' ) );
		$this->assertSame(
			'https://example.test/es/acerca-de/',
			$items[1]['url'],
			'Targets are the real permalinks the server resolved.'
		);
		$this->assertTrue( $items[0]['current'] );
		$this->assertFalse( $items[1]['current'] );
	}

	public function test_a_single_available_language_renders_nothing() {
		$this->routing->links = array( 'en-US' => array( 'url' => 'https://example.test/about/' ) );

		$this->assertSame( array(), $this->switcher->get_items() );
		$this->assertSame( '', $this->switcher->render() );
	}

	public function test_the_inactive_feature_renders_nothing() {
		$settings = DSF_Multilingual_Settings::sanitize_settings( array( 'enabled' => false ) );
		$switcher = new DSF_Language_Switcher(
			array(
				'context' => new DSF_Language_Context(
					array(
						'settings_reader'   => static function () use ( $settings ) {
							return $settings;
						},
						'conflict_detector' => static function () {
							return false;
						},
					)
				),
				'routing' => $this->routing,
			)
		);

		$this->assertSame( '', $switcher->render() );
	}

	public function test_the_current_language_is_announced_and_never_links_to_itself() {
		$html = $this->switcher->render();

		$this->assertStringContainsString( '<nav class="dsf-language-switcher dsf-language-switcher--dropdown" aria-label="Language">', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
		$this->assertStringContainsString( '<span class="dsf-language-switcher__current" aria-current="true" lang="en-US">English (United States)</span>', $html );
		$this->assertSame( 1, substr_count( $html, '<a class="dsf-language-switcher__link"' ) );
		$this->assertStringContainsString( 'hreflang="es-MX"', $html );
		$this->assertStringContainsString( 'dir="ltr"', $html );
	}

	public function test_right_to_left_targets_declare_their_direction() {
		$this->routing->links['ar'] = array( 'url' => 'https://example.test/ar/about/' );

		$html = $this->switcher->render( array( 'style' => 'list' ) );

		$this->assertStringContainsString( 'lang="ar" hreflang="ar" dir="rtl"', $html );
	}

	public function test_label_styles_are_bounded_and_never_empty() {
		$this->assertStringContainsString( '>Español (México)<', $this->switcher->render() );
		$this->assertStringContainsString( '>ES<', $this->switcher->render( array( 'style' => 'compact' ) ) );
		$this->assertStringContainsString(
			'>Español (México) (ES)<',
			$this->switcher->render( array( 'show_names' => 'true', 'show_codes' => 'true' ) )
		);
		$this->assertStringContainsString(
			'>Español (México)<',
			$this->switcher->render( array( 'show_names' => 'false', 'show_codes' => 'false' ) ),
			'A switcher with no readable label would be unusable.'
		);
	}

	public function test_shortcode_attributes_are_constrained() {
		$html = $this->switcher->render_shortcode(
			array(
				'style' => 'javascript:alert(1)',
				'class' => 'safe-class "onmouseover=alert(1)',
				'evil'  => '<script>',
			)
		);

		$this->assertStringContainsString( 'dsf-language-switcher--dropdown', $html, 'An unknown style falls back to the default.' );
		$this->assertStringContainsString( 'safe-class', $html );
		// The injected token survives only as an inert class name: no quote,
		// equals sign, or angle bracket escapes the attribute.
		$this->assertStringNotContainsString( 'onmouseover=', $html );
		$this->assertStringNotContainsString( '"onmouseover', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_filtered_items_cannot_introduce_an_unsafe_target() {
		$resolved = array(
			array(
				'code'      => 'en-US',
				'label'     => 'English (United States)',
				'html_lang' => 'en-US',
				'direction' => 'ltr',
				'short'     => 'EN',
				'url'       => 'https://example.test/about/',
				'current'   => true,
			),
			array(
				'code'      => 'es-MX',
				'label'     => 'Español (México)',
				'html_lang' => 'es-MX',
				'direction' => 'ltr',
				'short'     => 'ES',
				'url'       => 'https://example.test/es/acerca-de/',
				'current'   => false,
			),
		);
		WP_Mock::onFilter( 'dsf_language_switcher_items' )
			->with( $resolved )
			->reply(
				array(
					array( 'code' => 'es-MX', 'url' => 'https://example.test/es/acerca-de/' ),
					array( 'code' => 'es-MX', 'url' => 'javascript:alert(1)' ),
					array( 'code' => 'de-DE', 'url' => 'https://example.test/de/' ),
					array( 'code' => '<script>', 'url' => 'https://example.test/x/' ),
					'not-an-item',
				)
			);

		$items = $this->switcher->get_items();

		$this->assertSame( array( 'es-MX' ), array_column( $items, 'code' ) );
		$this->assertStringNotContainsString( 'javascript:', wp_json_encode_switcher( $items ) );
	}

	public function test_header_presentation_settings_are_constrained_on_save() {
		require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_language_switcher_settings' );
		$method->setAccessible( true );

		$clean = $method->invokeArgs(
			$ajax,
			array(
				array(
					'languageSwitcherStyle'     => 'LIST',
					'languageSwitcherLabels'    => '<script>',
					'languageSwitcherPlacement' => 'utility',
					'logoText'                  => 'Brand',
				),
			)
		);

		$this->assertSame( 'list', $clean['languageSwitcherStyle'] );
		$this->assertSame( 'utility', $clean['languageSwitcherPlacement'] );
		$this->assertArrayNotHasKey( 'languageSwitcherLabels', $clean, 'An unknown value is dropped, not stored.' );
		$this->assertSame( 'Brand', $clean['logoText'], 'Unrelated settings are untouched.' );
	}

	public function test_every_template_scope_header_offers_the_switcher() {
		require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

		$headers  = DSF_Blocks::language_switcher_headers();
		$controls = DSF_Blocks::language_switcher_settings();

		$this->assertSame(
			array( 'header-mega-menu', 'header-showcase-mega', 'header-cutout-mega', 'header-modern-mega' ),
			$headers
		);
		$this->assertSame(
			array( 'languageSwitcherStyle', 'languageSwitcherLabels', 'languageSwitcherPlacement' ),
			array_keys( $controls ),
			'Presentation is configurable; there is deliberately no control that removes the switcher.'
		);
		foreach ( $controls as $control ) {
			$this->assertSame( 'select', $control['type'] );
			$this->assertFalse( $control['translatable'] );
		}
	}

	public function test_argument_normalization_rebuilds_from_known_values() {
		$args = DSF_Language_Switcher::normalize_args(
			array(
				'style'      => 'LIST',
				'show_names' => 'no',
				'show_codes' => 'yes',
				'class'      => 'a b c d e f g',
				'unknown'    => 'dropped',
			)
		);

		$this->assertSame( array( 'style', 'show_names', 'show_codes', 'class' ), array_keys( $args ) );
		$this->assertSame( 'list', $args['style'] );
		$this->assertFalse( $args['show_names'] );
		$this->assertTrue( $args['show_codes'] );
		$this->assertSame( 'a b c d e', $args['class'], 'The class list is bounded.' );
	}
}

/**
 * Minimal JSON encoder so assertions do not need a WordPress runtime.
 *
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_switcher( $value ) {
	return (string) wp_json_encode_native( $value );
}

/**
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_native( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test helper outside WordPress.
}
