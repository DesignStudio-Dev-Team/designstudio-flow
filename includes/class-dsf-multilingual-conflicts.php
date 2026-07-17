<?php
/**
 * Active multilingual-plugin conflict detection.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect plugins that would compete with Flow for multilingual routing/content.
 *
 * Detection is intentionally read-only. It never deactivates another plugin and
 * never scans the normal plugins directory for inactive installations. Only
 * active, network-active, must-use, and loaded runtime signals are considered.
 */
class DSF_Multilingual_Conflicts {

	/**
	 * Get the filterable conflict-family map.
	 *
	 * The `dsf_multilingual_conflict_map` filter may add site-specific plugin
	 * families. Every filtered value is rebuilt into the known map shape before
	 * it is used.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_conflict_map() {
		$map = array(
			'wpml'              => array(
				'name'    => 'WPML',
				'slugs'   => array( 'sitepress-multilingual-cms', 'wpml-string-translation', 'wpml-media-translation', 'woocommerce-multilingual', 'wp-seo-multilingual', 'acfml' ),
				'runtime' => array(
					'constants' => array( 'ICL_SITEPRESS_VERSION', 'WPML_PLUGIN_PATH' ),
					'classes'   => array( 'SitePress' ),
					'functions' => array( 'icl_object_id' ),
				),
			),
			'polylang'          => array(
				'name'    => 'Polylang',
				'slugs'   => array( 'polylang', 'polylang-pro', 'polylang-wc' ),
				'runtime' => array(
					'constants' => array( 'POLYLANG_VERSION' ),
					'classes'   => array( 'Polylang' ),
					'functions' => array( 'pll_current_language' ),
				),
			),
			'translatepress'    => array(
				'name'    => 'TranslatePress',
				'slugs'   => array( 'translatepress-multilingual', 'translatepress-business' ),
				'runtime' => array(
					'constants' => array( 'TRP_PLUGIN_VERSION' ),
					'classes'   => array( 'TRP_Translate_Press' ),
					'functions' => array( 'trp_translate' ),
				),
			),
			'weglot'            => array(
				'name'    => 'Weglot',
				'slugs'   => array( 'weglot' ),
				'runtime' => array(
					'constants' => array( 'WEGLOT_VERSION' ),
					'classes'   => array( 'Weglot\\Client\\Client' ),
					'functions' => array( 'weglot_get_current_language' ),
				),
			),
			'multilingualpress' => array(
				'name'    => 'MultilingualPress',
				'slugs'   => array( 'multilingualpress', 'multilingualpress-pro' ),
				'runtime' => array(
					'constants' => array( 'MULTILINGUALPRESS_VERSION' ),
					'classes'   => array( 'Inpsyde\\MultilingualPress\\Framework\\Service\\Container' ),
					'functions' => array(),
				),
			),
			'qtranslate'        => array(
				'name'    => 'qTranslate-X / qTranslate-XT',
				'slugs'   => array( 'qtranslate-x', 'qtranslate-xt' ),
				'runtime' => array(
					'constants' => array( 'QTX_VERSION', 'QTRANSLATE_FILE' ),
					'classes'   => array(),
					'functions' => array( 'qtranxf_getLanguage' ),
				),
			),
			'gtranslate'        => array(
				'name'    => 'GTranslate',
				'slugs'   => array( 'gtranslate' ),
				'runtime' => array(
					'constants' => array( 'GTRANSLATE_VERSION' ),
					'classes'   => array(),
					'functions' => array(),
				),
			),
			'wpglobus'          => array(
				'name'    => 'WPGlobus',
				'slugs'   => array( 'wpglobus', 'wpglobus-plus' ),
				'runtime' => array(
					'constants' => array( 'WPGLOBUS_VERSION' ),
					'classes'   => array( 'WPGlobus' ),
					'functions' => array(),
				),
			),
			'wp-multilang'      => array(
				'name'    => 'WP Multilang',
				'slugs'   => array( 'wp-multilang' ),
				'runtime' => array(
					'constants' => array( 'WPM_VERSION' ),
					'classes'   => array( 'WPM\\Includes\\WPM' ),
					'functions' => array(),
				),
			),
			'falang'            => array(
				'name'    => 'Falang',
				'slugs'   => array( 'falang' ),
				'runtime' => array(
					'constants' => array( 'FALANG_VERSION' ),
					'classes'   => array( 'Falang' ),
					'functions' => array(),
				),
			),
			'conveythis'        => array(
				'name'    => 'ConveyThis',
				'slugs'   => array( 'conveythis-translate', 'conveythis' ),
				'runtime' => array(
					'constants' => array( 'CONVEYTHIS_VERSION' ),
					'classes'   => array(),
					'functions' => array( 'conveythis_get_language' ),
				),
			),
			'linguise'          => array(
				'name'    => 'Linguise',
				'slugs'   => array( 'linguise' ),
				'runtime' => array(
					'constants' => array( 'LINGUISE_VERSION' ),
					'classes'   => array( 'Linguise' ),
					'functions' => array(),
				),
			),
			'google-translator' => array(
				'name'    => 'Google Language Translator',
				'slugs'   => array( 'google-language-translator', 'prisna-google-website-translator' ),
				'runtime' => array(
					'constants' => array(),
					'classes'   => array(),
					'functions' => array(),
				),
			),
		);

		$map = apply_filters( 'dsf_multilingual_conflict_map', $map );

		return self::sanitize_conflict_map( $map );
	}

	/**
	 * Detect active conflicts.
	 *
	 * `$sources` can be injected by tests or callers that already have plugin
	 * state. Only the known `active`, `network_active`, and `must_use` keys are
	 * inspected; an installed-but-inactive inventory is intentionally ignored.
	 *
	 * @param array<string,mixed>|null $sources      Active plugin source lists.
	 * @param array<string,mixed>|null $runtime_hits Precomputed runtime signals.
	 * @return array<string,array<string,mixed>> Conflicts keyed by family ID.
	 */
	public static function detect_conflicts( $sources = null, $runtime_hits = null ) {
		$map                  = self::get_conflict_map();
		$sources              = is_array( $sources ) ? $sources : self::collect_active_sources();
		$allowed_source_names = array( 'active', 'network_active', 'must_use' );
		$conflicts            = array();

		foreach ( $allowed_source_names as $source_name ) {
			$plugin_files = self::normalize_plugin_file_list( isset( $sources[ $source_name ] ) ? $sources[ $source_name ] : array() );
			foreach ( $plugin_files as $plugin_file ) {
				$candidates = self::plugin_file_candidates( $plugin_file );
				foreach ( $map as $family_id => $family ) {
					if ( empty( array_intersect( $family['slugs'], $candidates ) ) ) {
						continue;
					}
					self::add_conflict_signal( $conflicts, $family_id, $family, $source_name, $plugin_file, '' );
				}
			}
		}

		if ( null === $runtime_hits ) {
			$runtime_hits = self::collect_runtime_hits( $map );
		}
		$runtime_hits = is_array( $runtime_hits ) ? $runtime_hits : array();

		foreach ( $runtime_hits as $family_id => $signals ) {
			if ( ! isset( $map[ $family_id ] ) ) {
				continue;
			}
			foreach ( (array) $signals as $signal ) {
				$signal = self::sanitize_runtime_report( $signal );
				if ( '' !== $signal ) {
					self::add_conflict_signal( $conflicts, $family_id, $map[ $family_id ], 'runtime', '', $signal );
				}
			}
		}

		foreach ( $conflicts as &$conflict ) {
			$conflict['sources']         = array_values( array_unique( $conflict['sources'] ) );
			$conflict['plugin_files']    = array_values( array_unique( $conflict['plugin_files'] ) );
			$conflict['runtime_signals'] = array_values( array_unique( $conflict['runtime_signals'] ) );
		}
		unset( $conflict );

		ksort( $conflicts );
		return $conflicts;
	}

