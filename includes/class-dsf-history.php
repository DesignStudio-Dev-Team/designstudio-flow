<?php
/**
 * Quick Restore history for DesignStudio Flow.
 *
 * Stores a bounded number of sanitized, non-secret prior states for Flow
 * objects and approved global settings groups. This is an undo safety net, not
 * a database or media backup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_History {

	const DB_VERSION   = '1.0.0';
	const MAX_VERSIONS = 2;
	const MAX_SUMMARY  = 500;
	const MAX_PAYLOAD  = 5242880;
	const TABLE_SUFFIX = 'dsf_history';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'maybe_install' ), 2 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/** Create or upgrade the history table. */
	public static function install() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_kind varchar(20) NOT NULL,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_key varchar(191) NOT NULL DEFAULT '',
			source_type varchar(64) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			schema_version varchar(20) NOT NULL DEFAULT '1.0.0',
			summary varchar(500) NOT NULL DEFAULT '',
			reason varchar(40) NOT NULL DEFAULT 'save',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_lookup (source_kind,source_id,source_key(80),source_type,created_at_gmt),
			KEY source_hash (source_kind,source_id,source_key(80),payload_hash)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'dsf_history_db_version', self::DB_VERSION, false );
	}

	public function maybe_install() {
		if ( version_compare( (string) get_option( 'dsf_history_db_version', '' ), self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Capture the current post state before a proposed mutation.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @param array  $next_payload Sanitized proposed state.
	 * @param string $reason Mutation reason.
	 * @return true|WP_Error
	 */
	public function capture_before_post_mutation( $post_id, $post_type, $next_payload, $reason = 'save' ) {
		$post_id   = absint( $post_id );
		$post_type = sanitize_key( $post_type );
		$post      = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || $post->post_type !== $post_type ) {
			return new WP_Error( 'dsf_history_source', 'History source not found.' );
		}

		$current = $this->capture_post_payload( $post );
		$next    = $this->normalize_post_payload( $post_type, $next_payload );
		if ( is_wp_error( $next ) ) {
			return $next;
		}

		$current_json = $this->canonical_json( $current );
		$next_json    = $this->canonical_json( $next );
		if ( hash( 'sha256', $current_json ) === hash( 'sha256', $next_json ) ) {
			return true;
		}

		return $this->insert_record(
			'post',
			$post_id,
			'',
			$post_type,
			$current,
			$this->build_summary( $current, $next ),
			$reason
		);
	}

	/** Capture a settings group before changing an approved option set. */
	public function capture_before_settings_mutation( $source_key, $current_value, $next_value, $reason = 'settings_save' ) {
		$source_key = sanitize_key( $source_key );
		if ( ! $source_key ) {
			return new WP_Error( 'dsf_history_settings', 'Invalid settings history key.' );
		}
		$current = $this->normalize_settings_payload( $source_key, $current_value );
		$next    = $this->normalize_settings_payload( $source_key, $next_value );
		if ( is_wp_error( $current ) || is_wp_error( $next ) ) {
			return is_wp_error( $current ) ? $current : $next;
		}
		$current_json = $this->canonical_json( $current );
		$next_json    = $this->canonical_json( $next );
		if ( hash( 'sha256', $current_json ) === hash( 'sha256', $next_json ) ) {
			return true;
		}
		return $this->insert_record( 'settings', 0, $source_key, 'settings', $current, $this->build_summary( $current, $next ), $reason );
	}

	/** Return safe metadata for the latest two records. */
	public function list_records( $source_kind, $source_id = 0, $source_key = '', $source_type = '' ) {
		global $wpdb;
		$source_kind = sanitize_key( $source_kind );
		$source_id   = absint( $source_id );
		$source_key  = sanitize_key( $source_key );
		$source_type = sanitize_key( $source_type );
		$table       = self::table_name();
		if ( 'post' === $source_kind ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the trusted wpdb prefix.
			$sql = $wpdb->prepare( "SELECT id, source_kind, source_id, source_type, payload_hash, schema_version, summary, reason, created_by, created_at_gmt FROM {$table} WHERE source_kind = 'post' AND source_id = %d AND source_type = %s ORDER BY id DESC LIMIT %d", $source_id, $source_type, self::MAX_VERSIONS );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the trusted wpdb prefix.
			$sql = $wpdb->prepare( "SELECT id, source_kind, source_id, source_key, source_type, payload_hash, schema_version, summary, reason, created_by, created_at_gmt FROM {$table} WHERE source_kind = 'settings' AND source_key = %s ORDER BY id DESC LIMIT %d", $source_key, self::MAX_VERSIONS );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared in the branch above.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return array_map( array( $this, 'format_record' ), is_array( $rows ) ? $rows : array() );
	}

	/** Restore one record after revalidating ownership and current hash. */
	public function get_record( $record_id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the trusted wpdb prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $record_id ) ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'dsf_history_missing', 'History record not found.' );
		}
		$payload = json_decode( (string) $row['payload'], true );
		if ( ! is_array( $payload ) || ! hash_equals( (string) $row['payload_hash'], hash( 'sha256', $this->canonical_json( $payload ) ) ) ) {
			return new WP_Error( 'dsf_history_corrupt', 'History record is invalid.' );
		}
		$row['payload'] = $payload;
		return $row;
	}

	public function current_post_hash( $post_id ) {
		$post = absint( $post_id ) ? get_post( absint( $post_id ) ) : null;
		if ( ! $post ) {
			return '';
		}
		return hash( 'sha256', $this->canonical_json( $this->capture_post_payload( $post ) ) );
	}

	public function current_settings_hash( $source_key ) {
		$source_key = sanitize_key( $source_key );
		if ( ! $this->is_allowed_settings_key( $source_key ) ) {
			return '';
		}
		$value = get_option( $source_key, null );
		return hash( 'sha256', $this->canonical_json( $this->normalize_settings_payload( $source_key, $value ) ) );
	}

	public function restore_settings_record( $record, $source_key, $expected_hash = '' ) {
		$source_key = sanitize_key( $source_key );
		if ( ! $this->is_allowed_settings_key( $source_key ) || ! is_array( $record ) || 'settings' !== ( $record['source_kind'] ?? '' ) || sanitize_key( $record['source_key'] ?? '' ) !== $source_key ) {
			return new WP_Error( 'dsf_history_settings_source', 'History record does not belong to this settings group.' );
		}
		$current      = $this->normalize_settings_payload( $source_key, get_option( $source_key, null ) );
		$current_hash = hash( 'sha256', $this->canonical_json( $current ) );
		if ( '' !== $expected_hash && ! hash_equals( $current_hash, (string) $expected_hash ) ) {
			return new WP_Error( 'dsf_history_stale', 'These settings changed after the history panel was loaded.' );
		}
		$target_value = $this->sanitize_settings_value( $source_key, $record['payload']['value'] ?? null );
		if ( is_wp_error( $target_value ) ) {
			return $target_value;
		}
		$target      = $this->normalize_settings_payload( $source_key, $target_value );
		$target_hash = hash( 'sha256', $this->canonical_json( $target ) );
		if ( $current_hash === $target_hash ) {
			return array(
				'message' => 'The selected settings version is already current.',
				'hash'    => $current_hash,
			);
		}
		$captured = $this->insert_record( 'settings', 0, $source_key, 'settings', $current, 'Restored previous settings', 'restore' );
		if ( is_wp_error( $captured ) ) {
			return $captured;
		}
		update_option( $source_key, $target_value );
		if ( in_array( $source_key, array( 'dsf_default_header_id', 'dsf_default_footer_id' ), true ) && absint( $target_value ) ) {
			DSF_Frontend::apply_layout_to_all_flow_content( 'dsf_default_footer_id' === $source_key ? 'footer' : 'header', absint( $target_value ) );
		}
		$this->prune( 'settings', 0, $source_key, 'settings' );
		return array(
			'message' => 'Settings restored.',
			'hash'    => $target_hash,
		);
	}

	/** Build a proposed state by overlaying known fields on the current state. */
	public function proposed_post_payload( $post_id, $overrides ) {
		$post = absint( $post_id ) ? get_post( absint( $post_id ) ) : null;
		if ( ! $post ) {
			return array();
		}
		$payload   = $this->capture_post_payload( $post );
		$overrides = is_array( $overrides ) ? $overrides : array();
		foreach ( array( 'post_title', 'post_name', 'post_parent', 'post_status' ) as $key ) {
			if ( array_key_exists( $key, $overrides ) ) {
				$payload[ $key ] = $overrides[ $key ];
			}
		}
		if ( isset( $overrides['meta'] ) && is_array( $overrides['meta'] ) ) {
			$payload['meta'] = array_merge( (array) $payload['meta'], $overrides['meta'] );
		}
		return class_exists( 'DSF_Ajax' ) ? DSF_Ajax::get_instance()->sanitize_history_post_payload( $post->post_type, $payload ) : $payload;
	}

	/**
	 * Restore one post-backed record after verifying its source and current hash.
	 *
	 * @param array  $record History row including decoded payload.
	 * @param int    $post_id Expected source post ID.
	 * @param string $expected_hash Hash read by the editor.
	 * @return array|WP_Error
	 */
	public function restore_post_record( $record, $post_id, $expected_hash = '' ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! is_array( $record ) || 'post' !== ( $record['source_kind'] ?? '' ) || absint( $record['source_id'] ?? 0 ) !== $post_id || sanitize_key( $record['source_type'] ?? '' ) !== sanitize_key( $post->post_type ) ) {
			return new WP_Error( 'dsf_history_source', 'History record does not belong to this object.' );
		}
		$current      = $this->capture_post_payload( $post );
		$current_hash = hash( 'sha256', $this->canonical_json( $current ) );
		if ( '' !== $expected_hash && ! hash_equals( $current_hash, (string) $expected_hash ) ) {
			return new WP_Error( 'dsf_history_stale', 'This object changed after the history panel was loaded.' );
		}
		$target = $this->normalize_post_payload( $post->post_type, $record['payload'] ?? array() );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$target_hash = hash( 'sha256', $this->canonical_json( $target ) );
		if ( $current_hash === $target_hash ) {
			return array(
				'message' => 'The selected version is already current.',
				'hash'    => $current_hash,
			);
		}
		$captured = $this->insert_record( 'post', $post_id, '', $post->post_type, $current, 'Restored a previous version', 'restore' );
		if ( is_wp_error( $captured ) ) {
			return $captured;
		}
		foreach ( $this->allowed_meta_keys( $post->post_type ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		foreach ( (array) ( $target['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( $post_id, sanitize_key( $key ), $value );
		}
		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $target['post_title'],
				'post_name'   => $target['post_name'],
				'post_parent' => $target['post_parent'],
				'post_status' => $target['post_status'],
			),
			true 
		);
		if ( is_wp_error( $updated ) ) {
			foreach ( $this->allowed_meta_keys( $post->post_type ) as $key ) {
				delete_post_meta( $post_id, $key );
			}
			foreach ( (array) ( $current['meta'] ?? array() ) as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_title'  => $current['post_title'],
					'post_name'   => $current['post_name'],
					'post_parent' => $current['post_parent'],
					'post_status' => $current['post_status'],
				) 
			);
			return new WP_Error( 'dsf_history_restore_failed', 'The previous version could not be restored.' );
		}
		delete_post_meta( $post_id, '_dsf_html_snapshot' );
		$this->prune( 'post', $post_id, '', $post->post_type );
		return array(
			'message' => 'Version restored.',
			'hash'    => $target_hash,
		);
	}

	public function prune( $source_kind, $source_id = 0, $source_key = '', $source_type = '' ) {
		global $wpdb;
		$table = self::table_name();
		if ( 'post' === $source_kind ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the trusted wpdb prefix.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_kind = 'post' AND source_id = %d AND source_type = %s ORDER BY id DESC", absint( $source_id ), sanitize_key( $source_type ) ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the trusted wpdb prefix.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_kind = 'settings' AND source_key = %s ORDER BY id DESC", sanitize_key( $source_key ) ) );
		}
		$delete = array_slice( array_map( 'absint', (array) $ids ), self::MAX_VERSIONS );
		foreach ( $delete as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}
	}

	private function insert_record( $source_kind, $source_id, $source_key, $source_type, $payload, $summary, $reason ) {
		global $wpdb;
		$json = $this->canonical_json( $payload );
		if ( strlen( $json ) > self::MAX_PAYLOAD ) {
			return new WP_Error( 'dsf_history_oversized', 'History payload is too large.' );
		}
		$table = self::table_name();
		$ok    = $wpdb->insert(
			$table,
			array(
				'source_kind'    => sanitize_key( $source_kind ),
				'source_id'      => absint( $source_id ),
				'source_key'     => sanitize_key( $source_key ),
				'source_type'    => sanitize_key( $source_type ),
				'payload'        => $json,
				'payload_hash'   => hash( 'sha256', $json ),
				'schema_version' => self::DB_VERSION,
				'summary'        => mb_substr( sanitize_text_field( $summary ), 0, self::MAX_SUMMARY ),
				'reason'         => mb_substr( sanitize_key( $reason ), 0, 40 ),
				'created_by'     => get_current_user_id(),
				'created_at_gmt' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'dsf_history_write', 'Could not create a restore point.' );
		}
		$this->prune( $source_kind, $source_id, $source_key, $source_type );
		return true;
	}

	private function capture_post_payload( $post ) {
		$type    = sanitize_key( $post->post_type );
		$keys    = array(
			'page'                 => array( '_dsf_blocks', '_dsf_settings', '_dsf_enabled', '_dsf_noindex' ),
			'dsf_layout'           => array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' ),
			'dsf_saved_block'      => array( '_dsf_block_type', '_dsf_block_settings', '_dsf_block_category', '_dsf_block_tags', '_dsf_block_featured' ),
			'dsf_template'         => array( '_dsf_template_blocks', '_dsf_template_theme', '_dsf_template_kind' ),
			'dsf_product_template' => array( '_dsf_blocks', '_dsf_settings', '_dsf_pt_assignment', '_dsf_pt_active', '_dsf_pt_preview_product' ),
			'dsf_shop_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_st_assignment', '_dsf_st_active', '_dsf_st_preview_term' ),
			'dsf_blog_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_bt_assignment', '_dsf_bt_active', '_dsf_bt_preview_term' ),
			'dsf_form'             => array( '_dsf_form_rows', '_dsf_form_settings' ),
			'dsf_popup'            => array( '_dsf_popup_settings' ),
		);
		$payload = array(
			'post_type'   => $type,
			'post_title'  => sanitize_text_field( (string) $post->post_title ),
			'post_name'   => sanitize_title( (string) $post->post_name ),
			'post_parent' => absint( $post->post_parent ),
			'post_status' => sanitize_key( (string) $post->post_status ),
			'meta'        => array(),
		);
		foreach ( $keys[ $type ] ?? array() as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '_dsf_html_snapshot' !== $key ) {
				$payload['meta'][ $key ] = $this->normalize_value( $value );
			}
		}
		return class_exists( 'DSF_Ajax' ) ? DSF_Ajax::get_instance()->sanitize_history_post_payload( $type, $payload ) : $payload;
	}

	private function normalize_post_payload( $post_type, $payload ) {
		$payload = is_array( $payload ) ? $payload : array();
		$clean   = array(
			'post_type'   => sanitize_key( $post_type ),
			'post_title'  => sanitize_text_field( $payload['post_title'] ?? '' ),
			'post_name'   => sanitize_title( $payload['post_name'] ?? '' ),
			'post_parent' => absint( $payload['post_parent'] ?? 0 ),
			'post_status' => in_array( sanitize_key( $payload['post_status'] ?? 'draft' ), array( 'draft', 'publish', 'private', 'pending', 'future' ), true ) ? sanitize_key( $payload['post_status'] ?? 'draft' ) : 'draft',
			'meta'        => array(),
		);
		$allowed = $this->allowed_meta_keys( $post_type );
		$meta    = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();
		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' !== $key && ( empty( $allowed ) || in_array( $key, $allowed, true ) ) ) {
				$clean['meta'][ $key ] = $this->normalize_value( $value );
			}
		}
		return class_exists( 'DSF_Ajax' ) ? DSF_Ajax::get_instance()->sanitize_history_post_payload( $post_type, $clean ) : $clean;
	}

	private function allowed_meta_keys( $post_type ) {
		$map = array(
			'page'                 => array( '_dsf_blocks', '_dsf_settings', '_dsf_enabled', '_dsf_noindex' ),
			'dsf_layout'           => array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' ),
			'dsf_saved_block'      => array( '_dsf_block_type', '_dsf_block_settings', '_dsf_block_category', '_dsf_block_tags', '_dsf_block_featured' ),
			'dsf_template'         => array( '_dsf_template_blocks', '_dsf_template_theme', '_dsf_template_kind' ),
			'dsf_product_template' => array( '_dsf_blocks', '_dsf_settings', '_dsf_pt_assignment', '_dsf_pt_active', '_dsf_pt_preview_product' ),
			'dsf_shop_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_st_assignment', '_dsf_st_active', '_dsf_st_preview_term' ),
			'dsf_blog_template'    => array( '_dsf_blocks', '_dsf_settings', '_dsf_bt_assignment', '_dsf_bt_active', '_dsf_bt_preview_term' ),
			'dsf_form'             => array( '_dsf_form_rows', '_dsf_form_settings' ),
			'dsf_popup'            => array( '_dsf_popup_settings' ),
		);
		return $map[ sanitize_key( $post_type ) ] ?? array();
	}

	private function normalize_settings_payload( $source_key, $value ) {
		return array(
			'settings_key' => sanitize_key( $source_key ),
			'value'        => $this->normalize_value( $value ),
		);
	}

	private function is_allowed_settings_key( $source_key ) {
		return in_array( sanitize_key( $source_key ), array( 'dsf_default_colors', 'dsf_typography', 'dsf_seo_defaults', 'dsf_notification_bar', 'dsf_default_header_id', 'dsf_default_footer_id', 'dsf_global_header_footer', 'dsf_products_enabled', 'dsf_redirects' ), true );
	}

	private function sanitize_settings_value( $source_key, $value ) {
		$source_key = sanitize_key( $source_key );
		if ( in_array( $source_key, array( 'dsf_default_header_id', 'dsf_default_footer_id' ), true ) ) {
			$id   = absint( $value );
			$post = $id ? get_post( $id ) : null;
			$type = 'dsf_default_footer_id' === $source_key ? 'footer' : 'header';
			return ( $post && 'dsf_layout' === $post->post_type && ( 'footer' === get_post_meta( $id, '_dsf_layout_type', true ) ? 'footer' : 'header' ) === $type ) ? $id : 0;
		}
		if ( in_array( $source_key, array( 'dsf_global_header_footer', 'dsf_products_enabled' ), true ) ) {
			return ! empty( $value );
		}
		if ( 'dsf_default_colors' === $source_key ) {
			$value = is_array( $value ) ? $value : array();
			$primary = sanitize_hex_color( isset( $value['primary'] ) ? $value['primary'] : '' );
			$secondary = sanitize_hex_color( isset( $value['secondary'] ) ? $value['secondary'] : '' );
			$text = sanitize_hex_color( isset( $value['text'] ) ? $value['text'] : '' );
			$background = sanitize_hex_color( isset( $value['background'] ) ? $value['background'] : '' );
			return array(
				'primary'    => $primary ? $primary : '#2C5F5D',
				'secondary'  => $secondary ? $secondary : '#1E40AF',
				'text'       => $text ? $text : '#1F2937',
				'background' => $background ? $background : '#FFFFFF',
			);
		}
		if ( 'dsf_notification_bar' === $source_key && class_exists( 'DSF_Notification_Bar' ) ) {
			return DSF_Notification_Bar::sanitize_settings( is_array( $value ) ? $value : array() );
		}
		if ( 'dsf_redirects' === $source_key && class_exists( 'DSF_Redirects' ) ) {
			$out = array();
			foreach ( is_array( $value ) ? array_slice( $value, 0, 500 ) : array() as $item ) {
				$clean = DSF_Redirects::sanitize_redirect( $item );
				if ( is_array( $clean ) ) {
					$out[] = $clean; }
			}
			return $out;
		}
		if ( 'dsf_seo_defaults' === $source_key ) {
			$value = is_array( $value ) ? $value : array();
			return array(
				'defaultSocialImage' => esc_url_raw( $value['defaultSocialImage'] ?? '' ),
				'titleSeparator'     => mb_substr( sanitize_text_field( $value['titleSeparator'] ?? '–' ), 0, 3 ),
				'orgName'            => sanitize_text_field( $value['orgName'] ?? '' ),
				'orgLogo'            => esc_url_raw( $value['orgLogo'] ?? '' ),
				'twitterSite'        => sanitize_text_field( $value['twitterSite'] ?? '' ),
				'socialProfiles'     => array_values( array_filter( array_map( 'esc_url_raw', is_array( $value['socialProfiles'] ?? null ) ? array_slice( $value['socialProfiles'], 0, 20 ) : array() ) ) ),
			);
		}
		if ( 'dsf_typography' === $source_key ) {
			$value = is_array( $value ) ? $value : array();
			$clean = array();
			foreach ( array( 'mode', 'heading_font', 'body_font' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $value[ $key ] ?? '' ); }
			foreach ( array( 'base', 'scale', 'container_width', 'size_p', 'size_h1', 'size_h2', 'size_h3', 'size_h4', 'base_laptop', 'base_mobile', 'size_p_laptop', 'size_h1_laptop', 'size_h2_laptop', 'size_h3_laptop', 'size_h4_laptop', 'size_p_mobile', 'size_h1_mobile', 'size_h2_mobile', 'size_h3_mobile', 'size_h4_mobile' ) as $key ) {
				$clean[ $key ] = is_numeric( $value[ $key ] ?? null ) ? (float) $value[ $key ] : 0; }
			return $clean;
		}
		return new WP_Error( 'dsf_history_settings_key', 'Settings group cannot be restored.' );
	}

	private function normalize_value( $value, $depth = 0 ) {
		if ( $depth > 20 ) {
			return null;
		}
		if ( is_array( $value ) ) {
			$out   = array();
			$count = 0;
			foreach ( $value as $key => $item ) {
				if ( $count++ >= 500 ) {
					break;
				}
				$out[ is_int( $key ) ? $key : sanitize_key( $key ) ] = $this->normalize_value( $item, $depth + 1 );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return mb_substr( (string) $value, 0, 100000 );
	}

	private function canonical_json( $value ) {
		return (string) wp_json_encode( $this->canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
	}

	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ $key ] = $this->canonicalize( $item );
		}
		$is_list = empty( $out ) || array_keys( $out ) === range( 0, count( $out ) - 1 );
		if ( ! $is_list ) {
			ksort( $out );
		}
		return $out;
	}

	private function build_summary( $old, $new ) {
		$changed = array();
		foreach ( array(
			'post_title'  => 'title',
			'post_name'   => 'slug',
			'post_status' => 'status',
			'post_parent' => 'parent',
		) as $key => $label ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				$changed[] = $label;
			}
		}
		$old_meta = isset( $old['meta'] ) && is_array( $old['meta'] ) ? $old['meta'] : array();
		$new_meta = isset( $new['meta'] ) && is_array( $new['meta'] ) ? $new['meta'] : array();
		foreach ( array_unique( array_merge( array_keys( $old_meta ), array_keys( $new_meta ) ) ) as $key ) {
			if ( $this->canonical_json( $old_meta[ $key ] ?? null ) !== $this->canonical_json( $new_meta[ $key ] ?? null ) ) {
				$changed[] = preg_replace( '/^_dsf_/', '', (string) $key );
			}
		}
		return empty( $changed ) ? 'Updated Flow content' : 'Changed: ' . implode( ', ', array_slice( $changed, 0, 8 ) );
	}

	private function format_record( $row ) {
		return array(
			'id'             => absint( $row['id'] ?? 0 ),
			'source_kind'    => sanitize_key( $row['source_kind'] ?? '' ),
			'source_id'      => absint( $row['source_id'] ?? 0 ),
			'source_key'     => sanitize_key( $row['source_key'] ?? '' ),
			'source_type'    => sanitize_key( $row['source_type'] ?? '' ),
			'payload_hash'   => preg_match( '/^[a-f0-9]{64}$/', (string) ( $row['payload_hash'] ?? '' ) ) ? $row['payload_hash'] : '',
			'schema_version' => sanitize_text_field( $row['schema_version'] ?? '' ),
			'summary'        => sanitize_text_field( $row['summary'] ?? '' ),
			'reason'         => sanitize_key( $row['reason'] ?? '' ),
			'created_by'     => absint( $row['created_by'] ?? 0 ),
			'created_at_gmt' => sanitize_text_field( $row['created_at_gmt'] ?? '' ),
		);
	}
}
