<?php
/**
 * Language prefix routing for the approved URL policy.
 *
 * The main language keeps its existing unprefixed URLs and its native
 * WordPress resolution. Every secondary language is served from a stable
 * prefix, and its paths are resolved through the indexed route map rather than
 * by rewriting URL text, because two languages may legitimately use the same
 * terminal slug.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Language_Routing {

	const PREFIX_HISTORY_OPTION = 'dsf_multilingual_prefix_history';
	const MAX_PREFIX_HISTORY    = 20;
	const MAX_SLUG_CONFLICTS    = 25;

	/** @var self|null */
	private static $instance = null;

	/** @var DSF_Translation_Routes */
	private $routes;

	/** @var DSF_Translation_Relationships */
	private $relationships;

	/** @var DSF_Language_Context */
	private $context;

	/** @var bool Whether this request was resolved by the route map. */
	private $resolved_by_route = false;

	/** @var array|null The route row that resolved this request. */
	private $resolved_route = null;

	/** @var array<int,string> Slugs requested before WordPress deduplicated them. */
	private $requested_slugs = array();

	/** @var string Language a pending insert will belong to. */
	private $expected_language = '';

	/** @var array<string,string> Per-request permalink cache. */
	private $permalink_cache = array();

	/**
	 * @param array $services Optional service overrides for tests.
	 */
	public function __construct( $services = array() ) {
		$services            = is_array( $services ) ? $services : array();
		$this->routes        = $services['routes'] ?? DSF_Translation_Routes::get_instance();
		$this->relationships = $services['relationships'] ?? DSF_Translation_Relationships::get_instance();
		$this->context       = $services['context'] ?? DSF_Language_Context::get_instance();
	}

	/**
	 * Return the shared routing service.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register request, permalink, and maintenance hooks. */
	public function register_hooks() {
		add_filter( 'wp_unique_post_slug', array( $this, 'capture_requested_slug' ), 10, 6 );
		add_filter( 'pre_get_page_by_path', array( $this, 'filter_page_by_path' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( $this, 'handle_post_saved' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'handle_term_saved' ), 20, 3 );
		add_action( 'created_term', array( $this, 'handle_term_saved' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_post_deleted' ), 5, 2 );
		add_action( 'delete_term', array( $this, 'handle_term_deleted' ), 5, 4 );
		add_action( 'update_option_' . DSF_Multilingual_Settings::OPTION_NAME, array( $this, 'handle_settings_updated' ), 10, 2 );

		add_action( 'parse_request', array( $this, 'resolve_request' ), 1 );
		add_action( 'template_redirect', array( $this, 'enforce_canonical_language_url' ), 5 );
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ), 10, 2 );
		add_filter( 'posts_where', array( $this, 'filter_query_language' ), 10, 2 );

		add_filter( 'page_link', array( $this, 'filter_page_link' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'filter_post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_post_link' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 3 );
	}

	/** Whether the current request was resolved through the route map. */
	public function is_route_request() {
		return $this->resolved_by_route;
	}

	/** The route row that resolved the current request, when any. */
	public function get_resolved_route() {
		return $this->resolved_route;
	}

	/**
	 * Resolve the request language and, for a secondary prefix, its object.
	 *
	 * @param WP $wp Current WordPress environment instance.
	 */
	public function resolve_request( $wp ) {
		if ( ! is_object( $wp ) || ! $this->context->is_active() ) {
			return;
		}

		$path    = $this->request_path( $wp );
		$segment = '' === $path ? '' : explode( '/', $path )[0];

		$language = $this->context->language_for_prefix( $segment );
		if ( '' === $language ) {
			$this->maybe_redirect_retired_prefix( $segment, $path );
			$this->context->set_request_language( $this->context->get_main_language() );
			return;
		}

		$this->context->set_request_language( $language );
		$remainder = trim( substr( $path, strlen( $segment ) ), '/' );
		$parts     = $this->split_trailing_request_parts( $remainder );

		if ( '' === $parts['path'] ) {
			$this->resolve_language_home( $wp, $language, $parts );
			return;
		}

		$route = $this->routes->find_by_path( $language, $parts['path'] );
		if ( ! is_array( $route ) ) {
			$this->handle_missing_translation( $wp, $language, $parts['path'] );
			return;
		}

		$query_vars = $this->query_vars_for_route( $route, $parts );
		if ( empty( $query_vars ) ) {
			$this->handle_missing_translation( $wp, $language, $parts['path'] );
			return;
		}

		$wp->query_vars          = $query_vars;
		$wp->matched_rule        = '';
		$wp->matched_query       = '';
		$this->resolved_route    = $route;
		$this->resolved_by_route = true;
		unset( $wp->query_vars['error'] );
	}

	/**
	 * Send a visitor from a bare language prefix to that language's front page.
	 *
	 * @param WP     $wp       Current environment.
	 * @param string $language Resolved language.
	 * @param array  $parts    Trailing pagination/feed parts.
	 */
	private function resolve_language_home( $wp, $language, $parts ) {
		$front_id = (int) get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $front_id ) {
			$member = $this->find_sibling( 'post', get_post_type( $front_id ), $front_id, $language );
			if ( ! is_array( $member ) || ! $this->is_publicly_viewable_member( $member ) ) {
				$this->handle_missing_translation( $wp, $language, '' );
				return;
			}
			$wp->query_vars = array( 'page_id' => absint( $member['object_id'] ) );
			if ( $parts['paged'] ) {
				$wp->query_vars['page'] = $parts['paged'];
			}
		} else {
			$wp->query_vars = array();
			if ( $parts['paged'] ) {
				$wp->query_vars['paged'] = $parts['paged'];
			}
		}

		if ( '' !== $parts['feed'] ) {
			$wp->query_vars['feed'] = $parts['feed'];
		}
		$this->resolved_by_route = true;
		$this->resolved_route    = null;
	}

	/**
	 * Apply the configured behavior for an unmatched secondary-language path.
	 *
	 * Rendering main-language content under a secondary URL is never allowed, so
	 * the only options are a language-specific 404 or an explicit redirect.
	 *
	 * @param WP     $wp       Current environment.
	 * @param string $language Resolved language.
	 * @param string $path     Unmatched path.
	 */
	private function handle_missing_translation( $wp, $language, $path ) {
		$settings = $this->context->get_settings();
		if ( 'visible_redirect' === $settings['missing_translation_policy'] ) {
			$target = $this->main_language_url_for_path( $path );
			if ( '' !== $target ) {
				$this->redirect( $target, 302 );
				return;
			}
		}

		unset( $language );
		$wp->query_vars          = array( 'error' => '404' );
		$this->resolved_by_route = true;
		$this->resolved_route    = null;
	}

	/**
	 * Redirect a retired language prefix to its current one.
	 *
	 * @param string $segment First path segment.
	 * @param string $path    Full request path.
	 */
	private function maybe_redirect_retired_prefix( $segment, $path ) {
		$segment = DSF_Multilingual_Settings::sanitize_prefix( $segment );
		if ( '' === $segment ) {
			return;
		}

		$history = self::get_prefix_history();
		if ( ! isset( $history[ $segment ] ) ) {
			return;
		}

		$prefix = $this->context->get_prefix( $history[ $segment ] );
		if ( '' === $prefix || $prefix === $segment ) {
			return;
		}

		$remainder = trim( substr( $path, strlen( $segment ) ), '/' );
		$this->redirect( $this->context->home_url( $history[ $segment ], $remainder ), 301 );
	}

	/**
	 * Report what a proposed language prefix would collide with.
	 *
	 * The static reserved list in the settings service covers core routes. This
	 * adds the checks that depend on this site's actual content and registered
	 * rewrite bases, so a prefix can never shadow a real URL.
	 *
	 * @param string $prefix Proposed prefix.
	 * @return string Human-readable collision reason, empty when the prefix is free.
	 */
	public static function describe_prefix_collision( $prefix ) {
		$prefix = DSF_Multilingual_Settings::sanitize_prefix( $prefix );
		if ( '' === $prefix ) {
			return __( 'This prefix is not a valid or allowed URL segment.', 'designstudio-flow' );
		}

		$page = get_page_by_path( $prefix, OBJECT, array( 'page', 'post' ) );
		if ( is_object( $page ) ) {
			return __( 'An existing page or post already uses this URL segment.', 'designstudio-flow' );
		}

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			$base = is_array( $post_type->rewrite ?? null ) && ! empty( $post_type->rewrite['slug'] ) ? (string) $post_type->rewrite['slug'] : '';
			if ( '' !== $base && strtok( trim( $base, '/' ), '/' ) === $prefix ) {
				return __( 'A registered post type already uses this URL segment.', 'designstudio-flow' );
			}
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$base = is_array( $taxonomy->rewrite ?? null ) && ! empty( $taxonomy->rewrite['slug'] ) ? (string) $taxonomy->rewrite['slug'] : '';
			if ( '' !== $base && strtok( trim( $base, '/' ), '/' ) === $prefix ) {
				return __( 'A registered taxonomy already uses this URL segment.', 'designstudio-flow' );
			}
		}

		return '';
	}

	/**
	 * Redirect a secondary object reached through its native permalink.
	 *
	 * WordPress still resolves the deduplicated native slug of a translated
	 * object. Exactly one public URL may represent it, so the request is sent to
	 * the prefixed route.
	 */
	public function enforce_canonical_language_url() {
		if ( $this->resolved_by_route || is_admin() || ! $this->context->is_active() || is_preview() || is_feed() ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! is_object( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) {
			return;
		}
		$member = $this->relationships->find_by_object( 'post', sanitize_key( $post->post_type ), absint( $post->ID ) );
		if ( ! is_array( $member ) || $member['language'] === $this->context->get_main_language() ) {
			return;
		}

		$route_url = $this->get_route_url( 'post', sanitize_key( $post->post_type ), absint( $post->ID ) );
		if ( '' === $route_url ) {
			return;
		}
		$this->redirect( $route_url, 301 );
	}

	/**
	 * Keep core canonical redirects away from resolved language routes.
	 *
	 * @param string|false $redirect_url  Proposed redirect.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function filter_redirect_canonical( $redirect_url, $requested_url = '' ) {
		unset( $requested_url );
		return $this->resolved_by_route ? false : $redirect_url;
	}

	/**
	 * Restrict language-blind archive, search, and feed queries.
	 *
	 * Singular requests are already language-specific because they resolve one
	 * object. Listing queries would otherwise mix languages on one URL.
	 *
	 * @param string   $where Current WHERE clause.
	 * @param WP_Query $query Query being prepared.
	 * @return string
	 */
	public function filter_query_language( $where, $query = null ) {
		if ( ! is_object( $query ) || ! $this->context->is_active() ) {
			return $where;
		}
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() ) {
			return $where;
		}
		if ( ! $query->is_home() && ! $query->is_archive() && ! $query->is_search() && ! $query->is_feed() ) {
			return $where;
		}

		$post_types = (array) $query->get( 'post_type' );
		$post_types = array_filter( array_map( 'sanitize_key', $post_types ) );
		if ( ! empty( $post_types ) && ! array_intersect( $post_types, DSF_Multilingual_Adapters::relationship_post_types() ) ) {
			return $where;
		}

		global $wpdb;
		$table    = DSF_Translation_Relationships::relationship_table_name( $wpdb );
		$language = $this->context->get_request_language();
		$posts    = $wpdb->posts;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted wpdb prefix.
		$matches = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$table} dsf_route_language WHERE dsf_route_language.object_kind = 'post' AND dsf_route_language.object_id = {$posts}.ID AND dsf_route_language.language = %s)", $language );

		// Objects that are never translated into separate posts — products above
		// all — carry no relationship row in any language and must stay visible.
		$untranslated = $this->untranslated_post_type_condition( $wpdb, $posts );
		if ( '' !== $untranslated ) {
			$matches = '( ' . $matches . ' OR ' . $untranslated . ' )';
		}

		if ( $language === $this->context->get_main_language() ) {
			// Content created before the foundation migration finished has no
			// relationship row yet and must keep its existing main-language URL.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted wpdb prefix.
			$unassigned = "NOT EXISTS (SELECT 1 FROM {$table} dsf_route_any WHERE dsf_route_any.object_kind = 'post' AND dsf_route_any.object_id = {$posts}.ID)";
			return $where . " AND ( {$matches} OR {$unassigned} )";
		}

		return $where . " AND {$matches}";
	}

	/**
	 * Build a SQL condition matching post types that are not translated objects.
	 *
	 * @param wpdb   $wpdb  Database handle.
	 * @param string $posts Posts table name.
	 * @return string Empty when every registered type is translatable.
	 */
	private function untranslated_post_type_condition( $wpdb, $posts ) {
		$translatable = DSF_Multilingual_Adapters::relationship_post_types();
		if ( empty( $translatable ) ) {
			return '';
		}

		$placeholders = implode( ', ', array_fill( 0, count( $translatable ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted wpdb prefix; values are placeholders.
		return $wpdb->prepare( "{$posts}.post_type NOT IN ( {$placeholders} )", ...$translatable );
	}

	/**
	 * Return the public URL of a translated object, when it has a route.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Object ID.
	 * @return string Empty when the object has no stored route.
	 */
	public function get_route_url( $object_kind, $object_subtype, $object_id ) {
		$cache_key = $object_kind . '|' . $object_subtype . '|' . absint( $object_id );
		if ( isset( $this->permalink_cache[ $cache_key ] ) ) {
			return $this->permalink_cache[ $cache_key ];
		}

		$route = $this->routes->get_route( $object_kind, $object_subtype, $object_id );
		$url   = is_array( $route ) ? $this->context->home_url( $route['language'], $route['path'] ) : '';

		$this->permalink_cache[ $cache_key ] = $url;
		return $url;
	}

	/**
	 * Resolve the publicly available language siblings of one object.
	 *
	 * This is the shared resolver for `hreflang` output and, later, the language
	 * switcher. It returns real permalinks for objects that are actually public;
	 * drafts, pending reviews, blocked, and private members are never exposed.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Object ID.
	 * @return array<string,array<string,string>> Language code to link data.
	 */
	public function get_translation_links( $object_kind, $object_subtype, $object_id ) {
		if ( ! $this->context->is_active() ) {
			return array();
		}

		$member = $this->relationships->find_by_object( sanitize_key( $object_kind ), sanitize_key( $object_subtype ), absint( $object_id ) );
		if ( ! is_array( $member ) ) {
			return array();
		}
		$members = $this->relationships->list_group( $member['group_uuid'] );
		if ( ! is_array( $members ) ) {
			return array();
		}

		$links = array();
		foreach ( $members as $sibling ) {
			if ( ! is_array( $sibling ) || ! $this->context->is_enabled_language( $sibling['language'] ) ) {
				continue;
			}
			$url = $this->public_url_for_member( $sibling );
			if ( '' === $url ) {
				continue;
			}
			$record = DSF_Language_Context::describe( $sibling['language'] );
			if ( empty( $record ) ) {
				continue;
			}
			$links[ $sibling['language'] ] = array(
				'code'      => $sibling['language'],
				'html_lang' => $record['html_lang'],
				'label'     => $record['native_label'],
				'direction' => $record['direction'],
				'url'       => $url,
			);
		}

		return $links;
	}

	/**
	 * Return the public URL of one member, or an empty string when it has none.
	 *
	 * @param array $member Relationship member.
	 * @return string
	 */
	public function public_url_for_member( $member ) {
		$kind    = (string) ( $member['object_kind'] ?? '' );
		$subtype = sanitize_key( (string) ( $member['object_subtype'] ?? '' ) );
		$id      = absint( $member['object_id'] ?? 0 );
		if ( ! $id || '' === $subtype ) {
			return '';
		}

		if ( 'post' === $kind ) {
			if ( ! $this->is_publicly_viewable_member( $member ) ) {
				return '';
			}
			$permalink = get_permalink( $id );
			return is_string( $permalink ) ? $permalink : '';
		}

		if ( 'term' === $kind ) {
			$link = get_term_link( $id, $subtype );
			return is_string( $link ) ? $link : '';
		}

		if ( class_exists( 'DSF_Translation_Overlays' ) && DSF_Translation_Overlays::KIND === $kind ) {
			$identity = DSF_Translation_Overlays::decode( $id );
			if ( ! $identity['canonical_id'] ) {
				return '';
			}
			// The main-language member is the canonical object at its own URL.
			if ( $identity['language'] === $this->context->get_main_language() ) {
				return $this->canonical_catalog_url( $subtype, $identity['canonical_id'] );
			}
			return $this->get_route_url( $kind, $subtype, $id );
		}

		return '';
	}

	/**
	 * The canonical, unprefixed URL of a catalog object.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @return string
	 */
	private function canonical_catalog_url( $subtype, $canonical_id ) {
		if ( DSF_Translation_Overlays::is_term_subtype( $subtype ) ) {
			$link = get_term_link( $canonical_id, $subtype );
			return is_string( $link ) ? $link : '';
		}
		$permalink = get_permalink( $canonical_id );
		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Store or drop the prefixed route of one catalog overlay.
	 *
	 * The path mirrors the canonical permalink exactly, so a prefixed catalog
	 * URL stays in step with whatever product base or permalink structure the
	 * store is configured with.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @param string $language     Target language.
	 * @return array|WP_Error|null
	 */
	public function sync_overlay_route( $subtype, $canonical_id, $language ) {
		if ( ! $this->context->is_active() || ! class_exists( 'DSF_Translation_Overlays' ) ) {
			return null;
		}

		$subtype      = sanitize_key( $subtype );
		$canonical_id = absint( $canonical_id );
		$language     = DSF_Multilingual_Settings::normalize_locale_code( $language );
		$overlay_id   = DSF_Translation_Overlays::overlay_id( $canonical_id, $language );
		if ( ! $overlay_id || ! in_array( $subtype, DSF_Translation_Overlays::subtypes(), true ) ) {
			return null;
		}

		if ( $language === $this->context->get_main_language() ) {
			// The main language keeps the canonical, unprefixed catalog URL.
			$this->routes->delete_route( DSF_Translation_Overlays::KIND, $subtype, $overlay_id );
			return null;
		}

		$path = $this->canonical_catalog_path( $subtype, $canonical_id );
		if ( '' === $path ) {
			$this->routes->delete_route( DSF_Translation_Overlays::KIND, $subtype, $overlay_id );
			return null;
		}

		$stored = $this->routes->set_route( DSF_Translation_Overlays::KIND, $subtype, $overlay_id, $language, $path );
		if ( is_array( $stored ) ) {
			$this->permalink_cache = array();
		}
		return $stored;
	}

	/**
	 * Reduce a canonical catalog permalink to a stored route path.
	 *
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @return string
	 */
	private function canonical_catalog_path( $subtype, $canonical_id ) {
		$url = $this->canonical_catalog_url( $subtype, $canonical_id );
		if ( '' === $url ) {
			return '';
		}

		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home = is_string( $home ) ? $home : '/';
		if ( '/' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return DSF_Translation_Routes::normalize_path( $path );
	}

	/**
	 * Replace a translated page permalink with its prefixed route.
	 *
	 * @param string $link    Native permalink.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function filter_page_link( $link, $post_id = 0 ) {
		$post = get_post( absint( $post_id ) );
		return is_object( $post ) ? $this->translated_permalink( $link, $post ) : $link;
	}

	/**
	 * Replace a translated post or custom post type permalink.
	 *
	 * @param string      $link Native permalink.
	 * @param WP_Post|int $post Post object or ID.
	 * @return string
	 */
	public function filter_post_link( $link, $post = null ) {
		$post = is_object( $post ) ? $post : get_post( absint( $post ) );
		return is_object( $post ) ? $this->translated_permalink( $link, $post ) : $link;
	}

	/**
	 * Replace a translated term archive link.
	 *
	 * @param string $link     Native term link.
	 * @param object $term     Term object.
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	public function filter_term_link( $link, $term = null, $taxonomy = '' ) {
		if ( ! $this->context->is_active() || ! is_object( $term ) || empty( $term->term_id ) ) {
			return $link;
		}

		$taxonomy = sanitize_key( $taxonomy );
		if ( ! in_array( $taxonomy, DSF_Multilingual_Adapters::relationship_taxonomies(), true ) ) {
			return $this->catalog_permalink( $link, $taxonomy, absint( $term->term_id ) );
		}

		$route_url = $this->get_route_url( 'term', $taxonomy, absint( $term->term_id ) );
		return '' !== $route_url ? $route_url : $link;
	}

	/**
	 * Resolve the public URL for a post, preferring its stored route.
	 *
	 * Unpublished objects keep their native preview-capable URL.
	 *
	 * @param string  $link Native permalink.
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function translated_permalink( $link, $post ) {
		if ( ! $this->context->is_active() || empty( $post->post_type ) || 'publish' !== get_post_status( $post ) ) {
			return $link;
		}

		$post_type = sanitize_key( $post->post_type );
		if ( ! in_array( $post_type, DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return $this->catalog_permalink( $link, $post_type, absint( $post->ID ) );
		}

		$route_url = $this->get_route_url( 'post', $post_type, absint( $post->ID ) );
		return '' !== $route_url ? $route_url : $link;
	}

	/**
	 * Point a catalog link at the prefixed URL of the language being browsed.
	 *
	 * Translated pages are separate objects with their own permanent URLs; a
	 * product is one object, so which URL it has depends on the request.
	 *
	 * @param string $link         Canonical link.
	 * @param string $subtype      Catalog subtype.
	 * @param int    $canonical_id Canonical object ID.
	 * @return string
	 */
	private function catalog_permalink( $link, $subtype, $canonical_id ) {
		if ( ! class_exists( 'DSF_Translation_Overlays' ) || ! in_array( $subtype, DSF_Translation_Overlays::subtypes(), true ) ) {
			return $link;
		}

		$language = $this->context->get_request_language();
		if ( $language === $this->context->get_main_language() ) {
			return $link;
		}

		$overlay_id = DSF_Translation_Overlays::overlay_id( $canonical_id, $language );
		if ( ! $overlay_id ) {
			return $link;
		}

		$route_url = $this->get_route_url( DSF_Translation_Overlays::KIND, $subtype, $overlay_id );
		return '' !== $route_url ? $route_url : $link;
	}

	/**
	 * Record the slug an editor actually asked for.
	 *
	 * WordPress renames a translated slug when another language already uses it.
	 * The route map keeps the requested value so `/es/about/` stays reachable.
	 *
	 * @param string $slug          Deduplicated slug.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Parent ID.
	 * @param string $original_slug Requested slug.
	 * @return string
	 */
	public function capture_requested_slug( $slug, $post_id, $post_status, $post_type, $post_parent = 0, $original_slug = '' ) {
		unset( $post_status );
		if ( ! $this->context->is_active() ) {
			return $slug;
		}
		if ( ! in_array( sanitize_key( $post_type ), DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return $slug;
		}

		$requested = DSF_Translation_Routes::normalize_slug( $original_slug );
		if ( '' === $requested ) {
			return $slug;
		}
		$this->requested_slugs[ absint( $post_id ) ] = $requested;

		// WordPress appends -2 when another post already holds the slug. Two
		// languages are allowed to share one, so the suffix is dropped when the
		// only objects in the way belong to a different language.
		if ( $slug === $requested || ! $this->slug_is_free_in_language( $requested, $post_id, $post_type, $post_parent ) ) {
			return $slug;
		}

		return $requested;
	}

	/**
	 * Declare the language an about-to-be-inserted post will belong to.
	 *
	 * The relationship row is written after the insert, so the clone service
	 * states its intent up front; without it a new translation would be treated
	 * as main-language content while its slug is decided.
	 *
	 * @param string $language Target language, or an empty string to forget it.
	 */
	public function expect_language( $language ) {
		$language                = DSF_Multilingual_Settings::normalize_locale_code( $language );
		$this->expected_language = $this->context->is_enabled_language( $language ) ? $language : '';
	}

	/**
	 * Resolve the language an object belongs to, or will belong to.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $post_type  Post type.
	 * @param bool   $is_subject Whether this is the object currently being saved.
	 * @return string
	 */
	private function language_for_post( $post_id, $post_type, $is_subject = false ) {
		$member = $this->relationships->find_by_object( 'post', sanitize_key( $post_type ), absint( $post_id ) );
		if ( is_array( $member ) ) {
			return (string) $member['language'];
		}
		// The declared language applies only to the object being saved. Anything
		// else without a relationship row is existing main-language content.
		if ( $is_subject && '' !== $this->expected_language ) {
			return $this->expected_language;
		}
		return $this->context->get_main_language();
	}

	/**
	 * Whether a slug is unused by other objects in the same language.
	 *
	 * @param string $slug        Requested slug.
	 * @param int    $post_id     Post being saved.
	 * @param string $post_type   Post type.
	 * @param int    $post_parent Parent ID.
	 * @return bool
	 */
	private function slug_is_free_in_language( $slug, $post_id, $post_type, $post_parent ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$post_id   = absint( $post_id );
		$post_type = sanitize_key( $post_type );
		$language  = $this->language_for_post( $post_id, $post_type, true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted wpdb prefix.
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_parent = %d AND ID != %d AND post_status NOT IN ( 'trash', 'auto-draft' ) LIMIT %d",
			$slug,
			$post_type,
			absint( $post_parent ),
			$post_id,
			self::MAX_SLUG_CONFLICTS
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; slug decisions must see current rows.
		$conflicts = $wpdb->get_col( $sql );
		if ( empty( $conflicts ) ) {
			return true;
		}
		if ( count( $conflicts ) >= self::MAX_SLUG_CONFLICTS ) {
			// Too many to judge cheaply; leave WordPress's suffix in place.
			return false;
		}

		foreach ( $conflicts as $conflict_id ) {
			if ( $this->language_for_post( absint( $conflict_id ), $post_type ) === $language ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Keep a shared slug resolving to the language being browsed.
	 *
	 * Two languages may now hold the same slug, so an unprefixed request must
	 * not land on a translation just because its row came back first.
	 *
	 * @param mixed  $page      Short-circuit value.
	 * @param string $path      Requested path.
	 * @param string $post_type Post type or types.
	 * @return mixed
	 */
	public function filter_page_by_path( $page, $path, $post_type ) {
		if ( null !== $page || ! $this->context->is_active() ) {
			return $page;
		}

		$types = array_filter( array_map( 'sanitize_key', (array) $post_type ) );
		if ( empty( $types ) || array_diff( $types, DSF_Multilingual_Adapters::relationship_post_types() ) ) {
			return $page;
		}

		remove_filter( 'pre_get_page_by_path', array( $this, 'filter_page_by_path' ), 10 );
		$found = get_page_by_path( $path, OBJECT, $post_type );
		add_filter( 'pre_get_page_by_path', array( $this, 'filter_page_by_path' ), 10, 3 );

		if ( ! is_object( $found ) || empty( $found->ID ) ) {
			return $page;
		}

		$language = $this->context->get_request_language();
		if ( $this->language_for_post( absint( $found->ID ), sanitize_key( $found->post_type ) ) === $language ) {
			return $found;
		}

		// The match belongs to another language; use its sibling for this one.
		$member = $this->relationships->find_by_object( 'post', sanitize_key( $found->post_type ), absint( $found->ID ) );
		if ( is_array( $member ) ) {
			$sibling = $this->relationships->find_member( $member['group_uuid'], $language );
			if ( is_array( $sibling ) ) {
				$sibling_post = get_post( absint( $sibling['object_id'] ) );
				if ( is_object( $sibling_post ) ) {
					return $sibling_post;
				}
			}
		}

		return $found;
	}

	/**
	 * Keep a saved object's route in sync with its language and slug.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function handle_post_saved( $post_id, $post = null ) {
		$post = is_object( $post ) ? $post : get_post( absint( $post_id ) );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return;
		}
		$this->sync_post_route( absint( $post_id ), $post );
	}

	/**
	 * Store or remove one post's translated route.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return array|WP_Error|null Stored route, error, or null when not applicable.
	 */
	public function sync_post_route( $post_id, $post = null ) {
		if ( ! $this->context->is_active() ) {
			// A single-language site never has language routes to maintain.
			return null;
		}
		$post      = is_object( $post ) ? $post : get_post( absint( $post_id ) );
		$post_id   = absint( $post_id );
		$post_type = is_object( $post ) ? sanitize_key( $post->post_type ) : '';
		if ( '' === $post_type || ! in_array( $post_type, DSF_Multilingual_Adapters::relationship_post_types(), true ) ) {
			return null;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return null;
		}

		$member = $this->relationships->find_by_object( 'post', $post_type, $post_id );
		if ( ! is_array( $member ) || $member['language'] === $this->context->get_main_language() ) {
			// The main language keeps its native, unprefixed URL.
			$this->routes->delete_route( 'post', $post_type, $post_id );
			return null;
		}
		if ( ! $this->post_type_is_routable( $post_type ) ) {
			return null;
		}

		$path = $this->build_post_path( $post, $member['language'] );
		if ( $path instanceof WP_Error ) {
			$this->routes->delete_route( 'post', $post_type, $post_id );
			return $path;
		}

		$stored = $this->routes->set_route( 'post', $post_type, $post_id, $member['language'], $path );
		if ( is_array( $stored ) ) {
			$this->permalink_cache = array();
			$this->resync_descendants( $post, $member['language'] );
		}
		return $stored;
	}

	/**
	 * Rebuild descendant routes after a translated ancestor path changes.
	 *
	 * @param WP_Post $post     Ancestor post.
	 * @param string  $language Language identifier.
	 */
	private function resync_descendants( $post, $language ) {
		if ( ! is_post_type_hierarchical( $post->post_type ) ) {
			return;
		}
		$children = get_posts(
			array(
				'post_type'        => $post->post_type,
				'post_parent'      => absint( $post->ID ),
				'posts_per_page'   => 100,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		foreach ( (array) $children as $child_id ) {
			$child = get_post( absint( $child_id ) );
			if ( ! is_object( $child ) ) {
				continue;
			}
			$member = $this->relationships->find_by_object( 'post', sanitize_key( $child->post_type ), absint( $child_id ) );
			if ( is_array( $member ) && $member['language'] === $language ) {
				$this->sync_post_route( absint( $child_id ), $child );
			}
		}
	}

	/**
	 * Build the language-relative path for a translated post.
	 *
	 * Hierarchical types follow their translated ancestors. Flat types keep the
	 * site's permalink structure segments and translate only the terminal slug,
	 * which is the approved stable-archive-base policy.
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $language Language identifier.
	 * @return string|WP_Error
	 */
	private function build_post_path( $post, $language ) {
		$slug = $this->resolve_route_slug( $post );
		if ( '' === $slug ) {
			return new WP_Error( 'dsf_route_slug', __( 'This translation needs a valid slug before it can get a language URL.', 'designstudio-flow' ) );
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$ancestors = $this->ancestor_segments( $post, $language );
			if ( $ancestors instanceof WP_Error ) {
				return $ancestors;
			}
			$path = DSF_Translation_Routes::normalize_path( array_merge( $ancestors, array( $slug ) ) );
		} else {
			$path = DSF_Translation_Routes::normalize_path( array_merge( $this->structure_segments( $post ), array( $slug ) ) );
		}

		if ( '' === $path ) {
			return new WP_Error( 'dsf_route_path', __( 'A valid language URL could not be built for this translation.', 'designstudio-flow' ) );
		}
		return $path;
	}

	/**
	 * Collect translated ancestor path segments.
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $language Language identifier.
	 * @return string[]|WP_Error
	 */
	private function ancestor_segments( $post, $language ) {
		$segments = array();
		$parent   = absint( $post->post_parent );
		$guard    = 0;

		while ( $parent && $guard < DSF_Translation_Routes::MAX_SEGMENTS ) {
			++$guard;
			$parent_post = get_post( $parent );
			if ( ! is_object( $parent_post ) || empty( $parent_post->post_type ) ) {
				return new WP_Error( 'dsf_route_parent', __( 'A parent page in this translation is missing.', 'designstudio-flow' ) );
			}

			$member = $this->relationships->find_by_object( 'post', sanitize_key( $parent_post->post_type ), $parent );
			if ( ! is_array( $member ) || $member['language'] !== $language ) {
				return new WP_Error( 'dsf_route_parent_language', __( 'The parent page has not been translated into this language yet.', 'designstudio-flow' ) );
			}

			$route = $this->routes->get_route( 'post', sanitize_key( $parent_post->post_type ), $parent );
			$slug  = is_array( $route ) ? $route['slug'] : $this->resolve_route_slug( $parent_post );
			$slug  = DSF_Translation_Routes::normalize_slug( $slug );
			if ( '' === $slug ) {
				return new WP_Error( 'dsf_route_parent_slug', __( 'The parent page does not have a valid translated slug.', 'designstudio-flow' ) );
			}

			array_unshift( $segments, $slug );
			$parent = absint( $parent_post->post_parent );
		}

		return $segments;
	}

	/**
	 * Return the non-terminal permalink segments for a flat post type.
	 *
	 * @param WP_Post $post Post object.
	 * @return string[]
	 */
	private function structure_segments( $post ) {
		$structure = (string) get_option( 'permalink_structure', '' );
		if ( '' === $structure || false === strpos( $structure, '%postname%' ) ) {
			return array();
		}

		$timestamp = strtotime( (string) $post->post_date_gmt );
		if ( ! $timestamp ) {
			$timestamp = strtotime( (string) $post->post_date );
		}
		$timestamp = $timestamp ? $timestamp : time();

		$segments = array();
		foreach ( explode( '/', trim( $structure, '/' ) ) as $segment ) {
			if ( '' === $segment || '%postname%' === $segment ) {
				break;
			}
			switch ( $segment ) {
				case '%year%':
					$segments[] = gmdate( 'Y', $timestamp );
					break;
				case '%monthnum%':
					$segments[] = gmdate( 'm', $timestamp );
					break;
				case '%day%':
					$segments[] = gmdate( 'd', $timestamp );
					break;
				case '%hour%':
					$segments[] = gmdate( 'H', $timestamp );
					break;
				case '%minute%':
					$segments[] = gmdate( 'i', $timestamp );
					break;
				case '%second%':
					$segments[] = gmdate( 's', $timestamp );
					break;
				case '%post_id%':
					$segments[] = (string) absint( $post->ID );
					break;
				default:
					// Registered bases and unsupported tokens stay in the site's
					// language, which is the approved stable-base policy.
					$literal = DSF_Translation_Routes::normalize_slug( $segment );
					if ( '' !== $literal && false === strpos( $segment, '%' ) ) {
						$segments[] = $literal;
					}
					break;
			}
		}

		return $segments;
	}

	/**
	 * Resolve the slug a translated object should use in its own language.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function resolve_route_slug( $post ) {
		$post_id = absint( $post->ID );
		if ( isset( $this->requested_slugs[ $post_id ] ) ) {
			return $this->requested_slugs[ $post_id ];
		}
		return DSF_Translation_Routes::normalize_slug( $post->post_name );
	}

	/**
	 * Keep a saved term's route in sync.
	 *
	 * @param int    $term_id          Term ID.
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param string $taxonomy         Taxonomy name.
	 */
	public function handle_term_saved( $term_id, $term_taxonomy_id, $taxonomy ) {
		unset( $term_taxonomy_id );
		if ( ! $this->context->is_active() ) {
			return;
		}
		$taxonomy = sanitize_key( $taxonomy );
		$term     = get_term( absint( $term_id ), $taxonomy );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return;
		}

		$member = $this->relationships->find_by_object( 'term', $taxonomy, absint( $term_id ) );
		if ( ! is_array( $member ) || $member['language'] === $this->context->get_main_language() ) {
			$this->routes->delete_route( 'term', $taxonomy, absint( $term_id ) );
			return;
		}

		$slug = DSF_Translation_Routes::normalize_slug( $term->slug );
		if ( '' === $slug ) {
			return;
		}

		$segments = $this->taxonomy_base_segments( $taxonomy );
		$path     = DSF_Translation_Routes::normalize_path( array_merge( $segments, array( $slug ) ) );
		if ( '' === $path ) {
			return;
		}

		$this->routes->set_route( 'term', $taxonomy, absint( $term_id ), $member['language'], $path );
		$this->permalink_cache = array();
	}

	/**
	 * Return the registered, untranslated base segments of a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string[]
	 */
	private function taxonomy_base_segments( $taxonomy ) {
		$object = get_taxonomy( $taxonomy );
		$base   = '';
		if ( is_object( $object ) && ! empty( $object->rewrite['slug'] ) ) {
			$base = (string) $object->rewrite['slug'];
		}
		if ( '' === $base ) {
			return array();
		}

		$segments = array();
		foreach ( explode( '/', trim( $base, '/' ) ) as $segment ) {
			$segment = DSF_Translation_Routes::normalize_slug( $segment );
			if ( '' !== $segment ) {
				$segments[] = $segment;
			}
		}
		return $segments;
	}

	/**
	 * Remove a deleted post's route.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public function handle_post_deleted( $post_id, $post = null ) {
		// Deletion cleanup is deliberately unconditional so a disabled feature
		// cannot leave a stale row pointing at an object that no longer exists.
		$post = is_object( $post ) ? $post : get_post( absint( $post_id ) );
		if ( is_object( $post ) && ! empty( $post->post_type ) ) {
			$this->routes->delete_route( 'post', sanitize_key( $post->post_type ), absint( $post_id ) );
		}
	}

	/**
	 * Remove a deleted term's route.
	 *
	 * @param int    $term_id          Term ID.
	 * @param int    $term_taxonomy_id Term taxonomy ID.
	 * @param string $taxonomy         Taxonomy name.
	 * @param mixed  $deleted_term     Deleted term.
	 */
	public function handle_term_deleted( $term_id, $term_taxonomy_id, $taxonomy, $deleted_term = null ) {
		unset( $term_taxonomy_id, $deleted_term );
		$this->routes->delete_route( 'term', sanitize_key( $taxonomy ), absint( $term_id ) );
	}

	/**
	 * Record retired prefixes and drop routes for removed languages.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	public function handle_settings_updated( $old_value, $value ) {
		$this->context->flush();
		$old = DSF_Multilingual_Settings::sanitize_settings( is_array( $old_value ) ? $old_value : array() );
		$new = DSF_Multilingual_Settings::sanitize_settings( is_array( $value ) ? $value : array() );

		$old_prefixes = array();
		foreach ( $old['languages'] as $language ) {
			if ( '' !== $language['prefix'] ) {
				$old_prefixes[ $language['code'] ] = $language['prefix'];
			}
		}
		$new_prefixes = array();
		foreach ( $new['languages'] as $language ) {
			if ( '' !== $language['prefix'] ) {
				$new_prefixes[ $language['code'] ] = $language['prefix'];
			}
		}

		$history = self::get_prefix_history();
		foreach ( $old_prefixes as $code => $prefix ) {
			if ( isset( $new_prefixes[ $code ] ) && $new_prefixes[ $code ] !== $prefix ) {
				$history[ $prefix ] = $code;
			}
		}
		foreach ( $new_prefixes as $prefix ) {
			unset( $history[ $prefix ] );
		}
		self::store_prefix_history( $history );

		foreach ( array_diff( array_keys( $old_prefixes ), array_keys( $new_prefixes ) ) as $removed ) {
			// A disabled language keeps its relationships for recovery, but it must
			// not keep answering on a public URL.
			$this->routes->delete_language_routes( $removed );
		}
	}

	/**
	 * Read the bounded retired-prefix map.
	 *
	 * @return array<string,string>
	 */
	public static function get_prefix_history() {
		$stored  = get_option( self::PREFIX_HISTORY_OPTION, array() );
		$history = array();
		foreach ( is_array( $stored ) ? $stored : array() as $prefix => $code ) {
			$prefix = DSF_Multilingual_Settings::sanitize_prefix( $prefix );
			$code   = DSF_Multilingual_Settings::normalize_locale_code( $code );
			if ( '' !== $prefix && '' !== $code ) {
				$history[ $prefix ] = $code;
			}
			if ( count( $history ) >= self::MAX_PREFIX_HISTORY ) {
				break;
			}
		}
		return $history;
	}

	/**
	 * Persist the bounded retired-prefix map.
	 *
	 * @param array $history Prefix to language map.
	 */
	private static function store_prefix_history( $history ) {
		$clean = array();
		foreach ( is_array( $history ) ? $history : array() as $prefix => $code ) {
			$prefix = DSF_Multilingual_Settings::sanitize_prefix( $prefix );
			$code   = DSF_Multilingual_Settings::normalize_locale_code( $code );
			if ( '' !== $prefix && '' !== $code ) {
				$clean[ $prefix ] = $code;
			}
			if ( count( $clean ) >= self::MAX_PREFIX_HISTORY ) {
				break;
			}
		}
		update_option( self::PREFIX_HISTORY_OPTION, $clean, false );
	}

	/**
	 * Build query variables for a resolved route.
	 *
	 * @param array $route Stored route row.
	 * @param array $parts Trailing pagination/feed parts.
	 * @return array<string,mixed> Empty when the object cannot be shown.
	 */
	private function query_vars_for_route( $route, $parts ) {
		$query_vars = array();

		if ( 'post' === $route['object_kind'] ) {
			$post = get_post( $route['object_id'] );
			if ( ! is_object( $post ) || sanitize_key( $post->post_type ) !== $route['object_subtype'] ) {
				return array();
			}
			if ( 'publish' !== $post->post_status && ! $this->can_preview( $post ) ) {
				return array();
			}

			if ( 'page' === $post->post_type ) {
				$query_vars['page_id'] = absint( $post->ID );
			} else {
				$query_vars['p']         = absint( $post->ID );
				$query_vars['post_type'] = sanitize_key( $post->post_type );
			}
			if ( $parts['paged'] ) {
				$query_vars['page'] = $parts['paged'];
			}
		} elseif ( 'term' === $route['object_kind'] ) {
			$term = get_term( $route['object_id'], $route['object_subtype'] );
			if ( ! is_object( $term ) || is_wp_error( $term ) ) {
				return array();
			}

			if ( 'category' === $route['object_subtype'] ) {
				$query_vars['cat'] = absint( $term->term_id );
			} elseif ( 'post_tag' === $route['object_subtype'] ) {
				$query_vars['tag_id'] = absint( $term->term_id );
			} else {
				$query_vars['taxonomy'] = sanitize_key( $route['object_subtype'] );
				$query_vars['term']     = (string) $term->slug;
			}
			if ( $parts['paged'] ) {
				$query_vars['paged'] = $parts['paged'];
			}
		} elseif ( class_exists( 'DSF_Translation_Overlays' ) && DSF_Translation_Overlays::KIND === $route['object_kind'] ) {
			// A catalog overlay has no object of its own: the prefixed URL renders
			// the canonical product or term with translated display text.
			$identity = DSF_Translation_Overlays::decode( $route['object_id'] );
			if ( ! $identity['canonical_id'] || $identity['language'] !== $route['language'] ) {
				return array();
			}

			if ( DSF_Translation_Overlays::is_term_subtype( $route['object_subtype'] ) ) {
				$term = get_term( $identity['canonical_id'], $route['object_subtype'] );
				if ( ! is_object( $term ) || is_wp_error( $term ) ) {
					return array();
				}
				$query_vars['taxonomy'] = sanitize_key( $route['object_subtype'] );
				$query_vars['term']     = (string) $term->slug;
				if ( $parts['paged'] ) {
					$query_vars['paged'] = $parts['paged'];
				}
			} else {
				$product = get_post( $identity['canonical_id'] );
				if ( ! is_object( $product ) || sanitize_key( $product->post_type ) !== $route['object_subtype'] || 'publish' !== $product->post_status ) {
					return array();
				}
				$query_vars['p']         = absint( $product->ID );
				$query_vars['post_type'] = sanitize_key( $product->post_type );
				if ( $parts['paged'] ) {
					$query_vars['page'] = $parts['paged'];
				}
			}
		} else {
			return array();
		}

		if ( '' !== $parts['feed'] ) {
			$query_vars['feed'] = $parts['feed'];
		}

		return $query_vars;
	}

	/**
	 * Whether the current user may see an unpublished translation.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private function can_preview( $post ) {
		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return false;
		}
		return is_user_logged_in() && current_user_can( 'edit_post', absint( $post->ID ) );
	}

	/**
	 * Whether a member currently resolves to a public URL.
	 *
	 * @param array $member Relationship member.
	 * @return bool
	 */
	private function is_publicly_viewable_member( $member ) {
		if ( 'post' !== ( $member['object_kind'] ?? '' ) ) {
			return false;
		}
		return 'publish' === get_post_status( absint( $member['object_id'] ) );
	}

	/**
	 * Find the sibling object of one group in another language.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Source object ID.
	 * @param string $language       Target language.
	 * @return array|null
	 */
	private function find_sibling( $object_kind, $object_subtype, $object_id, $language ) {
		$member = $this->relationships->find_by_object( $object_kind, sanitize_key( $object_subtype ), absint( $object_id ) );
		if ( ! is_array( $member ) ) {
			return null;
		}
		$sibling = $this->relationships->find_member( $member['group_uuid'], $language );
		return is_array( $sibling ) ? $sibling : null;
	}

	/**
	 * Whether a post type produces public URLs at all.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	private function post_type_is_routable( $post_type ) {
		$object = get_post_type_object( $post_type );
		if ( ! is_object( $object ) ) {
			return false;
		}
		return ! empty( $object->public ) && ! empty( $object->publicly_queryable );
	}

	/**
	 * Return the requested path relative to the site root.
	 *
	 * @param WP $wp Current environment.
	 * @return string
	 */
	private function request_path( $wp ) {
		$request = isset( $wp->request ) ? (string) $wp->request : '';
		if ( '' === $request && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			$request = is_string( $request ) ? $request : '';
			$home    = wp_parse_url( home_url(), PHP_URL_PATH );
			if ( is_string( $home ) && '' !== trim( $home, '/' ) && 0 === strpos( $request, $home ) ) {
				$request = substr( $request, strlen( $home ) );
			}
		}
		return trim( (string) $request, '/' );
	}

	/**
	 * Split pagination and feed suffixes off a request path.
	 *
	 * @param string $path Request path inside the language.
	 * @return array{path:string,paged:int,feed:string}
	 */
	private function split_trailing_request_parts( $path ) {
		$segments = array_values( array_filter( explode( '/', (string) $path ), 'strlen' ) );
		$paged    = 0;
		$feed     = '';

		$feed_types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		$count      = count( $segments );
		if ( 1 < $count && 'feed' === $segments[ $count - 2 ] && in_array( $segments[ $count - 1 ], $feed_types, true ) ) {
			$feed = array_pop( $segments );
			array_pop( $segments );
		} elseif ( 0 < $count && 'feed' === $segments[ $count - 1 ] ) {
			array_pop( $segments );
			$feed = 'feed';
		}

		$count = count( $segments );
		if ( 1 < $count && 'page' === $segments[ $count - 2 ] && ctype_digit( $segments[ $count - 1 ] ) ) {
			$paged = max( 1, min( 100000, (int) array_pop( $segments ) ) );
			array_pop( $segments );
		}

		return array(
			'path'  => implode( '/', $segments ),
			'paged' => $paged,
			'feed'  => $feed,
		);
	}

	/**
	 * Build the main-language URL used by the visible-redirect policy.
	 *
	 * @param string $path Unmatched secondary path.
	 * @return string
	 */
	private function main_language_url_for_path( $path ) {
		unset( $path );
		return home_url( '/' );
	}

	/**
	 * Perform a bounded redirect and stop the request.
	 *
	 * @param string $url    Target URL.
	 * @param int    $status HTTP status.
	 */
	private function redirect( $url, $status ) {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return;
		}
		/**
		 * Filters whether the routing layer may redirect this request.
		 *
		 * @param bool   $allowed Whether to redirect.
		 * @param string $url     Target URL.
		 * @param int    $status  HTTP status.
		 */
		if ( ! apply_filters( 'dsf_multilingual_allow_redirect', true, $url, $status ) ) {
			return;
		}
		wp_safe_redirect( $url, 301 === $status ? 301 : 302 );
		exit;
	}
}
