<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-relationships.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-routes.php';

/**
 * Minimal wpdb double that keeps rows in memory and enforces the unique keys
 * the production schema declares.
 */
class DSF_Translation_Routes_Test_DB {
	public $prefix     = 'wp_';
	public $insert_id  = 0;
	public $last_error = '';
	public $rows       = array();
	public $fail_write = false;

	private $next_id = 1;

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
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

	public function insert( $table, $data, $format ) {
		unset( $table, $format );
		if ( $this->fail_write ) {
			return false;
		}
		foreach ( $this->rows as $row ) {
			$same_object = $row['object_kind'] === $data['object_kind']
				&& $row['object_subtype'] === $data['object_subtype']
				&& (int) $row['object_id'] === (int) $data['object_id'];
			$same_path   = $row['language'] === $data['language'] && $row['path_hash'] === $data['path_hash'];
			if ( $same_object || $same_path ) {
				return false;
			}
		}
		$data['id']       = $this->next_id;
		$this->insert_id  = $this->next_id;
		$this->rows[]     = $data;
		$this->next_id++;
		return 1;
	}

	public function update( $table, $data, $where, $format, $where_format ) {
		unset( $table, $format, $where_format );
		if ( $this->fail_write ) {
			return false;
		}
		foreach ( $this->rows as $index => $row ) {
			if ( (int) $row['id'] === (int) $where['id'] ) {
				$this->rows[ $index ] = array_merge( $row, $data );
				return 1;
			}
		}
		return 0;
	}

	public function delete( $table, $where, $format ) {
		unset( $table, $format );
		$deleted = 0;
		foreach ( $this->rows as $index => $row ) {
			$matches = true;
			foreach ( $where as $column => $value ) {
				if ( (string) $row[ $column ] !== (string) $value ) {
					$matches = false;
					break;
				}
			}
			if ( $matches ) {
				unset( $this->rows[ $index ] );
				$deleted++;
			}
		}
		$this->rows = array_values( $this->rows );
		return $deleted;
	}

	public function get_row( $sql, $output ) {
		unset( $output );
		foreach ( $this->rows as $row ) {
			if ( $this->row_matches( $sql, $row ) ) {
				return $row;
			}
		}
		return null;
	}

	public function get_results( $sql, $output ) {
		unset( $output );
		$found = array();
		foreach ( $this->rows as $row ) {
			if ( false !== strpos( $sql, "language = '" . $row['language'] . "'" ) && false !== strpos( $sql, 'LIKE' ) ) {
				preg_match( "/LIKE '([^']*)'/", $sql, $matches );
				$like = isset( $matches[1] ) ? rtrim( $matches[1], '%' ) : '';
				if ( '' !== $like && 0 === strpos( $row['path'], $like ) ) {
					$found[] = $row;
				}
			}
		}
		return $found;
	}

	private function row_matches( $sql, $row ) {
		if ( false !== strpos( $sql, 'path_hash =' ) ) {
			return false !== strpos( $sql, "language = '" . $row['language'] . "'" )
				&& false !== strpos( $sql, "path_hash = '" . $row['path_hash'] . "'" );
		}
		return false !== strpos( $sql, "object_kind = '" . $row['object_kind'] . "'" )
			&& false !== strpos( $sql, "object_subtype = '" . $row['object_subtype'] . "'" )
			&& false !== strpos( $sql, 'object_id = ' . (int) $row['object_id'] . ' ' );
	}
}

/**
 * Covers the indexed language route map: normalization, per-language
 * uniqueness, collision handling, and deletion semantics.
 */
class Test_DSF_Translation_Routes extends TestCase {

	/** @var DSF_Translation_Routes_Test_DB */
	private $db;

