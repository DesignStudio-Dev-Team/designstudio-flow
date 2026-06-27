<?php
/**
 * Entries admin UI, plus export/import for forms and entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Entries {

	private static $instance = null;

	const MENU_SLUG_ENTRIES = 'dsf-entries';
	const MENU_SLUG_IMPORT  = 'dsf-import-export';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 45 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_post_dsf_export_form', array( $this, 'handle_export_form' ) );
		add_action( 'admin_post_dsf_export_entries_csv', array( $this, 'handle_export_entries_csv' ) );
		add_action( 'admin_post_dsf_export_entries_json', array( $this, 'handle_export_entries_json' ) );
		add_action( 'admin_post_dsf_export_all_forms', array( $this, 'handle_export_all_forms' ) );
		add_action( 'admin_post_dsf_export_all_entries', array( $this, 'handle_export_all_entries' ) );
		add_action( 'admin_post_dsf_import_forms', array( $this, 'handle_import_forms' ) );
		add_action( 'admin_post_dsf_import_entries', array( $this, 'handle_import_entries' ) );
	}

	public function register_menus() {
		add_submenu_page(
			'designstudio-flow',
			__( 'Form Entries', 'designstudio-flow' ),
			__( 'Entries', 'designstudio-flow' ),
			'edit_pages',
			self::MENU_SLUG_ENTRIES,
			array( $this, 'render_entries_page' )
		);

		// Forms & entries import/export now lives in the Tools page "Forms" tab
		// (rendered via render_tools_content), so no separate submenu is added.
	}

	/**
	 * URL of the Tools page "Forms" tab where this UI is surfaced.
	 *
	 * @return string
	 */
	private function tools_forms_url() {
		return add_query_arg(
			array(
				'page' => 'dsf-tools',
				'tab'  => 'forms',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Handles delete-entry GET action originating from the list table.
	 */
	public function handle_actions() {
		if ( ! is_admin() || empty( $_GET['page'] ) || self::MENU_SLUG_ENTRIES !== $_GET['page'] ) {
			return;
		}
		if ( empty( $_GET['action'] ) || 'delete' !== $_GET['action'] ) {
			return;
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete entries.', 'designstudio-flow' ) );
		}
		$entry_id = isset( $_GET['entry_id'] ) ? intval( $_GET['entry_id'] ) : 0;
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $entry_id || ! wp_verify_nonce( $nonce, 'dsf_delete_entry_' . $entry_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'designstudio-flow' ) );
		}
		DSF_Forms::get_instance()->delete_entry( $entry_id );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG_ENTRIES, 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Pages ────────────────────────────────────────────────────────────────

	public function render_entries_page() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$entry_id = isset( $_GET['view'] ) ? intval( $_GET['view'] ) : 0;
		if ( $entry_id ) {
			$this->render_single_entry( $entry_id );
			return;
		}

		$forms       = $this->get_forms_for_dropdown();
		$form_filter = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;
		$paged       = max( 1, isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1 );
		$per_page    = 20;
		$result      = DSF_Forms::get_instance()->query_entries(
			array(
				'form_id' => $form_filter,
				'limit'   => $per_page,
				'offset'  => ( $paged - 1 ) * $per_page,
			)
		);
		$entries     = $result['entries'];
		$total       = $result['total'];
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Form Entries', 'designstudio-flow' ) . '</h1>';

		// Top toolbar: filter + bulk export.
		echo '<form method="get" style="margin: 12px 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG_ENTRIES ) . '">';
		echo '<select name="form_id">';
		echo '<option value="0">' . esc_html__( 'All Forms', 'designstudio-flow' ) . '</option>';
		foreach ( $forms as $form_id => $form_title ) {
			$selected = ( $form_filter === $form_id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $form_id ) . '"' . $selected . '>' . esc_html( $form_title ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'designstudio-flow' ), 'secondary', '', false );
		echo '</form>';

		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry deleted.', 'designstudio-flow' ) . '</p></div>';
		}

		// Per-form export links when filtered.
		if ( $form_filter ) {
			$base    = admin_url( 'admin-post.php' );
			$csv_url = wp_nonce_url( add_query_arg( array( 'action' => 'dsf_export_entries_csv', 'form_id' => $form_filter ), $base ), 'dsf_export' );
			$json_url = wp_nonce_url( add_query_arg( array( 'action' => 'dsf_export_entries_json', 'form_id' => $form_filter ), $base ), 'dsf_export' );
			echo '<p>';
			echo '<a class="button" href="' . esc_url( $csv_url ) . '">' . esc_html__( 'Export Entries (CSV)', 'designstudio-flow' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $json_url ) . '">' . esc_html__( 'Export Entries (JSON)', 'designstudio-flow' ) . '</a>';
			echo '</p>';
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'designstudio-flow' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'designstudio-flow' ) . '</th>';
		echo '<th>' . esc_html__( 'Preview', 'designstudio-flow' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'designstudio-flow' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $entries ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No entries yet.', 'designstudio-flow' ) . '</td></tr>';
		} else {
			foreach ( $entries as $entry ) {
				$view_url   = add_query_arg(
					array(
						'page' => self::MENU_SLUG_ENTRIES,
						'view' => $entry['id'],
					),
					admin_url( 'admin.php' )
				);
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'page'     => self::MENU_SLUG_ENTRIES,
							'action'   => 'delete',
							'entry_id' => $entry['id'],
						),
						admin_url( 'admin.php' )
					),
					'dsf_delete_entry_' . $entry['id']
				);
				$form_title = isset( $forms[ $entry['form_id'] ] ) ? $forms[ $entry['form_id'] ] : '#' . $entry['form_id'];
				$preview    = $this->build_entry_preview( $entry['data'] );

				echo '<tr>';
				echo '<td>' . esc_html( $entry['submitted_at'] ) . '</td>';
				echo '<td>' . esc_html( $form_title ) . '</td>';
				echo '<td>' . esc_html( $preview ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'designstudio-flow' ) . '</a> | ';
				echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this entry?', 'designstudio-flow' ) ) . '\');" style="color:#b91c1c;">' . esc_html__( 'Delete', 'designstudio-flow' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $total_pages > 1 ) {
			$page_links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'total'     => $total_pages,
					'current'   => $paged,
					'prev_text' => '«',
					'next_text' => '»',
				)
			);
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $page_links ) . '</div></div>';
		}

		echo '</div>';
	}

	private function render_single_entry( $entry_id ) {
		$entry = DSF_Forms::get_instance()->get_entry( $entry_id );
		if ( ! $entry ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Entry not found', 'designstudio-flow' ) . '</h1></div>';
			return;
		}
		$back_url = remove_query_arg( 'view' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $entry['title'] ) . '</h1>';
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to entries', 'designstudio-flow' ) . '</a></p>';

		echo '<table class="wp-list-table widefat striped">';
		echo '<tbody>';
		echo '<tr><th style="width:200px;">' . esc_html__( 'Submitted', 'designstudio-flow' ) . '</th><td>' . esc_html( $entry['submitted_at'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Form ID', 'designstudio-flow' ) . '</th><td>' . esc_html( (string) $entry['form_id'] ) . '</td></tr>';

		foreach ( $entry['data'] as $field_name => $value ) {
			$display = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			echo '<tr>';
			echo '<th>' . esc_html( $field_name ) . '</th>';
			echo '<td>' . esc_html( $display ) . '</td>';
			echo '</tr>';
		}

		if ( ! empty( $entry['context'] ) ) {
			foreach ( $entry['context'] as $ctx_key => $ctx_val ) {
				echo '<tr>';
				echo '<th>' . esc_html( $ctx_key ) . '</th>';
				echo '<td><code>' . esc_html( (string) $ctx_val ) . '</code></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Forms & entries import/export UI, rendered inside the Tools page "Forms" tab.
	 * The Tools page supplies the surrounding .wrap and page title.
	 */
	public function render_tools_content() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$forms = $this->get_forms_for_dropdown();
		$base  = admin_url( 'admin-post.php' );

		if ( isset( $_GET['imported_forms'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Imported %d form(s).', 'designstudio-flow' ), intval( $_GET['imported_forms'] ) ) . '</p></div>';
		}
		if ( isset( $_GET['imported_entries'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Imported %d entries.', 'designstudio-flow' ), intval( $_GET['imported_entries'] ) ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['error'] ) ) . '</p></div>';
		}

		// ── Export ────────────────────────────────────────────────────────
		echo '<h2>' . esc_html__( 'Export', 'designstudio-flow' ) . '</h2>';
		echo '<div class="card" style="padding:16px;max-width:720px;">';
		echo '<h3>' . esc_html__( 'A single form', 'designstudio-flow' ) . '</h3>';
		echo '<form method="get" action="' . esc_url( $base ) . '">';
		echo '<input type="hidden" name="action" value="dsf_export_form">';
		wp_nonce_field( 'dsf_export', '_wpnonce' );
		echo '<select name="form_id" required>';
		echo '<option value="">' . esc_html__( 'Choose a form…', 'designstudio-flow' ) . '</option>';
		foreach ( $forms as $id => $title ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Download Form JSON', 'designstudio-flow' ), 'primary', '', false );
		echo '</form>';

		echo '<h3 style="margin-top:24px;">' . esc_html__( 'Entries for a form', 'designstudio-flow' ) . '</h3>';
		echo '<form method="get" action="' . esc_url( $base ) . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
		echo '<input type="hidden" name="action" id="dsf-entries-export-action" value="dsf_export_entries_csv">';
		wp_nonce_field( 'dsf_export', '_wpnonce' );
		echo '<select name="form_id" required>';
		echo '<option value="">' . esc_html__( 'Choose a form…', 'designstudio-flow' ) . '</option>';
		foreach ( $forms as $id => $title ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</option>';
		}
		echo '</select>';
		echo '<button type="submit" class="button button-primary" onclick="document.getElementById(\'dsf-entries-export-action\').value=\'dsf_export_entries_csv\';">' . esc_html__( 'Download CSV', 'designstudio-flow' ) . '</button>';
		echo '<button type="submit" class="button" onclick="document.getElementById(\'dsf-entries-export-action\').value=\'dsf_export_entries_json\';">' . esc_html__( 'Download JSON', 'designstudio-flow' ) . '</button>';
		echo '</form>';

		echo '<h3 style="margin-top:24px;">' . esc_html__( 'Everything', 'designstudio-flow' ) . '</h3>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( 'action', 'dsf_export_all_forms', $base ), 'dsf_export' ) ) . '">' . esc_html__( 'All Forms (ZIP)', 'designstudio-flow' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( 'action', 'dsf_export_all_entries', $base ), 'dsf_export' ) ) . '">' . esc_html__( 'All Entries (ZIP)', 'designstudio-flow' ) . '</a>';
		echo '</p>';
		echo '</div>';

		// ── Import ────────────────────────────────────────────────────────
		echo '<h2 style="margin-top:32px;">' . esc_html__( 'Import', 'designstudio-flow' ) . '</h2>';
		echo '<div class="card" style="padding:16px;max-width:720px;">';
		echo '<h3>' . esc_html__( 'Import Forms', 'designstudio-flow' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Upload a single form .json or a .zip containing multiple form .json files. Each import always creates a new form (existing forms are never overwritten).', 'designstudio-flow' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $base ) . '">';
		echo '<input type="hidden" name="action" value="dsf_import_forms">';
		wp_nonce_field( 'dsf_import_forms', 'dsf_import_nonce' );
		echo '<input type="file" name="dsf_import_file" accept=".json,.zip" required> ';
		submit_button( __( 'Import Forms', 'designstudio-flow' ), 'primary', '', false );
		echo '</form>';

		echo '<h3 style="margin-top:24px;">' . esc_html__( 'Import Entries', 'designstudio-flow' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Upload a .csv or .json of entries. You must pick the target form so column/key names map correctly. New entry records are created.', 'designstudio-flow' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $base ) . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
		echo '<input type="hidden" name="action" value="dsf_import_entries">';
		wp_nonce_field( 'dsf_import_entries', 'dsf_import_nonce' );
		echo '<select name="form_id" required>';
		echo '<option value="">' . esc_html__( 'Target form…', 'designstudio-flow' ) . '</option>';
		foreach ( $forms as $id => $title ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</option>';
		}
		echo '</select>';
		echo '<input type="file" name="dsf_import_file" accept=".json,.csv" required> ';
		submit_button( __( 'Import Entries', 'designstudio-flow' ), 'primary', '', false );
		echo '</form>';
		echo '</div>';
	}

	// ── Export handlers ─────────────────────────────────────────────────────

	private function require_export_nonce() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'designstudio-flow' ) );
		}
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dsf_export' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'designstudio-flow' ) );
		}
	}

	public function handle_export_form() {
		$this->require_export_nonce();
		$form_id = isset( $_REQUEST['form_id'] ) ? intval( $_REQUEST['form_id'] ) : 0;
		$payload = $this->build_form_export_payload( $form_id );
		if ( ! $payload ) {
			wp_die( esc_html__( 'Form not found.', 'designstudio-flow' ) );
		}
		$this->stream_download(
			sprintf( 'dsf-form-%d.json', $form_id ),
			'application/json',
			wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);
	}

	public function handle_export_entries_csv() {
		$this->require_export_nonce();
		$form_id = isset( $_REQUEST['form_id'] ) ? intval( $_REQUEST['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_die( esc_html__( 'Choose a form to export entries from.', 'designstudio-flow' ) );
		}
		$csv = $this->build_entries_csv( $form_id );
		$this->stream_download(
			sprintf( 'dsf-entries-form-%d.csv', $form_id ),
			'text/csv',
			$csv
		);
	}

	public function handle_export_entries_json() {
		$this->require_export_nonce();
		$form_id = isset( $_REQUEST['form_id'] ) ? intval( $_REQUEST['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_die( esc_html__( 'Choose a form to export entries from.', 'designstudio-flow' ) );
		}
		$payload = $this->build_entries_json( $form_id );
		$this->stream_download(
			sprintf( 'dsf-entries-form-%d.json', $form_id ),
			'application/json',
			wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);
	}

	public function handle_export_all_forms() {
		$this->require_export_nonce();
		$forms = get_posts( array( 'post_type' => 'dsf_form', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) );
		if ( ! $forms ) {
			wp_die( esc_html__( 'No forms to export.', 'designstudio-flow' ) );
		}
		$files = array();
		foreach ( $forms as $form ) {
			$payload = $this->build_form_export_payload( $form->ID );
			if ( ! $payload ) {
				continue;
			}
			$files[ sprintf( 'dsf-form-%d.json', $form->ID ) ] = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}
		$this->stream_zip( 'dsf-forms.zip', $files );
	}

	public function handle_export_all_entries() {
		$this->require_export_nonce();
		$forms = get_posts( array( 'post_type' => 'dsf_form', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) );
		if ( ! $forms ) {
			wp_die( esc_html__( 'No forms to export.', 'designstudio-flow' ) );
		}
		$files = array();
		foreach ( $forms as $form ) {
			$csv  = $this->build_entries_csv( $form->ID );
			$json = $this->build_entries_json( $form->ID );
			$files[ sprintf( 'dsf-entries-form-%d.csv', $form->ID ) ]  = $csv;
			$files[ sprintf( 'dsf-entries-form-%d.json', $form->ID ) ] = wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}
		$this->stream_zip( 'dsf-entries.zip', $files );
	}

	// ── Import handlers ─────────────────────────────────────────────────────

	public function handle_import_forms() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to import.', 'designstudio-flow' ) );
		}
		check_admin_referer( 'dsf_import_forms', 'dsf_import_nonce' );

		$file = $this->grab_uploaded_file();
		if ( is_wp_error( $file ) ) {
			$this->import_redirect_with_error( $file->get_error_message() );
		}

		$payloads = $this->extract_json_payloads_from_upload( $file );
		if ( empty( $payloads ) ) {
			$this->import_redirect_with_error( __( 'No valid form JSON found in the upload.', 'designstudio-flow' ) );
		}

		$imported = 0;
		foreach ( $payloads as $payload ) {
			if ( $this->import_single_form_payload( $payload ) ) {
				++$imported;
			}
		}

		wp_safe_redirect( add_query_arg( array( 'imported_forms' => $imported ), $this->tools_forms_url() ) );
		exit;
	}

	public function handle_import_entries() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to import.', 'designstudio-flow' ) );
		}
		check_admin_referer( 'dsf_import_entries', 'dsf_import_nonce' );

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		if ( ! $form_id || ! get_post( $form_id ) || 'dsf_form' !== get_post_type( $form_id ) ) {
			$this->import_redirect_with_error( __( 'Pick a valid target form.', 'designstudio-flow' ) );
		}

		$file = $this->grab_uploaded_file();
		if ( is_wp_error( $file ) ) {
			$this->import_redirect_with_error( $file->get_error_message() );
		}

		$rows = $this->parse_entries_upload( $file );
		if ( empty( $rows ) ) {
			$this->import_redirect_with_error( __( 'No entries found in the upload.', 'designstudio-flow' ) );
		}

		$imported = 0;
		foreach ( $rows as $row ) {
			$entry_id = wp_insert_post(
				array(
					'post_type'   => 'dsf_entry',
					'post_status' => 'publish',
					'post_title'  => sprintf( 'Entry (imported) — Form #%d', $form_id ),
					'meta_input'  => array(
						'_dsf_entry_form_id'      => $form_id,
						'_dsf_entry_data'         => $row['data'],
						'_dsf_entry_submitted_at' => $row['submitted_at'] ?: current_time( 'mysql' ),
						'_dsf_entry_context'      => array( 'imported' => 1 ),
					),
				),
				false
			);
			if ( $entry_id && ! is_wp_error( $entry_id ) ) {
				++$imported;
			}
		}

		wp_safe_redirect( add_query_arg( array( 'imported_entries' => $imported ), $this->tools_forms_url() ) );
		exit;
	}

	// ── Builders ────────────────────────────────────────────────────────────

	private function build_form_export_payload( $form_id ) {
		$form = get_post( $form_id );
		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			return null;
		}
		return array(
			'dsf_export'   => 'form',
			'version'      => 1,
			'exported_at'  => gmdate( 'c' ),
			'post_title'   => $form->post_title,
			'post_status'  => $form->post_status,
			'rows'         => get_post_meta( $form_id, '_dsf_form_rows', true ),
			'settings'     => get_post_meta( $form_id, '_dsf_form_settings', true ),
		);
	}

	private function build_entries_csv( $form_id ) {
		$rows = $this->collect_field_names_for_form( $form_id );
		$result = DSF_Forms::get_instance()->query_entries( array( 'form_id' => $form_id, 'limit' => -1 ) );

		// Use a memory-backed stream so fputcsv handles escaping correctly.
		$fh = fopen( 'php://temp', 'r+' );
		// Prepend UTF-8 BOM so Excel opens the file with the right encoding.
		fwrite( $fh, "\xEF\xBB\xBF" );
		$header = array_merge( array( 'entry_id', 'submitted_at' ), $rows );
		fputcsv( $fh, $header, ',', '"', '' );
		foreach ( $result['entries'] as $entry ) {
			$row = array( $entry['id'], $entry['submitted_at'] );
			foreach ( $rows as $field_name ) {
				$val = $entry['data'][ $field_name ] ?? '';
				$row[] = is_array( $val ) ? implode( '|', array_map( 'strval', $val ) ) : (string) $val;
			}
			fputcsv( $fh, $row, ',', '"', '' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	private function build_entries_json( $form_id ) {
		$result  = DSF_Forms::get_instance()->query_entries( array( 'form_id' => $form_id, 'limit' => -1 ) );
		return array(
			'dsf_export'  => 'entries',
			'version'     => 1,
			'exported_at' => gmdate( 'c' ),
			'form_id'     => $form_id,
			'entries'     => array_map(
				function ( $entry ) {
					return array(
						'submitted_at' => $entry['submitted_at'],
						'data'         => $entry['data'],
					);
				},
				$result['entries']
			),
		);
	}

	private function collect_field_names_for_form( $form_id ) {
		$rows = get_post_meta( $form_id, '_dsf_form_rows', true );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$names = array();
		foreach ( $rows as $row ) {
			$fields = $row['fields'] ?? array();
			foreach ( $fields as $field ) {
				$type = $field['type'] ?? '';
				$name = $field['name'] ?? '';
				if ( in_array( $type, array( 'html', 'page_break' ), true ) ) {
					continue;
				}
				if ( $name && ! in_array( $name, $names, true ) ) {
					$names[] = $name;
				}
			}
		}
		return $names;
	}

	private function build_entry_preview( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}
		$parts = array();
		foreach ( $data as $name => $value ) {
			$display = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			$display = trim( $display );
			if ( '' === $display ) {
				continue;
			}
			$parts[] = $name . ': ' . $display;
			if ( count( $parts ) >= 3 ) {
				break;
			}
		}
		$summary = implode( ' · ', $parts );
		if ( strlen( $summary ) > 120 ) {
			$summary = substr( $summary, 0, 117 ) . '…';
		}
		return $summary;
	}

	private function get_forms_for_dropdown() {
		$posts = get_posts(
			array(
				'post_type'   => 'dsf_form',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[ $post->ID ] = $post->post_title;
		}
		return $out;
	}

	// ── Import helpers ──────────────────────────────────────────────────────

	private function grab_uploaded_file() {
		if ( empty( $_FILES['dsf_import_file'] ) || ! isset( $_FILES['dsf_import_file']['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'designstudio-flow' ) );
		}
		$tmp = $_FILES['dsf_import_file']['tmp_name'];
		$name = sanitize_file_name( $_FILES['dsf_import_file']['name'] );
		if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'upload_failed', __( 'Upload failed.', 'designstudio-flow' ) );
		}
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'json', 'zip', 'csv' ), true ) ) {
			return new WP_Error( 'bad_type', __( 'File must be .json, .csv, or .zip.', 'designstudio-flow' ) );
		}
		return array( 'tmp' => $tmp, 'name' => $name, 'ext' => $ext );
	}

	private function extract_json_payloads_from_upload( $file ) {
		$payloads = array();
		if ( 'json' === $file['ext'] ) {
			$raw = file_get_contents( $file['tmp'] );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$payloads[] = $decoded;
			}
			return $payloads;
		}
		if ( 'zip' === $file['ext'] ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return $payloads;
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $file['tmp'] ) ) {
				return $payloads;
			}
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$entry = $zip->getNameIndex( $i );
				if ( ! preg_match( '/\.json$/i', $entry ) ) {
					continue;
				}
				$contents = $zip->getFromIndex( $i );
				$decoded  = json_decode( $contents, true );
				if ( is_array( $decoded ) ) {
					$payloads[] = $decoded;
				}
			}
			$zip->close();
		}
		return $payloads;
	}

	private function import_single_form_payload( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['rows'] ) ) {
			return false;
		}
		$title  = isset( $payload['post_title'] ) ? sanitize_text_field( $payload['post_title'] ) : 'Imported Form';
		$rows   = is_array( $payload['rows'] ) ? $payload['rows'] : array();
		$settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();

		$new_id = wp_insert_post(
			array(
				'post_type'   => 'dsf_form',
				'post_status' => 'publish',
				'post_title'  => $title . ' (imported)',
			),
			false
		);
		if ( ! $new_id || is_wp_error( $new_id ) ) {
			return false;
		}
		// Rows/settings are written raw here and re-sanitized on next save via the
		// form builder's sanitize_form_rows/sanitize_form_settings paths.
		update_post_meta( $new_id, '_dsf_form_rows', $rows );
		update_post_meta( $new_id, '_dsf_form_settings', $settings );
		return true;
	}

	private function parse_entries_upload( $file ) {
		if ( 'json' === $file['ext'] ) {
			$decoded = json_decode( file_get_contents( $file['tmp'] ), true );
			if ( ! is_array( $decoded ) || empty( $decoded['entries'] ) || ! is_array( $decoded['entries'] ) ) {
				return array();
			}
			$rows = array();
			foreach ( $decoded['entries'] as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['data'] ) || ! is_array( $entry['data'] ) ) {
					continue;
				}
				$rows[] = array(
					'submitted_at' => isset( $entry['submitted_at'] ) ? sanitize_text_field( $entry['submitted_at'] ) : '',
					'data'         => $this->sanitize_imported_entry_data( $entry['data'] ),
				);
			}
			return $rows;
		}

		if ( 'csv' === $file['ext'] ) {
			$fh = fopen( $file['tmp'], 'r' );
			if ( ! $fh ) {
				return array();
			}
			$header = fgetcsv( $fh, 0, ',', '"', '' );
			if ( ! $header ) {
				fclose( $fh );
				return array();
			}
			// Strip UTF-8 BOM that some exporters (including ours) write.
			if ( ! empty( $header[0] ) ) {
				$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
			}
			$rows = array();
			while ( ( $cols = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
				$assoc = array();
				foreach ( $header as $i => $key ) {
					$assoc[ $key ] = $cols[ $i ] ?? '';
				}
				$submitted = isset( $assoc['submitted_at'] ) ? sanitize_text_field( $assoc['submitted_at'] ) : '';
				unset( $assoc['entry_id'], $assoc['submitted_at'] );
				$rows[] = array(
					'submitted_at' => $submitted,
					'data'         => $this->sanitize_imported_entry_data( $assoc ),
				);
			}
			fclose( $fh );
			return $rows;
		}

		return array();
	}

	private function sanitize_imported_entry_data( $data ) {
		$clean = array();
		foreach ( $data as $key => $value ) {
			$k = sanitize_key( $key );
			if ( '' === $k ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$clean[ $k ] = array_map( 'sanitize_text_field', $value );
			} elseif ( false !== strpos( (string) $value, '|' ) ) {
				$clean[ $k ] = array_map( 'sanitize_text_field', explode( '|', (string) $value ) );
			} else {
				$clean[ $k ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}

	private function import_redirect_with_error( $message ) {
		wp_safe_redirect( add_query_arg( array( 'error' => rawurlencode( $message ) ), $this->tools_forms_url() ) );
		exit;
	}

	// ── Streaming helpers ───────────────────────────────────────────────────

	private function stream_download( $filename, $mime, $body ) {
		// Drop any output buffers so stray whitespace from other plugins doesn't
		// corrupt the download.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body;
		exit;
	}

	private function stream_zip( $filename, $files ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive PHP extension is required for bulk export.', 'designstudio-flow' ) );
		}
		$tmp = wp_tempnam( $filename );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Failed to create zip archive.', 'designstudio-flow' ) );
		}
		foreach ( $files as $name => $contents ) {
			$zip->addFromString( $name, (string) $contents );
		}
		$zip->close();
		$body = file_get_contents( $tmp );
		@unlink( $tmp );
		$this->stream_download( $filename, 'application/zip', $body );
	}
}
