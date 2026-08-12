<?php
/**
 * Curated multilingual settings and locale registry.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the stored multilingual configuration contract.
 *
 * Locale labels, HTML language values, WordPress locales, direction, and
 * provider codes are deliberately derived from this server-owned registry.
 * They are never accepted from a browser or imported settings payload.
 */
class DSF_Multilingual_Settings {

	const OPTION_NAME       = 'dsf_multilingual_settings';
	const SCHEMA_VERSION    = 1;
	const MIGRATION_VERSION = 1;
	const MAX_LANGUAGES     = 20;
	const MAX_PREFIX_LENGTH = 32;

	/**
	 * Get the curated locale registry.
	 *
	 * Registry identifiers use canonical BCP 47 casing. The WordPress locale is
	 * explicit because it cannot be safely inferred for every regional locale.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_locale_registry() {
		return array(
			'en'    => self::locale( 'English', 'en', 'en_US', 'ltr', 'en' ),
			'en-US' => self::locale( 'English (United States)', 'en-US', 'en_US', 'ltr', 'en' ),
			'en-GB' => self::locale( 'English (United Kingdom)', 'en-GB', 'en_GB', 'ltr', 'en' ),
			'en-CA' => self::locale( 'English (Canada)', 'en-CA', 'en_CA', 'ltr', 'en' ),
			'en-AU' => self::locale( 'English (Australia)', 'en-AU', 'en_AU', 'ltr', 'en' ),
			'es'    => self::locale( 'Español', 'es', 'es_ES', 'ltr', 'es' ),
			'es-ES' => self::locale( 'Español (España)', 'es-ES', 'es_ES', 'ltr', 'es' ),
			'es-MX' => self::locale( 'Español (México)', 'es-MX', 'es_MX', 'ltr', 'es' ),
			'es-US' => self::locale( 'Español (Estados Unidos)', 'es-US', 'es_US', 'ltr', 'es' ),
			'es-AR' => self::locale( 'Español (Argentina)', 'es-AR', 'es_AR', 'ltr', 'es' ),
			'fr'    => self::locale( 'Français', 'fr', 'fr_FR', 'ltr', 'fr' ),
			'fr-FR' => self::locale( 'Français (France)', 'fr-FR', 'fr_FR', 'ltr', 'fr' ),
			'fr-CA' => self::locale( 'Français (Canada)', 'fr-CA', 'fr_CA', 'ltr', 'fr' ),
			'de'    => self::locale( 'Deutsch', 'de', 'de_DE', 'ltr', 'de' ),
			'de-DE' => self::locale( 'Deutsch (Deutschland)', 'de-DE', 'de_DE', 'ltr', 'de' ),
			'de-AT' => self::locale( 'Deutsch (Österreich)', 'de-AT', 'de_AT', 'ltr', 'de' ),
			'de-CH' => self::locale( 'Deutsch (Schweiz)', 'de-CH', 'de_CH', 'ltr', 'de' ),
			'it'    => self::locale( 'Italiano', 'it', 'it_IT', 'ltr', 'it' ),
			'it-IT' => self::locale( 'Italiano (Italia)', 'it-IT', 'it_IT', 'ltr', 'it' ),
			'pt'    => self::locale( 'Português', 'pt', 'pt_PT', 'ltr', 'pt' ),
			'pt-PT' => self::locale( 'Português (Portugal)', 'pt-PT', 'pt_PT', 'ltr', 'pt' ),
			'pt-BR' => self::locale( 'Português (Brasil)', 'pt-BR', 'pt_BR', 'ltr', 'pt' ),
			'nl'    => self::locale( 'Nederlands', 'nl', 'nl_NL', 'ltr', 'nl' ),
			'nl-NL' => self::locale( 'Nederlands (Nederland)', 'nl-NL', 'nl_NL', 'ltr', 'nl' ),
			'nl-BE' => self::locale( 'Nederlands (België)', 'nl-BE', 'nl_BE', 'ltr', 'nl' ),
			'ca'    => self::locale( 'Català', 'ca', 'ca', 'ltr', 'ca' ),
			'eu'    => self::locale( 'Euskara', 'eu', 'eu', 'ltr', 'eu' ),
			'gl'    => self::locale( 'Galego', 'gl', 'gl_ES', 'ltr', 'gl' ),
			'da'    => self::locale( 'Dansk', 'da', 'da_DK', 'ltr', 'da' ),
			'nb-NO' => self::locale( 'Norsk bokmål', 'nb-NO', 'nb_NO', 'ltr', 'nb' ),
			'sv'    => self::locale( 'Svenska', 'sv', 'sv_SE', 'ltr', 'sv' ),
			'fi'    => self::locale( 'Suomi', 'fi', 'fi', 'ltr', 'fi' ),
			'is'    => self::locale( 'Íslenska', 'is', 'is_IS', 'ltr', 'is' ),
			'pl'    => self::locale( 'Polski', 'pl', 'pl_PL', 'ltr', 'pl' ),
			'cs'    => self::locale( 'Čeština', 'cs', 'cs_CZ', 'ltr', 'cs' ),
			'sk'    => self::locale( 'Slovenčina', 'sk', 'sk_SK', 'ltr', 'sk' ),
			'hu'    => self::locale( 'Magyar', 'hu', 'hu_HU', 'ltr', 'hu' ),
			'ro'    => self::locale( 'Română', 'ro', 'ro_RO', 'ltr', 'ro' ),
			'bg'    => self::locale( 'Български', 'bg', 'bg_BG', 'ltr', 'bg' ),
			'hr'    => self::locale( 'Hrvatski', 'hr', 'hr', 'ltr', 'hr' ),
			'sl'    => self::locale( 'Slovenščina', 'sl', 'sl_SI', 'ltr', 'sl' ),
			'sr'    => self::locale( 'Српски', 'sr', 'sr_RS', 'ltr', 'sr' ),
			'el'    => self::locale( 'Ελληνικά', 'el', 'el', 'ltr', 'el' ),
			'ru'    => self::locale( 'Русский', 'ru', 'ru_RU', 'ltr', 'ru' ),
			'uk'    => self::locale( 'Українська', 'uk', 'uk', 'ltr', 'uk' ),
			'tr'    => self::locale( 'Türkçe', 'tr', 'tr_TR', 'ltr', 'tr' ),
			'ar'    => self::locale( 'العربية', 'ar', 'ar', 'rtl', 'ar' ),
			'ar-SA' => self::locale( 'العربية (السعودية)', 'ar-SA', 'ar', 'rtl', 'ar' ),
			'ar-AE' => self::locale( 'العربية (الإمارات)', 'ar-AE', 'ar', 'rtl', 'ar' ),
			'he'    => self::locale( 'עברית', 'he', 'he_IL', 'rtl', 'he' ),
			'fa'    => self::locale( 'فارسی', 'fa', 'fa_IR', 'rtl', 'fa' ),
			'ur'    => self::locale( 'اردو', 'ur', 'ur', 'rtl', 'ur' ),
			'hi'    => self::locale( 'हिन्दी', 'hi', 'hi_IN', 'ltr', 'hi' ),
			'bn'    => self::locale( 'বাংলা', 'bn', 'bn_BD', 'ltr', 'bn' ),
			'zh-CN' => self::locale( '简体中文', 'zh-CN', 'zh_CN', 'ltr', 'zh' ),
			'zh-TW' => self::locale( '繁體中文', 'zh-TW', 'zh_TW', 'ltr', 'zh' ),
			'ja'    => self::locale( '日本語', 'ja', 'ja', 'ltr', 'ja' ),
			'ko'    => self::locale( '한국어', 'ko', 'ko_KR', 'ltr', 'ko' ),
			'vi'    => self::locale( 'Tiếng Việt', 'vi', 'vi', 'ltr', 'vi' ),
			'th'    => self::locale( 'ไทย', 'th', 'th', 'ltr', 'th' ),
			'id'    => self::locale( 'Bahasa Indonesia', 'id', 'id_ID', 'ltr', 'id' ),
			'ms'    => self::locale( 'Bahasa Melayu', 'ms', 'ms_MY', 'ltr', 'ms' ),
		);
	}

	/**
	 * Create one registry record.
	 *
	 * @param string $native_label Native language label.
	 * @param string $html_lang    Canonical HTML language.
	 * @param string $wp_locale    WordPress locale.
	 * @param string $direction    Text direction.
	 * @param string $provider     Translation-provider language code.
	 * @return array<string,string>
	 */
	private static function locale( $native_label, $html_lang, $wp_locale, $direction, $provider ) {
		return array(
			'native_label'  => $native_label,
			'html_lang'     => $html_lang,
			'wp_locale'     => $wp_locale,
			'direction'     => $direction,
			'provider_code' => $provider,
		);
	}

