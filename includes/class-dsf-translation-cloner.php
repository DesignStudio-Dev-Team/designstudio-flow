<?php
/**
 * Main-language-only clone-to-draft service.
 *
 * A translation is a separate WordPress object that starts life as a draft and
 * stays one until a human reviews it. This service copies the safe parts of a
 * main-language object, maps its relationships to same-language siblings,
 * refuses to carry anything that would make one language overwrite another, and
 * never publishes.
 *
 * Two things are deliberately never copied: the generated HTML snapshot, which
 * is a rendering of the source language, and any active/public template flag.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Cloner {

	const LOCK_TTL      = 30;
	const MAX_TITLE_LEN = 200;

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Translation_Workflow */
	private $workflow;

	/** @var DSF_Language_Routing */
	private $routing;

	/** @var callable Rebuilds the portable dependency edges of a saved object. */
	private $dependency_sync;

	/** @var string Language forced onto the next inserted post. */
	private $suppress_language = '';

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services            = is_array( $services ) ? $services : array();
		$this->relationships = $services['relationships'] ?? DSF_Multilingual::get_instance()->get_relationships();
		$this->workflow      = $services['workflow'] ?? null;
		$this->routing       = $services['routing'] ?? DSF_Language_Routing::get_instance();

		$this->dependency_sync = isset( $services['dependency_sync'] ) && is_callable( $services['dependency_sync'] )
			? $services['dependency_sync']
			: static function ( $post_id ) {
				DSF_Multilingual::get_instance()->sync_post_dependencies( $post_id );
			};
	}

	/**
	 * Return the shared cloner.
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
	 * Clone a main-language object into a target language as a draft.
	 *
	 * @param int    $source_id       Main-language post ID.
	 * @param string $target_language Curated target language.
	 * @param array  $args            Optional title, slug, and copy switches.
	 * @return array{post_id:int,group_uuid:string,language:string,notices:string[]}|WP_Error
	 */
	public function clone_post( $source_id, $target_language, $args = array() ) {
		$args      = is_array( $args ) ? $args : array();
		$source_id = absint( $source_id );
		$settings  = DSF_Multilingual_Settings::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'dsf_clone_disabled', __( 'Multilingual mode is not enabled.', 'designstudio-flow' ) );
		}
		if ( DSF_Multilingual_Conflicts::has_conflicts() ) {
			return new WP_Error( 'dsf_clone_conflict', __( 'Cloning is blocked while another multilingual plugin is active.', 'designstudio-flow' ) );
		}

		$source = get_post( $source_id );
		if ( ! is_object( $source ) || ! in_array( sanitize_key( $source->post_type ), DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return new WP_Error( 'dsf_clone_source', __( 'That object cannot be translated.', 'designstudio-flow' ) );
		}
		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			return new WP_Error( 'dsf_clone_forbidden', __( 'You are not allowed to translate this object.', 'designstudio-flow' ) );
		}

		$post_type_object = get_post_type_object( sanitize_key( $source->post_type ) );
		if ( ! is_object( $post_type_object ) || ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return new WP_Error( 'dsf_clone_forbidden', __( 'You are not allowed to create this kind of object.', 'designstudio-flow' ) );
		}

		$target_language = DSF_Multilingual_Settings::normalize_locale_code( $target_language );
		$main_language   = (string) $settings['main_language'];
		if ( '' === $target_language || ! in_array( $target_language, DSF_Multilingual_Settings::get_enabled_language_codes( $settings ), true ) ) {
			return new WP_Error( 'dsf_clone_language', __( 'Choose an enabled site language.', 'designstudio-flow' ) );
		}
		if ( $target_language === $main_language ) {
			return new WP_Error( 'dsf_clone_language', __( 'The main language already exists for this content.', 'designstudio-flow' ) );
		}

		$member = $this->relationships->find_by_object( 'post', sanitize_key( $source->post_type ), $source_id );
		if ( ! is_array( $member ) ) {
			return new WP_Error( 'dsf_clone_relationship', __( 'This object is not part of a translation group yet.', 'designstudio-flow' ) );
		}
		if ( $member['language'] !== $main_language ) {
			// Only the main language may be a clone source, so translations can
			// never drift through a chain of copies.
			return new WP_Error( 'dsf_clone_source_language', __( 'Translations can only be created from the main language.', 'designstudio-flow' ) );
		}

		$existing = $this->relationships->find_member( $member['group_uuid'], $target_language );
		if ( is_array( $existing ) ) {
			return new WP_Error( 'dsf_clone_exists', __( 'This content already has a translation in that language.', 'designstudio-flow' ) );
		}

		$lock = $this->acquire_lock( $member['group_uuid'], $target_language );
		if ( $lock instanceof WP_Error ) {
			return $lock;
		}

		$created = $this->create_translation( $source, $member, $target_language, $settings, $args );
		$this->release_lock( $member['group_uuid'], $target_language );

		return $created;
	}

	/**
	 * Create and populate the translated draft.
	 *
	 * @param WP_Post $source          Source post.
	 * @param array   $member          Source relationship member.
	 * @param string  $target_language Target language.
	 * @param array   $settings        Multilingual settings.
	 * @param array   $args            Clone options.
	 * @return array|WP_Error
	 */
	private function create_translation( $source, $member, $target_language, $settings, $args ) {
		$notices   = array();
		$post_type = sanitize_key( $source->post_type );

		$title = isset( $args['title'] ) && is_string( $args['title'] ) ? trim( $args['title'] ) : '';
		$title = '' === $title ? (string) $source->post_title : $title;
		$title = mb_substr( sanitize_text_field( $title ), 0, self::MAX_TITLE_LEN );
		if ( '' === $title ) {
			return new WP_Error( 'dsf_clone_title', __( 'Enter a title for the translation.', 'designstudio-flow' ) );
		}

		$slug = isset( $args['slug'] ) && is_string( $args['slug'] ) ? sanitize_title( $args['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( (string) $source->post_name );
		}

		$confirmed_identity = 'require_translated' === ( $settings['clone_identity_policy'] ?? 'copy_unconfirmed' )
			|| ( ! empty( $args['title'] ) && ! empty( $args['slug'] ) );

		$parent = $this->map_parent( $source, $target_language, $notices );

		// The coordinator assigns new posts to the main language by default. This
		// insert must not create a second main-language group, so the assignment
		// is suppressed and the member is added to the source group below.
		$this->suppress_language = $target_language;
		add_filter( 'dsf_multilingual_new_post_language', array( $this, 'suppress_default_language' ), 99 );
		// The relationship row is written after the insert, so the routing service
		// is told which language this post will belong to before its slug is
		// decided. Without it the copy would be treated as main-language content
		// and lose the shared slug.
		if ( method_exists( $this->routing, 'expect_language' ) ) {
			$this->routing->expect_language( $target_language );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'draft',
				'post_title'     => $title,
				'post_name'      => $slug,
				'post_content'   => (string) $source->post_content,
				'post_excerpt'   => (string) $source->post_excerpt,
				'post_parent'    => $parent,
				'menu_order'     => (int) $source->menu_order,
				'comment_status' => (string) $source->comment_status,
				'ping_status'    => (string) $source->ping_status,
				'post_author'    => get_current_user_id(),
			),
			true
		);

		remove_filter( 'dsf_multilingual_new_post_language', array( $this, 'suppress_default_language' ), 99 );
		$this->suppress_language = '';
		if ( method_exists( $this->routing, 'expect_language' ) ) {
			$this->routing->expect_language( '' );
		}

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'dsf_clone_insert', __( 'The translation draft could not be created.', 'designstudio-flow' ) );
		}
		$post_id = absint( $post_id );

		$added = $this->relationships->add_member( $member['group_uuid'], 'post', $post_type, $post_id, $target_language );
		if ( $added instanceof WP_Error ) {
			// Without a relationship the draft is an orphan that would later be
			// treated as new main-language content.
			wp_delete_post( $post_id, true );
			return $added;
		}

		$this->copy_meta( $source, $post_id, $post_type, $target_language, $args, $notices );
		$this->copy_taxonomies( $source, $post_id, $post_type, $target_language, $notices );

		if ( ! $confirmed_identity ) {
			// A copied title and slug are still source-language text. The publish
			// gate keeps the draft private until an editor confirms both.
			delete_post_meta( $post_id, '_dsf_translation_title_confirmed' );
			delete_post_meta( $post_id, '_dsf_translation_slug_confirmed' );
			$notices[] = __( 'The title and slug were copied from the main language. Confirm both before publishing.', 'designstudio-flow' );
		} else {
			update_post_meta( $post_id, '_dsf_translation_title_confirmed', 1 );
			update_post_meta( $post_id, '_dsf_translation_slug_confirmed', 1 );
		}

		// A snapshot is a rendering of the source language and must never travel.
		delete_post_meta( $post_id, '_dsf_html_snapshot' );

		call_user_func( $this->dependency_sync, $post_id );
		$this->routing->sync_post_route( $post_id );

		return array(
			'post_id'    => $post_id,
			'group_uuid' => $member['group_uuid'],
			'language'   => $target_language,
			'edit_link'  => (string) get_edit_post_link( $post_id, 'raw' ),
			'notices'    => $notices,
		);
	}

	/**
	 * Force the target language for the post being inserted right now.
	 *
	 * @param string $language Default language.
	 * @return string
	 */
	public function suppress_default_language( $language ) {
		return '' !== $this->suppress_language ? '' : $language;
	}

	/**
	 * Resolve the translated parent, or detach when it does not exist yet.
	 *
	 * @param WP_Post $source          Source post.
	 * @param string  $target_language Target language.
	 * @param array   $notices         Notices collected for the editor.
	 * @return int
	 */
	private function map_parent( $source, $target_language, &$notices ) {
		$parent = absint( $source->post_parent );
		if ( ! $parent ) {
			return 0;
		}

		$sibling = $this->find_sibling( 'post', get_post_type( $parent ), $parent, $target_language );
		if ( $sibling ) {
			return $sibling;
		}

		$notices[] = __( 'The parent page has no translation yet, so this draft was created at the top level.', 'designstudio-flow' );
		return 0;
	}

	/**
	 * Copy the allowlisted meta, mapping every language-sensitive reference.
	 *
	 * @param WP_Post $source          Source post.
	 * @param int     $post_id         New post ID.
	 * @param string  $post_type       Post type.
	 * @param string  $target_language Target language.
	 * @param array   $args            Clone options.
	 * @param array   $notices         Notices collected for the editor.
	 */
	private function copy_meta( $source, $post_id, $post_type, $target_language, $args, &$notices ) {
		foreach ( $this->meta_keys_for_type( $post_type ) as $key ) {
			$value = get_post_meta( $source->ID, $key, true );
			if ( '' === $value || null === $value ) {
				continue;
			}

			if ( in_array( $key, array( '_dsf_blocks', '_dsf_template_blocks' ), true ) ) {
				$value = $this->map_blocks( $value, $target_language, $notices );
			} elseif ( '_dsf_settings' === $key ) {
				$value = $this->map_settings( $value, $target_language, $args, $notices );
			} elseif ( '_dsf_block_settings' === $key ) {
				$value = is_array( $value ) ? $value : array();
			}

			update_post_meta( $post_id, $key, $value );
		}

		if ( ! empty( $args['copy_featured_image'] ) || ! isset( $args['copy_featured_image'] ) ) {
			$thumbnail = absint( get_post_meta( $source->ID, '_thumbnail_id', true ) );
			if ( $thumbnail ) {
				// Media is shared, not duplicated: both languages point at the same
				// attachment so a translation never doubles the media library.
				update_post_meta( $post_id, '_thumbnail_id', $thumbnail );
			}
		}
	}

	/**
	 * Copy taxonomy terms, mapping to translated terms when they exist.
	 *
	 * @param WP_Post $source          Source post.
	 * @param int     $post_id         New post ID.
	 * @param string  $post_type       Post type.
	 * @param string  $target_language Target language.
	 * @param array   $notices         Notices collected for the editor.
	 */
	private function copy_taxonomies( $source, $post_id, $post_type, $target_language, &$notices ) {
		$taxonomies = array_intersect(
			(array) get_object_taxonomies( $post_type ),
			DSF_Multilingual_Adapters::relationship_taxonomies()
		);

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$mapped  = array();
			$missing = false;
			foreach ( array_slice( (array) $terms, 0, 50 ) as $term_id ) {
				$sibling = $this->find_sibling( 'term', $taxonomy, $term_id, $target_language );
				if ( $sibling ) {
					$mapped[] = $sibling;
				} else {
					$missing = true;
				}
			}

			if ( $missing ) {
				$notices[] = sprintf(
					/* translators: %s: taxonomy name. */
					__( 'Some %s terms have no translation yet and were left off this draft.', 'designstudio-flow' ),
					$taxonomy
				);
			}
			if ( ! empty( $mapped ) ) {
				wp_set_object_terms( $post_id, $mapped, $taxonomy );
			}
		}
	}

	/**
	 * Map language-sensitive references inside a blocks array.
	 *
	 * @param mixed  $blocks          Stored blocks.
	 * @param string $target_language Target language.
	 * @param array  $notices         Notices collected for the editor.
	 * @return array
	 */
	private function map_blocks( $blocks, $target_language, &$notices ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		$detached = false;
		$forms    = false;

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				unset( $blocks[ $index ] );
				continue;
			}

			if ( ! empty( $block['savedBlockId'] ) ) {
				$sibling = $this->find_sibling( 'post', 'dsf_saved_block', absint( $block['savedBlockId'] ), $target_language );
				if ( $sibling ) {
					$blocks[ $index ]['savedBlockId'] = $sibling;
				} else {
					// Keeping the source-language saved block would let a later
					// global sync overwrite this translation with source copy.
					unset( $blocks[ $index ]['savedBlockId'] );
					$detached = true;
				}
			}

			if ( isset( $block['settings'] ) && is_array( $block['settings'] ) && ! empty( $block['settings']['formId'] ) ) {
				$sibling = $this->find_sibling( 'post', 'dsf_form', absint( $block['settings']['formId'] ), $target_language );
				if ( $sibling ) {
					$blocks[ $index ]['settings']['formId'] = $sibling;
				} else {
					$forms = true;
				}
			}
		}

		if ( $detached ) {
			$notices[] = __( 'Saved blocks without a translation were detached so this language cannot be overwritten by the source language.', 'designstudio-flow' );
		}
		if ( $forms ) {
			$notices[] = __( 'A form on this page has no translation yet. Publishing stays blocked until it does.', 'designstudio-flow' );
		}

		return array_values( $blocks );
	}

	/**
	 * Map the header, footer, and popup references of a settings array.
	 *
	 * @param mixed  $settings        Stored settings.
	 * @param string $target_language Target language.
	 * @param array  $args            Clone options.
	 * @param array  $notices         Notices collected for the editor.
	 * @return array
	 */
	private function map_settings( $settings, $target_language, $args, &$notices ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$missing = array();

		foreach ( array( 'headerTemplateId', 'footerTemplateId' ) as $key ) {
			if ( empty( $settings['layout'][ $key ] ) ) {
				continue;
			}
			$sibling = $this->find_sibling( 'post', 'dsf_layout', absint( $settings['layout'][ $key ] ), $target_language );
			if ( $sibling ) {
				$settings['layout'][ $key ] = $sibling;
			} else {
				unset( $settings['layout'][ $key ] );
				$missing[] = 'headerTemplateId' === $key ? __( 'header', 'designstudio-flow' ) : __( 'footer', 'designstudio-flow' );
			}
		}

		if ( ! empty( $settings['popupId'] ) ) {
			if ( isset( $args['copy_popup'] ) && ! $args['copy_popup'] ) {
				unset( $settings['popupId'] );
			} else {
				$sibling = $this->find_sibling( 'post', 'dsf_popup', absint( $settings['popupId'] ), $target_language );
				if ( $sibling ) {
					$settings['popupId'] = $sibling;
				} else {
					unset( $settings['popupId'] );
					$missing[] = __( 'popup', 'designstudio-flow' );
				}
			}
		}

		if ( isset( $args['copy_seo'] ) && ! $args['copy_seo'] ) {
			unset( $settings['seo'] );
		}

		if ( ! empty( $missing ) ) {
			$notices[] = sprintf(
				/* translators: %s: comma-separated list of missing dependencies. */
				__( 'These related items have no translation yet and were left unassigned: %s.', 'designstudio-flow' ),
				implode( ', ', $missing )
			);
		}

		return $settings;
	}

	/**
	 * Return the meta keys a clone may carry for a post type.
	 *
	 * Active/public template flags are excluded: a clone must never become
	 * publicly visible as a side effect of being created.
	 *
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	private function meta_keys_for_type( $post_type ) {
		$keys = class_exists( 'DSF_Import_Export' )
			? DSF_Import_Export::get_instance()->get_meta_keys_for_type( $post_type )
			: DSF_Multilingual_Adapters::fingerprint_meta_keys( $post_type );

		$excluded = array( '_dsf_html_snapshot', '_dsf_pt_active', '_dsf_st_active', '_dsf_bt_active' );
		return array_values( array_diff( (array) $keys, $excluded ) );
	}

	/**
	 * Resolve the same-language sibling of an object.
	 *
	 * @param string $kind            Object kind.
	 * @param string $subtype         Object subtype.
	 * @param int    $object_id       Source object ID.
	 * @param string $target_language Target language.
	 * @return int Zero when no sibling exists.
	 */
	private function find_sibling( $kind, $subtype, $object_id, $target_language ) {
		$subtype = sanitize_key( (string) $subtype );
		$member  = $this->relationships->find_by_object( $kind, $subtype, absint( $object_id ) );
		if ( ! is_array( $member ) ) {
			return 0;
		}
		$sibling = $this->relationships->find_member( $member['group_uuid'], $target_language );
		return is_array( $sibling ) ? absint( $sibling['object_id'] ) : 0;
	}

	/**
	 * Take a short-lived lock for one group and language.
	 *
	 * The database unique key is the real authority; this only turns a losing
	 * concurrent request into a clear message instead of a failed insert.
	 *
	 * @param string $group_uuid Group UUID.
	 * @param string $language   Target language.
	 * @return true|WP_Error
	 */
	private function acquire_lock( $group_uuid, $language ) {
		$key = $this->lock_key( $group_uuid, $language );
		if ( false !== get_transient( $key ) ) {
			return new WP_Error( 'dsf_clone_in_progress', __( 'A translation into that language is already being created.', 'designstudio-flow' ) );
		}
		set_transient( $key, 1, self::LOCK_TTL );
		return true;
	}

	/**
	 * Release a clone lock.
	 *
	 * @param string $group_uuid Group UUID.
	 * @param string $language   Target language.
	 */
	private function release_lock( $group_uuid, $language ) {
		delete_transient( $this->lock_key( $group_uuid, $language ) );
	}

	/**
	 * Build the lock transient key.
	 *
	 * @param string $group_uuid Group UUID.
	 * @param string $language   Target language.
	 * @return string
	 */
	private function lock_key( $group_uuid, $language ) {
		return 'dsf_clone_' . md5( (string) $group_uuid . '|' . (string) $language );
	}
}
