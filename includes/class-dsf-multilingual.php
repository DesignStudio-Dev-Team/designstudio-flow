<?php
/**
 * Multilingual foundation coordinator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Multilingual {
	const NETWORK_ACTIVATION_EPOCH_OPTION = 'dsf_multilingual_network_activation_epoch';
	const SITE_ACTIVATION_EPOCH_OPTION    = 'dsf_multilingual_site_activation_epoch';

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Translation_Workflow */
	private $workflow;

	/** @var DSF_Translation_Dependencies */
	private $dependencies;

	/** @var DSF_Translation_Publish_Gate */
	private $publish_gate;

	/** @var DSF_Multilingual_Migration */
	private $migration;

	/** @var DSF_Translation_Routes */
	private $routes;

	/** @var DSF_Language_Context */
	private $language_context;

	/** @var DSF_Language_Routing */
	private $language_routing;

	/** @var DSF_Language_SEO */
	private $language_seo;

	/** @var DSF_Language_Switcher */
	private $language_switcher;

	/** @var DSF_Translation_Overlays */
	private $overlays;

	/** Return the shared coordinator. */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Install every foundation table during plugin activation. */
	public static function install() {
		DSF_Translation_Relationships::install();
		DSF_Translation_Routes::install();
		DSF_Translation_Workflow::install();
		DSF_Translation_Dependencies::install();
	}

	/** Construct services and register security boundaries before `init`. */
	private function __construct() {
		add_filter( 'dsf_translation_relationship_languages', array( $this, 'relationship_languages' ) );
		add_filter( 'dsf_translation_relationship_object_is_valid', array( $this, 'validate_relationship_object' ), 10, 2 );

		$this->relationships = DSF_Translation_Relationships::get_instance();
		$this->relationships->register_adapter( 'post', DSF_Multilingual_Adapters::relationship_post_types() );
		$this->relationships->register_adapter( 'term', DSF_Multilingual_Adapters::relationship_taxonomies() );
		$this->relationships->register_adapter( DSF_Translation_Overlays::KIND, DSF_Translation_Overlays::subtypes() );

		$language_validator = array( $this, 'is_language_enabled' );
		$this->workflow     = new DSF_Translation_Workflow(
			array(
				'language_validator' => $language_validator,
			)
		);
		$this->publish_gate = new DSF_Translation_Publish_Gate(
			array(
				'relationships' => $this->relationships,
				'workflow'      => $this->workflow,
			)
		);
		$this->dependencies = new DSF_Translation_Dependencies(
			array(
				'language_validator'   => $language_validator,
				'member_resolver'      => array( $this->relationships, 'find_member' ),
				'eligibility_resolver' => array( $this->publish_gate, 'dependency_member_is_eligible' ),
			)
		);
		$this->publish_gate->set_dependencies( $this->dependencies );
		$this->migration = new DSF_Multilingual_Migration( $this->relationships, $this->dependencies );

		$this->routes           = DSF_Translation_Routes::get_instance();
		$this->language_context = DSF_Language_Context::get_instance();
		$this->language_routing = DSF_Language_Routing::get_instance();
		$this->language_seo     = DSF_Language_SEO::get_instance();
		$this->language_switcher = DSF_Language_Switcher::get_instance();
		$this->overlays          = DSF_Translation_Overlays::get_instance();

		$this->register_hooks();
	}

	/** Register integration hooks without adding later-phase routing or UI. */
	private function register_hooks() {
		// Schema self-heal reads four non-autoloaded version options. Frontend page
		// views can neither need nor apply an upgrade, so it is deferred to
		// admin/cron requests (the tables themselves are built at activation).
		if ( DSF_Runtime::is_maintenance_request() ) {
			add_action( 'init', array( $this, 'maybe_install' ), 1 );
		}
		add_action( 'switch_blog', array( $this, 'handle_switched_blog' ), 10, 3 );
		// Reads the non-autoloaded migration-state option to decide whether to queue
		// a cron batch. Only admin/cron requests can usefully act on that.
		if ( DSF_Runtime::is_maintenance_request() ) {
			add_action( 'init', array( $this->migration, 'maybe_schedule' ), 20 );
		}
		add_action( 'wp_after_insert_post', array( $this, 'ensure_post_relationship' ), 10, 4 );
		add_action( 'created_term', array( $this, 'ensure_term_relationship' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'ensure_term_relationship' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'handle_term_edited' ), 20, 4 );
		add_action( 'before_delete_post', array( $this, 'remove_post_relationship' ), 10, 2 );
		add_action( 'delete_term', array( $this, 'remove_term_relationship' ), 10, 4 );
		add_action( 'post_updated', array( $this, 'handle_post_updated' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'handle_object_terms' ), 10, 6 );
		add_action( 'added_post_meta', array( $this, 'handle_fingerprint_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'handle_fingerprint_meta_change' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'handle_fingerprint_meta_change' ), 10, 4 );
		add_action( 'update_option_' . DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION, array( $this, 'handle_notification_translations_updated' ), 10, 3 );
		add_action( 'add_option_' . DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION, array( $this, 'handle_notification_translations_added' ), 10, 2 );
		add_action( 'delete_option', array( $this, 'handle_option_before_delete' ) );
		add_action( 'admin_notices', array( $this, 'render_foundation_notices' ) );
		add_filter( 'dsf_multilingual_route_is_valid', array( $this, 'foundation_route_is_valid' ), 5, 2 );
		add_filter( 'dsf_default_layout_cascade_allows_post', array( $this, 'default_layout_cascade_allows_post' ), 10, 2 );

		$this->migration->register_hooks();
		$this->publish_gate->register_hooks();
		$this->language_context->register_hooks();
		$this->language_routing->register_hooks();
		$this->language_seo->register_hooks();
		$this->language_switcher->register_hooks();
		$this->overlays->register_hooks();
	}

	/** Idempotently upgrade tables outside activation-only code paths. */
	public function maybe_install() {
		$this->ensure_settings_autoload();
		$this->relationships->maybe_install();
		$this->routes->maybe_install();
		if ( version_compare( (string) get_option( 'dsf_translation_workflow_db_version', '' ), DSF_Translation_Workflow::DB_VERSION, '<' ) ) {
			DSF_Translation_Workflow::install();
		}
		if ( version_compare( (string) get_option( 'dsf_translation_dependencies_db_version', '' ), DSF_Translation_Dependencies::DB_VERSION, '<' ) ) {
			DSF_Translation_Dependencies::install();
		}
		$this->reconcile_network_activation();
	}

	/**
	 * Keep the multilingual settings in WordPress's autoloaded option set.
	 *
	 * Language routing reads these settings on every frontend request, so storing
	 * them with autoload off cost one dedicated query per page view — on every
	 * site, whether or not multilingual is switched on. Installs created before
	 * this ran stored the option as autoload=off, so it is corrected here (a
	 * no-op once the flag already matches).
	 */
	private function ensure_settings_autoload() {
		if ( ! function_exists( 'wp_set_option_autoload' ) ) {
			return;
		}
		wp_set_option_autoload( DSF_Multilingual_Settings::OPTION_NAME, true );
	}

	/** Reconcile each multisite lazily after a network-wide reactivation. */
	private function reconcile_network_activation() {
		if ( ! is_multisite() ) {
			return;
		}
		$network_epoch = absint( get_site_option( self::NETWORK_ACTIVATION_EPOCH_OPTION, 0 ) );
		$site_epoch    = absint( get_option( self::SITE_ACTIVATION_EPOCH_OPTION, 0 ) );
		if ( ! $network_epoch || $network_epoch === $site_epoch ) {
			return;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( ! empty( $settings['enabled'] ) ) {
			$result = $this->migration->start( true );
			if ( $result instanceof WP_Error ) {
				return;
			}
		}
		update_option( self::SITE_ACTIVATION_EPOCH_OPTION, $network_epoch, false );
	}

	/** Mark the current site reconciled during the network activation request. */
	public function mark_current_site_activation_epoch() {
		if ( ! is_multisite() ) {
			return;
		}
		$epoch = absint( get_site_option( self::NETWORK_ACTIVATION_EPOCH_OPTION, 0 ) );
		if ( $epoch ) {
			update_option( self::SITE_ACTIVATION_EPOCH_OPTION, $epoch, false );
		}
	}

	/** Ensure site-specific tables exist after code switches to another blog. */
	public function handle_switched_blog( $new_blog_id, $previous_blog_id, $context = '' ) {
		unset( $new_blog_id, $previous_blog_id, $context );
		$this->maybe_install();
	}

	/** Curated languages accepted by the relationship authority. */
	public function relationship_languages() {
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return array( $settings['main_language'] );
		}
		return DSF_Multilingual_Settings::get_enabled_language_codes( $settings );
	}

	/** Strict configured-language callback used by recoverable workflow state. */
	public function is_language_enabled( $language ) {
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		$settings = DSF_Multilingual_Settings::get_settings();
		return '' !== $language && in_array( $language, DSF_Multilingual_Settings::get_enabled_language_codes( $settings ), true );
	}

	/** Validate a new relationship against its actual adapter object. */
	public function validate_relationship_object( $valid, $identity ) {
		if ( $valid instanceof WP_Error || ! is_array( $identity ) ) {
			return $valid;
		}
		return DSF_Multilingual_Adapters::object_exists(
			$identity['object_kind'] ?? '',
			$identity['object_subtype'] ?? '',
			$identity['object_id'] ?? 0,
			$identity['language'] ?? ''
		);
	}

	/** Assign a new native post to the main language unless a trusted cloner opts out. */
	public function ensure_post_relationship( $post_id, $post, $update, $post_before ) {
		unset( $update, $post_before );
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) || ! is_object( $post ) || ! isset( $post->post_type, $post->post_status ) ) {
			return;
		}
		$post_type = sanitize_key( $post->post_type );
		if ( ! in_array( $post_type, DSF_Multilingual_Adapters::relationship_post_types(), true ) || in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}

		$existing = $this->relationships->find_by_object( 'post', $post_type, absint( $post_id ) );
		if ( is_array( $existing ) || $existing instanceof WP_Error ) {
			return;
		}

		/**
		 * Filter the initial language for a newly created native object.
		 *
		 * A future clone service must return an empty value here, then atomically
		 * add the new object to the source group in the requested language.
		 */
		$language = apply_filters( 'dsf_multilingual_new_post_language', $settings['main_language'], absint( $post_id ), $post );
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		if ( '' === $language || ! $this->is_language_enabled( $language ) ) {
			return;
		}

		$created = $this->relationships->create_group( 'post', $post_type, absint( $post_id ), $language );
		if ( is_array( $created ) ) {
			$this->sync_post_dependencies( absint( $post_id ) );
		}
	}

	/** Assign native terms to the main language under the same safe default. */
	public function ensure_term_relationship( $term_id, $term_taxonomy_id, $taxonomy ) {
		unset( $term_taxonomy_id );
		$settings = DSF_Multilingual_Settings::get_settings();
		$taxonomy = sanitize_key( $taxonomy );
		if ( empty( $settings['enabled'] ) || ! in_array( $taxonomy, DSF_Multilingual_Adapters::relationship_taxonomies(), true ) ) {
			return;
		}
		$existing = $this->relationships->find_by_object( 'term', $taxonomy, absint( $term_id ) );
		if ( is_array( $existing ) || $existing instanceof WP_Error ) {
			return;
		}
		$language = apply_filters( 'dsf_multilingual_new_term_language', $settings['main_language'], absint( $term_id ), $taxonomy );
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		if ( '' !== $language && $this->is_language_enabled( $language ) ) {
			$this->relationships->create_group( 'term', $taxonomy, absint( $term_id ), $language );
		}
	}

	/** Rebuild portable edges after a post's explicit relationship fields change. */
	public function sync_post_dependencies( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}
		$owner = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		if ( ! is_array( $owner ) ) {
			return false;
		}

		$existing = $this->dependencies->get_edges( $owner['group_uuid'], $owner['language'] );
		if ( $existing instanceof WP_Error ) {
			return $existing;
		}
		$edges  = $this->build_post_dependency_edges( $post_id );
		if ( $edges instanceof WP_Error ) {
			return $edges;
		}
		$result = $this->dependencies->replace_edges(
			$owner['group_uuid'],
			$owner['language'],
			$edges,
			static function () {
				return true;
			}
		);
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		return $this->dependency_signatures( $existing ) !== $this->dependency_signatures( $edges );
	}

	/** Clear review and synchronize edges when relevant Flow meta changes. */
	public function handle_fingerprint_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || ! in_array( $meta_key, DSF_Multilingual_Adapters::fingerprint_meta_keys( $post->post_type ?? '' ), true ) ) {
			return;
		}
		$this->ensure_post_relationship( $post_id, $post, true, null );
		$dependencies_changed = $this->sync_post_dependencies( $post_id );
		$structural_meta       = in_array( $meta_key, array( '_dsf_layout_type', '_dsf_pt_assignment', '_dsf_st_assignment', '_dsf_bt_assignment' ), true );
		if ( $structural_meta || true === $dependencies_changed || $dependencies_changed instanceof WP_Error ) {
			$this->mark_source_group_critical( $post_id );
		}
		$this->invalidate_secondary_review( $post_id, false, false );
	}

	/** Clear review after translated title, slug, excerpt or content changes. */
	public function handle_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! is_object( $post_after ) || ! is_object( $post_before ) ) {
			return;
		}
		$title_changed  = (string) $post_after->post_title !== (string) $post_before->post_title;
		$slug_changed   = (string) $post_after->post_name !== (string) $post_before->post_name;
		$body_changed   = (string) $post_after->post_excerpt !== (string) $post_before->post_excerpt
			|| (string) $post_after->post_content !== (string) $post_before->post_content;
		$parent_changed = absint( $post_after->post_parent ?? 0 ) !== absint( $post_before->post_parent ?? 0 );
		if ( $parent_changed ) {
			$dependencies_changed = $this->sync_post_dependencies( $post_id );
			if ( true === $dependencies_changed || $dependencies_changed instanceof WP_Error ) {
				$this->mark_source_group_critical( $post_id );
			}
		}
		if ( $title_changed || $slug_changed || $body_changed || $parent_changed ) {
			$this->invalidate_secondary_review( $post_id, $title_changed, $slug_changed );
		}
	}

	/** Clear a term target's review after its visitor-facing fields change. */
	public function handle_term_edited( $term_id, $term_taxonomy_id, $taxonomy, $args = array() ) {
		unset( $term_taxonomy_id, $args );
		$member = $this->relationships->find_by_object( 'term', sanitize_key( $taxonomy ), absint( $term_id ) );
		if ( ! is_array( $member ) ) {
			return;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( $member['language'] === $settings['main_language'] ) {
			return;
		}
		$this->workflow->clear_review(
			$member['group_uuid'],
			$member['language'],
			static function () {
				return true;
			}
		);
	}

	/** Treat taxonomy relationship changes as translation-critical structure. */
	public function handle_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $append );
		$before = array_map( 'absint', (array) $old_tt_ids );
		$after  = array_map( 'absint', (array) $tt_ids );
		sort( $before );
		sort( $after );
		if ( $before === $after ) {
			return;
		}
		$post = get_post( absint( $object_id ) );
		if ( ! is_object( $post ) || ! in_array( sanitize_key( $post->post_type ?? '' ), DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return;
		}
		unset( $taxonomy );
		$this->mark_source_group_critical( $object_id );
		$this->invalidate_secondary_review( $object_id, false, false );
	}

	/** Clear review for any stored notification overlay whose copy changed. */
	public function handle_notification_translations_updated( $old_value, $value, $option = '' ) {
		unset( $option );
		$old_value = is_array( $old_value ) ? $old_value : array();
		$value     = is_array( $value ) ? $value : array();
		$codes     = array_unique( array_merge( array_keys( $old_value ), array_keys( $value ) ) );
		foreach ( array_slice( $codes, 0, DSF_Multilingual_Settings::MAX_LANGUAGES * 2 ) as $code ) {
			$language = DSF_Multilingual_Settings::normalize_locale_code( $code );
			if ( '' === $language || ( $old_value[ $code ] ?? null ) === ( $value[ $code ] ?? null ) ) {
				continue;
			}
			$member = $this->relationships->find_by_object(
				'synthetic',
				'notification_bar',
				DSF_Multilingual_Adapters::synthetic_notification_id( $language )
			);
			if ( ! is_array( $member ) || $member['language'] !== $language ) {
				continue;
			}
			$this->workflow->clear_review(
				$member['group_uuid'],
				$language,
				static function () {
					return true;
				}
			);
		}
	}

	/** Invalidate stored review facts when the overlay option is first created. */
	public function handle_notification_translations_added( $option, $value ) {
		unset( $option );
		$this->handle_notification_translations_updated( array(), $value );
	}

	/** Invalidate stored review facts before the overlay option is deleted. */
	public function handle_option_before_delete( $option ) {
		if ( DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION !== $option ) {
			return;
		}
		$old_value = get_option( DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION, array() );
		$this->handle_notification_translations_updated( $old_value, array() );
	}

	/**
	 * A secondary object may only go public once it owns a resolvable URL.
	 *
	 * The main language keeps its native, unprefixed WordPress route, so it is
	 * always valid. Every other language must have a stored row in the indexed
	 * route map, which is also what guarantees the URL is unique in that
	 * language.
	 *
	 * @param bool  $valid  Whether earlier checks passed.
	 * @param array $member Relationship member under evaluation.
	 * @return bool
	 */
	public function foundation_route_is_valid( $valid, $member ) {
		if ( ! $valid || ! is_array( $member ) ) {
			return false;
		}

		$kind = (string) ( $member['object_kind'] ?? '' );
		if ( ! in_array( $kind, array( 'post', 'term' ), true ) ) {
			return true;
		}

		$settings = DSF_Multilingual_Settings::get_settings();
		if ( ( $member['language'] ?? '' ) === $settings['main_language'] ) {
			return true;
		}

		if ( 'post' === $kind ) {
			$post_type = get_post_type_object( sanitize_key( (string) ( $member['object_subtype'] ?? '' ) ) );
			if ( is_object( $post_type ) && ( empty( $post_type->public ) || empty( $post_type->publicly_queryable ) ) ) {
				// Objects such as layouts and popups never resolve their own URL;
				// they are reached through the page that embeds them.
				return true;
			}
		}

		$subtype   = sanitize_key( (string) ( $member['object_subtype'] ?? '' ) );
		$object_id = absint( $member['object_id'] ?? 0 );
		$route     = $this->routes->get_route( $kind, $subtype, $object_id );
		if ( is_array( $route ) ) {
			return true;
		}

		if ( 'post' === $kind ) {
			// Content translated while the migration was still running has no route
			// yet. Build it once here so the first publish attempt succeeds instead
			// of reporting a missing URL the editor cannot fix from the UI.
			$this->language_routing->sync_post_route( $object_id );
			return is_array( $this->routes->get_route( $kind, $subtype, $object_id ) );
		}

		return false;
	}

	/** Keep the legacy bulk default-layout cascade away from secondary objects. */
	public function default_layout_cascade_allows_post( $allowed, $post_id ) {
		if ( ! $allowed ) {
			return false;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return true;
		}
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return false;
		}
		$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		if ( $member instanceof WP_Error ) {
			return false;
		}
		if ( ! is_array( $member ) ) {
			return 'complete' !== $settings['migration_state'];
		}
		return $settings['main_language'] === $member['language'];
	}

	/** Remove one post member and its member-specific workflow state. */
	public function remove_post_relationship( $post_id, $post = null ) {
		$post = is_object( $post ) ? $post : get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}
		$this->remove_relationship( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
	}

	/** Remove one term member without deleting its group/siblings. */
	public function remove_term_relationship( $term_id, $term_taxonomy_id, $taxonomy, $deleted_term ) {
		unset( $term_taxonomy_id, $deleted_term );
		$this->remove_relationship( 'term', sanitize_key( $taxonomy ), absint( $term_id ) );
	}

	/** Read-only admin warnings for conflicts and resumable migration state. */
	public function render_foundation_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		$conflicts = DSF_Multilingual_Conflicts::detect_conflicts();
		if ( ! empty( $conflicts ) ) {
			$names = array_map(
				static function ( $conflict ) {
					return sanitize_text_field( $conflict['name'] ?? '' );
				},
				$conflicts
			);
			echo '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: %s: comma-separated names of active multilingual systems. */
					__( 'DesignStudio Flow multilingual mode is blocked by: %s. Disable the conflicting multilingual plugin; Flow will never deactivate it automatically.', 'designstudio-flow' ),
					implode( ', ', array_filter( $names ) )
				)
			) . '</p></div>';
			return;
		}
		if ( in_array( $settings['migration_state'], array( 'pending', 'running' ), true ) ) {
			$progress = $this->migration->get_progress();
			echo '<div class="notice notice-info"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of existing objects already assigned. */
					__( 'DesignStudio Flow is assigning existing content to the main language (%d processed). Public URLs remain unchanged.', 'designstudio-flow' ),
					absint( $progress['processed'] )
				)
			) . '</p></div>';
		} elseif ( 'failed' === $settings['migration_state'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The multilingual foundation migration paused safely. Review the Languages settings and retry after resolving the reported conflict or database error.', 'designstudio-flow' ) . '</p></div>';
		}
	}

	/** Expose the publish service to current secure transports. */
	public function get_publish_gate() {
		return $this->publish_gate;
	}

	/** Expose migration to the settings transport. */
	public function get_migration() {
		return $this->migration;
	}

	/** Expose relationships for later reviewed phases and tests. */
	public function get_relationships() {
		return $this->relationships;
	}

	/** Expose the indexed route map. */
	public function get_routes() {
		return $this->routes;
	}

	/** Expose review facts and fingerprints. */
	public function get_workflow() {
		return $this->workflow;
	}

	/** Expose the dependency graph. */
	public function get_dependencies() {
		return $this->dependencies;
	}

	/** Expose the request language context. */
	public function get_language_context() {
		return $this->language_context;
	}

	/** Expose the routing service to switchers and SEO output. */
	public function get_language_routing() {
		return $this->language_routing;
	}

	/** Expose the catalog overlay service. */
	public function get_overlays() {
		return $this->overlays;
	}

	/** Expose the shared language switcher. */
	public function get_language_switcher() {
		return $this->language_switcher;
	}

	/** Resolve current raw references to portable dependency-group edges. */
	private function build_post_dependency_edges( $post_id ) {
		$edges = array();
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return $edges;
		}
		$owner = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		if ( ! is_array( $owner ) ) {
			return $edges;
		}
		$definitions = DSF_Multilingual_Adapters::post_dependencies( $post_id, $owner['language'] );
		if ( $definitions instanceof WP_Error ) {
			return $definitions;
		}
		foreach ( $definitions as $dependency ) {
			$member = $this->relationships->find_by_object( $dependency['object_kind'], $dependency['object_subtype'], $dependency['object_id'] );
			if ( ! is_array( $member ) ) {
				continue;
			}
			$edges[] = array(
				'dependency_group_uuid' => $member['group_uuid'],
				'kind'                  => $dependency['dependency_kind'],
				'source_path'           => $dependency['path'],
				'required'              => ! empty( $dependency['required'] ),
			);
		}
		return $edges;
	}

	/** Build deterministic signatures independent of database row IDs/order. */
	private function dependency_signatures( $edges ) {
		$signatures = array();
		foreach ( is_array( $edges ) ? $edges : array() as $edge ) {
			if ( ! is_array( $edge ) ) {
				continue;
			}
			$signatures[] = implode(
				'|',
				array(
					(string) ( $edge['dependency_group_uuid'] ?? '' ),
					(string) ( $edge['kind'] ?? $edge['dependency_kind'] ?? '' ),
					(string) ( $edge['source_path'] ?? $edge['path'] ?? '' ),
					! empty( $edge['required'] ) ? '1' : '0',
				)
			);
		}
		sort( $signatures, SORT_STRING );
		return $signatures;
	}

	/** Mark and hide every enabled secondary sibling after a critical source change. */
	private function mark_source_group_critical( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return;
		}
		$source = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		if ( ! is_array( $source ) ) {
			return;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( $source['language'] !== $settings['main_language'] ) {
			return;
		}
		$members = $this->relationships->list_group( $source['group_uuid'] );
		if ( ! is_array( $members ) ) {
			return;
		}

		foreach ( $members as $member ) {
			if ( ! is_array( $member ) || $member['language'] === $settings['main_language'] || ! $this->is_language_enabled( $member['language'] ) ) {
				continue;
			}
			$this->workflow->set_critical_change(
				$member['group_uuid'],
				$member['language'],
				true,
				static function () {
					return true;
				}
			);
			if ( ! empty( $settings['enabled'] ) && 'post' === $member['object_kind'] && 'publish' === get_post_status( absint( $member['object_id'] ) ) ) {
				wp_update_post(
					array(
						'ID'          => absint( $member['object_id'] ),
						'post_status' => 'draft',
					)
				);
			}
		}
	}

	/** Clear a secondary member review and ensure edited live content is draft. */
	private function invalidate_secondary_review( $post_id, $title_changed, $slug_changed ) {
		$post = get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}
		$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		if ( ! is_array( $member ) ) {
			return;
		}
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( $member['language'] === $settings['main_language'] ) {
			return;
		}

		$this->workflow->clear_review(
			$member['group_uuid'],
			$member['language'],
			static function () {
				return true;
			}
		);
		if ( $title_changed ) {
			delete_post_meta( $post_id, '_dsf_translation_title_confirmed' );
		}
		if ( $slug_changed ) {
			delete_post_meta( $post_id, '_dsf_translation_slug_confirmed' );
		}
		if ( ! empty( $settings['enabled'] ) && 'publish' === get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => absint( $post_id ),
					'post_status' => 'draft',
				)
			);
		}
	}

	/** Delete member-specific rows, then exactly one relationship. */
	private function remove_relationship( $kind, $subtype, $object_id ) {
		$member = $this->relationships->find_by_object( $kind, $subtype, $object_id );
		if ( ! is_array( $member ) ) {
			return;
		}
		global $wpdb;
		// The language slot must never be released while old review facts remain.
		// A transaction makes cleanup-and-release atomic; if transactions are not
		// available, retain the relationship so any replacement stays blocked.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic cross-table cleanup requires an explicit transaction on the shared wpdb connection.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'dsf_translation_cleanup_transaction', __( 'Translation cleanup could not start safely.', 'designstudio-flow' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Permanent deletion must remove member-specific workflow facts immediately.
		$workflow_deleted = $wpdb->delete(
			DSF_Translation_Workflow::table_name(),
			array(
				'group_uuid' => $member['group_uuid'],
				'language'   => $member['language'],
			),
			array( '%s', '%s' )
		);
		if ( false === $workflow_deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back a partial member cleanup.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'dsf_translation_cleanup_workflow', __( 'Translation review cleanup failed safely.', 'designstudio-flow' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Owner edges are member-specific; inbound edges deliberately remain blocking.
		$dependencies_deleted = $wpdb->delete(
			DSF_Translation_Dependencies::table_name(),
			array(
				'owner_group_uuid' => $member['group_uuid'],
				'language'         => $member['language'],
			),
			array( '%s', '%s' )
		);
		if ( false === $dependencies_deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back a partial member cleanup.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'dsf_translation_cleanup_dependencies', __( 'Translation dependency cleanup failed safely.', 'designstudio-flow' ) );
		}
		$removed = $this->relationships->remove_member( $kind, $subtype, $object_id );
		if ( $removed instanceof WP_Error ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back a partial member cleanup.
			$wpdb->query( 'ROLLBACK' );
			return $removed;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Commit atomic cleanup only after the member slot is safely released.
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Best-effort rollback after a failed commit.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'dsf_translation_cleanup_commit', __( 'Translation cleanup could not be committed safely.', 'designstudio-flow' ) );
		}
		return true;
	}
}
