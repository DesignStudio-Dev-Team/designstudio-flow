<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// WP_Mock does not provide the core error value object. Keep this test double
// intentionally small and only define it outside a real WordPress test runtime.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-relationships.php';

/**
 * Queue-based wpdb double that also records every prepared statement.
 */
class DSF_Translation_Relationship_Test_DB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	public $query_results = array();
	public $row_results = array();
	public $list_results = array();
	public $prepared_calls = array();
	public $queries = array();
	public $row_queries = array();
	public $list_queries = array();

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function prepare( $query, ...$args ) {
		$this->prepared_calls[] = array(
			'query' => $query,
			'args'  => $args,
		);
		$index = 0;
		return preg_replace_callback(
			'/%([sd])/',
			static function ( $matches ) use ( $args, &$index ) {
				$value = isset( $args[ $index ] ) ? $args[ $index ] : null;
				$index++;
				if ( 'd' === $matches[1] ) {
					return (string) (int) $value;
				}
				return "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		return array_shift( $this->query_results );
	}

	public function get_row( $sql, $output ) {
		unset( $output );
		$this->row_queries[] = $sql;
		return array_shift( $this->row_results );
	}

	public function get_results( $sql, $output ) {
		unset( $output );
		$this->list_queries[] = $sql;
		return array_shift( $this->list_results );
	}
}

class Test_DSF_Translation_Relationships extends TestCase {
	private const GROUP_A = '11111111-2222-4333-8444-555555555555';
	private const GROUP_B = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

	/** @var DSF_Translation_Relationship_Test_DB */
	private $db;

	/** @var DSF_Translation_Relationships */
	private $service;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->db              = new DSF_Translation_Relationship_Test_DB();
		$this->service         = new DSF_Translation_Relationships( $this->db );
		WP_Mock::onFilter( 'dsf_translation_relationship_languages' )
			->with( array(), $this->service )
			->reply( array( 'en-US', 'es-MX', 'zh-Hant-TW' ) );
		WP_Mock::userFunction(
			'current_time',
			array(
				'return' => '2026-07-16 20:15:00',
			)
		);
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => static function ( $object_id ) {
					if ( 999 === (int) $object_id ) {
						return null;
					}
					return (object) array(
						'ID'        => (int) $object_id,
						'post_type' => 'page',
					);
				},
			)
		);
		WP_Mock::userFunction(
			'get_term',
			array(
				'return' => static function ( $object_id, $taxonomy ) {
					if ( 999 === (int) $object_id ) {
						return null;
					}
					return (object) array(
						'term_id'  => (int) $object_id,
						'taxonomy' => $taxonomy,
					);
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_schema_has_atomic_group_language_and_object_constraints() {
		$sql = implode( "\n", DSF_Translation_Relationships::schema_sql( $this->db ) );

		$this->assertStringContainsString( 'PRIMARY KEY  (group_uuid)', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY object_identity (object_kind,object_subtype,object_id)', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY group_language (group_uuid,language)', $sql );
		$this->assertStringContainsString( 'KEY language_lookup (language,object_kind,object_subtype)', $sql );
		$this->assertStringNotContainsString( 'ON DELETE CASCADE', $sql );
	}

	public function test_language_and_uuid_normalizers_are_bounded_and_canonical() {
		$this->assertSame( 'es-MX', DSF_Translation_Relationships::normalize_language_id( ' ES_mx ' ) );
		$this->assertSame( 'zh-Hant-TW', DSF_Translation_Relationships::normalize_language_id( 'zh-hant-tw' ) );
		$this->assertSame( '', DSF_Translation_Relationships::normalize_language_id( 'e' ) );
		$this->assertSame( '', DSF_Translation_Relationships::normalize_language_id( 'en-<script>' ) );
		$this->assertSame( self::GROUP_B, DSF_Translation_Relationships::normalize_group_uuid( strtoupper( self::GROUP_B ) ) );
		$this->assertSame( '', DSF_Translation_Relationships::normalize_group_uuid( 'not-a-uuid' ) );
	}

	public function test_adapter_registration_is_allowlisted_and_all_or_nothing() {
		$this->assertTrue( $this->service->register_adapter( 'term', array( 'pa_color', 'brand' ) ) );
		$this->assertContains( 'pa_color', $this->service->get_allowed_subtypes( 'term' ) );

		$result = $this->service->register_adapter( 'post', array( 'news', 'bad subtype!' ) );
		$this->assertErrorCode( 'dsf_translation_subtype', $result );
		$this->assertNotContains( 'news', $this->service->get_allowed_subtypes( 'post' ) );
	}

	public function test_create_group_uses_prepared_inserts_and_normalized_values() {
		$this->db->query_results = array( 1, 1 );
		$this->db->insert_id     = 91;
		WP_Mock::userFunction( 'wp_generate_uuid4', array( 'return' => strtoupper( self::GROUP_A ) ) );

		$result = $this->service->create_group( 'POST', 'PAGE', '42', 'es_mx' );

		$this->assertIsArray( $result );
		$this->assertSame( self::GROUP_A, $result['group_uuid'] );
		$this->assertSame( 'post', $result['object_kind'] );
		$this->assertSame( 'page', $result['object_subtype'] );
		$this->assertSame( 42, $result['object_id'] );
		$this->assertSame( 'es-MX', $result['language'] );
		$this->assertSame( 91, $result['id'] );
		$this->assertCount( 2, $this->db->queries );
		$this->assertStringContainsString( 'INSERT IGNORE INTO wp_dsf_translation_groups', $this->db->queries[0] );
		$this->assertStringContainsString( 'INSERT INTO wp_dsf_translation_relationships', $this->db->queries[1] );
		$this->assertCount( 2, $this->db->prepared_calls );
	}

	public function test_creation_rejects_unknown_or_mismatched_objects_before_sql() {
		$unknown_language = $this->service->add_member( self::GROUP_A, 'post', 'page', 42, 'fr-FR' );
		$this->assertErrorCode( 'dsf_translation_language', $unknown_language );

		$bad_subtype = $this->service->add_member( self::GROUP_A, 'post', 'page;drop_table', 42, 'en-US' );
		$this->assertErrorCode( 'dsf_translation_subtype', $bad_subtype );

		$missing = $this->service->add_member( self::GROUP_A, 'post', 'page', 999, 'en-US' );
		$this->assertErrorCode( 'dsf_translation_object_missing', $missing );
		$this->assertSame( array(), $this->db->queries );
	}

	public function test_existing_group_cannot_mix_object_adapters() {
		$this->db->query_results = array( 0 );
		$this->db->row_results   = array(
			array(
				'group_uuid'     => self::GROUP_A,
				'object_kind'    => 'term',
				'object_subtype' => 'category',
				'created_at_gmt' => '2026-07-16 20:00:00',
			),
		);

		$result = $this->service->add_member( self::GROUP_A, 'post', 'page', 42, 'en-US' );

		$this->assertErrorCode( 'dsf_translation_group_type', $result );
		$this->assertCount( 1, $this->db->queries );
	}

	public function test_exact_duplicate_after_unique_key_race_is_idempotent() {
		$this->db->query_results = array( 0, false );
		$this->db->row_results   = array(
			$this->group_row( self::GROUP_A, 'post', 'page' ),
			$this->member_row( 7, self::GROUP_A, 'post', 'page', 42, 'en-US' ),
		);

		$result = $this->service->add_member( self::GROUP_A, 'post', 'page', 42, 'en-US' );

		$this->assertIsArray( $result );
		$this->assertSame( 7, $result['id'] );
		$this->assertSame( self::GROUP_A, $result['group_uuid'] );
	}

	public function test_unique_object_conflict_is_classified_after_insert_failure() {
		$this->db->query_results = array( 0, false );
		$this->db->row_results   = array(
			$this->group_row( self::GROUP_A, 'post', 'page' ),
			$this->member_row( 8, self::GROUP_B, 'post', 'page', 42, 'es-MX' ),
		);

		$result = $this->service->add_member( self::GROUP_A, 'post', 'page', 42, 'en-US' );

		$this->assertErrorCode( 'dsf_translation_object_exists', $result );
	}

	public function test_unique_language_slot_conflict_is_classified_after_insert_failure() {
		$this->db->query_results = array( 0, false );
		$this->db->row_results   = array(
			$this->group_row( self::GROUP_A, 'post', 'page' ),
			null,
			$this->member_row( 9, self::GROUP_A, 'post', 'page', 77, 'en-US' ),
		);

		$result = $this->service->add_member( self::GROUP_A, 'post', 'page', 42, 'en-US' );

		$this->assertErrorCode( 'dsf_translation_language_exists', $result );
	}

	public function test_generated_uuid_collision_is_retried_without_joining_old_group() {
		$this->db->query_results = array( 0, 1, 1 );
		$this->db->row_results   = array( $this->group_row( self::GROUP_A, 'post', 'page' ) );
		$uuids                   = array( self::GROUP_A, self::GROUP_B );
		WP_Mock::userFunction(
			'wp_generate_uuid4',
			array(
				'return' => static function () use ( &$uuids ) {
					return array_shift( $uuids );
				},
			)
		);

		$result = $this->service->create_group( 'post', 'page', 42, 'en-US' );

		$this->assertIsArray( $result );
		$this->assertSame( self::GROUP_B, $result['group_uuid'] );
		$this->assertCount( 3, $this->db->queries );
	}

	public function test_synthetic_adapter_fails_closed_without_object_validator() {
		$blocked = $this->service->add_member( self::GROUP_A, 'synthetic', 'notification_bar', 1, 'en-US' );
		$this->assertErrorCode( 'dsf_translation_object_missing', $blocked );
		$this->assertSame( array(), $this->db->queries );
	}

	public function test_synthetic_adapter_can_explicitly_validate_its_object() {
		$identity = array(
			'object_kind'    => 'synthetic',
			'object_subtype' => 'notification_bar',
			'object_id'      => 1,
			'language'       => 'en-US',
		);
		WP_Mock::onFilter( 'dsf_translation_relationship_object_is_valid' )
			->with( false, $identity, $this->service )
			->reply( true );

		$this->db->query_results = array( 1, 1 );
		$created                 = $this->service->add_member( self::GROUP_A, 'synthetic', 'notification_bar', 1, 'en-US' );
		$this->assertIsArray( $created );
		$this->assertSame( 'synthetic', $created['object_kind'] );
	}

	public function test_find_and_list_use_bounded_prepared_reads() {
		$this->db->row_results = array( $this->member_row( 2, self::GROUP_A, 'post', 'page', 42, 'ES_mx' ) );
		$found                 = $this->service->find_by_object( 'post', 'page', 42 );
		$this->assertSame( 'es-MX', $found['language'] );

		$this->db->list_results = array(
			array(
				$this->member_row( 1, self::GROUP_A, 'post', 'page', 1, 'en-US' ),
				array( 'group_uuid' => '<corrupt>' ),
			),
		);
		$members = $this->service->list_group( self::GROUP_A );
		$this->assertCount( 1, $members );
		$this->assertStringContainsString( 'LIMIT 100', $this->db->list_queries[0] );
		$this->assertGreaterThanOrEqual( 2, count( $this->db->prepared_calls ) );
	}

	public function test_stored_language_members_can_be_detected_after_language_is_disabled() {
		$this->db->row_results = array( array( 'id' => 12 ), null );

		$this->assertTrue( $this->service->has_members_for_language( 'es_mx' ) );
		$this->assertFalse( $this->service->has_members_for_language( 'fr-FR' ) );
		$this->assertStringContainsString( "language = 'es-MX'", $this->db->row_queries[0] );
		$this->assertErrorCode( 'dsf_translation_language', $this->service->has_members_for_language( '<bad>' ) );
	}

	public function test_removing_member_never_deletes_group_or_siblings() {
		$this->db->query_results = array( 1 );

		$result = $this->service->remove_member( 'post', 'page', 42 );

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->db->queries );
		$this->assertStringContainsString( 'DELETE FROM wp_dsf_translation_relationships', $this->db->queries[0] );
		$this->assertStringNotContainsString( 'wp_dsf_translation_groups', $this->db->queries[0] );
	}

	private function assertErrorCode( $expected, $result ) {
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( $expected, $result->get_error_code() );
	}

	private function group_row( $uuid, $kind, $subtype ) {
		return array(
			'group_uuid'     => $uuid,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'created_at_gmt' => '2026-07-16 20:00:00',
		);
	}

	private function member_row( $id, $uuid, $kind, $subtype, $object_id, $language ) {
		return array(
			'id'               => $id,
			'group_uuid'       => $uuid,
			'object_kind'      => $kind,
			'object_subtype'   => $subtype,
			'object_id'        => $object_id,
			'language'         => $language,
			'created_at_gmt'   => '2026-07-16 20:00:00',
			'updated_at_gmt'   => '2026-07-16 20:00:00',
		);
	}
}
