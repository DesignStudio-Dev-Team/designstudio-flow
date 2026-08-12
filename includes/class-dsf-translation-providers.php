<?php
/**
 * Registry and stored configuration for machine-translation providers.
 *
 * Machine translation is a convenience, never a publishing authority. The
 * endpoint always comes from stored settings, the API key is encrypted at rest
 * and used server-side only, and every destination must pass the approved
 * routable-HTTPS network policy before a request is made.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry and stored configuration for translation providers.
 */
class DSF_Translation_Providers {

	const OPTION_NAME        = 'dsf_translation_provider_settings';
	const MAX_ENDPOINT_CHARS = 300;
	const MAX_SEGMENTS       = 50;
	const MAX_REQUEST_CHARS  = 20000;
	const MAX_RESPONSE_BYTES = 1000000;
	const MIN_TIMEOUT        = 3;
	const MAX_TIMEOUT        = 30;
	const MIN_RATE_LIMIT     = 1;
	const MAX_RATE_LIMIT     = 600;

	/**
	 * Default provider configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'provider'   => 'none',
			'endpoint'   => '',
			'api_key'    => '',
			'timeout'    => 10,
			'rate_limit' => 60,
		);
	}

	/**
	 * Rebuild the stored configuration from known keys only.
	 *
	 * The API key is stored encrypted at rest. An empty submitted key keeps the
	 * existing secret so the settings screen never has to echo it back.
	 *
	 * @param mixed $raw      Untrusted settings.
	 * @param mixed $existing Currently stored settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $raw, $existing = null ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::get_defaults();
		$existing = is_array( $existing ) ? $existing : array();

		$provider = isset( $raw['provider'] ) && is_string( $raw['provider'] ) ? sanitize_key( $raw['provider'] ) : '';
		if ( ! in_array( $provider, array_merge( array( 'none' ), array_keys( self::get_adapters() ) ), true ) ) {
			$provider = $defaults['provider'];
		}

		$endpoint = isset( $raw['endpoint'] ) && is_string( $raw['endpoint'] ) ? trim( $raw['endpoint'] ) : '';
		if ( self::MAX_ENDPOINT_CHARS < strlen( $endpoint ) ) {
			$endpoint = '';
		}
		$endpoint = '' === $endpoint ? '' : esc_url_raw( $endpoint, array( 'https' ) );

		$submitted_key = isset( $raw['api_key'] ) && is_string( $raw['api_key'] ) ? trim( $raw['api_key'] ) : '';
		if ( '' !== $submitted_key && 200 >= strlen( $submitted_key ) && preg_match( '/^[A-Za-z0-9_\-\.:]+$/', $submitted_key ) ) {
			$api_key = DSF_Crypto::encrypt( $submitted_key );
		} else {
			$api_key = isset( $existing['api_key'] ) && is_string( $existing['api_key'] ) ? $existing['api_key'] : '';
		}
		if ( isset( $raw['clear_api_key'] ) && $raw['clear_api_key'] ) {
			$api_key = '';
		}

		return array(
			'provider'   => $provider,
			'endpoint'   => (string) $endpoint,
			'api_key'    => (string) $api_key,
			'timeout'    => self::bounded( $raw['timeout'] ?? $defaults['timeout'], self::MIN_TIMEOUT, self::MAX_TIMEOUT, $defaults['timeout'] ),
			'rate_limit' => self::bounded( $raw['rate_limit'] ?? $defaults['rate_limit'], self::MIN_RATE_LIMIT, self::MAX_RATE_LIMIT, $defaults['rate_limit'] ),
		);
	}

	/**
	 * Read the stored configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_NAME, array() );
		return self::sanitize_settings( is_array( $stored ) ? $stored : array(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Persist a sanitized configuration.
	 *
	 * @param mixed $raw Untrusted settings.
	 * @return array<string,mixed> Stored settings.
	 */
	public static function update_settings( $raw ) {
		$existing = get_option( self::OPTION_NAME, array() );
		$clean    = self::sanitize_settings( $raw, is_array( $existing ) ? $existing : array() );
		update_option( self::OPTION_NAME, $clean, false );
		return $clean;
	}

	/**
	 * Return the decrypted API key for server-side use only.
	 *
	 * @param array $settings Stored settings.
	 * @return string
	 */
	public static function get_api_key( $settings ) {
		$stored = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
		return '' === $stored ? '' : DSF_Crypto::decrypt( $stored );
	}

	/**
	 * Available provider adapters.
	 *
	 * @return array<string,string> Provider id to class name.
	 */
	public static function get_adapters() {
		$adapters = array(
			'libretranslate' => 'DSF_LibreTranslate_Provider',
		);

		/**
		 * Filters the available translation-provider adapters.
		 *
		 * Adapters must implement DSF_Translation_Provider. Anything that does
		 * not is discarded rather than trusted.
		 *
		 * @param array<string,string> $adapters Provider id to class name.
		 */
		$adapters = apply_filters( 'dsf_translation_providers', $adapters );

		$clean = array();
		foreach ( is_array( $adapters ) ? $adapters : array() as $id => $class ) {
			$id = is_string( $id ) ? sanitize_key( $id ) : '';
			if ( '' !== $id && is_string( $class ) && class_exists( $class ) && in_array( 'DSF_Translation_Provider', (array) class_implements( $class ), true ) ) {
				$clean[ $id ] = $class;
			}
		}
		return $clean;
	}

