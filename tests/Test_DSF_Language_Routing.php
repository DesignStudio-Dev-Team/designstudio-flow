<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-relationships.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-routes.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-context.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-language-routing.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlays.php';

/** Minimal environment double standing in for the global WP object. */
class DSF_Routing_Test_WP {
	public $request       = '';
	public $query_vars    = array();
	public $matched_rule  = 'seeded';
	public $matched_query = 'seeded';

	public function __construct( $request = '', $query_vars = array() ) {
		$this->request    = $request;
		$this->query_vars = $query_vars;
	}
}

/** In-memory route map with the same contract as the indexed service. */
class DSF_Routing_Test_Routes {
	public $rows    = array();
	public $deleted = array();

	public function add( $kind, $subtype, $id, $language, $path ) {
		$this->rows[] = array(
			'id'             => count( $this->rows ) + 1,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'object_id'      => $id,
			'language'       => $language,
			'slug'           => substr( strrchr( '/' . $path, '/' ), 1 ),
			'path'           => $path,
			'path_hash'      => DSF_Translation_Routes::path_hash( $language, $path ),
			'updated_at_gmt' => '2026-07-28 10:00:00',
		);
	}

	public function find_by_path( $language, $path ) {
		$path = DSF_Translation_Routes::normalize_path( $path );
		foreach ( $this->rows as $row ) {
			if ( $row['language'] === $language && $row['path'] === $path ) {
				return $row;
			}
		}
		return null;
	}

	public function get_route( $kind, $subtype, $id ) {
		foreach ( $this->rows as $row ) {
			if ( $row['object_kind'] === $kind && $row['object_subtype'] === $subtype && (int) $row['object_id'] === (int) $id ) {
				return $row;
			}
		}
		return null;
	}

	public function set_route( $kind, $subtype, $id, $language, $path ) {
		$this->add( $kind, $subtype, $id, $language, $path );
		return $this->get_route( $kind, $subtype, $id );
	}

	public function delete_route( $kind, $subtype, $id ) {
		$this->deleted[] = $kind . ':' . $subtype . ':' . $id;
		foreach ( $this->rows as $index => $row ) {
			if ( $row['object_kind'] === $kind && $row['object_subtype'] === $subtype && (int) $row['object_id'] === (int) $id ) {
				unset( $this->rows[ $index ] );
				$this->rows = array_values( $this->rows );
				return true;
			}
		}
		return false;
	}

	public function delete_language_routes( $language ) {
		$deleted = 0;
		foreach ( $this->rows as $index => $row ) {
			if ( $row['language'] === $language ) {
				unset( $this->rows[ $index ] );
				++$deleted;
			}
		}
		$this->rows = array_values( $this->rows );
		return $deleted;
	}
}

/** In-memory relationship reader. */
class DSF_Routing_Test_Relationships {
	public $members = array();

	public function add( $group, $kind, $subtype, $id, $language ) {
		$this->members[] = array(
			'group_uuid'     => $group,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'object_id'      => $id,
			'language'       => $language,
		);
	}

	public function find_by_object( $kind, $subtype, $id ) {
		foreach ( $this->members as $member ) {
			if ( $member['object_kind'] === $kind && $member['object_subtype'] === $subtype && (int) $member['object_id'] === (int) $id ) {
				return $member;
			}
		}
		return null;
	}

	public function find_member( $group, $language ) {
		foreach ( $this->members as $member ) {
			if ( $member['group_uuid'] === $group && $member['language'] === $language ) {
				return $member;
			}
		}
		return null;
	}

	public function list_group( $group ) {
		$found = array();
		foreach ( $this->members as $member ) {
			if ( $member['group_uuid'] === $group ) {
				$found[] = $member;
			}
		}
		return $found;
	}
}

/**
 * Covers prefix resolution, route lookup, canonical behavior, and the
 * language-scoped listing query.
 */