	/**
	 * Build safe defaults using the WordPress locale when it is curated.
	 *
	 * @param string $site_locale WordPress-style or BCP 47 locale.
	 * @return array<string,mixed>
	 */
	public static function get_defaults( $site_locale = '' ) {
		$main_language = self::normalize_locale_code( $site_locale );
		if ( '' === $main_language ) {
			$main_language = 'en-US';
		}

		return array(
			'schema_version'             => self::SCHEMA_VERSION,
			'enabled'                    => false,
			'feature_state'              => 'disabled',
			'migration_state'            => 'not_started',
			'migration_version'          => 0,
			'migration_cursor'           => 0,
			'main_language'              => $main_language,
			'languages'                  => array(
				array(
					'code'   => $main_language,
					'prefix' => '',
				),
			),
			'url_policy'                 => 'main_unprefixed',
			'missing_translation_policy' => 'not_found',
			'clone_identity_policy'      => 'copy_unconfirmed',
			'source_change_policy'       => 'keep_minor',
			'critical_change_policy'     => 'hide_until_reviewed',
			'archive_base_policy'        => 'stable',
			'header_switcher_scope'      => 'template_headers',
			'commerce_storage_policy'    => 'canonical_overlay',
			'taxonomy_storage_policy'    => 'wp_terms_woo_overlays',
			'translator_network_policy'  => 'routable_https',
		);
	}

