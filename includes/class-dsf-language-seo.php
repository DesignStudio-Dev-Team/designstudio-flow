<?php
/**
 * Multilingual frontend SEO output.
 *
 * DesignStudio Flow owns the translation relationships, so it stays
 * authoritative for `hreflang` and `x-default` even when a dedicated SEO plugin
 * renders the rest of the head. Canonical output is deliberately conditional so
 * two plugins never print competing canonical tags for the same URL.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Language_SEO {

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Language_Context */
	private $context;

	/** @var DSF_Language_Routing */
	private $routing;

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services      = is_array( $services ) ? $services : array();
		$this->context = $services['context'] ?? DSF_Language_Context::get_instance();
		$this->routing = $services['routing'] ?? DSF_Language_Routing::get_instance();
	}

	/**
	 * Return the shared service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register frontend head output. */
	public function register_hooks() {
		if ( is_admin() ) {
			return;
		}
		// Priority 3 keeps alternates immediately before the Flow SEO block and
		// before most SEO plugins print their own head output.
		add_action( 'wp_head', array( $this, 'output_alternate_links' ), 3 );
		add_action( 'wp_head', array( $this, 'output_language_canonical' ), 3 );
	}

	/**
	 * Print reciprocal `hreflang` links plus `x-default`.
	 */
	public function output_alternate_links() {
		if ( ! $this->context->is_active() ) {
			return;
		}
		/**
		 * Filters whether Flow prints translation alternates.
		 *
		 * Set this to false when another integration is already emitting Flow's
		 * translation relationships, so no URL gets duplicate alternates.
		 *
		 * @param bool $enabled Whether to print alternates.
		 */
		if ( ! apply_filters( 'dsf_multilingual_output_hreflang', true ) ) {
			return;
		}

		$links = $this->current_alternates();
		if ( count( $links ) < 2 ) {
			// A single self-referencing alternate carries no information.
			return;
		}

		foreach ( $links as $link ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />%3$s',
				esc_attr( $link['html_lang'] ),
				esc_url( $link['url'] ),
				"\n"
			);
		}

		$main = $this->context->get_main_language();
		if ( isset( $links[ $main ] ) ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%1$s" />%2$s',
				esc_url( $links[ $main ]['url'] ),
				"\n"
			);
		}
	}

	/**
	 * Print a self-canonical URL for language routes core does not cover.
	 *
	 * Core prints `rel_canonical` on singular requests, and DSF_SEO prints an
	 * authored override when one exists. This only fills the remaining gap:
	 * non-singular secondary-language URLs.
	 */
	public function output_language_canonical() {
		if ( ! $this->context->is_active() || ! $this->routing->is_route_request() ) {
			return;
		}
		if ( is_singular() || is_feed() || ( class_exists( 'DSF_SEO' ) && DSF_SEO::has_seo_plugin() ) ) {
			return;
		}
		if ( '' !== (string) $this->authored_canonical() ) {
			return;
		}

		$url = $this->current_request_url();
		if ( '' === $url ) {
			return;
		}
		printf( '<link rel="canonical" href="%1$s" />%2$s', esc_url( $url ), "\n" );
	}

	/**
	 * Resolve alternates for whatever object the current request rendered.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function current_alternates() {
		$queried = get_queried_object();

		if ( is_object( $queried ) && isset( $queried->post_type, $queried->ID ) ) {
			return $this->routing->get_translation_links( 'post', sanitize_key( $queried->post_type ), absint( $queried->ID ) );
		}
		if ( is_object( $queried ) && isset( $queried->taxonomy, $queried->term_id ) ) {
			return $this->routing->get_translation_links( 'term', sanitize_key( $queried->taxonomy ), absint( $queried->term_id ) );
		}
		if ( is_front_page() || is_home() ) {
			return $this->home_alternates();
		}

		return array();
	}

	/**
	 * Build alternates for the site front page in every enabled language.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function home_alternates() {
		$front_id = (int) get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $front_id ) {
			return $this->routing->get_translation_links( 'post', get_post_type( $front_id ), $front_id );
		}

		$links = array();
		foreach ( $this->context->get_languages() as $language ) {
			$links[ $language['code'] ] = array(
				'code'      => $language['code'],
				'html_lang' => $language['html_lang'],
				'label'     => $language['native_label'],
				'direction' => $language['direction'],
				'url'       => $this->context->home_url( $language['code'] ),
			);
		}
		return $links;
	}

	/**
	 * Read an authored canonical override for the current Flow surface.
	 *
	 * @return string
	 */
	private function authored_canonical() {
		$queried = get_queried_object();
		if ( ! is_object( $queried ) || empty( $queried->ID ) ) {
			return '';
		}
		$settings = get_post_meta( absint( $queried->ID ), '_dsf_settings', true );
		if ( ! is_array( $settings ) || ! isset( $settings['seo']['canonical'] ) ) {
			return '';
		}
		return is_string( $settings['seo']['canonical'] ) ? trim( $settings['seo']['canonical'] ) : '';
	}

	/**
	 * Build the current request URL from trusted server-side values only.
	 *
	 * @return string
	 */
	private function current_request_url() {
		$route = $this->routing->get_resolved_route();
		if ( is_array( $route ) ) {
			return $this->context->home_url( $route['language'], $route['path'] );
		}
		return $this->context->home_url( $this->context->get_request_language() );
	}
}
