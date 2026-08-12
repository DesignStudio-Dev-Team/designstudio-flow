<?php
/**
 * The explicit translatable-path contract.
 *
 * Nothing in this plugin decides that a value is translatable because it
 * happens to be a string. Translatability is declared: it comes from the
 * setting's registered `type`, from a bounded per-composite field map, and from
 * explicit block-level overrides. Anything not declared is left alone, which
 * keeps URLs, IDs, enums, media, CSS, shortcodes, prices, and field keys out of
 * every translation payload by construction.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Contract {

	const FORMAT_TEXT      = 'text';
	const FORMAT_MULTILINE = 'multiline';
	const FORMAT_HTML      = 'html';

	const MAX_DEPTH       = 8;
	const MAX_LIST_ITEMS  = 200;
	const MAX_FIELD_COUNT = 64;

	/**
	 * Registered setting types that hold visitor-facing copy.
	 *
	 * Every other registered type — color, slider, toggle, select, number,
	 * image, video, url, datetime, icon, source, category, categories,
	 * products, product_tags, multiselect_tags — is non-translatable and is
	 * deliberately absent.
	 *
	 * @return array<string,string>
	 */
	public static function scalar_formats() {
		return array(
			'text'     => self::FORMAT_TEXT,
			'textarea' => self::FORMAT_MULTILINE,
			'wysiwyg'  => self::FORMAT_HTML,
			'richtext' => self::FORMAT_HTML,
		);
	}

	/**
	 * Setting keys that are never translated regardless of their type.
	 *
	 * These hold operational values that a translator must not change: routing
	 * targets, currency and locale identity, coupon codes, phone numbers, form
	 * field keys, and numeric amounts.
	 *
	 * @return string[]
	 */
	public static function excluded_setting_keys() {
		return array(
			'currencyText',
			'discountAmount',
			'localeText',
			'mobilePhoneNumber',
			'promoCode',
		);
	}

	/**
	 * Key suffixes that mark a value as structural rather than visitor copy.
	 *
	 * Matching is done on the registered setting key, not on its value, so the
	 * rule stays a schema statement instead of a guess about content.
	 *
	 * @return string[]
	 */
	public static function excluded_key_suffixes() {
		return array(
			'Action',
			'Code',
			'EmbedCode',
			'Html',
			'Id',
			'Ids',
			'Key',
			'Keys',
			'Shortcode',
			'Url',
			'Urls',
			'Video',
		);
	}

	/**
	 * Exact lowercase keys treated as structural.
	 *
	 * @return string[]
	 */
	public static function excluded_exact_keys() {
		return array( 'url', 'href', 'video', 'image', 'icon', 'anchor', 'anchorid', 'slug', 'id', 'key', 'code', 'source', 'kind', 'layout', 'mode', 'type' );
	}

	/**
	 * Bounded field maps for every composite/repeater setting type.
	 *
	 * Each entry lists exactly which sub-fields carry visitor-facing copy. A
	 * field that is absent is preserved untouched, so images, URLs, icons,
	 * prices, product IDs, and enum values can never be sent to a translator.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function composite_descriptors() {
		$link_list = self::items(
			array(
				'label' => self::text(),
			)
		);

		return array(
			'anchor_gallery_items'       => self::items(
				array(
					'title' => self::text(),
				)
			),
			'brand_repeater'             => self::items(
				array(
					'name' => self::text(),
				)
			),
			'brand_showcase_cards'       => self::items(
				array(
					'title'    => self::text(),
					'subtitle' => self::text(),
				)
			),
			'card_column_items'          => self::items(
				array(
					'title'       => self::text(),
					'description' => self::text( self::FORMAT_MULTILINE ),
					'buttonText'  => self::text(),
				)
			),
			'dock_nav_links'             => self::items(
				array(
					'label' => self::text(),
				)
			),
			'expander_cards'             => self::items(
				array(
					'title' => self::text(),
				)
			),
			'faq_items'                  => self::items(
				array(
					'question' => self::text(),
					'answer'   => self::html(),
				)
			),
			'footer_dealers'             => self::items(
				array(
					'name'            => self::text(),
					'addressLine1'    => self::text(),
					'addressLine2'    => self::text(),
					'directionsLabel' => self::text(),
					'hoursLabel'      => self::text(),
					'day1'            => self::text(),
					'day2'            => self::text(),
				)
			),
			'gallery_items'              => self::items(
				array(
					'category'    => self::text(),
					'title'       => self::text(),
					'description' => self::text( self::FORMAT_MULTILINE ),
				)
			),
			'icon_items'                 => self::items(
				array(
					'title'       => self::text(),
					'description' => self::text( self::FORMAT_MULTILINE ),
					'note'        => self::text(),
				)
			),
			// Logo grids hold only images and links.
			'image_logo_grid_items'      => self::items( array() ),
			'mega_menu'                  => self::items(
				array(
					'label'   => self::text(),
					'columns' => self::items(
						array(
							'heading' => self::text(),
							'links'   => $link_list,
						)
					),
					'banner'  => self::map(
						array(
							'title' => self::text(),
						)
					),
				)
			),
			'mega_menu_pro'              => self::items(
				array(
					'label'   => self::text(),
					'columns' => self::items(
						array(
							'heading' => self::text(),
							'links'   => $link_list,
						)
					),
					'banner'  => self::map(
						array(
							'title'    => self::text(),
							'subtitle' => self::text(),
						)
					),
				)
			),
			'mobile_stores'              => self::items(
				array(
					'title'       => self::text(),
					'address'     => self::text( self::FORMAT_MULTILINE ),
					'mapsLabel'   => self::text(),
					'buttonLabel' => self::text(),
				)
			),
			'pricing_plans'              => self::items(
				array(
					'name'        => self::text(),
					'description' => self::text( self::FORMAT_MULTILINE ),
					// Amounts and the currency prefix stay canonical; only the
					// human-readable billing suffix is translated.
					'priceSuffix' => self::text(),
					'buttonText'  => self::text(),
					'badgeText'   => self::text(),
					'features'    => self::text( self::FORMAT_MULTILINE ),
				)
			),
			'product_tabs'               => self::items(
				array(
					'label'   => self::text(),
					'content' => self::text( self::FORMAT_MULTILINE ),
				)
			),
			'repeater'                   => self::items(
				array(
					'title'              => self::text(),
					'description'        => self::text( self::FORMAT_MULTILINE ),
					'buttonText'         => self::text(),
					'buttonModalContent' => self::html(),
					'text'               => self::text(),
					'quote'              => self::text( self::FORMAT_MULTILINE ),
					'location'           => self::text(),
				)
			),
			'showcase_header_navigation' => self::map(
				array(
					'utility' => self::items(
						array(
							'label' => self::text(),
							'links' => $link_list,
							'panel' => self::map(
								array(
									'introTitle'    => self::text(),
									'introText'     => self::text( self::FORMAT_MULTILINE ),
									'buttonText'    => self::text(),
									'accentText'    => self::text(),
									'promoTitle'    => self::text(),
									'promoSubtitle' => self::text(),
									'cards'         => self::items(
										array(
											'eyebrow' => self::text(),
											'title'   => self::text(),
										)
									),
								)
							),
						)
					),
				)
			),
			'simple_links'               => $link_list,
			'tabbed_showcase_tabs'       => self::items(
				array(
					'label'          => self::text(),
					'supportingText' => self::text(),
				)
			),
		);
	}

	/**
	 * Per-block corrections to the type-derived contract.
	 *
	 * `false` removes a setting from translation entirely.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function block_overrides() {
		return array(
			// The form embed block stores third-party markup, not copy.
			'form-embed'       => array(
				'embedCode' => false,
			),
			'header-mega-menu' => array(
				'languageUrl' => false,
			),
		);
	}

	/**
	 * Resolve the translatable descriptor map for one registered block.
	 *
	 * @param string $block_id Block identifier.
	 * @param array  $block    Registered block definition.
	 * @return array<string,array<string,mixed>> Setting key to descriptor.
	 */
	public static function describe_block( $block_id, $block ) {
		$block_id  = is_string( $block_id ) ? $block_id : '';
		$settings  = is_array( $block ) && isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$overrides = self::block_overrides();
		$override  = isset( $overrides[ $block_id ] ) ? $overrides[ $block_id ] : array();
		$described = array();

		foreach ( $settings as $key => $definition ) {
			if ( ! is_string( $key ) || ! is_array( $definition ) ) {
				continue;
			}

			if ( array_key_exists( $key, $override ) ) {
				$descriptor = $override[ $key ];
			} elseif ( array_key_exists( 'translatable', $definition ) ) {
				// A block author may state the contract directly at registration.
				$descriptor = $definition['translatable'];
			} else {
				$descriptor = self::descriptor_for_setting( $key, $definition );
			}

			$descriptor = self::normalize_descriptor( $descriptor, 0 );
			if ( null !== $descriptor ) {
				$described[ $key ] = $descriptor;
			}
			if ( count( $described ) >= self::MAX_FIELD_COUNT ) {
				break;
			}
		}

		/**
		 * Filters the translatable settings of one block.
		 *
		 * Add-on blocks use this (or the `translatable` key in their
		 * registration) to declare their own visitor-facing fields. Descriptors
		 * are normalized and bounded again after filtering, and any field that
		 * is not declared is never translated.
		 *
		 * @param array  $described Setting key to descriptor.
		 * @param string $block_id  Block identifier.
		 * @param array  $block     Registered block definition.
		 */
		$described = apply_filters( 'dsf_block_translatable_settings', $described, $block_id, $block );

		$clean = array();
		foreach ( is_array( $described ) ? $described : array() as $key => $descriptor ) {
			if ( ! is_string( $key ) || ! isset( $settings[ $key ] ) ) {
				continue;
			}
			$descriptor = self::normalize_descriptor( $descriptor, 0 );
			if ( null !== $descriptor ) {
				$clean[ $key ] = $descriptor;
			}
			if ( count( $clean ) >= self::MAX_FIELD_COUNT ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Derive the descriptor for one registered setting.
	 *
	 * @param string $key        Setting key.
	 * @param array  $definition Registered setting definition.
	 * @return array|null
	 */
	private static function descriptor_for_setting( $key, $definition ) {
		if ( self::key_is_structural( $key ) ) {
			return null;
		}

		$type = isset( $definition['type'] ) && is_string( $definition['type'] ) ? $definition['type'] : '';

		$scalars = self::scalar_formats();
		if ( isset( $scalars[ $type ] ) ) {
			return self::text( $scalars[ $type ] );
		}

		$composites = self::composite_descriptors();
		if ( isset( $composites[ $type ] ) ) {
			return $composites[ $type ];
		}

		return null;
	}

	/**
	 * Whether a setting or field key names structural rather than visitor data.
	 *
	 * @param string $key Key name.
	 * @return bool
	 */
	public static function key_is_structural( $key ) {
		$key = is_string( $key ) ? $key : '';
		if ( '' === $key ) {
			return true;
		}
		if ( in_array( $key, self::excluded_setting_keys(), true ) ) {
			return true;
		}
		if ( in_array( strtolower( $key ), self::excluded_exact_keys(), true ) ) {
			return true;
		}
		foreach ( self::excluded_key_suffixes() as $suffix ) {
			$length = strlen( $suffix );
			if ( strlen( $key ) > $length && substr( $key, -$length ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build a plain value descriptor.
	 *
	 * @param string $format Value format.
	 * @return array<string,string>
	 */
	public static function text( $format = self::FORMAT_TEXT ) {
		return array(
			'kind'   => 'value',
			'format' => self::FORMAT_HTML === $format || self::FORMAT_MULTILINE === $format ? $format : self::FORMAT_TEXT,
		);
	}

	/**
	 * Build a rich-text value descriptor.
	 *
	 * @return array<string,string>
	 */
	public static function html() {
		return self::text( self::FORMAT_HTML );
	}

	/**
	 * Build a descriptor for a numerically indexed list of items.
	 *
	 * @param array $fields Field descriptors.
	 * @return array<string,mixed>
	 */
	public static function items( $fields ) {
		return array(
			'kind'   => 'list',
			'fields' => is_array( $fields ) ? $fields : array(),
		);
	}

	/**
	 * Build a descriptor for one associative structure.
	 *
	 * @param array $fields Field descriptors.
	 * @return array<string,mixed>
	 */
	public static function map( $fields ) {
		return array(
			'kind'   => 'map',
			'fields' => is_array( $fields ) ? $fields : array(),
		);
	}

	/**
	 * Rebuild a descriptor from known keys, discarding anything unrecognized.
	 *
	 * @param mixed $descriptor Raw descriptor.
	 * @param int   $depth      Current nesting depth.
	 * @return array|null
	 */
	public static function normalize_descriptor( $descriptor, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH || ! is_array( $descriptor ) ) {
			return null;
		}

		$kind = isset( $descriptor['kind'] ) && is_string( $descriptor['kind'] ) ? $descriptor['kind'] : '';

		if ( 'value' === $kind ) {
			return self::text( isset( $descriptor['format'] ) ? $descriptor['format'] : self::FORMAT_TEXT );
		}

		if ( 'list' !== $kind && 'map' !== $kind ) {
			return null;
		}

		$fields = isset( $descriptor['fields'] ) && is_array( $descriptor['fields'] ) ? $descriptor['fields'] : array();
		$clean  = array();
		foreach ( $fields as $field => $child ) {
			if ( ! is_string( $field ) || self::key_is_structural( $field ) ) {
				continue;
			}
			$child = self::normalize_descriptor( $child, $depth + 1 );
			if ( null !== $child ) {
				$clean[ $field ] = $child;
			}
			if ( count( $clean ) >= self::MAX_FIELD_COUNT ) {
				break;
			}
		}

		return 'list' === $kind ? self::items( $clean ) : self::map( $clean );
	}
}
