<?php
/**
 * The shared language switcher: one resolver, one accessible renderer.
 *
 * Every surface — the built-in headers, the shortcode, and anything an add-on
 * builds later — reads the same resolved list. Targets are real permalinks of
 * reviewed, published siblings; they are never constructed by adding or
 * replacing path text, and a translation that is missing, draft, blocked, or
 * unreviewed simply does not appear.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Language_Switcher {

	const SHORTCODE = 'dsf_language_switcher';

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Language_Context */
	private $context;

	/** @var DSF_Language_Routing */
	private $routing;

	/** @var array<string,array>|null Per-request cache of the resolved items. */
	private $items = null;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services      = is_array( $services ) ? $services : array();
		$this->context = $services['context'] ?? DSF_Language_Context::get_instance();
		$this->routing = $services['routing'] ?? DSF_Language_Routing::get_instance();
	}

	/**
	 * Return the shared switcher service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register the shortcode. */
	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/** Forget the resolved list, normally between requests in tests. */
	public function flush() {
		$this->items = null;
	}

	/**
	 * Resolve the switchable languages for the current request.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_items() {
		if ( null !== $this->items ) {
			return $this->items;
		}
		if ( ! $this->context->is_active() ) {
			$this->items = array();
			return $this->items;
		}

		$links   = $this->resolve_links();
		$current = $this->context->get_request_language();
		$items   = array();

		foreach ( $this->context->get_languages() as $language ) {
			$code = $language['code'];
			if ( ! isset( $links[ $code ] ) ) {
				// A language with no published sibling has nowhere to send a
				// visitor, so it is not offered at all.
				continue;
			}
			$items[] = array(
				'code'      => $code,
				'label'     => $language['native_label'],
				'html_lang' => $language['html_lang'],
				'direction' => $language['direction'],
				'short'     => strtoupper( explode( '-', $code )[0] ),
				'url'       => (string) $links[ $code ]['url'],
				'current'   => $code === $current,
			);
		}

		if ( 1 >= count( $items ) ) {
			// A switcher that cannot switch is noise; render nothing.
			$items = array();
		}

		/**
		 * Filters the resolved language switcher items.
		 *
		 * Items are re-validated after filtering: an entry without a curated
		 * code and a real URL is discarded rather than rendered.
		 *
		 * @param array $items Resolved items.
		 */
		$items = apply_filters( 'dsf_language_switcher_items', $items );

		$this->items = $this->validate_items( $items );
		return $this->items;
	}

	/**
	 * Resolve the published siblings of whatever this request rendered.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function resolve_links() {
		$queried = get_queried_object();

		if ( is_object( $queried ) && isset( $queried->post_type, $queried->ID ) ) {
			$links = $this->routing->get_translation_links( 'post', sanitize_key( $queried->post_type ), absint( $queried->ID ) );
			if ( ! empty( $links ) ) {
				return $links;
			}
			$links = $this->catalog_links( sanitize_key( $queried->post_type ), absint( $queried->ID ) );
			if ( ! empty( $links ) ) {
				return $links;
			}
		}
		if ( is_object( $queried ) && isset( $queried->taxonomy, $queried->term_id ) ) {
			$links = $this->routing->get_translation_links( 'term', sanitize_key( $queried->taxonomy ), absint( $queried->term_id ) );
			if ( ! empty( $links ) ) {
				return $links;
			}
			$links = $this->catalog_links( sanitize_key( $queried->taxonomy ), absint( $queried->term_id ) );
			if ( ! empty( $links ) ) {
				return $links;
			}
		}

		// Archives, search, and 404s have no single translated object. Sending a
		// visitor to the language home is honest; guessing a path is not.
		$links = array();
		foreach ( $this->context->get_languages() as $language ) {
			$links[ $language['code'] ] = array( 'url' => $this->context->home_url( $language['code'] ) );
		}
		return $links;
	}

	/**
	 * Resolve the language siblings of a canonical catalog object.
	 *
	 * A product is one object shared by every language, so its siblings are its
	 * overlay members rather than separate posts.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @return array<string,array<string,string>>
	 */
	private function catalog_links( $subtype, $canonical_id ) {
		if ( ! class_exists( 'DSF_Translation_Overlays' ) || ! in_array( $subtype, DSF_Translation_Overlays::subtypes(), true ) ) {
			return array();
		}

		$overlay_id = DSF_Translation_Overlays::overlay_id( $canonical_id, $this->context->get_main_language() );
		if ( ! $overlay_id ) {
			return array();
		}

		return $this->routing->get_translation_links( DSF_Translation_Overlays::KIND, $subtype, $overlay_id );
	}

	/**
	 * Discard anything a filter added that is not a real, curated target.
	 *
	 * @param mixed $items Filtered items.
	 * @return array<int,array<string,mixed>>
	 */
	private function validate_items( $items ) {
		$clean = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$code = DSF_Multilingual_Settings::normalize_locale_code( $item['code'] ?? '' );
			$url  = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			if ( '' === $code || '' === $url || ! $this->context->is_enabled_language( $code ) ) {
				continue;
			}
			$record  = DSF_Language_Context::describe( $code );
			$clean[] = array(
				'code'      => $code,
				'label'     => isset( $item['label'] ) && is_string( $item['label'] ) ? $item['label'] : $record['native_label'],
				'html_lang' => $record['html_lang'],
				'direction' => $record['direction'],
				'short'     => strtoupper( explode( '-', $code )[0] ),
				'url'       => $url,
				'current'   => ! empty( $item['current'] ),
			);
			if ( count( $clean ) >= DSF_Multilingual_Settings::MAX_LANGUAGES ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * Normalize renderer arguments from an untrusted source.
	 *
	 * @param mixed $args Raw arguments.
	 * @return array<string,mixed>
	 */
	public static function normalize_args( $args ) {
		$args  = is_array( $args ) ? $args : array();
		$style = isset( $args['style'] ) && is_string( $args['style'] ) ? strtolower( trim( $args['style'] ) ) : '';

		$classes = array();
		if ( isset( $args['class'] ) && is_string( $args['class'] ) ) {
			foreach ( array_slice( preg_split( '/\s+/', $args['class'] ), 0, 5 ) as $token ) {
				$token = sanitize_html_class( $token );
				if ( '' !== $token ) {
					$classes[] = $token;
				}
			}
		}

		return array(
			'style'      => in_array( $style, array( 'dropdown', 'list', 'compact' ), true ) ? $style : 'dropdown',
			'show_names' => self::to_bool( $args['show_names'] ?? true, true ),
			'show_codes' => self::to_bool( $args['show_codes'] ?? false, false ),
			'class'      => implode( ' ', $classes ),
		);
	}

	/**
	 * Render the accessible switcher markup.
	 *
	 * @param array $args Renderer arguments.
	 * @return string Empty when there is nothing to switch to.
	 */
	public function render( $args = array() ) {
		$items = $this->get_items();
		if ( empty( $items ) ) {
			return '';
		}

		$args    = self::normalize_args( $args );
		$classes = trim( 'dsf-language-switcher dsf-language-switcher--' . $args['style'] . ' ' . $args['class'] );
		$label   = __( 'Language', 'designstudio-flow' );

		$html  = '<nav class="' . esc_attr( $classes ) . '" aria-label="' . esc_attr( $label ) . '">';
		$html .= '<ul class="dsf-language-switcher__list">';

		foreach ( $items as $item ) {
			$text = $this->item_text( $item, $args );
			if ( ! empty( $item['current'] ) ) {
				// The current language is announced, not offered as a link back
				// to the page the visitor is already on.
				$html .= '<li class="dsf-language-switcher__item is-current">'
					. '<span class="dsf-language-switcher__current" aria-current="true" lang="' . esc_attr( $item['html_lang'] ) . '">'
					. esc_html( $text )
					. '</span></li>';
				continue;
			}

			$html .= '<li class="dsf-language-switcher__item">'
				. '<a class="dsf-language-switcher__link" href="' . esc_url( $item['url'] ) . '"'
				. ' lang="' . esc_attr( $item['html_lang'] ) . '"'
				. ' hreflang="' . esc_attr( $item['html_lang'] ) . '"'
				. ' dir="' . esc_attr( 'rtl' === $item['direction'] ? 'rtl' : 'ltr' ) . '">'
				. esc_html( $text )
				. '</a></li>';
		}

		$html .= '</ul></nav>';
		return $html;
	}

	/**
	 * Render the `[dsf_language_switcher]` shortcode.
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'style'      => 'dropdown',
				'show_names' => 'true',
				'show_codes' => 'false',
				'class'      => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		return $this->render( $atts );
	}

	/**
	 * Build the visible text of one item.
	 *
	 * @param array $item Resolved item.
	 * @param array $args Normalized arguments.
	 * @return string
	 */
	private function item_text( $item, $args ) {
		if ( 'compact' === $args['style'] || ( ! $args['show_names'] && $args['show_codes'] ) ) {
			return $item['short'];
		}
		if ( $args['show_names'] && $args['show_codes'] ) {
			return $item['label'] . ' (' . $item['short'] . ')';
		}
		if ( ! $args['show_names'] && ! $args['show_codes'] ) {
			// A switcher with no readable label would be unusable, so the native
			// name is the floor rather than an empty control.
			return $item['label'];
		}
		return $args['show_names'] ? $item['label'] : $item['short'];
	}

	/**
	 * Interpret only explicit boolean spellings.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Fallback when unrecognized.
	 * @return bool
	 */
	private static function to_bool( $value, $fallback ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		return $fallback;
	}
}
