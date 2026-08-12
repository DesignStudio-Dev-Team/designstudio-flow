<?php
/**
 * Bounded extraction of translatable segments.
 *
 * The extractor walks a source document with an explicit descriptor and emits a
 * flat, bounded list of segments. It never decides on its own that a value is
 * translatable: the descriptor comes from the registered schema through
 * DSF_Translation_Contract, and rich text is reduced to its visible nodes.
 *
 * Nothing here calls a remote service. The output is the exact payload a
 * provider adapter is later allowed to see.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Extractor {

	const MAX_SEGMENTS      = 2000;
	const MAX_SEGMENT_CHARS = 5000;
	const MAX_TOTAL_CHARS   = 300000;
	const MAX_LIST_ITEMS    = 200;
	const MAX_BLOCKS        = 500;

	/** @var self|null */
	private static $instance = null;

	/** @var callable Resolves the declared translatable settings of a block type. */
	private $block_resolver;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services             = is_array( $services ) ? $services : array();
		$this->block_resolver = isset( $services['block_resolver'] ) && is_callable( $services['block_resolver'] )
			? $services['block_resolver']
			: array( 'DSF_Blocks', 'get_translatable_settings' );
	}

	/**
	 * Return the declared translatable settings of one block type.
	 *
	 * @param string $type Block type.
	 * @return array
	 */
	public function get_block_fields( $type ) {
		$fields = call_user_func( $this->block_resolver, $type );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Return the shared extractor.
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
	 * Extract every translatable segment of one relationship member.
	 *
	 * @param array $member Relationship member.
	 * @return array{segments:array,truncated:bool}|WP_Error
	 */
	public function extract_member( $member ) {
		$document = $this->build_document( $member );
		if ( $document instanceof WP_Error ) {
			return $document;
		}
		return $this->extract_document( $document['value'], $document['descriptor'] );
	}

	/**
	 * Build the bounded source document and descriptor for one member.
	 *
	 * Operational data — IDs, slugs, recipients, prices, stock, SKUs, order and
	 * submission records, credentials, and generated snapshots — is never part
	 * of the document, so it cannot leak into a translation payload.
	 *
	 * @param array $member Relationship member.
	 * @return array{value:array,descriptor:array}|WP_Error
	 */
	public function build_document( $member ) {
		$member    = is_array( $member ) ? $member : array();
		$kind      = sanitize_key( $member['object_kind'] ?? '' );
		$subtype   = sanitize_key( $member['object_subtype'] ?? '' );
		$object_id = absint( $member['object_id'] ?? 0 );

		if ( 'post' === $kind ) {
			return $this->build_post_document( $subtype, $object_id );
		}
		if ( 'term' === $kind ) {
			return $this->build_term_document( $subtype, $object_id );
		}
		if ( 'synthetic' === $kind && 'notification_bar' === $subtype ) {
			return $this->build_notification_document( $object_id );
		}
		if ( class_exists( 'DSF_Translation_Overlays' ) && DSF_Translation_Overlays::KIND === $kind ) {
			return $this->build_overlay_document( $subtype, $object_id );
		}

		return new WP_Error( 'dsf_translation_unsupported_object', __( 'This object type cannot be translated yet.', 'designstudio-flow' ) );
	}

	/**
	 * Build the document for a post-backed object.
	 *
	 * @param string $post_type Post type.
	 * @param int    $post_id   Post ID.
	 * @return array|WP_Error
	 */
	private function build_post_document( $post_type, $post_id ) {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || sanitize_key( $post->post_type ) !== $post_type ) {
			return new WP_Error( 'dsf_translation_object_missing', __( 'The translation source object no longer exists.', 'designstudio-flow' ) );
		}

		$value      = array();
		$descriptor = array();

		$value['post_title']      = (string) $post->post_title;
		$descriptor['post_title'] = DSF_Translation_Contract::text();

		if ( in_array( $post_type, array( 'page', 'post', 'product' ), true ) ) {
			$value['post_excerpt']      = (string) $post->post_excerpt;
			$descriptor['post_excerpt'] = DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_HTML );
			$value['post_content']      = (string) $post->post_content;
			$descriptor['post_content'] = DSF_Translation_Contract::html();
		}

		$blocks_key = 'dsf_template' === $post_type ? '_dsf_template_blocks' : '_dsf_blocks';
		if ( in_array( $post_type, array( 'page', 'dsf_layout', 'dsf_template', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' ), true ) ) {
			$blocks               = get_post_meta( $post_id, $blocks_key, true );
			$value['blocks']      = is_array( $blocks ) ? array_slice( $blocks, 0, self::MAX_BLOCKS ) : array();
			$descriptor['blocks'] = array( 'kind' => 'blocks' );
		}

		if ( 'dsf_saved_block' === $post_type ) {
			$value['saved_block']      = array(
				'type'     => (string) get_post_meta( $post_id, '_dsf_block_type', true ),
				'settings' => (array) get_post_meta( $post_id, '_dsf_block_settings', true ),
			);
			$descriptor['saved_block'] = array( 'kind' => 'block' );
		}

		if ( in_array( $post_type, array( 'page', 'dsf_product_template', 'dsf_shop_template', 'dsf_blog_template' ), true ) ) {
			$settings = get_post_meta( $post_id, '_dsf_settings', true );
			$seo      = is_array( $settings ) && isset( $settings['seo'] ) && is_array( $settings['seo'] ) ? $settings['seo'] : array();
			// Only the authored SEO copy travels. The canonical URL, social image,
			// and noindex switch stay exactly as the editor configured them.
			$value['seo']      = array(
				'title'       => (string) ( $seo['title'] ?? '' ),
				'description' => (string) ( $seo['description'] ?? '' ),
			);
			$descriptor['seo'] = DSF_Translation_Contract::map(
				array(
					'title'       => DSF_Translation_Contract::text(),
					'description' => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
				)
			);
		}

		if ( 'dsf_popup' === $post_type ) {
			$popup               = get_post_meta( $post_id, '_dsf_popup_settings', true );
			$value['popup']      = is_array( $popup ) ? $popup : array();
			$descriptor['popup'] = $this->popup_descriptor();
		}

		if ( 'dsf_form' === $post_type ) {
			$rows                        = get_post_meta( $post_id, '_dsf_form_rows', true );
			$settings                    = get_post_meta( $post_id, '_dsf_form_settings', true );
			$value['form_rows']          = is_array( $rows ) ? $rows : array();
			$descriptor['form_rows']     = $this->form_rows_descriptor();
			$value['form_settings']      = $this->visitor_form_settings( $settings );
			$descriptor['form_settings'] = $this->form_settings_descriptor();
		}

		return array(
			'value'      => $value,
			'descriptor' => DSF_Translation_Contract::map( $descriptor ),
		);
	}

	/**
	 * Build the document for a taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id  Term ID.
	 * @return array|WP_Error
	 */
	private function build_term_document( $taxonomy, $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return new WP_Error( 'dsf_translation_object_missing', __( 'The translation source term no longer exists.', 'designstudio-flow' ) );
		}

		return array(
			'value'      => array(
				'name'        => (string) $term->name,
				'description' => (string) $term->description,
			),
			'descriptor' => DSF_Translation_Contract::map(
				array(
					'name'        => DSF_Translation_Contract::text(),
					'description' => DSF_Translation_Contract::html(),
				)
			),
		);
	}

	/**
	 * Build the document for a WooCommerce catalog overlay.
	 *
	 * The source is the canonical object's visitor-facing text. Price, SKU,
	 * stock, attributes, and every other operational value are absent, so they
	 * can never travel to a translator or be written back.
	 *
	 * @param string $subtype    Catalog subtype.
	 * @param int    $overlay_id Encoded overlay identity.
	 * @return array|WP_Error
	 */
	private function build_overlay_document( $subtype, $overlay_id ) {
		$identity = DSF_Translation_Overlays::decode( $overlay_id );
		if ( ! $identity['canonical_id'] ) {
			return new WP_Error( 'dsf_translation_object_missing', __( 'That catalog translation target is invalid.', 'designstudio-flow' ) );
		}

		if ( DSF_Translation_Overlays::is_term_subtype( $subtype ) ) {
			$term = get_term( $identity['canonical_id'], $subtype );
			if ( ! is_object( $term ) || is_wp_error( $term ) ) {
				return new WP_Error( 'dsf_translation_object_missing', __( 'The translation source term no longer exists.', 'designstudio-flow' ) );
			}
			$value = array(
				'title'   => (string) $term->name,
				'content' => (string) $term->description,
			);
		} else {
			$post = get_post( $identity['canonical_id'] );
			if ( ! is_object( $post ) ) {
				return new WP_Error( 'dsf_translation_object_missing', __( 'The translation source product no longer exists.', 'designstudio-flow' ) );
			}
			$value = array(
				'title'   => (string) $post->post_title,
				'excerpt' => (string) $post->post_excerpt,
				'content' => (string) $post->post_content,
			);
		}

		return array(
			'value'      => $value,
			'descriptor' => DSF_Translation_Contract::map(
				array(
					'title'   => DSF_Translation_Contract::text(),
					'excerpt' => DSF_Translation_Contract::html(),
					'content' => DSF_Translation_Contract::html(),
				)
			),
		);
	}

	/**
	 * Build the document for the global notification bar.
	 *
	 * @param int $object_id Reserved synthetic identity.
	 * @return array|WP_Error
	 */
	private function build_notification_document( $object_id ) {
		$language = DSF_Multilingual_Adapters::synthetic_notification_language( $object_id );
		if ( '' === $language ) {
			return new WP_Error( 'dsf_translation_object_missing', __( 'The notification language slot is invalid.', 'designstudio-flow' ) );
		}

		$settings = DSF_Multilingual_Settings::get_settings();
		if ( $language === $settings['main_language'] ) {
			$stored = get_option( 'dsf_notification_bar', array() );
		} else {
			$translations = get_option( DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION, array() );
			$stored       = is_array( $translations ) && isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array();
		}
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'value'      => array(
				'message'  => (string) ( $stored['message'] ?? '' ),
				'linkText' => (string) ( $stored['linkText'] ?? '' ),
			),
			'descriptor' => DSF_Translation_Contract::map(
				array(
					'message'  => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
					'linkText' => DSF_Translation_Contract::text(),
				)
			),
		);
	}

	/**
	 * The visitor-facing popup copy contract.
	 *
	 * @return array
	 */
	private function popup_descriptor() {
		return DSF_Translation_Contract::map(
			array(
				'title'       => DSF_Translation_Contract::text(),
				'subtitle'    => DSF_Translation_Contract::text(),
				'content'     => DSF_Translation_Contract::html(),
				'buttonText'  => DSF_Translation_Contract::text(),
				'successText' => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
			)
		);
	}

	/**
	 * The form structure contract.
	 *
	 * Field names, parameter names, values, conditional logic, and every
	 * routing or recipient setting are deliberately absent: a translation must
	 * never change what a submission contains or where it goes.
	 *
	 * @return array
	 */
	private function form_rows_descriptor() {
		return DSF_Translation_Contract::items(
			array(
				'fields' => DSF_Translation_Contract::items(
					array(
						'label'        => DSF_Translation_Contract::text(),
						'placeholder'  => DSF_Translation_Contract::text(),
						'helpText'     => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
						'defaultValue' => DSF_Translation_Contract::text(),
						'html'         => DSF_Translation_Contract::html(),
						'options'      => DSF_Translation_Contract::items(
							array(
								'label' => DSF_Translation_Contract::text(),
							)
						),
					)
				),
			)
		);
	}

	/**
	 * The visitor-visible and notification copy of a form.
	 *
	 * @return array
	 */
	private function form_settings_descriptor() {
		return DSF_Translation_Contract::map(
			array(
				'submitLabel'         => DSF_Translation_Contract::text(),
				'nextLabel'           => DSF_Translation_Contract::text(),
				'previousLabel'       => DSF_Translation_Contract::text(),
				'successMessage'      => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
				'notificationSubject' => DSF_Translation_Contract::text(),
				'notificationIntro'   => DSF_Translation_Contract::text( DSF_Translation_Contract::FORMAT_MULTILINE ),
				'confirmationMessage' => DSF_Translation_Contract::html(),
			)
		);
	}

	/**
	 * Keep only the form settings a translator may see.
	 *
	 * @param mixed $settings Stored form settings.
	 * @return array
	 */
	private function visitor_form_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$allowed  = array( 'submitLabel', 'nextLabel', 'previousLabel', 'successMessage', 'notificationSubject', 'notificationIntro', 'confirmationMessage' );
		$clean    = array();
		foreach ( $allowed as $key ) {
			if ( isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) ) {
				$clean[ $key ] = (string) $settings[ $key ];
			}
		}
		return $clean;
	}

	/**
	 * Walk a value with its descriptor and return bounded segments.
	 *
	 * @param mixed  $value      Source value.
	 * @param array  $descriptor Normalized descriptor.
	 * @param string $prefix     Path prefix.
	 * @return array{segments:array,truncated:bool}
	 */
	public function extract_document( $value, $descriptor, $prefix = '' ) {
		$state = array(
			'segments'  => array(),
			'chars'     => 0,
			'truncated' => false,
		);
		$this->walk( $value, $descriptor, $prefix, $state, 0 );

		return array(
			'segments'  => $state['segments'],
			'truncated' => $state['truncated'],
		);
	}

	/**
	 * Extract the translatable segments of one blocks array.
	 *
	 * @param array  $blocks Blocks array.
	 * @param string $prefix Path prefix.
	 * @return array{segments:array,truncated:bool}
	 */
	public function extract_blocks( $blocks, $prefix = 'blocks' ) {
		return $this->extract_document( is_array( $blocks ) ? $blocks : array(), array( 'kind' => 'blocks' ), $prefix );
	}

	/**
	 * Recursive bounded walker.
	 *
	 * @param mixed  $value      Current value.
	 * @param array  $descriptor Current descriptor.
	 * @param string $path       Current path.
	 * @param array  $state      Accumulator passed by reference.
	 * @param int    $depth      Current depth.
	 */
	private function walk( $value, $descriptor, $path, &$state, $depth ) {
		if ( $state['truncated'] || $depth > DSF_Translation_Contract::MAX_DEPTH || ! is_array( $descriptor ) ) {
			return;
		}

		$kind = isset( $descriptor['kind'] ) ? $descriptor['kind'] : '';

		if ( 'value' === $kind ) {
			$this->add_value_segments( $value, $descriptor, $path, $state );
			return;
		}

		if ( 'blocks' === $kind ) {
			if ( ! is_array( $value ) ) {
				return;
			}
			$index = 0;
			foreach ( $value as $block ) {
				if ( $index >= self::MAX_BLOCKS || $state['truncated'] ) {
					return;
				}
				$this->walk( $block, array( 'kind' => 'block' ), $this->join( $path, $index ), $state, $depth + 1 );
				++$index;
			}
			return;
		}

		if ( 'block' === $kind ) {
			$this->walk_block( $value, $path, $state, $depth );
			return;
		}

		if ( ! is_array( $value ) ) {
			return;
		}

		$fields = isset( $descriptor['fields'] ) && is_array( $descriptor['fields'] ) ? $descriptor['fields'] : array();

		if ( 'list' === $kind ) {
			$index = 0;
			foreach ( $value as $item ) {
				if ( $index >= self::MAX_LIST_ITEMS || $state['truncated'] ) {
					return;
				}
				$this->walk( $item, DSF_Translation_Contract::map( $fields ), $this->join( $path, $index ), $state, $depth + 1 );
				++$index;
			}
			return;
		}

		if ( 'map' === $kind ) {
			foreach ( $fields as $field => $child ) {
				if ( $state['truncated'] ) {
					return;
				}
				if ( ! array_key_exists( $field, $value ) ) {
					continue;
				}
				$this->walk( $value[ $field ], $child, $this->join( $path, $field ), $state, $depth + 1 );
			}
		}
	}

	/**
	 * Walk one block using its registered translatable contract.
	 *
	 * @param mixed  $block Block array.
	 * @param string $path  Current path.
	 * @param array  $state Accumulator.
	 * @param int    $depth Current depth.
	 */
	private function walk_block( $block, $path, &$state, $depth ) {
		if ( ! is_array( $block ) ) {
			return;
		}
		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( '' === $type || ! isset( $block['settings'] ) || ! is_array( $block['settings'] ) ) {
			return;
		}

		// An unregistered block type resolves to an empty contract, so none of
		// its values may be translated.
		$fields = $this->get_block_fields( $type );
		if ( empty( $fields ) ) {
			return;
		}

		$this->walk( $block['settings'], DSF_Translation_Contract::map( $fields ), $this->join( $path, 'settings' ), $state, $depth + 1 );
	}

	/**
	 * Emit the segment or segments for one leaf value.
	 *
	 * @param mixed  $value      Leaf value.
	 * @param array  $descriptor Value descriptor.
	 * @param string $path       Leaf path.
	 * @param array  $state      Accumulator.
	 */
	private function add_value_segments( $value, $descriptor, $path, &$state ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return;
		}

		$format = isset( $descriptor['format'] ) ? $descriptor['format'] : DSF_Translation_Contract::FORMAT_TEXT;

		if ( DSF_Translation_Contract::FORMAT_HTML === $format ) {
			foreach ( DSF_Translation_Html::extract( $value ) as $node ) {
				$this->push_segment( $state, $path, $node['index'], $format, $node['value'] );
				if ( $state['truncated'] ) {
					return;
				}
			}
			return;
		}

		if ( ! DSF_Translation_Html::is_translatable_value( $value ) ) {
			return;
		}
		$this->push_segment( $state, $path, null, $format, trim( $value ) );
	}

	/**
	 * Append one bounded segment.
	 *
	 * @param array    $state  Accumulator.
	 * @param string   $path   Segment path.
	 * @param int|null $node   Rich-text node position.
	 * @param string   $format Segment format.
	 * @param string   $text   Segment text.
	 */
	private function push_segment( &$state, $path, $node, $format, $text ) {
		if ( self::MAX_SEGMENTS <= count( $state['segments'] ) ) {
			$state['truncated'] = true;
			return;
		}
		$text = (string) $text;
		if ( self::MAX_SEGMENT_CHARS < strlen( $text ) ) {
			return;
		}
		if ( self::MAX_TOTAL_CHARS < $state['chars'] + strlen( $text ) ) {
			$state['truncated'] = true;
			return;
		}

		$state['chars']     += strlen( $text );
		$state['segments'][] = array(
			'path'   => $path,
			'node'   => null === $node ? null : (int) $node,
			'format' => $format,
			'text'   => $text,
		);
	}

	/**
	 * Append one safe path token.
	 *
	 * @param string     $path  Current path.
	 * @param string|int $token Next token.
	 * @return string
	 */
	private function join( $path, $token ) {
		$token = is_int( $token ) ? (string) $token : (string) $token;
		if ( ! preg_match( '/^[A-Za-z0-9_\-]+$/', $token ) ) {
			return $path;
		}
		return '' === $path ? $token : $path . '.' . $token;
	}
}
