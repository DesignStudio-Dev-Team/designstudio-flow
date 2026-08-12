<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * Covers the language boundary on saved-block synchronization.
 *
 * Global sync is the one place where editing a single object rewrites many
 * others, so it is also the one place where an English edit could silently
 * overwrite reviewed Spanish copy. It must not.
 */
class Test_DSF_Saved_Block_Language_Sync extends TestCase {

	/** @var array<int,array<string,mixed>> */
	private $meta = array();

	/** @var array<int,string> */
	private $types = array();

	/** @var array<int,string> */
	private $languages = array();

	/** @var array<int,array> */
	private $written = array();

	/** @var array<int,int> */
	private $snapshots_cleared = array();

	/** @var mixed */
	private $previous_history = null;

	/** @var bool */
	private $history_swapped = false;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->written           = array();
		$this->snapshots_cleared = array();

		// The sync path records history for every object it rewrites. When the
		// history class is loaded by another test, swap the singleton so these
		// assertions do not depend on a history table.
		$this->swap_history();

		$english_block = array(
			'type'         => 'content',
			'savedBlockId' => 70,
			'settings'     => array( 'content' => '<p>Original English</p>' ),
		);
		$spanish_block = array(
			'type'         => 'content',
			'savedBlockId' => 70,
			'settings'     => array( 'content' => '<p>Reviewed Spanish</p>' ),
		);

		$this->meta      = array(
			10 => array( '_dsf_blocks' => array( $english_block ) ),
			11 => array( '_dsf_blocks' => array( $spanish_block ) ),
			12 => array( '_dsf_blocks' => array( $english_block ) ),
		);
		$this->types     = array(
			10 => 'page',
			11 => 'page',
			12 => 'dsf_layout',
		);
		$this->languages = array(
			10 => 'en-US',
			11 => 'es-MX',
			12 => 'en-US',
			70 => 'en-US',
		);

		$meta      = &$this->meta;
		$types     = &$this->types;
		$written   = &$this->written;
		$snapshots = &$this->snapshots_cleared;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
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
			'is_wp_error',
			array(
				'return' => static function ( $v ) {
					return $v instanceof WP_Error;
				},
			)
		);
		WP_Mock::userFunction( 'get_posts', array( 'return' => array( 10, 11, 12 ) ) );
		WP_Mock::userFunction(
			'get_post_type',
			array(
				'return' => static function ( $id ) use ( &$types ) {
					return $types[ (int) $id ] ?? 'page';
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $id, $key = '', $single = false ) use ( &$meta ) {
						unset( $single );
						return $meta[ (int) $id ][ $key ] ?? '';
				},
			)
		);
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'return' => static function ( $id, $key, $value ) use ( &$meta, &$written ) {
						$meta[ (int) $id ][ $key ] = $value;
						$written[ (int) $id ]      = $value;
						return true;
				},
			)
		);
		WP_Mock::userFunction(
			'delete_post_meta',
			array(
				'return' => static function ( $id, $key ) use ( &$meta, &$snapshots ) {
						unset( $meta[ (int) $id ][ $key ] );
					if ( '_dsf_html_snapshot' === $key ) {
						$snapshots[] = (int) $id;
					}
						return true;
				},
			)
		);
	}

	public function tearDown(): void {
		if ( $this->history_swapped ) {
			$instance = new ReflectionProperty( 'DSF_History', 'instance' );
			$instance->setAccessible( true );
			$instance->setValue( null, $this->previous_history );
		}

		WP_Mock::tearDown();
		parent::tearDown();
	}

	/** Replace the history singleton with an inert double when it is loaded. */
	private function swap_history() {
		if ( ! class_exists( 'DSF_History', false ) ) {
			return;
		}

		$double = new class() extends DSF_History {
			// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Test double.
			public function __construct() {}

			// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Test double.
			public function proposed_post_payload( $post_id, $overrides ) {
				unset( $post_id );
				return is_array( $overrides ) ? $overrides : array();
			}

			// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Test double.
			public function capture_before_post_mutation( $post_id, $post_type, $next_payload, $reason = 'save' ) {
				unset( $post_id, $post_type, $next_payload, $reason );
				return true;
			}
		};

		$instance = new ReflectionProperty( 'DSF_History', 'instance' );
		$instance->setAccessible( true );
		$this->previous_history = $instance->getValue();
		$instance->setValue( null, $double );
		$this->history_swapped = true;
	}

	/**
	 * Run the private sync with an injected language resolver.
	 *
	 * @param callable $resolver Language resolver.
	 * @return mixed
	 */
	private function sync( $resolver ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sync_saved_block_instances' );
		$method->setAccessible( true );

		return $method->invokeArgs(
			$ajax,
			array( 70, array( 'content' => '<p>Updated English</p>' ), $resolver )
		);
	}

	/**
	 * Resolver mirroring the relationship table.
	 *
	 * @return callable
	 */
	private function language_resolver() {
		$languages = $this->languages;
		return static function ( $kind, $subtype, $id ) use ( $languages ) {
			unset( $kind, $subtype );
			return $languages[ (int) $id ] ?? null;
		};
	}

	public function test_only_same_language_instances_are_rewritten() {
		$synced = $this->sync( $this->language_resolver() );

		$this->assertSame( 2, $synced );
		$this->assertSame( '<p>Updated English</p>', $this->meta[10]['_dsf_blocks'][0]['settings']['content'] );
		$this->assertSame( '<p>Updated English</p>', $this->meta[12]['_dsf_blocks'][0]['settings']['content'] );
		$this->assertSame(
			'<p>Reviewed Spanish</p>',
			$this->meta[11]['_dsf_blocks'][0]['settings']['content'],
			'An English saved block must never overwrite reviewed Spanish copy.'
		);
		$this->assertArrayNotHasKey( 11, $this->written );
	}

	public function test_only_rewritten_objects_lose_their_snapshot() {
		$this->sync( $this->language_resolver() );

		$this->assertSame( array( 10, 12 ), $this->snapshots_cleared );
		$this->assertNotContains( 11, $this->snapshots_cleared );
	}

	public function test_a_single_language_site_still_syncs_everything() {
		// No relationships exist, so no language boundary applies.
		$synced = $this->sync(
			static function () {
				return null;
			}
		);

		$this->assertSame( 3, $synced );
		$this->assertSame( '<p>Updated English</p>', $this->meta[11]['_dsf_blocks'][0]['settings']['content'] );
	}

	public function test_an_unassigned_saved_block_does_not_block_synchronization() {
		$languages = $this->languages;
		$synced    = $this->sync(
			static function ( $kind, $subtype, $id ) use ( $languages ) {
				unset( $kind, $subtype );
				// The saved block itself has no relationship yet.
				return 70 === (int) $id ? null : ( $languages[ (int) $id ] ?? null );
			}
		);

		$this->assertSame( 3, $synced );
	}
}
