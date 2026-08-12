<?php
/**
 * Authoring UI for WooCommerce catalog translations.
 *
 * Products and catalog terms are never duplicated, so their translations are
 * edited in place: one panel per secondary language on the object an editor is
 * already working on. Saving records the translation and, because it is new
 * text, clears any previous review so it goes back through the dashboard.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Overlay_Admin {

	const NONCE_ACTION = 'dsf_overlay_save';
	const NONCE_FIELD  = 'dsf_overlay_nonce';
	const FIELD_NAME   = 'dsf_overlay';

	/** @var self|null */
	private static $instance = null;

	/** @var object|null Injected relationship service. */
	private $relationships;

	/** @var object|null Injected workflow service. */
	private $workflow;

	/** @var object|null Injected routing service. */
	private $routing;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services            = is_array( $services ) ? $services : array();
		$this->relationships = $services['relationships'] ?? null;
		$this->workflow      = $services['workflow'] ?? null;
		$this->routing       = $services['routing'] ?? null;
	}

	/**
	 * Return the shared admin service.
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
	 * Resolve the relationship service.
	 *
	 * Resolving the coordinator boots every multilingual hook, so it is only
	 * touched when a caller did not supply the service itself.
	 *
	 * @return object|null
	 */
	private function relationships() {
		if ( is_object( $this->relationships ) ) {
			return $this->relationships;
		}
		return class_exists( 'DSF_Multilingual' ) ? DSF_Multilingual::get_instance()->get_relationships() : null;
	}

	/**
	 * Resolve the workflow service.
	 *
	 * @return object|null
	 */
	private function workflow() {
		if ( is_object( $this->workflow ) ) {
			return $this->workflow;
		}
		return class_exists( 'DSF_Multilingual' ) ? DSF_Multilingual::get_instance()->get_workflow() : null;
	}

	/** Register the product panel and the catalog term fields. */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product' ), 10, 2 );

		foreach ( $this->term_taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_fields' ), 20, 2 );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_term' ), 10, 2 );
		}
	}

	/** Catalog taxonomies that support overlays. */
	private function term_taxonomies() {
		return array_values(
			array_filter(
				DSF_Translation_Overlays::subtypes(),
				array( 'DSF_Translation_Overlays', 'is_term_subtype' )
			)
		);
	}

	/** The secondary languages an editor can translate into. */
	private function target_languages() {
		$settings = DSF_Multilingual_Settings::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return array();
		}
		return array_values(
			array_diff(
				DSF_Multilingual_Settings::get_enabled_language_codes( $settings ),
				array( $settings['main_language'] )
			)
		);
	}

	/** Register the product translation panel. */
	public function register_meta_box() {
		if ( empty( $this->target_languages() ) ) {
			return;
		}

		add_meta_box(
			'dsf-translation-overlay',
			__( 'Translations', 'designstudio-flow' ),
			array( $this, 'render_product_panel' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Render the per-language product fields.
	 *
	 * @param WP_Post $post Product being edited.
	 */
	public function render_product_panel( $post ) {
		$languages = $this->target_languages();
		if ( empty( $languages ) || ! is_object( $post ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<p class="description">' . esc_html__( 'Translate what shoppers read. Price, SKU, stock, attributes, and variations stay shared across every language.', 'designstudio-flow' ) . '</p>';

		foreach ( $languages as $language ) {
			$record = DSF_Language_Context::describe( $language );
			$fields = DSF_Translation_Overlays::get_fields( 'product', $post->ID, $language );
			$status = $this->status_label( 'product', absint( $post->ID ), $language );

			echo '<fieldset style="margin:16px 0;padding:12px;border:1px solid #dcdcde;border-radius:4px;">';
			echo '<legend style="font-weight:600;padding:0 6px;">' . esc_html( $record['native_label'] ?? $language );
			if ( '' !== $status ) {
				echo ' <span style="font-weight:400;opacity:.7;">— ' . esc_html( $status ) . '</span>';
			}
			echo '</legend>';

			$this->render_field(
				$language,
				'title',
				__( 'Product name', 'designstudio-flow' ),
				$fields['title'] ?? '',
				'text'
			);
			$this->render_field(
				$language,
				'excerpt',
				__( 'Short description', 'designstudio-flow' ),
				$fields['excerpt'] ?? '',
				'textarea'
			);
			$this->render_field(
				$language,
				'content',
				__( 'Description', 'designstudio-flow' ),
				$fields['content'] ?? '',
				'textarea'
			);

			echo '</fieldset>';
		}
	}

	/**
	 * Render the per-language fields on a catalog term screen.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Taxonomy name.
	 */
	public function render_term_fields( $term, $taxonomy = '' ) {
		$languages = $this->target_languages();
		if ( empty( $languages ) || ! is_object( $term ) ) {
			return;
		}

		$taxonomy = sanitize_key( $taxonomy ? $taxonomy : ( $term->taxonomy ?? '' ) );
		if ( ! DSF_Translation_Overlays::is_term_subtype( $taxonomy ) ) {
			return;
		}

		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Translations', 'designstudio-flow' ) . '</th><td>';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		foreach ( $languages as $language ) {
			$record = DSF_Language_Context::describe( $language );
			$fields = DSF_Translation_Overlays::get_fields( $taxonomy, $term->term_id, $language );

			echo '<fieldset style="margin:0 0 14px;padding:10px;border:1px solid #dcdcde;border-radius:4px;">';
			echo '<legend style="font-weight:600;padding:0 6px;">' . esc_html( $record['native_label'] ?? $language ) . '</legend>';
			$this->render_field( $language, 'title', __( 'Name', 'designstudio-flow' ), $fields['title'] ?? '', 'text' );
			$this->render_field( $language, 'content', __( 'Description', 'designstudio-flow' ), $fields['content'] ?? '', 'textarea' );
			echo '</fieldset>';
		}

		echo '<p class="description">' . esc_html__( 'The slug is never translated: it is used by product filters and variation matching.', 'designstudio-flow' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Render one labelled field.
	 *
	 * @param string $language Language code.
	 * @param string $key      Field key.
	 * @param string $label    Field label.
	 * @param string $value    Current value.
	 * @param string $type     Control type.
	 */
	private function render_field( $language, $key, $label, $value, $type ) {
		$name = self::FIELD_NAME . '[' . esc_attr( $language ) . '][' . esc_attr( $key ) . ']';
		$id   = 'dsf-overlay-' . sanitize_html_class( $language . '-' . $key );

		echo '<p style="margin:8px 0;">';
		echo '<label for="' . esc_attr( $id ) . '" style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html( $label ) . '</label>';

		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="large-text" />';
		}

		echo '</p>';
	}

	/**
	 * Persist submitted product overlays.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Product object.
	 */
	public function save_product( $post_id, $post = null ) {
		unset( $post );
		$post_id = absint( $post_id );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $this->submission_is_valid() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->store_submitted( 'product', $post_id );
	}

	/**
	 * Persist submitted term overlays.
	 *
	 * @param int $term_id          Term ID.
	 * @param int $term_taxonomy_id Term taxonomy ID.
	 */
	public function save_term( $term_id, $term_taxonomy_id = 0 ) {
		unset( $term_taxonomy_id );
		if ( ! $this->submission_is_valid() || ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$term = get_term( absint( $term_id ) );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return;
		}

		$this->store_submitted( sanitize_key( $term->taxonomy ), absint( $term_id ) );
	}

	/**
	 * Whether the request carries a valid overlay submission.
	 *
	 * @return bool
	 */
	private function submission_is_valid() {
		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST[ self::FIELD_NAME ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Store every submitted language for one canonical object.
	 *
	 * Values reach storage only through the overlay sanitizer, which rebuilds
	 * them from known display keys, so no operational field can be written from
	 * this form regardless of what was posted.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 */
	private function store_submitted( $subtype, $canonical_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- submission_is_valid() verified the action nonce immediately before this call.
		if ( ! isset( $_POST[ self::FIELD_NAME ] ) || ! is_array( $_POST[ self::FIELD_NAME ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- flatten_submission() reduces the payload to bounded strings, and DSF_Translation_Overlays::sanitize_fields() rebuilds it from known display keys before storage.
		$submitted = $this->flatten_submission( wp_unslash( $_POST[ self::FIELD_NAME ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $submitted ) ) {
			return;
		}

		$languages = $this->target_languages();
		foreach ( $submitted as $language => $fields ) {
			$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
			if ( '' === $language || ! in_array( $language, $languages, true ) ) {
				continue;
			}

			$before = DSF_Translation_Overlays::get_fields( $subtype, $canonical_id, $language );
			$stored = DSF_Translation_Overlays::save_fields( $subtype, $canonical_id, $language, $fields );
			if ( $stored instanceof WP_Error ) {
				continue;
			}

			$this->register_member( $subtype, $canonical_id, $language, ! empty( $stored ) );
			$this->sync_route( $subtype, $canonical_id, $language, ! empty( $stored ) );

			if ( $before !== $stored ) {
				// New text has not been reviewed, whoever typed it.
				$this->clear_review( $subtype, $canonical_id, $language );
			}
		}
	}

	/**
	 * Reduce a submitted overlay array to bounded language/field strings.
	 *
	 * The overlay sanitizer is still the authority on what may be stored; this
	 * only guarantees the shape handed to it is scalar text, so nothing deeper
	 * than a two-level array of strings ever reaches storage.
	 *
	 * @param mixed $submitted Raw submitted value.
	 * @return array<string,array<string,string>>
	 */
	private function flatten_submission( $submitted ) {
		if ( ! is_array( $submitted ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $submitted, 0, DSF_Multilingual_Settings::MAX_LANGUAGES, true ) as $language => $fields ) {
			if ( ! is_string( $language ) || ! is_array( $fields ) ) {
				continue;
			}
			$row = array();
			foreach ( array_slice( $fields, 0, 8, true ) as $key => $value ) {
				if ( is_string( $key ) && is_scalar( $value ) ) {
					$row[ $key ] = (string) $value;
				}
			}
			if ( ! empty( $row ) ) {
				$clean[ $language ] = $row;
			}
		}

		return $clean;
	}

	/**
	 * Ensure the overlay has a relationship member in its group.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @param bool   $has_content  Whether the overlay holds any text.
	 */
	private function register_member( $subtype, $canonical_id, $language, $has_content ) {
		$relationships = $has_content ? $this->relationships() : null;
		if ( ! $relationships ) {
			return;
		}

		$settings   = DSF_Multilingual_Settings::get_settings();
		$overlay_id = DSF_Translation_Overlays::overlay_id( $canonical_id, $language );
		$source_id  = DSF_Translation_Overlays::overlay_id( $canonical_id, $settings['main_language'] );
		if ( ! $overlay_id || ! $source_id ) {
			return;
		}

		if ( is_array( $relationships->find_by_object( DSF_Translation_Overlays::KIND, $subtype, $overlay_id ) ) ) {
			return;
		}

		// The canonical object is the main-language member of its own group, so
		// the group is created from it the first time any language is written.
		$source = $relationships->find_by_object( DSF_Translation_Overlays::KIND, $subtype, $source_id );
		if ( ! is_array( $source ) ) {
			$source = $relationships->create_group( DSF_Translation_Overlays::KIND, $subtype, $source_id, $settings['main_language'] );
		}
		if ( ! is_array( $source ) ) {
			return;
		}

		$relationships->add_member( $source['group_uuid'], DSF_Translation_Overlays::KIND, $subtype, $overlay_id, $language );
	}

	/**
	 * Create or drop the prefixed catalog route for one overlay.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @param bool   $has_content  Whether the overlay holds any text.
	 */
	private function sync_route( $subtype, $canonical_id, $language, $has_content ) {
		$routing = is_object( $this->routing ) ? $this->routing : null;
		if ( ! $routing && class_exists( 'DSF_Language_Routing' ) ) {
			$routing = DSF_Language_Routing::get_instance();
		}
		if ( ! $routing ) {
			return;
		}

		if ( $has_content ) {
			$routing->sync_overlay_route( $subtype, $canonical_id, $language );
			return;
		}

		// An emptied overlay has nothing to show, so its prefixed URL goes away.
		$overlay_id = DSF_Translation_Overlays::overlay_id( $canonical_id, $language );
		if ( $overlay_id && class_exists( 'DSF_Translation_Routes' ) ) {
			DSF_Translation_Routes::get_instance()->delete_route( DSF_Translation_Overlays::KIND, sanitize_key( $subtype ), $overlay_id );
		}
	}

	/**
	 * Return an edited overlay to unreviewed.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 */
	private function clear_review( $subtype, $canonical_id, $language ) {
		$relationships = $this->relationships();
		$workflow      = $this->workflow();
		if ( ! $relationships || ! $workflow ) {
			return;
		}

		$member = $relationships->find_by_object(
			DSF_Translation_Overlays::KIND,
			$subtype,
			DSF_Translation_Overlays::overlay_id( $canonical_id, $language )
		);
		if ( ! is_array( $member ) ) {
			return;
		}

		$workflow->clear_review(
			$member['group_uuid'],
			$language,
			static function () {
				return true;
			}
		);
	}

	/**
	 * Describe the current review state of one overlay.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @return string
	 */
	private function status_label( $subtype, $canonical_id, $language ) {
		$relationships = $this->relationships();
		$workflow      = $this->workflow();
		if ( ! $relationships || ! $workflow ) {
			return '';
		}

		$overlay_id = DSF_Translation_Overlays::overlay_id( $canonical_id, $language );
		$member     = $overlay_id
			? $relationships->find_by_object( DSF_Translation_Overlays::KIND, $subtype, $overlay_id )
			: null;
		if ( ! is_array( $member ) ) {
			return __( 'Not translated yet', 'designstudio-flow' );
		}

		$facts = $workflow->get_facts( $member['group_uuid'], $language );
		if ( is_array( $facts ) && ! empty( $facts['reviewer_id'] ) ) {
			return __( 'Reviewed', 'designstudio-flow' );
		}

		return __( 'Needs review', 'designstudio-flow' );
	}
}
