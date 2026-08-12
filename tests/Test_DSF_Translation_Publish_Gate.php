<?php

use PHPUnit\Framework\TestCase;

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

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-workflow.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-publish-gate.php';

/** In-memory relationship fixture for publication decisions. */
class DSF_Test_Publish_Gate_Relationships {
	public $objects = array();
	public $members = array();

	public function find_by_object( $kind, $subtype, $object_id ) {
		$key = $kind . '|' . $subtype . '|' . (int) $object_id;
		return $this->objects[ $key ] ?? null;
	}

	public function find_member( $group_uuid, $language ) {
		$key = $group_uuid . '|' . $language;
		return $this->members[ $key ] ?? null;
	}

	public function set_object( $member ) {
		$key                   = $member['object_kind'] . '|' . $member['object_subtype'] . '|' . (int) $member['object_id'];
		$this->objects[ $key ] = $member;
	}

	public function set_member( $member ) {
		$key                   = $member['group_uuid'] . '|' . $member['language'];
		$this->members[ $key ] = $member;
	}
}

/** In-memory review-fact fixture for publication decisions. */
class DSF_Test_Publish_Gate_Workflow {
	public $facts = array();

	public function get_facts( $group_uuid, $language ) {
		return $this->facts ?: DSF_Translation_Workflow::empty_facts( $group_uuid, $language );
	}
}

/** In-memory dependency-closure fixture for publication decisions. */
class DSF_Test_Publish_Gate_Dependencies {
	public $calls  = 0;
	public $result = array( 'eligible' => true );

	public function evaluate_closure( $group_uuid, $language ) {
		unset( $group_uuid, $language );
		++$this->calls;
		return $this->result;
	}
}

/** Small WP_REST_Request-compatible fixture for partial-update field checks. */
class DSF_Test_Publish_Gate_REST_Request implements ArrayAccess {
	private $params;

	public function __construct( $params ) {
		$this->params = $params;
	}

	public function get_params() {
		return $this->params;
	}

	public function get_json_params() {
		return $this->params;
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}

	public function has_param( $key ) {
		return array_key_exists( $key, $this->params );
	}

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return array_key_exists( $offset, $this->params );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->params[ $offset ] ?? null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {
		$this->params[ $offset ] = $value;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		unset( $this->params[ $offset ] );
	}
}

class Test_DSF_Translation_Publish_Gate extends TestCase {

	private const GROUP            = '11111111-2222-4333-8444-555555555555';
	private const DEPENDENCY_GROUP = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
	private const MAIN_ID          = 100;
	private const TARGET_ID        = 200;

	private $posts;
	private $post_meta;
	private $settings;
	private $relationships;
	private $workflow;
	private $dependencies;
	private $source_payload;
	private $has_conflict;
	private $permissions_allowed;
	private $fields_confirmed;
	private $confirmation_calls;
	private $post_updates;
	private $cleared_schedules;
	private $route_valid;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->posts = array(
			self::MAIN_ID   => $this->post( self::MAIN_ID, 'Main source', 'main-source', 'Source body', 'publish' ),
			self::TARGET_ID => $this->post( self::TARGET_ID, 'Fuente traducida', 'fuente-traducida', 'Translated body', 'draft' ),
		);
		$this->post_meta           = array();
		$this->settings            = array(
			'enabled'       => true,
			'main_language' => 'en-US',
			'languages'     => array(
				array(
					'code'   => 'en-US',
					'prefix' => '',
				),
				array(
					'code'   => 'es-MX',
					'prefix' => 'es-mx',
				),
			),
		);
		$this->relationships       = new DSF_Test_Publish_Gate_Relationships();
		$this->workflow            = new DSF_Test_Publish_Gate_Workflow();
		$this->dependencies        = new DSF_Test_Publish_Gate_Dependencies();
		$this->source_payload      = array( 'title' => 'Main source', 'body' => 'Source body' );
		$this->has_conflict        = false;
		$this->permissions_allowed = true;
		$this->fields_confirmed    = true;
		$this->confirmation_calls  = 0;
		$this->post_updates        = array();
		$this->cleared_schedules   = array();
		$this->route_valid         = true;

