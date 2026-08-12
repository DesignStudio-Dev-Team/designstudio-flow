<?php
/**
 * Translation review facts and visitor-content fingerprints.
 *
 * Adapters are responsible for supplying a sanitized visitor-facing payload.
 * This service applies a second, explicit exclusion policy, canonicalizes the
 * payload, and records human review against the resulting versioned hash.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Workflow {

	const DB_VERSION                  = '1.0.0';
	const TABLE_SUFFIX                = 'dsf_translation_workflow';
	const FINGERPRINT_SCHEMA_VERSION  = 1;
	const MAX_FINGERPRINT_DEPTH       = 20;
	const MAX_FINGERPRINT_NODES       = 5000;
	const MAX_FINGERPRINT_STRING      = 262144;
	const MAX_FINGERPRINT_TOTAL_BYTES = 1048576;

	/** @var object|null */
	private $wpdb;

	/** @var string */
	private $table;

	/** @var callable|null */
	private $language_validator;

	/** @var callable|null */
	private $current_user_resolver;

	/** @var callable|null */
	private $clock;

	/**
	 * Constructor.
	 *
	 * Supported arguments are `wpdb`, `table_name`, `language_validator`,
	 * `current_user_resolver`, and `clock`. The language validator must return
	 * strict `true` for an enabled language. Mutating APIs separately require an
	 * action-specific authorization callback.
	 *
	 * @param array $args Dependency-injection arguments.
	 */
	public function __construct( $args = array() ) {
		global $wpdb;

		$args                        = is_array( $args ) ? $args : array();
		$this->wpdb                  = isset( $args['wpdb'] ) ? $args['wpdb'] : ( isset( $wpdb ) ? $wpdb : null );
		$this->table                 = ! empty( $args['table_name'] ) ? (string) $args['table_name'] : '';
		$this->language_validator    = isset( $args['language_validator'] ) && is_callable( $args['language_validator'] ) ? $args['language_validator'] : null;
		$this->current_user_resolver = isset( $args['current_user_resolver'] ) && is_callable( $args['current_user_resolver'] ) ? $args['current_user_resolver'] : null;
		$this->clock                 = isset( $args['clock'] ) && is_callable( $args['clock'] ) ? $args['clock'] : null;
	}

	/** Return the site-specific workflow table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Return the dbDelta schema. Loading and executing it remains the bootstrap
	 * installer's responsibility so this service has no activation side effects.
	 *
	 * @param object|null $wpdb_instance Optional wpdb-compatible object.
	 * @return string
	 */
	public static function schema_sql( $wpdb_instance = null ) {
		global $wpdb;
		$db = $wpdb_instance ? $wpdb_instance : ( isset( $wpdb ) ? $wpdb : null );
		if ( ! $db || ! isset( $db->prefix ) || ! is_callable( array( $db, 'get_charset_collate' ) ) ) {
			return '';
		}

		$table           = $db->prefix . self::TABLE_SUFFIX;
		$charset_collate = $db->get_charset_collate();

		return "CREATE TABLE {$table} (
			group_uuid char(36) NOT NULL,
			language varchar(20) NOT NULL,
			machine_prefilled tinyint(1) unsigned NOT NULL DEFAULT 0,
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at_gmt datetime NULL DEFAULT NULL,
			reviewed_source_fingerprint char(64) NOT NULL DEFAULT '',
			reviewed_fingerprint_schema smallint(5) unsigned NOT NULL DEFAULT 0,
			critical_change tinyint(1) unsigned NOT NULL DEFAULT 0,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (group_uuid,language),
			KEY reviewer (reviewer_id),
			KEY review_state (language,machine_prefilled,critical_change)
		) {$charset_collate};";
	}

	/** Create or upgrade the workflow table. Called by the plugin installer. */
	public static function install() {
		$sql = self::schema_sql();
		if ( '' === $sql ) {
			return new WP_Error( 'dsf_workflow_database', 'The translation workflow database is unavailable.' );
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
		update_option( 'dsf_translation_workflow_db_version', self::DB_VERSION, false );
		return true;
	}

	/**
	 * Build a complete fingerprint record from sanitized adapter output.
	 *
	 * @param array $payload                Sanitized visitor-facing fields only.
	 * @param int   $adapter_schema_version Adapter payload schema version.
	 * @return array|WP_Error
	 */
	public static function build_fingerprint( $payload, $adapter_schema_version = 1 ) {
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'dsf_fingerprint_payload', 'The fingerprint payload must be an array.' );
		}

		$adapter_schema_version = filter_var( $adapter_schema_version, FILTER_VALIDATE_INT );
		if ( false === $adapter_schema_version || $adapter_schema_version < 1 || $adapter_schema_version > 65535 ) {
			return new WP_Error( 'dsf_fingerprint_schema', 'The adapter fingerprint schema is invalid.' );
		}

		$state = array(
			'nodes' => 0,
			'bytes' => 0,
		);
		$clean = self::normalize_fingerprint_value( $payload, 0, $state );
		if ( $clean instanceof WP_Error ) {
			return $clean;
		}

		$envelope = array(
			'adapter_schema'     => (int) $adapter_schema_version,
			'fingerprint_schema' => self::FINGERPRINT_SCHEMA_VERSION,
			'payload'            => $clean,
		);
		$json     = wp_json_encode( $envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
		if ( false === $json ) {
			return new WP_Error( 'dsf_fingerprint_encoding', 'The fingerprint payload could not be encoded.' );
		}

		return array(
			'fingerprint' => hash( 'sha256', $json ),
			'schema'      => self::FINGERPRINT_SCHEMA_VERSION,
		);
	}

	/** Convenience wrapper returning only the hash. */
	public static function fingerprint( $payload, $adapter_schema_version = 1 ) {
		$record = self::build_fingerprint( $payload, $adapter_schema_version );
		return $record instanceof WP_Error ? $record : $record['fingerprint'];
	}

	/**
	 * Whether a key is forbidden in a visitor-content fingerprint.
	 *
	 * Adapters should omit these fields themselves. This policy is defense in
	 * depth so credentials, snapshots, IDs, submissions, and commerce operations
	 * cannot make their way into a hash by mistake.
	 *
	 * @param string $key Payload key.
	 * @return bool
	 */
	public static function is_excluded_key( $key ) {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $key ) );
		$exact      = array(
			'id',
			'ids',
			'postid',
			'termid',
			'userid',
			'authorid',
			'attachmentid',
			'formid',
			'savedblockid',
			'templateid',
			'relationshipid',
			'groupid',
			'credential',
			'credentials',
			'secret',
			'secrets',
			'password',
			'passphrase',
			'apikey',
			'accesstoken',
			'refreshtoken',
			'authtoken',
			'authorization',
			'dsfhtmlsnapshot',
			'htmlsnapshot',
			'snapshot',
			'snapshots',
			'history',
			'historyrecords',
			'revisions',
			'submission',
			'submissions',
			'entry',
			'entries',
			'customer',
			'customers',
			'order',
			'orders',
			'billing',
			'shipping',
			'sku',
			'price',
			'regularprice',
			'saleprice',
			'inventory',
			'stock',
			'stockquantity',
			'stockstatus',
			'tax',
			'taxclass',
			'downloads',
			'downloadablefiles',
			'payment',
			'payments',
			'status',
			'createdat',
			'updatedat',
			'modifiedat',
			'createdby',
			'updatedby',
		);

		if ( in_array( $normalized, $exact, true ) ) {
			return true;
		}

		return (bool) preg_match( '/(?:password|passwd|secret|credential|apikey|accesstoken|refreshtoken)$/', $normalized )
			|| (bool) preg_match( '/(?:post|term|user|author|attachment|form|block|template|relationship|group|header|footer|popup|parent|product|variation|category|tag|object|source|target|preview)ids?$/', $normalized );
	}

	/**
	 * Record a human review after calculating the current source fingerprint.
	 *
	 * The callback is mandatory and receives `(action, group UUID, language)`.
	 * It must verify the exact target object's broad and object-level capabilities
	 * and return strict `true`. HTTP endpoints must verify their action nonce
	 * before calling this service.
	 *
	 * @return array|WP_Error Stored review facts.
	 */
	public function record_review_from_payload( $group_uuid, $language, $source_payload, $adapter_schema_version, $authorization_callback ) {
		$record = self::build_fingerprint( $source_payload, $adapter_schema_version );
		if ( $record instanceof WP_Error ) {
			return $record;
		}
		return $this->record_review( $group_uuid, $language, $record['fingerprint'], $record['schema'], $authorization_callback );
	}

	/**
	 * Record review against an already calculated current source fingerprint.
	 *
	 * @return array|WP_Error Stored review facts.
	 */
	public function record_review( $group_uuid, $language, $source_fingerprint, $fingerprint_schema, $authorization_callback ) {
		$identity = $this->validate_identity( $group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		$fingerprint_schema = filter_var( $fingerprint_schema, FILTER_VALIDATE_INT );
		if ( ! self::is_valid_fingerprint( $source_fingerprint ) || false === $fingerprint_schema || self::FINGERPRINT_SCHEMA_VERSION !== $fingerprint_schema ) {
			return new WP_Error( 'dsf_review_fingerprint', 'The current source fingerprint is invalid.' );
		}

		$authorized = $this->authorize( $authorization_callback, 'review', $identity['group_uuid'], $identity['language'] );
		if ( $authorized instanceof WP_Error ) {
			return $authorized;
		}

		$reviewer_id = $this->current_user_resolver ? call_user_func( $this->current_user_resolver ) : get_current_user_id();
		$reviewer_id = absint( $reviewer_id );
		if ( ! $reviewer_id ) {
			return new WP_Error( 'dsf_review_user', 'A signed-in reviewer is required.' );
		}

		$reviewed_at = $this->now_gmt();
		$db          = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table = $this->current_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
		$sql = $db->prepare(
			"INSERT INTO {$table} (group_uuid, language, machine_prefilled, reviewer_id, reviewed_at_gmt, reviewed_source_fingerprint, reviewed_fingerprint_schema, critical_change, updated_at_gmt)
			VALUES (%s, %s, 0, %d, %s, %s, %d, 0, %s)
			ON DUPLICATE KEY UPDATE machine_prefilled = 0, reviewer_id = VALUES(reviewer_id), reviewed_at_gmt = VALUES(reviewed_at_gmt), reviewed_source_fingerprint = VALUES(reviewed_source_fingerprint), reviewed_fingerprint_schema = VALUES(reviewed_fingerprint_schema), critical_change = 0, updated_at_gmt = VALUES(updated_at_gmt)",
			$identity['group_uuid'],
			$identity['language'],
			$reviewer_id,
			$reviewed_at,
			strtolower( $source_fingerprint ),
			self::FINGERPRINT_SCHEMA_VERSION,
			$reviewed_at
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is fully prepared above; review facts require immediate consistency.
		if ( false === $db->query( $sql ) ) {
			return new WP_Error( 'dsf_review_database', 'The translation review could not be recorded.' );
		}

		return array(
			'group_uuid'                  => $identity['group_uuid'],
			'language'                    => $identity['language'],
			'machine_prefilled'           => false,
			'reviewer_id'                 => $reviewer_id,
			'reviewed_at_gmt'             => $reviewed_at,
			'reviewed_source_fingerprint' => strtolower( $source_fingerprint ),
			'reviewed_fingerprint_schema' => self::FINGERPRINT_SCHEMA_VERSION,
			'critical_change'             => false,
		);
	}

	/** Mark or clear the machine-prefilled fact through an authorized mutation. */
	public function set_machine_prefilled( $group_uuid, $language, $value, $authorization_callback ) {
		$value = $this->normalize_boolean_fact( $value );
		return $value instanceof WP_Error ? $value : $this->set_boolean_fact( $group_uuid, $language, 'machine_prefilled', $value, $authorization_callback );
	}

	/** Mark or clear an explicitly translation-critical source change. */
	public function set_critical_change( $group_uuid, $language, $value, $authorization_callback ) {
		$value = $this->normalize_boolean_fact( $value );
		return $value instanceof WP_Error ? $value : $this->set_boolean_fact( $group_uuid, $language, 'critical_change', $value, $authorization_callback );
	}

	/**
	 * Clear approval after the translated object itself changes.
	 *
	 * Source-change staleness is derived from the reviewed source fingerprint;
	 * this explicit API covers edits to the already-reviewed target translation.
	 */
	public function clear_review( $group_uuid, $language, $authorization_callback ) {
		$identity = $this->validate_identity( $group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		$authorized = $this->authorize( $authorization_callback, 'clear_review', $identity['group_uuid'], $identity['language'] );
		if ( $authorized instanceof WP_Error ) {
			return $authorized;
		}
		$db = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table = $this->current_table();
		$now   = $this->now_gmt();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
		$sql = $db->prepare(
			"INSERT INTO {$table} (group_uuid, language, updated_at_gmt) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE reviewer_id = 0, reviewed_at_gmt = NULL, reviewed_source_fingerprint = '', reviewed_fingerprint_schema = 0, updated_at_gmt = VALUES(updated_at_gmt)",
			$identity['group_uuid'],
			$identity['language'],
			$now
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is fully prepared above; workflow facts require immediate consistency.
		if ( false === $db->query( $sql ) ) {
			return new WP_Error( 'dsf_workflow_database', 'The translation review could not be cleared.' );
		}
		return true;
	}

	/** Return persisted facts, or safe empty facts when none exist. */
	public function get_facts( $group_uuid, $language ) {
		$identity = $this->validate_identity( $group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		$db = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table = $this->current_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is created from the trusted wpdb prefix or installer injection.
		$sql = $db->prepare( "SELECT machine_prefilled, reviewer_id, reviewed_at_gmt, reviewed_source_fingerprint, reviewed_fingerprint_schema, critical_change FROM {$table} WHERE group_uuid = %s AND language = %s", $identity['group_uuid'], $identity['language'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared above and workflow state must be current.
		$row = $db->get_row( $sql, ARRAY_A );

		$facts = self::empty_facts( $identity['group_uuid'], $identity['language'] );
		if ( ! is_array( $row ) ) {
			return $facts;
		}
		$facts['machine_prefilled']           = ! empty( $row['machine_prefilled'] );
		$facts['reviewer_id']                 = absint( $row['reviewer_id'] ?? 0 );
		$facts['reviewed_at_gmt']             = self::valid_mysql_datetime( $row['reviewed_at_gmt'] ?? '' ) ? (string) $row['reviewed_at_gmt'] : '';
		$facts['reviewed_source_fingerprint'] = self::is_valid_fingerprint( $row['reviewed_source_fingerprint'] ?? '' ) ? strtolower( $row['reviewed_source_fingerprint'] ) : '';
		$facts['reviewed_fingerprint_schema'] = max( 0, (int) ( $row['reviewed_fingerprint_schema'] ?? 0 ) );
		$facts['critical_change']             = ! empty( $row['critical_change'] );
		return $facts;
	}

	/**
	 * Derive workflow state from facts plus current adapter/publish checks.
	 *
	 * Required context keys are `exists`, `facts`, `current_source_fingerprint`,
	 * `current_fingerprint_schema`, `dependencies_eligible`, `route_valid`,
	 * `integrity_valid`, `content_ready`, and `required_fields_confirmed`.
	 * `is_public` describes the object's current public state. The separate
	 * `can_publish` and `retain_public` values encode the approved stale policy.
	 *
	 * @param array $context Current object and validation facts.
	 * @return array
	 */
	public static function derive_status( $context ) {
		$context = is_array( $context ) ? $context : array();
		$exists  = ! empty( $context['exists'] );
		$facts   = isset( $context['facts'] ) && is_array( $context['facts'] ) ? $context['facts'] : array();

		if ( ! $exists ) {
			return array(
				'status'         => 'missing',
				'flags'          => array( 'missing' ),
				'review_current' => false,
				'can_publish'    => false,
				'retain_public'  => false,
			);
		}

		$current_fingerprint  = isset( $context['current_source_fingerprint'] ) && is_string( $context['current_source_fingerprint'] ) ? strtolower( $context['current_source_fingerprint'] ) : '';
		$current_schema       = (int) ( $context['current_fingerprint_schema'] ?? 0 );
		$reviewed_fingerprint = isset( $facts['reviewed_source_fingerprint'] ) && is_string( $facts['reviewed_source_fingerprint'] ) ? strtolower( $facts['reviewed_source_fingerprint'] ) : '';
		$reviewed_schema      = (int) ( $facts['reviewed_fingerprint_schema'] ?? 0 );
		$reviewed_at          = isset( $facts['reviewed_at_gmt'] ) && is_string( $facts['reviewed_at_gmt'] ) ? $facts['reviewed_at_gmt'] : '';
		$has_review           = ! empty( $facts['reviewer_id'] ) && self::valid_mysql_datetime( $reviewed_at ) && self::is_valid_fingerprint( $reviewed_fingerprint ) && $reviewed_schema > 0;
		$review_current       = $has_review
			&& self::is_valid_fingerprint( $current_fingerprint )
			&& $current_schema === $reviewed_schema
			&& hash_equals( $reviewed_fingerprint, $current_fingerprint );
		$blocked              = empty( $context['dependencies_eligible'] ) || empty( $context['route_valid'] ) || empty( $context['integrity_valid'] );
		$content_ready        = ! empty( $context['content_ready'] ) && ! empty( $context['required_fields_confirmed'] );
		$machine              = ! empty( $facts['machine_prefilled'] );
		$critical             = ! empty( $facts['critical_change'] );
		$source_changed       = $critical || ( $has_review && ! $review_current );
		$is_public            = ! empty( $context['is_public'] );
		$allow_stale_public   = ! array_key_exists( 'allow_stale_public', $context ) || ! empty( $context['allow_stale_public'] );
		$can_publish          = ! $blocked && $content_ready && $review_current && ! $machine && ! $critical;
		$retain_public        = $is_public && ! $blocked && $content_ready && ( $can_publish || ( $source_changed && ! $critical && $allow_stale_public ) );

		$flags = array();
		if ( $blocked ) {
			$flags[] = 'blocked';
		}
		if ( $source_changed ) {
			$flags[] = 'source_changed';
		}
		if ( $machine ) {
			$flags[] = 'machine_prefilled';
		}
		if ( $review_current ) {
			$flags[] = 'reviewed';
		}
		if ( $can_publish && $is_public ) {
			$flags[] = 'published';
		}
		if ( ! $has_review && ! $machine && ! $blocked && $content_ready ) {
			$flags[] = 'ready_for_review';
		}
		if ( empty( $flags ) ) {
			$flags[] = 'draft';
		}

		if ( $blocked ) {
			$status = 'blocked';
		} elseif ( $source_changed ) {
			$status = 'source_changed';
		} elseif ( $can_publish && $is_public ) {
			$status = 'published';
		} elseif ( $machine ) {
			$status = 'machine_prefilled';
		} elseif ( $review_current ) {
			$status = 'reviewed';
		} elseif ( $content_ready ) {
			$status = 'ready_for_review';
		} else {
			$status = 'draft';
		}

		return array(
			'status'         => $status,
			'flags'          => array_values( array_unique( $flags ) ),
			'review_current' => $review_current,
			'can_publish'    => $can_publish,
			'retain_public'  => $retain_public,
		);
	}

	/** Return a safe empty fact record. */
	public static function empty_facts( $group_uuid = '', $language = '' ) {
		return array(
			'group_uuid'                  => (string) $group_uuid,
			'language'                    => (string) $language,
			'machine_prefilled'           => false,
			'reviewer_id'                 => 0,
			'reviewed_at_gmt'             => '',
			'reviewed_source_fingerprint' => '',
			'reviewed_fingerprint_schema' => 0,
			'critical_change'             => false,
		);
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

	/** Whether a value is a lowercase or uppercase SHA-256 hash. */
	public static function is_valid_fingerprint( $fingerprint ) {
		return is_string( $fingerprint ) && (bool) preg_match( '/^[a-f0-9]{64}$/i', $fingerprint );
	}

	/** Recursive canonicalization with explicit size and type bounds. */
	private static function normalize_fingerprint_value( $value, $depth, &$state ) {
		if ( $depth > self::MAX_FINGERPRINT_DEPTH ) {
			return new WP_Error( 'dsf_fingerprint_depth', 'The fingerprint payload is nested too deeply.' );
		}
		++$state['nodes'];
		if ( $state['nodes'] > self::MAX_FINGERPRINT_NODES ) {
			return new WP_Error( 'dsf_fingerprint_nodes', 'The fingerprint payload contains too many values.' );
		}

		if ( is_string( $value ) ) {
			$bytes = strlen( $value );
			if ( $bytes > self::MAX_FINGERPRINT_STRING ) {
				return new WP_Error( 'dsf_fingerprint_string', 'A fingerprint value is too large.' );
			}
			$state['bytes'] += $bytes;
			if ( $state['bytes'] > self::MAX_FINGERPRINT_TOTAL_BYTES ) {
				return new WP_Error( 'dsf_fingerprint_size', 'The fingerprint payload is too large.' );
			}
			return str_replace( array( "\r\n", "\r" ), "\n", $value );
		}
		if ( is_int( $value ) || is_bool( $value ) || null === $value ) {
			return $value;
		}
		if ( is_float( $value ) ) {
			return is_finite( $value ) ? $value : new WP_Error( 'dsf_fingerprint_number', 'The fingerprint payload contains an invalid number.' );
		}
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'dsf_fingerprint_type', 'The fingerprint payload contains an unsupported value.' );
		}

		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( array() === $value ) {
			$is_list = true;
		}
		$normalized = array();
		foreach ( $value as $key => $child ) {
			if ( ! $is_list ) {
				$key = (string) $key;
				if ( '' === $key || strlen( $key ) > 191 ) {
					return new WP_Error( 'dsf_fingerprint_key', 'The fingerprint payload contains an invalid key.' );
				}
				if ( self::is_excluded_key( $key ) ) {
					continue;
				}
			}
			$clean = self::normalize_fingerprint_value( $child, $depth + 1, $state );
			if ( $clean instanceof WP_Error ) {
				return $clean;
			}
			if ( $is_list ) {
				$normalized[] = $clean;
			} else {
				$normalized[ $key ] = $clean;
			}
		}
		if ( ! $is_list ) {
			ksort( $normalized, SORT_STRING );
		}
		return $normalized;
	}

	/** Validate group/language and the injected enabled-language policy. */
	private function validate_identity( $group_uuid, $language ) {
		if ( ! is_string( $group_uuid ) ) {
			return new WP_Error( 'dsf_workflow_group', 'The translation group is invalid.' );
		}
		$group_uuid = strtolower( trim( $group_uuid ) );
		$language   = self::normalize_language( $language );
		if ( ! self::is_valid_group_uuid( $group_uuid ) ) {
			return new WP_Error( 'dsf_workflow_group', 'The translation group is invalid.' );
		}
		if ( '' === $language ) {
			return new WP_Error( 'dsf_workflow_language', 'The translation language is invalid.' );
		}
		if ( ! $this->language_validator ) {
			return new WP_Error( 'dsf_workflow_language_validator', 'An enabled-language validator is required.' );
		}
		if ( true !== call_user_func( $this->language_validator, $language ) ) {
			return new WP_Error( 'dsf_workflow_language_disabled', 'The translation language is not enabled.' );
		}
		return array(
			'group_uuid' => $group_uuid,
			'language'   => $language,
		);
	}

	/** Require a strict, action-specific authorization decision. */
	private function authorize( $callback, $action, $group_uuid, $language ) {
		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'dsf_workflow_authorization', 'An object-specific authorization callback is required.' );
		}
		$result = call_user_func( $callback, $action, $group_uuid, $language );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		return true === $result ? true : new WP_Error( 'dsf_workflow_forbidden', 'You are not allowed to update this translation workflow.' );
	}

	/** Persist one allowlisted boolean fact. */
	private function set_boolean_fact( $group_uuid, $language, $column, $value, $authorization_callback ) {
		if ( ! in_array( $column, array( 'machine_prefilled', 'critical_change' ), true ) ) {
			return new WP_Error( 'dsf_workflow_fact', 'The workflow fact is invalid.' );
		}
		$identity = $this->validate_identity( $group_uuid, $language );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}
		$authorized = $this->authorize( $authorization_callback, 'set_' . $column, $identity['group_uuid'], $identity['language'] );
		if ( $authorized instanceof WP_Error ) {
			return $authorized;
		}
		$db = $this->database();
		if ( $db instanceof WP_Error ) {
			return $db;
		}
		$table            = $this->current_table();
		$now              = $this->now_gmt();
		$clear_review_sql = 'machine_prefilled' === $column && $value
			? ", reviewer_id = 0, reviewed_at_gmt = NULL, reviewed_source_fingerprint = '', reviewed_fingerprint_schema = 0"
			: '';

		// The column is selected from the fixed allowlist above. The remaining dynamic values are prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table and allowlisted column identifiers only.
		$sql = $db->prepare(
			"INSERT INTO {$table} (group_uuid, language, {$column}, updated_at_gmt) VALUES (%s, %s, %d, %s) ON DUPLICATE KEY UPDATE {$column} = VALUES({$column}){$clear_review_sql}, updated_at_gmt = VALUES(updated_at_gmt)",
			$identity['group_uuid'],
			$identity['language'],
			$value ? 1 : 0,
			$now
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL is fully prepared above; workflow facts require immediate consistency.
		if ( false === $db->query( $sql ) ) {
			return new WP_Error( 'dsf_workflow_database', 'The translation workflow could not be updated.' );
		}
		return true;
	}

	/** Accept only explicit boolean representations at the persistence boundary. */
	private function normalize_boolean_fact( $value ) {
		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}
		if ( false === $value || 0 === $value || '0' === $value ) {
			return false;
		}
		return new WP_Error( 'dsf_workflow_fact', 'The workflow fact must be a boolean.' );
	}

	/** Return wpdb or a typed error. */
	private function database() {
		if ( ! $this->wpdb || ! $this->current_table() || ! is_callable( array( $this->wpdb, 'prepare' ) ) ) {
			return new WP_Error( 'dsf_workflow_database', 'The translation workflow database is unavailable.' );
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

	/** Current UTC MySQL timestamp, injectable for deterministic tests. */
	private function now_gmt() {
		$value = $this->clock ? call_user_func( $this->clock ) : current_time( 'mysql', true );
		return self::valid_mysql_datetime( $value ) ? (string) $value : gmdate( 'Y-m-d H:i:s' );
	}

	/** Validate the narrow timestamp format stored in the workflow table. */
	private static function valid_mysql_datetime( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value );
	}
}
