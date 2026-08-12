<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-relationships.php';

/** Relationship double recording group membership changes. */
class DSF_Portability_Test_Relationships {
	public $members      = array();
	public $created      = array();
	public $removed      = array();
	public $add_response = null;

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

	public function add_member( $group, $kind, $subtype, $id, $language ) {
		if ( null !== $this->add_response ) {
			return $this->add_response;
		}
		$this->add( $group, $subtype, $id, $language );
		return end( $this->members );
	}

	public function create_group( $kind, $subtype, $id, $language ) {
		unset( $kind );
		$group           = 'new-group-' . count( $this->created );
		$this->created[] = array( $group, $subtype, (int) $id, $language );
		$this->add( $group, $subtype, $id, $language );
		return end( $this->members );
	}

	public function remove_member( $kind, $subtype, $id ) {
		$this->removed[] = array( $kind, $subtype, (int) $id );
		foreach ( $this->members as $index => $member ) {
			if ( $member['object_kind'] === $kind && $member['object_subtype'] === $subtype && $member['object_id'] === (int) $id ) {
				unset( $this->members[ $index ] );
				$this->members = array_values( $this->members );
				return true;
			}
		}
		return false;
	}
}

/** Coordinator double exposing the relationship service. */
class DSF_Portability_Test_Coordinator {
	public static $relationships = null;

	public static function get_instance() {
		return new self();
	}

	public function get_relationships() {
		return self::$relationships;
	}
}

/**
 * Covers portable translation membership across export and import: a group must
 * survive a site move without numeric IDs, and must never silently overwrite a
 * translation that already exists on the destination.
 */
class Test_DSF_Translation_Portability extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	/** @var DSF_Portability_Test_Relationships */
	private $relationships;

	/** @var array<int,object> */
	private $posts = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->relationships                             = new DSF_Portability_Test_Relationships();
		DSF_Portability_Test_Coordinator::$relationships = $this->relationships;

		$this->posts = array(
			10 => (object) array(
				'ID'        => 10,
				'post_type' => 'page',
			),
			11 => (object) array(
				'ID'        => 11,
				'post_type' => 'page',
			),
			12 => (object) array(
				'ID'        => 12,
				'post_type' => 'page',
			),
		);

		$posts = &$this->posts;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
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
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => static function ( $id = 0 ) use ( &$posts ) {
					$id = is_object( $id ) ? $id->ID : (int) $id;
					return $posts[ $id ] ?? null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) {
					if ( DSF_Multilingual_Settings::OPTION_NAME === $key ) {
						return array(
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
							),
						);
					}
					return $default;
				},
			)
		);

		require_once dirname( __DIR__ ) . '/includes/class-dsf-import-export.php';
	}

	public function tearDown(): void {
		DSF_Portability_Test_Coordinator::$relationships = null;
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_export_carries_portable_group_identity_not_ids() {
		$this->relationships->add( self::GROUP, 'page', 10, 'es-MX' );

		$identity = DSF_Import_Export::export_translation_identity( $this->posts[10], $this->relationships );

		$this->assertSame(
			array(
				'group_uuid' => self::GROUP,
				'language'   => 'es-MX',
			),
			$identity
		);
		$this->assertArrayNotHasKey( 'object_id', $identity );
		$this->assertArrayNotHasKey( 'reviewer_id', $identity, 'A review is earned per site, never imported.' );
	}

	public function test_an_unassigned_object_exports_no_translation_block() {
		$this->assertSame( array(), DSF_Import_Export::export_translation_identity( $this->posts[10], $this->relationships ) );
		$this->assertSame( array(), DSF_Import_Export::export_translation_identity( null, $this->relationships ) );
	}

	public function test_import_rejoins_the_original_group_and_drops_the_default_assignment() {
		// wp_insert_post() assigned the new object to the main language first.
		$this->relationships->add( 'auto-group', 'page', 11, 'en-US' );

		$restored = DSF_Import_Export::restore_translation_identity(
			11,
			array(
				'group_uuid' => self::GROUP,
				'language'   => 'es-MX',
			),
			$this->relationships
		);

		$this->assertTrue( $restored );
		$this->assertSame( array( array( 'post', 'page', 11 ) ), $this->relationships->removed );
		$this->assertSame( self::GROUP, $this->relationships->find_by_object( 'post', 'page', 11 )['group_uuid'] );
		$this->assertSame( 'es-MX', $this->relationships->find_by_object( 'post', 'page', 11 )['language'] );
	}

	public function test_an_occupied_language_slot_is_never_overwritten() {
		$this->relationships->add( self::GROUP, 'page', 10, 'es-MX' );

		$restored = DSF_Import_Export::restore_translation_identity(
			11,
			array(
				'group_uuid' => self::GROUP,
				'language'   => 'es-MX',
			),
			$this->relationships
		);

		$this->assertTrue( $restored );
		$this->assertCount( 1, $this->relationships->created, 'The import becomes its own group instead of replacing a reviewed translation.' );
		$this->assertSame( 10, $this->relationships->find_member( self::GROUP, 'es-MX' )['object_id'] );
		$this->assertNotSame( self::GROUP, $this->relationships->find_by_object( 'post', 'page', 11 )['group_uuid'] );
	}

	public function test_a_lost_race_still_lands_the_object_in_a_valid_group() {
		$this->relationships->add_response = new WP_Error( 'dsf_translation_duplicate', 'duplicate' );

		$restored = DSF_Import_Export::restore_translation_identity(
			12,
			array(
				'group_uuid' => self::GROUP,
				'language'   => 'es-MX',
			),
			$this->relationships
		);

		$this->assertTrue( $restored );
		$this->assertCount( 1, $this->relationships->created );
	}

	public function test_malformed_and_disabled_identities_are_refused() {
		$cases = array(
			array(
				'group_uuid' => self::GROUP,
				'language'   => 'de-DE',
			),
			array(
				'group_uuid' => self::GROUP,
				'language'   => '<script>',
			),
			array(
				'group_uuid' => 'not-a-uuid',
				'language'   => 'es-MX',
			),
			array(
				'group_uuid' => '',
				'language'   => '',
			),
			array(),
			'garbage',
		);

		foreach ( $cases as $case ) {
			$this->assertFalse(
				DSF_Import_Export::restore_translation_identity( 11, $case, $this->relationships ),
				is_array( $case ) ? wp_json_encode_portability( $case ) : (string) $case
			);
		}
		$this->assertSame( array(), $this->relationships->created );
	}

	public function test_a_missing_object_is_refused() {
		$this->assertFalse(
			DSF_Import_Export::restore_translation_identity(
				999,
				array(
					'group_uuid' => self::GROUP,
					'language'   => 'es-MX',
				),
				$this->relationships
			)
		);
	}
}

/**
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_portability( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test helper outside WordPress.
}
