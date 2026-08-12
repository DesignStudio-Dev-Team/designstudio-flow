<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-workflow.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-review.php';

/** Relationship double for review tests. */
class DSF_Review_Test_Relationships {
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

/** Workflow double recording review writes. */
class DSF_Review_Test_Workflow {
	public $facts           = array();
	public $recorded        = array();
	public $prefilled       = array();
	public $critical        = array();
	public $record_response = null;

	public function get_facts( $group, $language ) {
		unset( $group, $language );
		return $this->facts;
	}

	public function record_review_from_payload( $group, $language, $payload, $schema, $auth ) {
		if ( ! call_user_func( $auth ) ) {
			return new WP_Error( 'dsf_review_forbidden', 'forbidden' );
		}
		$this->recorded[] = array( $group, $language, $schema, $payload );
		return $this->record_response ?? array( 'ok' => true );
	}

	public function set_machine_prefilled( $group, $language, $value, $auth ) {
		unset( $auth );
		$this->prefilled[] = array( $group, $language, $value );
		return true;
	}

	public function set_critical_change( $group, $language, $value, $auth ) {
		unset( $auth );
		$this->critical[] = array( $group, $language, $value );
		return true;
	}
}

/** Publish-gate double returning scripted evaluations. */
class DSF_Review_Test_Gate {
	public $result = null;

	public function evaluate_post( $post_id, $check_permissions = true, $allow_stale = true, $incoming = null ) {
		unset( $post_id, $check_permissions, $allow_stale, $incoming );
		return $this->result;
	}
}

/**
 * Covers the review dashboard contract: derived status, blocking reasons, and
 * the server-side gates on approving and publishing.
 */
class Test_DSF_Translation_Review extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	/** @var DSF_Review_Test_Relationships */
	private $relationships;

	/** @var DSF_Review_Test_Workflow */
	private $workflow;

	/** @var DSF_Review_Test_Gate */
	private $gate;

	/** @var DSF_Translation_Review */
	private $review;

	/** @var array<int,object> */
	private $posts = array();

	/** @var array<int,array<string,mixed>> */
	private $meta = array();

	/** @var bool */
	private $can_edit = true;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->relationships = new DSF_Review_Test_Relationships();
		$this->workflow      = new DSF_Review_Test_Workflow();
		$this->gate          = new DSF_Review_Test_Gate();
		$this->can_edit      = true;

		$this->posts = array(
			10 => (object) array(
				'ID'                => 10,
				'post_type'         => 'page',
				'post_status'       => 'publish',
				'post_title'        => 'About us',
				'post_name'         => 'about',
				'post_content'      => '<p>Body</p>',
				'post_excerpt'      => 'Summary',
				'post_modified_gmt' => '2026-07-01 00:00:00',
			),
			11 => (object) array(
				'ID'                => 11,
				'post_type'         => 'page',
				'post_status'       => 'draft',
				'post_title'        => 'Acerca de',
				'post_name'         => 'acerca-de',
				'post_content'      => '',
				'post_excerpt'      => '',
				'post_modified_gmt' => '2026-07-20 00:00:00',
			),
		);
		$this->meta = array( 11 => array() );

		$this->relationships->add( self::GROUP, 'page', 10, 'en-US' );

