<?php

use PHPUnit\Framework\TestCase;

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

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-conflicts.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-migration.php';

class DSF_Test_Migration_Relationships {
	public $members = array();
	public $created = 0;
	public $failure = null;

	public function find_by_object( $kind, $subtype, $object_id ) {
		$key = $kind . ':' . $subtype . ':' . $object_id;
		return $this->members[ $key ] ?? null;
	}

	public function create_group( $kind, $subtype, $object_id, $language ) {
		if ( $this->failure instanceof WP_Error ) {
			return $this->failure;
		}
		$key      = $kind . ':' . $subtype . ':' . $object_id;
		$uuid     = sprintf( '00000000-0000-4000-8000-%012d', ++$this->created );
		$relation = array(
			'group_uuid'       => $uuid,
			'object_kind'      => $kind,
			'object_subtype'   => $subtype,
			'object_id'        => $object_id,
			'language'         => $language,
		);
		$this->members[ $key ] = $relation;
		return $relation;
	}
}

class DSF_Test_Migration_Dependencies {
	public $replacements = array();

	public function replace_edges( $group_uuid, $language, $edges, $authorization_callback ) {
		if ( true !== call_user_func( $authorization_callback, 'replace_dependencies', $group_uuid, $language ) ) {
			return new WP_Error( 'forbidden', 'Forbidden' );
		}
		$this->replacements[] = compact( 'group_uuid', 'language', 'edges' );
		return count( $edges );
	}
}

class Test_DSF_Multilingual_Migration extends TestCase {

