<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-html.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-extractor.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-reassembler.php';
require_once dirname( __DIR__ ) . '/includes/interface-dsf-translation-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-providers.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-machine.php';

/** Extractor double returning a fixed segment list. */
class DSF_Machine_Test_Extractor extends DSF_Translation_Extractor {
	public $segments  = array();
	public $truncated = false;
	public $error     = null;

	public function extract_member( $member ) {
		unset( $member );
		if ( $this->error ) {
			return $this->error;
		}
		return array(
			'segments'  => $this->segments,
			'truncated' => $this->truncated,
		);
	}
}

/** Reassembler double recording what it was asked to apply. */
class DSF_Machine_Test_Reassembler extends DSF_Translation_Reassembler {
	public $received = null;

	public function apply_to_member( $member, $translations ) {
		unset( $member );
		$this->received = $translations;
		return array(
			'document' => array( 'applied' => $translations ),
			'applied'  => count( $translations ),
			'rejected' => 0,
		);
	}
}

/** Provider double with scripted behavior. */
class DSF_Machine_Test_Provider implements DSF_Translation_Provider {
	public $configured   = true;
	public $result       = null;
	public $received     = null;

	public function get_id() {
		return 'test-provider';
	}

	public function get_label() {
		return 'Test provider';
	}

	public function is_configured() {
		return $this->configured;
	}

	public function check_health() {
		return array( 'languages' => array( 'en', 'es' ) );
	}

	public function translate( $segments, $source_language, $target_language ) {
		$this->received = array( $segments, $source_language, $target_language );
		return $this->result;
	}
}

/**
 * Covers machine-translation orchestration: what is sent, what comes back, and
 * how a provider failure degrades.
 */
class Test_DSF_Translation_Machine extends TestCase {

	/** @var DSF_Machine_Test_Extractor */
	private $extractor;

	/** @var DSF_Machine_Test_Reassembler */
	private $reassembler;

	/** @var DSF_Machine_Test_Provider */
	private $provider;

	/** @var DSF_Translation_Machine */
	private $machine;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );

		$this->extractor   = new DSF_Machine_Test_Extractor();
		$this->reassembler = new DSF_Machine_Test_Reassembler();
		$this->provider    = new DSF_Machine_Test_Provider();
		$provider          = $this->provider;

		$this->machine = new DSF_Translation_Machine(
			array(
				'extractor'         => $this->extractor,
				'reassembler'       => $this->reassembler,
				'provider_resolver' => static function () use ( $provider ) {
					return $provider;
				},
			)
		);

		$this->extractor->segments = array(
			array( 'path' => 'post_title', 'node' => null, 'format' => 'text', 'text' => 'Hello' ),
			array( 'path' => 'blocks.0.settings.body', 'node' => 2, 'format' => 'html', 'text' => 'World' ),
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/** @return array */
	private function member() {
		return array(
			'group_uuid'     => '11111111-2222-4333-8444-555555555555',
			'object_kind'    => 'post',
			'object_subtype' => 'page',
			'object_id'      => 12,
			'language'       => 'es-MX',
		);
	}

	public function test_only_extracted_segment_text_is_sent_to_the_provider() {
		$this->provider->result = array(
			'translations' => array( 0 => 'Hola', 1 => 'Mundo' ),
			'failed'       => array(),
		);

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertSame( array( 'Hello', 'World' ), $this->provider->received[0] );
		$this->assertSame( 'en-US', $this->provider->received[1] );
		$this->assertSame( 'es-MX', $this->provider->received[2] );
		$this->assertSame( 2, $result['translated'] );

		// The reassembler receives the original paths, not provider data.
		$this->assertSame( 'post_title', $this->reassembler->received[0]['path'] );
		$this->assertSame( 'Hola', $this->reassembler->received[0]['text'] );
		$this->assertSame( 2, $this->reassembler->received[1]['node'] );
		$this->assertSame( 'Mundo', $this->reassembler->received[1]['text'] );
	}

	public function test_partial_failures_are_reported_and_the_rest_still_applies() {
		$this->provider->result = array(
			'translations' => array( 0 => 'Hola' ),
			'failed'       => array( 1 => 'request_failed' ),
		);

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertSame( 1, $result['translated'] );
		$this->assertSame( array( 1 => 'request_failed' ), $result['failed'] );
		$this->assertCount( 1, $this->reassembler->received );
	}

	public function test_provider_output_for_unknown_indexes_is_discarded() {
		$this->provider->result = array(
			'translations' => array(
				0    => 'Hola',
				99   => 'Injected',
				'x'  => 'Injected',
				1    => array( 'nested' ),
			),
			'failed'       => array(),
		);

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertCount( 1, $this->reassembler->received );
		$this->assertSame( 'post_title', $this->reassembler->received[0]['path'] );
		$this->assertSame( 1, $result['translated'] );
	}

	public function test_an_unavailable_provider_never_blocks_manual_translation() {
		$machine = new DSF_Translation_Machine(
			array(
				'extractor'         => $this->extractor,
				'reassembler'       => $this->reassembler,
				'provider_resolver' => static function () {
					return null;
				},
			)
		);

		$result = $machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_translation_provider_missing', $result->get_error_code() );
		$this->assertFalse( $machine->is_available() );
		$this->assertNull( $this->reassembler->received, 'Nothing is written when no service answered.' );
	}

	public function test_a_misconfigured_provider_is_refused_before_extraction() {
		$this->provider->configured = false;

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_translation_provider_config', $result->get_error_code() );
		$this->assertNull( $this->provider->received );
	}

	public function test_provider_errors_are_returned_without_touching_content() {
		$this->provider->result = new WP_Error( 'dsf_translation_unreachable', 'unreachable' );

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertNull( $this->reassembler->received );
	}

	public function test_invalid_language_pairs_are_refused() {
		foreach ( array( array( 'en-US', 'en-US' ), array( 'en-US', 'xx-ZZ' ), array( '', 'es-MX' ) ) as $pair ) {
			$result = $this->machine->prefill( $this->member(), $pair[0], $pair[1] );
			$this->assertInstanceOf( 'WP_Error', $result );
			$this->assertSame( 'dsf_translation_pair', $result->get_error_code() );
		}
		$this->assertNull( $this->provider->received );
	}

	public function test_nothing_to_translate_is_not_an_error() {
		$this->extractor->segments = array();

		$result = $this->machine->prefill( $this->member(), 'en-US', 'es-MX' );

		$this->assertSame( 0, $result['translated'] );
		$this->assertSame( array(), $result['failed'] );
		$this->assertNull( $this->provider->received );
	}
}
