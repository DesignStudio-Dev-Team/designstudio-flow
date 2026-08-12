<?php
/**
 * Self-hosted LibreTranslate adapter.
 *
 * Only an administrator-configured, routable HTTPS endpoint is used. The URL is
 * never taken from a request, redirects are refused, TLS verification is
 * mandatory, and every response is treated as untrusted: bounded in size,
 * validated in shape, and stripped of control characters before it reaches the
 * reassembler, which sanitizes it again on the way into storage.
 *
 * Errors reported to the browser are deliberately generic, and nothing here
 * logs segment text, translated output, or the API key.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_LibreTranslate_Provider implements DSF_Translation_Provider {

	const HEALTH_CACHE_KEY = 'dsf_translation_libretranslate_languages';
	const HEALTH_CACHE_TTL = 900;

	/** @var array<string,mixed> */
	private $settings;

	/** @var callable Performs the HTTP request. */
	private $transport;

	/**
	 * @param array $settings  Stored provider settings.
	 * @param array $overrides Optional transport override for tests.
	 */
	public function __construct( $settings = array(), $overrides = array() ) {
		$this->settings  = is_array( $settings ) ? $settings : array();
		$overrides       = is_array( $overrides ) ? $overrides : array();
		$this->transport = isset( $overrides['transport'] ) && is_callable( $overrides['transport'] )
			? $overrides['transport']
			: array( $this, 'request' );
	}

	/** Stable provider identifier. */
	public function get_id() {
		return 'libretranslate';
	}

	/** Human-readable provider name. */
	public function get_label() {
		return __( 'LibreTranslate (self-hosted)', 'designstudio-flow' );
	}

	/** Whether the endpoint passes the approved network policy. */
	public function is_configured() {
		return true === DSF_Translation_Providers::validate_endpoint( $this->settings['endpoint'] ?? '' );
	}

	/**
	 * Verify the endpoint and read its supported languages.
	 *
	 * No site content is sent. The result is cached briefly so a settings screen
	 * cannot be used to hammer the service.
	 *
	 * @return array{languages:string[]}|WP_Error
	 */
	public function check_health() {
		$endpoint = $this->validated_endpoint();
		if ( $endpoint instanceof WP_Error ) {
			return $endpoint;
		}

		$limit = DSF_Translation_Providers::consume_rate_limit( $this->get_id(), $this->settings['rate_limit'] ?? 60 );
		if ( $limit instanceof WP_Error ) {
			return $limit;
		}

		$response = call_user_func( $this->transport, $this->build_url( $endpoint, 'languages' ), null );
		if ( $response instanceof WP_Error ) {
			return $response;
		}

		$languages = array();
		foreach ( is_array( $response ) ? $response : array() as $entry ) {
			$code = is_array( $entry ) && isset( $entry['code'] ) ? $entry['code'] : null;
			if ( is_string( $code ) && preg_match( '/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $code ) ) {
				$languages[] = $code;
			}
			if ( count( $languages ) >= 200 ) {
				break;
			}
		}

		if ( empty( $languages ) ) {
			return new WP_Error( 'dsf_translation_health', __( 'The translation service responded, but did not report any supported languages.', 'designstudio-flow' ) );
		}

		$languages = array_values( array_unique( $languages ) );
		set_transient( self::HEALTH_CACHE_KEY, $languages, self::HEALTH_CACHE_TTL );

		return array( 'languages' => $languages );
	}

	/**
	 * Whether both sides of a language pair are supported.
	 *
	 * @param string $source_language Curated source language.
	 * @param string $target_language Curated target language.
	 * @return bool
	 */
	public function supports_pair( $source_language, $target_language ) {
		$known = get_transient( self::HEALTH_CACHE_KEY );
		if ( ! is_array( $known ) || empty( $known ) ) {
			$health = $this->check_health();
			if ( $health instanceof WP_Error ) {
				return false;
			}
			$known = $health['languages'];
		}

		$source = $this->provider_code( $source_language );
		$target = $this->provider_code( $target_language );
		return '' !== $source && '' !== $target && in_array( $source, $known, true ) && in_array( $target, $known, true );
	}

	/**
	 * Translate a bounded list of plain-text segments.
	 *
	 * A failed batch never discards the segments it could not translate: they
	 * are reported per index so the caller can keep the source text and show the
	 * editor exactly what still needs work.
	 *
	 * @param string[] $segments        Segment texts keyed by caller index.
	 * @param string   $source_language Curated source language.
	 * @param string   $target_language Curated target language.
	 * @return array{translations:array,failed:array}|WP_Error
	 */
	public function translate( $segments, $source_language, $target_language ) {
		$endpoint = $this->validated_endpoint();
		if ( $endpoint instanceof WP_Error ) {
			return $endpoint;
		}

		$source = $this->provider_code( $source_language );
		$target = $this->provider_code( $target_language );
		if ( '' === $source || '' === $target || $source === $target ) {
			return new WP_Error( 'dsf_translation_pair', __( 'That language pair cannot be machine translated.', 'designstudio-flow' ) );
		}

		$prepared = $this->prepare_segments( $segments );
		if ( empty( $prepared['batches'] ) ) {
			return array(
				'translations' => array(),
				'failed'       => $prepared['failed'],
			);
		}

		$translations = array();
		$failed       = $prepared['failed'];

		foreach ( $prepared['batches'] as $batch ) {
			$limit = DSF_Translation_Providers::consume_rate_limit( $this->get_id(), $this->settings['rate_limit'] ?? 60 );
			if ( $limit instanceof WP_Error ) {
				$failed = $this->fail_batch( $failed, $batch, 'rate_limited' );
				continue;
			}

			$response = call_user_func(
				$this->transport,
				$this->build_url( $endpoint, 'translate' ),
				array(
					'q'      => array_values( $batch ),
					'source' => $source,
					'target' => $target,
					'format' => 'text',
				)
			);

			if ( $response instanceof WP_Error ) {
				// A provider outage must never lose work: the caller keeps the
				// source text and the editor can still translate by hand.
				$failed = $this->fail_batch( $failed, $batch, 'request_failed' );
				continue;
			}

			$returned = $this->read_translated_text( $response, count( $batch ) );
			$position = 0;
			foreach ( $batch as $index => $text ) {
				unset( $text );
				if ( isset( $returned[ $position ] ) ) {
					$translations[ $index ] = $returned[ $position ];
				} else {
					$failed[ $index ] = 'missing_translation';
				}
				++$position;
			}
		}

		return array(
			'translations' => $translations,
			'failed'       => $failed,
		);
	}

	/**
	 * Split segments into bounded batches and reject unusable ones.
	 *
	 * @param mixed $segments Segment texts keyed by caller index.
	 * @return array{batches:array,failed:array}
	 */
	private function prepare_segments( $segments ) {
		$batches = array();
		$failed  = array();
		$current = array();
		$chars   = 0;

		foreach ( is_array( $segments ) ? $segments : array() as $index => $text ) {
			if ( ! is_scalar( $text ) ) {
				$failed[ $index ] = 'invalid_segment';
				continue;
			}
			$text = (string) $text;
			if ( '' === trim( $text ) || DSF_Translation_Extractor::MAX_SEGMENT_CHARS < strlen( $text ) ) {
				$failed[ $index ] = 'invalid_segment';
				continue;
			}

			if (
				count( $current ) >= DSF_Translation_Providers::MAX_SEGMENTS
				|| ( $chars + strlen( $text ) ) > DSF_Translation_Providers::MAX_REQUEST_CHARS
			) {
				if ( ! empty( $current ) ) {
					$batches[] = $current;
				}
				$current = array();
				$chars   = 0;
			}

			$current[ $index ] = $text;
			$chars            += strlen( $text );
		}

		if ( ! empty( $current ) ) {
			$batches[] = $current;
		}

		return array(
			'batches' => $batches,
			'failed'  => $failed,
		);
	}

	/**
	 * Mark every segment of a batch as failed without losing its index.
	 *
	 * @param array  $failed Current failures.
	 * @param array  $batch  Batch segments.
	 * @param string $reason Failure reason code.
	 * @return array
	 */
	private function fail_batch( $failed, $batch, $reason ) {
		foreach ( array_keys( $batch ) as $index ) {
			$failed[ $index ] = $reason;
		}
		return $failed;
	}

	/**
	 * Read translated strings out of an untrusted decoded response.
	 *
	 * @param mixed $response Decoded response body.
	 * @param int   $expected Number of segments sent.
	 * @return string[]
	 */
	private function read_translated_text( $response, $expected ) {
		if ( ! is_array( $response ) || ! isset( $response['translatedText'] ) ) {
			return array();
		}

		$values = $response['translatedText'];
		$values = is_array( $values ) ? $values : array( $values );
		$clean  = array();

		foreach ( array_slice( array_values( $values ), 0, $expected ) as $value ) {
			if ( ! is_scalar( $value ) ) {
				break;
			}
			$text = $this->sanitize_returned_text( (string) $value );
			if ( '' === $text ) {
				break;
			}
			$clean[] = $text;
		}

		return $clean;
	}

	/**
	 * Bound and clean one returned string.
	 *
	 * The reassembler sanitizes again for its destination; this only removes
	 * what should never appear in any translated segment.
	 *
	 * @param string $text Returned text.
	 * @return string
	 */
	private function sanitize_returned_text( $text ) {
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		$text = is_string( $text ) ? $text : '';
		if ( DSF_Translation_Extractor::MAX_SEGMENT_CHARS < strlen( $text ) ) {
			return '';
		}
		return trim( $text );
	}

	/**
	 * Re-validate the configured endpoint immediately before every request.
	 *
	 * Re-resolving here keeps a hostname that changed after configuration from
	 * silently pointing at an internal address.
	 *
	 * @return string|WP_Error
	 */
	private function validated_endpoint() {
		$endpoint = isset( $this->settings['endpoint'] ) ? (string) $this->settings['endpoint'] : '';
		$valid    = DSF_Translation_Providers::validate_endpoint( $endpoint );
		return $valid instanceof WP_Error ? $valid : untrailingslashit( $endpoint );
	}

	/**
	 * Build one exact service URL from the configured base.
	 *
	 * @param string $endpoint Validated base URL.
	 * @param string $path     Fixed API path.
	 * @return string
	 */
	private function build_url( $endpoint, $path ) {
		return $endpoint . '/' . $path;
	}

	/**
	 * Map a curated site language to the provider's language code.
	 *
	 * @param string $language Curated language identifier.
	 * @return string
	 */
	private function provider_code( $language ) {
		$record = DSF_Multilingual_Settings::get_locale( $language );
		return is_array( $record ) ? (string) $record['provider_code'] : '';
	}

	/**
	 * Perform one bounded HTTPS request and decode its body.
	 *
	 * @param string     $url  Exact request URL.
	 * @param array|null $body Request body, or null for a read-only request.
	 * @return array|WP_Error
	 */
	private function request( $url, $body = null ) {
		$timeout = (int) ( $this->settings['timeout'] ?? 10 );
		$args    = array(
			'timeout'     => max( DSF_Translation_Providers::MIN_TIMEOUT, min( DSF_Translation_Providers::MAX_TIMEOUT, $timeout ) ),
			'redirection' => 0,
			'sslverify'   => true,
			'httpversion' => '1.1',
			'user-agent'  => 'DesignStudioFlow/' . ( defined( 'DSF_VERSION' ) ? DSF_VERSION : '1.0' ),
			'headers'     => array( 'Accept' => 'application/json' ),
		);

		if ( null === $body ) {
			$response = wp_remote_get( $url, $args );
		} else {
			$api_key = DSF_Translation_Providers::get_api_key( $this->settings );
			if ( '' !== $api_key ) {
				$body['api_key'] = $api_key;
			}
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
			$response                        = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			// The transport error can contain the endpoint and internal details;
			// it is never surfaced or logged.
			return new WP_Error( 'dsf_translation_unreachable', __( 'The translation service could not be reached.', 'designstudio-flow' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'dsf_translation_http_error',
				401 === $code || 403 === $code
					? __( 'The translation service refused the configured API key.', 'designstudio-flow' )
					: __( 'The translation service returned an error.', 'designstudio-flow' )
			);
		}

		$raw = (string) wp_remote_retrieve_body( $response );
		if ( DSF_Translation_Providers::MAX_RESPONSE_BYTES < strlen( $raw ) ) {
			return new WP_Error( 'dsf_translation_response_size', __( 'The translation service returned an unexpectedly large response.', 'designstudio-flow' ) );
		}

		$decoded = json_decode( $raw, true, 8 );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'dsf_translation_response', __( 'The translation service returned an unreadable response.', 'designstudio-flow' ) );
		}

		return $decoded;
	}
}
