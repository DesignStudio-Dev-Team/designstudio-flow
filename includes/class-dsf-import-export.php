<?php
/**
 * Import / Export for Pages, Headers, and Footers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Import_Export {

	const EXPORT_FLAG    = '_dsf_export';
	const EXPORT_VERSION = '1';
	const BULK_ACTION    = 'dsf_export';
	const SINGLE_ACTION  = 'dsf_export_item';
	const IMPORT_ACTION  = 'dsf_import_items';

	private static $instance = null;

	// Per-import media budget (set in handle_import).
	private $media_files_remaining = null;
	private $media_deadline        = 0;
	private $media_skipped         = 0;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_row_actions' ), 10, 2 );

		add_filter( 'bulk_actions-edit-page', array( $this, 'register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-dsf_layout', array( $this, 'register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-dsf_saved_block', array( $this, 'register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-dsf_template', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-page', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-dsf_layout', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-dsf_saved_block', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-dsf_template', array( $this, 'handle_bulk_action' ), 10, 3 );

		add_action( 'admin_post_' . self::SINGLE_ACTION, array( $this, 'handle_single_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );

		add_action( 'admin_menu', array( $this, 'add_tools_menu' ), 90 );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	private function is_supported_post( $post ) {
		if ( ! $post ) {
			return false;
		}

		if ( in_array( $post->post_type, array( 'dsf_layout', 'dsf_saved_block', 'dsf_template' ), true ) ) {
			return true;
		}

		if ( 'page' !== $post->post_type ) {
			return false;
		}

		return (bool) get_post_meta( $post->ID, '_dsf_enabled', true ) || get_post_meta( $post->ID, '_dsf_blocks', true );
	}

	private function get_meta_keys_for_type( $post_type ) {
		if ( in_array( $post_type, array( 'page', 'dsf_page' ), true ) ) {
			return array( '_dsf_blocks', '_dsf_settings', '_dsf_theme_colors' );
		}
		if ( 'dsf_layout' === $post_type ) {
			return array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' );
		}
		if ( 'dsf_saved_block' === $post_type ) {
			return array( '_dsf_block_type', '_dsf_block_settings' );
		}
		if ( 'dsf_template' === $post_type ) {
			return array( '_dsf_template_blocks', '_dsf_template_theme', '_dsf_template_kind' );
		}
		return array();
	}

	public function add_row_actions( $actions, $post ) {
		if ( ! $this->is_supported_post( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::SINGLE_ACTION,
					'post_id' => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::SINGLE_ACTION . '_' . $post->ID
		);

		$actions['dsf_export'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Export', 'designstudio-flow' ) . '</a>';
		return $actions;
	}

	public function register_bulk_actions( $actions ) {
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';
		$dsf_flow  = filter_input( INPUT_GET, 'dsf_flow', FILTER_VALIDATE_INT );

		if ( 'page' === $post_type && 1 !== intval( $dsf_flow ) ) {
			return $actions;
		}

		$actions[ self::BULK_ACTION ] = __( 'Export to JSON', 'designstudio-flow' );
		return $actions;
	}

	public function handle_bulk_action( $sendback, $action, $post_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $sendback;
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to export.', 'designstudio-flow' ) );
		}

		$items = array();
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = intval( $post_id );
			$post    = get_post( $post_id );
			if ( ! $post || ! $this->is_supported_post( $post ) ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$items[] = $this->build_export_item( $post );
		}

		if ( empty( $items ) ) {
			return add_query_arg( 'dsf_export_status', 'empty', $sendback );
		}

		$this->stream_export( $items );
		exit;
	}

	public function handle_single_export() {
		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid request.', 'designstudio-flow' ) );
		}

		check_admin_referer( self::SINGLE_ACTION . '_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to export this item.', 'designstudio-flow' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $this->is_supported_post( $post ) ) {
			wp_die( esc_html__( 'Item not found.', 'designstudio-flow' ) );
		}

		$this->stream_export( array( $this->build_export_item( $post ) ) );
		exit;
	}

	private function build_export_item( $post ) {
		$item = array(
			'post_type' => $post->post_type,
			'title'     => $post->post_title,
			'slug'      => $post->post_name,
			'status'    => $post->post_status,
			'excerpt'   => $post->post_excerpt,
			'meta'      => array(),
		);

		if ( 'dsf_layout' === $post->post_type ) {
			$layout_type        = get_post_meta( $post->ID, '_dsf_layout_type', true );
			$item['layout_type'] = in_array( $layout_type, array( 'header', 'footer' ), true ) ? $layout_type : 'header';
		}

		foreach ( $this->get_meta_keys_for_type( $post->post_type ) as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' === $value || null === $value ) {
				continue;
			}
			$item['meta'][ $key ] = $value;
		}

		return $item;
	}

	private function stream_export( $items ) {
		$payload = array(
			self::EXPORT_FLAG => true,
			'plugin'          => 'designstudio-flow',
			'version'         => defined( 'DSF_VERSION' ) ? DSF_VERSION : '1.0',
			'format'          => self::EXPORT_VERSION,
			'exported_at'     => gmdate( 'c' ),
			'site_url'        => home_url(),
			'items'           => array_values( $items ),
		);

		$filename = $this->build_filename( $items );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private function build_filename( $items ) {
		$stamp = gmdate( 'Ymd-His' );
		if ( 1 === count( $items ) ) {
			$slug = isset( $items[0]['slug'] ) ? sanitize_title( $items[0]['slug'] ) : '';
			if ( '' === $slug ) {
				$slug = sanitize_title( $items[0]['title'] ?? 'item' );
			}
			if ( '' === $slug ) {
				$slug = 'item';
			}
			$type = $items[0]['post_type'] ?? 'item';
			if ( 'dsf_layout' === $type && isset( $items[0]['layout_type'] ) ) {
				$type = 'dsf_' . $items[0]['layout_type'];
			}
			return $type . '-' . $slug . '-' . $stamp . '.json';
		}
		return 'dsf-export-' . $stamp . '.json';
	}

	public function add_tools_menu() {
		add_submenu_page(
			'designstudio-flow',
			__( 'Tools', 'designstudio-flow' ),
			__( 'Tools', 'designstudio-flow' ),
			'edit_pages',
			'dsf-tools',
			array( $this, 'render_tools_page' )
		);
	}

	private function get_tabs() {
		return array(
			'pages'     => __( 'Pages, Headers & Footers', 'designstudio-flow' ),
			'forms'     => __( 'Forms', 'designstudio-flow' ),
			'redirects' => __( 'Redirects', 'designstudio-flow' ),
			'mail'      => __( 'Mail / SMTP', 'designstudio-flow' ),
		);
	}

	public function render_tools_page() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$tabs   = $this->get_tabs();
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'pages';
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'pages';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DesignStudio Flow — Tools', 'designstudio-flow' ); ?></h1>

			<nav class="nav-tab-wrapper" style="margin-top:12px;">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'dsf-tools', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>"
					><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'forms' === $active ) {
				$this->render_forms_tab();
			} elseif ( 'redirects' === $active && class_exists( 'DSF_Redirects' ) ) {
				DSF_Redirects::get_instance()->render_admin_tab();
			} elseif ( 'mail' === $active && class_exists( 'DSF_Mail_SMTP' ) ) {
				DSF_Mail_SMTP::get_instance()->render_admin_tab();
			} else {
				$this->render_pages_tab();
			}
			?>
		</div>
		<?php
	}

	private function render_pages_tab() {
		?>
		<div class="dsf-tools-grid" style="display:grid;grid-template-columns:minmax(0,1fr);gap:20px;max-width:760px;margin-top:16px;">
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import', 'designstudio-flow' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
					<p><?php esc_html_e( 'Upload a JSON file exported by DesignStudio Flow. Each item is imported as a new post — existing pages, headers, footers, and saved blocks are never overwritten.', 'designstudio-flow' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf-import-file"><?php esc_html_e( 'JSON file', 'designstudio-flow' ); ?></label></th>
							<td><input type="file" id="dsf-import-file" name="dsf_import_file" accept=".json,application/json" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf-import-status"><?php esc_html_e( 'Imported item status', 'designstudio-flow' ); ?></label></th>
							<td>
								<select id="dsf-import-status" name="dsf_import_status">
									<option value="draft"><?php esc_html_e( 'Draft (recommended)', 'designstudio-flow' ); ?></option>
									<option value="publish"><?php esc_html_e( 'Published', 'designstudio-flow' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Media', 'designstudio-flow' ); ?></th>
							<td>
								<label for="dsf-import-media">
									<input type="checkbox" id="dsf-import-media" name="dsf_import_media" value="1" checked>
									<?php esc_html_e( 'Download images & videos referenced in blocks into this site\'s Media Library', 'designstudio-flow' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Fetches each media URL from the source site and rewrites the block to use the local copy. Media already on this site is reused, not duplicated.', 'designstudio-flow' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'designstudio-flow' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Export', 'designstudio-flow' ); ?></h2>
				<p><?php esc_html_e( 'Use the "Export" row action on a single item, or pick "Export to JSON" from the Bulk Actions dropdown to export multiple at once.', 'designstudio-flow' ); ?></p>
				<ul style="list-style:disc;margin-left:24px;">
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&dsf_flow=1' ) ); ?>"><?php esc_html_e( 'Pages', 'designstudio-flow' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=header' ) ); ?>"><?php esc_html_e( 'Headers', 'designstudio-flow' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=footer' ) ); ?>"><?php esc_html_e( 'Footers', 'designstudio-flow' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_saved_block' ) ); ?>"><?php esc_html_e( 'Saved Blocks', 'designstudio-flow' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_template' ) ); ?>"><?php esc_html_e( 'Templates', 'designstudio-flow' ); ?></a></li>
				</ul>
				<p class="description"><?php esc_html_e( 'Media (images, videos) is referenced by URL. On import you can pull it into the destination Media Library automatically — just keep the source site reachable while importing.', 'designstudio-flow' ); ?></p>
			</div>
		</div>
		<?php
	}

	private function render_forms_tab() {
		if ( class_exists( 'DSF_Entries' ) && method_exists( 'DSF_Entries', 'render_tools_content' ) ) {
			DSF_Entries::get_instance()->render_tools_content();
			return;
		}

		echo '<p style="margin-top:16px;">' . esc_html__( 'Forms import / export is unavailable.', 'designstudio-flow' ) . '</p>';
	}

	public function handle_import() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'designstudio-flow' ) );
		}

		check_admin_referer( self::IMPORT_ACTION );

		$redirect_base = add_query_arg(
			array(
				'page' => 'dsf-tools',
				'tab'  => 'pages',
			),
			admin_url( 'admin.php' )
		);

		if ( empty( $_FILES['dsf_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['dsf_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_import', 'no_file', $redirect_base ) );
			exit;
		}

		$raw  = file_get_contents( $_FILES['dsf_import_file']['tmp_name'] );
		$data = $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $data ) || empty( $data[ self::EXPORT_FLAG ] ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_import', 'invalid', $redirect_base ) );
			exit;
		}

		$status       = ( isset( $_POST['dsf_import_status'] ) && 'publish' === $_POST['dsf_import_status'] ) ? 'publish' : 'draft';
		$import_media = isset( $_POST['dsf_import_media'] );

		// Budget the whole import's media sideloading: a file cap and a wall-clock
		// deadline, so a media-heavy import can't run past PHP's time limit. Any
		// media beyond the budget keeps its original URL.
		if ( $import_media ) {
			$this->media_files_remaining = (int) apply_filters( 'dsf_import_media_max_files', 100 );
			$time_budget                 = (int) apply_filters( 'dsf_import_media_time_budget', 25 );
			$this->media_deadline        = $time_budget > 0 ? ( microtime( true ) + $time_budget ) : 0;
			$this->media_skipped         = 0;
		}

		$imported = 0;
		$skipped  = 0;
		foreach ( $data['items'] as $item ) {
			if ( $this->import_item( $item, $status, $import_media ) ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		$url = add_query_arg(
			array(
				'dsf_import'             => 'done',
				'dsf_import_count'       => $imported,
				'dsf_import_skipped'     => $skipped,
				'dsf_import_media_left'  => $import_media ? (int) $this->media_skipped : 0,
			),
			$redirect_base
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function import_item( $item, $status, $import_media = false ) {
		if ( ! is_array( $item ) ) {
			return false;
		}

		$post_type = isset( $item['post_type'] ) ? sanitize_key( $item['post_type'] ) : '';
		if ( ! in_array( $post_type, array( 'page', 'dsf_page', 'dsf_layout', 'dsf_saved_block', 'dsf_template' ), true ) ) {
			return false;
		}

		if ( 'dsf_page' === $post_type ) {
			$post_type = 'page';
		}

		$title   = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		$slug    = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
		$excerpt = isset( $item['excerpt'] ) ? wp_kses_post( $item['excerpt'] ) : '';

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_title'   => $title ? $title : __( 'Imported', 'designstudio-flow' ),
				'post_name'    => $slug,
				'post_excerpt' => $excerpt,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		$meta        = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
		$media_cache = array();
		// Meta keys whose block settings may reference images/videos by URL.
		$media_keys = array( '_dsf_blocks', '_dsf_block_settings', '_dsf_template_blocks', '_dsf_settings' );
		foreach ( $this->get_meta_keys_for_type( $post_type ) as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}
			$value = $meta[ $key ];
			if ( $import_media && in_array( $key, $media_keys, true ) ) {
				$value = $this->sideload_media_in_value( $value, $post_id, $media_cache );
			}
			update_post_meta( $post_id, $key, wp_slash( $value ) );
		}

		if ( 'dsf_layout' === $post_type ) {
			$layout_type = isset( $item['layout_type'] ) ? sanitize_key( $item['layout_type'] ) : '';
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = isset( $meta['_dsf_layout_type'] ) && in_array( $meta['_dsf_layout_type'], array( 'header', 'footer' ), true )
					? $meta['_dsf_layout_type']
					: 'header';
			}
			update_post_meta( $post_id, '_dsf_layout_type', $layout_type );
		}

		if ( 'page' === $post_type ) {
			update_post_meta( $post_id, '_dsf_enabled', true );
		}

		return $post_id;
	}

	/**
	 * Recursively walk an imported meta value, downloading any referenced media
	 * (images/videos/etc.) into this site's media library and rewriting the URL
	 * to the new local attachment. Originals are kept on any failure.
	 *
	 * @param mixed $value   Settings value (array or scalar).
	 * @param int   $post_id Post the media is attached to.
	 * @param array $cache   Original-URL => new-URL map (dedupes downloads).
	 * @return mixed
	 */
	private function sideload_media_in_value( $value, $post_id, &$cache ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->sideload_media_in_value( $item, $post_id, $cache );
			}
			return $value;
		}

		if ( is_string( $value ) && $this->looks_like_media_url( $value ) ) {
			return $this->sideload_url( $value, $post_id, $cache );
		}

		return $value;
	}

	private function looks_like_media_url( $url ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$allowed = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg', 'mp4', 'm4v', 'mov', 'webm', 'ogv', 'ogg', 'mp3', 'wav', 'm4a', 'pdf' );
		if ( in_array( $ext, $allowed, true ) ) {
			return true;
		}
		// Fall back to anything served from an uploads directory.
		return false !== strpos( $url, '/wp-content/uploads/' );
	}

	private function sideload_url( $url, $post_id, &$cache ) {
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		// Already a local attachment on this site — reuse it, never duplicate.
		if ( attachment_url_to_postid( $url ) ) {
			$cache[ $url ] = $url;
			return $url;
		}

		// SSRF guard: never fetch from private/reserved/unresolvable hosts.
		if ( ! $this->is_safe_remote_host( $url ) ) {
			$cache[ $url ] = $url;
			return $url;
		}

		// Respect the import-wide media budget (file count + wall clock). Anything
		// past the budget keeps its original URL so the import still completes.
		if ( null !== $this->media_files_remaining ) {
			if ( $this->media_files_remaining <= 0 || ( $this->media_deadline && microtime( true ) > $this->media_deadline ) ) {
				$this->media_skipped++;
				$cache[ $url ] = $url;
				return $url;
			}
		}

		// Skip oversized media when the server advertises a length over the cap.
		$max_bytes = (int) apply_filters( 'dsf_import_media_max_bytes', 64 * 1024 * 1024 );
		if ( $max_bytes > 0 ) {
			$head = wp_remote_head( $url, array( 'timeout' => 10, 'redirection' => 3 ) );
			if ( ! is_wp_error( $head ) ) {
				$length = (int) wp_remote_retrieve_header( $head, 'content-length' );
				if ( $length > 0 && $length > $max_bytes ) {
					$cache[ $url ] = $url;
					return $url;
				}
			}
		}

		// Consume one slot from the file-count budget for this download attempt.
		if ( null !== $this->media_files_remaining ) {
			$this->media_files_remaining--;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$cache[ $url ] = $url;
			return $url;
		}

		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name ) {
			$name = 'imported-media';
		}

		// SVGs are sanitized before import (and require a temporary mime allowance,
		// since WordPress blocks SVG uploads by default). If sanitizing fails, the
		// original URL is kept rather than importing untrusted markup.
		$is_svg = 'svg' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( $is_svg && ! $this->sanitize_svg_file( $tmp ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			$cache[ $url ] = $url;
			return $url;
		}

		$file_array = array(
			'name'     => sanitize_file_name( $name ),
			'tmp_name' => $tmp,
		);

		if ( $is_svg ) {
			$this->allow_svg_uploads( true );
		}
		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( $is_svg ) {
			$this->allow_svg_uploads( false );
		}

		if ( is_wp_error( $attach_id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			$cache[ $url ] = $url;
			return $url;
		}

		$new_url       = wp_get_attachment_url( $attach_id );
		$cache[ $url ] = $new_url ? $new_url : $url;
		return $cache[ $url ];
	}

	/**
	 * Toggle a temporary allowance for SVG uploads, scoped to a single sideload.
	 */
	private function allow_svg_uploads( $enable ) {
		if ( $enable ) {
			add_filter( 'upload_mimes', array( $this, 'filter_allow_svg_mime' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_svg_filetype' ), 10, 4 );
		} else {
			remove_filter( 'upload_mimes', array( $this, 'filter_allow_svg_mime' ) );
			remove_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_svg_filetype' ), 10 );
		}
	}

	public function filter_allow_svg_mime( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	public function filter_svg_filetype( $data, $file, $filename ) {
		if ( '.svg' === strtolower( substr( (string) $filename, -4 ) ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	/**
	 * Sanitize an SVG file in place: drop DOCTYPE/entities, script-bearing
	 * elements, event-handler attributes, and javascript:/data: links. Returns
	 * false if the file isn't a parseable SVG (caller then keeps the remote URL).
	 */
	private function sanitize_svg_file( $path ) {
		$content = @file_get_contents( $path );
		if ( false === $content || '' === trim( (string) $content ) || false === stripos( $content, '<svg' ) ) {
			return false;
		}

		// Strip DOCTYPE (blocks inline entity definitions / XXE) before parsing.
		$content = preg_replace( '/<!DOCTYPE.*?>/is', '', $content );

		if ( ! class_exists( 'DOMDocument' ) ) {
			return false;
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return false;
		}

		$strip_tags = array( 'script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'handler', 'listener', 'set', 'animate', 'animatemotion', 'animatetransform' );
		$remove     = array();
		$xpath      = new DOMXPath( $dom );

		foreach ( $xpath->query( '//*' ) as $node ) {
			if ( in_array( strtolower( $node->localName ), $strip_tags, true ) ) {
				$remove[] = $node;
				continue;
			}
			if ( ! $node->hasAttributes() ) {
				continue;
			}
			$drop_attrs = array();
			foreach ( $node->attributes as $attr ) {
				$name  = strtolower( $attr->nodeName );
				$value = preg_replace( '/\s+/', '', strtolower( (string) $attr->nodeValue ) );
				if ( 0 === strpos( $name, 'on' ) ) {
					$drop_attrs[] = $attr->nodeName;
				} elseif ( in_array( $name, array( 'href', 'xlink:href', 'src' ), true ) && ( 0 === strpos( $value, 'javascript:' ) || 0 === strpos( $value, 'data:' ) ) ) {
					$drop_attrs[] = $attr->nodeName;
				}
			}
			foreach ( $drop_attrs as $attr_name ) {
				$node->removeAttribute( $attr_name );
			}
		}

		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}

		$clean = $dom->saveXML();
		if ( ! $clean ) {
			return false;
		}
		return false !== file_put_contents( $path, $clean );
	}

	/**
	 * SSRF guard: a URL is safe to fetch only if its host resolves and every
	 * resolved address is a public IP (no localhost/private/link-local ranges).
	 */
	private function is_safe_remote_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}

		$ips = array();
		$v4  = gethostbynamel( $host );
		if ( is_array( $v4 ) ) {
			$ips = array_merge( $ips, $v4 );
		}
		if ( function_exists( 'dns_get_record' ) ) {
			$v6 = dns_get_record( $host, DNS_AAAA );
			if ( is_array( $v6 ) ) {
				foreach ( $v6 as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}

		if ( empty( $ips ) ) {
			return false;
		}

		foreach ( $ips as $ip ) {
			if ( ! $this->is_public_ip( $ip ) ) {
				return false;
			}
		}

		return true;
	}

	private function is_public_ip( $ip ) {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	public function show_admin_notices() {
		if ( isset( $_GET['dsf_import'] ) ) {
			$result = sanitize_key( wp_unslash( $_GET['dsf_import'] ) );
			if ( 'done' === $result ) {
				$count   = isset( $_GET['dsf_import_count'] ) ? intval( $_GET['dsf_import_count'] ) : 0;
				$skipped = isset( $_GET['dsf_import_skipped'] ) ? intval( $_GET['dsf_import_skipped'] ) : 0;
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: imported count, 2: skipped count */
							_n(
								'Imported %1$d item (%2$d skipped).',
								'Imported %1$d items (%2$d skipped).',
								$count,
								'designstudio-flow'
							),
							$count,
							$skipped
						)
					)
				);

				$media_left = isset( $_GET['dsf_import_media_left'] ) ? intval( $_GET['dsf_import_media_left'] ) : 0;
				if ( $media_left > 0 ) {
					printf(
						'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %d: number of media files left as remote URLs */
								_n(
									'%d media file exceeded the import limit and was left as a remote URL. Re-run the import to fetch the rest.',
									'%d media files exceeded the import limit and were left as remote URLs. Re-run the import to fetch the rest.',
									$media_left,
									'designstudio-flow'
								),
								$media_left
							)
						)
					);
				}
			} elseif ( 'invalid' === $result ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Invalid file. Please upload a JSON file exported by DesignStudio Flow.', 'designstudio-flow' )
				);
			} elseif ( 'no_file' === $result ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'No file was uploaded.', 'designstudio-flow' )
				);
			}
		}

		if ( isset( $_GET['dsf_export_status'] ) && 'empty' === sanitize_key( wp_unslash( $_GET['dsf_export_status'] ) ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'No items selected for export.', 'designstudio-flow' )
			);
		}
	}
}