		$source = $this->member( self::MAIN_ID, 'en-US' );
		$target = $this->member( self::TARGET_ID, 'es-MX' );
		$this->relationships->set_object( $source );
		$this->relationships->set_object( $target );
		$this->relationships->set_member( $source );
		$this->relationships->set_member( $target );

		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return max( 0, (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
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
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_title', array( 'return' => static function ( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); } ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_option', array( 'return' => 0 ) );
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => function ( $post_id ) {
					if ( is_object( $post_id ) && isset( $post_id->ID ) ) {
						$post_id = $post_id->ID;
					}
					return $this->posts[ (int) $post_id ] ?? null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_status',
			array(
				'return' => function ( $post_id ) {
					return isset( $this->posts[ (int) $post_id ] ) ? $this->posts[ (int) $post_id ]->post_status : false;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => function ( $post_id, $key ) {
					return $this->post_meta[ (int) $post_id ][ $key ] ?? array();
				},
			)
		);
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'return' => function () {
					return $this->permissions_allowed;
				},
			)
		);
		WP_Mock::userFunction(
			'wp_update_post',
			array(
				'return' => function ( $post_data ) {
					$this->post_updates[] = $post_data;
					$post_id              = (int) ( $post_data['ID'] ?? 0 );
					if ( isset( $this->posts[ $post_id ], $post_data['post_status'] ) ) {
						$this->posts[ $post_id ]->post_status = $post_data['post_status'];
					}
					return $post_id;
				},
			)
		);
		WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			array(
				'return' => function ( $hook, $args = array() ) {
					$this->cleared_schedules[] = array( $hook, $args );
					return true;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_disabled_and_main_language_objects_are_allowed() {
		$this->settings['enabled'] = false;
		$disabled                  = $this->gate()->evaluate_post( 0 );

		$this->assertTrue( $disabled['eligible'] );
		$this->assertSame( 'disabled', $disabled['reason'] );

		$this->settings['enabled'] = true;
		$main_target               = $this->member( self::TARGET_ID, 'en-US' );
		$this->relationships->set_object( $main_target );
		$main = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertTrue( $main['eligible'] );
		$this->assertSame( 'main_language', $main['reason'] );
	}

	public function test_secondary_without_review_is_blocked() {
		$result = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_publish_blocked', $result->get_error_code() );
	}

	public function test_unassigned_supported_post_is_allowed_only_until_relationship_migration_completes() {
		unset( $this->relationships->objects['post|page|' . self::TARGET_ID] );
		$this->settings['migration_state']   = 'running';
		$this->settings['migration_version'] = 0;

		$during_migration = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertTrue( $during_migration['eligible'] );
		$this->assertSame( 'unassigned_legacy_main', $during_migration['reason'] );

		$this->settings['migration_state']   = 'complete';
		$this->settings['migration_version'] = DSF_Multilingual_Settings::MIGRATION_VERSION;
		$after_migration                     = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertInstanceOf( WP_Error::class, $after_migration );
	}

	public function test_current_review_dependencies_and_confirmations_allow_publication() {
		$this->mark_current_review();

		$result = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertTrue( $result['eligible'] );
		$this->assertSame( 'reviewed', $result['workflow']['status'] );
		$this->assertSame( 1, $this->dependencies->calls );
		$this->assertSame( 1, $this->confirmation_calls );
	}

	public function test_active_multilingual_conflict_blocks_secondary_publication() {
		$this->mark_current_review();
		$this->has_conflict = true;

		$result = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_plugin_conflict', $result->get_error_code() );
	}

	public function test_minor_stale_public_translation_is_retained_but_cannot_be_newly_activated() {
		$this->mark_current_review();
		$this->posts[ self::TARGET_ID ]->post_status = 'publish';
		$this->source_payload['body']                = 'A newer source body';

		$retained = $this->gate()->evaluate_post( self::TARGET_ID, true, true );

		$this->assertTrue( $retained['eligible'] );
		$this->assertSame( 'source_changed', $retained['workflow']['status'] );
		$this->assertTrue( $retained['workflow']['retain_public'] );

		$activation = $this->gate()->evaluate_post( self::TARGET_ID, true, false );
		$this->assertInstanceOf( WP_Error::class, $activation );
		$this->assertSame( 'dsf_translation_publish_blocked', $activation->get_error_code() );

		$this->posts[ self::TARGET_ID ]->post_status = 'draft';
		$new_publication = $this->gate()->evaluate_post( self::TARGET_ID, true, true );
		$this->assertInstanceOf( WP_Error::class, $new_publication );
	}

	public function test_strict_source_change_policy_disables_minor_stale_retention() {
		$this->mark_current_review();
		$this->posts[ self::TARGET_ID ]->post_status = 'publish';
		$this->source_payload['body']                = 'A newer source body';
		$this->settings['source_change_policy']      = 'hide_until_reviewed';

		$result = $this->gate()->evaluate_post( self::TARGET_ID, true, true );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_publish_blocked', $result->get_error_code() );
	}

	public function test_public_minor_stale_dependency_follows_source_change_policy() {
		$dependency = $this->register_dependency_pair();
		$this->mark_current_review();
		$this->source_payload['body']           = 'A newer dependency source body';
		$this->settings['source_change_policy'] = 'keep_minor';

		$kept = $this->gate()->dependency_member_is_eligible( $dependency['target'], self::DEPENDENCY_GROUP, 'es-MX', array() );

		$this->assertTrue( $kept['eligible'] );

		$this->settings['source_change_policy'] = 'hide_until_reviewed';
		$strict = $this->gate()->dependency_member_is_eligible( $dependency['target'], self::DEPENDENCY_GROUP, 'es-MX', array() );

		$this->assertFalse( $strict['eligible'] );
		$this->assertSame( 'source_changed', $strict['reason'] );
	}

	public function test_publish_with_edited_target_content_is_blocked_before_review_reuse() {
		$this->mark_current_review();

		$result = $this->gate()->evaluate_post(
			self::TARGET_ID,
			true,
			true,
			array( 'post_content' => 'Edited translated body' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_changed', $result->get_error_code() );
	}

	public function test_rest_status_only_update_reuses_review_but_supplied_content_change_does_not() {
		$this->mark_current_review();
		$status_only              = new stdClass();
		$status_only->ID          = self::TARGET_ID;
		$status_only->post_status = 'publish';

		$allowed = $this->gate()->filter_rest_pre_insert(
			$status_only,
			new DSF_Test_Publish_Gate_REST_Request( array( 'status' => 'publish' ) )
		);

		$this->assertSame( $status_only, $allowed );

		$with_content               = clone $status_only;
		$with_content->post_content = 'Edited translated body';
		$blocked                    = $this->gate()->filter_rest_pre_insert(
			$with_content,
			new DSF_Test_Publish_Gate_REST_Request(
				array(
					'status'  => 'publish',
					'content' => 'Edited translated body',
				)
			)
		);

		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 'dsf_translation_changed', $blocked->get_error_code() );
	}

	public function test_wordpress_58_three_argument_post_data_filter_keeps_eligible_publish() {
		$this->mark_current_review();

		$result = $this->gate()->filter_post_data(
			array( 'post_status' => 'publish' ),
			array( 'ID' => self::TARGET_ID ),
			array()
		);

		$this->assertSame( 'publish', $result['post_status'] );
	}

	public function test_new_supported_post_is_drafted_until_its_relationship_exists() {
		$gate = $this->gate();

		$result = $gate->filter_post_data(
			array( 'post_status' => 'publish', 'post_type' => 'page' ),
			array( 'post_type' => 'page' ),
			array(),
			false
		);

		$this->assertSame( 'draft', $result['post_status'] );
		$this->assertInstanceOf( WP_Error::class, $gate->get_blocked_error( 0 ) );
		$this->assertSame( 'dsf_translation_relationship_pending', $gate->get_blocked_error( 0 )->get_error_code() );
	}

	public function test_missing_same_language_explicit_dependency_blocks_publication() {
		$this->mark_current_review();
		$this->post_meta[ self::TARGET_ID ]['_dsf_settings'] = array(
			'layout' => array( 'headerTemplateId' => 300 ),
		);
		$dependency_reference = array(
			'group_uuid'    => self::DEPENDENCY_GROUP,
			'language'      => 'es-MX',
			'object_kind'   => 'post',
			'object_subtype' => 'dsf_layout',
			'object_id'     => 300,
		);
		$this->relationships->set_object( $dependency_reference );

		$result = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_dependency_language', $result->get_error_code() );
	}

	public function test_active_template_flag_is_rejected_while_language_route_is_unavailable() {
		$this->mark_current_review();
		$this->route_valid = false;
		$gate = $this->gate();

		$result = $gate->filter_public_meta_update( null, self::TARGET_ID, '_dsf_pt_active', 1 );

		$this->assertFalse( $result );
		$this->assertInstanceOf( WP_Error::class, $gate->get_blocked_error( self::TARGET_ID ) );
	}

	public function test_scheduled_publish_guard_moves_blocked_translation_to_draft_before_core_cron() {
		$this->posts[ self::TARGET_ID ]->post_status = 'future';
		$gate                                          = $this->gate();

		$gate->guard_scheduled_publish( $this->posts[ self::TARGET_ID ] );

		$this->assertSame(
			array(
				'ID'          => self::TARGET_ID,
				'post_status' => 'draft',
			),
			$this->post_updates[0]
		);
		$this->assertSame( 'draft', $this->posts[ self::TARGET_ID ]->post_status );
		$this->assertSame( array( 'publish_future_post', array( self::TARGET_ID ) ), $this->cleared_schedules[0] );
		$this->assertInstanceOf( WP_Error::class, $gate->get_blocked_error( self::TARGET_ID ) );
	}

	public function test_direct_publish_transition_guard_immediately_repairs_blocked_status() {
		$this->posts[ self::TARGET_ID ]->post_status = 'publish';
		$post                                          = $this->posts[ self::TARGET_ID ];
		$gate                                          = $this->gate();

		$gate->guard_direct_publish_transition( 'publish', 'draft', $post );

		$this->assertSame(
			array(
				'ID'          => self::TARGET_ID,
				'post_status' => 'draft',
			),
			$this->post_updates[0]
		);
		$this->assertSame( 'draft', $post->post_status );
		$this->assertInstanceOf( WP_Error::class, $gate->get_blocked_error( self::TARGET_ID ) );
	}

	public function test_raw_dependency_id_in_wrong_language_is_blocked_even_when_target_sibling_exists() {
		$this->mark_current_review();
		$this->register_dependency_pair();
		$this->post_meta[ self::TARGET_ID ]['_dsf_settings'] = array(
			'layout' => array( 'headerTemplateId' => 300 ),
		);

		$result = $this->gate()->evaluate_post( self::TARGET_ID );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_dependency_raw_language', $result->get_error_code() );
	}

	public function test_missing_object_capability_blocks_publication() {
		$this->mark_current_review();
		$this->permissions_allowed = false;

		$result = $this->gate()->evaluate_post( self::TARGET_ID, true );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_translation_forbidden', $result->get_error_code() );
	}

	private function gate() {
		return new DSF_Translation_Publish_Gate(
			array(
				'relationships'            => $this->relationships,
				'workflow'                 => $this->workflow,
				'dependencies'             => $this->dependencies,
				'settings_reader'          => function () {
					return $this->settings;
				},
				'conflict_detector'        => function () {
					return $this->has_conflict;
				},
				'fingerprint_resolver'     => function () {
					return $this->source_payload;
				},
				'required_fields_resolver' => function () {
					++$this->confirmation_calls;
					return $this->fields_confirmed;
				},
				'route_validator'          => function () {
					return $this->route_valid;
				},
			)
		);
	}

	private function mark_current_review() {
		$fingerprint = DSF_Translation_Workflow::build_fingerprint( $this->source_payload, 1 );
		$facts       = DSF_Translation_Workflow::empty_facts( self::GROUP, 'es-MX' );
		$facts['reviewer_id']                  = 7;
		$facts['reviewed_at_gmt']              = '2026-07-16 12:00:00';
		$facts['reviewed_source_fingerprint'] = $fingerprint['fingerprint'];
		$facts['reviewed_fingerprint_schema'] = $fingerprint['schema'];
		$this->workflow->facts                 = $facts;
	}

	private function member( $object_id, $language ) {
		return array(
			'group_uuid'    => self::GROUP,
			'language'      => $language,
			'object_kind'   => 'post',
			'object_subtype' => 'page',
			'object_id'     => $object_id,
		);
	}

	private function register_dependency_pair() {
		$this->posts[300]            = $this->post( 300, 'Header source', 'header-source', 'Header source body', 'publish' );
		$this->posts[300]->post_type = 'dsf_layout';
		$this->posts[301]            = $this->post( 301, 'Encabezado', 'encabezado', 'Translated header body', 'publish' );
		$this->posts[301]->post_type = 'dsf_layout';

		$source = array(
			'group_uuid'    => self::DEPENDENCY_GROUP,
			'language'      => 'en-US',
			'object_kind'   => 'post',
			'object_subtype' => 'dsf_layout',
			'object_id'     => 300,
		);
		$target = array(
			'group_uuid'    => self::DEPENDENCY_GROUP,
			'language'      => 'es-MX',
			'object_kind'   => 'post',
			'object_subtype' => 'dsf_layout',
			'object_id'     => 301,
		);
		$this->relationships->set_object( $source );
		$this->relationships->set_object( $target );
		$this->relationships->set_member( $source );
		$this->relationships->set_member( $target );

		return array(
			'source' => $source,
			'target' => $target,
		);
	}

	private function post( $post_id, $title, $slug, $content, $status ) {
		$post               = new stdClass();
		$post->ID           = $post_id;
		$post->post_type    = 'page';
		$post->post_title   = $title;
		$post->post_name    = $slug;
		$post->post_excerpt = '';
		$post->post_content = $content;
		$post->post_status  = $status;
		return $post;
	}
}