	/**
	 * Rebuild a complete settings record from known keys and values.
	 *
	 * Invalid and unknown language rows are discarded. If fewer than two valid
	 * languages remain, multilingual mode cannot be enabled.
	 *
	 * @param mixed  $raw         Untrusted settings value.
	 * @param string $site_locale WordPress-style or BCP 47 fallback locale.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $raw, $site_locale = '' ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::get_defaults( $site_locale );
		$main     = self::normalize_locale_code( isset( $raw['main_language'] ) ? $raw['main_language'] : '' );

		if ( '' === $main ) {
			$main = $defaults['main_language'];
		}

		$languages = self::sanitize_languages(
			isset( $raw['languages'] ) && is_array( $raw['languages'] ) ? $raw['languages'] : array(),
			$main
		);

		$enabled           = self::to_bool( isset( $raw['enabled'] ) ? $raw['enabled'] : false );
		$migration_version = self::bounded_integer( isset( $raw['migration_version'] ) ? $raw['migration_version'] : 0, 0, self::MIGRATION_VERSION );
		$migration_state   = self::allow_value(
			isset( $raw['migration_state'] ) ? $raw['migration_state'] : $defaults['migration_state'],
			array( 'not_started', 'pending', 'running', 'complete', 'failed' ),
			$defaults['migration_state']
		);

		if ( $enabled && count( $languages ) < 2 ) {
			$enabled = false;
		}

		if ( $enabled && 'not_started' === $migration_state ) {
			$migration_state = 'pending';
		}
		if ( $enabled && 'complete' === $migration_state && self::MIGRATION_VERSION !== $migration_version ) {
			$migration_state = 'pending';
		}

		$feature_state = 'disabled';
		if ( $enabled ) {
			$feature_state = 'complete' === $migration_state ? 'enabled' : 'enabling';
		}

		return array(
			'schema_version'             => self::SCHEMA_VERSION,
			'enabled'                    => $enabled,
			'feature_state'              => $feature_state,
			'migration_state'            => $migration_state,
			'migration_version'          => $migration_version,
			'migration_cursor'           => self::bounded_integer( isset( $raw['migration_cursor'] ) ? $raw['migration_cursor'] : 0, 0, 2147483647 ),
			'main_language'              => $main,
			'languages'                  => $languages,
			'url_policy'                 => 'main_unprefixed',
			'missing_translation_policy' => self::allow_value(
				isset( $raw['missing_translation_policy'] ) ? $raw['missing_translation_policy'] : $defaults['missing_translation_policy'],
				array( 'not_found', 'visible_redirect' ),
				$defaults['missing_translation_policy']
			),
			'clone_identity_policy'      => self::allow_value(
				isset( $raw['clone_identity_policy'] ) ? $raw['clone_identity_policy'] : $defaults['clone_identity_policy'],
				array( 'copy_unconfirmed', 'require_translated' ),
				$defaults['clone_identity_policy']
			),
			'source_change_policy'       => self::allow_value(
				isset( $raw['source_change_policy'] ) ? $raw['source_change_policy'] : $defaults['source_change_policy'],
				array( 'keep_minor', 'hide_until_reviewed' ),
				$defaults['source_change_policy']
			),
			'critical_change_policy'     => 'hide_until_reviewed',
			'archive_base_policy'        => self::allow_value(
				isset( $raw['archive_base_policy'] ) ? $raw['archive_base_policy'] : $defaults['archive_base_policy'],
				array( 'stable', 'translated' ),
				$defaults['archive_base_policy']
			),
			'header_switcher_scope'      => self::allow_value(
				isset( $raw['header_switcher_scope'] ) ? $raw['header_switcher_scope'] : $defaults['header_switcher_scope'],
				array( 'template_headers', 'all_header_like' ),
				$defaults['header_switcher_scope']
			),
			'commerce_storage_policy'    => 'canonical_overlay',
			'taxonomy_storage_policy'    => 'wp_terms_woo_overlays',
			'translator_network_policy'  => 'routable_https',
		);
	}

	/**
	 * Read and revalidate the stored option.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$site_locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		$saved       = get_option( self::OPTION_NAME, array() );
		return self::sanitize_settings( $saved, $site_locale );
	}

	/**
	 * Get one registry entry by a loose locale spelling.
	 *
	 * @param mixed $code Locale identifier.
	 * @return array<string,string>|null
	 */
	public static function get_locale( $code ) {
		$code     = self::normalize_locale_code( $code );
		$registry = self::get_locale_registry();
		return '' !== $code && isset( $registry[ $code ] ) ? $registry[ $code ] : null;
	}

