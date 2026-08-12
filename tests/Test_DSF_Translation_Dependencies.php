<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-dependencies.php';

/** Minimal wpdb recorder for dependency persistence tests. */
class DSF_Test_Dependencies_DB {
	public $prefix = 'wp_';
	public $queries = array();
	public $prepared = array();
	public $rows = array();
	public $fail_on = '';

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function prepare( $query, ...$args ) {
		$this->prepared[] = array(
			'query' => $query,
			'args'  => $args,
		);
		return $query . ' /* prepared */';
	}

	public function query( $query ) {
		$this->queries[] = $query;
		return $this->fail_on && false !== strpos( $query, $this->fail_on ) ? false : 1;
	}

	public function get_results( $query, $output ) {
		$this->queries[] = $query;
		return $this->rows;
	}
}

/** Graph fixture that isolates traversal from a database implementation. */
class DSF_Testable_Translation_Dependencies extends DSF_Translation_Dependencies {
	public $graph = array();

	public function get_edges( $owner_group_uuid, $language ) {
		return $this->graph[ $owner_group_uuid ] ?? array();
	}
}

class Test_DSF_Translation_Dependencies extends TestCase {

	private const A = 'aaaaaaaa-1111-4111-8111-111111111111';
	private const B = 'bbbbbbbb-2222-4222-8222-222222222222';
	private const C = 'cccccccc-3333-4333-8333-333333333333';
	private const D = 'dddddddd-4444-4444-8444-444444444444';

