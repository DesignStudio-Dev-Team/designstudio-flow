<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-context.php';

/**
 * Covers the single trusted request-language resolver.
 */
class Test_DSF_Language_Context extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_rtl', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_attr', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'sanitize_html_class',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Build a context bound to fixed settings and no plugin conflicts.
	 *
	 * @param array $overrides Settings overrides.
	 * @param bool  $conflicts Whether a conflicting plugin is active.
	 * @return DSF_Language_Context
	 */
	private function context( $overrides = array(), $conflicts = false ) {
		$settings = DSF_Multilingual_Settings::sanitize_settings(
			array_merge(
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
				),
				$overrides
			)
		);

		return new DSF_Language_Context(
			array(
				'settings_reader'   => static function () use ( $settings ) {
					return $settings;
				},
				'conflict_detector' => static function () use ( $conflicts ) {
					return $conflicts;
				},
			)
		);
	}

	public function test_feature_stays_inactive_until_migration_and_conflicts_are_resolved() {
		$this->assertTrue( $this->context()->is_active() );
		$this->assertFalse( $this->context( array(), true )->is_active() );
		$this->assertFalse( $this->context( array( 'migration_state' => 'running' ) )->is_active() );
		$this->assertFalse( $this->context( array( 'enabled' => false ) )->is_active() );
	}

	public function test_request_language_defaults_to_the_main_language() {
		$context = $this->context();

		$this->assertSame( 'en-US', $context->get_request_language() );
		$this->assertFalse( $context->is_secondary_request() );
	}

	public function test_only_curated_enabled_languages_are_accepted() {
		$context = $this->context();

		$this->assertFalse( $context->set_request_language( 'de-DE' ) );
		$this->assertFalse( $context->set_request_language( '../es' ) );
		$this->assertFalse( $context->set_request_language( array( 'es-MX' ) ) );
		$this->assertSame( 'en-US', $context->get_request_language() );

		$this->assertTrue( $context->set_request_language( 'es_MX' ) );
		$this->assertSame( 'es-MX', $context->get_request_language() );
		$this->assertTrue( $context->is_secondary_request() );
	}

	public function test_prefixes_resolve_only_for_configured_secondary_languages() {
		$context = $this->context();

		$this->assertSame( 'es-MX', $context->language_for_prefix( 'es' ) );
		$this->assertSame( 'es-MX', $context->language_for_prefix( '/ES/' ) );
		$this->assertSame( '', $context->language_for_prefix( 'fr' ) );
		$this->assertSame( '', $context->language_for_prefix( 'wp-json' ) );
		$this->assertSame( '', $context->language_for_prefix( '' ) );
		$this->assertSame( '', $context->get_prefix( 'en-US' ) );
		$this->assertSame( 'es', $context->get_prefix( 'es-MX' ) );
	}

	public function test_document_attributes_follow_the_resolved_language() {
		$context = $this->context();

		$this->assertSame( 'dir="ltr" lang="en-US"', $context->filter_language_attributes( 'lang="en-US"' ) );

		$context->set_request_language( 'ar' );
		$this->assertSame( 'dir="rtl" lang="ar"', $context->filter_language_attributes( 'lang="en-US"' ) );
		$this->assertSame(
			'dir="rtl" lang="ar" xml:lang="ar"',
			$context->filter_language_attributes( 'lang="en-US"', 'xhtml' )
		);
	}

	public function test_inactive_feature_leaves_document_attributes_untouched() {
		$context = $this->context( array( 'enabled' => false ) );

		$this->assertSame( 'lang="en-US"', $context->filter_language_attributes( 'lang="en-US"' ) );
		$this->assertSame( 'en_US', $context->filter_determine_locale( 'en_US' ) );
	}

	public function test_locale_switches_to_the_resolved_language_on_the_frontend() {
		$context = $this->context();
		$context->set_request_language( 'es-MX' );

		$this->assertSame( 'es_MX', $context->filter_determine_locale( 'en_US' ) );
	}

	public function test_body_classes_expose_language_and_direction() {
		$context = $this->context();
		$context->set_request_language( 'ar' );

		$classes = $context->filter_body_class( array( 'existing' ) );

		$this->assertSame( array( 'existing', 'dsf-lang-ar', 'dsf-dir-rtl' ), $classes );
	}

	public function test_localized_payload_carries_only_curated_language_data() {
		$context = $this->context();
		$context->set_request_language( 'es-MX' );

		$payload = $context->get_localized_payload();

		$this->assertTrue( $payload['active'] );
		$this->assertSame( 'es-MX', $payload['current'] );
		$this->assertSame( 'en-US', $payload['main'] );
		$this->assertSame( 'ltr', $payload['dir'] );
		$this->assertSame( array( 'code', 'label', 'prefix', 'dir' ), array_keys( $payload['list'][0] ) );
		$this->assertSame( 'Español (México)', $payload['list'][1]['label'] );
		$this->assertSame( 'es', $payload['list'][1]['prefix'] );
	}

	public function test_language_home_urls_use_the_configured_prefix() {
		WP_Mock::userFunction(
			'home_url',
			array(
				'return' => static function ( $path = '' ) {
					return 'https://example.test' . $path;
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

		$context = $this->context();

		$this->assertSame( 'https://example.test/', $context->home_url( 'en-US' ) );
		$this->assertSame( 'https://example.test/es/', $context->home_url( 'es-MX' ) );
		$this->assertSame( 'https://example.test/es/acerca-de', $context->home_url( 'es-MX', 'acerca-de' ) );
	}

	public function test_describe_returns_nothing_for_uncurated_codes() {
		$this->assertSame( array(), DSF_Language_Context::describe( 'xx-ZZ' ) );
		$this->assertSame( array(), DSF_Language_Context::describe( '<script>' ) );
		$this->assertSame( 'rtl', DSF_Language_Context::describe( 'he' )['direction'] );
	}
}
