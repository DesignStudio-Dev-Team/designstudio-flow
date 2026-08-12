<?php
/**
 * Portable, same-language translation dependency graph.
 *
 * Edges use translation-group UUIDs instead of local object IDs. Resolution is
 * delegated to injected relationship and eligibility callbacks so this class
 * cannot silently fall back to a member in another language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Dependencies {

	const DB_VERSION      = '1.0.0';
	const TABLE_SUFFIX    = 'dsf_translation_dependencies';
	const MAX_EDGES_OWNER = 250;
	const MAX_KIND_LENGTH = 48;
	const MAX_PATH_LENGTH = 255;
	const MAX_GRAPH_DEPTH = 20;
	const MAX_GRAPH_EDGES = 500;

	const DEFAULT_KINDS = array(
		'archive',
		'blog_template',
		'dynamic_content',
		'footer',
		'form',
		'header',
		'layout_footer',
		'layout_header',
		'notification',
		'page',
		'parent',
		'popup',
		'post',
		'product',
		'product_template',
		'reusable_content',
		'saved_block',
		'shop_template',
		'taxonomy',
		'template',
		'term',
		'variation',
	);

	/** @var object|null */
	private $wpdb;

	/** @var string */
	private $table;

	/** @var callable|null */
	private $member_resolver;

	/** @var callable|null */
	private $eligibility_resolver;

	/** @var callable|null */
	private $language_validator;

	/** @var array */
	private $allowed_kinds;

	/** @var int */
	private $max_depth;

	/** @var int */
	private $max_graph_edges;

	/**
	 * Constructor.
	 *
	 * `member_resolver(group_uuid, language)` must return the exact-language
	 * member or an empty value. `eligibility_resolver(member, group_uuid,
	 * language, edge)` must return strict true, or an array with strict
	 * `eligible => true`, only when that member is reviewed, published,
	 * viewable, and route-valid. No resolver may return a fallback language.
	 *
	 * @param array $args Dependency-injection arguments.
	 */
	public function __construct( $args = array() ) {
		global $wpdb;

		$args                       = is_array( $args ) ? $args : array();
		$this->wpdb                 = isset( $args['wpdb'] ) ? $args['wpdb'] : ( isset( $wpdb ) ? $wpdb : null );
		$this->table                = ! empty( $args['table_name'] ) ? (string) $args['table_name'] : '';
		$this->member_resolver      = isset( $args['member_resolver'] ) && is_callable( $args['member_resolver'] ) ? $args['member_resolver'] : null;
		$this->eligibility_resolver = isset( $args['eligibility_resolver'] ) && is_callable( $args['eligibility_resolver'] ) ? $args['eligibility_resolver'] : null;
		$this->language_validator   = isset( $args['language_validator'] ) && is_callable( $args['language_validator'] ) ? $args['language_validator'] : null;
		$this->allowed_kinds        = $this->normalize_allowed_kinds( $args['allowed_kinds'] ?? self::DEFAULT_KINDS );
		$this->max_depth            = $this->bounded_integer( $args['max_depth'] ?? self::MAX_GRAPH_DEPTH, 1, self::MAX_GRAPH_DEPTH );
		$this->max_graph_edges      = $this->bounded_integer( $args['max_graph_edges'] ?? self::MAX_GRAPH_EDGES, 1, self::MAX_GRAPH_EDGES );
	}

	/** Return the site-specific dependency table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/** Return the dbDelta schema for the plugin installer. */
	public static function schema_sql( $wpdb_instance = null ) {
		global $wpdb;
		$db = $wpdb_instance ? $wpdb_instance : ( isset( $wpdb ) ? $wpdb : null );
		if ( ! $db || ! isset( $db->prefix ) || ! is_callable( array( $db, 'get_charset_collate' ) ) ) {
			return '';
		}

		$table           = $db->prefix . self::TABLE_SUFFIX;
		$charset_collate = $db->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			edge_key char(64) NOT NULL,
			owner_group_uuid char(36) NOT NULL,
			language varchar(20) NOT NULL,
			dependency_group_uuid char(36) NOT NULL,
			kind varchar(48) NOT NULL,
			source_path varchar(255) NOT NULL,
			required tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY edge_key (edge_key),
			KEY owner_language (owner_group_uuid,language,required),
			KEY dependency_language (dependency_group_uuid,language)
		) {$charset_collate};";
	}

	/** Create or upgrade the dependency table. Called by plugin installation. */
	public static function install() {
		$sql = self::schema_sql();
		if ( '' === $sql ) {
			return new WP_Error( 'dsf_dependency_database', 'The translation dependency database is unavailable.' );
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
		update_option( 'dsf_translation_dependencies_db_version', self::DB_VERSION, false );
		return true;
	}

	/**
	 * Atomically replace one translated owner's complete dependency set.
	 *
	 * The mandatory authorization callback receives `(action, owner group UUID,
	 * language)` and must return strict true after resolving exact object-level
	 * permissions. HTTP callers must verify their action nonce before this API.
	 *
	 * @param string   $owner_group_uuid     Portable owner group UUID.
	 * @param string   $language             Enabled owner language.
	 * @param array    $edges                Adapter-extracted dependency edges.
	 * @param callable $authorization_callback Exact authorization callback.
	 * @return int|WP_Error Number of unique stored edges.
	 */
	public function replace_edges( $owner_group_uuid, $language, $edges, $authorization_callback ) {
		$identity = $this->validate_identity( $owner_group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		if ( ! is_array( $edges ) || count( $edges ) > self::MAX_EDGES_OWNER ) {
			return new WP_Error( 'dsf_dependency_edges', 'The dependency list is malformed or too large.' );
		}

		$normalized = array();
		foreach ( $edges as $edge ) {
			$clean = $this->normalize_edge( $identity['group_uuid'], $identity['language'], $edge );
			if ( $clean instanceof WP_Error ) {
				return $clean;
			}
			if ( isset( $normalized[ $clean['edge_key'] ] ) ) {
				// Conflicting duplicate declarations fail safe: required wins.
				$normalized[ $clean['edge_key'] ]['required'] = $normalized[ $clean['edge_key'] ]['required'] || $clean['required'];
			} else {
				$normalized[ $clean['edge_key'] ] = $clean;
			}
		}

		$authorized = $this->authorize( $authorization_callback, 'replace_dependencies', $identity['group_uuid'], $identity['language'] );
		if ( $authorized instanceof WP_Error ) {
			return $authorized;
		}
		$db = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table = $this->current_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction keeps a complete owner graph atomic.
		if ( false === $db->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'dsf_dependency_transaction', 'The dependency update could not start.' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
		$delete_sql = $db->prepare( "DELETE FROM {$table} WHERE owner_group_uuid = %s AND language = %s", $identity['group_uuid'], $identity['language'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is fully prepared above.
		if ( false === $db->query( $delete_sql ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction cleanup uses a fixed statement.
			$db->query( 'ROLLBACK' );
			return new WP_Error( 'dsf_dependency_database', 'The existing dependency set could not be replaced.' );
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $normalized as $edge ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
			$insert_sql = $db->prepare(
				"INSERT INTO {$table} (edge_key, owner_group_uuid, language, dependency_group_uuid, kind, source_path, required, created_at_gmt, updated_at_gmt)
				VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %s)
				ON DUPLICATE KEY UPDATE required = VALUES(required), updated_at_gmt = VALUES(updated_at_gmt)",
				$edge['edge_key'],
				$edge['owner_group_uuid'],
				$edge['language'],
				$edge['dependency_group_uuid'],
				$edge['kind'],
				$edge['source_path'],
				$edge['required'] ? 1 : 0,
				$now,
				$now
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is fully prepared above.
			if ( false === $db->query( $insert_sql ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction cleanup uses a fixed statement.
				$db->query( 'ROLLBACK' );
				return new WP_Error( 'dsf_dependency_database', 'The dependency set could not be stored.' );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fixed transaction statement.
		if ( false === $db->query( 'COMMIT' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Best-effort fixed transaction cleanup.
			$db->query( 'ROLLBACK' );
			return new WP_Error( 'dsf_dependency_transaction', 'The dependency update could not be committed.' );
		}
		return count( $normalized );
	}

	/**
	 * Normalize one edge using only known keys and bounded scalar values.
	 *
	 * @return array|WP_Error
	 */
	public function normalize_edge( $owner_group_uuid, $language, $edge ) {
		$identity = $this->validate_identity( $owner_group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		if ( ! is_array( $edge ) ) {
			return new WP_Error( 'dsf_dependency_edge', 'A dependency edge must be an array.' );
		}

		$dependency_group_uuid = isset( $edge['dependency_group_uuid'] ) && is_string( $edge['dependency_group_uuid'] ) ? strtolower( trim( $edge['dependency_group_uuid'] ) ) : '';
		if ( ! self::is_valid_group_uuid( $dependency_group_uuid ) ) {
			return new WP_Error( 'dsf_dependency_group', 'The dependency translation group is invalid.' );
		}
		if ( hash_equals( $identity['group_uuid'], $dependency_group_uuid ) ) {
			return new WP_Error( 'dsf_dependency_self', 'A translation cannot depend directly on itself.' );
		}

		$raw_kind = $edge['kind'] ?? ( $edge['dependency_kind'] ?? '' );
		$kind     = is_string( $raw_kind ) ? strtolower( trim( $raw_kind ) ) : '';
		if ( '' === $kind || strlen( $kind ) > self::MAX_KIND_LENGTH || ! preg_match( '/^[a-z][a-z0-9_]*$/', $kind ) || ! in_array( $kind, $this->allowed_kinds, true ) ) {
			return new WP_Error( 'dsf_dependency_kind', 'The dependency kind is invalid.' );
		}

		$raw_source_path = $edge['source_path'] ?? ( $edge['path'] ?? '' );
		$source_path     = is_string( $raw_source_path ) ? trim( $raw_source_path ) : '';
		if ( '' === $source_path || strlen( $source_path ) > self::MAX_PATH_LENGTH || ! preg_match( '/^[A-Za-z0-9_.:\/\[\]-]+$/', $source_path ) ) {
			return new WP_Error( 'dsf_dependency_path', 'The dependency source path is invalid.' );
		}

		$required_values = array( true, false, 1, 0, '1', '0' );
		if ( ! array_key_exists( 'required', $edge ) || ! in_array( $edge['required'], $required_values, true ) ) {
			return new WP_Error( 'dsf_dependency_required', 'The dependency requirement flag is invalid.' );
		}
		$required = true === $edge['required'] || 1 === $edge['required'] || '1' === $edge['required'];
		$edge_key = hash( 'sha256', implode( "\n", array( $identity['group_uuid'], $identity['language'], $dependency_group_uuid, $kind, $source_path ) ) );

		return array(
			'edge_key'              => $edge_key,
			'owner_group_uuid'      => $identity['group_uuid'],
			'language'              => $identity['language'],
			'dependency_group_uuid' => $dependency_group_uuid,
			'kind'                  => $kind,
			'source_path'           => $source_path,
			'required'              => $required,
		);
	}

	/** Return all validated edges for one owner and language. */
	public function get_edges( $owner_group_uuid, $language ) {
		$identity = $this->validate_identity( $owner_group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		$db = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table = $this->current_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
		$sql = $db->prepare( "SELECT dependency_group_uuid, kind, source_path, required FROM {$table} WHERE owner_group_uuid = %s AND language = %s ORDER BY required DESC, id ASC LIMIT %d", $identity['group_uuid'], $identity['language'], self::MAX_EDGES_OWNER + 1 );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared above and dependency state must be current.
		$rows = $db->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'dsf_dependency_database', 'The dependency set could not be loaded.' );
		}
		if ( count( $rows ) > self::MAX_EDGES_OWNER ) {
			return new WP_Error( 'dsf_dependency_corrupt', 'The stored dependency set exceeds its safety limit.' );
		}

		$edges = array();
		foreach ( $rows as $row ) {
			$edge = $this->normalize_edge(
				$identity['group_uuid'],
				$identity['language'],
				array(
					'dependency_group_uuid' => $row['dependency_group_uuid'] ?? '',
					'kind'                  => $row['kind'] ?? '',
					'source_path'           => $row['source_path'] ?? '',
					'required'              => isset( $row['required'] ) ? (string) $row['required'] : '',
				)
			);
			if ( $edge instanceof WP_Error ) {
				return new WP_Error( 'dsf_dependency_corrupt', 'Stored translation dependency data is invalid.' );
			}
			$edges[] = $edge;
		}
		return $edges;
	}

	/**
	 * Evaluate the bounded same-language dependency closure.
	 *
	 * The result never substitutes a different language. Missing or ineligible
	 * required members, required cycles, corrupt data, and required traversal
	 * limits fail closed. Optional failures are listed but do not block the owner.
	 *
	 * @return array|WP_Error
	 */
	public function evaluate_closure( $owner_group_uuid, $language ) {
		$identity = $this->validate_identity( $owner_group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		if ( ! $this->member_resolver || ! $this->eligibility_resolver ) {
			return new WP_Error( 'dsf_dependency_resolver', 'Exact-language member and eligibility resolvers are required.' );
		}

		$result   = array(
			'owner_group_uuid'     => $identity['group_uuid'],
			'language'             => $identity['language'],
			'eligible'             => true,
			'checked_edges'        => 0,
			'resolved'             => array(),
			'missing'              => array(),
			'ineligible'           => array(),
			'optional_unavailable' => array(),
			'cycles'               => array(),
			'truncated'            => false,
		);
		$expanded         = array();
		$required_checked = 0;
		$optional_checked = 0;
		$walk             = $this->walk_closure(
			$identity['group_uuid'],
			$identity['language'],
			true,
			array( $identity['group_uuid'] ),
			0,
			$expanded,
			$result,
			$required_checked,
			$optional_checked
		);
		if ( $walk instanceof WP_Error ) {
			return $walk;
		}

		return $result;
	}

	/**
	 * Depth-first closure walk. A node is marked expanded only after its children
	 * finish, which preserves ancestry-based cycle detection across diamonds.
	 *
	 * @return true|WP_Error
	 */
	private function walk_closure( $group_uuid, $language, $chain_required, $ancestry, $depth, &$expanded, &$result, &$required_checked, &$optional_checked ) {
		$state_key = $group_uuid . '|' . ( $chain_required ? '1' : '0' );
		if ( isset( $expanded[ $state_key ] ) ) {
			return true;
		}
		if ( ! $chain_required && $optional_checked >= $this->max_graph_edges ) {
			$result['truncated']              = true;
			$result['optional_unavailable'][] = $this->failure_record(
				array(
					'language'    => $language,
					'kind'        => '',
					'source_path' => '',
				),
				$group_uuid,
				false,
				'edge_limit'
			);
			$expanded[ $state_key ] = true;
			return true;
		}

		$edges = $this->get_edges( $group_uuid, $language );
		if ( $edges instanceof WP_Error ) {
			if ( $chain_required ) {
				return $edges;
			}
			$result['optional_unavailable'][] = $this->failure_record(
				array(
					'language'    => $language,
					'kind'        => '',
					'source_path' => '',
				),
				$group_uuid,
				false,
				'graph_unavailable'
			);
			$expanded[ $state_key ] = true;
			return true;
		}
		if ( $depth >= $this->max_depth && ! empty( $edges ) ) {
			$result['truncated'] = true;
			if ( $chain_required ) {
				$result['eligible']     = false;
				$result['ineligible'][] = $this->failure_record(
					array(
						'language'    => $language,
						'kind'        => '',
						'source_path' => '',
					),
					$group_uuid,
					true,
					'depth_limit'
				);
			} else {
				$result['optional_unavailable'][] = $this->failure_record(
					array(
						'language'    => $language,
						'kind'        => '',
						'source_path' => '',
					),
					$group_uuid,
					false,
					'depth_limit'
				);
			}
			$expanded[ $state_key ] = true;
			return true;
		}

		foreach ( $edges as $edge ) {
			$dependency          = $edge['dependency_group_uuid'];
			$dependency_required = $chain_required && $edge['required'];
			if ( $dependency_required ) {
				if ( $required_checked >= $this->max_graph_edges ) {
					$result['truncated']    = true;
					$result['eligible']     = false;
					$result['ineligible'][] = $this->failure_record( $edge, $dependency, true, 'edge_limit' );
					return true;
				}
				++$required_checked;
			} else {
				if ( $optional_checked >= $this->max_graph_edges ) {
					$result['truncated']              = true;
					$result['optional_unavailable'][] = $this->failure_record( $edge, $dependency, false, 'edge_limit' );
					continue;
				}
				++$optional_checked;
			}
			++$result['checked_edges'];

			if ( in_array( $dependency, $ancestry, true ) ) {
				$cycle              = $this->failure_record( $edge, $dependency, $dependency_required, 'cycle' );
				$result['cycles'][] = $cycle;
				if ( $dependency_required ) {
					$result['eligible']     = false;
					$result['ineligible'][] = $cycle;
				} else {
					$result['optional_unavailable'][] = $cycle;
				}
				continue;
			}

			$member = call_user_func( $this->member_resolver, $dependency, $language );
			if ( $member instanceof WP_Error || null === $member || false === $member || 0 === $member || '' === $member ) {
				$failure = $this->failure_record( $edge, $dependency, $dependency_required, 'missing_same_language_member' );
				if ( $dependency_required ) {
					$result['eligible']  = false;
					$result['missing'][] = $failure;
				} else {
					$result['optional_unavailable'][] = $failure;
				}
				continue;
			}

			$member_language = $this->member_language( $member );
			if ( null !== $member_language && $language !== $member_language ) {
				$failure = $this->failure_record( $edge, $dependency, $dependency_required, 'language_mismatch' );
				if ( $dependency_required ) {
					$result['eligible']     = false;
					$result['ineligible'][] = $failure;
				} else {
					$result['optional_unavailable'][] = $failure;
				}
				continue;
			}

			$eligibility = call_user_func( $this->eligibility_resolver, $member, $dependency, $language, $edge );
			$decision    = $this->normalize_eligibility( $eligibility );
			if ( ! $decision['eligible'] ) {
				$failure = $this->failure_record( $edge, $dependency, $dependency_required, $decision['reason'] );
				if ( $dependency_required ) {
					$result['eligible']     = false;
					$result['ineligible'][] = $failure;
				} else {
					$result['optional_unavailable'][] = $failure;
				}
				continue;
			}

			$result['resolved'][] = array(
				'dependency_group_uuid' => $dependency,
				'language'              => $language,
				'kind'                  => $edge['kind'],
				'source_path'           => $edge['source_path'],
				'required'              => $dependency_required,
				'eligible'              => true,
			);

			$next_ancestry   = $ancestry;
			$next_ancestry[] = $dependency;
			$walk            = $this->walk_closure( $dependency, $language, $dependency_required, $next_ancestry, $depth + 1, $expanded, $result, $required_checked, $optional_checked );
			if ( $walk instanceof WP_Error ) {
				return $walk;
			}
		}

		$expanded[ $state_key ] = true;
		return true;
	}

	/** Validate an RFC 4122-shaped portable group UUID. */
	public static function is_valid_group_uuid( $group_uuid ) {
		return is_string( $group_uuid ) && (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $group_uuid );
	}

	/** Canonicalize a syntactically valid BCP 47 language tag. */
	public static function normalize_language( $language ) {
		if ( ! is_string( $language ) ) {
			return '';
		}
		$language = trim( $language );
		if ( strlen( $language ) > 20 || ! preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z]{4})?(?:-(?:[A-Za-z]{2}|[0-9]{3}))?(?:-[A-Za-z0-9]{5,8})*$/', $language ) ) {
			return '';
		}
		$parts = explode( '-', $language );
		foreach ( $parts as $index => $part ) {
			if ( 0 === $index ) {
				$parts[ $index ] = strtolower( $part );
			} elseif ( 4 === strlen( $part ) && ctype_alpha( $part ) ) {
				$parts[ $index ] = ucfirst( strtolower( $part ) );
			} elseif ( ( 2 === strlen( $part ) && ctype_alpha( $part ) ) || ( 3 === strlen( $part ) && ctype_digit( $part ) ) ) {
				$parts[ $index ] = strtoupper( $part );
			} else {
				$parts[ $index ] = strtolower( $part );
			}
		}
		return implode( '-', $parts );
	}

	/** Validate group/language and the injected enabled-language policy. */
	private function validate_identity( $group_uuid, $language ) {
		if ( ! is_string( $group_uuid ) ) {
			return new WP_Error( 'dsf_dependency_owner', 'The dependency owner group is invalid.' );
		}
		$group_uuid = strtolower( trim( $group_uuid ) );
		$language   = self::normalize_language( $language );
		if ( ! self::is_valid_group_uuid( $group_uuid ) ) {
			return new WP_Error( 'dsf_dependency_owner', 'The dependency owner group is invalid.' );
		}
		if ( '' === $language ) {
			return new WP_Error( 'dsf_dependency_language', 'The dependency language is invalid.' );
		}
		if ( ! $this->language_validator ) {
			return new WP_Error( 'dsf_dependency_language_validator', 'An enabled-language validator is required.' );
		}
		if ( true !== call_user_func( $this->language_validator, $language ) ) {
			return new WP_Error( 'dsf_dependency_language_disabled', 'The dependency language is not enabled.' );
		}
		return array(
			'group_uuid' => $group_uuid,
			'language'   => $language,
		);
	}

	/** Require a strict, object-specific authorization result. */
	private function authorize( $callback, $action, $group_uuid, $language ) {
		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'dsf_dependency_authorization', 'An object-specific authorization callback is required.' );
		}
		$result = call_user_func( $callback, $action, $group_uuid, $language );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		return true === $result ? true : new WP_Error( 'dsf_dependency_forbidden', 'You are not allowed to update these translation dependencies.' );
	}

	/** Return wpdb or a typed error. */
	private function database() {
		if ( ! $this->wpdb || ! $this->current_table() || ! is_callable( array( $this->wpdb, 'prepare' ) ) ) {
			return new WP_Error( 'dsf_dependency_database', 'The translation dependency database is unavailable.' );
		}
		return $this->wpdb;
	}

	/** Resolve the active site's table after a possible multisite blog switch. */
	private function current_table() {
		if ( '' !== $this->table ) {
			return $this->table;
		}
		return $this->wpdb && isset( $this->wpdb->prefix ) ? $this->wpdb->prefix . self::TABLE_SUFFIX : '';
	}

	/** Keep the extension allowlist bounded and identifier-safe. */
	private function normalize_allowed_kinds( $kinds ) {
		$clean = array();
		if ( ! is_array( $kinds ) || count( $kinds ) > 100 ) {
			return self::DEFAULT_KINDS;
		}
		foreach ( $kinds as $kind ) {
			$kind = is_string( $kind ) ? strtolower( trim( $kind ) ) : '';
			if ( $kind && strlen( $kind ) <= self::MAX_KIND_LENGTH && preg_match( '/^[a-z][a-z0-9_]*$/', $kind ) ) {
				$clean[] = $kind;
			}
		}
		return $clean ? array_values( array_unique( $clean ) ) : self::DEFAULT_KINDS;
	}

	/** Normalize an eligibility resolver result without trusting its reason text. */
	private function normalize_eligibility( $value ) {
		if ( true === $value ) {
			return array(
				'eligible' => true,
				'reason'   => '',
			);
		}
		if ( $value instanceof WP_Error ) {
			return array(
				'eligible' => false,
				'reason'   => 'resolver_error',
			);
		}
		if ( is_array( $value ) ) {
			return array(
				'eligible' => isset( $value['eligible'] ) && true === $value['eligible'],
				'reason'   => isset( $value['eligible'] ) && true === $value['eligible'] ? '' : $this->bounded_reason( $value['reason'] ?? 'ineligible' ),
			);
		}
		return array(
			'eligible' => false,
			'reason'   => 'ineligible',
		);
	}

	/** Extract an optional member language and canonicalize it for comparison. */
	private function member_language( $member ) {
		$found = false;
		$value = '';
		if ( is_array( $member ) ) {
			if ( array_key_exists( 'language', $member ) ) {
				$found = true;
				$value = $member['language'];
			} elseif ( array_key_exists( 'language_code', $member ) ) {
				$found = true;
				$value = $member['language_code'];
			}
		} elseif ( is_object( $member ) ) {
			if ( isset( $member->language ) ) {
				$found = true;
				$value = $member->language;
			} elseif ( isset( $member->language_code ) ) {
				$found = true;
				$value = $member->language_code;
			}
		}
		return $found ? self::normalize_language( $value ) : null;
	}

	/** Build a bounded failure row suitable for later escaped admin output. */
	private function failure_record( $edge, $dependency_group_uuid, $required, $reason ) {
		return array(
			'dependency_group_uuid' => (string) $dependency_group_uuid,
			'language'              => is_array( $edge ) ? $edge['language'] : '',
			'kind'                  => is_array( $edge ) ? $edge['kind'] : '',
			'source_path'           => is_array( $edge ) ? $edge['source_path'] : '',
			'required'              => (bool) $required,
			'eligible'              => false,
			'reason'                => $this->bounded_reason( $reason ),
		);
	}

	/** Restrict resolver reasons to a short machine-readable token. */
	private function bounded_reason( $reason ) {
		$reason = is_scalar( $reason ) ? strtolower( trim( (string) $reason ) ) : '';
		$reason = preg_replace( '/[^a-z0-9_-]+/', '_', $reason );
		$reason = substr( trim( $reason, '_' ), 0, 80 );
		return '' !== $reason ? $reason : 'ineligible';
	}

	/** Clamp a constructor limit without relying on request sanitizers. */
	private function bounded_integer( $value, $minimum, $maximum ) {
		$value = (int) $value;
		return max( $minimum, min( $maximum, $value ) );
	}
}
