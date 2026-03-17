<?php
/**
 * Admin functionality for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ), 80 );
		add_action( 'admin_init', array( $this, 'handle_disable_flow' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_layout_admin_list' ) );
		add_action( 'current_screen', array( $this, 'set_layout_screen_labels' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'parent_file', array( $this, 'set_flow_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'set_flow_submenu' ) );
		add_filter( 'plugin_action_links_' . DSF_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
		add_filter( 'page_row_actions', array( $this, 'add_flow_edit_link' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'add_template_edit_link' ), 10, 2 );
		add_filter( 'manage_dsf_layout_posts_columns', array( $this, 'add_layout_columns' ) );
		add_action( 'manage_dsf_layout_posts_custom_column', array( $this, 'render_layout_column' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'add_flow_editor_button' ) );
		add_filter( 'admin_body_class', array( $this, 'add_flow_body_class' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'DesignStudio Flow', 'designstudio-flow' ),
			__( 'DesignStudio Flow', 'designstudio-flow' ),
			'edit_pages',
			'designstudio-flow',
			array( $this, 'render_dashboard_page' ),
			'dashicons-layout',
			30
		);

		// Dashboard submenu
		add_submenu_page(
			'designstudio-flow',
			__( 'Dashboard', 'designstudio-flow' ),
			__( 'Dashboard', 'designstudio-flow' ),
			'edit_pages',
			'designstudio-flow',
			array( $this, 'render_dashboard_page' )
		);

		// Pages.
		add_submenu_page(
			'designstudio-flow',
			__( 'Pages', 'designstudio-flow' ),
			__( 'Pages', 'designstudio-flow' ),
			'edit_pages',
			'edit.php?post_type=dsf_page'
		);

		// Add New Page.
		add_submenu_page(
			'designstudio-flow',
			__( 'Add New Page', 'designstudio-flow' ),
			__( 'Add New Page', 'designstudio-flow' ),
			'edit_pages',
			'admin.php?page=dsf-editor&post_type=dsf_page'
		);

		// Headers.
		add_submenu_page(
			'designstudio-flow',
			__( 'DesignStudio Flow Headers', 'designstudio-flow' ),
			__( 'Headers', 'designstudio-flow' ),
			'edit_pages',
			'edit.php?post_type=dsf_layout&dsf_layout_type=header'
		);

		// Add New Header.
		add_submenu_page(
			'designstudio-flow',
			__( 'Add New DSFlow Header', 'designstudio-flow' ),
			__( 'Add New DSFlow Header', 'designstudio-flow' ),
			'edit_pages',
			'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=header'
		);

		// Footers.
		add_submenu_page(
			'designstudio-flow',
			__( 'DesignStudio Flow Footers', 'designstudio-flow' ),
			__( 'Footers', 'designstudio-flow' ),
			'edit_pages',
			'edit.php?post_type=dsf_layout&dsf_layout_type=footer'
		);

		// Add New Footer.
		add_submenu_page(
			'designstudio-flow',
			__( 'Add New DSFlow Footer', 'designstudio-flow' ),
			__( 'Add New DSFlow Footer', 'designstudio-flow' ),
			'edit_pages',
			'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=footer'
		);
	}

	/**
	 * Add settings submenu after feature-specific menus.
	 */
	public function add_settings_submenu() {
		add_submenu_page(
			'designstudio-flow',
			__( 'Settings', 'designstudio-flow' ),
			__( 'Settings', 'designstudio-flow' ),
			'manage_options',
			'dsf-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only on our pages or page editor screens
		$is_flow_admin  = ( false !== strpos( $hook, 'designstudio-flow' ) ) || ( false !== strpos( $hook, 'dsf_page' ) );
		$is_page_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$screen         = $is_page_editor ? get_current_screen() : null;
		$post_type      = $screen ? $screen->post_type : '';

		if ( ! $is_flow_admin && ! ( $is_page_editor && 'page' === $post_type ) ) {
			return;
		}

		wp_enqueue_style(
			'dsf-admin',
			DSF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DSF_VERSION
		);
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		$pages = get_posts(
			array(
				'post_type'      => 'dsf_page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$headers = get_posts(
			array(
				'post_type'      => 'dsf_layout',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_dsf_layout_type',
						'value'   => 'header',
						'compare' => '=',
					),
					array(
						'key'     => '_dsf_layout_type',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$footers = get_posts(
			array(
				'post_type'      => 'dsf_layout',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => '_dsf_layout_type',
						'value'   => 'footer',
						'compare' => '=',
					),
				),
			)
		);
		$forms = get_posts(
			array(
				'post_type'      => 'dsf_form',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		include DSF_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	/**
	 * Filter the Layouts list to headers/footers based on menu route.
	 */
	public function filter_layout_admin_list( $query ) {
		if ( ! ( $query instanceof WP_Query ) || ! $query->is_main_query() || ! is_admin() ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'dsf_layout' !== $post_type ) {
			return;
		}

		$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$layout_type = $layout_type ? sanitize_key( $layout_type ) : '';
		if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
			return;
		}

		if ( 'header' === $layout_type ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_dsf_layout_type',
						'value'   => 'header',
						'compare' => '=',
					),
					array(
						'key'     => '_dsf_layout_type',
						'compare' => 'NOT EXISTS',
					),
				)
			);
			return;
		}

		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_dsf_layout_type',
					'value'   => 'footer',
					'compare' => '=',
				),
			)
		);
	}

	/**
	 * Adjust dsf_layout labels for Header/Footer screens.
	 */
	public function set_layout_screen_labels( $screen ) {
		if ( ! $screen || ! isset( $screen->post_type ) || 'dsf_layout' !== $screen->post_type ) {
			return;
		}

		$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$layout_type = $layout_type ? sanitize_key( $layout_type ) : '';

		if ( '' === $layout_type ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
			$post_id = $post_id ? intval( $post_id ) : 0;
			if ( $post_id > 0 ) {
				$stored_type = get_post_meta( $post_id, '_dsf_layout_type', true );
				$layout_type = $stored_type ? sanitize_key( $stored_type ) : '';
			}
		}

		if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
			$layout_type = 'header';
		}

		$labels = 'footer' === $layout_type
			? array(
				'name'               => __( 'DesignStudio Flow Footers', 'designstudio-flow' ),
				'singular_name'      => __( 'DesignStudio Flow Footer', 'designstudio-flow' ),
				'menu_name'          => __( 'DesignStudio Flow Footers', 'designstudio-flow' ),
				'name_admin_bar'     => __( 'DSFlow Footer', 'designstudio-flow' ),
				'add_new'            => __( 'Add New DSFlow Footer', 'designstudio-flow' ),
				'add_new_item'       => __( 'Add New DSFlow Footer', 'designstudio-flow' ),
				'new_item'           => __( 'New DSFlow Footer', 'designstudio-flow' ),
				'edit_item'          => __( 'Edit DSFlow Footer', 'designstudio-flow' ),
				'view_item'          => __( 'View DSFlow Footer', 'designstudio-flow' ),
				'all_items'          => __( 'All DesignStudio Flow Footers', 'designstudio-flow' ),
				'search_items'       => __( 'Search DesignStudio Flow Footers', 'designstudio-flow' ),
				'not_found'          => __( 'No DesignStudio Flow footers found.', 'designstudio-flow' ),
				'not_found_in_trash' => __( 'No DesignStudio Flow footers found in Trash.', 'designstudio-flow' ),
			)
			: array(
				'name'               => __( 'DesignStudio Flow Headers', 'designstudio-flow' ),
				'singular_name'      => __( 'DesignStudio Flow Header', 'designstudio-flow' ),
				'menu_name'          => __( 'DesignStudio Flow Headers', 'designstudio-flow' ),
				'name_admin_bar'     => __( 'DSFlow Header', 'designstudio-flow' ),
				'add_new'            => __( 'Add New DSFlow Header', 'designstudio-flow' ),
				'add_new_item'       => __( 'Add New DSFlow Header', 'designstudio-flow' ),
				'new_item'           => __( 'New DSFlow Header', 'designstudio-flow' ),
				'edit_item'          => __( 'Edit DSFlow Header', 'designstudio-flow' ),
				'view_item'          => __( 'View DSFlow Header', 'designstudio-flow' ),
				'all_items'          => __( 'All DesignStudio Flow Headers', 'designstudio-flow' ),
				'search_items'       => __( 'Search DesignStudio Flow Headers', 'designstudio-flow' ),
				'not_found'          => __( 'No DesignStudio Flow headers found.', 'designstudio-flow' ),
				'not_found_in_trash' => __( 'No DesignStudio Flow headers found in Trash.', 'designstudio-flow' ),
			);

		global $wp_post_types;
		if ( empty( $wp_post_types['dsf_layout'] ) || empty( $wp_post_types['dsf_layout']->labels ) ) {
			return;
		}

		$post_type_labels = $wp_post_types['dsf_layout']->labels;
		foreach ( $labels as $key => $value ) {
			if ( isset( $post_type_labels->$key ) ) {
				$post_type_labels->$key = $value;
			}
		}
	}

	/**
	 * Keep DSF parent menu highlighted on Header/Footer routes.
	 */
	public function set_flow_parent_menu( $parent_file ) {
		if ( $this->get_layout_submenu_slug() ) {
			return 'designstudio-flow';
		}

		return $parent_file;
	}

	/**
	 * Keep Header/Footer submenu item selected on DSF layout screens.
	 */
	public function set_flow_submenu( $submenu_file ) {
		$layout_submenu = $this->get_layout_submenu_slug();
		if ( $layout_submenu ) {
			return $layout_submenu;
		}

		return $submenu_file;
	}

	/**
	 * Resolve current Header/Footer submenu slug from request context.
	 */
	private function get_layout_submenu_slug() {
		global $pagenow;

		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';
		$page      = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$page      = $page ? sanitize_key( $page ) : '';

		$is_layout_list   = ( 'edit.php' === $pagenow && 'dsf_layout' === $post_type );
		$is_layout_editor = ( 'admin.php' === $pagenow && 'dsf-editor' === $page && $this->is_layout_editor_context() );

		if ( ! $is_layout_list && ! $is_layout_editor ) {
			return '';
		}

		$layout_type = $this->get_requested_layout_type();
		if ( ! $layout_type ) {
			$layout_type = 'header';
		}

		return 'edit.php?post_type=dsf_layout&dsf_layout_type=' . $layout_type;
	}

	/**
	 * Detect whether current dsf-editor route is editing a layout.
	 */
	private function is_layout_editor_context() {
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';
		if ( 'dsf_layout' === $post_type ) {
			return true;
		}

		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;
		if ( $post_id > 0 ) {
			return 'dsf_layout' === get_post_type( $post_id );
		}

		return false;
	}

	/**
	 * Read layout type from query string or current layout post.
	 */
	private function get_requested_layout_type() {
		$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$layout_type = $layout_type ? sanitize_key( $layout_type ) : '';

		if ( '' === $layout_type ) {
			$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
			$post_id = $post_id ? intval( $post_id ) : 0;
			if ( $post_id > 0 ) {
				$stored_type = get_post_meta( $post_id, '_dsf_layout_type', true );
				$layout_type = $stored_type ? sanitize_key( $stored_type ) : '';
			}
		}

		if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
			return '';
		}

		return $layout_type;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		include DSF_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Add plugin action links
	 */
	public function add_plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=designstudio-flow' ) ) . '">' .
				__( 'Dashboard', 'designstudio-flow' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=dsf-settings' ) ) . '">' .
				__( 'Settings', 'designstudio-flow' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}

	/**
	 * Add "Edit with DesignStudio Flow" link to Pages list
	 */
	public function add_flow_edit_link( $actions, $post ) {
		if ( 'page' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		$url                 = admin_url( 'admin.php?page=dsf-editor&post_id=' . $post->ID );
		$actions['dsf_edit'] = '<a href="' . esc_url( $url ) . '">' . __( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</a>';

		return $actions;
	}

	/**
	 * Add "Edit with DesignStudio Flow" link to Header/Footer template list.
	 */
	public function add_template_edit_link( $actions, $post ) {
		if ( ! $post || 'dsf_layout' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_pages', $post->ID ) ) {
			return $actions;
		}

		$url                 = admin_url( 'admin.php?page=dsf-editor&post_id=' . $post->ID );
		$actions['dsf_edit'] = '<a href="' . esc_url( $url ) . '">' . __( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</a>';

		return $actions;
	}

	/**
	 * Add template type column to Header/Footer template list.
	 */
	public function add_layout_columns( $columns ) {
		$updated_columns = array();
		foreach ( $columns as $key => $label ) {
			$updated_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$updated_columns['dsf_layout_type'] = __( 'Template Type', 'designstudio-flow' );
			}
		}
		return $updated_columns;
	}

	/**
	 * Render template type column value.
	 */
	public function render_layout_column( $column, $post_id ) {
		if ( 'dsf_layout_type' !== $column ) {
			return;
		}

		$type  = get_post_meta( $post_id, '_dsf_layout_type', true );
		$type  = 'footer' === $type ? 'footer' : 'header';
		$label = 'footer' === $type ? __( 'Footer', 'designstudio-flow' ) : __( 'Header', 'designstudio-flow' );
		echo esc_html( $label );
	}

	/**
	 * Add "Edit with DesignStudio Flow" button on the page editor
	 */
	public function add_flow_editor_button( $post ) {
		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_pages', $post->ID ) ) {
			return;
		}

		$url        = admin_url( 'admin.php?page=dsf-editor&post_id=' . $post->ID );
		$enabled    = (bool) get_post_meta( $post->ID, '_dsf_enabled', true );
		$show_panel = $enabled || 'auto-draft' === $post->post_status;

		echo '<div class="dsf-edit-with-flow">';
		echo '<a class="button button-primary dsf-flow-button" href="' . esc_url( $url ) . '">';
		echo wp_kses( $this->get_flow_logo_svg( 'dsf-flow-button__logo', 'compact' ), $this->get_flow_logo_allowed_html() );
		echo '<span>' . esc_html__( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</span>';
		echo '</a>';
		echo '</div>';

		if ( $show_panel ) {
			echo '<div class="dsf-flow-editor-panel">';
			echo '<div class="dsf-flow-editor-panel__inner">';
			echo wp_kses( $this->get_flow_logo_svg( 'dsf-flow-editor-panel__logo' ), $this->get_flow_logo_allowed_html() );
			if ( $enabled ) {
				echo '<h2 class="dsf-flow-editor-panel__title">' . esc_html__( 'This page is edited with DesignStudio Flow', 'designstudio-flow' ) . '</h2>';
				echo '<p class="dsf-flow-editor-panel__text">' . esc_html__( 'The WordPress editor is disabled for this page. Use DesignStudio Flow to make changes.', 'designstudio-flow' ) . '</p>';
			} else {
				echo '<h2 class="dsf-flow-editor-panel__title">' . esc_html__( 'Build this page with DesignStudio Flow', 'designstudio-flow' ) . '</h2>';
				echo '<p class="dsf-flow-editor-panel__text">' . esc_html__( 'Launch the DesignStudio Flow editor to design this page using blocks and global theme settings.', 'designstudio-flow' ) . '</p>';
			}
			echo '<a class="button button-primary dsf-flow-button dsf-flow-button--lg" href="' . esc_url( $url ) . '">';
			echo wp_kses( $this->get_flow_logo_svg( 'dsf-flow-button__logo', 'compact' ), $this->get_flow_logo_allowed_html() );
			echo '<span>' . esc_html__( 'Edit with DesignStudio Flow', 'designstudio-flow' ) . '</span>';
			echo '</a>';

			if ( $enabled ) {
				$disable_url = wp_nonce_url(
					add_query_arg(
						array(
							'dsf_disable_flow' => 1,
							'post'             => $post->ID,
						),
						admin_url( 'post.php' )
					),
					'dsf_disable_flow_' . $post->ID
				);
				echo '<a class="dsf-flow-editor-panel__switch" href="' . esc_url( $disable_url ) . '">';
				echo esc_html__( 'Switch back to WordPress editor', 'designstudio-flow' );
				echo '</a>';
			}
			echo '</div></div>';
		}
	}

	/**
	 * Add body class when Flow controls the editor
	 */
	public function add_flow_body_class( $classes ) {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'page' !== $screen->post_type ) {
			return $classes;
		}

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;
		if ( $post_id && get_post_meta( $post_id, '_dsf_enabled', true ) ) {
			$classes .= ' dsf-flow-editor--locked';
		}

		return $classes;
	}

	/**
	 * Inline Flow logo SVG
	 */
	private function get_flow_logo_svg( $class_name = '', $variant = 'full' ) {
		$class_attr = $class_name ? ' class="' . esc_attr( $class_name ) . '"' : '';
		$logo_url   = DSF_PLUGIN_URL . 'assets/images/dsflow-logo.png';
		unset( $variant );

		return '<span' . $class_attr . ' aria-hidden="true">
			<img src="' . esc_url( $logo_url ) . '" alt="" loading="lazy" />
		</span>';
	}

	/**
	 * Allowed HTML for Flow logo SVG.
	 */
	private function get_flow_logo_allowed_html() {
		return array(
			'span' => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'img'  => array(
				'src'     => true,
				'alt'     => true,
				'loading' => true,
			),
		);
	}

	/**
	 * Allow switching back to the WordPress editor.
	 */
	public function handle_disable_flow() {
		$disable_flow = filter_input( INPUT_GET, 'dsf_disable_flow', FILTER_VALIDATE_INT );
		$post_id      = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$nonce        = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! $disable_flow || ! $post_id ) {
			return;
		}

		$post_id = intval( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_pages', $post_id ) ) {
			return;
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dsf_disable_flow_' . $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, '_dsf_enabled' );
		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		exit;
	}
}