		$posts    = &$this->posts;
		$meta     = &$this->meta;
		$can_edit = &$this->can_edit;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => static function ( $v ) { return $v instanceof WP_Error; } ) );
		WP_Mock::userFunction( 'get_edit_post_link', array( 'return' => 'https://example.test/edit' ) );
		WP_Mock::userFunction( 'get_permalink', array( 'return' => 'https://example.test/es/acerca-de/' ) );
		WP_Mock::userFunction( 'get_the_author_meta', array( 'return' => 'Reviewer Name' ) );
		WP_Mock::userFunction( 'sanitize_title', array( 'return' => static function ( $v ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ), '-' ); } ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => static function () use ( &$can_edit ) { return $can_edit; } ) );
		WP_Mock::userFunction( 'get_post', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			$id = is_object( $id ) ? $id->ID : (int) $id;
			return $posts[ $id ] ?? null;
		} ) );
		WP_Mock::userFunction( 'get_post_status', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			return isset( $posts[ (int) $id ] ) ? $posts[ (int) $id ]->post_status : false;
		} ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => static function ( $id, $key = '', $single = false ) use ( &$meta ) {
			unset( $single );
			return $meta[ (int) $id ][ $key ] ?? '';
		} ) );
		WP_Mock::userFunction( 'get_option', array( 'return' => static function ( $key, $default = false ) {
			if ( DSF_Multilingual_Settings::OPTION_NAME === $key ) {
				return array(
					'enabled'           => true,
					'main_language'     => 'en-US',
					'migration_state'   => 'complete',
					'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
					'languages'         => array(
						array( 'code' => 'en-US', 'prefix' => '' ),
						array( 'code' => 'es-MX', 'prefix' => 'es' ),
					),
				);
			}
			return $default;
		} ) );

		$this->review = new DSF_Translation_Review(
			array(
				'relationships' => $this->relationships,
				'workflow'      => $this->workflow,
				'publish_gate'  => $this->gate,
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_a_language_with_no_object_reports_missing_and_offers_a_clone() {
		$row = $this->review->describe_target( $this->posts[10], 'es-MX' );

		$this->assertSame( 'missing', $row['status'] );
		$this->assertTrue( $row['can_clone'] );
		$this->assertSame( 0, $row['target_id'] );
		$this->assertFalse( $row['can_publish'] );
	}

	public function test_derived_status_and_blockers_come_from_the_publishing_gate() {
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );
		$this->gate->result = new WP_Error(
			'dsf_translation_publish_blocked',
			'blocked',
			array(
				'workflow'     => array(
					'status'      => 'blocked',
					'flags'       => array( 'blocked' ),
					'can_publish' => false,
				),
				'dependencies' => array(
					'missing' => array(
						array( 'kind' => 'header', 'source_path' => '_dsf_settings.layout.headerTemplateId' ),
					),
				),
			)
		);

		$row = $this->review->describe_target( $this->posts[10], 'es-MX' );

		$this->assertSame( 'blocked', $row['status'] );
		$this->assertSame( 11, $row['target_id'] );
		$this->assertFalse( $row['can_publish'] );
		$this->assertFalse( $row['can_clone'] );

		$messages = wp_list_pluck_review( $row['blockers'], 'message' );
		$this->assertNotEmpty( array_filter( $messages, static function ( $m ) { return false !== strpos( $m, 'header' ); } ) );
		$this->assertNotEmpty( array_filter( $messages, static function ( $m ) { return false !== strpos( $m, 'title' ); } ), 'An unconfirmed copied title blocks publishing.' );
	}

	public function test_a_publishable_translation_reports_its_public_url() {
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );
		$this->posts[11]->post_status = 'publish';
		$this->gate->result           = array(
			'eligible' => true,
			'workflow' => array(
				'status'      => 'published',
				'flags'       => array( 'published' ),
				'can_publish' => true,
			),
		);

		$row = $this->review->describe_target( $this->posts[10], 'es-MX' );

		$this->assertSame( 'published', $row['status'] );
		$this->assertTrue( $row['can_publish'] );
		$this->assertSame( 'https://example.test/es/acerca-de/', $row['target_view'] );
		$this->assertFalse( $row['can_review'], 'An already-published, reviewed translation needs no second approval.' );
	}

	public function test_approving_records_the_source_version_and_clears_machine_output() {
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );
		$this->gate->result = array(
			'eligible' => true,
			'workflow' => array( 'status' => 'reviewed', 'flags' => array( 'reviewed' ), 'can_publish' => true ),
		);
		WP_Mock::onFilter( 'dsf_multilingual_fingerprint_payload' )->with( WP_Mock\Functions::type( 'array' ), WP_Mock\Functions::type( 'array' ) )->reply( array( 'post_title' => 'About us' ) );

		$result = $this->review->approve( 11 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->workflow->recorded );
		$this->assertSame( self::GROUP, $this->workflow->recorded[0][0] );
		$this->assertSame( 'es-MX', $this->workflow->recorded[0][1] );
		$this->assertSame( array( self::GROUP, 'es-MX', false ), $this->workflow->prefilled[0], 'Approval is what turns machine output into reviewed output.' );
		$this->assertSame( array( self::GROUP, 'es-MX', false ), $this->workflow->critical[0] );
	}

	public function test_approval_is_refused_without_object_permission() {
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );
		$this->can_edit = false;

		$result = $this->review->approve( 11 );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_review_forbidden', $result->get_error_code() );
		$this->assertSame( array(), $this->workflow->recorded );
	}

	public function test_the_main_language_and_unknown_objects_cannot_be_reviewed() {
		$main = $this->review->approve( 10 );
		$this->assertInstanceOf( 'WP_Error', $main );
		$this->assertSame( 'dsf_review_main', $main->get_error_code() );

		$missing = $this->review->approve( 999 );
		$this->assertInstanceOf( 'WP_Error', $missing );
		$this->assertSame( 'dsf_review_object', $missing->get_error_code() );

		$this->assertSame( array(), $this->workflow->recorded );
	}

	public function test_publishing_refuses_whatever_the_gate_refuses() {
		$this->relationships->add( self::GROUP, 'page', 11, 'es-MX' );
		$this->gate->result = new WP_Error( 'dsf_translation_publish_blocked', 'blocked', array() );

		$result = $this->review->publish( 11 );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_translation_publish_blocked', $result->get_error_code() );
	}

	public function test_a_catalog_overlay_is_reviewed_through_its_own_adapter() {
		require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlays.php';

		$overlay_id = DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' );
		$source_id  = DSF_Translation_Overlays::overlay_id( 4321, 'en-US' );
		$this->relationships->members[] = array(
			'group_uuid'     => self::GROUP,
			'object_kind'    => DSF_Translation_Overlays::KIND,
			'object_subtype' => 'product',
			'object_id'      => $overlay_id,
			'language'       => 'es-MX',
		);
		$this->relationships->members[] = array(
			'group_uuid'     => self::GROUP,
			'object_kind'    => DSF_Translation_Overlays::KIND,
			'object_subtype' => 'product',
			'object_id'      => $source_id,
			'language'       => 'en-US',
		);
		WP_Mock::onFilter( 'dsf_multilingual_fingerprint_payload' )->with( WP_Mock\Functions::type( 'array' ), WP_Mock\Functions::type( 'array' ) )->reply( array( 'title' => 'Trail Runner' ) );

		$result = $this->review->approve( $overlay_id, DSF_Translation_Overlays::KIND, 'product' );

		$this->assertIsArray( $result );
		$this->assertSame( 'reviewed', $result['status'] );
		$this->assertCount( 1, $this->workflow->recorded );
		$this->assertSame( 'es-MX', $this->workflow->recorded[0][1] );
	}

	public function test_a_catalog_overlay_review_is_refused_without_permission() {
		require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlays.php';
		$this->can_edit = false;

		$result = $this->review->approve(
			DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' ),
			DSF_Translation_Overlays::KIND,
			'product'
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_review_forbidden', $result->get_error_code() );
		$this->assertSame( array(), $this->workflow->recorded );
	}

	public function test_every_dashboard_status_has_a_label() {
		$labels = DSF_Translation_Review::get_status_labels();

		foreach ( array( 'missing', 'draft', 'machine_prefilled', 'source_changed', 'blocked', 'ready_for_review', 'reviewed', 'published' ) as $status ) {
			$this->assertArrayHasKey( $status, $labels );
		}
	}
}

/**
 * Local pluck helper so assertions do not need a WordPress runtime.
 *
 * @param array  $rows  Rows.
 * @param string $field Field name.
 * @return array
 */
function wp_list_pluck_review( $rows, $field ) {
	return array_map(
		static function ( $row ) use ( $field ) {
			return $row[ $field ];
		},
		$rows
	);
}