	private $options = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->options = array(
			DSF_Multilingual_Settings::OPTION_NAME => DSF_Multilingual_Settings::sanitize_settings(
				array(
					'enabled'         => true,
					'main_language'   => 'en-US',
					'languages'       => array(
						array( 'code' => 'en-US', 'prefix' => '' ),
						array( 'code' => 'es-MX', 'prefix' => 'es-mx' ),
					),
					'migration_state' => 'pending',
				)
			),
		);

		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => function ( $key, $default = false ) {
					return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
				},
			)
		);
		WP_Mock::userFunction(
			'update_option',
			array(
				'return' => function ( $key, $value ) {
					$this->options[ $key ] = $value;
					return true;
				},
			)
		);
		WP_Mock::userFunction(
			'add_option',
			array(
				'return' => function ( $key, $value ) {
					if ( array_key_exists( $key, $this->options ) ) {
						return false;
					}
					$this->options[ $key ] = $value;
					return true;
				},
			)
		);
		WP_Mock::userFunction(
			'delete_option',
			array(
				'return' => function ( $key ) {
					unset( $this->options[ $key ] );
					return true;
				},
			)
		);
		WP_Mock::userFunction( 'wp_next_scheduled', array( 'return' => false ) );
		WP_Mock::userFunction( 'wp_schedule_single_event', array( 'return' => true ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
		WP_Mock::userFunction( 'get_site_option', array( 'return' => array() ) );
		WP_Mock::userFunction( 'get_mu_plugins', array( 'return' => array() ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_migration_is_batched_idempotent_and_completes_all_foundation_phases() {
		$relationships = new DSF_Test_Migration_Relationships();
		$dependencies  = new DSF_Test_Migration_Dependencies();
		$provider      = static function ( $phase, $cursor ) {
			if ( 'posts' === $phase && 0 === $cursor ) {
				return array( array( 'cursor' => 10, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 10 ) );
			}
			if ( 'terms' === $phase && 0 === $cursor ) {
				return array( array( 'cursor' => 20, 'object_kind' => 'term', 'object_subtype' => 'category', 'object_id' => 3 ) );
			}
			if ( 'synthetic' === $phase && 0 === $cursor ) {
				return array( array( 'cursor' => 1, 'object_kind' => 'synthetic', 'object_subtype' => 'notification_bar', 'object_id' => 1 ) );
			}
			if ( 'dependencies' === $phase && 0 === $cursor ) {
				return array( array( 'cursor' => 10, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 10 ) );
			}
			return array();
		};

		$post            = new stdClass();
		$post->post_type = 'page';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => array() ) );

		$migration = new DSF_Multilingual_Migration( $relationships, $dependencies, $provider );
		$this->assertTrue( $migration->start() );

		$result = null;
		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$result = $migration->run_batch();
			if ( is_array( $result ) && 'complete' === $result['status'] ) {
				break;
			}
		}

		$this->assertIsArray( $result );
		$this->assertSame( 'complete', $result['status'] );
		$this->assertSame( 3, $relationships->created );
		$this->assertCount( 1, $dependencies->replacements );
		$this->assertSame( 'complete', $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_state'] );
		$this->assertSame( DSF_Multilingual_Settings::MIGRATION_VERSION, $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_version'] );

		$this->assertTrue( $migration->start() );
		$this->assertSame( 3, $relationships->created );
	}

	public function test_migration_failure_is_bounded_and_requires_explicit_restart() {
		$relationships          = new DSF_Test_Migration_Relationships();
		$relationships->failure = new WP_Error( 'database_write', 'Write failed.' );
		$dependencies           = new DSF_Test_Migration_Dependencies();
		$provider               = static function ( $phase, $cursor ) {
			return 'posts' === $phase && 0 === $cursor
				? array( array( 'cursor' => 7, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 7 ) )
				: array();
		};
		$migration              = new DSF_Multilingual_Migration( $relationships, $dependencies, $provider );

		$result = $migration->run_batch();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'failed', $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_state'] );
		$this->assertSame( 'database_write', $migration->get_progress()['last_error'] );
	}

	public function test_force_rescan_reopens_a_completed_idempotent_migration() {
		$settings                      = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['migration_state']   = 'complete';
		$settings['migration_version'] = DSF_Multilingual_Settings::MIGRATION_VERSION;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );
		$this->options[ DSF_Multilingual_Migration::STATE_OPTION ] = array(
			'phase'             => 'complete',
			'post_cursor'       => 90,
			'term_cursor'       => 80,
			'synthetic_cursor'  => 2,
			'dependency_cursor' => 70,
			'processed'         => 42,
			'last_error'        => '',
		);
		$migration = new DSF_Multilingual_Migration(
			new DSF_Test_Migration_Relationships(),
			new DSF_Test_Migration_Dependencies(),
			static function () {
				return array();
			}
		);

		$this->assertTrue( $migration->start( true ) );

		$state    = $this->options[ DSF_Multilingual_Migration::STATE_OPTION ];
		$settings = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$this->assertSame( 'posts', $state['phase'] );
		$this->assertSame( 0, $state['post_cursor'] );
		$this->assertSame( 0, $state['processed'] );
		$this->assertSame( 'pending', $settings['migration_state'] );
		$this->assertSame( 0, $settings['migration_version'] );
	}

	public function test_force_rescan_rebuilds_secondary_dependencies_under_secondary_language() {
		$settings                      = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['migration_state']   = 'complete';
		$settings['migration_version'] = DSF_Multilingual_Settings::MIGRATION_VERSION;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );
		$relationships = new DSF_Test_Migration_Relationships();
		$relationships->members['post:page:20'] = array(
			'group_uuid'     => '99999999-9999-4999-8999-999999999999',
			'object_kind'    => 'post',
			'object_subtype' => 'page',
			'object_id'      => 20,
			'language'       => 'es-MX',
		);
		$dependencies = new DSF_Test_Migration_Dependencies();
		$provider     = static function ( $phase, $cursor ) {
			return 'dependencies' === $phase && 0 === $cursor
				? array( array( 'cursor' => 20, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 20 ) )
				: array();
		};
		$post            = new stdClass();
		$post->post_type = 'page';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => array() ) );
		$migration = new DSF_Multilingual_Migration( $relationships, $dependencies, $provider );

		$this->assertTrue( $migration->start( true ) );
		$result = $migration->run_batch();

		$this->assertSame( 'running', $result['status'] );
		$this->assertCount( 1, $dependencies->replacements );
		$this->assertSame( 'es-MX', $dependencies->replacements[0]['language'] );
		$this->assertNotSame( 'en-US', $dependencies->replacements[0]['language'] );
	}

	public function test_force_rescan_withdraws_existing_secondary_post_until_route_phase() {
		$settings                      = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['migration_state']   = 'complete';
		$settings['migration_version'] = DSF_Multilingual_Settings::MIGRATION_VERSION;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );
		$relationships = new DSF_Test_Migration_Relationships();
		$member        = array(
			'group_uuid'     => '88888888-8888-4888-8888-888888888888',
			'object_kind'    => 'post',
			'object_subtype' => 'page',
			'object_id'      => 21,
			'language'       => 'es-MX',
		);
		$relationships->members['post:page:21'] = $member;
		$provider = static function ( $phase, $cursor ) {
			return 'posts' === $phase && 0 === $cursor
				? array( array( 'cursor' => 21, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 21 ) )
				: array();
		};
		WP_Mock::userFunction( 'get_post_status', array( 'return' => 'publish' ) );
		WP_Mock::onFilter( 'dsf_multilingual_route_is_valid' )
			->with( true, $member )
			->reply( false );
		$updates = array();
		WP_Mock::userFunction(
			'wp_update_post',
			array(
				'return' => static function ( $post_data, $wp_error = false ) use ( &$updates ) {
					$updates[] = array( $post_data, $wp_error );
					return $post_data['ID'];
				},
			)
		);
		$migration = new DSF_Multilingual_Migration( $relationships, new DSF_Test_Migration_Dependencies(), $provider );

		$this->assertTrue( $migration->start( true ) );
		$result = $migration->run_batch();

		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( array( 'ID' => 21, 'post_status' => 'draft' ), $updates[0][0] );
		$this->assertTrue( $updates[0][1] );
	}

	public function test_failed_migration_restart_preserves_saved_phase_cursors_and_processed_count() {
		$saved_state = array(
			'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
			'phase'             => 'terms',
			'post_cursor'       => 31,
			'term_cursor'       => 47,
			'synthetic_cursor'  => 1,
			'dependency_cursor' => 19,
			'processed'         => 23,
			'last_error'        => 'database_write',
		);
		$this->options[ DSF_Multilingual_Migration::STATE_OPTION ] = $saved_state;

		$settings                     = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['migration_state']  = 'failed';
		$settings['migration_cursor'] = 47;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );

		$migration = new DSF_Multilingual_Migration(
			new DSF_Test_Migration_Relationships(),
			new DSF_Test_Migration_Dependencies(),
			static function () {
				return array();
			}
		);

		$this->assertTrue( $migration->start() );

		$restarted_state = $this->options[ DSF_Multilingual_Migration::STATE_OPTION ];
		$this->assertSame( 'terms', $restarted_state['phase'] );
		$this->assertSame( 31, $restarted_state['post_cursor'] );
		$this->assertSame( 47, $restarted_state['term_cursor'] );
		$this->assertSame( 1, $restarted_state['synthetic_cursor'] );
		$this->assertSame( 19, $restarted_state['dependency_cursor'] );
		$this->assertSame( 23, $restarted_state['processed'] );
		$this->assertSame( '', $restarted_state['last_error'] );
		$this->assertSame( 'pending', $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_state'] );
		$this->assertSame( 47, $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_cursor'] );
	}

	public function test_relationship_missing_during_creation_is_a_safe_concurrent_deletion_skip() {
		$relationships          = new DSF_Test_Migration_Relationships();
		$relationships->failure = new WP_Error( 'dsf_translation_object_missing', 'The object was deleted concurrently.' );
		$provider               = static function ( $phase, $cursor ) {
			return 'posts' === $phase && 0 === $cursor
				? array( array( 'cursor' => 7, 'object_kind' => 'post', 'object_subtype' => 'page', 'object_id' => 7 ) )
				: array();
		};
		$migration              = new DSF_Multilingual_Migration( $relationships, new DSF_Test_Migration_Dependencies(), $provider );

		$result = $migration->run_batch();

		$this->assertIsArray( $result );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 'posts', $result['phase'] );
		$this->assertSame( 7, $result['cursor'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( 0, $relationships->created );
		$this->assertSame( '', $migration->get_progress()['last_error'] );
		$this->assertSame( 'running', $this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['migration_state'] );
	}

	public function test_running_migration_resumes_from_saved_cursor_in_the_configured_main_language() {
		$this->options[ DSF_Multilingual_Migration::STATE_OPTION ] = array(
			'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
			'phase'             => 'terms',
			'post_cursor'       => 101,
			'term_cursor'       => 22,
			'synthetic_cursor'  => 0,
			'dependency_cursor' => 0,
			'processed'         => 6,
			'last_error'        => '',
		);
		$settings                     = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['migration_state']  = 'running';
		$settings['migration_cursor'] = 22;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );

		$calls     = array();
		$provider  = static function ( $phase, $cursor ) use ( &$calls ) {
			$calls[] = array( $phase, $cursor );
			return 'terms' === $phase && 22 === $cursor
				? array( array( 'cursor' => 23, 'object_kind' => 'term', 'object_subtype' => 'category', 'object_id' => 8 ) )
				: array();
		};
		$relationships = new DSF_Test_Migration_Relationships();
		$migration     = new DSF_Multilingual_Migration( $relationships, new DSF_Test_Migration_Dependencies(), $provider );

		$this->assertTrue( $migration->start() );
		$result = $migration->run_batch();

		$this->assertSame( array( array( 'terms', 22 ) ), $calls );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 'terms', $result['phase'] );
		$this->assertSame( 23, $result['cursor'] );
		$this->assertSame( 7, $result['processed'] );
		$this->assertSame( 101, $this->options[ DSF_Multilingual_Migration::STATE_OPTION ]['post_cursor'] );
		$this->assertSame( 23, $this->options[ DSF_Multilingual_Migration::STATE_OPTION ]['term_cursor'] );
		$this->assertSame( 'en-US', $relationships->members['term:category:8']['language'] );
	}

	public function test_disabled_configuration_cannot_start_or_mutate_existing_objects() {
		$settings            = $this->options[ DSF_Multilingual_Settings::OPTION_NAME ];
		$settings['enabled'] = false;
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ] = DSF_Multilingual_Settings::sanitize_settings( $settings );
		$relationships = new DSF_Test_Migration_Relationships();
		$migration     = new DSF_Multilingual_Migration( $relationships, new DSF_Test_Migration_Dependencies(), static function () { return array(); } );

		$this->assertInstanceOf( WP_Error::class, $migration->start() );
		$this->assertSame( array( 'status' => 'disabled' ), $migration->run_batch() );
		$this->assertSame( 0, $relationships->created );
	}

	public function test_stale_version_state_cannot_skip_a_new_migration_scan() {
		$this->options[ DSF_Multilingual_Migration::STATE_OPTION ] = array(
			'migration_version' => 0,
			'phase'             => 'complete',
			'post_cursor'       => 500,
			'term_cursor'       => 400,
			'synthetic_cursor'  => 1008,
			'dependency_cursor' => 300,
			'processed'         => 99,
			'last_error'        => '',
		);
		$migration = new DSF_Multilingual_Migration(
			new DSF_Test_Migration_Relationships(),
			new DSF_Test_Migration_Dependencies(),
			static function () {
				return array();
			}
		);

		$this->assertTrue( $migration->start() );

		$state = $this->options[ DSF_Multilingual_Migration::STATE_OPTION ];
		$this->assertSame( DSF_Multilingual_Settings::MIGRATION_VERSION, $state['migration_version'] );
		$this->assertSame( 'posts', $state['phase'] );
		$this->assertSame( 0, $state['post_cursor'] );
		$this->assertSame( 0, $state['processed'] );
	}
}