	/**
	 * Normalize a code only when it exactly maps to the curated registry.
	 *
	 * @param mixed $code Locale identifier.
	 * @return string Canonical registry identifier, or an empty string.
	 */
	public static function normalize_locale_code( $code ) {
		if ( ! is_string( $code ) && ! is_numeric( $code ) ) {
			return '';
		}

		$needle = strtolower( str_replace( '_', '-', trim( (string) $code ) ) );
		if ( '' === $needle || ! preg_match( '/^[a-z]{2,3}(?:-[a-z]{2})?$/', $needle ) ) {
			return '';
		}

		foreach ( array_keys( self::get_locale_registry() ) as $registered ) {
			if ( strtolower( $registered ) === $needle ) {
				return $registered;
			}
		}

		return '';
	}

	/**
	 * Normalize one safe URL path segment and reject reserved route prefixes.
	 *
	 * @param mixed $prefix Submitted path segment.
	 * @return string Safe lowercase prefix, or an empty string when invalid.
	 */
	public static function sanitize_prefix( $prefix ) {
		if ( ! is_string( $prefix ) && ! is_numeric( $prefix ) ) {
			return '';
		}

		$prefix = strtolower( str_replace( '_', '-', trim( (string) $prefix, " \t\n\r\0\x0B/" ) ) );
		$prefix = preg_replace( '/-+/', '-', $prefix );

		if (
			'' === $prefix ||
			strlen( $prefix ) > self::MAX_PREFIX_LENGTH ||
			! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $prefix ) ||
			self::is_reserved_prefix( $prefix )
		) {
			return '';
		}