	public function test_edge_normalization_rebuilds_known_fields_and_canonicalizes_language() {
		$service = $this->service();
		$edge    = $service->normalize_edge(
			self::A,
			'es-mx',
			array(
				'dependency_group_uuid' => strtoupper( self::B ),
				'dependency_kind'       => 'layout_header',
				'path'                  => 'settings.layout.headerTemplateId',
				'required'              => '1',
				'unknown'               => '<script>ignored</script>',
			)
		);

		$this->assertSame( 'es-MX', $edge['language'] );
		$this->assertSame( self::B, $edge['dependency_group_uuid'] );
		$this->assertSame( 'layout_header', $edge['kind'] );
		$this->assertTrue( $edge['required'] );
		$this->assertArrayNotHasKey( 'unknown', $edge );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $edge['edge_key'] );
	}

	public function test_edge_validation_rejects_unknown_malformed_and_self_dependencies() {
		$service = $this->service();
		$base    = array(
			'dependency_group_uuid' => self::B,
			'kind'                  => 'form',
			'source_path'           => 'blocks.0.settings.formId',
			'required'              => true,
		);

		$this->assertSame( 'dsf_dependency_language_disabled', $service->normalize_edge( self::A, 'fr', $base )->get_error_code() );
		$this->assertSame( 'dsf_dependency_self', $service->normalize_edge( self::A, 'es-MX', array_replace( $base, array( 'dependency_group_uuid' => self::A ) ) )->get_error_code() );
		$this->assertSame( 'dsf_dependency_kind', $service->normalize_edge( self::A, 'es-MX', array_replace( $base, array( 'kind' => 'arbitrary_remote_kind' ) ) )->get_error_code() );
		$this->assertSame( 'dsf_dependency_path', $service->normalize_edge( self::A, 'es-MX', array_replace( $base, array( 'source_path' => '<script>alert(1)</script>' ) ) )->get_error_code() );
		$this->assertSame( 'dsf_dependency_required', $service->normalize_edge( self::A, 'es-MX', array_replace( $base, array( 'required' => 'yes' ) ) )->get_error_code() );
	}

	public function test_schema_has_database_unique_edge_and_lookup_indexes() {
		$db     = new DSF_Test_Dependencies_DB();
		$schema = DSF_Translation_Dependencies::schema_sql( $db );

		$this->assertStringContainsString( 'UNIQUE KEY edge_key (edge_key)', $schema );
		$this->assertStringContainsString( 'KEY owner_language (owner_group_uuid,language,required)', $schema );
		$this->assertStringContainsString( 'KEY dependency_language (dependency_group_uuid,language)', $schema );
	}

	public function test_default_table_tracks_multisite_prefix_changes() {
		$db      = new DSF_Test_Dependencies_DB();
		$service = $this->service( array( 'wpdb' => $db ) );

		$service->get_edges( self::A, 'es-MX' );
		$db->prefix = 'wp_7_';
		$service->get_edges( self::A, 'es-MX' );

		$this->assertStringContainsString( 'FROM wp_dsf_translation_dependencies', $db->prepared[0]['query'] );
		$this->assertStringContainsString( 'FROM wp_7_dsf_translation_dependencies', $db->prepared[1]['query'] );
	}

	public function test_replace_edges_requires_strict_authorization_and_merges_duplicates_required_first() {
		$db      = new DSF_Test_Dependencies_DB();
		$service = $this->service( array( 'wpdb' => $db, 'table_name' => 'wp_dsf_translation_dependencies' ) );
		$edge    = array(
			'dependency_group_uuid' => self::B,
			'kind'                  => 'form',
			'source_path'           => 'blocks.0.settings.formId',
			'required'              => false,
		);

		$forbidden = $service->replace_edges( self::A, 'es-MX', array( $edge ), static function () {
			return 1;
		} );
		$this->assertSame( 'dsf_dependency_forbidden', $forbidden->get_error_code() );
		$this->assertSame( array(), $db->queries );

		$count = $service->replace_edges( self::A, 'es-MX', array( $edge, array_replace( $edge, array( 'required' => true ) ) ), static function () {
			return true;
		} );
		$this->assertSame( 1, $count );
		$this->assertSame( 'START TRANSACTION', $db->queries[0] );
		$this->assertSame( 'COMMIT', end( $db->queries ) );
		$this->assertCount( 2, $db->prepared );
		$this->assertSame( 1, $db->prepared[1]['args'][6] );
	}

	public function test_replace_edges_rolls_back_failed_insert() {
		$db          = new DSF_Test_Dependencies_DB();
		$db->fail_on = 'INSERT INTO';
		$service     = $this->service( array( 'wpdb' => $db, 'table_name' => 'wp_dsf_translation_dependencies' ) );
		$result      = $service->replace_edges(
			self::A,
			'es-MX',
			array( $this->edge( self::B, true ) ),
			static function () {
				return true;
			}
		);

		$this->assertSame( 'dsf_dependency_database', $result->get_error_code() );
		$this->assertSame( 'ROLLBACK', end( $db->queries ) );
	}

	public function test_same_language_closure_allows_optional_missing_member() {
		$members = array(
			self::B => array( 'id' => 2, 'language' => 'es-MX', 'eligible' => true ),
			self::D => array( 'id' => 4, 'language' => 'es-MX', 'eligible' => true ),
		);
		$service = $this->graph_service( $members );
		$service->graph = array(
			self::A => array( $this->edge( self::B, true, 'header' ), $this->edge( self::C, false, 'popup' ) ),
			self::B => array( $this->edge( self::D, true, 'form' ) ),
		);

		$result = $service->evaluate_closure( self::A, 'es-MX' );
		$this->assertTrue( $result['eligible'] );
		$this->assertSame( 3, $result['checked_edges'] );
		$this->assertCount( 2, $result['resolved'] );
		$this->assertCount( 1, $result['optional_unavailable'] );
		$this->assertSame( 'es-MX', $result['optional_unavailable'][0]['language'] );
	}

	public function test_optional_subgraph_error_and_edge_limit_never_block_owner() {
		$members = array(
			self::B => array( 'language' => 'es-MX', 'eligible' => true ),
			self::C => array( 'language' => 'es-MX', 'eligible' => true ),
		);
		$error_service        = $this->graph_service( $members );
		$error_service->graph = array(
			self::A => array( $this->edge( self::B, false ) ),
			self::B => new WP_Error( 'database', 'Unavailable' ),
		);

		$error_result = $error_service->evaluate_closure( self::A, 'es-MX' );
		$this->assertTrue( $error_result['eligible'] );
		$this->assertSame( 'graph_unavailable', $error_result['optional_unavailable'][0]['reason'] );

		$limit_service        = $this->graph_service( $members, array( 'max_graph_edges' => 1 ) );
		$limit_service->graph = array(
			self::A => array( $this->edge( self::B, false ) ),
			self::B => array( $this->edge( self::C, true ) ),
		);

		$limit_result = $limit_service->evaluate_closure( self::A, 'es-MX' );
		$this->assertTrue( $limit_result['eligible'] );
		$this->assertTrue( $limit_result['truncated'] );
		$this->assertSame( 'edge_limit', $limit_result['optional_unavailable'][0]['reason'] );
	}

	public function test_required_missing_mismatch_and_ineligible_members_fail_closed() {
		$missing_service        = $this->graph_service( array() );
		$missing_service->graph = array( self::A => array( $this->edge( self::B, true ) ) );
		$missing                = $missing_service->evaluate_closure( self::A, 'es-MX' );
		$this->assertFalse( $missing['eligible'] );
		$this->assertSame( 'missing_same_language_member', $missing['missing'][0]['reason'] );

		$mismatch_service        = $this->graph_service( array( self::B => array( 'language' => 'fr', 'eligible' => true ) ) );
		$mismatch_service->graph = array( self::A => array( $this->edge( self::B, true ) ) );
		$mismatch                = $mismatch_service->evaluate_closure( self::A, 'es-MX' );
		$this->assertFalse( $mismatch['eligible'] );
		$this->assertSame( 'language_mismatch', $mismatch['ineligible'][0]['reason'] );

		$bad_service        = $this->graph_service( array( self::B => array( 'language' => 'es-MX', 'eligible' => false, 'reason' => 'route invalid' ) ) );
		$bad_service->graph = array( self::A => array( $this->edge( self::B, true ) ) );
		$bad                = $bad_service->evaluate_closure( self::A, 'es-MX' );
		$this->assertFalse( $bad['eligible'] );
		$this->assertSame( 'route_invalid', $bad['ineligible'][0]['reason'] );
	}

	public function test_required_cycle_is_detected_without_unbounded_recursion() {
		$members = array(
			self::A => array( 'language' => 'es-MX', 'eligible' => true ),
			self::B => array( 'language' => 'es-MX', 'eligible' => true ),
		);
		$service = $this->graph_service( $members );
		$service->graph = array(
			self::A => array( $this->edge( self::B, true ) ),
			self::B => array( $this->edge( self::A, true ) ),
		);

		$result = $service->evaluate_closure( self::A, 'es-MX' );
		$this->assertFalse( $result['eligible'] );
		$this->assertCount( 1, $result['cycles'] );
		$this->assertSame( 'cycle', $result['cycles'][0]['reason'] );
		$this->assertLessThanOrEqual( 2, $result['checked_edges'] );
	}

	public function test_cycle_detection_is_not_hidden_by_a_diamond_graph() {
		$members = array(
			self::B => array( 'language' => 'es-MX', 'eligible' => true ),
			self::C => array( 'language' => 'es-MX', 'eligible' => true ),
			self::D => array( 'language' => 'es-MX', 'eligible' => true ),
		);
		$service = $this->graph_service( $members );
		$service->graph = array(
			self::A => array( $this->edge( self::B, true ), $this->edge( self::C, true ) ),
			self::B => array( $this->edge( self::D, true ) ),
			self::C => array( $this->edge( self::D, true ) ),
			self::D => array( $this->edge( self::C, true ) ),
		);

		$result = $service->evaluate_closure( self::A, 'es-MX' );
		$this->assertFalse( $result['eligible'] );
		$this->assertNotEmpty( $result['cycles'] );
	}

	public function test_bounded_traversal_fails_closed_for_required_branch() {
		$members = array(
			self::B => array( 'language' => 'es-MX', 'eligible' => true ),
			self::C => array( 'language' => 'es-MX', 'eligible' => true ),
		);
		$service = $this->graph_service( $members, array( 'max_depth' => 1 ) );
		$service->graph = array(
			self::A => array( $this->edge( self::B, true ) ),
			self::B => array( $this->edge( self::C, true ) ),
		);

		$result = $service->evaluate_closure( self::A, 'es-MX' );
		$this->assertTrue( $result['truncated'] );
		$this->assertFalse( $result['eligible'] );
		$this->assertSame( 'depth_limit', $result['ineligible'][0]['reason'] );
	}

	public function test_evaluation_requires_both_exact_language_resolvers() {
		$service = $this->service();
		$this->assertSame( 'dsf_dependency_resolver', $service->evaluate_closure( self::A, 'es-MX' )->get_error_code() );
	}

	public function test_identity_operations_fail_closed_without_enabled_language_validator() {
		$service = new DSF_Translation_Dependencies();
		$this->assertSame( 'dsf_dependency_language_validator', $service->evaluate_closure( self::A, 'es-MX' )->get_error_code() );
	}

	private function service( $extra = array() ) {
		return new DSF_Translation_Dependencies(
			array_replace(
				array(
					'language_validator' => static function ( $language ) {
						return 'es-MX' === $language;
					},
				),
				$extra
			)
		);
	}

	private function graph_service( $members, $extra = array() ) {
		return new DSF_Testable_Translation_Dependencies(
			array_replace(
				array(
					'language_validator' => static function ( $language ) {
						return 'es-MX' === $language;
					},
					'member_resolver' => static function ( $group_uuid, $language ) use ( $members ) {
						return $members[ $group_uuid ] ?? null;
					},
					'eligibility_resolver' => static function ( $member ) {
						return array(
							'eligible' => isset( $member['eligible'] ) && true === $member['eligible'],
							'reason'   => $member['reason'] ?? 'not_publishable',
						);
					},
				),
				$extra
			)
		);
	}

	private function edge( $dependency_group_uuid, $required, $kind = 'form' ) {
		return array(
			'edge_key'                 => hash( 'sha256', self::A . $dependency_group_uuid . $kind ),
			'owner_group_uuid'         => self::A,
			'language'                 => 'es-MX',
			'dependency_group_uuid'    => $dependency_group_uuid,
			'kind'                      => $kind,
			'source_path'               => 'blocks.0.settings.reference',
			'required'                  => (bool) $required,
		);
	}
}
