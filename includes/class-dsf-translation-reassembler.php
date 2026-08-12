<?php
/**
 * Reassembly of translated segments into storable content.
 *
 * Translated text is untrusted input, whatever produced it. Reassembly
 * therefore never writes a value by path alone: it re-walks the source
 * document with the same explicit descriptor, so only declared paths can
 * change, and then hands every section back through the same type-specific
 * sanitizer the normal save path uses.
 *
 * Persisting the result is deliberately not this class's job. It returns a
 * sanitized payload the clone/review workflow stores under its own gates.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Reassembler {

	const MAX_TRANSLATIONS = 2000;

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Extractor */
	private $extractor;

	/** @var int Number of segments actually replaced during the last run. */
	private $applied = 0;

	/** @var int Number of submitted segments rejected during the last run. */
	private $rejected = 0;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services        = is_array( $services ) ? $services : array();
		$this->extractor = $services['extractor'] ?? DSF_Translation_Extractor::get_instance();
	}

	/**
	 * Return the shared reassembler.
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
	 * Apply translated segments to one relationship member.
	 *
	 * @param array $member       Relationship member.
	 * @param array $translations Segments as returned by the extractor, with
	 *                            translated `text` values.
	 * @return array{document:array,applied:int,rejected:int}|WP_Error
	 */
	public function apply_to_member( $member, $translations ) {
		$document = $this->extractor->build_document( $member );
		if ( $document instanceof WP_Error ) {
			return $document;
		}

		$map = $this->index_translations( $translations );
		if ( $map instanceof WP_Error ) {
			return $map;
		}

		$this->applied  = 0;
		$this->rejected = 0;
		$submitted      = $this->count_entries( $map );

		$value = $this->apply( $document['value'], $document['descriptor'], '', $map );
		$value = $this->sanitize_document( $member, is_array( $value ) ? $value : array() );

		return array(
			'document' => $value,
			'applied'  => $this->applied,
			// Anything the descriptor did not claim is silently discarded rather
			// than written to an undeclared path.
			'rejected' => max( 0, $submitted - $this->applied ),
		);
	}

	/**
	 * Apply translated segments to a value using its explicit descriptor.
	 *
	 * @param mixed  $value      Source value.
	 * @param array  $descriptor Normalized descriptor.
	 * @param string $prefix     Path prefix.
	 * @param array  $map        Indexed translations.
	 * @return mixed Modified value.
	 */
	public function apply_to_document( $value, $descriptor, $prefix, $map ) {
		$this->applied  = 0;
		$this->rejected = 0;
		$indexed        = $this->index_translations( $map );
		if ( $indexed instanceof WP_Error ) {
			return $value;
		}
		return $this->apply( $value, $descriptor, $prefix, $indexed );
	}

	/** Segments replaced during the last run. */
	public function get_applied_count() {
		return $this->applied;
	}

	/**
	 * Count the individual translations an indexed map carries.
	 *
	 * @param array $map Indexed translations.
	 * @return int
	 */
	private function count_entries( $map ) {
		$total = 0;
		foreach ( is_array( $map ) ? $map : array() as $entry ) {
			if ( isset( $entry['value'] ) ) {
				++$total;
			}
			if ( isset( $entry['nodes'] ) && is_array( $entry['nodes'] ) ) {
				$total += count( $entry['nodes'] );
			}
		}
		return $total;
	}

	/**
	 * Build a path-indexed translation map from untrusted segment input.
	 *
	 * @param array $translations Submitted segments.
	 * @return array<string,array>|WP_Error
	 */
	private function index_translations( $translations ) {
		if ( ! is_array( $translations ) ) {
			return new WP_Error( 'dsf_translation_payload', __( 'The translation payload is invalid.', 'designstudio-flow' ) );
		}
		if ( self::MAX_TRANSLATIONS < count( $translations ) ) {
			return new WP_Error( 'dsf_translation_payload_size', __( 'The translation payload contains too many segments.', 'designstudio-flow' ) );
		}

		$map = array();
		foreach ( $translations as $segment ) {
			if ( ! is_array( $segment ) ) {
				continue;
			}
			$path = isset( $segment['path'] ) && is_string( $segment['path'] ) ? $segment['path'] : '';
			$text = isset( $segment['text'] ) && is_scalar( $segment['text'] ) ? (string) $segment['text'] : null;
			if ( '' === $path || null === $text || ! preg_match( '/^[A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)*$/', $path ) ) {
				continue;
			}
			if ( DSF_Translation_Extractor::MAX_SEGMENT_CHARS < strlen( $text ) || '' === trim( $text ) ) {
				continue;
			}

			$node = isset( $segment['node'] ) && is_numeric( $segment['node'] ) ? (int) $segment['node'] : null;
			if ( null === $node ) {
				$map[ $path ]['value'] = $text;
			} else {
				$map[ $path ]['nodes'][ $node ] = $text;
			}
		}

		return $map;
	}

	/**
	 * Recursively rebuild a value, replacing only declared paths.
	 *
	 * @param mixed  $value      Source value.
	 * @param array  $descriptor Descriptor.
	 * @param string $path       Current path.
	 * @param array  $map        Indexed translations.
	 * @param int    $depth      Current depth.
	 * @return mixed
	 */
	private function apply( $value, $descriptor, $path, $map, $depth = 0 ) {
		if ( $depth > DSF_Translation_Contract::MAX_DEPTH || ! is_array( $descriptor ) ) {
			return $value;
		}

		$kind = isset( $descriptor['kind'] ) ? $descriptor['kind'] : '';

		if ( 'value' === $kind ) {
			return $this->apply_value( $value, $descriptor, $path, $map );
		}

		if ( 'blocks' === $kind ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			$index = 0;
			foreach ( $value as $key => $block ) {
				if ( $index >= DSF_Translation_Extractor::MAX_BLOCKS ) {
					break;
				}
				$value[ $key ] = $this->apply( $block, array( 'kind' => 'block' ), $this->join( $path, $index ), $map, $depth + 1 );
				++$index;
			}
			return $value;
		}

		if ( 'block' === $kind ) {
			return $this->apply_block( $value, $path, $map, $depth );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$fields = isset( $descriptor['fields'] ) && is_array( $descriptor['fields'] ) ? $descriptor['fields'] : array();

		if ( 'list' === $kind ) {
			$index = 0;
			foreach ( $value as $key => $item ) {
				if ( $index >= DSF_Translation_Extractor::MAX_LIST_ITEMS ) {
					break;
				}
				$value[ $key ] = $this->apply( $item, DSF_Translation_Contract::map( $fields ), $this->join( $path, $index ), $map, $depth + 1 );
				++$index;
			}
			return $value;
		}

		if ( 'map' === $kind ) {
			foreach ( $fields as $field => $child ) {
				if ( ! array_key_exists( $field, $value ) ) {
					continue;
				}
				$value[ $field ] = $this->apply( $value[ $field ], $child, $this->join( $path, $field ), $map, $depth + 1 );
			}
		}

		return $value;
	}

	/**
	 * Apply translations inside one block's declared settings.
	 *
	 * @param mixed  $block Block array.
	 * @param string $path  Current path.
	 * @param array  $map   Indexed translations.
	 * @param int    $depth Current depth.
	 * @return mixed
	 */
	private function apply_block( $block, $path, $map, $depth ) {
		if ( ! is_array( $block ) || ! isset( $block['settings'] ) || ! is_array( $block['settings'] ) ) {
			return $block;
		}
		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( '' === $type ) {
			return $block;
		}
		$fields = $this->extractor->get_block_fields( $type );
		if ( empty( $fields ) ) {
			return $block;
		}

		$block['settings'] = $this->apply(
			$block['settings'],
			DSF_Translation_Contract::map( $fields ),
			$this->join( $path, 'settings' ),
			$map,
			$depth + 1
		);
		return $block;
	}

	/**
	 * Replace one leaf value when a translation was submitted for it.
	 *
	 * @param mixed  $value      Source value.
	 * @param array  $descriptor Value descriptor.
	 * @param string $path       Leaf path.
	 * @param array  $map        Indexed translations.
	 * @return mixed
	 */
	private function apply_value( $value, $descriptor, $path, $map ) {
		if ( ! is_string( $value ) || ! isset( $map[ $path ] ) ) {
			return $value;
		}

		$format = isset( $descriptor['format'] ) ? $descriptor['format'] : DSF_Translation_Contract::FORMAT_TEXT;

		if ( DSF_Translation_Contract::FORMAT_HTML === $format ) {
			if ( empty( $map[ $path ]['nodes'] ) || ! is_array( $map[ $path ]['nodes'] ) ) {
				return $value;
			}
			$replaced = DSF_Translation_Html::replace( $value, $map[ $path ]['nodes'] );
			if ( $replaced !== $value ) {
				$this->applied += count( $map[ $path ]['nodes'] );
			}
			return $replaced;
		}

		if ( ! isset( $map[ $path ]['value'] ) ) {
			return $value;
		}
		++$this->applied;
		return (string) $map[ $path ]['value'];
	}

	/**
	 * Re-enter the type-specific sanitizers for every translated section.
	 *
	 * @param array $member   Relationship member.
	 * @param array $document Modified document.
	 * @return array
	 */
	private function sanitize_document( $member, $document ) {
		$subtype = sanitize_key( $member['object_subtype'] ?? '' );

		if ( isset( $document['post_title'] ) ) {
			$document['post_title'] = sanitize_text_field( (string) $document['post_title'] );
		}
		if ( isset( $document['post_excerpt'] ) ) {
			$document['post_excerpt'] = wp_kses_post( (string) $document['post_excerpt'] );
		}
		if ( isset( $document['post_content'] ) ) {
			$document['post_content'] = wp_kses_post( (string) $document['post_content'] );
		}
		if ( isset( $document['name'] ) ) {
			$document['name'] = sanitize_text_field( (string) $document['name'] );
		}
		if ( isset( $document['description'] ) ) {
			$document['description'] = wp_kses_post( (string) $document['description'] );
		}

		if ( isset( $document['blocks'] ) && class_exists( 'DSF_Ajax' ) ) {
			$document['blocks'] = DSF_Ajax::get_instance()->sanitize_blocks_for_storage( $document['blocks'] );
		}

		if ( isset( $document['saved_block'] ) && class_exists( 'DSF_Ajax' ) ) {
			$sanitized               = DSF_Ajax::get_instance()->sanitize_blocks_for_storage( array( $document['saved_block'] ) );
			$document['saved_block'] = isset( $sanitized[0] ) && is_array( $sanitized[0] ) ? $sanitized[0] : array();
		}

		if ( isset( $document['seo'] ) && class_exists( 'DSF_Ajax' ) ) {
			$clean           = DSF_Ajax::get_instance()->sanitize_seo_for_storage( $document['seo'] );
			$document['seo'] = array(
				'title'       => $clean['title'],
				'description' => $clean['description'],
			);
		}

		if ( isset( $document['popup'] ) && class_exists( 'DSF_Popup' ) ) {
			$document['popup'] = DSF_Popup::sanitize_settings( $document['popup'] );
		}

		if ( ( isset( $document['form_rows'] ) || isset( $document['form_settings'] ) ) && class_exists( 'DSF_Forms' ) ) {
			$clean                     = DSF_Forms::get_instance()->sanitize_imported_form(
				$document['form_rows'] ?? array(),
				$document['form_settings'] ?? array()
			);
			$document['form_rows']     = $clean['rows'];
			$document['form_settings'] = $this->keep_translated_form_settings( $document['form_settings'] ?? array(), $clean['settings'] );
		}

		if ( class_exists( 'DSF_Translation_Overlays' ) && DSF_Translation_Overlays::KIND === sanitize_key( $member['object_kind'] ?? '' ) ) {
			// A catalog overlay may only ever carry display text.
			return DSF_Translation_Overlays::sanitize_fields( $document );
		}

		if ( 'notification_bar' === $subtype && class_exists( 'DSF_Notification_Bar' ) ) {
			$clean    = DSF_Notification_Bar::sanitize_settings( $document );
			$document = array(
				'message'  => $clean['message'] ?? '',
				'linkText' => $clean['linkText'] ?? '',
			);
		}

		return $document;
	}

	/**
	 * Keep only the visitor-facing form settings after full sanitization.
	 *
	 * Recipients, webhooks, redirects, and spam controls belong to the form
	 * itself and must never be carried by a translation payload.
	 *
	 * @param array $submitted Translated settings.
	 * @param array $sanitized Fully sanitized settings.
	 * @return array
	 */
	private function keep_translated_form_settings( $submitted, $sanitized ) {
		$clean = array();
		foreach ( array_keys( is_array( $submitted ) ? $submitted : array() ) as $key ) {
			if ( isset( $sanitized[ $key ] ) && is_scalar( $sanitized[ $key ] ) ) {
				$clean[ $key ] = $sanitized[ $key ];
			}
		}
		return $clean;
	}

	/**
	 * Append one safe path token.
	 *
	 * @param string     $path  Current path.
	 * @param string|int $token Next token.
	 * @return string
	 */
	private function join( $path, $token ) {
		$token = (string) $token;
		if ( ! preg_match( '/^[A-Za-z0-9_\-]+$/', $token ) ) {
			return $path;
		}
		return '' === $path ? $token : $path . '.' . $token;
	}
}
