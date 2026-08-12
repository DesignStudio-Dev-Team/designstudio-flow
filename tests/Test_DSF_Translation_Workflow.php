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

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-workflow.php';

/** Minimal wpdb recorder for isolated workflow persistence tests. */
class DSF_Test_Workflow_DB {
	public $prefix = 'wp_';
	public $queries = array();
	public $prepared = array();
	public $row = null;
	public $fail_queries = false;

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
		return $this->fail_queries ? false : 1;
	}

	public function get_row( $query, $output ) {
		$this->queries[] = $query;
		return $this->row;
	}
}

class Test_DSF_Translation_Workflow extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => static function ( $value, $flags = 0 ) {
					return json_encode( $value, $flags );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_fingerprint_is_canonical_and_versioned() {
		$first = array(
			'z' => "Last\r\nline",
			'a' => array( 'b' => 'Two', 'a' => 'One' ),
		);
		$second = array(
			'a' => array( 'a' => 'One', 'b' => 'Two' ),
			'z' => "Last\nline",
		);

		$this->assertSame( DSF_Translation_Workflow::fingerprint( $first, 1 ), DSF_Translation_Workflow::fingerprint( $second, 1 ) );
		$this->assertNotSame( DSF_Translation_Workflow::fingerprint( $first, 1 ), DSF_Translation_Workflow::fingerprint( $second, 2 ) );
	}

	public function test_fingerprint_preserves_list_order() {
		$this->assertNotSame(
			DSF_Translation_Workflow::fingerprint( array( 'items' => array( 'one', 'two' ) ) ),
			DSF_Translation_Workflow::fingerprint( array( 'items' => array( 'two', 'one' ) ) )
		);
	}

	public function test_fingerprint_excludes_sensitive_operational_and_relationship_fields() {
		$base = array(
			'title' => 'Visible title',
			'body'  => 'Visible body',
		);
		$unsafe = $base + array(
			'apiKey'             => 'secret-one',
			'_dsf_html_snapshot' => '<script>cached</script>',
			'headerTemplateId'   => 77,
			'sku'                => 'SKU-1',
			'order'              => array( 'email' => 'customer@example.test' ),
		);
		$changed_operations = $base + array(
			'apiKey'             => 'secret-two',
			'_dsf_html_snapshot' => '<p>different cache</p>',
			'headerTemplateId'   => 99,
			'sku'                => 'SKU-2',
			'order'              => array( 'email' => 'other@example.test' ),
		);

		$this->assertSame( DSF_Translation_Workflow::fingerprint( $base ), DSF_Translation_Workflow::fingerprint( $unsafe ) );
		$this->assertSame( DSF_Translation_Workflow::fingerprint( $unsafe ), DSF_Translation_Workflow::fingerprint( $changed_operations ) );
		$this->assertNotSame( DSF_Translation_Workflow::fingerprint( $base ), DSF_Translation_Workflow::fingerprint( array( 'title' => 'Changed', 'body' => 'Visible body' ) ) );
	}

	public function test_fingerprint_rejects_unsupported_and_over_nested_payloads() {
		$this->assertInstanceOf( WP_Error::class, DSF_Translation_Workflow::fingerprint( 'not-an-array' ) );
		$this->assertInstanceOf( WP_Error::class, DSF_Translation_Workflow::fingerprint( array( 'bad' => new stdClass() ) ) );

		$nested = 'leaf';
		for ( $i = 0; $i < 22; $i++ ) {
			$nested = array( 'child' => $nested );
		}
		$this->assertSame( 'dsf_fingerprint_depth', DSF_Translation_Workflow::fingerprint( $nested )->get_error_code() );
	}

	public function test_locale_and_schema_helpers_are_bounded() {
		$this->assertSame( 'es-MX', DSF_Translation_Workflow::normalize_language( 'es-mx' ) );
		$this->assertSame( 'zh-Hant-TW', DSF_Translation_Workflow::normalize_language( 'ZH-hant-tw' ) );
		$this->assertSame( '', DSF_Translation_Workflow::normalize_language( 'not_a_locale<script>' ) );
		$this->assertTrue( DSF_Translation_Workflow::is_valid_group_uuid( self::GROUP ) );

		$db     = new DSF_Test_Workflow_DB();
		$schema = DSF_Translation_Workflow::schema_sql( $db );
		$this->assertStringContainsString( 'PRIMARY KEY  (group_uuid,language)', $schema );
		$this->assertStringContainsString( 'reviewed_source_fingerprint char(64)', $schema );
	}