	/**
	 * Build the configured provider, when one is selected and usable.
	 *
	 * @return DSF_Translation_Provider|null
	 */
	public static function get_active_provider() {
		$settings = self::get_settings();
		$adapters = self::get_adapters();
		$id       = (string) $settings['provider'];
		if ( 'none' === $id || ! isset( $adapters[ $id ] ) ) {
			return null;
		}
		return new $adapters[ $id ]( $settings );
	}

	/**
	 * Validate a configured endpoint against the approved network policy.
	 *
	 * The approved topology is a routable HTTPS endpoint. Private, loopback,
	 * link-local, and reserved destinations are refused, which keeps a
	 * misconfigured or attacker-supplied endpoint from turning the site into an
	 * SSRF proxy for its own network.
	 *
	 * @param string $endpoint Configured base URL.
	 * @return true|WP_Error
	 */
	public static function validate_endpoint( $endpoint ) {
		$endpoint = is_string( $endpoint ) ? trim( $endpoint ) : '';
		if ( '' === $endpoint || self::MAX_ENDPOINT_CHARS < strlen( $endpoint ) ) {
			return new WP_Error( 'dsf_translation_endpoint', __( 'Enter the URL of your translation service.', 'designstudio-flow' ) );
		}

		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'dsf_translation_endpoint', __( 'The translation service URL is not valid.', 'designstudio-flow' ) );
		}
		if ( ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return new WP_Error( 'dsf_translation_endpoint_tls', __( 'The translation service must be reached over HTTPS.', 'designstudio-flow' ) );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'dsf_translation_endpoint_credentials', __( 'Remove the credentials from the translation service URL and use the API key field.', 'designstudio-flow' ) );
		}
		if ( ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
			return new WP_Error( 'dsf_translation_endpoint', __( 'The translation service URL must not contain a query string.', 'designstudio-flow' ) );
		}

		if ( ! self::host_is_public( (string) $parts['host'] ) ) {
			return new WP_Error( 'dsf_translation_endpoint_private', __( 'The translation service must be a routable public host. Private, loopback, and link-local addresses are not allowed.', 'designstudio-flow' ) );
		}

		return true;
	}

	/**
	 * Whether every address a host resolves to is publicly routable.
	 *
	 * @param string $host Hostname or IP literal.
	 * @return bool
	 */
	public static function host_is_public( $host ) {
		$host = trim( (string) $host, "[] \t\n\r\0\x0B" );
		if ( '' === $host ) {
			return false;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::ip_is_public( $host );
		}

		/**
		 * Filters the resolved addresses of a translation endpoint host.
		 *
		 * Returning an array skips DNS resolution. Every returned address is
		 * still required to be publicly routable, so this can change what is
		 * resolved but never relax the policy itself.
		 *
		 * @param array|null $addresses Resolved addresses, or null to resolve.
		 * @param string     $host      Hostname being validated.
		 */
		$resolved = apply_filters( 'dsf_translation_endpoint_addresses', null, $host );
		if ( is_array( $resolved ) ) {
			if ( empty( $resolved ) ) {
				return false;
			}
			foreach ( $resolved as $address ) {
				if ( ! self::ip_is_public( $address ) ) {
					return false;
				}
			}
			return true;
		}

		$addresses = array();
		if ( function_exists( 'gethostbynamel' ) ) {
			$v4 = gethostbynamel( $host );
			if ( is_array( $v4 ) ) {
				$addresses = array_merge( $addresses, $v4 );
			}
		}
		if ( function_exists( 'dns_get_record' ) && defined( 'DNS_AAAA' ) ) {
			$v6 = dns_get_record( $host, DNS_AAAA );
			foreach ( is_array( $v6 ) ? $v6 : array() as $record ) {
				if ( ! empty( $record['ipv6'] ) ) {
					$addresses[] = $record['ipv6'];
				}
			}
		}

		if ( empty( $addresses ) ) {
			return false;
		}
		foreach ( $addresses as $address ) {
			if ( ! self::ip_is_public( $address ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether one address is outside every private and reserved range.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function ip_is_public( $ip ) {
		return false !== filter_var( (string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Consume one slot from the per-minute request budget.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param int    $limit       Requests allowed per minute.
	 * @return true|WP_Error
	 */
	public static function consume_rate_limit( $provider_id, $limit ) {
		$key   = 'dsf_translation_rate_' . sanitize_key( $provider_id );
		$limit = max( self::MIN_RATE_LIMIT, min( self::MAX_RATE_LIMIT, (int) $limit ) );
		$used  = (int) get_transient( $key );

		if ( $used >= $limit ) {
			return new WP_Error( 'dsf_translation_rate_limited', __( 'The translation service request limit was reached. Try again in a minute.', 'designstudio-flow' ) );
		}

		set_transient( $key, $used + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Clamp an integer setting.
	 *
	 * @param mixed $value    Submitted value.
	 * @param int   $min      Minimum.
	 * @param int   $max      Maximum.
	 * @param int   $fallback Fallback when not numeric.
	 * @return int
	 */
	private static function bounded( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		return max( $min, min( $max, (int) $value ) );
	}
}
