<?php
/**
 * Idempotent, batched migration of existing visitor-facing objects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Multilingual_Migration {

	const CRON_HOOK    = 'dsf_run_multilingual_migration';
	const STATE_OPTION = 'dsf_multilingual_migration';
	const LOCK_OPTION  = 'dsf_multilingual_migration_lock';
	const BATCH_SIZE   = 50;
	const LOCK_TTL     = 300;

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Translation_Dependencies */
	private $dependencies;

	/** @var callable|null */
	private $batch_provider;

	/**
	 * @param DSF_Translation_Relationships $relationships Relationship service.
	 * @param DSF_Translation_Dependencies  $dependencies Dependency service.
	 * @param callable|null                 $batch_provider Optional test provider.
	 */
	public function __construct( $relationships, $dependencies, $batch_provider = null ) {
		$this->relationships  = $relationships;
		$this->dependencies   = $dependencies;
		$this->batch_provider = is_callable( $batch_provider ) ? $batch_provider : null;
	}

	/** Register the bounded background worker. */
	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Start or resume the current migration without discarding completed work.
	 *
	 * @param bool $force_rescan Restart from the first phase even when the current schema completed.
	 * @return true|WP_Error
	 */
	public function start( $force_rescan = false ) {
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'dsf_multilingual_migration_disabled', __( 'Enable multilingual mode before starting its migration.', 'designstudio-flow' ) );
		}
		$force_rescan = true === $force_rescan;

		if ( ! $force_rescan && DSF_Multilingual_Settings::MIGRATION_VERSION === (int) $settings['migration_version'] && 'complete' === $settings['migration_state'] ) {
			return true;
		}

		$state = $force_rescan ? self::default_state() : $this->get_state();
		if (
			! $force_rescan
			&& (
				DSF_Multilingual_Settings::MIGRATION_VERSION !== (int) $state['migration_version']
				|| 'complete' === $state['phase']
			)
		) {
			// The summarized settings record is not the resumable cursor authority.
			// A code-version bump or an inconsistent completed cursor must begin at
			// the first phase so a stale STATE_OPTION cannot skip the new scan.
			$state = self::default_state();
		}
		if ( ! in_array( $state['phase'], array( 'posts', 'terms', 'synthetic', 'dependencies' ), true ) ) {
			$state = self::default_state();
		}

		$state['last_error'] = '';
		update_option( self::STATE_OPTION, $state, false );
		$this->set_settings_state( 'pending', $this->state_cursor( $state ), 0 );
		$this->schedule_next();
		return true;
	}

	/** Schedule unfinished work after plugin initialization. */
	public function maybe_schedule() {
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$state = $this->get_state();
		if (
			DSF_Multilingual_Settings::MIGRATION_VERSION !== (int) $state['migration_version']
			|| (
				in_array( $settings['migration_state'], array( 'not_started', 'pending', 'running' ), true )
				&& 'complete' === $state['phase']
			)
		) {
			$this->start();
			return;
		}

		if ( in_array( $settings['migration_state'], array( 'not_started', 'pending', 'running' ), true ) ) {
			$this->schedule_next();
		}
	}

	/**
	 * Process one bounded batch.
	 *
	 * @return array|WP_Error Progress result.
	 */
	public function run_batch() {
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return array( 'status' => 'disabled' );
		}
		if ( class_exists( 'DSF_Multilingual_Conflicts' ) && DSF_Multilingual_Conflicts::has_conflicts() ) {
			return $this->fail( 'conflict', __( 'Migration paused because another multilingual plugin is active.', 'designstudio-flow' ) );
		}
		if ( ! $this->acquire_lock() ) {
			return array( 'status' => 'locked' );
		}

		try {
			$state = $this->get_state();
			$this->set_settings_state( 'running', $this->state_cursor( $state ), 0 );

			while ( true ) {
				$items = $this->load_batch( $state['phase'], $this->state_cursor( $state ), self::BATCH_SIZE );
				if ( $items instanceof WP_Error ) {
					return $this->fail( $items->get_error_code(), $items->get_error_message(), $state );
				}

				if ( empty( $items ) ) {
					$next = $this->next_phase( $state['phase'] );
					if ( '' === $next ) {
						$state['phase']      = 'complete';
						$state['last_error'] = '';
						update_option( self::STATE_OPTION, $state, false );
						$this->set_settings_state( 'complete', 0, DSF_Multilingual_Settings::MIGRATION_VERSION );
						return array(
							'status'    => 'complete',
							'processed' => $state['processed'],
						);
					}
					$state['phase'] = $next;
					$this->set_state_cursor( $state, 0 );
					update_option( self::STATE_OPTION, $state, false );
					continue;
				}

				foreach ( $items as $item ) {
					$result = 'dependencies' === $state['phase']
						? $this->migrate_dependencies( $item )
						: $this->migrate_object( $item, $settings['main_language'] );
					if ( $result instanceof WP_Error ) {
						return $this->fail( $result->get_error_code(), $result->get_error_message(), $state );
					}
					++$state['processed'];
					$this->set_state_cursor( $state, absint( $item['cursor'] ?? $item['object_id'] ?? 0 ) );
				}

				update_option( self::STATE_OPTION, $state, false );
				$this->set_settings_state( 'running', $this->state_cursor( $state ), 0 );
				$this->schedule_next();
				return array(
					'status'    => 'running',
					'phase'     => $state['phase'],
					'cursor'    => $this->state_cursor( $state ),
					'processed' => $state['processed'],
				);
			}
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/** Return a sanitized public progress record. */
	public function get_progress() {
		$state = $this->get_state();
		return array(
			'phase'      => $state['phase'],
			'processed'  => $state['processed'],
			'last_error' => $state['last_error'],
		);
	}

	/** Migrate one post/term/synthetic identity idempotently. */
	private function migrate_object( $item, $language ) {
		$kind      = sanitize_key( $item['object_kind'] ?? '' );
		$subtype   = sanitize_key( $item['object_subtype'] ?? '' );
		$object_id = absint( $item['object_id'] ?? 0 );
		if ( ! $kind || ! $subtype || ! $object_id ) {
			return new WP_Error( 'dsf_multilingual_migration_item', __( 'Migration encountered an invalid object identity.', 'designstudio-flow' ) );
		}

		$existing = $this->relationships->find_by_object( $kind, $subtype, $object_id );
		if ( $existing instanceof WP_Error ) {
			return $existing;
		}
		if ( is_array( $existing ) ) {
			return $this->reconcile_existing_member( $existing, $kind, $object_id, $language );
		}

		$created = $this->relationships->create_group( $kind, $subtype, $object_id, $language );
		if ( $created instanceof WP_Error && 'dsf_translation_object_missing' === $created->get_error_code() ) {
			// A bounded batch can race with a legitimate delete/retype. The next
			// stable cursor remains valid, and there is no object left to assign.
			return true;
		}
		if ( $created instanceof WP_Error && 'dsf_translation_object_exists' === $created->get_error_code() ) {
			// A post/term creation hook can assign the object after the preflight
			// SELECT but before our INSERT. Re-read the unique-index winner and
			// accept only the exact object identity that this batch was assigning.
			$winner = $this->relationships->find_by_object( $kind, $subtype, $object_id );
			if ( is_array( $winner ) ) {
				return $this->reconcile_existing_member( $winner, $kind, $object_id, $language );
			}
			return $winner instanceof WP_Error ? $winner : $created;
		}
		return $created;
	}

	/** Accept an exact existing/race-winning member and enforce foundation privacy. */
	private function reconcile_existing_member( $member, $kind, $object_id, $main_language ) {
		if (
			'post' === $kind
			&& ( $member['language'] ?? '' ) !== $main_language
			&& 'publish' === get_post_status( $object_id )
			&& ! apply_filters( 'dsf_multilingual_route_is_valid', true, $member )
		) {
			$updated = wp_update_post(
				array(
					'ID'          => $object_id,
					'post_status' => 'draft',
				),
				true
			);
			if ( $updated instanceof WP_Error ) {
				return $updated;
			}
		}
		return true;
	}

	/** Rebuild explicit portable dependencies after every object has a group. */
	private function migrate_dependencies( $item ) {
		$post_id = absint( $item['object_id'] ?? 0 );
		if ( ! get_post( $post_id ) ) {
			return true;
		}
		$owner = $this->relationships->find_by_object( 'post', sanitize_key( $item['object_subtype'] ?? '' ), $post_id );
		if ( $owner instanceof WP_Error ) {
			return $owner;
		}
		if ( ! is_array( $owner ) ) {
			if ( ! get_post( $post_id ) ) {
				return true;
			}
			$settings = DSF_Multilingual_Settings::get_settings();
			$assigned = $this->migrate_object( $item, $settings['main_language'] );
			if ( $assigned instanceof WP_Error ) {
				return $assigned;
			}
			$owner = $this->relationships->find_by_object( 'post', sanitize_key( $item['object_subtype'] ?? '' ), $post_id );
			if ( ! is_array( $owner ) ) {
				return ! get_post( $post_id )
					? true
					: new WP_Error( 'dsf_multilingual_migration_owner', __( 'A dependency owner has no translation relationship.', 'designstudio-flow' ) );
			}
		}
		$owner_language = DSF_Multilingual_Settings::normalize_locale_code( $owner['language'] ?? '' );
		if ( '' === $owner_language ) {
			return new WP_Error( 'dsf_multilingual_migration_owner_language', __( 'A dependency owner has an invalid language relationship.', 'designstudio-flow' ) );
		}
		$configured_languages = DSF_Multilingual_Settings::get_enabled_language_codes();
		if ( ! in_array( $owner_language, $configured_languages, true ) ) {
			// Preserve recoverable rows for a language removed from the active
			// configuration; never rewrite them under the main-language key.
			return true;
		}

		$definitions = DSF_Multilingual_Adapters::post_dependencies( $post_id, $owner_language );
		if ( $definitions instanceof WP_Error ) {
			return $definitions;
		}

		$edges = array();
		foreach ( $definitions as $dependency ) {
			$member = $this->relationships->find_by_object(
				$dependency['object_kind'],
				$dependency['object_subtype'],
				$dependency['object_id']
			);
			if ( $member instanceof WP_Error ) {
				return $member;
			}
			if ( ! is_array( $member ) ) {
				// The publish gate separately checks unresolved raw references. An
				// invented UUID here would turn recoverable content into corrupt data.
				continue;
			}
			$edges[] = array(
				'dependency_group_uuid' => $member['group_uuid'],
				'kind'                  => $dependency['dependency_kind'],
				'source_path'           => $dependency['path'],
				'required'              => ! empty( $dependency['required'] ),
			);
		}

		$result = $this->dependencies->replace_edges(
			$owner['group_uuid'],
			$owner_language,
			$edges,
			static function () {
				return true;
			}
		);
		if ( ! ( $result instanceof WP_Error ) ) {
			return $result;
		}

		// Deletion can win after the owner read but before edge replacement.
		// Swallow only that verified disappearance; preserve real storage errors.
		$current = $this->relationships->find_by_object( 'post', sanitize_key( $item['object_subtype'] ?? '' ), $post_id );
		if ( null === $current && ! get_post( $post_id ) ) {
			return true;
		}
		return $current instanceof WP_Error ? $current : $result;
	}

	/** Load one stable-ID batch from WordPress storage. */
	private function load_batch( $phase, $cursor, $limit ) {
		if ( $this->batch_provider ) {
			return call_user_func( $this->batch_provider, $phase, $cursor, $limit );
		}

		global $wpdb;
		$limit  = max( 1, min( self::BATCH_SIZE, absint( $limit ) ) );
		$cursor = absint( $cursor );

		if ( in_array( $phase, array( 'posts', 'dependencies' ), true ) ) {
			$post_types = DSF_Multilingual_Adapters::relationship_post_types();
			if ( empty( $post_types ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$args         = array_merge( array( $cursor ), $post_types, array( $limit ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count and matching argument array are built from the server-owned post-type allowlist.
			$sql = $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ({$placeholders}) AND post_status NOT IN ('auto-draft','trash','inherit') ORDER BY ID ASC LIMIT %d", $args );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared and migration state must be current.
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! is_array( $rows ) ) {
				return new WP_Error( 'dsf_multilingual_migration_query', __( 'Existing posts could not be loaded for migration.', 'designstudio-flow' ) );
			}
			return array_map(
				static function ( $row ) {
					return array(
						'cursor'         => absint( $row['ID'] ?? 0 ),
						'object_kind'    => 'post',
						'object_subtype' => sanitize_key( $row['post_type'] ?? '' ),
						'object_id'      => absint( $row['ID'] ?? 0 ),
					);
				},
				$rows
			);
		}

		if ( 'terms' === $phase ) {
			$taxonomies = DSF_Multilingual_Adapters::relationship_taxonomies();
			if ( empty( $taxonomies ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
			$args         = array_merge( array( $cursor ), $taxonomies, array( $limit ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count and matching argument array are built from the server-owned taxonomy allowlist.
			$sql = $wpdb->prepare( "SELECT term_taxonomy_id, term_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id > %d AND taxonomy IN ({$placeholders}) ORDER BY term_taxonomy_id ASC LIMIT %d", $args );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared and migration state must be current.
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! is_array( $rows ) ) {
				return new WP_Error( 'dsf_multilingual_migration_query', __( 'Existing terms could not be loaded for migration.', 'designstudio-flow' ) );
			}
			return array_map(
				static function ( $row ) {
					return array(
						'cursor'         => absint( $row['term_taxonomy_id'] ?? 0 ),
						'object_kind'    => 'term',
						'object_subtype' => sanitize_key( $row['taxonomy'] ?? '' ),
						'object_id'      => absint( $row['term_id'] ?? 0 ),
					);
				},
				$rows
			);
		}

		if ( 'synthetic' === $phase && 0 === $cursor ) {
			$settings = DSF_Multilingual_Settings::get_settings();
			return array(
				array(
					'cursor'         => 1,
					'object_kind'    => 'synthetic',
					'object_subtype' => 'notification_bar',
					'object_id'      => DSF_Multilingual_Adapters::synthetic_notification_id( $settings['main_language'] ),
				),
			);
		}

		return array();
	}

	/** Acquire an option-backed lock with stale-lock recovery. */
	private function acquire_lock() {
		$now = time();
		if ( add_option( self::LOCK_OPTION, $now, '', 'no' ) ) {
			return true;
		}
		$locked_at = absint( get_option( self::LOCK_OPTION, 0 ) );
		if ( $locked_at && $locked_at + self::LOCK_TTL >= $now ) {
			return false;
		}
		delete_option( self::LOCK_OPTION );
		return (bool) add_option( self::LOCK_OPTION, $now, '', 'no' );
	}

	/** Schedule one non-blocking continuation. */
	private function schedule_next() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/** Persist the summarized state inside the validated settings option. */
	private function set_settings_state( $migration_state, $cursor, $version ) {
		$settings                      = DSF_Multilingual_Settings::get_settings();
		$settings['migration_state']   = $migration_state;
		$settings['migration_cursor']  = max( 0, (int) $cursor );
		$settings['migration_version'] = max( 0, (int) $version );
		update_option(
			DSF_Multilingual_Settings::OPTION_NAME,
			DSF_Multilingual_Settings::sanitize_settings( $settings ),
			false
		);
	}

	/** Mark the migration failed without losing its idempotent cursor. */
	private function fail( $code, $message, $state = null ) {
		$state               = is_array( $state ) ? $state : $this->get_state();
		$state['last_error'] = sanitize_key( $code );
		update_option( self::STATE_OPTION, $state, false );
		$this->set_settings_state( 'failed', $this->state_cursor( $state ), 0 );
		return new WP_Error( 'dsf_multilingual_migration_failed', $message );
	}

	/** Read and reconstruct bounded progress data. */
	private function get_state() {
		$raw      = get_option( self::STATE_OPTION, array() );
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::default_state();
		$phase    = sanitize_key( $raw['phase'] ?? $defaults['phase'] );
		if ( ! in_array( $phase, array( 'posts', 'terms', 'synthetic', 'dependencies', 'complete' ), true ) ) {
			$phase = 'posts';
		}
		return array(
			'migration_version' => array_key_exists( 'migration_version', $raw )
				? max( 0, (int) $raw['migration_version'] )
				: ( empty( $raw ) ? $defaults['migration_version'] : 0 ),
			'phase'             => $phase,
			'post_cursor'       => absint( $raw['post_cursor'] ?? 0 ),
			'term_cursor'       => absint( $raw['term_cursor'] ?? 0 ),
			'synthetic_cursor'  => absint( $raw['synthetic_cursor'] ?? 0 ),
			'dependency_cursor' => absint( $raw['dependency_cursor'] ?? 0 ),
			'processed'         => min( PHP_INT_MAX, max( 0, (int) ( $raw['processed'] ?? 0 ) ) ),
			'last_error'        => sanitize_key( $raw['last_error'] ?? '' ),
		);
	}

	/** Default resumable state. */
	private static function default_state() {
		return array(
			'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
			'phase'             => 'posts',
			'post_cursor'       => 0,
			'term_cursor'       => 0,
			'synthetic_cursor'  => 0,
			'dependency_cursor' => 0,
			'processed'         => 0,
			'last_error'        => '',
		);
	}

	/** Current phase cursor. */
	private function state_cursor( $state ) {
		$key = $this->cursor_key( $state['phase'] ?? '' );
		return $key ? absint( $state[ $key ] ?? 0 ) : 0;
	}

	/** Update current phase cursor. */
	private function set_state_cursor( &$state, $cursor ) {
		$key = $this->cursor_key( $state['phase'] ?? '' );
		if ( $key ) {
			$state[ $key ] = absint( $cursor );
		}
	}

	/** Cursor field for a migration phase. */
	private function cursor_key( $phase ) {
		$map = array(
			'posts'        => 'post_cursor',
			'terms'        => 'term_cursor',
			'synthetic'    => 'synthetic_cursor',
			'dependencies' => 'dependency_cursor',
		);
		return $map[ $phase ] ?? '';
	}

	/** Deterministic phase order. */
	private function next_phase( $phase ) {
		$map = array(
			'posts'        => 'terms',
			'terms'        => 'synthetic',
			'synthetic'    => 'dependencies',
			'dependencies' => '',
		);
		return $map[ $phase ] ?? '';
	}
}
