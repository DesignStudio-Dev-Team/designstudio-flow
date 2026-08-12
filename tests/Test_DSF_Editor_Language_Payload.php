<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-context.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-editor.php';

/** Relationship double for editor payload tests. */
class DSF_Editor_Payload_Test_Relationships {
	public $members = array();

	public function add( $group, $subtype, $id, $language ) {
		$this->members[] = array(
			'group_uuid'     => $group,
			'object_kind'    => 'post',
			'object_subtype' => $subtype,
			'object_id'      => (int) $id,
			'language'       => $language,
		);
	}

	public function find_by_object( $kind, $subtype, $id ) {
		foreach ( $this->members as $member ) {
			if ( $member['object_kind'] === $kind && $member['object_subtype'] === $subtype && $member['object_id'] === (int) $id ) {
				return $member;
			}
		}
		return null;
	}

	public function find_member( $group, $language ) {
		foreach ( $this->members as $member ) {
			if ( $member['group_uuid'] === $group && $member['language'] === $language ) {
				return $member;
			}
		}
		return null;
	}
}

/**
 * Covers the language payload the editor dock reads.
 *
 * The control has to appear on its own from the site's language settings, and
 * has to disappear whenever the server would refuse what it offers.
 */
class Test_DSF_Editor_Language_Payload extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	/** @var DSF_Editor_Payload_Test_Relationships */
	private $relationships;

	/** @var array<int,object> */
	private $posts = array();

	/** @var array<string,mixed> */
	private $settings = array();

	/** @var bool */
	private $conflicts = false;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->relationships = new DSF_Editor_Payload_Test_Relationships();

		$this->conflicts = false;
		$this->settings  = array(
			'enabled'           => true,
			'main_language'     => 'en-US',
			'migration_state'   => 'complete',
			'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
			'languages'         => array(
				array(
					'code'   => 'en-US',
					'prefix' => '',
				),
				array(
					'code'   => 'es-MX',
					'prefix' => 'es',
				),
				array(
					'code'   => 'fr',
					'prefix' => 'fr',
				),
			),
		);

		$this->posts = array(
			10 => (object) array(
				'ID'          => 10,
				'post_type'   => 'page',
				'post_status' => 'publish',
			),
			11 => (object) array(
				'ID'          => 11,
				'post_type'   => 'page',
				'post_status' => 'draft',
			),
			20 => (object) array(
				'ID'          => 20,
				'post_type'   => 'dsf_entry',
				'post_status' => 'publish',
			),
		);

		$posts    = &$this->posts;
		$settings = &$this->settings;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'is_admin', array( 'return' => true ) );
		WP_Mock::userFunction( 'is_rtl', array( 'return' => false ) );
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $v ) {
					return abs( (int) $v );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $v ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) );
				},
			)
		);
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'wp_create_nonce', array( 'return' => 'nonce-value' ) );
		WP_Mock::userFunction( 'get_edit_post_link', array( 'return' => 'https://example.test/edit' ) );
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => static function ( $id = 0 ) use ( &$posts ) {
					return $posts[ (int) $id ] ?? null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_status',
			array(
				'return' => static function ( $id = 0 ) use ( &$posts ) {
					return isset( $posts[ (int) $id ] ) ? $posts[ (int) $id ]->post_status : false;
				},
			)
		);
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) use ( &$settings ) {
					return DSF_Multilingual_Settings::OPTION_NAME === $key ? $settings : $default;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Build the payload with a language context wired to these settings.
	 *
	 * @param int $post_id Post being edited.
	 * @return array
	 */
	private function payload( $post_id ) {
		$settings  = &$this->settings;
		$conflicts = &$this->conflicts;

		$context = new DSF_Language_Context(
			array(
				'settings_reader'   => static function () use ( &$settings ) {
					return DSF_Multilingual_Settings::sanitize_settings( $settings );
				},
				'conflict_detector' => static function () use ( &$conflicts ) {
					return $conflicts;
				},
			)
		);

		$shared = new ReflectionProperty( 'DSF_Language_Context', 'instance' );
		$shared->setAccessible( true );
		$previous = $shared->getValue();
		$shared->setValue( null, $context );

		$reflection = new ReflectionClass( 'DSF_Editor' );
		$editor     = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'get_translation_payload' );
		$method->setAccessible( true );
		$payload = $method->invoke( $editor, $post_id, $this->relationships );

		$shared->setValue( null, $previous );
		return $payload;
	}

	public function test_the_control_lists_exactly_the_enabled_languages() {
		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );

		$payload = $this->payload( 10 );

		$this->assertTrue( $payload['active'] );
		$this->assertSame( array( 'en-US', 'es-MX', 'fr' ), array_column( $payload['languages'], 'code' ) );
		$this->assertSame( array( 'draft', 'missing' ), array_column( array_slice( $payload['languages'], 1 ), 'state' ) );
		$this->assertTrue( $payload['canClone'] );
		$this->assertSame( 'en-US', $payload['current'] );
	}

	public function test_adding_a_language_in_settings_adds_it_to_the_control() {
		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );
		$this->assertCount( 3, $this->payload( 10 )['languages'] );

		$this->settings['languages'][] = array(
			'code'   => 'de',
			'prefix' => 'de',
		);
		$payload                       = $this->payload( 10 );

		$this->assertSame( array( 'en-US', 'es-MX', 'fr', 'de' ), array_column( $payload['languages'], 'code' ) );
	}

	public function test_an_unsaved_page_still_lists_the_languages() {
		// No relationship row exists yet, which is the state of a page that has
		// never been saved.
		$payload = $this->payload( 10 );

		$this->assertTrue( $payload['active'] );
		$this->assertSame( 'en-US', $payload['current'] );
		$this->assertCount( 3, $payload['languages'] );
		$this->assertFalse( $payload['canClone'], 'There is no group to clone from yet.' );
		$this->assertNotSame( '', $payload['notice'] );
	}

	public function test_a_translation_reports_itself_and_cannot_clone() {
		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );

		$payload = $this->payload( 11 );

		$this->assertSame( 'es-MX', $payload['current'] );
		$this->assertFalse( $payload['isMain'] );
		$this->assertFalse( $payload['canClone'] );
		$this->assertNotSame( '', $payload['notice'] );
	}

	public function test_a_paused_setup_keeps_the_control_but_explains_itself() {
		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );

		// A conflicting multilingual plugin.
		$this->conflicts = true;
		$payload         = $this->payload( 10 );
		$this->assertTrue( $payload['active'], 'The control stays visible so the reason can be shown.' );
		$this->assertFalse( $payload['ready'] );
		$this->assertFalse( $payload['canClone'] );
		$this->assertStringContainsString( 'multilingual plugin', $payload['notice'] );
		$this->conflicts = false;

		// Setup stopped part way.
		$this->settings['migration_state'] = 'failed';
		$payload                           = $this->payload( 10 );
		$this->assertTrue( $payload['active'] );
		$this->assertFalse( $payload['canClone'] );
		$this->assertStringContainsString( 'Settings', $payload['notice'] );

		// Still running.
		$this->settings['migration_state'] = 'running';
		$payload                           = $this->payload( 10 );
		$this->assertTrue( $payload['active'] );
		$this->assertFalse( $payload['ready'] );
		$this->settings['migration_state'] = 'complete';

		$ready = $this->payload( 10 );
		$this->assertTrue( $ready['ready'] );
		$this->assertTrue( $ready['canClone'] );
		$this->assertSame( '', $ready['notice'] );
	}

	public function test_the_control_is_absent_when_there_is_nothing_to_switch_between() {
		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );

		$this->settings['enabled'] = false;
		$this->assertFalse( $this->payload( 10 )['active'] );
		$this->settings['enabled'] = true;

		$this->settings['languages'] = array(
			array(
				'code'   => 'en-US',
				'prefix' => '',
			),
		);
		$this->assertFalse( $this->payload( 10 )['active'] );
	}

	public function test_object_types_that_are_not_translated_get_no_control() {
		$this->assertFalse( $this->payload( 20 )['active'] );
		$this->assertFalse( $this->payload( 999 )['active'] );
	}
}
