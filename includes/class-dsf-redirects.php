<?php
/**
 * Lightweight redirect manager with CSV import / export.
 *
 * Redirects are stored in a single autoloaded option so the frontend match is a
 * cheap in-memory lookup. The pure helpers (normalize_path, sanitize_type,
 * sanitize_redirect, parse_csv, to_csv) carry no WordPress dependencies so they
 * can be unit-tested in isolation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Redirects {

	const OPTION        = 'dsf_redirects';
	const CAP           = 'manage_options';
	const SAVE_ACTION   = 'dsf_redirect_save';
	const DELETE_ACTION = 'dsf_redirect_delete';
	const TOGGLE_ACTION = 'dsf_redirect_toggle';
	const IMPORT_ACTION = 'dsf_redirect_import';
	const EXPORT_ACTION = 'dsf_redirect_export';
	const MAX_REDIRECTS = 1000;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );

		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
	}

	/* -----------------------------------------------------------------
	 * Pure helpers (no WordPress dependencies)
	 * ----------------------------------------------------------------- */

	/**
	 * Collapse a path to a leading-slash, no-trailing-slash form. Root is "/".
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = (string) $path;
		$path = strtok( $path, '?#' ); // Drop any query string / fragment.
		$path = '/' . trim( (string) $path, '/' );
		return rawurldecode( $path );
	}

	/**
	 * Reduce a user-entered source (path or full URL) to a comparable path.
	 *
	 * @param string $source Raw source.
	 * @return string Normalized path, or '' when nothing usable was given.
	 */
	public static function normalize_source( $source ) {
		$source = trim( (string) $source );
		if ( '' === $source ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $source ) ) {
			$parsed = parse_url( $source );
			$source = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		}
		return self::normalize_path( $source );
	}

	/**
	 * Clean a redirect target (relative path or absolute URL).
	 *
	 * @param string $target Raw target.
	 * @return string
	 */
	public static function sanitize_target( $target ) {
		$target = trim( self::strip_tags( (string) $target ) );
		if ( '' === $target ) {
			return '';
		}
		// Strip control characters that have no business in a Location header.
		$target = preg_replace( '/[\x00-\x1F\x7F]/', '', $target );
		if ( preg_match( '#^https?://#i', $target ) || 0 === strpos( $target, '//' ) ) {
			return $target;
		}
		return '/' . ltrim( $target, '/' );
	}

	/**
	 * Constrain the HTTP status to the two redirect types we support.
	 *
	 * @param mixed $type Raw type.
	 * @return int 301 or 302.
	 */
	public static function sanitize_type( $type ) {
		return 302 === (int) $type ? 302 : 301;
	}

	/**
	 * Constrain the query-parameter handling mode.
	 *
	 * @param mixed $value Raw mode.
	 * @return string 'exact', 'ignore', or 'pass'.
	 */
	public static function sanitize_query_mode( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( 'exact', 'ignore', 'pass' ), true ) ? $value : 'ignore';
	}

	/**
	 * Extract query parameters from a raw source string (the part after "?").
	 *
	 * @param string $source Raw source (path or URL, possibly with a query).
	 * @return array Associative parameters.
	 */
	public static function parse_query_params( $source ) {
		$source = (string) $source;
		$pos    = strpos( $source, '?' );
		if ( false === $pos ) {
			return array();
		}
		$query  = strtok( substr( $source, $pos + 1 ), '#' );
		$params = array();
		parse_str( (string) $query, $params );
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Canonical (key-sorted) query string for order-independent comparison.
	 *
	 * @param array $params Associative parameters.
	 * @return string
	 */
	public static function canonical_query( $params ) {
		$params = (array) $params;
		ksort( $params );
		return http_build_query( $params );
	}

	/**
	 * Whether two parameter sets are equal regardless of order.
	 *
	 * @param array $a First set.
	 * @param array $b Second set.
	 * @return bool
	 */
	public static function query_params_match( $a, $b ) {
		return self::canonical_query( $a ) === self::canonical_query( $b );
	}

	/**
	 * Append a raw query string to a URL, choosing ? or & as needed.
	 *
	 * @param string $url   URL or path.
	 * @param string $query Raw query string.
	 * @return string
	 */
	public static function append_query( $url, $query ) {
		$query = ltrim( (string) $query, '?&' );
		if ( '' === $query ) {
			return (string) $url;
		}
		$separator = ( false === strpos( (string) $url, '?' ) ) ? '?' : '&';
		return $url . $separator . $query;
	}

	/**
	 * Interpret common truthy spellings used in spreadsheets.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'on', 'enabled', 'y' ), true );
	}

	/**
	 * A relative target whose path equals the source would loop forever.
	 *
	 * @param string $source Normalized source path.
	 * @param string $target Sanitized target.
	 * @return bool
	 */
	public static function is_loop( $source, $target ) {
		if ( '' === $target || preg_match( '#^https?://#i', $target ) || 0 === strpos( $target, '//' ) ) {
			return false;
		}
		return self::normalize_path( $target ) === $source;
	}

	/**
	 * Build a stored redirect record from loosely-typed input, or null when invalid.
	 *
	 * @param array $raw Raw fields (source, target, type, enabled, id, hits).
	 * @return array|null
	 */
	public static function sanitize_redirect( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$source = self::normalize_source( isset( $raw['source'] ) ? $raw['source'] : '' );
		$target = self::sanitize_target( isset( $raw['target'] ) ? $raw['target'] : '' );

		if ( '' === $source || '' === $target || self::is_loop( $source, $target ) ) {
			return null;
		}

		$query_mode = self::sanitize_query_mode( isset( $raw['query'] ) ? $raw['query'] : 'ignore' );

		// In "exact" mode, remember the source's query params. They may come as a
		// stored array (re-sanitize) or be parsed from the raw source string.
		$source_query = array();
		if ( 'exact' === $query_mode ) {
			if ( isset( $raw['source_query'] ) && is_array( $raw['source_query'] ) ) {
				$source_query = $raw['source_query'];
			} else {
				$source_query = self::parse_query_params( isset( $raw['source'] ) ? $raw['source'] : '' );
			}
		}

		return array(
			'id'           => ( isset( $raw['id'] ) && $raw['id'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $raw['id'] ) ) : self::make_id(),
			'source'       => $source,
			'target'       => $target,
			'type'         => self::sanitize_type( isset( $raw['type'] ) ? $raw['type'] : 301 ),
			'enabled'      => self::truthy( isset( $raw['enabled'] ) ? $raw['enabled'] : true ),
			'query'        => $query_mode,
			'source_query' => $source_query,
			'hits'         => isset( $raw['hits'] ) ? max( 0, (int) $raw['hits'] ) : 0,
		);
	}

	/**
	 * Stable identity for de-duplication: source path plus, for exact mode, its
	 * canonical query. Two exact rules on the same path with different params are
	 * therefore distinct; ignore/pass rules dedupe by path alone.
	 *
	 * @param array $redirect Redirect record.
	 * @return string
	 */
	public static function match_key( $redirect ) {
		$mode  = isset( $redirect['query'] ) ? $redirect['query'] : 'ignore';
		$query = ( 'exact' === $mode && isset( $redirect['source_query'] ) ) ? self::canonical_query( $redirect['source_query'] ) : '';
		return ( isset( $redirect['source'] ) ? $redirect['source'] : '' ) . '|' . $query;
	}

	/**
	 * Parse CSV text into a list of sanitized redirects. A header row whose first
	 * cell reads like "source" is skipped. Invalid rows are dropped.
	 *
	 * @param string $content CSV text.
	 * @return array
	 */
	public static function parse_csv( $content ) {
		$content = str_replace( array( "\r\n", "\r" ), "\n", (string) $content );
		$lines   = explode( "\n", $content );
		$out     = array();
		$first   = true;

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cols = str_getcsv( $line );

			if ( $first ) {
				$first  = false;
				$header = strtolower( trim( isset( $cols[0] ) ? $cols[0] : '' ) );
				if ( in_array( $header, array( 'source', 'from', 'old', 'old url', 'source url', 'request' ), true ) ) {
					continue;
				}
			}

			$redirect = self::sanitize_redirect(
				array(
					'source'  => isset( $cols[0] ) ? $cols[0] : '',
					'target'  => isset( $cols[1] ) ? $cols[1] : '',
					'type'    => isset( $cols[2] ) ? $cols[2] : 301,
					'enabled' => isset( $cols[3] ) ? $cols[3] : true,
					'query'   => isset( $cols[4] ) ? $cols[4] : 'ignore',
				)
			);

			if ( $redirect ) {
				$out[] = $redirect;
			}
		}

		return $out;
	}

	/**
	 * Serialize redirects to CSV text (with a header row).
	 *
	 * @param array $redirects List of redirect records.
	 * @return string
	 */
	public static function to_csv( $redirects ) {
		$rows   = array( array( 'source', 'target', 'type', 'enabled', 'query' ) );
		foreach ( (array) $redirects as $r ) {
			$mode   = self::sanitize_query_mode( isset( $r['query'] ) ? $r['query'] : 'ignore' );
			$source = isset( $r['source'] ) ? $r['source'] : '';
			if ( 'exact' === $mode && ! empty( $r['source_query'] ) ) {
				$source = self::append_query( $source, self::canonical_query( $r['source_query'] ) );
			}
			$rows[] = array(
				$source,
				isset( $r['target'] ) ? $r['target'] : '',
				self::sanitize_type( isset( $r['type'] ) ? $r['type'] : 301 ),
				! empty( $r['enabled'] ) ? '1' : '0',
				$mode,
			);
		}

		$lines = array();
		foreach ( $rows as $row ) {
			$cells = array();
			foreach ( $row as $cell ) {
				$cell = (string) $cell;
				if ( preg_match( '/[",\n]/', $cell ) ) {
					$cell = '"' . str_replace( '"', '""', $cell ) . '"';
				}
				$cells[] = $cell;
			}
			$lines[] = implode( ',', $cells );
		}

		return implode( "\n", $lines ) . "\n";
	}

	private static function make_id() {
		return 'r' . substr( md5( uniqid( '', true ) ), 0, 10 );
	}

	/**
	 * Strip tags using WordPress when available, falling back to a plain regex so
	 * the pure helpers still work under the unit-test bootstrap.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function strip_tags( $value ) {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $value );
		}
		return trim( preg_replace( '/<[^>]*>/', '', (string) $value ) );
	}

	/* -----------------------------------------------------------------
	 * Storage
	 * ----------------------------------------------------------------- */

	public function get_redirects() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$clean = array();
		foreach ( $stored as $item ) {
			$redirect = self::sanitize_redirect( $item );
			if ( $redirect ) {
				// Preserve the stored id rather than minting a new one.
				if ( isset( $item['id'] ) && $item['id'] ) {
					$redirect['id'] = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $item['id'] ) );
				}
				$clean[] = $redirect;
			}
		}
		return $clean;
	}

	private function save_redirects( $redirects ) {
		$redirects = array_slice( array_values( (array) $redirects ), 0, self::MAX_REDIRECTS );
		update_option( self::OPTION, $redirects, true );
	}

	/**
	 * Merge a redirect in by source: an existing entry with the same source is
	 * replaced (keeping its id and hit count); otherwise it is appended.
	 *
	 * @param array $redirect Sanitized redirect.
	 */
	private function upsert( $redirect ) {
		$redirects = $this->get_redirects();
		$replaced  = false;

		foreach ( $redirects as $index => $existing ) {
			$same_id     = ! empty( $redirect['id'] ) && $existing['id'] === $redirect['id'];
			$same_source = self::match_key( $existing ) === self::match_key( $redirect );
			if ( $same_id || $same_source ) {
				$redirect['id']      = $existing['id'];
				$redirect['hits']    = $existing['hits'];
				$redirects[ $index ] = $redirect;
				$replaced            = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$redirects[] = $redirect;
		}

		$this->save_redirects( $redirects );
	}

	/* -----------------------------------------------------------------
	 * Frontend
	 * ----------------------------------------------------------------- */

	public function maybe_redirect() {
		if ( is_admin() ) {
			return;
		}

		$redirects = $this->get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

		$request       = $this->current_path();
		$request_query = $this->current_query();
		$request_qs    = $this->current_query_string();

		foreach ( $redirects as $redirect ) {
			if ( empty( $redirect['enabled'] ) || $redirect['source'] !== $request ) {
				continue;
			}

			$mode = isset( $redirect['query'] ) ? $redirect['query'] : 'ignore';

			// Exact mode: the request's params must match the source's, any order.
			if ( 'exact' === $mode && ! self::query_params_match( isset( $redirect['source_query'] ) ? $redirect['source_query'] : array(), $request_query ) ) {
				continue;
			}

			$target = $redirect['target'];
			if ( ! preg_match( '#^https?://#i', $target ) && 0 !== strpos( $target, '//' ) ) {
				$target = home_url( $target );
			}

			// Pass mode: forward the original query string onto the target.
			if ( 'pass' === $mode && '' !== $request_qs ) {
				$target = self::append_query( $target, $request_qs );
			}

			$this->record_hit( $redirect['id'] );

			wp_redirect( esc_url_raw( $target ), self::sanitize_type( $redirect['type'] ) );
			exit;
		}
	}

	private function current_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = self::normalize_path( $uri );

		// Account for installs in a subdirectory by trimming the home path prefix.
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = '/' . trim( (string) $home, '/' );
		if ( '/' !== $home && 0 === strpos( $path, $home ) ) {
			$path = '/' . ltrim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}

	private function current_query_string() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return (string) wp_parse_url( $uri, PHP_URL_QUERY );
	}

	private function current_query() {
		$params = array();
		parse_str( $this->current_query_string(), $params );
		return is_array( $params ) ? $params : array();
	}

	private function record_hit( $id ) {
		$redirects = $this->get_redirects();
		foreach ( $redirects as $index => $redirect ) {
			if ( $redirect['id'] === $id ) {
				$redirects[ $index ]['hits'] = (int) $redirect['hits'] + 1;
				$this->save_redirects( $redirects );
				return;
			}
		}
	}

	/* -----------------------------------------------------------------
	 * Admin actions
	 * ----------------------------------------------------------------- */

	private function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to manage redirects.', 'designstudio-flow' ) );
		}
	}

	private function back( $status ) {
		$url = add_query_arg(
			array(
				'page'         => 'dsf-tools',
				'tab'          => 'redirects',
				'dsf_redirect' => $status,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_save() {
		$this->guard();
		check_admin_referer( self::SAVE_ACTION );

		$redirect = self::sanitize_redirect(
			array(
				'id'      => isset( $_POST['redirect_id'] ) ? wp_unslash( $_POST['redirect_id'] ) : '',
				'source'  => isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '',
				'target'  => isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '',
				'type'    => isset( $_POST['type'] ) ? wp_unslash( $_POST['type'] ) : 301,
				'enabled' => isset( $_POST['enabled'] ),
				'query'   => isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : 'ignore',
			)
		);

		if ( ! $redirect ) {
			$this->back( 'invalid' );
		}

		$this->upsert( $redirect );
		$this->back( 'saved' );
	}

	public function handle_delete() {
		$this->guard();
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		check_admin_referer( self::DELETE_ACTION . '_' . $id );

		$redirects = array_filter(
			$this->get_redirects(),
			static function ( $redirect ) use ( $id ) {
				return $redirect['id'] !== $id;
			}
		);
		$this->save_redirects( $redirects );
		$this->back( 'deleted' );
	}

	public function handle_toggle() {
		$this->guard();
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		check_admin_referer( self::TOGGLE_ACTION . '_' . $id );

		$redirects = $this->get_redirects();
		foreach ( $redirects as $index => $redirect ) {
			if ( $redirect['id'] === $id ) {
				$redirects[ $index ]['enabled'] = empty( $redirect['enabled'] );
				break;
			}
		}
		$this->save_redirects( $redirects );
		$this->back( 'toggled' );
	}

	public function handle_import() {
		$this->guard();
		check_admin_referer( self::IMPORT_ACTION );

		if ( empty( $_FILES['dsf_redirect_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['dsf_redirect_csv']['tmp_name'] ) ) {
			$this->back( 'no_file' );
		}

		$raw      = file_get_contents( $_FILES['dsf_redirect_csv']['tmp_name'] );
		$imported = self::parse_csv( $raw );

		if ( empty( $imported ) ) {
			$this->back( 'csv_empty' );
		}

		foreach ( $imported as $redirect ) {
			$this->upsert( $redirect );
		}

		$this->back( 'imported_' . count( $imported ) );
	}

	public function handle_export() {
		$this->guard();
		check_admin_referer( self::EXPORT_ACTION );

		$csv = self::to_csv( $this->get_redirects() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="dsf-redirects-' . gmdate( 'Ymd-His' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body.
		exit;
	}

	/* -----------------------------------------------------------------
	 * Admin UI (rendered inside the Tools page "Redirects" tab)
	 * ----------------------------------------------------------------- */

	/**
	 * Human label for a query-handling mode.
	 */
	private function query_mode_label( $mode ) {
		switch ( $mode ) {
			case 'exact':
				return __( 'Exact match', 'designstudio-flow' );
			case 'pass':
				return __( 'Pass to target', 'designstudio-flow' );
			default:
				return __( 'Ignore', 'designstudio-flow' );
		}
	}

	/**
	 * Source shown in the list — includes the matched params for exact mode.
	 */
	private function display_source( $redirect ) {
		$source = isset( $redirect['source'] ) ? $redirect['source'] : '';
		if ( 'exact' === ( isset( $redirect['query'] ) ? $redirect['query'] : 'ignore' ) && ! empty( $redirect['source_query'] ) ) {
			$source = self::append_query( $source, self::canonical_query( $redirect['source_query'] ) );
		}
		return $source;
	}

	public function render_admin_tab() {
		if ( ! current_user_can( self::CAP ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'You need the Administrator role to manage redirects.', 'designstudio-flow' ) . '</p></div>';
			return;
		}

		$this->render_notice();

		$redirects = $this->get_redirects();
		$post_url  = admin_url( 'admin-post.php' );
		?>
		<div class="dsf-tools-grid" style="display:grid;gap:20px;max-width:980px;margin-top:16px;">
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Add a redirect', 'designstudio-flow' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Source is a path on this site (for example /old-page). Target can be a path or a full URL. For exact query matching, include the parameters on the source, e.g. /old-page?ref=email.', 'designstudio-flow' ); ?></p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>">
					<?php wp_nonce_field( self::SAVE_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf-redirect-source"><?php esc_html_e( 'Source path', 'designstudio-flow' ); ?></label></th>
							<td><input type="text" id="dsf-redirect-source" name="source" class="regular-text" placeholder="/old-page" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-redirect-target"><?php esc_html_e( 'Target', 'designstudio-flow' ); ?></label></th>
							<td><input type="text" id="dsf-redirect-target" name="target" class="regular-text" placeholder="/new-page" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-redirect-query"><?php esc_html_e( 'Query parameters', 'designstudio-flow' ); ?></label></th>
							<td>
								<select id="dsf-redirect-query" name="query">
									<option value="ignore"><?php esc_html_e( 'Ignore all parameters', 'designstudio-flow' ); ?></option>
									<option value="exact"><?php esc_html_e( 'Exact match in any order', 'designstudio-flow' ); ?></option>
									<option value="pass"><?php esc_html_e( 'Ignore, but pass parameters to the target', 'designstudio-flow' ); ?></option>
								</select>
								<p class="description" style="margin-top:6px;">
									<?php esc_html_e( 'Ignore: match the path regardless of ?params. Exact: the request must carry the same params as the source (any order). Pass: match the path and forward the original ?params onto the target.', 'designstudio-flow' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-redirect-type"><?php esc_html_e( 'Type', 'designstudio-flow' ); ?></label></th>
							<td>
								<select id="dsf-redirect-type" name="type">
									<option value="301"><?php esc_html_e( '301 — Permanent', 'designstudio-flow' ); ?></option>
									<option value="302"><?php esc_html_e( '302 — Temporary', 'designstudio-flow' ); ?></option>
								</select>
								<label style="margin-left:16px;"><input type="checkbox" name="enabled" value="1" checked> <?php esc_html_e( 'Enabled', 'designstudio-flow' ); ?></label>
							</td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Add redirect', 'designstudio-flow' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Active redirects', 'designstudio-flow' ); ?></h2>
				<?php if ( empty( $redirects ) ) : ?>
					<p><?php esc_html_e( 'No redirects yet. Add one above or import a CSV.', 'designstudio-flow' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Target', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Type', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Query', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Hits', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Status', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'designstudio-flow' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $redirects as $redirect ) : ?>
								<?php
								$toggle_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => self::TOGGLE_ACTION, 'id' => $redirect['id'] ),
										$post_url
									),
									self::TOGGLE_ACTION . '_' . $redirect['id']
								);
								$delete_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => self::DELETE_ACTION, 'id' => $redirect['id'] ),
										$post_url
									),
									self::DELETE_ACTION . '_' . $redirect['id']
								);
								?>
								<tr>
									<td><code><?php echo esc_html( $this->display_source( $redirect ) ); ?></code></td>
									<td><?php echo esc_html( $redirect['target'] ); ?></td>
									<td><?php echo esc_html( (string) $redirect['type'] ); ?></td>
									<td><?php echo esc_html( $this->query_mode_label( isset( $redirect['query'] ) ? $redirect['query'] : 'ignore' ) ); ?></td>
									<td><?php echo esc_html( (string) $redirect['hits'] ); ?></td>
									<td>
										<?php if ( ! empty( $redirect['enabled'] ) ) : ?>
											<span style="color:#1a7f37;font-weight:600;"><?php esc_html_e( 'Enabled', 'designstudio-flow' ); ?></span>
										<?php else : ?>
											<span style="color:#8a8a8a;"><?php esc_html_e( 'Disabled', 'designstudio-flow' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo ! empty( $redirect['enabled'] ) ? esc_html__( 'Disable', 'designstudio-flow' ) : esc_html__( 'Enable', 'designstudio-flow' ); ?></a>
										&nbsp;|&nbsp;
										<a href="<?php echo esc_url( $delete_url ); ?>" style="color:#b32d2e;" onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect?', 'designstudio-flow' ) ); ?>');"><?php esc_html_e( 'Delete', 'designstudio-flow' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import / Export CSV', 'designstudio-flow' ); ?></h2>
				<p class="description"><?php esc_html_e( 'CSV columns: source, target, type, enabled, query. The query column is one of ignore, exact, or pass (defaults to ignore). For exact, include the params on the source, e.g. /old-page?ref=email.', 'designstudio-flow' ); ?></p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data" style="margin-bottom:16px;">
					<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
					<input type="file" name="dsf_redirect_csv" accept=".csv,text/csv" required>
					<button type="submit" class="button"><?php esc_html_e( 'Import CSV', 'designstudio-flow' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>">
					<?php wp_nonce_field( self::EXPORT_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Export CSV', 'designstudio-flow' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['dsf_redirect'] ) ) {
			return;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['dsf_redirect'] ) );

		if ( 0 === strpos( $status, 'imported_' ) ) {
			$count = (int) substr( $status, strlen( 'imported_' ) );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of redirects imported. */
						_n( 'Imported %d redirect.', 'Imported %d redirects.', $count, 'designstudio-flow' ),
						$count
					)
				)
			);
			return;
		}

		$messages = array(
			'saved'     => array( 'success', __( 'Redirect saved.', 'designstudio-flow' ) ),
			'deleted'   => array( 'success', __( 'Redirect deleted.', 'designstudio-flow' ) ),
			'toggled'   => array( 'success', __( 'Redirect updated.', 'designstudio-flow' ) ),
			'invalid'   => array( 'error', __( 'Could not save: a source and target are required, and a redirect cannot point to itself.', 'designstudio-flow' ) ),
			'no_file'   => array( 'error', __( 'No CSV file was uploaded.', 'designstudio-flow' ) ),
			'csv_empty' => array( 'error', __( 'No valid rows found in that CSV.', 'designstudio-flow' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}
	}
}
