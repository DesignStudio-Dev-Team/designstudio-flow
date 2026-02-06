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
		add_action( 'admin_init', array( $this, 'handle_disable_flow' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'plugin_action_links_' . DSF_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
		add_filter( 'page_row_actions', array( $this, 'add_flow_edit_link' ), 10, 2 );
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
			__( 'DSF Pages', 'designstudio-flow' ),
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

		// All Flow Pages
		add_submenu_page(
			'designstudio-flow',
			__( 'All Pages', 'designstudio-flow' ),
			__( 'All Pages', 'designstudio-flow' ),
			'edit_pages',
			'edit.php?post_type=dsf_page'
		);

		// Add New
		add_submenu_page(
			'designstudio-flow',
			__( 'Add New', 'designstudio-flow' ),
			__( 'Add New', 'designstudio-flow' ),
			'edit_pages',
			'post-new.php?post_type=dsf_page'
		);

		// Settings
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
				'posts_per_page' => 10,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		include DSF_PLUGIN_DIR . 'templates/admin-dashboard.php';
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
				echo '<p class="dsf-flow-editor-panel__text">' . esc_html__( 'Launch the Flow editor to design this page using blocks and global theme settings.', 'designstudio-flow' ) . '</p>';
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
