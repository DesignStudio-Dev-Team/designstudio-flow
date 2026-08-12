<?php
/**
 * Object adapters shared by the multilingual foundation services.
 *
 * Prompt 2 deliberately does not translate or route content. These adapters
 * define the bounded object inventory, conservative source-fingerprint inputs,
 * and the explicit dependency fields that already exist in Flow data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Multilingual_Adapters {

	const NOTIFICATION_TRANSLATIONS_OPTION = 'dsf_notification_bar_translations';
	const MAX_DEPENDENCIES                 = 200;
	const MAX_DEPENDENCY_BLOCKS            = 5000;

	/**
	 * Stable, append-only synthetic slots for the curated locale registry.
	 *
	 * These IDs are persisted in relationship/dependency rows. Never renumber or
	 * reuse an entry; adding a locale requires assigning the next unused slot.
	 *
	 * @return array<string,int>
	 */
	private static function notification_slots() {
		return array(
			'en'    => 1001,
			'en-US' => 1002,
			'en-GB' => 1003,
			'en-CA' => 1004,
			'en-AU' => 1005,
			'es'    => 1006,
			'es-ES' => 1007,
			'es-MX' => 1008,
			'es-US' => 1009,
			'es-AR' => 1010,
			'fr'    => 1011,
			'fr-FR' => 1012,
			'fr-CA' => 1013,
			'de'    => 1014,
			'de-DE' => 1015,
			'de-AT' => 1016,
			'de-CH' => 1017,
			'it'    => 1018,
			'it-IT' => 1019,
			'pt'    => 1020,
			'pt-PT' => 1021,
			'pt-BR' => 1022,
			'nl'    => 1023,
			'nl-NL' => 1024,
			'nl-BE' => 1025,
			'ca'    => 1026,
			'eu'    => 1027,
			'gl'    => 1028,
			'da'    => 1029,
			'nb-NO' => 1030,
			'sv'    => 1031,
			'fi'    => 1032,
			'is'    => 1033,
			'pl'    => 1034,
			'cs'    => 1035,
			'sk'    => 1036,
			'hu'    => 1037,
			'ro'    => 1038,
			'bg'    => 1039,
			'hr'    => 1040,
			'sl'    => 1041,
			'sr'    => 1042,
			'el'    => 1043,
			'ru'    => 1044,
			'uk'    => 1045,
			'tr'    => 1046,
			'ar'    => 1047,
			'ar-SA' => 1048,
			'ar-AE' => 1049,
			'he'    => 1050,
			'fa'    => 1051,
			'ur'    => 1052,
			'hi'    => 1053,
			'bn'    => 1054,
			'zh-CN' => 1055,
			'zh-TW' => 1056,
			'ja'    => 1057,
			'ko'    => 1058,
			'vi'    => 1059,
			'th'    => 1060,
			'id'    => 1061,
			'ms'    => 1062,
		);
	}

	/** Return the stable local identity reserved for one notification language. */
	public static function synthetic_notification_id( $language ) {
		$language = DSF_Multilingual_Settings::normalize_locale_code( $language );
		$slots    = self::notification_slots();
		return isset( $slots[ $language ] ) ? $slots[ $language ] : 0;
	}

	/** Resolve a reserved notification identity back to its curated language. */
	public static function synthetic_notification_language( $object_id ) {
		$language = array_search( absint( $object_id ), self::notification_slots(), true );
		return false === $language ? '' : $language;
	}

	/**
	 * Post types included in the approved release scope.
	 *
	 * Form entries, attachments, orders, coupons and other operational records
	 * are intentionally excluded.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array(
			'page',
			'post',
			'product',
			'product_variation',
			'dsf_layout',
			'dsf_form',
			'dsf_popup',
			'dsf_saved_block',
			'dsf_template',
			'dsf_product_template',
			'dsf_shop_template',
			'dsf_blog_template',
		);

		/**
		 * Filter supported multilingual post types.
		 *
		 * Extensions must add only visitor-facing content objects. Operational
		 * records such as entries, orders and inventory must never be added.
		 *
		 * @param string[] $types Post type names.
		 */
		$types = apply_filters( 'dsf_multilingual_post_types', $types );
		$types = is_array( $types ) ? array_slice( $types, 0, 64 ) : array();

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $types ),
					static function ( $type ) {
						return '' !== $type && ! in_array( $type, array( 'attachment', 'dsf_entry', 'shop_order', 'shop_order_refund', 'shop_coupon' ), true );
					}
				)
			)
		);
	}

	/**
	 * Post-backed relationship members available before the Woo overlay phase.
	 *
	 * Products and variations are fingerprint sources, but their translated
	 * members use the approved synthetic overlay identity. Registering their
	 * canonical posts as ordinary translated posts would make a later overlay
	 * impossible without duplicating commerce identity.
	 *
	 * @return string[]
	 */
	public static function relationship_post_types() {
		return array_values( array_diff( self::post_types(), array( 'product', 'product_variation' ) ) );
	}

	/**
	 * Taxonomies included in the approved release scope.
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		$taxonomies = array( 'category', 'post_tag', 'product_cat', 'product_tag' );

		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$taxonomies = array_merge( $taxonomies, (array) wc_get_attribute_taxonomy_names() );
		} elseif ( function_exists( 'get_object_taxonomies' ) ) {
			foreach ( (array) get_object_taxonomies( 'product', 'names' ) as $taxonomy ) {
				if ( 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
					$taxonomies[] = $taxonomy;
				}
			}
		}

		/**
		 * Filter supported multilingual taxonomies.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 */
		$taxonomies = apply_filters( 'dsf_multilingual_taxonomies', $taxonomies );
		$taxonomies = is_array( $taxonomies ) ? array_slice( $taxonomies, 0, 128 ) : array();

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $taxonomies ) ) ) );
	}

	/**
	 * Native term relationship members available before Woo term overlays.
	 *
	 * @return string[]
	 */
	public static function relationship_taxonomies() {
		return array_values(
			array_filter(
				self::taxonomies(),
				static function ( $taxonomy ) {
					return ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) && 0 !== strpos( $taxonomy, 'pa_' );
				}
			)
		);
	}

	/**
	 * Whether an object identity is valid and supported.
	 *
	 * @param string $kind Object kind.
	 * @param string $subtype Post type, taxonomy or synthetic subtype.
	 * @param int    $object_id Local object ID.
	 * @param string $language Optional exact language for synthetic slots.
	 * @return bool
	 */
	public static function object_exists( $kind, $subtype, $object_id, $language = '' ) {
		$kind      = sanitize_key( $kind );
		$subtype   = sanitize_key( $subtype );
		$object_id = absint( $object_id );

		if ( 'post' === $kind && in_array( $subtype, self::post_types(), true ) ) {
			$post = $object_id ? get_post( $object_id ) : null;
			return is_object( $post ) && isset( $post->post_type ) && sanitize_key( $post->post_type ) === $subtype;
		}

		if ( 'term' === $kind && in_array( $subtype, self::taxonomies(), true ) ) {
			$term = $object_id ? get_term( $object_id, $subtype ) : null;
			return is_object( $term ) && ! is_wp_error( $term );
		}

		if ( 'synthetic' === $kind && 'notification_bar' === $subtype ) {
			$slot_language = self::synthetic_notification_language( $object_id );
			$language      = '' === $language ? '' : DSF_Multilingual_Settings::normalize_locale_code( $language );
			if ( '' === $slot_language || ( '' !== $language && $slot_language !== $language ) ) {
				return false;
			}
			$settings = DSF_Multilingual_Settings::get_settings();
			if ( $slot_language === $settings['main_language'] ) {
				return true;
			}
			$translations = get_option( self::NOTIFICATION_TRANSLATIONS_OPTION, array() );
			return is_array( $translations ) && isset( $translations[ $slot_language ] ) && is_array( $translations[ $slot_language ] );
		}

		/**
		 * Filter support for a custom object adapter.
		 *
		 * @param bool   $exists Object support/existence result.
		 * @param string $kind Object kind.
		 * @param string $subtype Object subtype.
		 * @param int    $object_id Object ID.
		 */
		return (bool) apply_filters( 'dsf_multilingual_object_exists', false, $kind, $subtype, $object_id );
	}

	/**
	 * Return a conservative, non-secret source payload for fingerprinting.
	 *
	 * Prompt 4 will replace whole sanitized block payloads with explicit
	 * translatable paths. Until then, hashing the full saved block structure can
	 * over-report staleness but cannot miss a visitor-facing block change.
	 *
	 * @param array $member Relationship row/object identity.
	 * @return array|WP_Error
	 */
	public static function fingerprint_payload( $member ) {
		$member    = is_array( $member ) ? $member : array();
		$kind      = sanitize_key( $member['object_kind'] ?? '' );
		$subtype   = sanitize_key( $member['object_subtype'] ?? '' );
		$object_id = absint( $member['object_id'] ?? 0 );

		if ( ! self::object_exists( $kind, $subtype, $object_id ) ) {
			return new WP_Error( 'dsf_multilingual_object_missing', __( 'The translation object no longer exists.', 'designstudio-flow' ) );
		}

		if ( 'post' === $kind ) {
			$post = get_post( $object_id );
			$meta = self::fingerprint_meta( $object_id, $subtype );
			if ( $meta instanceof WP_Error ) {
				return $meta;
			}
			$payload = array(
				'object_kind'    => 'post',
				'object_subtype' => $subtype,
				'post_title'     => sanitize_text_field( (string) $post->post_title ),
				'post_name'      => sanitize_title( (string) $post->post_name ),
				'post_excerpt'   => self::sanitize_rich_text( (string) $post->post_excerpt ),
				'post_content'   => self::sanitize_rich_text( (string) $post->post_content ),
				'meta'           => $meta,
			);
		} elseif ( 'term' === $kind ) {
			$term    = get_term( $object_id, $subtype );
			$payload = array(
				'object_kind'    => 'term',
				'object_subtype' => $subtype,
				'name'           => sanitize_text_field( (string) $term->name ),
				'slug'           => sanitize_title( (string) $term->slug ),
				'description'    => self::sanitize_rich_text( (string) $term->description ),
			);
		} else {
			$language = self::synthetic_notification_language( $object_id );
			$config   = DSF_Multilingual_Settings::get_settings();
			if ( $language === $config['main_language'] ) {
				$settings = get_option( 'dsf_notification_bar', array() );
			} else {
				$translations = get_option( self::NOTIFICATION_TRANSLATIONS_OPTION, array() );
				$settings     = is_array( $translations ) && isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array();
			}
			if ( class_exists( 'DSF_Notification_Bar' ) ) {
				$settings = DSF_Notification_Bar::sanitize_settings( $settings );
			}
			$payload = array(
				'object_kind'    => 'synthetic',
				'object_subtype' => 'notification_bar',
				'message'        => self::sanitize_rich_text( (string) ( $settings['message'] ?? '' ) ),
				'link_text'      => sanitize_text_field( (string) ( $settings['linkText'] ?? '' ) ),
			);
		}

		/**
		 * Filter the bounded payload used to fingerprint an object.
		 *
		 * Callers must not add secrets, submissions, customer/order data,
		 * credentials, generated snapshots or operational commerce values.
		 *
		 * @param array $payload Fingerprint payload.
		 * @param array $member Relationship row/object identity.
		 */
		return apply_filters( 'dsf_multilingual_fingerprint_payload', $payload, $member );
	}

	/**
	 * Return current explicit Flow dependency references for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $language Exact owner language for implicit global dependencies.
	 * @return array[]|WP_Error
	 */
	public static function post_dependencies( $post_id, $language = '' ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || ! in_array( sanitize_key( $post->post_type ), self::post_types(), true ) ) {
			return array();
		}

		$dependencies = array();
		$settings     = get_post_meta( $post_id, '_dsf_settings', true );
		$settings     = is_array( $settings ) ? $settings : array();
		$post_type    = sanitize_key( $post->post_type );

		// Only objects that render as a whole page are wrapped in a header and
		// footer. A layout *is* the header or footer, and a saved block, form, or
		// popup is embedded inside a page that already has its own.
		if ( self::renders_with_layout( $post_type ) ) {
			$layout      = isset( $settings['layout'] ) && is_array( $settings['layout'] ) ? $settings['layout'] : array();
			$header_id   = absint( $layout['headerTemplateId'] ?? 0 );
			$footer_id   = absint( $layout['footerTemplateId'] ?? 0 );
			$header_path = 'settings.layout.headerTemplateId';
			$footer_path = 'settings.layout.footerTemplateId';

			// A zero explicit assignment currently resolves through the site-wide raw ID.
			// Record that fallback so a secondary object cannot pass review while still
			// rendering a main-language layout during the foundation-only phase.
			if ( ! $header_id ) {
				$header_id   = absint( get_option( 'dsf_default_header_id', 0 ) );
				$header_path = 'defaults.headerTemplateId';
			}
			if ( ! $footer_id ) {
				$footer_id   = absint( get_option( 'dsf_default_footer_id', 0 ) );
				$footer_path = 'defaults.footerTemplateId';
			}

			self::add_dependency( $dependencies, 'post', 'dsf_layout', $header_id, 'layout_header', $header_path, $post_id );
			self::add_dependency( $dependencies, 'post', 'dsf_layout', $footer_id, 'layout_footer', $footer_path, $post_id );
		}

		self::add_dependency( $dependencies, 'post', 'dsf_popup', $settings['popupId'] ?? 0, 'popup', 'settings.popupId', $post_id );
		$language     = DSF_Multilingual_Settings::normalize_locale_code( $language );
		$notification = '' !== $language ? get_option( 'dsf_notification_bar', array() ) : array();
		if ( is_array( $notification ) && ! empty( $notification['enabled'] ) ) {
			self::add_dependency( $dependencies, 'synthetic', 'notification_bar', self::synthetic_notification_id( $language ), 'notification', 'globals.notification_bar' );
		}

		$parent_id = absint( $post->post_parent ?? 0 );
		if ( $parent_id ) {
			$parent      = get_post( $parent_id );
			$parent_type = is_object( $parent ) && isset( $parent->post_type ) ? sanitize_key( $parent->post_type ) : '';
			if ( in_array( $parent_type, self::relationship_post_types(), true ) ) {
				self::add_dependency( $dependencies, 'post', $parent_type, $parent_id, 'parent', 'post_parent', $post_id );
			}
		}

		$blocks = get_post_meta( $post_id, '_dsf_blocks', true );
		$blocks = is_array( $blocks ) ? $blocks : array();
		if ( count( $blocks ) > self::MAX_DEPENDENCY_BLOCKS ) {
			return new WP_Error( 'dsf_multilingual_dependency_blocks', __( 'The content has too many blocks for a complete dependency review.', 'designstudio-flow' ) );
		}
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			self::add_dependency( $dependencies, 'post', 'dsf_saved_block', $block['savedBlockId'] ?? 0, 'saved_block', 'blocks.' . absint( $index ) . '.savedBlockId', $post_id );
			$block_settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$block_type     = sanitize_key( $block['type'] ?? '' );
			$form_source    = sanitize_key( $block_settings['formSource'] ?? 'dsf' );
			if ( 'form-embed' === $block_type || ( 'form-with-content' === $block_type && 'embed' !== $form_source ) ) {
				self::add_dependency( $dependencies, 'post', 'dsf_form', $block_settings['formId'] ?? 0, 'form', 'blocks.' . absint( $index ) . '.settings.formId', $post_id );
			}
			if ( 'dsf' === sanitize_key( $block_settings['newsletterSource'] ?? '' ) ) {
				self::add_dependency( $dependencies, 'post', 'dsf_form', $block_settings['newsletterFormId'] ?? 0, 'form', 'blocks.' . absint( $index ) . '.settings.newsletterFormId', $post_id );
			}
			if ( $dependencies instanceof WP_Error ) {
				return $dependencies;
			}
		}

		/**
		 * Filter explicit dependency identities for a post.
		 *
		 * @param array[] $dependencies Dependency definitions.
		 * @param int     $post_id Post ID.
		 */
		$dependencies = apply_filters( 'dsf_multilingual_post_dependencies', $dependencies, $post_id );
		if ( $dependencies instanceof WP_Error ) {
			return $dependencies;
		}
		if ( ! is_array( $dependencies ) || count( $dependencies ) > self::MAX_DEPENDENCIES ) {
			return new WP_Error( 'dsf_multilingual_dependency_limit', __( 'The content has too many required dependencies for a complete review.', 'designstudio-flow' ) );
		}

		return $dependencies;
	}

	/**
	 * Meta keys whose values participate in the conservative fingerprint.
	 *
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	public static function fingerprint_meta_keys( $post_type ) {
		$map = array(
			'page'                 => array( '_dsf_blocks', '_dsf_settings' ),
			'dsf_layout'           => array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' ),
			'dsf_saved_block'      => array( '_dsf_block_type', '_dsf_block_settings' ),
			'dsf_template'         => array( '_dsf_template_blocks' ),
			'dsf_product_template' => array( '_dsf_blocks', '_dsf_settings', '_dsf_pt_assignment' ),
			'dsf_shop_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_st_assignment' ),
			'dsf_blog_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_bt_assignment' ),
			'dsf_form'             => array( '_dsf_form_rows', '_dsf_form_settings' ),
			'dsf_popup'            => array( '_dsf_popup_settings' ),
		);
		return $map[ sanitize_key( $post_type ) ] ?? array();
	}

	/**
	 * Visitor-facing Flow meta included in the conservative fingerprint.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return array|WP_Error
	 */
	private static function fingerprint_meta( $post_id, $post_type ) {
		$meta = array();
		foreach ( self::fingerprint_meta_keys( $post_type ) as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '_dsf_form_settings' === $key ) {
				$value = self::visitor_form_settings( $value );
			}
			$value = self::normalize_fingerprint_value( $value );
			if ( $value instanceof WP_Error ) {
				return $value;
			}
			$meta[ $key ] = $value;
		}

		return $meta;
	}

	/**
	 * Strip form recipients, webhooks, secrets and redirect targets.
	 *
	 * @param mixed $settings Form settings.
	 * @return array
	 */
	private static function visitor_form_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$allowed  = array(
			'submitLabel',
			'nextLabel',
			'previousLabel',
			'successMessage',
			'notificationSubject',
			'notificationIntro',
			'confirmationType',
			'confirmationMessage',
		);
		$clean    = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$clean[ $key ] = $settings[ $key ];
			}
		}
		return $clean;
	}

	/**
	 * Normalize stored values without treating them as trusted output.
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Current depth.
	 * @return mixed
	 */
	private static function normalize_fingerprint_value( $value, $depth = 0 ) {
		if ( $depth > 20 ) {
			return new WP_Error( 'dsf_multilingual_fingerprint_depth', __( 'Visitor-facing source data is nested too deeply to fingerprint safely.', 'designstudio-flow' ) );
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > 5000 ) {
				return new WP_Error( 'dsf_multilingual_fingerprint_nodes', __( 'Visitor-facing source data is too large to fingerprint safely.', 'designstudio-flow' ) );
			}
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key = is_int( $key ) ? $key : sanitize_key( $key );
				if ( array_key_exists( $clean_key, $clean ) ) {
					return new WP_Error( 'dsf_multilingual_fingerprint_key', __( 'Visitor-facing source data contains ambiguous keys.', 'designstudio-flow' ) );
				}
				$item = self::normalize_fingerprint_value( $item, $depth + 1 );
				if ( $item instanceof WP_Error ) {
					return $item;
				}
				$clean[ $clean_key ] = $item;
			}
			return $clean;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		if ( is_scalar( $value ) ) {
			$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
			return function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : $value;
		}
		return new WP_Error( 'dsf_multilingual_fingerprint_type', __( 'Visitor-facing source data contains an unsupported value.', 'designstudio-flow' ) );
	}

	/**
	 * Add one valid explicit dependency.
	 *
	 * @param array|WP_Error $dependencies    Collected dependencies, by reference.
	 * @param string         $kind            Object kind.
	 * @param string         $subtype         Object subtype.
	 * @param int            $object_id       Dependency object ID.
	 * @param string         $dependency_kind Dependency role.
	 * @param string         $path            Source path for review messages.
	 * @param int            $owner_id        Owning object, so it cannot depend on itself.
	 */
	private static function add_dependency( &$dependencies, $kind, $subtype, $object_id, $dependency_kind, $path, $owner_id = 0 ) {
		if ( $dependencies instanceof WP_Error ) {
			return;
		}
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return;
		}
		// An object is never its own dependency. Site-wide defaults make this
		// reachable: the layout chosen as the default header resolves that
		// default for itself.
		if ( 'post' === sanitize_key( $kind ) && absint( $owner_id ) === $object_id ) {
			return;
		}
		if ( count( $dependencies ) >= self::MAX_DEPENDENCIES ) {
			$dependencies = new WP_Error( 'dsf_multilingual_dependency_limit', __( 'The content has too many required dependencies for a complete review.', 'designstudio-flow' ) );
			return;
		}
		$dependencies[] = array(
			'object_kind'     => sanitize_key( $kind ),
			'object_subtype'  => sanitize_key( $subtype ),
			'object_id'       => $object_id,
			'dependency_kind' => sanitize_key( $dependency_kind ),
			'path'            => sanitize_text_field( $path ),
			'required'        => true,
		);
	}

	/**
	 * Whether a post type renders inside a Flow header and footer.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function renders_with_layout( $post_type ) {
		$types = array( 'page', 'post', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' );

		/**
		 * Filter which post types depend on a header and footer.
		 *
		 * @param string[] $types     Post types.
		 * @param string   $post_type Post type being evaluated.
		 */
		$types = apply_filters( 'dsf_multilingual_layout_dependent_types', $types, $post_type );
		return is_array( $types ) && in_array( sanitize_key( $post_type ), $types, true );
	}

	/** Sanitize rich source text before it enters a fingerprint payload. */
	private static function sanitize_rich_text( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : $value;
	}
}
