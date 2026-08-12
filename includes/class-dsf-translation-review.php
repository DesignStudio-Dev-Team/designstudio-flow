<?php
/**
 * Central translation review queries and actions.
 *
 * One place answers "what is the state of every translation on this site, and
 * what exactly is blocking it". Status is always derived from current facts —
 * the reviewed fingerprint, the dependency closure, the route, and the object's
 * own state — never from a stored label that could drift out of date.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Review {

	const MAX_ROWS     = 200;
	const MAX_PER_PAGE = 50;

	/**
	 * Adapter payload version the publish gate fingerprints against.
	 *
	 * Review must hash exactly what the gate compares, or an approval would
	 * never match the current source version.
	 */
	const ADAPTER_SCHEMA_VERSION = 1;

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Translation_Workflow */
	private $workflow;

	/** @var DSF_Translation_Publish_Gate */
	private $publish_gate;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services = is_array( $services ) ? $services : array();
		$needed   = ! isset( $services['relationships'], $services['workflow'], $services['publish_gate'] );
		// Resolving the coordinator boots every multilingual hook, so it is only
		// touched when a caller did not supply the services itself.
		$coordinator = $needed ? DSF_Multilingual::get_instance() : null;

		$this->relationships = $services['relationships'] ?? $coordinator->get_relationships();
		$this->workflow      = $services['workflow'] ?? $coordinator->get_workflow();
		$this->publish_gate  = $services['publish_gate'] ?? $coordinator->get_publish_gate();
	}

	/**
	 * Return the shared review service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * The statuses the dashboard can filter by.
	 *
	 * @return array<string,string>
	 */
	public static function get_status_labels() {
		return array(
			'missing'           => __( 'Missing', 'designstudio-flow' ),
			'draft'             => __( 'Draft', 'designstudio-flow' ),
			'machine_prefilled' => __( 'Machine prefilled', 'designstudio-flow' ),
			'source_changed'    => __( 'Source changed', 'designstudio-flow' ),
			'blocked'           => __( 'Blocked', 'designstudio-flow' ),
			'ready_for_review'  => __( 'Ready for review', 'designstudio-flow' ),
			'reviewed'          => __( 'Reviewed', 'designstudio-flow' ),
			'published'         => __( 'Published', 'designstudio-flow' ),
		);
	}

	/**
	 * Build the review rows for one bounded query.
	 *
	 * @param array $args Filter arguments: post_type, language, status, paged.
	 * @return array{rows:array,total:int,languages:array,post_types:array}
	 */
	public function query( $args = array() ) {
		$args      = is_array( $args ) ? $args : array();
		$settings  = DSF_Multilingual_Settings::get_settings();
		$languages = array_values( array_diff( DSF_Multilingual_Settings::get_enabled_language_codes( $settings ), array( $settings['main_language'] ) ) );

		$post_types    = $this->reviewable_post_types();
		$post_type     = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : '';
		$post_type     = in_array( $post_type, $post_types, true ) ? $post_type : '';
		$language      = DSF_Multilingual_Settings::normalize_locale_code( $args['language'] ?? '' );
		$language      = in_array( $language, $languages, true ) ? $language : '';
		$status_filter = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$status_filter = array_key_exists( $status_filter, self::get_status_labels() ) ? $status_filter : '';
		$paged         = max( 1, absint( $args['paged'] ?? 1 ) );

		$sources = $this->query_main_language_sources( $post_type ? array( $post_type ) : $post_types, $settings['main_language'], $paged );

		$rows = array();

		// Global and catalog content is not post-backed, so it is described by
		// its own adapters rather than the main-language post query.
		foreach ( $this->describe_global_targets( $language ? array( $language ) : $languages, $settings ) as $row ) {
			if ( '' !== $status_filter && $row['status'] !== $status_filter ) {
				continue;
			}
			if ( '' !== $post_type ) {
				continue;
			}
			$rows[] = $row;
		}

		foreach ( $sources['posts'] as $source ) {
			foreach ( $language ? array( $language ) : $languages as $code ) {
				$row = $this->describe_target( $source, $code, $settings );
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( '' !== $status_filter && $row['status'] !== $status_filter ) {
					continue;
				}
				$rows[] = $row;
				if ( count( $rows ) >= self::MAX_ROWS ) {
					break 2;
				}
			}
		}

		return array(
			'rows'       => $rows,
			'total'      => $sources['total'],
			'paged'      => $paged,
			'languages'  => $languages,
			'post_types' => $post_types,
		);
	}

	/**
	 * Describe the non-post translation targets: global messages and catalog.
	 *
	 * These have no main-language post to hang a row off, so their state comes
	 * from the workflow facts recorded against their own relationship members.
	 *
	 * @param string[] $languages Target languages.
	 * @param array    $settings  Multilingual settings.
	 * @return array<int,array<string,mixed>>
	 */
	private function describe_global_targets( $languages, $settings ) {
		$rows = array();

		foreach ( $languages as $language ) {
			$row = $this->describe_notification_target( $language, $settings );
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		foreach ( $this->describe_catalog_targets( $languages ) as $row ) {
			$rows[] = $row;
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Describe the notification bar in one language.
	 *
	 * @param string $language Target language.
	 * @param array  $settings Multilingual settings.
	 * @return array|null
	 */
	private function describe_notification_target( $language, $settings ) {
		if ( ! class_exists( 'DSF_Notification_Bar' ) ) {
			return null;
		}

		$main = get_option( 'dsf_notification_bar', array() );
		if ( ! is_array( $main ) || empty( $main['enabled'] ) ) {
			// Nothing is shown to visitors, so there is nothing to translate.
			return null;
		}

		$object_id = DSF_Multilingual_Adapters::synthetic_notification_id( $language );
		$member    = $object_id ? $this->relationships->find_by_object( 'synthetic', 'notification_bar', $object_id ) : null;

		$row = $this->empty_row(
			$language,
			__( 'Notification bar', 'designstudio-flow' ),
			'notification_bar',
			(string) admin_url( 'admin.php?page=dsf-settings' )
		);

		$translations = get_option( DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION, array() );
		$exists       = is_array( $translations ) && ! empty( $translations[ $language ] );
		if ( ! $exists || ! is_array( $member ) ) {
			return $row;
		}

		$row['target_id'] = $object_id;
		$row['can_clone'] = false;
		$this->apply_workflow_facts( $row, $member, $language );

		return $row;
	}

	/**
	 * Describe every catalog overlay that has a relationship member.
	 *
	 * @param string[] $languages Target languages.
	 * @return array<int,array<string,mixed>>
	 */
	private function describe_catalog_targets( $languages ) {
		if ( ! class_exists( 'DSF_Translation_Overlays' ) || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$rows     = array();
		$products = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 25,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( (array) $products as $product_id ) {
			foreach ( $languages as $language ) {
				$overlay_id = DSF_Translation_Overlays::overlay_id( $product_id, $language );
				if ( ! $overlay_id ) {
					continue;
				}

				$row              = $this->empty_row(
					$language,
					(string) get_the_title( $product_id ),
					'product',
					(string) get_edit_post_link( $product_id, 'raw' ),
					DSF_Translation_Overlays::KIND,
					'product'
				);
				$row['source_id'] = absint( $product_id );
				$row['can_clone'] = false;

				$member = $this->relationships->find_by_object( DSF_Translation_Overlays::KIND, 'product', $overlay_id );
				$fields = DSF_Translation_Overlays::get_fields( 'product', $product_id, $language );
				if ( is_array( $member ) && ! empty( $fields ) ) {
					$row['target_id']   = $overlay_id;
					$row['target_edit'] = $row['source_edit'];
					$this->apply_workflow_facts( $row, $member, $language );
				}

				$rows[] = $row;
				if ( count( $rows ) >= self::MAX_ROWS ) {
					return $rows;
				}
			}
		}

		return $rows;
	}

	/**
	 * Build the shared shape of a review row.
	 *
	 * @param string $language       Target language.
	 * @param string $title          Source label.
	 * @param string $type           Source type label.
	 * @param string $edit_url       Source edit URL.
	 * @param string $object_kind    Adapter kind for row actions.
	 * @param string $object_subtype Adapter subtype for row actions.
	 * @return array<string,mixed>
	 */
	private function empty_row( $language, $title, $type, $edit_url, $object_kind = 'synthetic', $object_subtype = '' ) {
		return array(
			'object_kind'    => $object_kind,
			'object_subtype' => '' !== $object_subtype ? $object_subtype : $type,
			'group_uuid'     => '',
			'source_id'      => 0,
			'source_title'   => $title,
			'source_type'    => $type,
			'source_edit'    => $edit_url,
			'source_status'  => 'publish',
			'source_change'  => '',
			'language'       => $language,
			'target_id'      => 0,
			'target_edit'    => '',
			'target_view'    => '',
			'target_change'  => '',
			'reviewer'       => '',
			'reviewed_at'    => '',
			'status'         => 'missing',
			'flags'          => array( 'missing' ),
			'blockers'       => array(),
			'can_publish'    => false,
			'can_review'     => false,
			'can_clone'      => false,
		);
	}

	/**
	 * Fold recorded review facts into a non-post row.
	 *
	 * @param array  $row      Row passed by reference.
	 * @param array  $member   Relationship member.
	 * @param string $language Target language.
	 */
	private function apply_workflow_facts( &$row, $member, $language ) {
		$row['group_uuid'] = (string) $member['group_uuid'];

		$facts = $this->workflow->get_facts( $member['group_uuid'], $language );
		if ( ! is_array( $facts ) ) {
			$row['status'] = 'draft';
			$row['flags']  = array( 'draft' );
			return;
		}

		$reviewer           = absint( $facts['reviewer_id'] ?? 0 );
		$row['reviewer']    = $reviewer ? (string) get_the_author_meta( 'display_name', $reviewer ) : '';
		$row['reviewed_at'] = (string) ( $facts['reviewed_at_gmt'] ?? '' );

		$payload     = DSF_Multilingual_Adapters::fingerprint_payload( $member );
		$fingerprint = $payload instanceof WP_Error ? $payload : DSF_Translation_Workflow::build_fingerprint( $payload, self::ADAPTER_SCHEMA_VERSION );

		$status = DSF_Translation_Workflow::derive_status(
			array(
				'exists'                     => true,
				'facts'                      => $facts,
				'current_source_fingerprint' => $fingerprint instanceof WP_Error ? '' : $fingerprint['fingerprint'],
				'current_fingerprint_schema' => $fingerprint instanceof WP_Error ? 0 : $fingerprint['schema'],
				'dependencies_eligible'      => true,
				'route_valid'                => true,
				'integrity_valid'            => true,
				'content_ready'              => true,
				'required_fields_confirmed'  => true,
				'is_public'                  => ! empty( $facts['reviewer_id'] ) && empty( $facts['machine_prefilled'] ),
			)
		);

		$row['status']      = (string) $status['status'];
		$row['flags']       = (array) $status['flags'];
		$row['can_publish'] = false;
		$row['can_review']  = ! in_array( $status['status'], array( 'reviewed', 'published' ), true ) && current_user_can( 'manage_options' );
	}

	/**
	 * Describe one source object in one target language.
	 *
	 * @param WP_Post $source   Main-language source post.
	 * @param string  $language Target language.
	 * @param array   $settings Multilingual settings.
	 * @return array|null
	 */
	public function describe_target( $source, $language, $settings = null ) {
		$settings = is_array( $settings ) ? $settings : DSF_Multilingual_Settings::get_settings();
		if ( ! is_object( $source ) || empty( $source->ID ) ) {
			return null;
		}

		$member = $this->relationships->find_by_object( 'post', sanitize_key( $source->post_type ), absint( $source->ID ) );
		if ( ! is_array( $member ) || $member['language'] !== $settings['main_language'] ) {
			return null;
		}

		$row = array(
			'object_kind'    => 'post',
			'object_subtype' => sanitize_key( $source->post_type ),
			'group_uuid'     => $member['group_uuid'],
			'source_id'      => absint( $source->ID ),
			'source_title'   => (string) $source->post_title,
			'source_type'    => sanitize_key( $source->post_type ),
			'source_edit'    => (string) get_edit_post_link( $source->ID, 'raw' ),
			'source_status'  => (string) $source->post_status,
			'source_change'  => (string) $source->post_modified_gmt,
			'language'       => $language,
			'target_id'      => 0,
			'target_edit'    => '',
			'target_view'    => '',
			'target_change'  => '',
			'reviewer'       => '',
			'reviewed_at'    => '',
			'status'         => 'missing',
			'flags'          => array( 'missing' ),
			'blockers'       => array(),
			'can_publish'    => false,
			'can_review'     => false,
			'can_clone'      => current_user_can( 'edit_post', absint( $source->ID ) ),
		);

		$target = $this->relationships->find_member( $member['group_uuid'], $language );
		if ( ! is_array( $target ) ) {
			return $row;
		}

		$post = get_post( absint( $target['object_id'] ) );
		if ( ! is_object( $post ) ) {
			return $row;
		}

		$row['target_id']     = absint( $post->ID );
		$row['target_edit']   = (string) get_edit_post_link( $post->ID, 'raw' );
		$row['target_view']   = 'publish' === $post->post_status ? (string) get_permalink( $post->ID ) : '';
		$row['target_change'] = (string) $post->post_modified_gmt;
		$row['can_clone']     = false;

		$evaluation = $this->publish_gate->evaluate_post( absint( $post->ID ), false, true );
		$workflow   = $this->read_workflow( $evaluation );
		$facts      = $this->workflow->get_facts( $member['group_uuid'], $language );

		if ( is_array( $facts ) ) {
			$reviewer           = absint( $facts['reviewer_id'] ?? 0 );
			$row['reviewer']    = $reviewer ? (string) get_the_author_meta( 'display_name', $reviewer ) : '';
			$row['reviewed_at'] = (string) ( $facts['reviewed_at_gmt'] ?? '' );
		}

		$row['status']      = (string) $workflow['status'];
		$row['flags']       = (array) $workflow['flags'];
		$row['can_publish'] = ! empty( $workflow['can_publish'] );
		$row['blockers']    = $this->describe_blockers( $evaluation, $post, $language );
		$row['can_review']  = current_user_can( 'edit_post', absint( $post->ID ) )
			&& ! in_array( $row['status'], array( 'reviewed', 'published' ), true );

		return $row;
	}

	/**
	 * Record a human review against the current source fingerprint.
	 *
	 * @param int    $target_id      Translated object ID.
	 * @param string $object_kind    Adapter kind: post, synthetic, or overlay.
	 * @param string $object_subtype Adapter subtype, required for non-post kinds.
	 * @return array|WP_Error
	 */
	public function approve( $target_id, $object_kind = 'post', $object_subtype = '' ) {
		$target_id   = absint( $target_id );
		$object_kind = sanitize_key( $object_kind );

		if ( 'post' === $object_kind ) {
			$post = get_post( $target_id );
			if ( ! is_object( $post ) ) {
				return new WP_Error( 'dsf_review_object', __( 'That translation no longer exists.', 'designstudio-flow' ) );
			}
			$object_subtype = sanitize_key( $post->post_type );
			if ( ! current_user_can( 'edit_post', $target_id ) ) {
				return new WP_Error( 'dsf_review_forbidden', __( 'You are not allowed to review this translation.', 'designstudio-flow' ) );
			}
		} else {
			// Global and catalog translations are not post-backed, so they carry
			// their own capability rather than an object-level one.
			$object_subtype = sanitize_key( $object_subtype );
			if ( ! $this->can_review_non_post( $object_kind, $object_subtype, $target_id ) ) {
				return new WP_Error( 'dsf_review_forbidden', __( 'You are not allowed to review this translation.', 'designstudio-flow' ) );
			}
		}

		$settings = DSF_Multilingual_Settings::get_settings();
		$member   = $this->relationships->find_by_object( $object_kind, $object_subtype, $target_id );
		if ( ! is_array( $member ) ) {
			return new WP_Error( 'dsf_review_relationship', __( 'That object is not part of a translation group.', 'designstudio-flow' ) );
		}
		if ( $member['language'] === $settings['main_language'] ) {
			return new WP_Error( 'dsf_review_main', __( 'The main language does not need review.', 'designstudio-flow' ) );
		}

		$source = $this->relationships->find_member( $member['group_uuid'], $settings['main_language'] );
		if ( ! is_array( $source ) ) {
			return new WP_Error( 'dsf_review_source', __( 'The main-language source is missing.', 'designstudio-flow' ) );
		}

		$payload = DSF_Multilingual_Adapters::fingerprint_payload( $source );
		if ( $payload instanceof WP_Error ) {
			return $payload;
		}

		// Review is recorded against the source version the reviewer actually
		// saw, so a later source edit automatically returns this to stale.
		$authorize = $this->review_authorization( $object_kind, $object_subtype, $target_id );

		$recorded = $this->workflow->record_review_from_payload(
			$member['group_uuid'],
			$member['language'],
			$payload,
			self::ADAPTER_SCHEMA_VERSION,
			$authorize
		);
		if ( $recorded instanceof WP_Error ) {
			return $recorded;
		}

		// Machine output is not reviewed output until a human says so; approving
		// is exactly that moment.
		$this->workflow->set_machine_prefilled( $member['group_uuid'], $member['language'], false, $authorize );
		$this->workflow->set_critical_change( $member['group_uuid'], $member['language'], false, $authorize );

		return array(
			'target_id' => $target_id,
			'status'    => 'post' === $object_kind
				? $this->read_workflow( $this->publish_gate->evaluate_post( $target_id, false, true ) )['status']
				: 'reviewed',
		);
	}

	/**
	 * Whether the current user may review a non-post translation.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $target_id      Object ID.
	 * @return bool
	 */
	private function can_review_non_post( $object_kind, $object_subtype, $target_id ) {
		if ( class_exists( 'DSF_Translation_Overlays' ) && DSF_Translation_Overlays::KIND === $object_kind ) {
			$identity = DSF_Translation_Overlays::decode( $target_id );
			if ( ! $identity['canonical_id'] ) {
				return false;
			}
			return DSF_Translation_Overlays::is_term_subtype( $object_subtype )
				? current_user_can( 'manage_categories' )
				: current_user_can( 'edit_post', $identity['canonical_id'] );
		}

		// Global messages are site configuration.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Build the authorization callback the workflow service re-checks with.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $target_id      Object ID.
	 * @return callable
	 */
	private function review_authorization( $object_kind, $object_subtype, $target_id ) {
		if ( 'post' === $object_kind ) {
			return static function () use ( $target_id ) {
				return current_user_can( 'edit_post', $target_id );
			};
		}

		$review = $this;
		return static function () use ( $review, $object_kind, $object_subtype, $target_id ) {
			return $review->user_can_review( $object_kind, $object_subtype, $target_id );
		};
	}

	/**
	 * Public wrapper so the workflow authorization callback can re-check.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $target_id      Object ID.
	 * @return bool
	 */
	public function user_can_review( $object_kind, $object_subtype, $target_id ) {
		return $this->can_review_non_post( sanitize_key( $object_kind ), sanitize_key( $object_subtype ), absint( $target_id ) );
	}

	/**
	 * Publish a reviewed translation through the central gate.
	 *
	 * @param int $target_id Translated post ID.
	 * @return array|WP_Error
	 */
	public function publish( $target_id ) {
		$target_id = absint( $target_id );
		$post      = get_post( $target_id );
		if ( ! is_object( $post ) ) {
			return new WP_Error( 'dsf_review_object', __( 'That translation no longer exists.', 'designstudio-flow' ) );
		}
		if ( ! current_user_can( 'edit_post', $target_id ) || ! current_user_can( 'publish_post', $target_id ) ) {
			return new WP_Error( 'dsf_review_forbidden', __( 'You are not allowed to publish this translation.', 'designstudio-flow' ) );
		}

		// The gate is authoritative and runs again inside wp_update_post; this
		// call only turns a refusal into a useful message.
		$evaluation = $this->publish_gate->evaluate_post( $target_id, true, false );
		if ( $evaluation instanceof WP_Error ) {
			return $evaluation;
		}

		$updated = wp_update_post(
			array(
				'ID'          => $target_id,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( 'publish' !== get_post_status( $target_id ) ) {
			return new WP_Error( 'dsf_review_publish_blocked', __( 'The publishing gate refused this translation.', 'designstudio-flow' ) );
		}

		return array(
			'target_id' => $target_id,
			'status'    => 'published',
		);
	}

	/**
	 * Turn a gate evaluation into human-readable blocking reasons.
	 *
	 * @param mixed   $evaluation Gate result.
	 * @param WP_Post $post       Translated post.
	 * @param string  $language   Target language.
	 * @return array<int,array<string,string>>
	 */
	private function describe_blockers( $evaluation, $post, $language ) {
		$blockers = array();

		if ( $evaluation instanceof WP_Error ) {
			$code = $evaluation->get_error_code();
			if ( 'dsf_translation_publish_blocked' !== $code ) {
				$blockers[] = array(
					'code'    => (string) $code,
					'message' => (string) $evaluation->get_error_message(),
				);
			}
			$data = $evaluation->get_error_data();
		} else {
			$data = is_array( $evaluation ) ? $evaluation : array();
		}

		$dependencies = is_array( $data ) && isset( $data['dependencies'] ) && is_array( $data['dependencies'] ) ? $data['dependencies'] : array();

		foreach ( array(
			'missing'    => __( 'Missing translation', 'designstudio-flow' ),
			'ineligible' => __( 'Not publishable yet', 'designstudio-flow' ),
		) as $key => $label ) {
			foreach ( (array) ( $dependencies[ $key ] ?? array() ) as $edge ) {
				$blockers[] = array(
					'code'    => 'dependency_' . $key,
					'message' => sprintf(
						/* translators: 1: blocking reason, 2: dependency kind, 3: source path. */
						__( '%1$s: %2$s (%3$s)', 'designstudio-flow' ),
						$label,
						sanitize_text_field( (string) ( $edge['kind'] ?? $edge['dependency_kind'] ?? __( 'related item', 'designstudio-flow' ) ) ),
						sanitize_text_field( (string) ( $edge['source_path'] ?? '' ) )
					),
				);
			}
		}

		if ( ! empty( $dependencies['cycles'] ) ) {
			$blockers[] = array(
				'code'    => 'dependency_cycle',
				'message' => __( 'These translations depend on each other in a loop.', 'designstudio-flow' ),
			);
		}

		if ( ! get_post_meta( $post->ID, '_dsf_translation_title_confirmed', true ) ) {
			$blockers[] = array(
				'code'    => 'title_unconfirmed',
				'message' => __( 'The translated title has not been confirmed.', 'designstudio-flow' ),
			);
		}
		if ( ! get_post_meta( $post->ID, '_dsf_translation_slug_confirmed', true ) ) {
			$blockers[] = array(
				'code'    => 'slug_unconfirmed',
				'message' => __( 'The translated slug has not been confirmed.', 'designstudio-flow' ),
			);
		}

		unset( $language );
		return array_slice( $blockers, 0, 25 );
	}

	/**
	 * Read the derived workflow status out of a gate evaluation.
	 *
	 * @param mixed $evaluation Gate result.
	 * @return array{status:string,flags:array,can_publish:bool}
	 */
	private function read_workflow( $evaluation ) {
		$default = array(
			'status'      => 'blocked',
			'flags'       => array( 'blocked' ),
			'can_publish' => false,
		);

		if ( $evaluation instanceof WP_Error ) {
			$data = $evaluation->get_error_data();
		} elseif ( is_array( $evaluation ) ) {
			$data = $evaluation;
		} else {
			return $default;
		}

		if ( ! is_array( $data ) || ! isset( $data['workflow'] ) || ! is_array( $data['workflow'] ) ) {
			return $default;
		}

		return array(
			'status'      => (string) ( $data['workflow']['status'] ?? 'blocked' ),
			'flags'       => (array) ( $data['workflow']['flags'] ?? array( 'blocked' ) ),
			'can_publish' => ! empty( $data['workflow']['can_publish'] ),
		);
	}

	/**
	 * Fetch a bounded page of main-language source objects.
	 *
	 * @param string[] $post_types    Post types to include.
	 * @param string   $main_language Main language.
	 * @param int      $paged         Page number.
	 * @return array{posts:array,total:int}
	 */
	private function query_main_language_sources( $post_types, $main_language, $paged ) {
		$main_language = DSF_Multilingual_Settings::normalize_locale_code( $main_language );

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => self::MAX_PER_PAGE,
				'paged'                  => $paged,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post->ID ) );
			if ( is_array( $member ) && $main_language === $member['language'] ) {
				$posts[] = $post;
			}
		}

		return array(
			'posts' => $posts,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Post types the dashboard reviews.
	 *
	 * @return string[]
	 */
	private function reviewable_post_types() {
		return array_values(
			array_intersect(
				DSF_Multilingual_Adapters::relationship_post_types(),
				array( 'page', 'post', 'dsf_layout', 'dsf_popup', 'dsf_form', 'dsf_saved_block', 'dsf_template', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' )
			)
		);
	}
}
