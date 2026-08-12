<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-context.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-seo.php';

/** Routing double returning fixed translation links. */
class DSF_Language_SEO_Test_Routing {
	public $links      = array();
	public $is_route   = false;
	public $route      = null;
	public $link_calls = array();

	public function get_translation_links( $kind, $subtype, $id ) {
		$this->link_calls[] = array( $kind, $subtype, $id );
		return $this->links;
	}

	public function is_route_request() {
		return $this->is_route;
	}

	public function get_resolved_route() {
		return $this->route;
	}
}

/**
 * Covers reciprocal `hreflang`, `x-default`, and the conditional canonical.
 */
class Test_DSF_Language_SEO extends TestCase {

	/** @var DSF_Language_SEO_Test_Routing */
	private $routing;

	/** @var DSF_Language_Context */
	private $context;

	/** @var DSF_Language_SEO */
	private $seo;

	/** @var bool */
	private $is_singular = false;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_rtl', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_feed', array( 'return' => false ) );
		$singular = &$this->is_singular;
		WP_Mock::userFunction(
			'is_singular',
			array(
				'return' => static function () use ( &$singular ) {
					return $singular;
				},
			)
		);
		WP_Mock::userFunction( 'is_front_page', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_home', array( 'return' => false ) );
		WP_Mock::userFunction( 'apply_filters', array( 'return_arg' => 1 ) );
		WP_Mock::userFunction( 'esc_attr', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
			)
		);
		WP_Mock::userFunction(
			'home_url',
			array(
				'return' => static function ( $path = '' ) {
					return 'https://example.test' . ( '' === $path ? '/' : $path );
				},
			)
		);
		WP_Mock::userFunction( 'user_trailingslashit', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'trailingslashit',
			array(
				'return' => static function ( $value ) {
					return rtrim( (string) $value, '/' ) . '/';
				},
			)
		);

		$settings = DSF_Multilingual_Settings::sanitize_settings(
			array(
				'enabled'           => true,
				'main_language'     => 'en-US',
				'migration_state'   => 'complete',
				'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
				'languages'         => array(
					array( 'code' => 'en-US' ),
					array( 'code' => 'es-MX', 'prefix' => 'es' ),
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
		$this->routing = new DSF_Language_SEO_Test_Routing();
		$this->seo     = new DSF_Language_SEO(
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

	/**
	 * Seed the queried object and its published siblings.
	 *
	 * @param array $links Sibling links.
	 */
	private function seed_page( $links ) {
		WP_Mock::userFunction(
			'get_queried_object',
			array(
				'return' => (object) array(
					'ID'        => 20,
					'post_type' => 'page',
				),
			)
		);
		$this->routing->links = $links;
	}

	public function test_reciprocal_alternates_include_self_and_x_default() {
		$this->seed_page(
			array(
				'en-US' => array(
					'html_lang' => 'en-US',
					'url'       => 'https://example.test/about/',
				),
				'es-MX' => array(
					'html_lang' => 'es-MX',
					'url'       => 'https://example.test/es/acerca-de/',
				),
			)
		);

		ob_start();
		$this->seo->output_alternate_links();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<link rel="alternate" hreflang="en-US" href="https://example.test/about/" />', $output );
		$this->assertStringContainsString( '<link rel="alternate" hreflang="es-MX" href="https://example.test/es/acerca-de/" />', $output );
		$this->assertStringContainsString( '<link rel="alternate" hreflang="x-default" href="https://example.test/about/" />', $output );
		$this->assertSame( 3, substr_count( $output, 'rel="alternate"' ) );
	}

	public function test_a_lone_language_version_prints_no_alternates() {
		$this->seed_page(
			array(
				'en-US' => array(
					'html_lang' => 'en-US',
					'url'       => 'https://example.test/about/',
				),
			)
		);

		ob_start();
		$this->seo->output_alternate_links();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_x_default_is_omitted_when_the_main_language_is_unpublished() {
		$this->seed_page(
			array(
				'es-MX' => array(
					'html_lang' => 'es-MX',
					'url'       => 'https://example.test/es/acerca-de/',
				),
				'fr-FR' => array(
					'html_lang' => 'fr-FR',
					'url'       => 'https://example.test/fr/a-propos/',
				),
			)
		);

		ob_start();
		$this->seo->output_alternate_links();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'x-default', $output );
		$this->assertSame( 2, substr_count( $output, 'rel="alternate"' ) );
	}

	public function test_disabled_feature_prints_nothing() {
		$settings = DSF_Multilingual_Settings::sanitize_settings( array( 'enabled' => false ) );
		$seo      = new DSF_Language_SEO(
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

		ob_start();
		$seo->output_alternate_links();
		$seo->output_language_canonical();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_alternates_are_resolved_for_the_queried_term() {
		WP_Mock::userFunction(
			'get_queried_object',
			array(
				'return' => (object) array(
					'term_id'  => 7,
					'taxonomy' => 'category',
				),
			)
		);
		$this->routing->links = array();

		$this->seo->current_alternates();

		$this->assertSame( array( array( 'term', 'category', 7 ) ), $this->routing->link_calls );
	}

	public function test_self_canonical_only_fills_the_gap_core_leaves() {
		WP_Mock::userFunction( 'get_queried_object', array( 'return' => null ) );
		$this->routing->is_route = true;
		$this->routing->route    = array(
			'language' => 'es-MX',
			'path'     => 'categoria/noticias',
		);

		ob_start();
		$this->seo->output_language_canonical();
		$this->assertStringContainsString( '<link rel="canonical" href="https://example.test/es/categoria/noticias" />', ob_get_clean() );

		// Core already prints rel_canonical for singular requests.
		$this->is_singular = true;
		ob_start();
		$this->seo->output_language_canonical();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_no_canonical_is_printed_outside_language_routes() {
		WP_Mock::userFunction( 'get_queried_object', array( 'return' => null ) );
		$this->routing->is_route = false;

		ob_start();
		$this->seo->output_language_canonical();

		$this->assertSame( '', ob_get_clean() );
	}
}
