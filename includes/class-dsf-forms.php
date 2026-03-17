<?php
/**
 * Forms functionality for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Forms {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 40 );
		add_action( 'admin_init', array( $this, 'handle_form_editor_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'add_form_builder_row_action' ), 10, 2 );
		add_filter( 'manage_dsf_form_posts_columns', array( $this, 'add_form_columns' ) );
		add_action( 'manage_dsf_form_posts_custom_column', array( $this, 'render_form_columns' ), 10, 2 );
		add_action( 'wp_ajax_dsf_save_form', array( $this, 'save_form' ) );
		add_action( 'wp_ajax_dsf_submit_form', array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_dsf_submit_form', array( $this, 'submit_form' ) );
		add_shortcode( 'dsform', array( $this, 'render_form_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_shortcode_assets' ), 30 );
	}

	/**
	 * Add Forms pages to the DSF admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'designstudio-flow',
			__( 'DesignStudio Flow Forms', 'designstudio-flow' ),
			__( 'Forms', 'designstudio-flow' ),
			'edit_pages',
			'edit.php?post_type=dsf_form'
		);

		add_submenu_page(
			'designstudio-flow',
			__( 'Add New DSFlow Form', 'designstudio-flow' ),
			__( 'Add New DSFlow Form', 'designstudio-flow' ),
			'edit_pages',
			'dsf-form-builder',
			array( $this, 'render_form_builder_page' )
		);
	}

	/**
	 * Redirect default dsf_form editor routes to the form builder.
	 */
	public function handle_form_editor_redirect() {
		global $pagenow;

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$action  = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action  = $action ? sanitize_key( $action ) : '';
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';

		if ( 'post.php' === $pagenow && $post_id && 'edit' === $action ) {
			$post_id = intval( $post_id );
			if ( 'dsf_form' === get_post_type( $post_id ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dsf-form-builder&form_id=' . $post_id ) );
				exit;
			}
		}

		if ( 'post-new.php' === $pagenow && 'dsf_form' === $post_type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dsf-form-builder' ) );
			exit;
		}
	}

	/**
	 * Enqueue admin assets for the form builder.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'designstudio-flow_page_dsf-form-builder', 'admin_page_dsf-form-builder' ), true ) ) {
			return;
		}

		$form_id = filter_input( INPUT_GET, 'form_id', FILTER_VALIDATE_INT );
		$form_id = $form_id ? intval( $form_id ) : 0;
		$form    = $form_id ? get_post( $form_id ) : null;

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'dsf-forms',
			DSF_PLUGIN_URL . 'assets/css/forms.css',
			array(),
			$this->get_asset_version( 'assets/css/forms.css' )
		);
		wp_enqueue_style(
			'dsf-forms-builder',
			DSF_PLUGIN_URL . 'assets/css/forms-builder.css',
			array( 'dsf-forms' ),
			$this->get_asset_version( 'assets/css/forms-builder.css' )
		);
		wp_enqueue_script(
			'dsf-forms-builder',
			DSF_PLUGIN_URL . 'assets/js/forms-builder.js',
			array(),
			$this->get_asset_version( 'assets/js/forms-builder.js' ),
			true
		);

		wp_localize_script(
			'dsf-forms-builder',
			'dsfFormsBuilderData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'dsf_forms_nonce' ),
				'formId'      => $form_id,
				'formTitle'   => ( $form && 'dsf_form' === $form->post_type ) ? $form->post_title : '',
				'rows'        => $this->get_form_rows( $form_id ),
				'settings'    => $this->get_form_settings( $form_id ),
				'entriesCount' => $this->get_form_entries_count( $form_id ),
				'shortcode'   => $form_id ? "[dsform id='" . $form_id . "']" : '',
				'status'      => ( $form && 'dsf_form' === $form->post_type ) ? $form->post_status : 'draft',
				'adminListUrl' => admin_url( 'edit.php?post_type=dsf_form' ),
			)
		);
	}

	/**
	 * Render the form builder admin page.
	 */
	public function render_form_builder_page() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit forms.', 'designstudio-flow' ) );
		}

		$form_id = filter_input( INPUT_GET, 'form_id', FILTER_VALIDATE_INT );
		$form_id = $form_id ? intval( $form_id ) : 0;

		if ( ! $form_id ) {
			$form_id = wp_insert_post(
				array(
					'post_type'   => 'dsf_form',
					'post_status' => 'draft',
					'post_title'  => __( 'Untitled Form', 'designstudio-flow' ),
				)
			);

			if ( is_wp_error( $form_id ) || ! $form_id ) {
				wp_die( esc_html__( 'Unable to create the form.', 'designstudio-flow' ) );
			}

			update_post_meta( $form_id, '_dsf_form_rows', array() );
			update_post_meta( $form_id, '_dsf_form_settings', $this->get_default_form_settings() );
			update_post_meta( $form_id, '_dsf_form_entries_count', 0 );

			wp_safe_redirect( admin_url( 'admin.php?page=dsf-form-builder&form_id=' . intval( $form_id ) ) );
			exit;
		}

		$form = get_post( $form_id );
		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			wp_die( esc_html__( 'Form not found.', 'designstudio-flow' ) );
		}

		include DSF_PLUGIN_DIR . 'templates/forms-builder-page.php';
	}

	/**
	 * Add a row action to open the builder from the Forms list.
	 */
	public function add_form_builder_row_action( $actions, $post ) {
		if ( ! $post || 'dsf_form' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		$url = admin_url( 'admin.php?page=dsf-form-builder&form_id=' . intval( $post->ID ) );
		$actions['dsf_form_builder'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit with Form Builder', 'designstudio-flow' ) . '</a>';

		return $actions;
	}

	/**
	 * Add custom columns to the Forms list.
	 */
	public function add_form_columns( $columns ) {
		$updated = array();
		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'title' === $key ) {
				$updated['dsf_form_shortcode'] = __( 'Shortcode', 'designstudio-flow' );
			}
		}

		return $updated;
	}

	/**
	 * Render custom form columns.
	 */
	public function render_form_columns( $column, $post_id ) {
		if ( 'dsf_form_shortcode' !== $column ) {
			return;
		}

		echo '<code>[dsform id=\'' . intval( $post_id ) . '\']</code>';
	}

	/**
	 * Save form data from the builder.
	 */
	public function save_form() {
		if ( ! check_ajax_referer( 'dsf_forms_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => 'Invalid form ID' ) );
		}

		$form = get_post( $form_id );
		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			wp_send_json_error( array( 'message' => 'Form not found' ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$rows_raw = isset( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : '[]';
		$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';

		$rows_data = json_decode( $rows_raw, true );
		if ( ! is_array( $rows_data ) ) {
			wp_send_json_error( array( 'message' => 'Invalid rows payload' ) );
		}

		$settings_data = json_decode( $settings_raw, true );
		if ( ! is_array( $settings_data ) ) {
			wp_send_json_error( array( 'message' => 'Invalid settings payload' ) );
		}

		$rows     = $this->sanitize_form_rows( $rows_data );
		$settings = $this->sanitize_form_settings( $settings_data );

		update_post_meta( $form_id, '_dsf_form_rows', $rows );
		update_post_meta( $form_id, '_dsf_form_settings', $settings );

		$post_update = array(
			'ID'                => $form_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}

		if ( in_array( $status, array( 'draft', 'publish' ), true ) ) {
			$post_update['post_status'] = $status;
		}

		wp_update_post( $post_update );

		wp_send_json_success(
			array(
				'message'    => 'Form saved successfully',
				'form_id'    => $form_id,
				'post_title' => get_the_title( $form_id ),
				'shortcode'  => "[dsform id='" . $form_id . "']",
				'status'     => get_post_status( $form_id ),
			)
		);
	}

	/**
	 * Handle frontend form submission.
	 */
	public function submit_form() {
		if ( ! check_ajax_referer( 'dsf_forms_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid submission nonce.', 'designstudio-flow' ) ), 403 );
		}

		$form_id = isset( $_POST['dsf_form_id'] ) ? intval( $_POST['dsf_form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form ID.', 'designstudio-flow' ) ), 400 );
		}

		$form = get_post( $form_id );
		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'designstudio-flow' ) ), 404 );
		}

		if ( 'publish' !== $form->post_status && ! current_user_can( 'edit_post', $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Form is not available.', 'designstudio-flow' ) ), 403 );
		}

		$form_nonce = isset( $_POST['dsf_form_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_form_nonce'] ) ) : '';
		if ( empty( $form_nonce ) || ! wp_verify_nonce( $form_nonce, 'dsf_form_submit_' . $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form nonce.', 'designstudio-flow' ) ), 403 );
		}

		$recaptcha_token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
		$recaptcha_action = isset( $_POST['recaptcha_action'] ) ? sanitize_key( wp_unslash( $_POST['recaptcha_action'] ) ) : 'dsf_form_submit';
		$recaptcha_result = $this->verify_recaptcha_token( $recaptcha_token, $recaptcha_action );
		if ( is_wp_error( $recaptcha_result ) ) {
			wp_send_json_error( array( 'message' => $recaptcha_result->get_error_message() ), 403 );
		}

		$submission = $this->sanitize_submission_payload( $_POST );
		$settings   = $this->get_form_settings( $form_id );

		/**
		 * Fires after a DSF form passes validation and reCAPTCHA checks.
		 *
		 * @param int   $form_id    Form post ID.
		 * @param array $submission Sanitized payload.
		 */
		do_action( 'dsf_form_submitted', $form_id, $submission );

		$this->increment_form_entries_count( $form_id );
		$this->maybe_send_form_notifications( $form, $settings, $submission );

		$message = isset( $settings['confirmationMessage'] ) ? $settings['confirmationMessage'] : '';
		if ( '' === $message ) {
			$message = isset( $settings['successMessage'] ) ? $settings['successMessage'] : __( 'Thanks! Your form has been submitted.', 'designstudio-flow' );
		}

		$redirect_url = '';
		if ( isset( $settings['confirmationType'] ) && 'redirect_url' === $settings['confirmationType'] ) {
			$candidate = isset( $settings['redirectUrl'] ) ? esc_url_raw( $settings['redirectUrl'] ) : '';
			if ( '' !== $candidate && wp_http_validate_url( $candidate ) ) {
				$redirect_url = $candidate;
			}
		}

		wp_send_json_success(
			array(
				'message'     => $message,
				'redirectUrl' => $redirect_url,
			)
		);
	}

	/**
	 * Register frontend form assets.
	 */
	public function register_frontend_assets() {
		wp_register_style(
			'dsf-forms',
			DSF_PLUGIN_URL . 'assets/css/forms.css',
			array(),
			$this->get_asset_version( 'assets/css/forms.css' )
		);
		wp_register_script(
			'dsf-forms',
			DSF_PLUGIN_URL . 'assets/js/forms.js',
			array(),
			$this->get_asset_version( 'assets/js/forms.js' ),
			true
		);
	}

	/**
	 * Enqueue form assets for singular content that contains the shortcode.
	 */
	public function maybe_enqueue_shortcode_assets() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( ! has_shortcode( $post->post_content, 'dsform' ) ) {
			return;
		}

		$this->enqueue_frontend_assets();
	}

	/**
	 * Shortcode callback: [dsform id='1'].
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'dsform'
		);

		$form_id = intval( $atts['id'] );
		if ( ! $form_id ) {
			return '';
		}

		$form = get_post( $form_id );
		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			return '';
		}

		if ( 'publish' !== $form->post_status && ! current_user_can( 'edit_post', $form_id ) ) {
			return '';
		}

		$rows     = $this->get_form_rows( $form_id );
		$settings = $this->get_form_settings( $form_id );

		if ( empty( $rows ) ) {
			return '';
		}

		$this->enqueue_frontend_assets();

		return $this->render_form_markup( $form_id, $rows, $settings );
	}

	/**
	 * Enqueue frontend form assets.
	 */
	private function enqueue_frontend_assets() {
		if ( ! wp_style_is( 'dsf-forms', 'registered' ) ) {
			$this->register_frontend_assets();
		}

		$recaptcha = $this->get_recaptcha_settings();
		if ( $recaptcha['enabled'] && ! empty( $recaptcha['siteKey'] ) ) {
			wp_enqueue_script(
				'dsf-recaptcha-v3',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha['siteKey'] ),
				array(),
				null,
				true
			);
		}

		wp_enqueue_style( 'dsf-forms' );
		wp_enqueue_script( 'dsf-forms' );

		wp_localize_script(
			'dsf-forms',
			'dsfFormsFrontendData',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'dsf_forms_frontend_nonce' ),
				'recaptcha'  => $recaptcha,
			)
		);
	}

	/**
	 * Render complete frontend markup for a form.
	 */
	private function render_form_markup( $form_id, $rows, $settings ) {
		$pages = $this->split_rows_into_pages( $rows );
		if ( empty( $pages ) ) {
			return '';
		}

		$submit_label  = $settings['submitLabel'] ?? __( 'Submit', 'designstudio-flow' );
		$next_label    = $settings['nextLabel'] ?? __( 'Next', 'designstudio-flow' );
		$previous_label = $settings['previousLabel'] ?? __( 'Previous', 'designstudio-flow' );
		$success_message = $settings['confirmationMessage'] ?? '';
		if ( '' === $success_message ) {
			$success_message = $settings['successMessage'] ?? __( 'Thanks! Your form has been submitted.', 'designstudio-flow' );
		}

		$total_pages = count( $pages );
		$output      = '';
		$output     .= '<div class="dsf-form-wrap" data-dsf-form-id="' . intval( $form_id ) . '">';
		$output     .= '<form class="dsf-form" method="post" data-dsf-multipage="' . intval( $total_pages > 1 ) . '" data-dsf-success-message="' . esc_attr( $success_message ) . '" novalidate>';

		foreach ( $pages as $page_index => $page ) {
			$is_active  = 0 === $page_index;
			$page_class = 'dsf-form-page' . ( $is_active ? ' is-active' : '' );
			$next_transition = $page['nextTransition'] ?? 'slide-left';

			$output .= '<div class="' . esc_attr( $page_class ) . '" data-dsf-page-index="' . intval( $page_index ) . '" data-dsf-next-transition="' . esc_attr( $next_transition ) . '"' . ( $is_active ? '' : ' hidden' ) . '>';

			foreach ( $page['rows'] as $row ) {
				$fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
				if ( empty( $fields ) ) {
					continue;
				}

				if ( 1 === count( $fields ) && 'hidden' === ( $fields[0]['type'] ?? '' ) ) {
					$output .= $this->render_field_markup( $fields[0], $form_id, $page_index, 0 );
					continue;
				}

				$columns = count( $fields );
				if ( 1 === $columns ) {
					$single_field = $fields[0];
					$has_half     = ( isset( $single_field['width'] ) && 'half' === $single_field['width'] );
					$is_forced_full = in_array( $single_field['type'] ?? '', array( 'hidden', 'page_break' ), true );
					if ( $has_half && ! $is_forced_full ) {
						$columns = 2;
					}
				}
				$output .= '<div class="dsf-form-row dsf-form-row--cols-' . intval( $columns ) . '">';
				foreach ( $fields as $field_index => $field ) {
					$output .= $this->render_field_markup( $field, $form_id, $page_index, $field_index );
				}
				$output .= '</div>';
			}

			$output .= '<div class="dsf-form-nav">';
			if ( $page_index > 0 ) {
				$output .= '<button type="button" class="dsf-form-nav__btn dsf-form-nav__btn--secondary" data-dsf-nav="prev">' . esc_html( $previous_label ) . '</button>';
			}

			if ( $page_index < ( $total_pages - 1 ) ) {
				$output .= '<button type="button" class="dsf-form-nav__btn dsf-form-nav__btn--primary" data-dsf-nav="next">' . esc_html( $next_label ) . '</button>';
			} else {
				$output .= '<button type="submit" class="dsf-form-nav__btn dsf-form-nav__btn--primary">' . esc_html( $submit_label ) . '</button>';
			}

			$output .= '</div>';
			$output .= '</div>';
		}

		$output .= '<div class="dsf-form-message" aria-live="polite"></div>';
		$recaptcha = $this->get_recaptcha_settings();
		if ( $recaptcha['enabled'] ) {
			$output .= '<p class="dsf-form-recaptcha-note">';
			$output .= esc_html__( 'This site is protected by reCAPTCHA and the Google', 'designstudio-flow' ) . ' ';
			$output .= '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'designstudio-flow' ) . '</a> ';
			$output .= esc_html__( 'and', 'designstudio-flow' ) . ' ';
			$output .= '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms of Service', 'designstudio-flow' ) . '</a> ';
			$output .= esc_html__( 'apply.', 'designstudio-flow' );
			$output .= '</p>';
		}
		$output .= '<input type="hidden" name="dsf_form_id" value="' . intval( $form_id ) . '">';
		$output .= wp_nonce_field( 'dsf_form_submit_' . $form_id, 'dsf_form_nonce', true, false );
		$output .= '</form>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Split rows into pages using page break fields.
	 */
	private function split_rows_into_pages( $rows ) {
		$pages = array(
			array(
				'rows'           => array(),
				'nextTransition' => 'slide-left',
			),
		);

		$current_page = 0;
		foreach ( $rows as $row ) {
			$fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			$field  = isset( $fields[0] ) && is_array( $fields[0] ) ? $fields[0] : null;

			if ( 1 === count( $fields ) && $field && 'page_break' === ( $field['type'] ?? '' ) ) {
				$pages[ $current_page ]['nextTransition'] = $field['pageBreakAnimation'] ?? 'slide-left';
				$pages[] = array(
					'rows'           => array(),
					'nextTransition' => 'slide-left',
				);
				++$current_page;
				continue;
			}

			$pages[ $current_page ]['rows'][] = $row;
		}

		$last_index = count( $pages ) - 1;
		if ( $last_index > 0 && empty( $pages[ $last_index ]['rows'] ) ) {
			array_pop( $pages );
		}

		return $pages;
	}

	/**
	 * Render one form field.
	 */
	private function render_field_markup( $field, $form_id, $page_index, $field_index ) {
		$type = $field['type'] ?? 'single_line_text';
		if ( 'hidden' === $type ) {
			$name  = $field['name'] ?? 'hidden_field';
			$value = $field['defaultValue'] ?? '';
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}

		$field_key  = sanitize_title( $field['id'] ?? 'field' );
		if ( '' === $field_key ) {
			$field_key = 'field';
		}
		$field_id   = 'dsf-form-' . intval( $form_id ) . '-' . intval( $page_index ) . '-' . intval( $field_index ) . '-' . $field_key;
		$label      = $field['label'] ?? $this->get_default_field_label( $type );
		$name       = $field['name'] ?? 'field_' . intval( $field_index + 1 );
		$required   = ! empty( $field['required'] );
		$placeholder = $field['placeholder'] ?? '';
		$required_attr = $required ? ' required' : '';
		$required_mark = $required ? ' <span class="dsf-required" aria-hidden="true">*</span>' : '';
		$options    = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$html_value = $field['html'] ?? '';
		$default_value = $field['defaultValue'] ?? '';
		$help_text = $field['helpText'] ?? '';

		$group_required_attr = '';
		if ( $required && in_array( $type, array( 'checkboxes', 'radio_buttons' ), true ) ) {
			$group_required_attr = ' data-required-group="1"';
		}

		$output  = '<div class="dsf-form-field dsf-form-field--' . esc_attr( $type ) . '" data-field-type="' . esc_attr( $type ) . '"' . $group_required_attr . '>';

		if ( 'html' === $type ) {
			$output .= '<div class="dsf-form-html">' . wp_kses_post( $html_value ) . '</div>';
			$output .= '</div>';
			return $output;
		}

		$output .= '<label class="dsf-form-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . $required_mark . '</label>';

		switch ( $type ) {
			case 'paragraph_text':
				$output .= '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . '></textarea>';
				break;
			case 'checkboxes':
				$output .= '<div class="dsf-form-options">';
				foreach ( $options as $index => $option ) {
					$option_id = $field_id . '-cb-' . intval( $index );
					$output .= '<label class="dsf-form-option" for="' . esc_attr( $option_id ) . '">';
					$output .= '<input type="checkbox" id="' . esc_attr( $option_id ) . '" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $option ) . '">';
					$output .= '<span>' . esc_html( $option ) . '</span>';
					$output .= '</label>';
				}
				$output .= '</div>';
				break;
			case 'radio_buttons':
				$output .= '<div class="dsf-form-options">';
				foreach ( $options as $index => $option ) {
					$option_id = $field_id . '-rb-' . intval( $index );
					$output .= '<label class="dsf-form-option" for="' . esc_attr( $option_id ) . '">';
					$output .= '<input type="radio" id="' . esc_attr( $option_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $option ) . '">';
					$output .= '<span>' . esc_html( $option ) . '</span>';
					$output .= '</label>';
				}
				$output .= '</div>';
				break;
			case 'drop_down':
				$output .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"' . $required_attr . '>';
				$output .= '<option value="">' . esc_html__( 'Select an option', 'designstudio-flow' ) . '</option>';
				foreach ( $options as $option ) {
					$output .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				$output .= '</select>';
				break;
			case 'number':
				$output .= '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
			case 'phone':
				$output .= '<input type="tel" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
			case 'date':
				$output .= '<input type="date" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
			case 'email':
				$output .= '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
			case 'website':
				$output .= '<input type="url" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
			case 'file_upload':
				$output .= '<input type="file" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"' . $required_attr . '>';
				break;
			case 'single_line_text':
			default:
				$output .= '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default_value ) . '"' . $required_attr . '>';
				break;
		}

		if ( '' !== $help_text ) {
			$output .= '<p class="dsf-form-help-text">' . esc_html( $help_text ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Parse and sanitize form rows from post meta.
	 */
	private function get_form_rows( $form_id ) {
		if ( ! $form_id ) {
			return array();
		}

		$rows_meta = get_post_meta( $form_id, '_dsf_form_rows', true );
		if ( is_array( $rows_meta ) ) {
			return $this->sanitize_form_rows( $rows_meta );
		}

		$decoded = $rows_meta ? json_decode( $rows_meta, true ) : array();
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $this->sanitize_form_rows( $decoded );
	}

	/**
	 * Parse and sanitize form settings from post meta.
	 */
	private function get_form_settings( $form_id ) {
		$defaults = $this->get_default_form_settings();
		if ( ! $form_id ) {
			return $defaults;
		}

		$settings_meta = get_post_meta( $form_id, '_dsf_form_settings', true );
		if ( ! is_array( $settings_meta ) ) {
			$settings_meta = $settings_meta ? json_decode( $settings_meta, true ) : array();
		}

		if ( ! is_array( $settings_meta ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $this->sanitize_form_settings( $settings_meta ) );
	}

	/**
	 * Default form settings.
	 */
	private function get_default_form_settings() {
		return array(
			'submitLabel'             => __( 'Submit', 'designstudio-flow' ),
			'nextLabel'               => __( 'Next', 'designstudio-flow' ),
			'previousLabel'           => __( 'Previous', 'designstudio-flow' ),
			'successMessage'          => __( 'Thanks! Your form has been submitted.', 'designstudio-flow' ),
			'sendAdminNotifications'  => true,
			'adminEmails'             => array(),
			'notificationSubject'     => __( 'New form submission - {form_title}', 'designstudio-flow' ),
			'notificationIntro'       => '',
			'sendSubmitterCopy'       => false,
			'confirmationType'        => 'message',
			'confirmationMessage'     => __( 'Thanks! Your form was submitted successfully.', 'designstudio-flow' ),
			'redirectUrl'             => '',
			'connections'             => array(),
		);
	}

	/**
	 * Sanitize form settings payload.
	 */
	private function sanitize_form_settings( $settings ) {
		$defaults = $this->get_default_form_settings();

		$sanitized = array(
			'submitLabel'            => isset( $settings['submitLabel'] ) ? sanitize_text_field( $settings['submitLabel'] ) : $defaults['submitLabel'],
			'nextLabel'              => isset( $settings['nextLabel'] ) ? sanitize_text_field( $settings['nextLabel'] ) : $defaults['nextLabel'],
			'previousLabel'          => isset( $settings['previousLabel'] ) ? sanitize_text_field( $settings['previousLabel'] ) : $defaults['previousLabel'],
			'successMessage'         => isset( $settings['successMessage'] ) ? sanitize_text_field( $settings['successMessage'] ) : $defaults['successMessage'],
			'sendAdminNotifications' => ! empty( $settings['sendAdminNotifications'] ),
			'adminEmails'            => $this->sanitize_email_list( $settings['adminEmails'] ?? array() ),
			'notificationSubject'    => isset( $settings['notificationSubject'] ) ? sanitize_text_field( $settings['notificationSubject'] ) : $defaults['notificationSubject'],
			'notificationIntro'      => isset( $settings['notificationIntro'] ) ? sanitize_textarea_field( $settings['notificationIntro'] ) : $defaults['notificationIntro'],
			'sendSubmitterCopy'      => ! empty( $settings['sendSubmitterCopy'] ),
			'confirmationType'       => ( isset( $settings['confirmationType'] ) && 'redirect_url' === sanitize_key( $settings['confirmationType'] ) ) ? 'redirect_url' : 'message',
			'confirmationMessage'    => isset( $settings['confirmationMessage'] )
				? sanitize_textarea_field( $settings['confirmationMessage'] )
				: ( isset( $settings['successMessage'] ) ? sanitize_textarea_field( $settings['successMessage'] ) : $defaults['confirmationMessage'] ),
			'redirectUrl'            => isset( $settings['redirectUrl'] ) ? esc_url_raw( $settings['redirectUrl'] ) : '',
			'connections'            => $this->sanitize_connections( $settings['connections'] ?? array() ),
		);

		foreach ( array( 'submitLabel', 'nextLabel', 'previousLabel', 'successMessage', 'notificationSubject', 'confirmationMessage' ) as $key ) {
			$value = $sanitized[ $key ];
			if ( '' === $value ) {
				$sanitized[ $key ] = $defaults[ $key ];
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize notification email list.
	 */
	private function sanitize_email_list( $emails ) {
		if ( ! is_array( $emails ) ) {
			return array();
		}

		$clean = array();
		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}

			$clean[] = $email;
			if ( count( $clean ) >= 25 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize connection definitions.
	 */
	private function sanitize_connections( $connections ) {
		if ( ! is_array( $connections ) ) {
			return array();
		}

		$allowed_types = array( 'webhook', 'salesforce' );
		$sanitized     = array();

		foreach ( $connections as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$type = isset( $connection['type'] ) ? sanitize_key( $connection['type'] ) : 'webhook';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'webhook';
			}

			$timeout = isset( $connection['timeout'] ) ? intval( $connection['timeout'] ) : 8;
			$timeout = min( 120, max( 1, $timeout ) );

			$id = isset( $connection['id'] ) ? sanitize_text_field( $connection['id'] ) : wp_generate_uuid4();
			if ( '' === $id ) {
				$id = wp_generate_uuid4();
			}

			$sanitized[] = array(
				'id'          => $id,
				'enabled'     => ! empty( $connection['enabled'] ),
				'type'        => $type,
				'label'       => isset( $connection['label'] ) ? sanitize_text_field( $connection['label'] ) : '',
				'endpointUrl' => isset( $connection['endpointUrl'] ) ? esc_url_raw( $connection['endpointUrl'] ) : '',
				'secret'      => isset( $connection['secret'] ) ? sanitize_text_field( $connection['secret'] ) : '',
				'timeout'     => $timeout,
			);

			if ( count( $sanitized ) >= 20 ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Get total entries count for a form.
	 */
	private function get_form_entries_count( $form_id ) {
		if ( ! $form_id ) {
			return 0;
		}

		return max( 0, intval( get_post_meta( $form_id, '_dsf_form_entries_count', true ) ) );
	}

	/**
	 * Increment entries count after a successful submission.
	 */
	private function increment_form_entries_count( $form_id ) {
		if ( ! $form_id ) {
			return;
		}

		$count = $this->get_form_entries_count( $form_id ) + 1;
		update_post_meta( $form_id, '_dsf_form_entries_count', $count );
	}

	/**
	 * Send configured notification emails for a form submission.
	 */
	private function maybe_send_form_notifications( $form, $settings, $submission ) {
		$send_admin = ! empty( $settings['sendAdminNotifications'] );
		$send_copy  = ! empty( $settings['sendSubmitterCopy'] );
		if ( ! $send_admin && ! $send_copy ) {
			return;
		}

		$form_title = ( $form instanceof WP_Post ) ? get_the_title( $form ) : __( 'Form', 'designstudio-flow' );
		$subject_tpl = isset( $settings['notificationSubject'] ) ? (string) $settings['notificationSubject'] : '';
		if ( '' === $subject_tpl ) {
			$subject_tpl = __( 'New form submission - {form_title}', 'designstudio-flow' );
		}
		$subject = str_replace( '{form_title}', $form_title, $subject_tpl );

		$message_lines = array();
		$intro = isset( $settings['notificationIntro'] ) ? trim( (string) $settings['notificationIntro'] ) : '';
		if ( '' !== $intro ) {
			$message_lines[] = $intro;
			$message_lines[] = '';
		}

		$message_lines[] = sprintf(
			/* translators: %s: form title */
			__( 'Form: %s', 'designstudio-flow' ),
			$form_title
		);
		$message_lines[] = sprintf(
			/* translators: %s: submission datetime */
			__( 'Submitted: %s', 'designstudio-flow' ),
			current_time( 'mysql' )
		);
		$message_lines[] = '';

		foreach ( $submission as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', sanitize_text_field( (string) $key ) ) );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			}
			$message_lines[] = $label . ': ' . sanitize_text_field( (string) $value );
		}

		$message = implode( "\n", $message_lines );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( $send_admin ) {
			$recipients = isset( $settings['adminEmails'] ) ? $this->sanitize_email_list( $settings['adminEmails'] ) : array();
			if ( empty( $recipients ) ) {
				$default_admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
				if ( '' !== $default_admin && is_email( $default_admin ) ) {
					$recipients[] = $default_admin;
				}
			}

			if ( ! empty( $recipients ) ) {
				wp_mail( $recipients, $subject, $message, $headers );
			}
		}

		if ( $send_copy ) {
			$submitter_email = $this->find_submitter_email( $submission );
			if ( '' !== $submitter_email ) {
				$copy_subject = sprintf(
					/* translators: %s: form title */
					__( 'Copy of your submission: %s', 'designstudio-flow' ),
					$form_title
				);
				wp_mail( $submitter_email, $copy_subject, $message, $headers );
			}
		}
	}

	/**
	 * Find first valid email in submission payload.
	 */
	private function find_submitter_email( $submission ) {
		if ( ! is_array( $submission ) ) {
			return '';
		}

		foreach ( $submission as $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$email = sanitize_email( (string) $item );
					if ( '' !== $email && is_email( $email ) ) {
						return $email;
					}
				}
				continue;
			}

			$email = sanitize_email( (string) $value );
			if ( '' !== $email && is_email( $email ) ) {
				return $email;
			}
		}

		return '';
	}

	/**
	 * Sanitize form rows payload.
	 */
	private function sanitize_form_rows( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$sanitized_rows = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row_fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			$clean_fields = array();

			foreach ( $row_fields as $field ) {
				$clean_field = $this->sanitize_form_field( $field );
				if ( ! $clean_field ) {
					continue;
				}
				$clean_fields[] = $clean_field;
				if ( count( $clean_fields ) >= 2 ) {
					break;
				}
			}

			if ( empty( $clean_fields ) ) {
				continue;
			}

			// Page break rows must contain only one field.
			if ( count( $clean_fields ) > 1 ) {
				foreach ( $clean_fields as $candidate ) {
					if ( 'page_break' === $candidate['type'] ) {
						$clean_fields = array( $candidate );
						break;
					}
				}
			}

			if ( 1 === count( $clean_fields ) ) {
				$single_type = $clean_fields[0]['type'] ?? '';
				if ( in_array( $single_type, array( 'hidden', 'page_break' ), true ) ) {
					$clean_fields[0]['width'] = 'full';
				} else {
					$clean_fields[0]['width'] = ( isset( $clean_fields[0]['width'] ) && 'half' === $clean_fields[0]['width'] ) ? 'half' : 'full';
				}
			} else {
				$clean_fields[0]['width'] = 'half';
				$clean_fields[1]['width'] = 'half';
			}

			$row_id = isset( $row['id'] ) ? sanitize_text_field( $row['id'] ) : wp_generate_uuid4();
			if ( '' === $row_id ) {
				$row_id = wp_generate_uuid4();
			}

			$sanitized_rows[] = array(
				'id'     => $row_id,
				'fields' => $clean_fields,
			);
		}

		return $sanitized_rows;
	}

	/**
	 * Sanitize one field definition.
	 */
	private function sanitize_form_field( $field ) {
		if ( ! is_array( $field ) ) {
			return null;
		}

		$allowed_types = $this->get_allowed_field_types();
		$type          = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'single_line_text';
		if ( 'multiple_choice' === $type ) {
			$type = 'radio_buttons';
		}
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return null;
		}

		$field_id = isset( $field['id'] ) ? sanitize_text_field( $field['id'] ) : wp_generate_uuid4();
		if ( '' === $field_id ) {
			$field_id = wp_generate_uuid4();
		}

		$default_label = $this->get_default_field_label( $type );
		$label         = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : $default_label;
		if ( '' === $label && 'hidden' !== $type && 'page_break' !== $type ) {
			$label = $default_label;
		}

		$fallback_name = 'field_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( str_replace( '-', '_', $field_id ) ) );
		$name_input    = isset( $field['name'] ) ? sanitize_text_field( $field['name'] ) : $fallback_name;
		$name          = sanitize_title( $name_input );
		$name          = str_replace( '-', '_', $name );
		if ( '' === $name ) {
			$name = $fallback_name;
		}

		$width = isset( $field['width'] ) && 'half' === $field['width'] ? 'half' : 'full';
		if ( in_array( $type, array( 'hidden', 'page_break' ), true ) ) {
			$width = 'full';
		}

		$required = ! empty( $field['required'] );

		$options = array();
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $option ) {
				$option = sanitize_text_field( $option );
				if ( '' !== $option ) {
					$options[] = $option;
				}
				if ( count( $options ) >= 50 ) {
					break;
				}
			}
		}

		if ( empty( $options ) ) {
			$options = $this->get_default_options( $type );
		}

		$page_break_animation = isset( $field['pageBreakAnimation'] ) ? sanitize_key( $field['pageBreakAnimation'] ) : 'slide-left';
		if ( 'fade-in' === $page_break_animation ) {
			$page_break_animation = 'fade';
		}
		if ( ! in_array( $page_break_animation, array( 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom', 'fade', 'none' ), true ) ) {
			$page_break_animation = 'slide-left';
		}

		return array(
			'id'                 => $field_id,
			'type'               => $type,
			'label'              => $label,
			'name'               => $name,
			'width'              => $width,
			'required'           => $required,
			'placeholder'        => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
			'defaultValue'       => isset( $field['defaultValue'] ) ? sanitize_text_field( $field['defaultValue'] ) : '',
			'helpText'           => isset( $field['helpText'] ) ? sanitize_text_field( $field['helpText'] ) : '',
			'options'            => $options,
			'html'               => isset( $field['html'] ) ? wp_kses_post( $field['html'] ) : '',
			'pageBreakAnimation' => $page_break_animation,
		);
	}

	/**
	 * Allowed form field types.
	 */
	private function get_allowed_field_types() {
		return array(
			'single_line_text',
			'paragraph_text',
			'checkboxes',
			'radio_buttons',
			'drop_down',
			'number',
			'phone',
			'date',
			'email',
			'website',
			'file_upload',
			'html',
			'hidden',
			'page_break',
		);
	}

	/**
	 * Default label by field type.
	 */
	private function get_default_field_label( $type ) {
		$labels = array(
			'single_line_text' => __( 'Single Line Text', 'designstudio-flow' ),
			'paragraph_text'   => __( 'Paragraph Text', 'designstudio-flow' ),
			'checkboxes'       => __( 'Checkboxes', 'designstudio-flow' ),
			'radio_buttons'    => __( 'Radio Buttons', 'designstudio-flow' ),
			'drop_down'        => __( 'Drop Down', 'designstudio-flow' ),
			'number'           => __( 'Number', 'designstudio-flow' ),
			'phone'            => __( 'Phone', 'designstudio-flow' ),
			'date'             => __( 'Date', 'designstudio-flow' ),
			'email'            => __( 'Email', 'designstudio-flow' ),
			'website'          => __( 'Website', 'designstudio-flow' ),
			'file_upload'      => __( 'File Upload', 'designstudio-flow' ),
			'html'             => __( 'HTML', 'designstudio-flow' ),
			'hidden'           => __( 'Hidden', 'designstudio-flow' ),
			'page_break'       => __( 'Page Break', 'designstudio-flow' ),
		);

		return $labels[ $type ] ?? __( 'Field', 'designstudio-flow' );
	}

	/**
	 * Default options for option-based fields.
	 */
	private function get_default_options( $type ) {
		if ( in_array( $type, array( 'checkboxes', 'radio_buttons', 'drop_down' ), true ) ) {
			return array(
				__( 'Option 1', 'designstudio-flow' ),
				__( 'Option 2', 'designstudio-flow' ),
			);
		}

		return array();
	}

	/**
	 * Get frontend reCAPTCHA settings.
	 */
	private function get_recaptcha_settings() {
		$enabled = (bool) get_option( 'dsf_recaptcha_enabled', false );
		$site_key = trim( (string) get_option( 'dsf_recaptcha_site_key', '' ) );
		$secret_key = trim( (string) get_option( 'dsf_recaptcha_secret_key', '' ) );
		$threshold = floatval( get_option( 'dsf_recaptcha_threshold', 0.5 ) );
		$threshold = min( 1, max( 0, $threshold ) );

		$is_ready = $enabled && '' !== $site_key && '' !== $secret_key;

		return array(
			'enabled'   => $is_ready,
			'siteKey'   => $site_key,
			'threshold' => $threshold,
			'action'    => 'dsf_form_submit',
		);
	}

	/**
	 * Verify a reCAPTCHA token with Google.
	 */
	private function verify_recaptcha_token( $token, $expected_action = 'dsf_form_submit' ) {
		$settings = $this->get_recaptcha_settings();
		if ( ! $settings['enabled'] ) {
			return true;
		}

		if ( empty( $token ) ) {
			return new WP_Error( 'dsf_recaptcha_missing', __( 'Please complete the reCAPTCHA check.', 'designstudio-flow' ) );
		}

		$secret_key = trim( (string) get_option( 'dsf_recaptcha_secret_key', '' ) );
		if ( '' === $secret_key ) {
			return new WP_Error( 'dsf_recaptcha_missing_secret', __( 'reCAPTCHA is not configured correctly.', 'designstudio-flow' ) );
		}

		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$request_body = array(
			'secret'   => $secret_key,
			'response' => $token,
		);
		if ( '' !== $remote_ip ) {
			$request_body['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => $request_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'dsf_recaptcha_request_failed', __( 'Unable to verify reCAPTCHA right now. Please try again.', 'designstudio-flow' ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error( 'dsf_recaptcha_bad_status', __( 'reCAPTCHA verification failed. Please try again.', 'designstudio-flow' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return new WP_Error( 'dsf_recaptcha_unsuccessful', __( 'reCAPTCHA verification was unsuccessful.', 'designstudio-flow' ) );
		}

		if ( isset( $data['action'] ) && $expected_action && $expected_action !== $data['action'] ) {
			return new WP_Error( 'dsf_recaptcha_action_mismatch', __( 'Security check failed (action mismatch).', 'designstudio-flow' ) );
		}

		$score = isset( $data['score'] ) ? floatval( $data['score'] ) : 0;
		if ( $score < floatval( $settings['threshold'] ) ) {
			return new WP_Error( 'dsf_recaptcha_low_score', __( 'reCAPTCHA score was too low. Please try again.', 'designstudio-flow' ) );
		}

		return true;
	}

	/**
	 * Sanitize frontend submission payload.
	 */
	private function sanitize_submission_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$reserved_keys = array(
			'action',
			'nonce',
			'dsf_form_id',
			'dsf_form_nonce',
			'recaptcha_token',
			'recaptcha_action',
		);

		$clean = array();
		foreach ( $payload as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key || in_array( $key, $reserved_keys, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map(
					static function ( $item ) {
						return sanitize_text_field( wp_unslash( $item ) );
					},
					$value
				);
				continue;
			}

			$clean[ $key ] = sanitize_text_field( wp_unslash( $value ) );
		}

		return $clean;
	}

	/**
	 * Get version string for cache busting based on file mtime.
	 */
	private function get_asset_version( $relative_path ) {
		$relative_path = ltrim( $relative_path, '/' );
		$path          = DSF_PLUGIN_DIR . $relative_path;
		if ( file_exists( $path ) ) {
			return (string) filemtime( $path );
		}
		return DSF_VERSION;
	}
}