	public function test_record_review_requires_strict_exact_authorization() {
		$db      = new DSF_Test_Workflow_DB();
		$service = $this->service( $db );
		$hash    = str_repeat( 'a', 64 );

		$forbidden = $service->record_review( self::GROUP, 'es-MX', $hash, 1, static function () {
			return 1;
		} );
		$this->assertSame( 'dsf_workflow_forbidden', $forbidden->get_error_code() );
		$this->assertSame( array(), $db->queries );

		$received = array();
		$stored   = $service->record_review(
			self::GROUP,
			'es-mx',
			$hash,
			1,
			static function ( $action, $group_uuid, $language ) use ( &$received ) {
				$received = array( $action, $group_uuid, $language );
				return true;
			}
		);

		$this->assertSame( array( 'review', self::GROUP, 'es-MX' ), $received );
		$this->assertSame( 55, $stored['reviewer_id'] );
		$this->assertSame( '2026-07-16 12:00:00', $stored['reviewed_at_gmt'] );
		$this->assertFalse( $stored['machine_prefilled'] );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $db->prepared[0]['query'] );
	}

	public function test_record_review_rejects_disabled_language_and_bad_fingerprint_before_authorization() {
		$db      = new DSF_Test_Workflow_DB();
		$service = $this->service( $db );
		$calls   = 0;
		$auth    = static function () use ( &$calls ) {
			++$calls;
			return true;
		};

		$this->assertSame( 'dsf_workflow_language_disabled', $service->record_review( self::GROUP, 'fr', str_repeat( 'a', 64 ), 1, $auth )->get_error_code() );
		$this->assertSame( 'dsf_review_fingerprint', $service->record_review( self::GROUP, 'es-MX', 'bad', 1, $auth )->get_error_code() );
		$this->assertSame( 'dsf_review_fingerprint', $service->record_review( self::GROUP, 'es-MX', str_repeat( 'a', 64 ), '1bad', $auth )->get_error_code() );
		$this->assertSame( 0, $calls );
	}

	public function test_workflow_mutations_reject_implicit_boolean_values() {
		$db      = new DSF_Test_Workflow_DB();
		$service = $this->service( $db );
		$auth    = static function () {
			return true;
		};

		$this->assertSame( 'dsf_workflow_fact', $service->set_machine_prefilled( self::GROUP, 'es-MX', 'yes', $auth )->get_error_code() );
		$this->assertSame( 'dsf_workflow_fact', $service->set_critical_change( self::GROUP, 'es-MX', array(), $auth )->get_error_code() );
		$this->assertSame( array(), $db->queries );
	}

	public function test_get_facts_revalidates_database_values() {
		$db      = new DSF_Test_Workflow_DB();
		$db->row = array(
			'machine_prefilled'           => '1',
			'reviewer_id'                 => '17',
			'reviewed_at_gmt'             => '2026-07-16 09:30:00',
			'reviewed_source_fingerprint' => strtoupper( str_repeat( 'b', 64 ) ),
			'reviewed_fingerprint_schema' => '1',
			'critical_change'             => '0',
		);
		$facts = $this->service( $db )->get_facts( self::GROUP, 'es-MX' );

		$this->assertTrue( $facts['machine_prefilled'] );
		$this->assertSame( 17, $facts['reviewer_id'] );
		$this->assertSame( str_repeat( 'b', 64 ), $facts['reviewed_source_fingerprint'] );
		$this->assertFalse( $facts['critical_change'] );
	}

	public function test_default_table_tracks_multisite_prefix_changes() {
		$db      = new DSF_Test_Workflow_DB();
		$service = new DSF_Translation_Workflow(
			array(
				'wpdb'               => $db,
				'language_validator' => static function ( $language ) {
					return 'es-MX' === $language;
				},
			)
		);

		$service->get_facts( self::GROUP, 'es-MX' );
		$db->prefix = 'wp_7_';
		$service->get_facts( self::GROUP, 'es-MX' );

		$this->assertStringContainsString( 'FROM wp_dsf_translation_workflow', $db->prepared[0]['query'] );
		$this->assertStringContainsString( 'FROM wp_7_dsf_translation_workflow', $db->prepared[1]['query'] );
	}

	public function test_target_changes_and_machine_prefill_clear_prior_review_facts() {
		$db      = new DSF_Test_Workflow_DB();
		$service = $this->service( $db );
		$auth    = static function () {
			return true;
		};

		$this->assertTrue( $service->clear_review( self::GROUP, 'es-MX', $auth ) );
		$this->assertStringContainsString( 'reviewer_id = 0', $db->prepared[0]['query'] );
		$this->assertStringContainsString( 'reviewed_source_fingerprint =', $db->prepared[0]['query'] );

		$this->assertTrue( $service->set_machine_prefilled( self::GROUP, 'es-MX', true, $auth ) );
		$this->assertStringContainsString( 'reviewer_id = 0', $db->prepared[1]['query'] );
		$this->assertStringContainsString( 'machine_prefilled = VALUES(machine_prefilled)', $db->prepared[1]['query'] );
	}

	public function test_derived_statuses_and_recommended_minor_stale_policy() {
		$current = str_repeat( 'a', 64 );
		$facts   = DSF_Translation_Workflow::empty_facts( self::GROUP, 'es-MX' );
		$facts['reviewer_id']                  = 7;
		$facts['reviewed_at_gmt']              = '2026-07-16 12:00:00';
		$facts['reviewed_source_fingerprint'] = $current;
		$facts['reviewed_fingerprint_schema'] = 1;
		$base = array(
			'exists'                    => true,
			'facts'                     => $facts,
			'current_source_fingerprint' => $current,
			'current_fingerprint_schema' => 1,
			'dependencies_eligible'      => true,
			'route_valid'                => true,
			'integrity_valid'            => true,
			'content_ready'              => true,
			'required_fields_confirmed'  => true,
			'is_public'                  => false,
		);

		$this->assertSame( 'reviewed', DSF_Translation_Workflow::derive_status( $base )['status'] );
		$incomplete_facts                    = $facts;
		$incomplete_facts['reviewed_at_gmt'] = '';
		$incomplete_review                   = DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'facts' => $incomplete_facts ) ) );
		$this->assertSame( 'ready_for_review', $incomplete_review['status'] );
		$this->assertFalse( $incomplete_review['review_current'] );
		$published = DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'is_public' => true ) ) );
		$this->assertSame( 'published', $published['status'] );
		$this->assertTrue( $published['can_publish'] );

		$stale = DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'current_source_fingerprint' => str_repeat( 'c', 64 ), 'is_public' => true ) ) );
		$this->assertSame( 'source_changed', $stale['status'] );
		$this->assertFalse( $stale['can_publish'] );
		$this->assertTrue( $stale['retain_public'] );

		$critical_facts                    = $facts;
		$critical_facts['critical_change'] = true;
		$critical = DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'facts' => $critical_facts, 'current_source_fingerprint' => str_repeat( 'c', 64 ), 'is_public' => true ) ) );
		$this->assertFalse( $critical['retain_public'] );

		$blocked = DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'dependencies_eligible' => false ) ) );
		$this->assertSame( 'blocked', $blocked['status'] );
	}

	public function test_critical_change_invalidates_even_a_matching_review_fingerprint() {
		$current = str_repeat( 'a', 64 );
		$facts   = DSF_Translation_Workflow::empty_facts( self::GROUP, 'es-MX' );
		$facts['reviewer_id']                  = 7;
		$facts['reviewed_at_gmt']              = '2026-07-16 12:00:00';
		$facts['reviewed_source_fingerprint'] = $current;
		$facts['reviewed_fingerprint_schema'] = 1;
		$facts['critical_change']              = true;

		$status = DSF_Translation_Workflow::derive_status(
			array(
				'exists'                     => true,
				'facts'                      => $facts,
				'current_source_fingerprint' => $current,
				'current_fingerprint_schema' => 1,
				'dependencies_eligible'      => true,
				'route_valid'                => true,
				'integrity_valid'            => true,
				'content_ready'              => true,
				'required_fields_confirmed'  => true,
				'is_public'                  => true,
				'allow_stale_public'         => true,
			)
		);

		$this->assertSame( 'source_changed', $status['status'] );
		$this->assertContains( 'source_changed', $status['flags'] );
		$this->assertTrue( $status['review_current'] );
		$this->assertFalse( $status['can_publish'] );
		$this->assertFalse( $status['retain_public'] );
	}

	public function test_machine_ready_draft_and_missing_statuses() {
		$base = array(
			'exists'                    => true,
			'facts'                     => DSF_Translation_Workflow::empty_facts(),
			'current_source_fingerprint' => str_repeat( 'a', 64 ),
			'current_fingerprint_schema' => 1,
			'dependencies_eligible'      => true,
			'route_valid'                => true,
			'integrity_valid'            => true,
			'content_ready'              => true,
			'required_fields_confirmed'  => true,
			'is_public'                  => false,
		);
		$this->assertSame( 'ready_for_review', DSF_Translation_Workflow::derive_status( $base )['status'] );

		$machine_facts                       = $base['facts'];
		$machine_facts['machine_prefilled'] = true;
		$this->assertSame( 'machine_prefilled', DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'facts' => $machine_facts ) ) )['status'] );
		$machine_facts['reviewer_id']                  = 9;
		$machine_facts['reviewed_at_gmt']              = '2026-07-16 12:00:00';
		$machine_facts['reviewed_source_fingerprint'] = str_repeat( 'a', 64 );
		$machine_facts['reviewed_fingerprint_schema'] = 1;
		$this->assertSame( 'machine_prefilled', DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'facts' => $machine_facts ) ) )['status'] );
		$this->assertSame( 'draft', DSF_Translation_Workflow::derive_status( array_replace( $base, array( 'required_fields_confirmed' => false ) ) )['status'] );
		$this->assertSame( 'missing', DSF_Translation_Workflow::derive_status( array( 'exists' => false ) )['status'] );
	}

	private function service( $db ) {
		return new DSF_Translation_Workflow(
			array(
				'wpdb'                  => $db,
				'table_name'            => 'wp_dsf_translation_workflow',
				'language_validator'    => static function ( $language ) {
					return 'es-MX' === $language;
				},
				'current_user_resolver' => static function () {
					return 55;
				},
				'clock'                 => static function () {
					return '2026-07-16 12:00:00';
				},
			)
		);
	}
}