class Test_DSF_Language_Routing extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	/** @var DSF_Routing_Test_Routes */
	private $routes;

	/** @var DSF_Routing_Test_Relationships */
	private $relationships;

	/** @var DSF_Language_Context */
	private $context;

	/** @var DSF_Language_Routing */
	private $routing;

	/** @var array<int,array<string,mixed>> */
	private $posts = array();

	/** @var array<string,mixed> */
	private $options = array();

	/** @var mixed Global wpdb captured before the listing-query test replaces it. */
	private $previous_wpdb = null;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->routes        = new DSF_Routing_Test_Routes();
		$this->relationships = new DSF_Routing_Test_Relationships();
		$this->posts         = array(
			12 => (object) array(
				'ID'          => 12,
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'acerca-de',
				'post_parent' => 0,
			),
			13 => (object) array(
				'ID'          => 13,
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_name'   => 'borrador',
				'post_parent' => 0,
			),
			20 => (object) array(
				'ID'          => 20,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_name'   => 'noticia',
				'post_parent' => 0,
			),
		);

		$posts = &$this->posts;
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => static function ( $id = 0 ) use ( &$posts ) {
					$id = is_object( $id ) ? $id->ID : (int) $id;
					return isset( $posts[ $id ] ) ? $posts[ $id ] : null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_status',
			array(
				'return' => static function ( $id = 0 ) use ( &$posts ) {
					$id = is_object( $id ) ? $id->ID : (int) $id;
					return isset( $posts[ $id ] ) ? $posts[ $id ]->post_status : false;
				},
			)
		);
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
			)
		);
		$options = &$this->options;
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $name, $default = false ) use ( &$options ) {
					return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
				},
			)
		);
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_rtl', array( 'return' => false ) );
		WP_Mock::userFunction( 'apply_filters', array( 'return_arg' => 1 ) );
		WP_Mock::userFunction(
			'home_url',
			array(
				'return' => static function ( $path = '' ) {
					return 'https://example.test' . ( '' === $path ? '/' : $path );
				},
			)
		);
		WP_Mock::userFunction( 'user_trailingslashit', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'trailingslashit',
			array(
				'return' => static function ( $value ) {
					return rtrim( (string) $value, '/' ) . '/';
				},
			)
		);
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );

		$this->context = $this->build_context();
		$this->routing = new DSF_Language_Routing(
			array(
				'routes'        => $this->routes,
				'relationships' => $this->relationships,
				'context'       => $this->context,
			)
		);
	}

	public function tearDown(): void {
		if ( null === $this->previous_wpdb ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Build a context with fixed settings.
	 *
	 * @param array $overrides Settings overrides.
	 * @return DSF_Language_Context
	 */
	private function build_context( $overrides = array() ) {
		$settings = DSF_Multilingual_Settings::sanitize_settings(
			array_merge(
				array(
					'enabled'           => true,
					'main_language'     => 'en-US',
					'migration_state'   => 'complete',
					'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
					'languages'         => array(
						array( 'code' => 'en-US' ),
						array(
							'code'   => 'es-MX',
							'prefix' => 'es',
						),
						array(
							'code'   => 'fr-FR',
							'prefix' => 'fr',
						),
					),
				),
				$overrides
			)
		);

		return new DSF_Language_Context(
			array(
				'settings_reader'   => static function () use ( $settings ) {
					return $settings;
				},
				'conflict_detector' => static function () {
					return false;
				},
			)
		);
	}

	public function test_main_language_requests_are_left_to_wordpress() {
		$wp = new DSF_Routing_Test_WP( 'about', array( 'pagename' => 'about' ) );

		$this->routing->resolve_request( $wp );

		$this->assertSame( array( 'pagename' => 'about' ), $wp->query_vars );
		$this->assertFalse( $this->routing->is_route_request() );
		$this->assertSame( 'en-US', $this->context->get_request_language() );
	}

	public function test_prefixed_path_resolves_through_the_route_map() {
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$wp = new DSF_Routing_Test_WP( 'es/acerca-de', array( 'pagename' => 'es/acerca-de' ) );

		$this->routing->resolve_request( $wp );

		$this->assertSame( array( 'page_id' => 12 ), $wp->query_vars );
		$this->assertTrue( $this->routing->is_route_request() );
		$this->assertSame( 'es-MX', $this->context->get_request_language() );
		$this->assertSame( 'acerca-de', $this->routing->get_resolved_route()['path'] );
	}

	public function test_identical_slugs_resolve_to_different_objects_per_language() {
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'about' );
		$this->routes->add( 'post', 'post', 20, 'fr-FR', 'about' );

		$spanish = new DSF_Routing_Test_WP( 'es/about' );
		$this->routing->resolve_request( $spanish );
		$this->assertSame( array( 'page_id' => 12 ), $spanish->query_vars );

		$french  = new DSF_Routing_Test_WP( 'fr/about' );
		$routing = new DSF_Language_Routing(
			array(
				'routes'        => $this->routes,
				'relationships' => $this->relationships,
				'context'       => $this->build_context(),
			)
		);
		$routing->resolve_request( $french );
		$this->assertSame(
			array(
				'p'         => 20,
				'post_type' => 'post',
			),
			$french->query_vars
		);
	}

	public function test_unmatched_secondary_path_returns_a_language_specific_404() {
		$wp = new DSF_Routing_Test_WP( 'es/no-existe', array( 'pagename' => 'es/no-existe' ) );

		$this->routing->resolve_request( $wp );

		$this->assertSame( array( 'error' => '404' ), $wp->query_vars );
		$this->assertSame( 'es-MX', $this->context->get_request_language() );
	}

	public function test_unpublished_translation_is_not_served_to_anonymous_visitors() {
		$this->routes->add( 'post', 'page', 13, 'es-MX', 'borrador' );
		WP_Mock::userFunction( 'is_user_logged_in', array( 'return' => false ) );
		$wp = new DSF_Routing_Test_WP( 'es/borrador' );

		$this->routing->resolve_request( $wp );

		$this->assertSame( array( 'error' => '404' ), $wp->query_vars );
	}

	public function test_pagination_and_feed_suffixes_survive_route_resolution() {
		$this->routes->add( 'term', 'category', 7, 'es-MX', 'categoria/noticias' );
		WP_Mock::userFunction(
			'get_term',
			array(
				'return' => (object) array(
					'term_id'  => 7,
					'slug'     => 'noticias',
					'taxonomy' => 'category',
				),
			)
		);
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );

		$paged = new DSF_Routing_Test_WP( 'es/categoria/noticias/page/3' );
		$this->routing->resolve_request( $paged );
		$this->assertSame(
			array(
				'cat'   => 7,
				'paged' => 3,
			),
			$paged->query_vars
		);

		$feed = new DSF_Routing_Test_WP( 'es/categoria/noticias/feed' );
		$this->routing->resolve_request( $feed );
		$this->assertSame(
			array(
				'cat'  => 7,
				'feed' => 'feed',
			),
			$feed->query_vars
		);
	}

	public function test_bare_prefix_resolves_the_translated_front_page() {
		$this->options['show_on_front'] = 'page';
		$this->options['page_on_front'] = 5;
		WP_Mock::userFunction( 'get_post_type', array( 'return' => 'page' ) );
		$this->relationships->add( self::GROUP, 'post', 'page', 5, 'en-US' );
		$this->relationships->add( self::GROUP, 'post', 'page', 12, 'es-MX' );

		$wp = new DSF_Routing_Test_WP( 'es' );
		$this->routing->resolve_request( $wp );

		$this->assertSame( array( 'page_id' => 12 ), $wp->query_vars );
		$this->assertTrue( $this->routing->is_route_request() );
	}

	public function test_retired_prefix_redirects_permanently_to_the_current_one() {
		$redirects = array();
		$this->options[ DSF_Language_Routing::PREFIX_HISTORY_OPTION ] = array( 'esp' => 'es-MX' );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'return' => static function ( $url, $status ) use ( &$redirects ) {
					$redirects[] = array( $url, $status );
					throw new RuntimeException( 'redirected' );
				},
			)
		);

		$wp = new DSF_Routing_Test_WP( 'esp/acerca-de' );

		try {
			$this->routing->resolve_request( $wp );
			$this->fail( 'A retired prefix must redirect.' );
		} catch ( RuntimeException $exception ) {
			unset( $exception );
		}

		$this->assertSame( array( array( 'https://example.test/es/acerca-de', 301 ) ), $redirects );
	}

	public function test_inactive_feature_never_touches_the_request() {
		$routing = new DSF_Language_Routing(
			array(
				'routes'        => $this->routes,
				'relationships' => $this->relationships,
				'context'       => $this->build_context( array( 'enabled' => false ) ),
			)
		);
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$wp = new DSF_Routing_Test_WP( 'es/acerca-de', array( 'pagename' => 'es/acerca-de' ) );

		$routing->resolve_request( $wp );

		$this->assertSame( array( 'pagename' => 'es/acerca-de' ), $wp->query_vars );
		$this->assertFalse( $routing->is_route_request() );
	}

	public function test_permalinks_use_stored_routes_only_for_published_translations() {
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$this->routes->add( 'post', 'page', 13, 'es-MX', 'borrador' );

		$this->assertSame(
			'https://example.test/es/acerca-de',
			$this->routing->filter_page_link( 'https://example.test/acerca-de-2/', 12 )
		);
		$this->assertSame(
			'https://example.test/?p=13',
			$this->routing->filter_page_link( 'https://example.test/?p=13', 13 )
		);
		$this->assertSame(
			'https://example.test/noticia/',
			$this->routing->filter_post_link( 'https://example.test/noticia/', $this->posts[20] )
		);
	}

	public function test_canonical_redirects_are_suppressed_only_on_resolved_routes() {
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'acerca-de' );

		$this->assertSame( 'https://example.test/x/', $this->routing->filter_redirect_canonical( 'https://example.test/x/' ) );

		$this->routing->resolve_request( new DSF_Routing_Test_WP( 'es/acerca-de' ) );
		$this->assertFalse( $this->routing->filter_redirect_canonical( 'https://example.test/x/' ) );
	}

	public function test_translation_links_expose_published_siblings_only() {
		$this->relationships->add( self::GROUP, 'post', 'page', 20, 'en-US' );
		$this->relationships->add( self::GROUP, 'post', 'page', 12, 'es-MX' );
		$this->relationships->add( self::GROUP, 'post', 'page', 13, 'fr-FR' );
		WP_Mock::userFunction(
			'get_permalink',
			array(
				'return' => static function ( $id ) {
					return 12 === (int) $id ? 'https://example.test/es/acerca-de' : 'https://example.test/about/';
				},
			)
		);

		$links = $this->routing->get_translation_links( 'post', 'page', 20 );

		$this->assertSame( array( 'en-US', 'es-MX' ), array_keys( $links ) );
		$this->assertSame( 'https://example.test/es/acerca-de', $links['es-MX']['url'] );
		$this->assertSame( 'Español (México)', $links['es-MX']['label'] );
		$this->assertArrayNotHasKey( 'fr-FR', $links, 'Draft translations must never appear publicly.' );
	}

	public function test_a_prefix_cannot_shadow_existing_content_or_registered_bases() {
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'get_page_by_path',
			array(
				'return' => static function ( $path ) {
					return 'es' === $path ? (object) array( 'ID' => 3 ) : null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_types',
			array(
				'return' => array(
					(object) array( 'rewrite' => array( 'slug' => 'store/product' ) ),
				),
			)
		);
		WP_Mock::userFunction(
			'get_taxonomies',
			array(
				'return' => array(
					(object) array( 'rewrite' => array( 'slug' => 'temas' ) ),
				),
			)
		);
		if ( ! defined( 'OBJECT' ) ) {
			define( 'OBJECT', 'OBJECT' );
		}

		$this->assertNotSame( '', DSF_Language_Routing::describe_prefix_collision( 'es' ) );
		$this->assertNotSame( '', DSF_Language_Routing::describe_prefix_collision( 'store' ) );
		$this->assertNotSame( '', DSF_Language_Routing::describe_prefix_collision( 'temas' ) );
		$this->assertNotSame( '', DSF_Language_Routing::describe_prefix_collision( 'wp-json' ) );
		$this->assertNotSame( '', DSF_Language_Routing::describe_prefix_collision( '../etc' ) );
		$this->assertSame( '', DSF_Language_Routing::describe_prefix_collision( 'fr' ) );
	}

	public function test_changing_a_prefix_records_a_redirect_and_removing_a_language_drops_routes() {
		$stored = array();
		WP_Mock::userFunction(
			'update_option',
			array(
				'return' => static function ( $name, $value ) use ( &$stored ) {
					$stored[ $name ] = $value;
					return true;
				},
			)
		);
		$this->routes->add( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$this->routes->add( 'post', 'page', 30, 'fr-FR', 'a-propos' );

		$before = array(
			'enabled'       => true,
			'main_language' => 'en-US',
			'languages'     => array(
				array(
					'code'   => 'en-US',
					'prefix' => '',
				),
				array(
					'code'   => 'es-MX',
					'prefix' => 'esp',
				),
				array(
					'code'   => 'fr-FR',
					'prefix' => 'fr',
				),
			),
		);
		$after  = array(
			'enabled'       => true,
			'main_language' => 'en-US',
			'languages'     => array(
				array(
					'code'   => 'en-US',
					'prefix' => '',
				),
				array(
					'code'   => 'es-MX',
					'prefix' => 'es',
				),
			),
		);

		$this->routing->handle_settings_updated( $before, $after );

		$this->assertSame(
			array( 'esp' => 'es-MX' ),
			$stored[ DSF_Language_Routing::PREFIX_HISTORY_OPTION ],
			'The retired prefix must keep redirecting to the new one.'
		);
		$this->assertNotNull( $this->routes->find_by_path( 'es-MX', 'acerca-de' ) );
		$this->assertNull(
			$this->routes->find_by_path( 'fr-FR', 'a-propos' ),
			'A removed language must stop answering on a public URL.'
		);
	}

	public function test_listing_queries_are_restricted_to_the_request_language() {
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb']     = new DSF_Routing_Query_Test_DB();
		$query               = new DSF_Routing_Test_Query();

		$main = $this->routing->filter_query_language( ' AND 1=1', $query );
		$this->assertStringContainsString( "language = 'en-US'", $main );
		$this->assertStringContainsString( 'NOT EXISTS', $main, 'Unmigrated content must keep its main-language URL.' );

		$this->context->set_request_language( 'es-MX' );
		$secondary = $this->routing->filter_query_language( ' AND 1=1', $query );
		$this->assertStringContainsString( "language = 'es-MX'", $secondary );
		$this->assertStringNotContainsString( 'NOT EXISTS', $secondary );

		$query->singular = true;
		$this->assertSame( ' AND 1=1', $this->routing->filter_query_language( ' AND 1=1', $query ) );
	}
	/**
	 * Seed a published product and its Spanish catalog overlay route.
	 *
	 * @return int The encoded overlay identity.
	 */
	private function seed_catalog_overlay() {
		$this->posts[4321] = (object) array(
			'ID'          => 4321,
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_name'   => 'trail-runner',
			'post_parent' => 0,
		);

		$overlay_id = DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' );
		$this->routes->add( DSF_Translation_Overlays::KIND, 'product', $overlay_id, 'es-MX', 'product/trail-runner' );

		return $overlay_id;
	}

	public function test_a_prefixed_catalog_url_renders_the_canonical_product() {
		$this->seed_catalog_overlay();

		$wp = new DSF_Routing_Test_WP( 'es/product/trail-runner' );
		$this->routing->resolve_request( $wp );

		$this->assertSame(
			array(
				'p'         => 4321,
				'post_type' => 'product',
			),
			$wp->query_vars,
			'A translated catalog URL resolves to the one canonical product, never a copy.'
		);
		$this->assertTrue( $this->routing->is_route_request() );
	}

	public function test_an_unpublished_product_has_no_translated_url() {
		$this->seed_catalog_overlay();
		$this->posts[4321]->post_status = 'draft';

		$wp = new DSF_Routing_Test_WP( 'es/product/trail-runner' );
		$this->routing->resolve_request( $wp );

		$this->assertArrayNotHasKey( 'p', $wp->query_vars );
	}

	public function test_a_catalog_route_whose_language_was_tampered_with_is_refused() {
		$this->posts[4321] = (object) array(
			'ID'          => 4321,
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_name'   => 'trail-runner',
			'post_parent' => 0,
		);

		// The stored row claims Spanish, but the identity encodes French.
		$mismatched = DSF_Translation_Overlays::overlay_id( 4321, 'fr' );
		$this->routes->add( DSF_Translation_Overlays::KIND, 'product', $mismatched, 'es-MX', 'product/trail-runner' );

		$wp = new DSF_Routing_Test_WP( 'es/product/trail-runner' );
		$this->routing->resolve_request( $wp );

		$this->assertArrayNotHasKey( 'p', $wp->query_vars );
	}

	public function test_a_catalog_link_follows_the_language_being_browsed() {
		$overlay_id = $this->seed_catalog_overlay();
		unset( $overlay_id );

		$this->context->set_request_language( 'es-MX' );
		$this->assertSame(
			'https://example.test/es/product/trail-runner',
			$this->routing->filter_post_link( 'https://example.test/product/trail-runner/', $this->posts[4321] )
		);

		// The main language keeps the canonical, unprefixed catalog URL.
		$this->context->set_request_language( 'en-US' );
		$this->assertSame(
			'https://example.test/product/trail-runner/',
			$this->routing->filter_post_link( 'https://example.test/product/trail-runner/', $this->posts[4321] )
		);
	}

	/**
	 * Install a wpdb double that reports the given slug conflicts.
	 *
	 * @param array $conflicts Conflicting post IDs.
	 * @return DSF_Routing_Query_Test_DB
	 */
	private function use_slug_database( $conflicts = array() ) {
		$db                  = new DSF_Routing_Query_Test_DB();
		$db->conflicts       = $conflicts;
		$this->previous_wpdb = array_key_exists( 'wpdb', $GLOBALS ) ? $GLOBALS['wpdb'] : null;
		$GLOBALS['wpdb']     = $db;
		return $db;
	}

	public function test_a_translation_keeps_the_slug_wordpress_would_have_deduplicated() {
		// Post 5 is the English page holding the slug; this insert is Spanish.
		$this->relationships->add( self::GROUP, 'post', 'page', 5, 'en-US' );
		$this->use_slug_database( array( 5 ) );
		$this->routing->expect_language( 'es-MX' );

		$slug = $this->routing->capture_requested_slug( 'about-2', 99, 'draft', 'page', 0, 'about' );

		$this->assertSame( 'about', $slug, 'Two languages may share one slug.' );
	}

	public function test_a_slug_already_used_in_the_same_language_keeps_its_suffix() {
		// Post 12 is already Spanish, and so is this insert.
		$this->relationships->add( self::GROUP, 'post', 'page', 12, 'es-MX' );
		$this->use_slug_database( array( 12 ) );
		$this->routing->expect_language( 'es-MX' );

		$slug = $this->routing->capture_requested_slug( 'acerca-de-2', 99, 'draft', 'page', 0, 'acerca-de' );

		$this->assertSame( 'acerca-de-2', $slug, 'One language cannot hold the same slug twice.' );
	}

	public function test_an_unassigned_conflict_counts_as_main_language_content() {
		// Post 4321 has no relationship row, so it is existing main-language
		// content and must not be mistaken for the language being inserted.
		$this->use_slug_database( array( 4321 ) );
		$this->routing->expect_language( 'es-MX' );

		$this->assertSame(
			'about',
			$this->routing->capture_requested_slug( 'about-2', 99, 'draft', 'page', 0, 'about' )
		);

		$this->routing->expect_language( '' );
		$this->assertSame(
			'about-2',
			$this->routing->capture_requested_slug( 'about-2', 98, 'draft', 'page', 0, 'about' ),
			'Two main-language pages still cannot share a slug.'
		);
	}

	public function test_an_unjudgeable_number_of_conflicts_keeps_the_wordpress_suffix() {
		$this->use_slug_database( range( 1, DSF_Language_Routing::MAX_SLUG_CONFLICTS ) );
		$this->routing->expect_language( 'es-MX' );

		$this->assertSame(
			'about-2',
			$this->routing->capture_requested_slug( 'about-2', 99, 'draft', 'page', 0, 'about' )
		);
	}

	public function test_an_untouched_slug_is_returned_unchanged() {
		$this->use_slug_database( array() );

		$this->assertSame(
			'acerca-de',
			$this->routing->capture_requested_slug( 'acerca-de', 99, 'draft', 'page', 0, 'acerca-de' )
		);
		$this->assertSame(
			'whatever-2',
			$this->routing->capture_requested_slug( 'whatever-2', 99, 'draft', 'dsf_entry', 0, 'whatever' ),
			'Object types that are not translated keep WordPress behaviour.'
		);
	}
}

/** Query double exposing only the flags the language filter reads. */
class DSF_Routing_Test_Query {
	public $singular = false;

	public function is_main_query() {
		return true;
	}

	public function is_singular() {
		return $this->singular;
	}

	public function is_home() {
		return true;
	}

	public function is_archive() {
		return false;
	}

	public function is_search() {
		return false;
	}

	public function is_feed() {
		return false;
	}

	public function get( $key ) {
		unset( $key );
		return '';
	}
}

/** wpdb double used by the listing-query filter. */
class DSF_Routing_Query_Test_DB {
	public $prefix    = 'wp_';
	public $posts     = 'wp_posts';
	public $conflicts = array();

	public function get_col( $sql ) {
		unset( $sql );
		return $this->conflicts;
	}

	public function prepare( $query, ...$args ) {
		$index = 0;
		return preg_replace_callback(
			'/%([sd])/',
			static function ( $matches ) use ( $args, &$index ) {
				$value = isset( $args[ $index ] ) ? $args[ $index ] : null;
				$index++;
				if ( 'd' === $matches[1] ) {
					return (string) (int) $value;
				}
				return "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}
}
