<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';

/**
 * Covers the strict multilingual settings and curated locale contracts.
 */
class Test_DSF_Multilingual_Settings extends TestCase {

	public function test_registry_supports_broad_and_regional_locales() {
		$this->assertSame( 'en-US', DSF_Multilingual_Settings::normalize_locale_code( 'en_US' ) );
		$this->assertSame( 'es-MX', DSF_Multilingual_Settings::normalize_locale_code( 'ES-mx' ) );
		$this->assertSame( '', DSF_Multilingual_Settings::normalize_locale_code( 'xx-<script>' ) );

		$spanish = DSF_Multilingual_Settings::get_locale( 'es_MX' );
		$this->assertSame( 'Español (México)', $spanish['native_label'] );
		$this->assertSame( 'es-MX', $spanish['html_lang'] );
		$this->assertSame( 'es_MX', $spanish['wp_locale'] );
		$this->assertSame( 'es', $spanish['provider_code'] );
		$this->assertSame( 'rtl', DSF_Multilingual_Settings::get_locale( 'ar' )['direction'] );
		$this->assertNull( DSF_Multilingual_Settings::get_locale( 'not-a-locale' ) );
	}

	public function test_settings_are_reconstructed_from_known_keys() {
		$clean = DSF_Multilingual_Settings::sanitize_settings(
			array(
				'enabled'         => 'yes',
				'feature_state'   => 'enabled',
				'migration_state' => 'complete',
				'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
				'main_language'   => 'en_US',
				'languages'       => array(
					array(
						'code'         => 'es_MX',
						'prefix'       => '/ES_MX/',
						'native_label' => '<script>Injected</script>',
						'direction'    => 'rtl',
					),
					array(
						'code'   => 'en-US',
						'prefix' => 'english',
					),
					array(
						'code'   => 'fr-FR',
						'prefix' => 'FR',
					),
				),
				'url_policy'     => 'query_parameter',
				'unknown_secret' => 'do not retain me',
			)
		);

		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( 'enabled', $clean['feature_state'] );
		$this->assertSame( 'en-US', $clean['main_language'] );
		$this->assertSame( array( 'code' => 'en-US', 'prefix' => '' ), $clean['languages'][0] );
		$this->assertContains( array( 'code' => 'es-MX', 'prefix' => 'es-mx' ), $clean['languages'] );
		$this->assertContains( array( 'code' => 'fr-FR', 'prefix' => 'fr' ), $clean['languages'] );
		$this->assertSame( array( 'code', 'prefix' ), array_keys( $clean['languages'][1] ) );
		$this->assertSame( 'main_unprefixed', $clean['url_policy'] );
		$this->assertArrayNotHasKey( 'unknown_secret', $clean );
	}

	public function test_duplicate_and_reserved_secondary_prefixes_are_rejected() {
		$clean = DSF_Multilingual_Settings::sanitize_settings(
			array(
				'enabled'        => true,
				'main_language'  => 'en-US',
				'languages'      => array(
					array( 'code' => 'en-US', 'prefix' => '' ),
					array( 'code' => 'es-MX', 'prefix' => 'es' ),
					array( 'code' => 'fr-FR', 'prefix' => 'es' ),
					array( 'code' => 'de-DE', 'prefix' => 'wp-json' ),
					array( 'code' => 'xx-ZZ', 'prefix' => 'xx' ),
					array( 'code' => 'es-MX', 'prefix' => 'spanish' ),
				),
				'migration_state'   => 'complete',
				'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
			)
		);

		$this->assertSame(
			array(
				array( 'code' => 'en-US', 'prefix' => '' ),
				array( 'code' => 'es-MX', 'prefix' => 'es' ),
			),
			$clean['languages']
		);
		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( '', DSF_Multilingual_Settings::sanitize_prefix( '../es' ) );
		$this->assertSame( '', DSF_Multilingual_Settings::sanitize_prefix( 'feed' ) );
		$this->assertSame( '', DSF_Multilingual_Settings::sanitize_prefix( 'WP_ADMIN' ) );
		$this->assertSame( 'es-mx', DSF_Multilingual_Settings::sanitize_prefix( '/ES_MX/' ) );
	}

	public function test_invalid_configuration_cannot_enable_multilingual_mode() {
		$clean = DSF_Multilingual_Settings::sanitize_settings(
			array(
				'enabled'         => true,
				'feature_state'   => 'enabled',
				'migration_state' => 'failed',
				'main_language'   => 'javascript:alert(1)',
				'languages'       => '<script>',
			),
			'fr_CA'
		);

		$this->assertFalse( $clean['enabled'] );
		$this->assertSame( 'disabled', $clean['feature_state'] );
		$this->assertSame( 'failed', $clean['migration_state'] );
		$this->assertSame( 'fr-CA', $clean['main_language'] );
		$this->assertSame( array( array( 'code' => 'fr-CA', 'prefix' => '' ) ), $clean['languages'] );
	}

	public function test_feature_state_is_derived_from_migration_state() {
		$base = array(
			'enabled'        => true,
			'main_language'  => 'en-US',
			'languages'      => array(
				array( 'code' => 'en-US' ),
				array( 'code' => 'es-MX' ),
			),
			'migration_cursor' => PHP_INT_MAX,
		);

		$pending = DSF_Multilingual_Settings::sanitize_settings( $base );
		$this->assertSame( 'pending', $pending['migration_state'] );
		$this->assertSame( 'enabling', $pending['feature_state'] );
		$this->assertSame( 2147483647, $pending['migration_cursor'] );

		$complete = DSF_Multilingual_Settings::sanitize_settings(
			array_merge(
				$base,
				array(
					'migration_state'   => 'complete',
					'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
				)
			)
		);
		$this->assertSame( 'enabled', $complete['feature_state'] );

		$disabled = DSF_Multilingual_Settings::sanitize_settings(
			array_merge(
				$base,
				array(
					'enabled'            => false,
					'migration_state'    => 'complete',
					'migration_version'  => DSF_Multilingual_Settings::MIGRATION_VERSION,
				)
			)
		);
		$this->assertSame( 'disabled', $disabled['feature_state'] );
		$this->assertSame( 'complete', $disabled['migration_state'] );
	}

	public function test_recommended_policy_defaults_are_explicit() {
		$defaults = DSF_Multilingual_Settings::get_defaults( 'en_US' );

		$this->assertSame( 'not_found', $defaults['missing_translation_policy'] );
		$this->assertSame( 'copy_unconfirmed', $defaults['clone_identity_policy'] );
		$this->assertSame( 'keep_minor', $defaults['source_change_policy'] );
		$this->assertSame( 'hide_until_reviewed', $defaults['critical_change_policy'] );
		$this->assertSame( 'stable', $defaults['archive_base_policy'] );
		$this->assertSame( 'template_headers', $defaults['header_switcher_scope'] );
		$this->assertSame( 'canonical_overlay', $defaults['commerce_storage_policy'] );
		$this->assertSame( 'wp_terms_woo_overlays', $defaults['taxonomy_storage_policy'] );
		$this->assertSame( 'routable_https', $defaults['translator_network_policy'] );
	}
}
