<?php
/**
 * Indexed language route map.
 *
 * Secondary-language objects cannot rely on native WordPress slug uniqueness:
 * two languages legitimately want the same terminal slug, and WordPress would
 * silently rename one of them. This service therefore owns one normalized path
 * per secondary-language object, keyed by language, with the database as the
 * uniqueness authority.
 *
 * Main-language objects deliberately have no rows here. Their public URLs stay
 * exactly as WordPress already resolves them, which is the approved
 * unprefixed-main policy and avoids a URL migration for existing content.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Routes {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'dsf_translation_routes_db_version';
	const TABLE_SUFFIX      = 'dsf_translation_routes';
	const MAX_PATH_LENGTH   = 400;
	const MAX_SLUG_LENGTH   = 200;
	const MAX_SEGMENTS      = 12;
	const MAX_SUFFIX_TRIES  = 25;

	/** @var self|null */
	private static $instance = null;

	/** @var wpdb|null */
	private $database;

	/**
	 * @param wpdb|null $database Database adapter. Tests inject a double.
	 */
	public function __construct( $database = null ) {
		$this->database = $database;
	}

	/**
	 * Return the shared service instance.
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
	 * Create or upgrade the route table.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema_sql( $wpdb ) );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Run the idempotent schema upgrade when the stored version is behind.
	 */
	public function maybe_install() {
		if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '' ), self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Return dbDelta-compatible schema SQL.
	 *
	 * The path hash carries the uniqueness constraint because a full path can
	 * exceed the indexable key length for utf8mb4 columns.
	 *
	 * @param wpdb|null $database Database adapter.
	 * @return string
	 */
	public static function schema_sql( $database = null ) {
		if ( null === $database ) {
			global $wpdb;
			$database = $wpdb;
		}

		$table           = self::table_name( $database );
		$charset_collate = $database->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_kind varchar(16) NOT NULL,
			object_subtype varchar(64) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			language varchar(35) NOT NULL,
			slug varchar(200) NOT NULL,
			path varchar(400) NOT NULL,
			path_hash char(64) NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_identity (object_kind,object_subtype,object_id),
			UNIQUE KEY language_path (language,path_hash),
			KEY language_lookup (language)
		) {$charset_collate};";
	}

	/**
	 * Return the route table name.
	 *
	 * @param wpdb|null $database Database adapter.
	 * @return string
	 */
	public static function table_name( $database = null ) {
		if ( null === $database ) {
			global $wpdb;
			$database = $wpdb;
		}
		return $database->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Normalize one URL path segment.
	 *
	 * WordPress slugs may legitimately contain percent-encoded UTF-8, so the
	 * allowlist keeps `%`. Traversal segments, separators, and control bytes are
	 * rejected rather than escaped.
	 *
	 * @param mixed $slug Raw segment.
	 * @return string Empty when unusable.
	 */
	public static function normalize_slug( $slug ) {
		if ( ! is_scalar( $slug ) ) {
			return '';
		}

		$slug = trim( (string) $slug, "/ \t\n\r\0\x0B" );

		if ( '' === $slug || self::MAX_SLUG_LENGTH < strlen( $slug ) ) {
			return '';
		}
		if ( in_array( $slug, array( '.', '..' ), true ) ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9%_~.\-]+$/', $slug ) ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Normalize a full path built from already-trusted segments.
	 *
	 * @param mixed $path Raw path.
	 * @return string Empty when any segment is unusable.
	 */
	public static function normalize_path( $path ) {
		if ( is_array( $path ) ) {
			$segments = $path;
		} elseif ( is_scalar( $path ) ) {
			$separator = strpos( (string) $path, '?' );
			$path      = false === $separator ? (string) $path : substr( (string) $path, 0, $separator );
			$separator = strpos( $path, '#' );
			$path      = false === $separator ? $path : substr( $path, 0, $separator );
			$segments  = explode( '/', $path );
		} else {
			return '';
		}

		$clean = array();
		foreach ( $segments as $segment ) {
			if ( is_scalar( $segment ) && '' === trim( (string) $segment ) ) {
				continue;
			}
			$segment = self::normalize_slug( $segment );
			if ( '' === $segment ) {
				return '';
			}
			$clean[] = $segment;
			if ( count( $clean ) > self::MAX_SEGMENTS ) {
				return '';
			}
		}

		if ( empty( $clean ) ) {
			return '';
		}

		$normalized = implode( '/', $clean );
		return self::MAX_PATH_LENGTH < strlen( $normalized ) ? '' : $normalized;
	}

	/**
	 * Store one object's route, resolving same-language collisions.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @param string $language       Curated language identifier.
	 * @param string $path           Desired normalized path.
	 * @return array|WP_Error Stored route row.
	 */
	public function set_route( $object_kind, $object_subtype, $object_id, $language, $path ) {
		$identity = $this->validate_identity( $object_kind, $object_subtype, $object_id, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}

		$path = self::normalize_path( $path );
		if ( '' === $path ) {
			return $this->error( 'dsf_route_path', 'The translated route path is invalid.' );
		}

		$existing = $this->get_route( $identity['object_kind'], $identity['object_subtype'], $identity['object_id'] );
		if ( $existing instanceof WP_Error ) {
			return $existing;
		}
		if ( is_array( $existing ) && $existing['language'] === $identity['language'] && $existing['path'] === $path ) {
			return $existing;
		}

		$candidate = $this->resolve_available_path( $path, $identity );
		if ( $candidate instanceof WP_Error ) {
			return $candidate;
		}

		$db     = $this->get_database();
		$table  = self::table_name( $db );
		$now    = current_time( 'mysql', true );
		$row    = array(
			'object_kind'    => $identity['object_kind'],
			'object_subtype' => $identity['object_subtype'],
			'object_id'      => $identity['object_id'],
			'language'       => $identity['language'],
			'slug'           => $this->terminal_segment( $candidate ),
			'path'           => $candidate,
			'path_hash'      => self::path_hash( $identity['language'], $candidate ),
			'updated_at_gmt' => $now,
		);
		$format = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

		if ( is_array( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The route map is an indexed authority table with its own uniqueness keys.
			$updated = $db->update( $table, $row, array( 'id' => absint( $existing['id'] ) ), $format, array( '%d' ) );
			if ( false === $updated ) {
				return $this->error( 'dsf_route_write', 'The translated route could not be stored.' );
			}
			$row['id'] = absint( $existing['id'] );
			return $row;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert relies on the unique keys to reject a concurrent duplicate.
		$inserted = $db->insert( $table, $row, $format );
		if ( false === $inserted ) {
			return $this->error( 'dsf_route_write', 'The translated route could not be stored.' );
		}
		$row['id'] = absint( $db->insert_id );
		return $row;
	}

	/**
	 * Read one object's stored route.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @return array|null|WP_Error
	 */
	public function get_route( $object_kind, $object_subtype, $object_id ) {
		$object_kind    = $this->normalize_token( $object_kind, 16 );
		$object_subtype = $this->normalize_token( $object_subtype, 64 );
		$object_id      = absint( $object_id );
		if ( '' === $object_kind || '' === $object_subtype || ! $object_id ) {
			return $this->error( 'dsf_route_identity', 'The translated route object is invalid.' );
		}

		$db    = $this->get_database();
		$table = self::table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "SELECT id, object_kind, object_subtype, object_id, language, slug, path, path_hash, updated_at_gmt FROM {$table} WHERE object_kind = %s AND object_subtype = %s AND object_id = %d LIMIT 1", $object_kind, $object_subtype, $object_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; this indexed table is the route authority.
		return $this->format_route( $db->get_row( $sql, ARRAY_A ) );
	}

	/**
	 * Resolve a request path inside one language.
	 *
	 * @param string $language Curated language identifier.
	 * @param string $path     Normalized request path without the prefix.
	 * @return array|null|WP_Error
	 */
	public function find_by_path( $language, $path ) {
		$language = DSF_Translation_Relationships::normalize_language_id( $language );
		$path     = self::normalize_path( $path );
		if ( '' === $language || '' === $path ) {
			return null;
		}

		$db    = $this->get_database();
		$table = self::table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "SELECT id, object_kind, object_subtype, object_id, language, slug, path, path_hash, updated_at_gmt FROM {$table} WHERE language = %s AND path_hash = %s LIMIT 1", $language, self::path_hash( $language, $path ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; public route resolution must read the indexed authority.
		return $this->format_route( $db->get_row( $sql, ARRAY_A ) );
	}

	/**
	 * Return every stored route that begins with a path, including itself.
	 *
	 * Used when a translated ancestor slug changes and descendants must follow.
	 *
	 * @param string $language Curated language identifier.
	 * @param string $path     Normalized ancestor path.
	 * @param int    $limit    Maximum rows.
	 * @return array[]
	 */
	public function find_descendants( $language, $path, $limit = 200 ) {
		$language = DSF_Translation_Relationships::normalize_language_id( $language );
		$path     = self::normalize_path( $path );
		if ( '' === $language || '' === $path ) {
			return array();
		}

		$db    = $this->get_database();
		$table = self::table_name( $db );
		$like  = $db->esc_like( $path . '/' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "SELECT id, object_kind, object_subtype, object_id, language, slug, path, path_hash, updated_at_gmt FROM {$table} WHERE language = %s AND path LIKE %s ORDER BY path ASC LIMIT %d", $language, $like, max( 1, min( 1000, absint( $limit ) ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; descendant repair reads the indexed authority.
		$rows = $db->get_results( $sql, ARRAY_A );

		$routes = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$row = $this->format_route( $row );
			if ( is_array( $row ) ) {
				$routes[] = $row;
			}
		}
		return $routes;
	}

	/**
	 * Delete one object's route.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @return bool|WP_Error True when a row was removed.
	 */
	public function delete_route( $object_kind, $object_subtype, $object_id ) {
		$object_kind    = $this->normalize_token( $object_kind, 16 );
		$object_subtype = $this->normalize_token( $object_subtype, 64 );
		$object_id      = absint( $object_id );
		if ( '' === $object_kind || '' === $object_subtype || ! $object_id ) {
			return $this->error( 'dsf_route_identity', 'The translated route object is invalid.' );
		}

		$db = $this->get_database();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Route removal must reach the indexed authority immediately.
		$deleted = $db->delete(
			self::table_name( $db ),
			array(
				'object_kind'    => $object_kind,
				'object_subtype' => $object_subtype,
				'object_id'      => $object_id,
			),
			array( '%s', '%s', '%d' )
		);
		if ( false === $deleted ) {
			return $this->error( 'dsf_route_write', 'The translated route could not be removed.' );
		}
		return 0 < (int) $deleted;
	}

	/**
	 * Remove every stored route for a language.
	 *
	 * @param string $language Curated language identifier.
	 * @return int|WP_Error Deleted row count.
	 */
	public function delete_language_routes( $language ) {
		$language = DSF_Translation_Relationships::normalize_language_id( $language );
		if ( '' === $language ) {
			return $this->error( 'dsf_route_language', 'The translated route language is invalid.' );
		}

		$db = $this->get_database();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Language removal must clear the indexed authority immediately.
		$deleted = $db->delete( self::table_name( $db ), array( 'language' => $language ), array( '%s' ) );
		if ( false === $deleted ) {
			return $this->error( 'dsf_route_write', 'The translated routes could not be removed.' );
		}
		return (int) $deleted;
	}

	/**
	 * Build the stable uniqueness hash for a language and path.
	 *
	 * @param string $language Language identifier.
	 * @param string $path     Normalized path.
	 * @return string
	 */
	public static function path_hash( $language, $path ) {
		return hash( 'sha256', strtolower( (string) $language ) . "\n" . (string) $path );
	}

	/**
	 * Find a free path inside a language, appending a bounded numeric suffix.
	 *
	 * @param string $path     Desired path.
	 * @param array  $identity Validated object identity.
	 * @return string|WP_Error
	 */
	private function resolve_available_path( $path, $identity ) {
		$segments = explode( '/', $path );
		$terminal = array_pop( $segments );
		$parent   = implode( '/', $segments );

		for ( $attempt = 1; $attempt <= self::MAX_SUFFIX_TRIES; $attempt++ ) {
			$candidate_slug = 1 === $attempt ? $terminal : $terminal . '-' . $attempt;
			$candidate      = self::normalize_path( '' === $parent ? $candidate_slug : $parent . '/' . $candidate_slug );
			if ( '' === $candidate ) {
				return $this->error( 'dsf_route_path', 'The translated route path is invalid.' );
			}

			$owner = $this->find_by_path( $identity['language'], $candidate );
			if ( $owner instanceof WP_Error ) {
				return $owner;
			}
			if ( ! is_array( $owner ) ) {
				return $candidate;
			}
			if (
				$owner['object_kind'] === $identity['object_kind']
				&& $owner['object_subtype'] === $identity['object_subtype']
				&& $owner['object_id'] === $identity['object_id']
			) {
				return $candidate;
			}
		}

		return $this->error( 'dsf_route_collision', 'A unique translated route could not be created for this language.' );
	}

	/**
	 * Validate an object identity and its language.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @param string $language       Language identifier.
	 * @return array|WP_Error
	 */
	private function validate_identity( $object_kind, $object_subtype, $object_id, $language ) {
		$object_kind    = $this->normalize_token( $object_kind, 16 );
		$object_subtype = $this->normalize_token( $object_subtype, 64 );
		$object_id      = absint( $object_id );
		$language       = DSF_Translation_Relationships::normalize_language_id( $language );

		if ( '' === $object_kind || '' === $object_subtype || ! $object_id ) {
			return $this->error( 'dsf_route_identity', 'The translated route object is invalid.' );
		}
		if ( '' === $language ) {
			return $this->error( 'dsf_route_language', 'The translated route language is invalid.' );
		}

		return array(
			'object_kind'    => $object_kind,
			'object_subtype' => $object_subtype,
			'object_id'      => $object_id,
			'language'       => $language,
		);
	}

	/**
	 * Return the last segment of a normalized path.
	 *
	 * @param string $path Normalized path.
	 * @return string
	 */
	private function terminal_segment( $path ) {
		$segments = explode( '/', (string) $path );
		return (string) array_pop( $segments );
	}

	/**
	 * Normalize a bounded lowercase identifier token.
	 *
	 * @param mixed $value  Raw token.
	 * @param int   $length Maximum length.
	 * @return string
	 */
	private function normalize_token( $value, $length ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value || $length < strlen( $value ) || ! preg_match( '/^[a-z0-9_\-]+$/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Convert a raw row into a typed route array.
	 *
	 * @param mixed $row Raw database row.
	 * @return array|null
	 */
	private function format_route( $row ) {
		if ( ! is_array( $row ) || empty( $row['path'] ) ) {
			return null;
		}
		return array(
			'id'             => absint( $row['id'] ?? 0 ),
			'object_kind'    => (string) ( $row['object_kind'] ?? '' ),
			'object_subtype' => (string) ( $row['object_subtype'] ?? '' ),
			'object_id'      => absint( $row['object_id'] ?? 0 ),
			'language'       => (string) ( $row['language'] ?? '' ),
			'slug'           => (string) ( $row['slug'] ?? '' ),
			'path'           => (string) $row['path'],
			'path_hash'      => (string) ( $row['path_hash'] ?? '' ),
			'updated_at_gmt' => (string) ( $row['updated_at_gmt'] ?? '' ),
		);
	}

	/**
	 * Return the active database adapter.
	 *
	 * @return wpdb
	 */
	private function get_database() {
		if ( null === $this->database ) {
			global $wpdb;
			$this->database = $wpdb;
		}
		return $this->database;
	}

	/**
	 * Build a translated error object.
	 *
	 * @param string $code    Error code.
	 * @param string $message Untranslated message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( $code, __( $message, 'designstudio-flow' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Messages are fixed literals supplied by this class only.
	}
}
