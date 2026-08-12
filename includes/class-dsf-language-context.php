<?php
/**
 * Trusted request language context.
 *
 * Exactly one service resolves the language of the current request. Everything
 * downstream — locale, text direction, document attributes, editor payloads,
 * and localized frontend data — reads that resolved value instead of guessing
 * from a URL path, a cookie, or a browser header.
 *
 * The routing layer is the only caller allowed to set the request language, and
 * it may only set a curated, enabled identifier.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Language_Context {

	/** @var self|null */
	private static $instance = null;

	/** @var string Resolved request language, empty until routing resolves one. */
	private $request_language = '';

	/** @var string Language of the object currently being edited or rendered. */
	private $object_language = '';

	/** @var array<string,mixed>|null Per-request settings cache. */
	private $settings = null;

	/** @var callable */
	private $settings_reader;

	/** @var callable */
	private $conflict_detector;

	/**
	 * @param array $args Optional service overrides for tests.
	 */
	public function __construct( $args = array() ) {
		$args                    = is_array( $args ) ? $args : array();
		$this->settings_reader   = isset( $args['settings_reader'] ) && is_callable( $args['settings_reader'] )
			? $args['settings_reader']
			: array( 'DSF_Multilingual_Settings', 'get_settings' );
		$this->conflict_detector = isset( $args['conflict_detector'] ) && is_callable( $args['conflict_detector'] )
			? $args['conflict_detector']
			: array( 'DSF_Multilingual_Conflicts', 'has_conflicts' );
	}

	/**
	 * Return the shared context service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register document-language integration. */
	public function register_hooks() {
		add_filter( 'determine_locale', array( $this, 'filter_determine_locale' ), 20 );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 20, 2 );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
	}

	/** Forget cached settings, normally after the option is saved. */
	public function flush() {
		$this->settings = null;
	}

	/**
	 * Read the sanitized multilingual settings once per request.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings() {
		if ( null === $this->settings ) {
			$settings       = call_user_func( $this->settings_reader );
			$this->settings = is_array( $settings ) ? $settings : DSF_Multilingual_Settings::get_defaults();
		}
		return $this->settings;
	}

	/**
	 * Whether multilingual routing may affect the public site.
	 *
	 * Content stays on its existing URLs while the foundation migration runs.
	 *
	 * @return bool
	 */
	public function is_active() {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) || 'enabled' !== $settings['feature_state'] ) {
			return false;
		}
		return ! call_user_func( $this->conflict_detector );
	}

	/**
	 * The configured main language.
	 *
	 * @return string
	 */
	public function get_main_language() {
		$settings = $this->get_settings();
		return (string) $settings['main_language'];
	}

	/**
	 * Ordered enabled languages with their curated presentation data.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_languages() {
		$languages = array();
		foreach ( $this->get_settings()['languages'] as $language ) {
			$record = self::describe( $language['code'] );
			if ( empty( $record ) ) {
				continue;
			}
			$record['prefix'] = (string) $language['prefix'];
			$languages[]      = $record;
		}
		return $languages;
	}

	/**
	 * Describe one curated language.
	 *
	 * @param string $code Language identifier.
	 * @return array<string,string> Empty when the code is not curated.
	 */
	public static function describe( $code ) {
		$code   = DSF_Multilingual_Settings::normalize_locale_code( $code );
		$record = '' === $code ? null : DSF_Multilingual_Settings::get_locale( $code );
		if ( ! is_array( $record ) ) {
			return array();
		}
		return array(
			'code'          => $code,
			'native_label'  => (string) $record['native_label'],
			'html_lang'     => (string) $record['html_lang'],
			'wp_locale'     => (string) $record['wp_locale'],
			'direction'     => 'rtl' === $record['direction'] ? 'rtl' : 'ltr',
			'provider_code' => (string) $record['provider_code'],
			'prefix'        => '',
		);
	}

	/**
	 * Whether a language is an enabled site language.
	 *
	 * @param string $code Language identifier.
	 * @return bool
	 */
	public function is_enabled_language( $code ) {
		$code = DSF_Multilingual_Settings::normalize_locale_code( $code );
		return '' !== $code && in_array( $code, DSF_Multilingual_Settings::get_enabled_language_codes( $this->get_settings() ), true );
	}

	/**
	 * The URL prefix for a language. The main language has none.
	 *
	 * @param string $code Language identifier.
	 * @return string
	 */
	public function get_prefix( $code ) {
		$code = DSF_Multilingual_Settings::normalize_locale_code( $code );
		foreach ( $this->get_settings()['languages'] as $language ) {
			if ( $language['code'] === $code ) {
				return (string) $language['prefix'];
			}
		}
		return '';
	}

	/**
	 * Resolve an enabled language from a URL prefix.
	 *
	 * @param string $prefix Raw first path segment.
	 * @return string Empty when the segment is not an enabled prefix.
	 */
	public function language_for_prefix( $prefix ) {
		$prefix = DSF_Multilingual_Settings::sanitize_prefix( $prefix );
		if ( '' === $prefix ) {
			return '';
		}
		foreach ( $this->get_settings()['languages'] as $language ) {
			if ( '' !== $language['prefix'] && $language['prefix'] === $prefix ) {
				return (string) $language['code'];
			}
		}
		return '';
	}

	/**
	 * Record the language resolved for this request.
	 *
	 * Only curated, enabled identifiers are accepted, so a crafted request can
	 * never widen the context to an unconfigured language.
	 *
	 * @param string $language Language identifier.
	 * @return bool Whether the language was accepted.
	 */
	public function set_request_language( $language ) {
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		if ( '' === $language || ! $this->is_enabled_language( $language ) ) {
			return false;
		}
		$this->request_language = $language;
		return true;
	}

	/**
	 * The language of the current request.
	 *
	 * @return string
	 */
	public function get_request_language() {
		if ( '' !== $this->request_language ) {
			return $this->request_language;
		}
		if ( '' !== $this->object_language ) {
			return $this->object_language;
		}
		return $this->get_main_language();
	}

	/**
	 * Record the language of the object being edited or rendered.
	 *
	 * @param string $language Language identifier.
	 * @return bool Whether the language was accepted.
	 */
	public function set_object_language( $language ) {
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		if ( '' === $language || ! $this->is_enabled_language( $language ) ) {
			return false;
		}
		$this->object_language = $language;
		return true;
	}

	/** Whether the request resolved to a secondary language. */
	public function is_secondary_request() {
		return $this->is_active() && $this->get_request_language() !== $this->get_main_language();
	}

	/**
	 * The curated record for the current request language.
	 *
	 * @return array<string,string>
	 */
	public function get_current() {
		$record = self::describe( $this->get_request_language() );
		if ( ! empty( $record ) ) {
			$record['prefix'] = $this->get_prefix( $record['code'] );
		}
		return $record;
	}

	/**
	 * Resolve the stored language of one post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when the post has no relationship.
	 */
	public function get_post_language( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return '';
		}
		$member = DSF_Multilingual::get_instance()->get_relationships()->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		return is_array( $member ) ? (string) $member['language'] : '';
	}

	/**
	 * The home URL for one language.
	 *
	 * @param string $language Language identifier.
	 * @param string $path     Optional path appended inside the language.
	 * @return string
	 */
	public function home_url( $language, $path = '' ) {
		$prefix = $this->get_prefix( $language );
		$path   = ltrim( (string) $path, '/' );
		$target = '' === $prefix ? $path : trailingslashit( $prefix ) . $path;
		return home_url( user_trailingslashit( '/' . ltrim( $target, '/' ) ) );
	}

	/**
	 * Serve translated strings and formats in the resolved request language.
	 *
	 * @param string $locale Locale determined by WordPress.
	 * @return string
	 */
	public function filter_determine_locale( $locale ) {
		if ( is_admin() || ! $this->is_active() ) {
			return $locale;
		}
		$current = $this->get_current();
		return empty( $current['wp_locale'] ) ? $locale : $current['wp_locale'];
	}

	/**
	 * Emit the resolved document language and direction.
	 *
	 * @param string $output Attribute string built by WordPress.
	 * @param string $doctype Document type.
	 * @return string
	 */
	public function filter_language_attributes( $output, $doctype = 'html' ) {
		if ( is_admin() || ! $this->is_active() ) {
			return $output;
		}
		$current = $this->get_current();
		if ( empty( $current['html_lang'] ) ) {
			return $output;
		}

		$attributes = array();
		if ( 'rtl' === $current['direction'] ) {
			$attributes[] = 'dir="rtl"';
		} else {
			$attributes[] = 'dir="ltr"';
		}
		$attributes[] = 'lang="' . esc_attr( $current['html_lang'] ) . '"';
		if ( 'xhtml' === $doctype ) {
			$attributes[] = 'xml:lang="' . esc_attr( $current['html_lang'] ) . '"';
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Expose the resolved language to frontend styling.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function filter_body_class( $classes ) {
		if ( ! $this->is_active() ) {
			return $classes;
		}
		$current   = $this->get_current();
		$classes   = is_array( $classes ) ? $classes : array();
		$classes[] = 'dsf-lang-' . sanitize_html_class( strtolower( (string) $current['code'] ) );
		$classes[] = 'dsf-dir-' . ( 'rtl' === $current['direction'] ? 'rtl' : 'ltr' );
		return $classes;
	}

	/**
	 * Expose the edited object's language to admin styling.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function filter_admin_body_class( $classes ) {
		if ( ! $this->is_active() || '' === $this->object_language ) {
			return $classes;
		}
		return trim( (string) $classes . ' dsf-lang-' . sanitize_html_class( strtolower( $this->object_language ) ) );
	}

	/**
	 * Build the localized language payload shared with editor and frontend code.
	 *
	 * @return array<string,mixed>
	 */
	public function get_localized_payload() {
		if ( ! $this->is_active() ) {
			return array(
				'active'  => false,
				'current' => '',
				'main'    => $this->get_main_language(),
				'dir'     => is_rtl() ? 'rtl' : 'ltr',
				'list'    => array(),
			);
		}

		$current = $this->get_current();
		return array(
			'active'  => true,
			'current' => (string) $current['code'],
			'main'    => $this->get_main_language(),
			'dir'     => (string) $current['direction'],
			'list'    => array_map(
				static function ( $language ) {
					return array(
						'code'   => $language['code'],
						'label'  => $language['native_label'],
						'prefix' => $language['prefix'],
						'dir'    => $language['direction'],
					);
				},
				$this->get_languages()
			),
		);
	}
}