	/** @var DSF_Translation_Routes */
	private $routes;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->db     = new DSF_Translation_Routes_Test_DB();
		$this->routes = new DSF_Translation_Routes( $this->db );
		WP_Mock::userFunction( 'current_time', array( 'return' => '2026-07-28 10:00:00' ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_slug_normalization_rejects_unsafe_segments() {
		$this->assertSame( 'acerca-de', DSF_Translation_Routes::normalize_slug( '/acerca-de/' ) );
		$this->assertSame( '%d0%be-%d0%bd%d0%b0%d1%81', DSF_Translation_Routes::normalize_slug( '%d0%be-%d0%bd%d0%b0%d1%81' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( '..' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( 'a/b' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( 'about?x=1' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( "about\nteam" ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( '<script>' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( str_repeat( 'a', 201 ) ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_slug( array( 'about' ) ) );
	}

	public function test_path_normalization_strips_queries_and_bounds_depth() {
		$this->assertSame( 'acerca-de/equipo', DSF_Translation_Routes::normalize_path( '/acerca-de/equipo/' ) );
		$this->assertSame( 'acerca-de/equipo', DSF_Translation_Routes::normalize_path( 'acerca-de//equipo' ) );
		$this->assertSame( 'acerca-de', DSF_Translation_Routes::normalize_path( 'acerca-de?utm=1' ) );
		$this->assertSame( 'acerca-de', DSF_Translation_Routes::normalize_path( 'acerca-de#team' ) );
		$this->assertSame( 'a/b', DSF_Translation_Routes::normalize_path( array( 'a', 'b' ) ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_path( 'a/../b' ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_path( implode( '/', array_fill( 0, 13, 'x' ) ) ) );
		$this->assertSame( '', DSF_Translation_Routes::normalize_path( '' ) );
	}

	public function test_route_is_stored_and_resolved_within_one_language() {
		$stored = $this->routes->set_route( 'post', 'page', 12, 'es-MX', 'acerca-de' );

		$this->assertIsArray( $stored );
		$this->assertSame( 'acerca-de', $stored['path'] );
		$this->assertSame( 'acerca-de', $stored['slug'] );
		$this->assertSame( 'es-MX', $stored['language'] );

		$found = $this->routes->find_by_path( 'es-MX', '/acerca-de/' );
		$this->assertSame( 12, $found['object_id'] );

		$this->assertNull( $this->routes->find_by_path( 'fr-FR', 'acerca-de' ) );
		$this->assertNull( $this->routes->find_by_path( 'es-MX', 'no-existe' ) );
	}

	public function test_same_path_in_two_languages_does_not_collide() {
		$this->routes->set_route( 'post', 'page', 12, 'es-MX', 'about' );
		$french = $this->routes->set_route( 'post', 'page', 13, 'fr-FR', 'about' );

		$this->assertSame( 'about', $french['path'] );
		$this->assertSame( 12, $this->routes->find_by_path( 'es-MX', 'about' )['object_id'] );
		$this->assertSame( 13, $this->routes->find_by_path( 'fr-FR', 'about' )['object_id'] );
	}

	public function test_same_language_collision_gets_a_bounded_suffix() {
		$this->routes->set_route( 'post', 'page', 12, 'es-MX', 'servicios/consultoria' );
		$second = $this->routes->set_route( 'post', 'page', 14, 'es-MX', 'servicios/consultoria' );

		$this->assertSame( 'servicios/consultoria-2', $second['path'] );
		$this->assertSame( 'consultoria-2', $second['slug'] );
		$this->assertSame( 14, $this->routes->find_by_path( 'es-MX', 'servicios/consultoria-2' )['object_id'] );
	}

	public function test_resaving_the_same_object_keeps_its_path() {
		$first  = $this->routes->set_route( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$second = $this->routes->set_route( 'post', 'page', 12, 'es-MX', 'acerca-de' );

		$this->assertSame( $first['path'], $second['path'] );
		$this->assertCount( 1, $this->db->rows );
	}

	public function test_changing_a_slug_updates_the_existing_row() {
		$this->routes->set_route( 'post', 'page', 12, 'es-MX', 'acerca-de' );
		$updated = $this->routes->set_route( 'post', 'page', 12, 'es-MX', 'sobre-nosotros' );

		$this->assertSame( 'sobre-nosotros', $updated['path'] );
		$this->assertCount( 1, $this->db->rows );
		$this->assertNull( $this->routes->find_by_path( 'es-MX', 'acerca-de' ) );
	}

	public function test_invalid_identity_language_and_path_are_rejected() {
		$this->assertInstanceOf( 'WP_Error', $this->routes->set_route( 'post', 'page', 0, 'es-MX', 'about' ) );
		$this->assertInstanceOf( 'WP_Error', $this->routes->set_route( 'post', 'page', 12, 'not a language', 'about' ) );
		$this->assertInstanceOf( 'WP_Error', $this->routes->set_route( 'post', 'page', 12, 'es-MX', '../../etc/passwd' ) );
		$this->assertInstanceOf( 'WP_Error', $this->routes->set_route( 'post<script>', 'page', 12, 'es-MX', 'about' ) );
		$this->assertSame( 'dsf_route_path', $this->routes->set_route( 'post', 'page', 12, 'es-MX', '' )->get_error_code() );
		$this->assertEmpty( $this->db->rows );
	}

	public function test_write_failures_return_errors_instead_of_partial_state() {
		$this->db->fail_write = true;
		$result               = $this->routes->set_route( 'post', 'page', 12, 'es-MX', 'about' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_route_write', $result->get_error_code() );
		$this->assertEmpty( $this->db->rows );
	}

	public function test_deleting_one_route_leaves_other_languages_intact() {
		$this->routes->set_route( 'post', 'page', 12, 'es-MX', 'about' );
		$this->routes->set_route( 'post', 'page', 13, 'fr-FR', 'about' );

		$this->assertTrue( $this->routes->delete_route( 'post', 'page', 12 ) );
		$this->assertNull( $this->routes->find_by_path( 'es-MX', 'about' ) );
		$this->assertSame( 13, $this->routes->find_by_path( 'fr-FR', 'about' )['object_id'] );
		$this->assertFalse( $this->routes->delete_route( 'post', 'page', 12 ) );
	}

	public function test_removing_a_language_drops_only_its_routes() {
		$this->routes->set_route( 'post', 'page', 12, 'es-MX', 'about' );
		$this->routes->set_route( 'post', 'page', 15, 'es-MX', 'contacto' );
		$this->routes->set_route( 'post', 'page', 13, 'fr-FR', 'about' );

		$this->assertSame( 2, $this->routes->delete_language_routes( 'es-MX' ) );
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( 13, $this->routes->find_by_path( 'fr-FR', 'about' )['object_id'] );
	}

	public function test_schema_declares_the_uniqueness_authority() {
		$sql = DSF_Translation_Routes::schema_sql( $this->db );

		$this->assertStringContainsString( 'wp_dsf_translation_routes', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY object_identity (object_kind,object_subtype,object_id)', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY language_path (language,path_hash)', $sql );
	}

	public function test_path_hash_is_language_scoped() {
		$this->assertNotSame(
			DSF_Translation_Routes::path_hash( 'es-MX', 'about' ),
			DSF_Translation_Routes::path_hash( 'fr-FR', 'about' )
		);
		$this->assertSame(
			DSF_Translation_Routes::path_hash( 'es-MX', 'about' ),
			DSF_Translation_Routes::path_hash( 'ES-mx', 'about' )
		);
	}
}