	/**
	 * Whether any active conflict exists.
	 *
	 * @param array<string,mixed>|null $sources      Active plugin source lists.
	 * @param array<string,mixed>|null $runtime_hits Precomputed runtime signals.
	 * @return bool
	 */
	public static function has_conflicts( $sources = null, $runtime_hits = null ) {
		return ! empty( self::detect_conflicts( $sources, $runtime_hits ) );
	}

	/**
	 * Read active, network-active, and must-use plugin basenames.
	 *
	 * @return array<string,array>
	 */
	public static function collect_active_sources() {
		$active         = get_option( 'active_plugins', array() );
		$network_active = get_site_option( 'active_sitewide_plugins', array() );
		$must_use       = function_exists( 'wp_get_mu_plugins' ) ? wp_get_mu_plugins() : array();
		if ( function_exists( 'get_mu_plugins' ) ) {
			$must_use = array_merge( (array) $must_use, array_keys( (array) get_mu_plugins() ) );
		}

		return array(
			'active'         => is_array( $active ) ? $active : array(),
			'network_active' => is_array( $network_active ) ? $network_active : array(),
			'must_use'       => is_array( $must_use ) ? $must_use : array(),
		);
	}

	/**
	 * Derive exact slug candidates from an active plugin basename/path.
	 *
	 * @param mixed $plugin_file Plugin basename or path.
	 * @return string[]
	 */
	public static function plugin_file_candidates( $plugin_file ) {
		if ( ! is_string( $plugin_file ) ) {
			return array();
		}

		$path = strtolower( trim( str_replace( '\\', '/', $plugin_file ), '/' ) );
		if ( '' === $path || strlen( $path ) > 500 || ! preg_match( '#^[a-z0-9_./-]+$#', $path ) ) {
			return array();
		}

		$parts = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( empty( $parts ) || in_array( '..', $parts, true ) || in_array( '.', $parts, true ) ) {
			return array();
		}

		$file_base  = preg_replace( '/\.php$/', '', end( $parts ) );
		$candidates = array( self::sanitize_slug( $file_base ) );
		if ( count( $parts ) > 1 ) {
			$candidates[] = self::sanitize_slug( $parts[ count( $parts ) - 2 ] );
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	/**
	 * Gather exact loaded runtime signatures without triggering autoloaders.
	 *
	 * @param array<string,array<string,mixed>> $map Conflict map.
	 * @return array<string,string[]>
	 */
	private static function collect_runtime_hits( $map ) {
		$hits = array();

		foreach ( $map as $family_id => $family ) {
			foreach ( $family['runtime']['constants'] as $constant ) {
				if ( defined( $constant ) ) {
					$hits[ $family_id ][] = 'constant:' . $constant;
				}
			}
			foreach ( $family['runtime']['classes'] as $class ) {
				if ( class_exists( $class, false ) || interface_exists( $class, false ) ) {
					$hits[ $family_id ][] = 'class:' . $class;
				}
			}
			foreach ( $family['runtime']['functions'] as $function ) {
				if ( function_exists( $function ) ) {
					$hits[ $family_id ][] = 'function:' . $function;
				}
			}
		}

		return $hits;
	}

	/**
	 * Rebuild a filtered conflict map from known keys.
	 *
	 * @param mixed $map Filtered map.
	 * @return array<string,array<string,mixed>>
	 */
	private static function sanitize_conflict_map( $map ) {
		$clean = array();
		if ( ! is_array( $map ) ) {
			return $clean;
		}

		foreach ( $map as $family_id => $family ) {
			$family_id = self::sanitize_slug( $family_id );
			if ( '' === $family_id || ! is_array( $family ) ) {
				continue;
			}

			// The value is reduced to plain text here and must still be escaped by any UI.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure helper avoids a global WP_Mock function shim.
			$name  = isset( $family['name'] ) && is_string( $family['name'] ) ? trim( strip_tags( $family['name'] ) ) : '';
			$slugs = array();
			foreach ( isset( $family['slugs'] ) ? (array) $family['slugs'] : array() as $slug ) {
				$slug = self::sanitize_slug( $slug );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}

			$runtime             = isset( $family['runtime'] ) && is_array( $family['runtime'] ) ? $family['runtime'] : array();
			$clean[ $family_id ] = array(
				'name'    => '' !== $name ? substr( $name, 0, 100 ) : $family_id,
				'slugs'   => array_values( array_unique( $slugs ) ),
				'runtime' => array(
					'constants' => self::sanitize_runtime_identifiers( isset( $runtime['constants'] ) ? $runtime['constants'] : array() ),
					'classes'   => self::sanitize_runtime_identifiers( isset( $runtime['classes'] ) ? $runtime['classes'] : array() ),
					'functions' => self::sanitize_runtime_identifiers( isset( $runtime['functions'] ) ? $runtime['functions'] : array() ),
				),
			);
		}

		return $clean;
	}

	/**
	 * Normalize a plugin basename list from indexed or associative WP options.
	 *
	 * @param mixed $plugins Plugin list.
	 * @return string[]
	 */
	private static function normalize_plugin_file_list( $plugins ) {
		$files = array();
		if ( ! is_array( $plugins ) ) {
			return $files;
		}

		foreach ( $plugins as $key => $value ) {
			$candidate = is_int( $key ) ? $value : $key;
			if ( ! is_string( $candidate ) ) {
				continue;
			}
			$candidate = trim( str_replace( '\\', '/', $candidate ) );
			if ( '' !== $candidate && strlen( $candidate ) <= 500 ) {
				$files[] = $candidate;
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Add one source/runtime signal to a conflict record.
	 *
	 * @param array  $conflicts  Conflict accumulator.
	 * @param string $family_id Family identifier.
	 * @param array  $family    Family definition.
	 * @param string $source    Signal source.
	 * @param string $file      Plugin file.
	 * @param string $runtime   Runtime signature.
	 */
	private static function add_conflict_signal( &$conflicts, $family_id, $family, $source, $file, $runtime ) {
		if ( ! isset( $conflicts[ $family_id ] ) ) {
			$conflicts[ $family_id ] = array(
				'id'              => $family_id,
				'name'            => $family['name'],
				'sources'         => array(),
				'plugin_files'    => array(),
				'runtime_signals' => array(),
			);
		}

		$conflicts[ $family_id ]['sources'][] = $source;
		if ( '' !== $file ) {
			$conflicts[ $family_id ]['plugin_files'][] = $file;
		}
		if ( '' !== $runtime ) {
			$conflicts[ $family_id ]['runtime_signals'][] = $runtime;
		}
	}

	/**
	 * Sanitize a plugin family/slug identifier.
	 *
	 * @param mixed $value Identifier.
	 * @return string
	 */
	private static function sanitize_slug( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/\.php$/', '', $value );
		return preg_match( '/^[a-z0-9][a-z0-9_-]{0,99}$/', $value ) ? $value : '';
	}

	/**
	 * Constrain runtime identifiers from the filterable map.
	 *
	 * @param mixed $identifiers Runtime identifiers.
	 * @return string[]
	 */
	private static function sanitize_runtime_identifiers( $identifiers ) {
		$clean = array();
		foreach ( (array) $identifiers as $identifier ) {
			if ( is_string( $identifier ) && strlen( $identifier ) <= 200 && preg_match( '/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $identifier ) ) {
				$clean[] = $identifier;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Clean a runtime signal for an admin-only report.
	 *
	 * @param mixed $signal Runtime signal.
	 * @return string
	 */
	private static function sanitize_runtime_report( $signal ) {
		if ( ! is_string( $signal ) ) {
			return '';
		}
		$signal = trim( $signal );
		return strlen( $signal ) <= 220 && preg_match( '/^(constant|class|function):[A-Za-z_][A-Za-z0-9_\\\\]*$/', $signal ) ? $signal : '';
	}
}
