<?php
/**
 * Indexed translation groups and object relationships.
 *
 * Translation-group UUIDs are portable across sites. Local post, term, and
 * synthetic IDs are deliberately kept in a separate relationship row so an
 * importer can recreate every object before rebuilding the group membership.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Relationships {

	const DB_VERSION               = '1.0.0';
	const DB_VERSION_OPTION        = 'dsf_translation_relationships_db_version';
	const GROUP_TABLE_SUFFIX       = 'dsf_translation_groups';
	const RELATION_TABLE_SUFFIX    = 'dsf_translation_relationships';
	const MAX_KIND_LENGTH          = 16;
	const MAX_SUBTYPE_LENGTH       = 64;
	const MAX_LANGUAGE_LENGTH      = 35;
	const MAX_GROUP_MEMBERS        = 100;
	const UUID_GENERATION_ATTEMPTS = 3;

	/** @var self|null */
	private static $instance = null;

	/** @var wpdb|null */
	private $database;

	/** @var array<string,string[]> */
	private $adapter_subtypes = array();

	/**
	 * Approved first-party relationship adapters.
	 *
	 * Entries, orders, submissions, and operational WooCommerce records are
	 * intentionally absent. Add-on adapters can register additional bounded
	 * subtypes through register_adapter() or the documented filter.
	 *
	 * @var array<string,string[]>
	 */
	private $core_subtypes = array(
		'post'      => array(
			'post',
			'page',
			'dsf_layout',
			'dsf_form',
			'dsf_popup',
			'dsf_saved_block',
			'dsf_template',
			'dsf_product_template',
			'dsf_shop_template',
			'dsf_blog_template',
		),
		'term'      => array(
			'category',
			'post_tag',
		),
		'synthetic' => array(
			'notification_bar',
			'woo_product_overlay',
			'woo_variation_overlay',
			'woo_term_overlay',
			'store_page',
		),
	);

	/**
	 * The optional database argument is for isolated services/tests. Production
	 * callers normally use get_instance(), which uses the global wpdb object.
	 *
	 * @param wpdb|null $database Database adapter.
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
	 * Register one trusted adapter's object subtypes.
	 *
	 * Registration is all-or-nothing. Invalid values do not partially extend
	 * the allowlist.
	 *
	 * @param string          $object_kind Object kind.
	 * @param string|string[] $subtypes    Adapter subtypes.
	 * @return true|WP_Error
	 */
	public function register_adapter( $object_kind, $subtypes ) {
		$object_kind = $this->normalize_kind( $object_kind );
		if ( '' === $object_kind ) {
			return $this->error( 'dsf_translation_kind', 'The translation object kind is invalid.' );
		}

		$normalized = array();
		foreach ( (array) $subtypes as $subtype ) {
			$subtype = $this->normalize_subtype( $subtype );
			if ( '' === $subtype ) {
				return $this->error( 'dsf_translation_subtype', 'The translation object subtype is invalid.' );
			}
			$normalized[] = $subtype;
		}

		if ( empty( $normalized ) ) {
			return $this->error( 'dsf_translation_subtype', 'At least one translation object subtype is required.' );
		}

		$current = isset( $this->adapter_subtypes[ $object_kind ] ) ? $this->adapter_subtypes[ $object_kind ] : array();

		$this->adapter_subtypes[ $object_kind ] = array_values( array_unique( array_merge( $current, $normalized ) ) );
		return true;
	}

	/**
	 * Return the allowlisted subtypes for an object kind.
	 *
	 * @param string $object_kind Object kind.
	 * @return string[]
	 */
	public function get_allowed_subtypes( $object_kind ) {
		$object_kind = $this->normalize_kind( $object_kind );
		if ( '' === $object_kind ) {
			return array();
		}

		$core       = isset( $this->core_subtypes[ $object_kind ] ) ? $this->core_subtypes[ $object_kind ] : array();
		$registered = isset( $this->adapter_subtypes[ $object_kind ] ) ? $this->adapter_subtypes[ $object_kind ] : array();
		$subtypes   = array_merge( $core, $registered );

		/**
		 * Filters trusted relationship-adapter subtypes.
		 *
		 * The filter runs in server code; request data must never be passed into it
		 * as an allowlist. Values are normalized and bounded again after filtering.
		 *
		 * @param string[]                         $subtypes   Allowed subtypes.
		 * @param string                           $object_kind Object kind.
		 * @param DSF_Translation_Relationships    $service    Relationship service.
		 */
		$subtypes = apply_filters( 'dsf_translation_relationship_subtypes', $subtypes, $object_kind, $this );
		$clean    = array();
		foreach ( is_array( $subtypes ) ? $subtypes : array() as $subtype ) {
			$subtype = $this->normalize_subtype( $subtype );
			if ( '' !== $subtype ) {
				$clean[] = $subtype;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Return language identifiers supplied by the curated language registry.
	 *
	 * This service intentionally has no permissive fallback. Until the settings
	 * service attaches the filter, relationship creation fails closed.
	 *
	 * @return string[]
	 */
	public function get_allowed_languages() {
		/**
		 * Filters enabled, curated language identifiers accepted for new members.
		 *
		 * @param string[]                      $languages Enabled language IDs.
		 * @param DSF_Translation_Relationships $service   Relationship service.
		 */
		$languages = apply_filters( 'dsf_translation_relationship_languages', array(), $this );
		$clean     = array();
		foreach ( is_array( $languages ) ? $languages : array() as $language ) {
			$language = $this->normalize_language( $language );
			if ( '' !== $language ) {
				$clean[] = $language;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Create a new portable group and its first member.
	 *
	 * Supplying a UUID is intended for import. When absent, a fresh UUID v4 is
	 * generated and collisions are retried before any member is inserted.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @param string $language       Curated language ID.
	 * @param string $group_uuid     Optional portable group UUID.
	 * @return array|WP_Error
	 */
	public function create_group( $object_kind, $object_subtype, $object_id, $language, $group_uuid = '' ) {
		$member = $this->validate_new_member( $object_kind, $object_subtype, $object_id, $language );
		if ( $member instanceof WP_Error ) {
			return $member;
		}

		if ( '' !== $group_uuid ) {
			$group_uuid = $this->normalize_uuid( $group_uuid );
			if ( '' === $group_uuid ) {
				return $this->error( 'dsf_translation_group_uuid', 'The translation group UUID is invalid.' );
			}
			return $this->add_validated_member( $group_uuid, $member, true );
		}

		for ( $attempt = 0; $attempt < self::UUID_GENERATION_ATTEMPTS; $attempt++ ) {
			$generated = $this->normalize_uuid( wp_generate_uuid4() );
			if ( '' === $generated ) {
				continue;
			}
			$group = $this->insert_group( $generated, $member['object_kind'], $member['object_subtype'] );
			if ( $group instanceof WP_Error ) {
				return $group;
			}
			if ( ! $group['created'] ) {
				continue;
			}
			return $this->insert_member( $generated, $member );
		}

		return $this->error( 'dsf_translation_group_uuid', 'A unique translation group UUID could not be generated.' );
	}

	/**
	 * Add a member to an existing or imported portable group.
	 *
	 * Exact retries are idempotent. Conflicting object assignments and occupied
	 * language slots return distinct errors after the database unique constraint
	 * wins any race.
	 *
	 * @param string $group_uuid     Portable group UUID.
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @param string $language       Curated language ID.
	 * @return array|WP_Error
	 */
	public function add_member( $group_uuid, $object_kind, $object_subtype, $object_id, $language ) {
		$group_uuid = $this->normalize_uuid( $group_uuid );
		if ( '' === $group_uuid ) {
			return $this->error( 'dsf_translation_group_uuid', 'The translation group UUID is invalid.' );
		}

		$member = $this->validate_new_member( $object_kind, $object_subtype, $object_id, $language );
		if ( $member instanceof WP_Error ) {
			return $member;
		}
		return $this->add_validated_member( $group_uuid, $member, true );
	}

	/**
	 * Find a relationship by its local object identity.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @return array|null|WP_Error
	 */
	public function find_by_object( $object_kind, $object_subtype, $object_id ) {
		$identity = $this->validate_object_identity( $object_kind, $object_subtype, $object_id );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}

		$db           = $this->get_database();
		$table        = self::relationship_table_name( $db );
		$groups_table = self::group_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use the trusted wpdb prefix and fixed suffixes.
		$sql = $db->prepare( "SELECT relationship.id, relationship.group_uuid, relationship.object_kind, relationship.object_subtype, relationship.object_id, relationship.language, relationship.created_at_gmt, relationship.updated_at_gmt FROM {$table} AS relationship INNER JOIN {$groups_table} AS translation_group ON translation_group.group_uuid = relationship.group_uuid AND translation_group.object_kind = relationship.object_kind AND translation_group.object_subtype = relationship.object_subtype WHERE relationship.object_kind = %s AND relationship.object_subtype = %s AND relationship.object_id = %d LIMIT 1", $identity['object_kind'], $identity['object_subtype'], $identity['object_id'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; this indexed table is the relationship authority.
		$row = $db->get_row( $sql, ARRAY_A );
		return $this->format_member( $row );
	}

	/**
	 * Find the member occupying a language slot in a group.
	 *
	 * Disabled-but-stored languages remain readable for recovery. Creation is
	 * the boundary that requires the current curated language allowlist.
	 *
	 * @param string $group_uuid Portable group UUID.
	 * @param string $language   Language identifier.
	 * @return array|null|WP_Error
	 */
	public function find_member( $group_uuid, $language ) {
		$group_uuid = $this->normalize_uuid( $group_uuid );
		$language   = $this->normalize_language( $language );
		if ( '' === $group_uuid ) {
			return $this->error( 'dsf_translation_group_uuid', 'The translation group UUID is invalid.' );
		}
		if ( '' === $language ) {
			return $this->error( 'dsf_translation_language', 'The translation language is invalid.' );
		}

		$db           = $this->get_database();
		$table        = self::relationship_table_name( $db );
		$groups_table = self::group_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use the trusted wpdb prefix and fixed suffixes.
		$sql = $db->prepare( "SELECT relationship.id, relationship.group_uuid, relationship.object_kind, relationship.object_subtype, relationship.object_id, relationship.language, relationship.created_at_gmt, relationship.updated_at_gmt FROM {$table} AS relationship INNER JOIN {$groups_table} AS translation_group ON translation_group.group_uuid = relationship.group_uuid AND translation_group.object_kind = relationship.object_kind AND translation_group.object_subtype = relationship.object_subtype WHERE relationship.group_uuid = %s AND relationship.language = %s LIMIT 1", $group_uuid, $language );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; this indexed table is the relationship authority.
		$row = $db->get_row( $sql, ARRAY_A );
		return $this->format_member( $row );
	}

	/**
	 * List all members of one portable group.
	 *
	 * @param string $group_uuid Portable group UUID.
	 * @return array[]|WP_Error
	 */
	public function list_group( $group_uuid ) {
		$group_uuid = $this->normalize_uuid( $group_uuid );
		if ( '' === $group_uuid ) {
			return $this->error( 'dsf_translation_group_uuid', 'The translation group UUID is invalid.' );
		}

		$db           = $this->get_database();
		$table        = self::relationship_table_name( $db );
		$groups_table = self::group_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use the trusted wpdb prefix and fixed suffixes.
		$sql = $db->prepare( "SELECT relationship.id, relationship.group_uuid, relationship.object_kind, relationship.object_subtype, relationship.object_id, relationship.language, relationship.created_at_gmt, relationship.updated_at_gmt FROM {$table} AS relationship INNER JOIN {$groups_table} AS translation_group ON translation_group.group_uuid = relationship.group_uuid AND translation_group.object_kind = relationship.object_kind AND translation_group.object_subtype = relationship.object_subtype WHERE relationship.group_uuid = %s ORDER BY relationship.language ASC, relationship.id ASC LIMIT %d", $group_uuid, self::MAX_GROUP_MEMBERS );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; this indexed table is the relationship authority.
		$rows   = $db->get_results( $sql, ARRAY_A );
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$row = $this->format_member( $row );
			if ( null !== $row ) {
				$result[] = $row;
			}
		}
		return $result;
	}

	/**
	 * Whether recoverable relationship data exists for a language.
	 *
	 * This read intentionally accepts a stored-but-disabled language so settings
	 * code can prevent orphaning review state during removal.
	 *
	 * @param string $language Language identifier.
	 * @return bool|WP_Error
	 */
	public function has_members_for_language( $language ) {
		$language = $this->normalize_language( $language );
		if ( '' === $language ) {
			return $this->error( 'dsf_translation_language', 'The translation language is invalid.' );
		}

		$db    = $this->get_database();
		$table = self::relationship_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "SELECT id FROM {$table} WHERE language = %s LIMIT 1", $language );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; this indexed read protects recoverable relationship data.
		$row = $db->get_row( $sql, ARRAY_A );
		if ( null === $row && ! empty( $db->last_error ) ) {
			return $this->error( 'dsf_translation_read', 'Translation relationships could not be checked.' );
		}
		return is_array( $row ) && 0 < (int) ( $row['id'] ?? 0 );
	}

	/**
	 * Remove exactly one local member without deleting its group or siblings.
	 *
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @param int    $object_id      Local object ID.
	 * @return bool|WP_Error True when removed, false when already absent.
	 */
	public function remove_member( $object_kind, $object_subtype, $object_id ) {
		$identity = $this->validate_object_identity( $object_kind, $object_subtype, $object_id );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}

		$db    = $this->get_database();
		$table = self::relationship_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "DELETE FROM {$table} WHERE object_kind = %s AND object_subtype = %s AND object_id = %d LIMIT 1", $identity['object_kind'], $identity['object_subtype'], $identity['object_id'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; member deletion intentionally leaves the group row intact.
		$deleted = $db->query( $sql );
		if ( false === $deleted ) {
			return $this->error( 'dsf_translation_remove', 'The translation relationship could not be removed.' );
		}
		return 0 < (int) $deleted;
	}

	/**
	 * Create or upgrade both relationship tables.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::schema_sql( $wpdb ) as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Run the idempotent schema upgrade when its stored version is behind.
	 */
	public function maybe_install() {
		if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '' ), self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Return dbDelta-compatible schema SQL.
	 *
	 * A separate group table atomically fixes kind/subtype for a UUID. The
	 * relationship table then uses database unique indexes as the final authority
	 * for one object membership and one language slot per group.
	 *
	 * @param wpdb|null $database Database adapter.
	 * @return string[]
	 */
	public static function schema_sql( $database = null ) {
		if ( null === $database ) {
			global $wpdb;
			$database = $wpdb;
		}

		$groups          = self::group_table_name( $database );
		$relationships   = self::relationship_table_name( $database );
		$charset_collate = $database->get_charset_collate();

		$group_sql = "CREATE TABLE {$groups} (
			group_uuid char(36) NOT NULL,
			object_kind varchar(16) NOT NULL,
			object_subtype varchar(64) NOT NULL,
			created_at_gmt datetime NOT NULL,
			PRIMARY KEY  (group_uuid),
			KEY object_adapter (object_kind,object_subtype)
		) {$charset_collate};";

		$relationship_sql = "CREATE TABLE {$relationships} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_uuid char(36) NOT NULL,
			object_kind varchar(16) NOT NULL,
			object_subtype varchar(64) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			language varchar(35) NOT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_identity (object_kind,object_subtype,object_id),
			UNIQUE KEY group_language (group_uuid,language),
			KEY group_lookup (group_uuid),
			KEY language_lookup (language,object_kind,object_subtype)
		) {$charset_collate};";

		return array( $group_sql, $relationship_sql );
	}

	/**
	 * Return the group table name.
	 *
	 * @param wpdb|null $database Database adapter.
	 * @return string
	 */
	public static function group_table_name( $database = null ) {
		if ( null === $database ) {
			global $wpdb;
			$database = $wpdb;
		}
		return $database->prefix . self::GROUP_TABLE_SUFFIX;
	}

	/**
	 * Return the relationship table name.
	 *
	 * @param wpdb|null $database Database adapter.
	 * @return string
	 */
	public static function relationship_table_name( $database = null ) {
		if ( null === $database ) {
			global $wpdb;
			$database = $wpdb;
		}
		return $database->prefix . self::RELATION_TABLE_SUFFIX;
	}

	/**
	 * Normalize a BCP 47-style curated language identifier.
	 *
	 * @param mixed $language Raw language value.
	 * @return string Empty when malformed or oversized.
	 */
	public static function normalize_language_id( $language ) {
		if ( ! is_scalar( $language ) ) {
			return '';
		}
		$language = str_replace( '_', '-', trim( (string) $language ) );
		if ( '' === $language || self::MAX_LANGUAGE_LENGTH < strlen( $language ) || ! preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language ) ) {
			return '';
		}

		$parts    = explode( '-', $language );
		$parts[0] = strtolower( $parts[0] );
		$count    = count( $parts );
		for ( $index = 1; $index < $count; $index++ ) {
			$part = $parts[ $index ];
			if ( 4 === strlen( $part ) && ctype_alpha( $part ) ) {
				$parts[ $index ] = ucfirst( strtolower( $part ) );
			} elseif ( 2 === strlen( $part ) && ctype_alpha( $part ) ) {
				$parts[ $index ] = strtoupper( $part );
			} else {
				$parts[ $index ] = strtolower( $part );
			}
		}
		return implode( '-', $parts );
	}

	/**
	 * Normalize a canonical UUID without accepting shortened/opaque identifiers.
	 *
	 * @param mixed $uuid Raw UUID.
	 * @return string Empty when malformed.
	 */
	public static function normalize_group_uuid( $uuid ) {
		if ( ! is_scalar( $uuid ) ) {
			return '';
		}
		$uuid = strtolower( trim( (string) $uuid ) );
		return preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Add a prevalidated member after atomically establishing group metadata.
	 *
	 * @param string $group_uuid Portable group UUID.
	 * @param array  $member     Validated member data.
	 * @param bool   $allow_existing_group Whether the group may already exist.
	 * @return array|WP_Error
	 */
	private function add_validated_member( $group_uuid, $member, $allow_existing_group ) {
		$group = $this->insert_group( $group_uuid, $member['object_kind'], $member['object_subtype'] );
		if ( $group instanceof WP_Error ) {
			return $group;
		}
		if ( ! $allow_existing_group && ! $group['created'] ) {
			return $this->error( 'dsf_translation_group_exists', 'The translation group already exists.' );
		}
		return $this->insert_member( $group_uuid, $member );
	}

	/**
	 * Insert group metadata or verify the winner of a concurrent insert.
	 *
	 * @param string $group_uuid     Portable group UUID.
	 * @param string $object_kind    Object kind.
	 * @param string $object_subtype Object subtype.
	 * @return array|WP_Error
	 */
	private function insert_group( $group_uuid, $object_kind, $object_subtype ) {
		$db      = $this->get_database();
		$table   = self::group_table_name( $db );
		$created = $this->current_time();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "INSERT IGNORE INTO {$table} (group_uuid, object_kind, object_subtype, created_at_gmt) VALUES (%s, %s, %s, %s)", $group_uuid, $object_kind, $object_subtype, $created );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; INSERT IGNORE resolves concurrent group creation against the primary key.
		$inserted = $db->query( $sql );
		if ( false === $inserted ) {
			return $this->error( 'dsf_translation_group_write', 'The translation group could not be stored.' );
		}
		if ( 0 < (int) $inserted ) {
			return array( 'created' => true );
		}

		$existing = $this->find_group( $group_uuid );
		if ( ! is_array( $existing ) ) {
			return $this->error( 'dsf_translation_group_write', 'The translation group could not be verified.' );
		}
		if ( $object_kind !== $existing['object_kind'] || $object_subtype !== $existing['object_subtype'] ) {
			return $this->error( 'dsf_translation_group_type', 'A translation group cannot mix object adapters.' );
		}
		return array( 'created' => false );
	}

	/**
	 * Insert a member and classify the database winner after a unique-key race.
	 *
	 * @param string $group_uuid Portable group UUID.
	 * @param array  $member     Validated member data.
	 * @return array|WP_Error
	 */
	private function insert_member( $group_uuid, $member ) {
		$db      = $this->get_database();
		$table   = self::relationship_table_name( $db );
		$created = $this->current_time();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare(
			"INSERT INTO {$table} (group_uuid, object_kind, object_subtype, object_id, language, created_at_gmt, updated_at_gmt) VALUES (%s, %s, %s, %d, %s, %s, %s)",
			$group_uuid,
			$member['object_kind'],
			$member['object_subtype'],
			$member['object_id'],
			$member['language'],
			$created,
			$created
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; unique indexes are the atomic membership authority.
		$inserted = $db->query( $sql );
		if ( false !== $inserted && 0 < (int) $inserted ) {
			return array(
				'id'             => isset( $db->insert_id ) ? (int) $db->insert_id : 0,
				'group_uuid'     => $group_uuid,
				'object_kind'    => $member['object_kind'],
				'object_subtype' => $member['object_subtype'],
				'object_id'      => $member['object_id'],
				'language'       => $member['language'],
				'created_at_gmt' => $created,
				'updated_at_gmt' => $created,
			);
		}

		// A preflight SELECT is never the authority. These reads only classify the
		// unique index that won between validation and INSERT.
		$by_object = $this->find_by_object( $member['object_kind'], $member['object_subtype'], $member['object_id'] );
		if ( is_array( $by_object ) ) {
			if ( $group_uuid === $by_object['group_uuid'] && $member['language'] === $by_object['language'] ) {
				return $by_object;
			}
			return $this->error( 'dsf_translation_object_exists', 'This object already belongs to a translation group.' );
		}

		$by_language = $this->find_member( $group_uuid, $member['language'] );
		if ( is_array( $by_language ) ) {
			return $this->error( 'dsf_translation_language_exists', 'This translation group already has a member for the language.' );
		}

		return $this->error( 'dsf_translation_write', 'The translation relationship could not be stored.' );
	}

	/**
	 * Find the immutable adapter metadata for a group.
	 *
	 * @param string $group_uuid Portable group UUID.
	 * @return array|null
	 */
	private function find_group( $group_uuid ) {
		$db    = $this->get_database();
		$table = self::group_table_name( $db );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted wpdb prefix and a fixed suffix.
		$sql = $db->prepare( "SELECT group_uuid, object_kind, object_subtype, created_at_gmt FROM {$table} WHERE group_uuid = %s LIMIT 1", $group_uuid );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is prepared above; this indexed read verifies the group primary-key winner.
		$row = $db->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$uuid    = $this->normalize_uuid( isset( $row['group_uuid'] ) ? $row['group_uuid'] : '' );
		$kind    = $this->normalize_kind( isset( $row['object_kind'] ) ? $row['object_kind'] : '' );
		$subtype = $this->normalize_subtype( isset( $row['object_subtype'] ) ? $row['object_subtype'] : '' );
		if ( '' === $uuid || '' === $kind || '' === $subtype ) {
			return null;
		}
		return array(
			'group_uuid'     => $uuid,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'created_at_gmt' => isset( $row['created_at_gmt'] ) ? (string) $row['created_at_gmt'] : '',
		);
	}

	/**
	 * Validate every field required for a new member.
	 *
	 * @param mixed $object_kind    Object kind.
	 * @param mixed $object_subtype Object subtype.
	 * @param mixed $object_id      Local object ID.
	 * @param mixed $language       Curated language ID.
	 * @return array|WP_Error
	 */
	private function validate_new_member( $object_kind, $object_subtype, $object_id, $language ) {
		$identity = $this->validate_object_identity( $object_kind, $object_subtype, $object_id );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}

		$language = $this->normalize_language( $language );
		if ( '' === $language || ! in_array( $language, $this->get_allowed_languages(), true ) ) {
			return $this->error( 'dsf_translation_language', 'The translation language is not enabled.' );
		}
		$identity['language'] = $language;

		$valid_object = $this->validate_object_reference( $identity );
		if ( $valid_object instanceof WP_Error ) {
			return $valid_object;
		}
		return $identity;
	}

	/**
	 * Validate and normalize a local object identity.
	 *
	 * @param mixed $object_kind    Object kind.
	 * @param mixed $object_subtype Object subtype.
	 * @param mixed $object_id      Local object ID.
	 * @return array|WP_Error
	 */
	private function validate_object_identity( $object_kind, $object_subtype, $object_id ) {
		$object_kind = $this->normalize_kind( $object_kind );
		if ( '' === $object_kind ) {
			return $this->error( 'dsf_translation_kind', 'The translation object kind is invalid.' );
		}

		$object_subtype = $this->normalize_subtype( $object_subtype );
		if ( '' === $object_subtype || ! in_array( $object_subtype, $this->get_allowed_subtypes( $object_kind ), true ) ) {
			return $this->error( 'dsf_translation_subtype', 'The translation object subtype is not allowed.' );
		}

		$object_id = $this->normalize_object_id( $object_id );
		if ( 0 === $object_id ) {
			return $this->error( 'dsf_translation_object', 'The translation object ID is invalid.' );
		}

		return array(
			'object_kind'    => $object_kind,
			'object_subtype' => $object_subtype,
			'object_id'      => $object_id,
		);
	}

	/**
	 * Confirm a new relationship points at the adapter object it claims.
	 *
	 * Reads and removals intentionally do not repeat this check so a deletion
	 * hook or recovery tool can remove a stale relationship after its source
	 * object has already disappeared.
	 *
	 * @param array $identity Normalized object identity.
	 * @return true|WP_Error
	 */
	private function validate_object_reference( $identity ) {
		$valid = false;
		if ( 'post' === $identity['object_kind'] ) {
			$post  = get_post( $identity['object_id'] );
			$valid = is_object( $post ) && isset( $post->post_type ) && $identity['object_subtype'] === $this->normalize_subtype( $post->post_type );
		} elseif ( 'term' === $identity['object_kind'] ) {
			$term  = get_term( $identity['object_id'], $identity['object_subtype'] );
			$valid = is_object( $term ) && ! ( $term instanceof WP_Error ) && isset( $term->taxonomy ) && $identity['object_subtype'] === $this->normalize_subtype( $term->taxonomy );
		}

		/**
		 * Filters adapter-specific object validation for relationship creation.
		 *
		 * Synthetic adapters must explicitly return true. Trusted adapters may also
		 * add stronger validation for post/term objects or return a WP_Error.
		 *
		 * @param bool|WP_Error                    $valid    Current validation result.
		 * @param array                            $identity Normalized object identity.
		 * @param DSF_Translation_Relationships    $service  Relationship service.
		 */
		$valid = apply_filters( 'dsf_translation_relationship_object_is_valid', $valid, $identity, $this );
		if ( $valid instanceof WP_Error ) {
			return $valid;
		}
		return true === $valid ? true : $this->error( 'dsf_translation_object_missing', 'The translation object does not exist or does not match its adapter.' );
	}

	/**
	 * Convert a database row into a bounded public result.
	 *
	 * @param mixed $row Database row.
	 * @return array|null
	 */
	private function format_member( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$uuid      = $this->normalize_uuid( isset( $row['group_uuid'] ) ? $row['group_uuid'] : '' );
		$kind      = $this->normalize_kind( isset( $row['object_kind'] ) ? $row['object_kind'] : '' );
		$subtype   = $this->normalize_subtype( isset( $row['object_subtype'] ) ? $row['object_subtype'] : '' );
		$object_id = $this->normalize_object_id( isset( $row['object_id'] ) ? $row['object_id'] : 0 );
		$language  = $this->normalize_language( isset( $row['language'] ) ? $row['language'] : '' );
		if ( '' === $uuid || '' === $kind || '' === $subtype || 0 === $object_id || '' === $language ) {
			return null;
		}

		return array(
			'id'             => isset( $row['id'] ) ? max( 0, (int) $row['id'] ) : 0,
			'group_uuid'     => $uuid,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'object_id'      => $object_id,
			'language'       => $language,
			'created_at_gmt' => isset( $row['created_at_gmt'] ) ? (string) $row['created_at_gmt'] : '',
			'updated_at_gmt' => isset( $row['updated_at_gmt'] ) ? (string) $row['updated_at_gmt'] : '',
		);
	}

	/** @return wpdb */
	private function get_database() {
		if ( null !== $this->database ) {
			return $this->database;
		}
		global $wpdb;
		return $wpdb;
	}

	/** @return string */
	private function current_time() {
		return (string) current_time( 'mysql', true );
	}

	/** @return string */
	private function normalize_kind( $value ) {
		$value = $this->normalize_key( $value, self::MAX_KIND_LENGTH );
		return in_array( $value, array( 'post', 'term', 'synthetic' ), true ) ? $value : '';
	}

	/** @return string */
	private function normalize_subtype( $value ) {
		return $this->normalize_key( $value, self::MAX_SUBTYPE_LENGTH );
	}

	/** @return string */
	private function normalize_key( $value, $max_length ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value || $max_length < strlen( $value ) || ! preg_match( '/^[a-z0-9_-]+$/', $value ) ) {
			return '';
		}
		return $value;
	}

	/** @return int */
	private function normalize_object_id( $value ) {
		if ( is_int( $value ) ) {
			return 0 < $value ? $value : 0;
		}
		if ( ! is_string( $value ) ) {
			return 0;
		}
		$value = trim( $value );
		if ( '' === $value || ! ctype_digit( $value ) ) {
			return 0;
		}
		$value = (int) $value;
		return 0 < $value ? $value : 0;
	}

	/** @return string */
	private function normalize_language( $value ) {
		return self::normalize_language_id( $value );
	}

	/** @return string */
	private function normalize_uuid( $value ) {
		return self::normalize_group_uuid( $value );
	}

	/** @return WP_Error */
	private function error( $code, $message ) {
		return new WP_Error( $code, $message );
	}
}
