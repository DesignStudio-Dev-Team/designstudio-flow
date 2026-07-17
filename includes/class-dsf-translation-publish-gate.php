<?php
/**
 * Central server-side publication eligibility for translated post objects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Publish_Gate {

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Translation_Workflow */
	private $workflow;

	/** @var DSF_Translation_Dependencies|null */
	private $dependencies;

	/** @var callable */
	private $settings_reader;

	/** @var callable */
	private $conflict_detector;

	/** @var callable */
	private $fingerprint_resolver;

	/** @var callable */
	private $required_fields_resolver;

	/** @var callable */
	private $route_validator;

	/** @var bool */
	private $filtering = false;

	/** @var array<int,WP_Error> */
	private $blocked = array();

	/**
	 * @param array $args Service dependencies and optional test callbacks.
	 */
	public function __construct( $args ) {
		$args                           = is_array( $args ) ? $args : array();
		$this->relationships            = $args['relationships'];
		$this->workflow                 = $args['workflow'];
		$this->dependencies             = $args['dependencies'] ?? null;
		$this->settings_reader          = isset( $args['settings_reader'] ) && is_callable( $args['settings_reader'] )
			? $args['settings_reader']
			: array( 'DSF_Multilingual_Settings', 'get_settings' );
		$this->conflict_detector        = isset( $args['conflict_detector'] ) && is_callable( $args['conflict_detector'] )
			? $args['conflict_detector']
			: array( 'DSF_Multilingual_Conflicts', 'has_conflicts' );
		$this->fingerprint_resolver     = isset( $args['fingerprint_resolver'] ) && is_callable( $args['fingerprint_resolver'] )
			? $args['fingerprint_resolver']
			: array( 'DSF_Multilingual_Adapters', 'fingerprint_payload' );
		$this->required_fields_resolver = isset( $args['required_fields_resolver'] ) && is_callable( $args['required_fields_resolver'] )
			? $args['required_fields_resolver']
			: array( $this, 'default_required_fields_confirmed' );
		$this->route_validator          = isset( $args['route_validator'] ) && is_callable( $args['route_validator'] )
			? $args['route_validator']
			: static function ( $member ) {
				return (bool) apply_filters( 'dsf_multilingual_route_is_valid', true, $member );
			};
	}

	/** Complete the dependency-service cycle after both services are built. */
	public function set_dependencies( $dependencies ) {
		$this->dependencies = $dependencies;
	}

	/** Register native WordPress, REST, and public-template state gates. */
	public function register_hooks() {
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 99, 4 );
		add_filter( 'update_post_metadata', array( $this, 'filter_public_meta_update' ), 10, 5 );
		add_filter( 'add_post_metadata', array( $this, 'filter_public_meta_update' ), 10, 5 );
		add_action( 'publish_future_post', array( $this, 'guard_scheduled_publish' ), 9 );
		add_action( 'transition_post_status', array( $this, 'guard_direct_publish_transition' ), -1000, 3 );
		add_action( 'rest_api_init', array( $this, 'register_rest_hooks' ) );
		add_action( 'admin_notices', array( $this, 'render_blocked_notice' ) );
	}

	/** Add exact post-type REST pre-insert gates. */
	public function register_rest_hooks() {
		foreach ( DSF_Multilingual_Adapters::relationship_post_types() as $post_type ) {
			add_filter( 'rest_pre_insert_' . $post_type, array( $this, 'filter_rest_pre_insert' ), 10, 2 );
		}
	}

	/**
	 * Downgrade a blocked native status transition before it reaches the DB.
	 *
	 * @param array $data Sanitized post data.
	 * @param array $postarr Raw post array.
	 * @param array $unsanitized_postarr Original post array.
	 * @param bool|null $update Whether this is an update. Added by WordPress 6.0.
	 * @return array
	 */
	public function filter_post_data( $data, $postarr, $unsanitized_postarr, $update = null ) {
		unset( $unsanitized_postarr );
		if ( $this->filtering || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future' ), true ) ) {
			return $data;
		}

		$post_id = absint( $postarr['ID'] ?? 0 );
		if ( null === $update ) {
			$update = 0 < $post_id;
		}
		if ( ! $post_id ) {
			$settings  = call_user_func( $this->settings_reader );
			$post_type = sanitize_key( $data['post_type'] ?? $postarr['post_type'] ?? '' );
			if ( ! empty( $settings['enabled'] ) && in_array( $post_type, DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
				// A relationship cannot be stored atomically before WordPress assigns
				// the local ID. Keep the first save private; the post-insert hook creates
				// its main-language member, and a subsequent publish request is then
				// evaluated against that persisted relationship.
				$this->blocked[0]    = new WP_Error( 'dsf_translation_relationship_pending', __( 'Save this new multilingual object as a draft before publishing it.', 'designstudio-flow' ) );
				$data['post_status'] = 'draft';
			}
			return $data;
		}

		$result = $this->evaluate_post( $post_id, false, (bool) $update, $data );
		if ( $result instanceof WP_Error ) {
			$this->blocked[ $post_id ] = $result;
			$data['post_status']       = 'draft';
		}
		return $data;
	}

	/** Return a typed REST error instead of silently accepting a blocked publish. */
	public function filter_rest_pre_insert( $prepared_post, $request ) {
		$status = is_object( $prepared_post ) && isset( $prepared_post->post_status ) ? $prepared_post->post_status : '';
		if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return $prepared_post;
		}
		$post_id = is_object( $prepared_post ) && isset( $prepared_post->ID ) ? absint( $prepared_post->ID ) : 0;
		if ( ! $post_id ) {
			$settings  = call_user_func( $this->settings_reader );
			$post_type = is_object( $prepared_post ) && isset( $prepared_post->post_type ) ? sanitize_key( $prepared_post->post_type ) : '';
			if ( ! empty( $settings['enabled'] ) && in_array( $post_type, DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
				return new WP_Error( 'dsf_translation_relationship_pending', __( 'Create this multilingual object as a draft before publishing it.', 'designstudio-flow' ), array( 'status' => 409 ) );
			}
			return $prepared_post;
		}
		$incoming  = array();
		$field_map = array(
			'title'   => 'post_title',
			'slug'    => 'post_name',
			'excerpt' => 'post_excerpt',
			'content' => 'post_content',
		);
		foreach ( $field_map as $request_field => $post_field ) {
			$was_supplied = is_object( $request ) && is_callable( array( $request, 'has_param' ) )
				? $request->has_param( $request_field )
				: property_exists( $prepared_post, $post_field );
			if ( $was_supplied && property_exists( $prepared_post, $post_field ) ) {
				$incoming[ $post_field ] = $prepared_post->{$post_field};
			}
		}
		$result = $this->evaluate_post( $post_id, true, true, $incoming );
		return $result instanceof WP_Error ? $result : $prepared_post;
	}

	/**
	 * Prevent an active template flag from bypassing post-status enforcement.
	 *
	 * Returning null lets WordPress continue; false rejects the metadata write.
	 */
	public function filter_public_meta_update( $check, $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		unset( $prev_value );
		if ( null !== $check || ! in_array( $meta_key, array( '_dsf_pt_active', '_dsf_st_active', '_dsf_bt_active' ), true ) || empty( $meta_value ) ) {
			return $check;
		}
		$result = $this->evaluate_post( absint( $object_id ), true, false );
		if ( $result instanceof WP_Error ) {
			$this->blocked[ absint( $object_id ) ] = $result;
			return false;
		}
		return null;
	}

	/**
	 * Stop WordPress cron before its direct wp_publish_post() call.
	 *
	 * Core's scheduled-publish function bypasses wp_insert_post_data. Moving a
	 * blocked translation back to draft before the core priority-10 callback runs keeps it
	 * private without relying on an after-the-fact status repair.
	 *
	 * @param int|WP_Post $post Post ID or object supplied by the cron action.
	 */
	public function guard_scheduled_publish( $post ) {
		$post = get_post( $post );
		if ( $this->filtering || ! is_object( $post ) || ! isset( $post->ID, $post->post_status ) || 'future' !== $post->post_status ) {
			return;
		}

		$result = $this->evaluate_post( absint( $post->ID ), false, false );
		if ( ! ( $result instanceof WP_Error ) ) {
			return;
		}

		$post_id                   = absint( $post->ID );
		$this->blocked[ $post_id ] = $result;
		$this->filtering           = true;
		try {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
		} finally {
			$this->filtering = false;
		}
	}

	/**
	 * Fail closed for code that calls wp_publish_post() directly.
	 *
	 * WordPress exposes no pre-publication filter inside wp_publish_post(). This
	 * earliest transition hook repairs the database status immediately. The cron
	 * path is intercepted earlier by guard_scheduled_publish(). Normal REST/admin,
	 * CLI, XML-RPC and import saves are blocked by wp_insert_post_data before write.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 */
	public function guard_direct_publish_transition( $new_status, $old_status, $post ) {
		if ( $this->filtering || 'publish' !== $new_status || 'publish' === $old_status || ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}

		$result = $this->evaluate_post( absint( $post->ID ), false, false );
		if ( ! ( $result instanceof WP_Error ) ) {
			return;
		}

		$post_id                   = absint( $post->ID );
		$this->blocked[ $post_id ] = $result;
		$this->filtering           = true;
		try {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			// Objects are handles in PHP. Correct the object that later status hooks
			// receive as well as the persisted row.
			$post->post_status = 'draft';
		} finally {
			$this->filtering = false;
		}
	}

	/**
	 * Evaluate one post-backed translated member.
	 *
	 * Main-language and unassigned native objects remain compatible. A secondary
	 * object must be reviewed against the current main source, have every exact-
	 * language dependency, retain integrity, and pass future route adapters.
	 *
	 * @param int        $post_id Post ID.
	 * @param bool       $check_permissions Require current-user edit/publish caps.
	 * @param bool       $allow_stale_retain Allow an already-public minor-stale translation to remain public.
	 * @param array|null $incoming_data Incoming core fields, when available.
	 * @return array|WP_Error
	 */
	public function evaluate_post( $post_id, $check_permissions = true, $allow_stale_retain = true, $incoming_data = null ) {
		$post_id  = absint( $post_id );
		$settings = call_user_func( $this->settings_reader );
		$settings = is_array( $settings ) ? $settings : array();
		if ( empty( $settings['enabled'] ) ) {
			return array(
				'eligible' => true,
				'reason'   => 'disabled',
			);
		}

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || ! in_array( sanitize_key( $post->post_type ), DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return new WP_Error( 'dsf_translation_object', __( 'The translation object is invalid.', 'designstudio-flow' ) );
		}

		$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), $post_id );
		if ( $member instanceof WP_Error ) {
			return $member;
		}
		if ( ! is_array( $member ) ) {
			$migration_complete = 'complete' === ( $settings['migration_state'] ?? '' )
				&& DSF_Multilingual_Settings::MIGRATION_VERSION === (int) ( $settings['migration_version'] ?? 0 );
			if ( ! $migration_complete ) {
				return array(
					'eligible' => true,
					'reason'   => 'unassigned_legacy_main',
				);
			}
			return new WP_Error( 'dsf_translation_relationship_missing', __( 'This object has no valid translation relationship.', 'designstudio-flow' ) );
		}

		$main_language = DSF_Multilingual_Settings::normalize_locale_code( $settings['main_language'] ?? '' );
		if ( $main_language === $member['language'] ) {
			return array(
				'eligible' => true,
				'reason'   => 'main_language',
			);
		}
		if ( ! in_array( $member['language'], DSF_Multilingual_Settings::get_enabled_language_codes( $settings ), true ) ) {
			return new WP_Error( 'dsf_translation_language_disabled', __( 'This translation language is not enabled.', 'designstudio-flow' ) );
		}
		if ( call_user_func( $this->conflict_detector ) ) {
			return new WP_Error( 'dsf_translation_plugin_conflict', __( 'Publishing is blocked while another multilingual plugin is active.', 'designstudio-flow' ) );
		}
		if ( $check_permissions && ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'publish_post', $post_id ) ) ) {
			return new WP_Error( 'dsf_translation_forbidden', __( 'You are not allowed to publish this translation.', 'designstudio-flow' ) );
		}

		if ( is_array( $incoming_data ) && $this->core_content_changed( $post, $incoming_data ) ) {
			return new WP_Error( 'dsf_translation_changed', __( 'The translated content changed and must be reviewed again before publishing.', 'designstudio-flow' ) );
		}

		$source = $this->relationships->find_member( $member['group_uuid'], $main_language );
		if ( $source instanceof WP_Error ) {
			return $source;
		}
		if ( ! is_array( $source ) || ! $this->member_is_public( $source ) ) {
			return new WP_Error( 'dsf_translation_source_missing', __( 'The published main-language source is missing.', 'designstudio-flow' ) );
		}

		$fingerprint = $this->current_source_fingerprint( $source );
		if ( $fingerprint instanceof WP_Error ) {
			return $fingerprint;
		}
		$facts = $this->workflow->get_facts( $member['group_uuid'], $member['language'] );
		if ( $facts instanceof WP_Error ) {
			return $facts;
		}

		$raw_dependencies = $this->evaluate_explicit_dependencies( $post_id, $member['language'] );
		if ( $raw_dependencies instanceof WP_Error ) {
			return $raw_dependencies;
		}

		$dependencies_eligible = true;
		$dependency_result     = array( 'eligible' => true );
		if ( $this->dependencies ) {
			$dependency_result = $this->dependencies->evaluate_closure( $member['group_uuid'], $member['language'] );
			if ( $dependency_result instanceof WP_Error ) {
				return $dependency_result;
			}
			$dependencies_eligible = ! empty( $dependency_result['eligible'] );
		}

		$route_valid = (bool) call_user_func( $this->route_validator, $member );
		$confirmed   = (bool) call_user_func( $this->required_fields_resolver, $member, $post );
		$status      = DSF_Translation_Workflow::derive_status(
			array(
				'exists'                     => true,
				'facts'                      => $facts,
				'current_source_fingerprint' => $fingerprint['fingerprint'],
				'current_fingerprint_schema' => $fingerprint['schema'],
				'dependencies_eligible'      => $dependencies_eligible,
				'route_valid'                => $route_valid,
				'integrity_valid'            => DSF_Multilingual_Adapters::object_exists( 'post', $member['object_subtype'], $member['object_id'] ),
				'content_ready'              => '' !== trim( (string) $post->post_title ),
				'required_fields_confirmed'  => $confirmed,
				'is_public'                  => 'publish' === (string) $post->post_status,
				'allow_stale_public'         => $this->allows_minor_stale_public( $settings ),
			)
		);

		if ( ! empty( $status['can_publish'] ) || ( $allow_stale_retain && ! empty( $status['retain_public'] ) ) ) {
			return array(
				'eligible'     => true,
				'workflow'     => $status,
				'dependencies' => $dependency_result,
			);
		}

		return new WP_Error(
			'dsf_translation_publish_blocked',
			__( 'This translation is not reviewed or has a blocking dependency.', 'designstudio-flow' ),
			array(
				'workflow'     => $status,
				'dependencies' => $dependency_result,
			)
		);
	}

	/**
	 * Exact-language dependency member eligibility callback.
	 *
	 * @return array
	 */
	public function dependency_member_is_eligible( $member, $group_uuid, $language, $edge ) {
		unset( $edge );
		if ( ! is_array( $member ) || ( $member['language'] ?? '' ) !== $language || ( $member['group_uuid'] ?? '' ) !== $group_uuid ) {
			return array(
				'eligible' => false,
				'reason'   => 'language_mismatch',
			);
		}
		if ( ! $this->member_is_public( $member ) ) {
			return array(
				'eligible' => false,
				'reason'   => 'not_published',
			);
		}

		$settings      = call_user_func( $this->settings_reader );
		$main_language = DSF_Multilingual_Settings::normalize_locale_code( $settings['main_language'] ?? '' );
		$source        = $this->relationships->find_member( $group_uuid, $main_language );
		if ( ! is_array( $source ) || ! $this->member_is_public( $source ) ) {
			return array(
				'eligible' => false,
				'reason'   => 'source_missing',
			);
		}
		$fingerprint = $this->current_source_fingerprint( $source );
		$facts       = $this->workflow->get_facts( $group_uuid, $language );
		if ( $fingerprint instanceof WP_Error || $facts instanceof WP_Error ) {
			return array(
				'eligible' => false,
				'reason'   => 'workflow_unavailable',
			);
		}

		$target_object = 'post' === ( $member['object_kind'] ?? '' ) ? get_post( absint( $member['object_id'] ?? 0 ) ) : null;
		$confirmed     = 'post' === ( $member['object_kind'] ?? '' )
			? (bool) call_user_func( $this->required_fields_resolver, $member, $target_object )
			: true;
		$route_valid   = (bool) call_user_func( $this->route_validator, $member );
		$status        = DSF_Translation_Workflow::derive_status(
			array(
				'exists'                     => true,
				'facts'                      => $facts,
				'current_source_fingerprint' => $fingerprint['fingerprint'],
				'current_fingerprint_schema' => $fingerprint['schema'],
				'dependencies_eligible'      => true,
				'route_valid'                => $route_valid,
				'integrity_valid'            => DSF_Multilingual_Adapters::object_exists( $member['object_kind'], $member['object_subtype'], $member['object_id'] ),
				'content_ready'              => true,
				'required_fields_confirmed'  => $confirmed,
				'is_public'                  => true,
				'allow_stale_public'         => $this->allows_minor_stale_public( $settings ),
			)
		);
		$eligible      = ! empty( $status['can_publish'] ) || ! empty( $status['retain_public'] );

		return array(
			'eligible' => $eligible,
			'reason'   => $eligible ? '' : $status['status'],
		);
	}

	/** Return the last blocked decision for a request. */
	public function get_blocked_error( $post_id ) {
		return $this->blocked[ absint( $post_id ) ] ?? null;
	}

	/**
	 * Complete an endpoint-created main-language object after its meta is stored.
	 *
	 * New objects are intentionally drafted on their first insert because no
	 * relationship can exist before WordPress assigns an ID. Trusted creation
	 * transports call this only after all visitor-facing meta is written.
	 *
	 * @return int|WP_Error
	 */
	public function finalize_new_post_publication( $post_id ) {
		$preflight = $this->preflight_new_post_publication( $post_id );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		$post_id = absint( $post_id );
		if ( 'publish' !== get_post_status( $post_id ) ) {
			$updated = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				),
				true
			);
			if ( $updated instanceof WP_Error ) {
				return $updated;
			}
		}
		return 'publish' === get_post_status( $post_id )
			? $post_id
			: new WP_Error( 'dsf_translation_publish_blocked', __( 'The object remained a draft because publication checks did not pass.', 'designstudio-flow' ) );
	}

	/** Validate a completed new object without changing its public state. */
	public function preflight_new_post_publication( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'publish_post', $post_id ) ) {
			return new WP_Error( 'dsf_translation_forbidden', __( 'You are not allowed to publish this object.', 'designstudio-flow' ) );
		}
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || ! in_array( sanitize_key( $post->post_type ), DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return new WP_Error( 'dsf_translation_object', __( 'The object cannot be published by the multilingual service.', 'designstudio-flow' ) );
		}

		$settings = call_user_func( $this->settings_reader );
		$settings = is_array( $settings ) ? $settings : array();
		if ( ! empty( $settings['enabled'] ) ) {
			$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), $post_id );
			if ( ! is_array( $member ) ) {
				return $member instanceof WP_Error
					? $member
					: new WP_Error( 'dsf_translation_relationship_missing', __( 'The object remained a draft because its language relationship was not stored.', 'designstudio-flow' ) );
			}
			$result = $this->evaluate_post( $post_id, true, false );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
		}

		return $post_id;
	}

	/** Show a generic, escaped wp-admin failure without leaking content. */
	public function render_blocked_notice() {
		if ( empty( $this->blocked ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'A translation remained a draft because review or a required same-language dependency is incomplete.', 'designstudio-flow' ) . '</p></div>';
	}

	/** Check every raw dependency so an unmapped stale ID cannot evade the graph. */
	private function evaluate_explicit_dependencies( $post_id, $language ) {
		$dependencies = DSF_Multilingual_Adapters::post_dependencies( $post_id, $language );
		if ( $dependencies instanceof WP_Error ) {
			return $dependencies;
		}
		foreach ( $dependencies as $dependency ) {
			$required      = ! empty( $dependency['required'] );
			$source_member = $this->relationships->find_by_object( $dependency['object_kind'], $dependency['object_subtype'], $dependency['object_id'] );
			if ( ! is_array( $source_member ) ) {
				if ( ! $required ) {
					continue;
				}
				return new WP_Error( 'dsf_translation_dependency_missing', __( 'A required translation dependency is missing.', 'designstudio-flow' ) );
			}
			if ( ( $source_member['language'] ?? '' ) !== $language ) {
				if ( ! $required ) {
					continue;
				}
				return new WP_Error( 'dsf_translation_dependency_raw_language', __( 'A required reference still points to an object in a different language.', 'designstudio-flow' ) );
			}
			$translated = $this->relationships->find_member( $source_member['group_uuid'], $language );
			if ( ! is_array( $translated ) ) {
				if ( ! $required ) {
					continue;
				}
				return new WP_Error( 'dsf_translation_dependency_language', __( 'A required same-language dependency is missing.', 'designstudio-flow' ) );
			}
			$eligible = $this->dependency_member_is_eligible( $translated, $source_member['group_uuid'], $language, $dependency );
			if ( empty( $eligible['eligible'] ) ) {
				if ( ! $required ) {
					continue;
				}
				return new WP_Error( 'dsf_translation_dependency_ineligible', __( 'A required same-language dependency is not reviewed and published.', 'designstudio-flow' ) );
			}
		}
		return true;
	}

	/** Build the current main-source fingerprint. */
	private function current_source_fingerprint( $source_member ) {
		$payload = call_user_func( $this->fingerprint_resolver, $source_member );
		if ( $payload instanceof WP_Error ) {
			return $payload;
		}
		return DSF_Translation_Workflow::build_fingerprint( $payload, 1 );
	}

	/** Public eligibility of a relationship member before workflow review. */
	private function member_is_public( $member ) {
		if ( ! is_array( $member ) ) {
			return false;
		}
		if ( 'post' === ( $member['object_kind'] ?? '' ) ) {
			$object_id = absint( $member['object_id'] ?? 0 );
			if ( 'publish' !== get_post_status( $object_id ) ) {
				return false;
			}
			$activation_keys = array(
				'dsf_product_template' => '_dsf_pt_active',
				'dsf_shop_template'    => '_dsf_st_active',
				'dsf_blog_template'    => '_dsf_bt_active',
			);
			$subtype         = sanitize_key( $member['object_subtype'] ?? '' );
			return ! isset( $activation_keys[ $subtype ] ) || '1' === (string) get_post_meta( $object_id, $activation_keys[ $subtype ], true );
		}
		if ( 'term' === ( $member['object_kind'] ?? '' ) ) {
			return DSF_Multilingual_Adapters::object_exists( 'term', $member['object_subtype'], $member['object_id'] );
		}
		return DSF_Multilingual_Adapters::object_exists( $member['object_kind'] ?? '', $member['object_subtype'] ?? '', $member['object_id'] ?? 0 );
	}

	/** Whether the approved settings permit minor-stale public retention. */
	private function allows_minor_stale_public( $settings ) {
		return 'hide_until_reviewed' !== ( $settings['source_change_policy'] ?? 'keep_minor' );
	}

	/** Reject publish-with-edit because the target review must be cleared. */
	private function core_content_changed( $post, $incoming ) {
		$fields = array( 'post_title', 'post_name', 'post_excerpt', 'post_content' );
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $incoming ) && (string) $incoming[ $field ] !== (string) $post->{$field} ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Copied identity fields are unconfirmed until a later review/clone phase
	 * writes the explicit confirmation markers.
	 */
	public function default_required_fields_confirmed( $member, $post ) {
		if ( ! is_array( $member ) || ! is_object( $post ) || empty( $post->post_title ) ) {
			return false;
		}
		$title_confirmed = '1' === (string) get_post_meta( absint( $member['object_id'] ), '_dsf_translation_title_confirmed', true );
		if ( ! $title_confirmed ) {
			return false;
		}
		if ( in_array( $member['object_subtype'], array( 'page', 'post' ), true ) ) {
			return ! empty( $post->post_name ) && '1' === (string) get_post_meta( absint( $member['object_id'] ), '_dsf_translation_slug_confirmed', true );
		}
		return true;
	}
}
