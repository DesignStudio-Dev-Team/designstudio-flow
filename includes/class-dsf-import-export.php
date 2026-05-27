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

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_row_actions' ), 10, 2 );

		add_filter( 'bulk_actions-edit-dsf_page', array( $this, 'register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-dsf_layout', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-dsf_page', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-dsf_layout', array( $this, 'handle_bulk_action' ), 10, 3 );

		add_action( 'admin_post_' . self::SINGLE_ACTION, array( $this, 'handle_single_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );

		add_action( 'admin_menu', array( $this, 'add_tools_menu' ), 90 );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	private function is_supported_post( $post ) {
		return $post && in_array( $post->post_type, array( 'dsf_page', 'dsf_layout' ), true );
	}

	private function get_meta_keys_for_type( $post_type ) {
		if ( 'dsf_page' === $post_type ) {
			return array( '_dsf_blocks', '_dsf_settings', '_dsf_theme_colors' );
		}
		if ( 'dsf_layout' === $post_type ) {
			return array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' );
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
			__( 'Import / Export', 'designstudio-flow' ),
			__( 'Import / Export', 'designstudio-flow' ),
			'edit_pages',
			'dsf-tools',
			array( $this, 'render_tools_page' )
		);
	}

	public function render_tools_page() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DesignStudio Flow — Import / Export', 'designstudio-flow' ); ?></h1>

			<div class="dsf-tools-grid" style="display:grid;grid-template-columns:minmax(0,1fr);gap:20px;max-width:760px;margin-top:16px;">
				<div class="card" style="padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Import', 'designstudio-flow' ); ?></h2>
					<p><?php esc_html_e( 'Upload a JSON file exported by DesignStudio Flow. Each item is imported as a new post — existing pages, headers, and footers are never overwritten.', 'designstudio-flow' ); ?></p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
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
						</table>
						<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'designstudio-flow' ); ?></button></p>
					</form>
				</div>

				<div class="card" style="padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Export', 'designstudio-flow' ); ?></h2>
					<p><?php esc_html_e( 'Use the "Export" row action on a single item, or pick "Export to JSON" from the Bulk Actions dropdown to export multiple at once.', 'designstudio-flow' ); ?></p>
					<ul style="list-style:disc;margin-left:24px;">
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_page' ) ); ?>"><?php esc_html_e( 'Pages', 'designstudio-flow' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=header' ) ); ?>"><?php esc_html_e( 'Headers', 'designstudio-flow' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=footer' ) ); ?>"><?php esc_html_e( 'Footers', 'designstudio-flow' ); ?></a></li>
					</ul>
					<p class="description"><?php esc_html_e( 'Media (images, uploaded files) is referenced by URL — files are not bundled. Make sure media is available on the destination site.', 'designstudio-flow' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_import() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'designstudio-flow' ) );
		}

		check_admin_referer( self::IMPORT_ACTION );

		$redirect_base = admin_url( 'admin.php?page=dsf-tools' );

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

		$status = ( isset( $_POST['dsf_import_status'] ) && 'publish' === $_POST['dsf_import_status'] ) ? 'publish' : 'draft';

		$imported = 0;
		$skipped  = 0;
		foreach ( $data['items'] as $item ) {
			if ( $this->import_item( $item, $status ) ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		$url = add_query_arg(
			array(
				'dsf_import'         => 'done',
				'dsf_import_count'   => $imported,
				'dsf_import_skipped' => $skipped,
			),
			$redirect_base
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function import_item( $item, $status ) {
		if ( ! is_array( $item ) ) {
			return false;
		}

		$post_type = isset( $item['post_type'] ) ? sanitize_key( $item['post_type'] ) : '';
		if ( ! in_array( $post_type, array( 'dsf_page', 'dsf_layout' ), true ) ) {
			return false;
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

		$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
		foreach ( $this->get_meta_keys_for_type( $post_type ) as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, wp_slash( $meta[ $key ] ) );
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

		return $post_id;
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
