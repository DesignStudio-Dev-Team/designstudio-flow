<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-crypto.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-html.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-extractor.php';
require_once dirname( __DIR__ ) . '/includes/interface-dsf-translation-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-providers.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-libretranslate-provider.php';

/**
 * Covers the provider contract, the network policy, and the LibreTranslate
 * adapter. Every request is served by an injected transport double — these
 * tests never open a socket.
 */
class Test_DSF_Translation_Provider extends TestCase {

	/** @var array<string,mixed> */
	private $transients = array();

	/** @var array<int,array<string,mixed>> */
	private $calls = array();

	/** @var array<int,mixed> */
	private $responses = array();

	/** @var string[] */
	private $public_hosts = array( 'translate.example.com' );

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->transients = array();
		$this->calls      = array();
		$this->responses  = array();

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'untrailingslashit', array( 'return' => static function ( $v ) { return rtrim( (string) $v, '/' ); } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => static function ( $v ) { return $v instanceof WP_Error; } ) );
		WP_Mock::userFunction(
			'wp_parse_url',
			array(
				'return' => static function ( $url, $component = -1 ) {
					return -1 === $component ? wp_parse_url_native( $url ) : wp_parse_url_native( $url, $component );
				},
			)
		);

		$transients = &$this->transients;
		WP_Mock::userFunction(
			'get_transient',
			array(
				'return' => static function ( $key ) use ( &$transients ) {
					return array_key_exists( $key, $transients ) ? $transients[ $key ] : false;
				},
			)
		);
		WP_Mock::userFunction(
			'set_transient',
			array(
				'return' => static function ( $key, $value ) use ( &$transients ) {
					$transients[ $key ] = $value;
					return true;
				},
			)
		);
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Pin DNS resolution for every host this test touches.
	 *
	 * @param string $address Address every host resolves to.
	 */
	private function resolve_to( $address ) {
		foreach ( array( 'translate.example.com', 'internal.example.com', 'intranet.example.com' ) as $host ) {
			WP_Mock::onFilter( 'dsf_translation_endpoint_addresses' )
				->with( null, $host )
				->reply( array( $address ) );
		}
	}

	/**
	 * Build a provider whose transport is fully controlled by the test.
	 *
	 * @param array $settings Provider settings.
	 * @return DSF_LibreTranslate_Provider
	 */
	private function provider( $settings = array() ) {
		$calls     = &$this->calls;
		$responses = &$this->responses;

		return new DSF_LibreTranslate_Provider(
			array_merge(
				array(
					'provider'   => 'libretranslate',
					'endpoint'   => 'https://translate.example.com',
					'api_key'    => '',
					'timeout'    => 10,
					'rate_limit' => 60,
				),
				$settings
			),
			array(
				'transport' => static function ( $url, $body ) use ( &$calls, &$responses ) {
					$calls[] = array(
						'url'  => $url,
						'body' => $body,
					);
					if ( empty( $responses ) ) {
						return new WP_Error( 'no_response', 'no queued response' );
					}
					return array_shift( $responses );
				},
			)
		);
	}

	public function test_endpoint_policy_refuses_unsafe_destinations() {
		WP_Mock::onFilter( 'dsf_translation_endpoint_addresses' )
			->with( null, 'translate.example.com' )
			->reply( array( '93.184.216.34' ) );
		WP_Mock::onFilter( 'dsf_translation_endpoint_addresses' )
			->with( null, 'internal.example.com' )
			->reply( array( '10.1.2.3' ) );

		$this->assertTrue( DSF_Translation_Providers::validate_endpoint( 'https://translate.example.com' ) );

		$cases = array(
			'http://translate.example.com'                 => 'dsf_translation_endpoint_tls',
			'https://user:pass@translate.example.com'      => 'dsf_translation_endpoint_credentials',
			'https://translate.example.com?debug=1'        => 'dsf_translation_endpoint',
			'https://127.0.0.1'                            => 'dsf_translation_endpoint_private',
			'https://internal.example.com'                 => 'dsf_translation_endpoint_private',
			'ftp://translate.example.com'                  => 'dsf_translation_endpoint_tls',
			'not a url'                                    => 'dsf_translation_endpoint',
			''                                             => 'dsf_translation_endpoint',
		);

		foreach ( $cases as $endpoint => $expected ) {
			$result = DSF_Translation_Providers::validate_endpoint( $endpoint );
			$this->assertInstanceOf( 'WP_Error', $result, $endpoint );
			$this->assertSame( $expected, $result->get_error_code(), $endpoint );
		}
	}

	public function test_private_and_reserved_addresses_are_never_public() {
		foreach ( array( '127.0.0.1', '10.0.0.5', '192.168.1.10', '172.16.4.4', '169.254.169.254', '::1', 'fc00::1', '0.0.0.0' ) as $ip ) {
			$this->assertFalse( DSF_Translation_Providers::ip_is_public( $ip ), $ip );
		}
		$this->assertTrue( DSF_Translation_Providers::ip_is_public( '93.184.216.34' ) );
	}

	public function test_settings_are_rebuilt_from_known_keys_and_the_secret_is_encrypted() {
		WP_Mock::userFunction( 'wp_salt', array( 'return' => 'unit-test-salt' ) );

		$clean = DSF_Translation_Providers::sanitize_settings(
			array(
				'provider'    => 'libretranslate',
				'endpoint'    => 'https://translate.example.com',
				'api_key'     => 'secret-key-1234',
				'timeout'     => 9999,
				'rate_limit'  => -5,
				'webhook_url' => 'https://evil.test',
			)
		);

		$this->assertSame( array( 'provider', 'endpoint', 'api_key', 'timeout', 'rate_limit' ), array_keys( $clean ) );
		$this->assertSame( DSF_Translation_Providers::MAX_TIMEOUT, $clean['timeout'] );
		$this->assertSame( DSF_Translation_Providers::MIN_RATE_LIMIT, $clean['rate_limit'] );
		$this->assertStringStartsWith( DSF_Crypto::PREFIX, $clean['api_key'], 'The key is stored encrypted at rest.' );
		$this->assertStringNotContainsString( 'secret-key-1234', $clean['api_key'] );
		$this->assertSame( 'secret-key-1234', DSF_Translation_Providers::get_api_key( $clean ) );

		$kept = DSF_Translation_Providers::sanitize_settings( array( 'provider' => 'libretranslate', 'api_key' => '' ), $clean );
		$this->assertSame( $clean['api_key'], $kept['api_key'], 'A blank submission keeps the stored key.' );

		$cleared = DSF_Translation_Providers::sanitize_settings( array( 'provider' => 'libretranslate', 'clear_api_key' => true ), $clean );
		$this->assertSame( '', $cleared['api_key'] );

		$unknown = DSF_Translation_Providers::sanitize_settings( array( 'provider' => 'evil-provider' ) );
		$this->assertSame( 'none', $unknown['provider'] );
	}

	public function test_health_check_sends_no_content_and_reads_supported_languages() {
		$this->resolve_to( '93.184.216.34' );
		$this->responses[] = array(
			array( 'code' => 'en', 'name' => 'English' ),
			array( 'code' => 'es', 'name' => 'Spanish' ),
			array( 'code' => '<script>', 'name' => 'Bad' ),
			'not-an-entry',
		);

		$health = $this->provider()->check_health();

		$this->assertSame( array( 'en', 'es' ), $health['languages'] );
		$this->assertSame( 'https://translate.example.com/languages', $this->calls[0]['url'] );
		$this->assertNull( $this->calls[0]['body'], 'A health check never carries site content.' );
	}

	public function test_translation_batches_segments_and_keeps_indexes() {
		$this->resolve_to( '93.184.216.34' );
		$this->responses[] = array( 'translatedText' => array( 'Hola', 'Mundo' ) );

		$result = $this->provider()->translate(
			array(
				'blocks.0.settings.title'   => 'Hello',
				'blocks.0.settings.heading' => 'World',
			),
			'en-US',
			'es-MX'
		);

		$this->assertSame(
			array(
				'blocks.0.settings.title'   => 'Hola',
				'blocks.0.settings.heading' => 'Mundo',
			),
			$result['translations']
		);
		$this->assertSame( array(), $result['failed'] );
		$this->assertSame( 'https://translate.example.com/translate', $this->calls[0]['url'] );
		$this->assertSame( array( 'Hello', 'World' ), $this->calls[0]['body']['q'] );
		$this->assertSame( 'en', $this->calls[0]['body']['source'] );
		$this->assertSame( 'es', $this->calls[0]['body']['target'] );
		$this->assertArrayNotHasKey( 'api_key', $this->calls[0]['body'], 'No key is sent when none is configured.' );
	}

	public function test_a_failed_request_preserves_every_segment_as_a_failure() {
		$this->resolve_to( '93.184.216.34' );
		$this->responses[] = new WP_Error( 'dsf_translation_unreachable', 'unreachable' );

		$result = $this->provider()->translate( array( 'a' => 'Hello', 'b' => 'World' ), 'en-US', 'es-MX' );

		$this->assertSame( array(), $result['translations'] );
		$this->assertSame(
			array( 'a' => 'request_failed', 'b' => 'request_failed' ),
			$result['failed'],
			'An outage must never silently drop work.'
		);
	}

	public function test_short_and_malformed_responses_become_per_segment_failures() {
		$this->resolve_to( '93.184.216.34' );
		$this->responses[] = array( 'translatedText' => array( 'Hola' ) );

		$result = $this->provider()->translate( array( 'a' => 'Hello', 'b' => 'World' ), 'en-US', 'es-MX' );
		$this->assertSame( array( 'a' => 'Hola' ), $result['translations'] );
		$this->assertSame( array( 'b' => 'missing_translation' ), $result['failed'] );

		$this->responses[] = array( 'unexpected' => true );
		$result            = $this->provider()->translate( array( 'a' => 'Hello' ), 'en-US', 'es-MX' );
		$this->assertSame( array(), $result['translations'] );
		$this->assertSame( array( 'a' => 'missing_translation' ), $result['failed'] );
	}

	public function test_returned_text_is_bounded_and_stripped_of_control_characters() {
		$this->resolve_to( '93.184.216.34' );
		$this->responses[] = array(
			'translatedText' => array(
				"  Hola\x00\x07mundo  ",
				str_repeat( 'x', DSF_Translation_Extractor::MAX_SEGMENT_CHARS + 1 ),
			),
		);

		$result = $this->provider()->translate( array( 'a' => 'Hello', 'b' => 'World' ), 'en-US', 'es-MX' );

		$this->assertSame( 'Holamundo', $result['translations']['a'] );
		$this->assertSame( array( 'b' => 'missing_translation' ), $result['failed'] );
	}

	public function test_unusable_segments_are_rejected_before_any_request() {
		$this->resolve_to( '93.184.216.34' );

		$result = $this->provider()->translate(
			array(
				'a' => '   ',
				'b' => array( 'nested' ),
				'c' => str_repeat( 'x', DSF_Translation_Extractor::MAX_SEGMENT_CHARS + 1 ),
			),
			'en-US',
			'es-MX'
		);

		$this->assertSame( array(), $result['translations'] );
		$this->assertSame(
			array( 'a' => 'invalid_segment', 'b' => 'invalid_segment', 'c' => 'invalid_segment' ),
			$result['failed']
		);
		$this->assertSame( array(), $this->calls, 'Nothing usable means no request at all.' );
	}

	public function test_an_unconfigured_or_private_endpoint_never_reaches_the_network() {
		$this->resolve_to( '10.0.0.9' );

		$provider = $this->provider( array( 'endpoint' => 'https://intranet.example.com' ) );

		$this->assertFalse( $provider->is_configured() );
		$this->assertInstanceOf( 'WP_Error', $provider->check_health() );
		$this->assertInstanceOf( 'WP_Error', $provider->translate( array( 'a' => 'Hello' ), 'en-US', 'es-MX' ) );
		$this->assertSame( array(), $this->calls );
	}

	public function test_identical_or_uncurated_language_pairs_are_refused() {
		$this->resolve_to( '93.184.216.34' );
		$provider = $this->provider();

		$this->assertInstanceOf( 'WP_Error', $provider->translate( array( 'a' => 'Hello' ), 'en-US', 'en-GB' ) );
		$this->assertInstanceOf( 'WP_Error', $provider->translate( array( 'a' => 'Hello' ), 'en-US', 'xx-ZZ' ) );
		$this->assertSame( array(), $this->calls );
	}

	public function test_the_rate_limit_stops_further_requests_within_the_window() {
		$this->resolve_to( '93.184.216.34' );
		$this->transients['dsf_translation_rate_libretranslate'] = 2;

		$provider = $this->provider( array( 'rate_limit' => 2 ) );
		$result   = $provider->translate( array( 'a' => 'Hello' ), 'en-US', 'es-MX' );

		$this->assertSame( array( 'a' => 'rate_limited' ), $result['failed'] );
		$this->assertSame( array(), $this->calls );
		$this->assertInstanceOf( 'WP_Error', $provider->check_health() );
	}

	public function test_a_configured_api_key_is_sent_only_in_the_request_body() {
		$this->resolve_to( '93.184.216.34' );
		WP_Mock::userFunction( 'wp_salt', array( 'return' => 'unit-test-salt' ) );
		$this->responses[] = array( 'translatedText' => array( 'Hola' ) );

		$provider = $this->provider( array( 'api_key' => DSF_Crypto::encrypt( 'secret-key-1234' ) ) );
		$provider->translate( array( 'a' => 'Hello' ), 'en-US', 'es-MX' );

		$this->assertStringNotContainsString( 'secret-key-1234', $this->calls[0]['url'], 'A secret never travels in a URL.' );
	}
}

/**
 * Native URL parser used by the wp_parse_url() double.
 *
 * @param string $url       URL.
 * @param int    $component Component constant.
 * @return mixed
 */
function wp_parse_url_native( $url, $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}