		return $prefix;
	}

	/**
	 * Whether a prefix is owned by WordPress or a common core route.
	 *
	 * Dynamic page, taxonomy, and archive collisions receive a second check in
	 * the routing phase; this list prevents unsafe core/reserved values now.
	 *
	 * @param mixed $prefix Normalized or raw path segment.
	 * @return bool
	 */
	public static function is_reserved_prefix( $prefix ) {
		$prefix = strtolower( trim( (string) $prefix, " \t\n\r\0\x0B/" ) );
		return in_array(
			$prefix,
			array(
				'admin',
				'api',
				'atom',
				'author',
				'cart',
				'category',
				'checkout',
				'comments',
				'embed',
				'feed',
				'feeds',
				'login',
				'my-account',
				'page',
				'product',
				'product-category',
				'product-tag',
				'rdf',
				'rest',
				'robots',
				'rss',
				'rss2',
				'search',
				'shop',
				'sitemap',
				'sitemap-index',
				'tag',
				'wc-ajax',
				'wp-admin',
				'wp-comments-post',
				'wp-content',
				'wp-includes',
				'wp-json',
				'wp-login',
				'xmlrpc',
			),
			true
		);
	}

	/**
	 * Extract ordered language codes from sanitized settings.
	 *
	 * @param mixed $settings Raw or sanitized settings.
	 * @return string[]
	 */
	public static function get_enabled_language_codes( $settings = null ) {
		$settings = null === $settings ? self::get_settings() : self::sanitize_settings( $settings );
		return array_values(
			array_map(
				static function ( $language ) {
					return $language['code'];
				},
				$settings['languages']
			)
		);
	}

	/**
	 * Whether sanitized settings have enough languages to begin enablement.
	 *
	 * The plugin-conflict gate remains a separate service so validation can be
	 * used safely during migrations and imports without hidden global behavior.
	 *
	 * @param mixed $settings Raw or sanitized settings.
	 * @return bool
	 */
	public static function has_multilingual_configuration( $settings ) {
		$settings = self::sanitize_settings( $settings );
		return ! empty( $settings['enabled'] ) && count( $settings['languages'] ) > 1;
	}

	/**
	 * Sanitize and de-duplicate the ordered language list.
	 *
	 * @param array  $rows Submitted rows.
	 * @param string $main Main language.
	 * @return array<int,array{code:string,prefix:string}>
	 */
	private static function sanitize_languages( $rows, $main ) {
		$accepted      = array();
		$seen_codes    = array();
		$seen_prefixes = array();

		foreach ( array_slice( $rows, 0, self::MAX_LANGUAGES * 2 ) as $row ) {
			if ( is_string( $row ) ) {
				$row = array( 'code' => $row );
			}
			if ( ! is_array( $row ) ) {
				continue;
			}

			$code = self::normalize_locale_code( isset( $row['code'] ) ? $row['code'] : '' );
			if ( '' === $code || isset( $seen_codes[ $code ] ) ) {
				continue;
			}

			if ( $code === $main ) {
				$prefix = '';
			} else {
				$has_submitted_prefix = array_key_exists( 'prefix', $row );
				$raw_prefix           = $has_submitted_prefix ? $row['prefix'] : strtolower( $code );
				$prefix               = self::sanitize_prefix( $raw_prefix );
				if ( '' === $prefix || isset( $seen_prefixes[ $prefix ] ) ) {
					continue;
				}
			}

			$accepted[]          = array(
				'code'   => $code,
				'prefix' => $prefix,
			);
			$seen_codes[ $code ] = true;
			if ( '' !== $prefix ) {
				$seen_prefixes[ $prefix ] = true;
			}

			if ( count( $accepted ) >= self::MAX_LANGUAGES ) {
				break;
			}
		}

		if ( ! isset( $seen_codes[ $main ] ) ) {
			array_unshift(
				$accepted,
				array(
					'code'   => $main,
					'prefix' => '',
				)
			);
		} else {
			foreach ( $accepted as $index => $language ) {
				if ( $language['code'] !== $main || 0 === $index ) {
					continue;
				}
				unset( $accepted[ $index ] );
				$accepted = array_values( $accepted );
				array_unshift( $accepted, $language );
				break;
			}
		}

		return array_slice( $accepted, 0, self::MAX_LANGUAGES );
	}

	/**
	 * Constrain a scalar enum value.
	 *
	 * @param mixed    $value   Submitted value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $fallback Safe fallback.
	 * @return string
	 */
	private static function allow_value( $value, $allowed, $fallback ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Interpret only explicit boolean spellings.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on', 'enabled' ), true );
	}

	/**
	 * Sanitize a bounded non-negative integer.
	 *
	 * @param mixed $value Submitted value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private static function bounded_integer( $value, $min, $max ) {
		$value = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $value ) );
	}
}
