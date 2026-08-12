<?php
/**
 * WooCommerce catalog translation overlays.
 *
 * A product is one operational record. Duplicating it per language would
 * duplicate its SKU, stock, price, variations, and every order that references
 * it, so translations are stored as display overlays over the canonical object
 * instead: only visitor-facing text is translated, and it is swapped in at
 * render time for the resolved request language.
 *
 * Everything operational — IDs, SKUs, prices, tax, stock, downloads, variation
 * attributes, cart and order data — stays canonical and is never touched here.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Overlays {

	const KIND        = 'overlay';
	const META_KEY    = '_dsf_translation_overlay';
	const SLOT_STRIDE = 100;
	const MAX_TITLE   = 300;
	const MAX_TEXT    = 100000;

	/** @var self|null */
	private static $instance = null;

	/** @var array<string,array|null> Per-request resolution cache. */
	private $resolved = array();

	/**
	 * Return the shared overlay service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register adapter and display integration. */
	public function register_hooks() {
		add_filter( 'dsf_multilingual_object_exists', array( $this, 'object_exists' ), 10, 4 );
		add_filter( 'dsf_multilingual_fingerprint_payload', array( $this, 'fingerprint_payload' ), 10, 2 );

		if ( is_admin() ) {
			return;
		}

		// Display only. Nothing below changes what is stored, ordered, or priced.
		add_filter( 'woocommerce_product_get_name', array( $this, 'filter_product_name' ), 10, 2 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'filter_product_excerpt' ), 10, 2 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'filter_product_content' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_name', array( $this, 'filter_product_name' ), 10, 2 );
		add_filter( 'the_title', array( $this, 'filter_post_title' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_post_excerpt' ), 10, 2 );
		add_filter( 'get_term', array( $this, 'filter_term' ), 10, 2 );
	}

	/**
	 * Catalog subtypes that carry translatable display text.
	 *
	 * @return string[]
	 */
	public static function subtypes() {
		$subtypes = array( 'product', 'product_variation', 'product_cat', 'product_tag' );

		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$subtypes = array_merge( $subtypes, (array) wc_get_attribute_taxonomy_names() );
		}

		/**
		 * Filters the catalog subtypes that support translation overlays.
		 *
		 * Operational records — orders, refunds, coupons, subscriptions — must
		 * never be added.
		 *
		 * @param string[] $subtypes Subtype names.
		 */
		$subtypes = apply_filters( 'dsf_translation_overlay_subtypes', $subtypes );
		$subtypes = is_array( $subtypes ) ? array_slice( $subtypes, 0, 128 ) : array();

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $subtypes ),
					static function ( $subtype ) {
						return '' !== $subtype && ! in_array( $subtype, array( 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_subscription' ), true );
					}
				)
			)
		);
	}

	/**
	 * Whether a subtype addresses a term rather than a post.
	 *
	 * @param string $subtype Catalog subtype.
	 * @return bool
	 */
	public static function is_term_subtype( $subtype ) {
		$subtype = sanitize_key( $subtype );
		return in_array( $subtype, array( 'product_cat', 'product_tag' ), true ) || 0 === strpos( $subtype, 'pa_' );
	}

	/**
	 * The stable slot reserved for one language.
	 *
	 * Slots come from the curated registry, not from the site's ordering, so a
	 * stored overlay identity survives languages being reordered or removed.
	 *
	 * @param string $language Language code.
	 * @return int Zero when the language is not curated.
	 */
	public static function language_slot( $language ) {
		$reserved = DSF_Multilingual_Adapters::synthetic_notification_id( $language );
		return $reserved > 1000 ? $reserved - 1000 : 0;
	}

	/**
	 * Resolve a slot back to its language.
	 *
	 * @param int $slot Reserved slot.
	 * @return string
	 */
	public static function slot_language( $slot ) {
		return DSF_Multilingual_Adapters::synthetic_notification_language( 1000 + absint( $slot ) );
	}

	/**
	 * Encode the relationship identity of one canonical object in one language.
	 *
	 * @param int    $canonical_id Canonical product or term ID.
	 * @param string $language     Language code.
	 * @return int Zero when either part is invalid.
	 */
	public static function overlay_id( $canonical_id, $language ) {
		$canonical_id = absint( $canonical_id );
		$slot         = self::language_slot( $language );
		if ( ! $canonical_id || ! $slot || $slot >= self::SLOT_STRIDE ) {
			return 0;
		}
		return ( $canonical_id * self::SLOT_STRIDE ) + $slot;
	}

	/**
	 * Decode an overlay identity.
	 *
	 * @param int $overlay_id Encoded identity.
	 * @return array{canonical_id:int,language:string}
	 */
	public static function decode( $overlay_id ) {
		$overlay_id = absint( $overlay_id );
		$slot       = $overlay_id % self::SLOT_STRIDE;
		$canonical  = intdiv( $overlay_id, self::SLOT_STRIDE );
		$language   = self::slot_language( $slot );

		return array(
			'canonical_id' => $language && $canonical ? $canonical : 0,
			'language'     => $language && $canonical ? $language : '',
		);
	}

	/**
	 * Read the stored overlay fields for one language.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @return array<string,string>
	 */
	public static function get_fields( $subtype, $canonical_id, $language ) {
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		if ( '' === $language ) {
			return array();
		}

		$stored = self::is_term_subtype( $subtype )
			? get_term_meta( absint( $canonical_id ), self::META_KEY, true )
			: get_post_meta( absint( $canonical_id ), self::META_KEY, true );

		if ( ! is_array( $stored ) || ! isset( $stored[ $language ] ) || ! is_array( $stored[ $language ] ) ) {
			return array();
		}

		return self::sanitize_fields( $stored[ $language ] );
	}

	/**
	 * Store overlay fields for one language.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @param array  $fields       Candidate fields.
	 * @return array<string,string>|WP_Error
	 */
	public static function save_fields( $subtype, $canonical_id, $language, $fields ) {
		$subtype      = sanitize_key( $subtype );
		$canonical_id = absint( $canonical_id );
		$language     = DSF_Multilingual_Settings::normalize_locale_code( $language );

		if ( ! in_array( $subtype, self::subtypes(), true ) || ! $canonical_id || '' === $language ) {
			return new WP_Error( 'dsf_overlay_identity', __( 'That catalog translation target is invalid.', 'designstudio-flow' ) );
		}

		$capability = self::is_term_subtype( $subtype ) ? 'manage_categories' : 'edit_post';
		$allowed    = self::is_term_subtype( $subtype )
			? current_user_can( $capability )
			: current_user_can( $capability, $canonical_id );
		if ( ! $allowed ) {
			return new WP_Error( 'dsf_overlay_forbidden', __( 'You are not allowed to translate this catalog item.', 'designstudio-flow' ) );
		}

		$is_term = self::is_term_subtype( $subtype );
		$stored  = $is_term
			? get_term_meta( $canonical_id, self::META_KEY, true )
			: get_post_meta( $canonical_id, self::META_KEY, true );
		$stored  = is_array( $stored ) ? $stored : array();

		$clean               = self::sanitize_fields( $fields );
		$stored[ $language ] = $clean;
		$stored              = array_slice( $stored, 0, DSF_Multilingual_Settings::MAX_LANGUAGES, true );

		if ( $is_term ) {
			update_term_meta( $canonical_id, self::META_KEY, $stored );
		} else {
			update_post_meta( $canonical_id, self::META_KEY, $stored );
		}

		return $clean;
	}

	/**
	 * Rebuild overlay fields from known keys.
	 *
	 * Price, SKU, stock, attribute and identity values are absent by design: an
	 * overlay can only ever carry display text.
	 *
	 * @param mixed $fields Candidate fields.
	 * @return array<string,string>
	 */
	public static function sanitize_fields( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();
		$clean  = array();

		if ( isset( $fields['title'] ) && is_scalar( $fields['title'] ) ) {
			$title = mb_substr( sanitize_text_field( (string) $fields['title'] ), 0, self::MAX_TITLE );
			if ( '' !== $title ) {
				$clean['title'] = $title;
			}
		}
		foreach ( array( 'excerpt', 'content' ) as $key ) {
			if ( ! isset( $fields[ $key ] ) || ! is_scalar( $fields[ $key ] ) ) {
				continue;
			}
			$value = mb_substr( wp_kses_post( (string) $fields[ $key ] ), 0, self::MAX_TEXT );
			if ( '' !== trim( $value ) ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Resolve the overlay to display for the current request.
	 *
	 * An overlay is only shown once a human has reviewed it against the current
	 * canonical source. Anything else falls back to the canonical text, which is
	 * correct here: the catalog object itself is shared, not translated.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @return array<string,string>
	 */
	public function resolve_display( $subtype, $canonical_id ) {
		$subtype      = sanitize_key( $subtype );
		$canonical_id = absint( $canonical_id );
		if ( ! $canonical_id || ! class_exists( 'DSF_Language_Context' ) ) {
			return array();
		}

		$context = DSF_Language_Context::get_instance();
		if ( ! $context->is_active() ) {
			return array();
		}

		$language = $context->get_request_language();
		if ( $language === $context->get_main_language() ) {
			return array();
		}

		$cache_key = $subtype . '|' . $canonical_id . '|' . $language;
		if ( array_key_exists( $cache_key, $this->resolved ) ) {
			return $this->resolved[ $cache_key ];
		}

		$fields = array();
		if ( in_array( $subtype, self::subtypes(), true ) && $this->overlay_is_reviewed( $subtype, $canonical_id, $language ) ) {
			$fields = self::get_fields( $subtype, $canonical_id, $language );
		}

		$this->resolved[ $cache_key ] = $fields;
		return $fields;
	}

	/** Forget resolved overlays, normally between requests in tests. */
	public function flush() {
		$this->resolved = array();
	}

	/**
	 * Whether an overlay has been reviewed against the current canonical source.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Language code.
	 * @return bool
	 */
	private function overlay_is_reviewed( $subtype, $canonical_id, $language ) {
		if ( ! class_exists( 'DSF_Multilingual' ) ) {
			return false;
		}

		$overlay_id = self::overlay_id( $canonical_id, $language );
		if ( ! $overlay_id ) {
			return false;
		}

		$coordinator = DSF_Multilingual::get_instance();
		$member      = $coordinator->get_relationships()->find_by_object( self::KIND, $subtype, $overlay_id );
		if ( ! is_array( $member ) || $member['language'] !== $language ) {
			return false;
		}

		$facts = $coordinator->get_workflow()->get_facts( $member['group_uuid'], $language );
		if ( ! is_array( $facts ) || empty( $facts['reviewer_id'] ) || ! empty( $facts['machine_prefilled'] ) || ! empty( $facts['critical_change'] ) ) {
			return false;
		}

		$source = $coordinator->get_relationships()->find_member( $member['group_uuid'], $coordinator->get_language_context()->get_main_language() );
		if ( ! is_array( $source ) ) {
			return false;
		}

		$payload = DSF_Multilingual_Adapters::fingerprint_payload( $source );
		if ( $payload instanceof WP_Error ) {
			return false;
		}
		$current = DSF_Translation_Workflow::build_fingerprint( $payload, 1 );
		if ( $current instanceof WP_Error ) {
			return false;
		}

		return ! empty( $facts['reviewed_source_fingerprint'] )
			&& hash_equals( (string) $facts['reviewed_source_fingerprint'], (string) $current['fingerprint'] );
	}

	/**
	 * Report overlay identities to the relationship authority.
	 *
	 * @param bool   $exists    Current result.
	 * @param string $kind      Object kind.
	 * @param string $subtype   Object subtype.
	 * @param int    $object_id Object ID.
	 * @return bool
	 */
	public function object_exists( $exists, $kind, $subtype, $object_id ) {
		if ( $exists || self::KIND !== sanitize_key( $kind ) ) {
			return $exists;
		}
		$subtype = sanitize_key( $subtype );
		if ( ! in_array( $subtype, self::subtypes(), true ) ) {
			return false;
		}

		$identity = self::decode( $object_id );
		if ( ! $identity['canonical_id'] ) {
			return false;
		}

		if ( self::is_term_subtype( $subtype ) ) {
			$term = get_term( $identity['canonical_id'], $subtype );
			return is_object( $term ) && ! is_wp_error( $term );
		}

		$post = get_post( $identity['canonical_id'] );
		return is_object( $post ) && isset( $post->post_type ) && sanitize_key( $post->post_type ) === $subtype;
	}

	/**
	 * Fingerprint an overlay against its canonical visitor-facing source.
	 *
	 * @param mixed $payload Current payload.
	 * @param array $member  Relationship member.
	 * @return array
	 */
	public function fingerprint_payload( $payload, $member ) {
		if ( ! is_array( $member ) || self::KIND !== sanitize_key( $member['object_kind'] ?? '' ) ) {
			return $payload;
		}

		$subtype  = sanitize_key( $member['object_subtype'] ?? '' );
		$identity = self::decode( $member['object_id'] ?? 0 );
		if ( ! $identity['canonical_id'] ) {
			return $payload;
		}

		if ( self::is_term_subtype( $subtype ) ) {
			$term = get_term( $identity['canonical_id'], $subtype );
			if ( ! is_object( $term ) || is_wp_error( $term ) ) {
				return $payload;
			}
			return array(
				'object_kind'    => self::KIND,
				'object_subtype' => $subtype,
				'title'          => sanitize_text_field( (string) $term->name ),
				'content'        => wp_kses_post( (string) $term->description ),
			);
		}

		$post = get_post( $identity['canonical_id'] );
		if ( ! is_object( $post ) ) {
			return $payload;
		}

		// Only visitor-facing text is hashed. A price or stock change must not
		// mark a translated description stale.
		return array(
			'object_kind'    => self::KIND,
			'object_subtype' => $subtype,
			'title'          => sanitize_text_field( (string) $post->post_title ),
			'excerpt'        => wp_kses_post( (string) $post->post_excerpt ),
			'content'        => wp_kses_post( (string) $post->post_content ),
		);
	}

	/**
	 * Swap a product's display name.
	 *
	 * @param string $name    Canonical name.
	 * @param mixed  $product Product object.
	 * @return string
	 */
	public function filter_product_name( $name, $product = null ) {
		$fields = $this->resolve_display( 'product', $this->product_id( $product ) );
		return isset( $fields['title'] ) ? $fields['title'] : $name;
	}

	/**
	 * Swap a product's short description.
	 *
	 * @param string $excerpt Canonical short description.
	 * @param mixed  $product Product object.
	 * @return string
	 */
	public function filter_product_excerpt( $excerpt, $product = null ) {
		$fields = $this->resolve_display( 'product', $this->product_id( $product ) );
		return isset( $fields['excerpt'] ) ? $fields['excerpt'] : $excerpt;
	}

	/**
	 * Swap a product's long description.
	 *
	 * @param string $content Canonical description.
	 * @param mixed  $product Product object.
	 * @return string
	 */
	public function filter_product_content( $content, $product = null ) {
		$fields = $this->resolve_display( 'product', $this->product_id( $product ) );
		return isset( $fields['content'] ) ? $fields['content'] : $content;
	}

	/**
	 * Swap a product post title outside WooCommerce's own accessors.
	 *
	 * @param string $title   Canonical title.
	 * @param mixed  $post_id Post ID.
	 * @return string
	 */
	public function filter_post_title( $title, $post_id = 0 ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return $title;
		}
		$fields = $this->resolve_display( 'product', $post_id );
		return isset( $fields['title'] ) ? $fields['title'] : $title;
	}

	/**
	 * Swap a product excerpt outside WooCommerce's own accessors.
	 *
	 * @param string $excerpt Canonical excerpt.
	 * @param mixed  $post    Post object.
	 * @return string
	 */
	public function filter_post_excerpt( $excerpt, $post = null ) {
		$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : 0;
		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return $excerpt;
		}
		$fields = $this->resolve_display( 'product', $post_id );
		return isset( $fields['excerpt'] ) ? $fields['excerpt'] : $excerpt;
	}

	/**
	 * Swap a catalog term's display name and description.
	 *
	 * The slug is deliberately untouched: it participates in variation matching
	 * and filter URLs.
	 *
	 * @param mixed  $term     Term object.
	 * @param string $taxonomy Taxonomy name.
	 * @return mixed
	 */
	public function filter_term( $term, $taxonomy = '' ) {
		if ( ! is_object( $term ) || ! isset( $term->term_id ) || ! self::is_term_subtype( $taxonomy ) ) {
			return $term;
		}

		$fields = $this->resolve_display( sanitize_key( $taxonomy ), absint( $term->term_id ) );
		if ( isset( $fields['title'] ) ) {
			$term->name = $fields['title'];
		}
		if ( isset( $fields['content'] ) ) {
			$term->description = $fields['content'];
		}
		return $term;
	}

	/**
	 * Resolve a product ID from a WooCommerce product object.
	 *
	 * @param mixed $product Product object.
	 * @return int
	 */
	private function product_id( $product ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return absint( $product->get_id() );
		}
		return 0;
	}
}
