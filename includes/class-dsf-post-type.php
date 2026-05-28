<?php
/**
 * Post type and meta registration for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Post_Type {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register DSF internal post types.
	 */
	public function register_post_type() {
		$layout_labels = array(
			'name'               => _x( 'DesignStudio Flow Layouts', 'Post type general name', 'designstudio-flow' ),
			'singular_name'      => _x( 'DesignStudio Flow Layout', 'Post type singular name', 'designstudio-flow' ),
			'menu_name'          => _x( 'DesignStudio Flow Layouts', 'Admin Menu text', 'designstudio-flow' ),
			'name_admin_bar'     => _x( 'DesignStudio Flow Layout', 'Add New on Toolbar', 'designstudio-flow' ),
			'add_new'            => __( 'Add New DSFlow Layout', 'designstudio-flow' ),
			'add_new_item'       => __( 'Add New DSFlow Layout', 'designstudio-flow' ),
			'new_item'           => __( 'New DSFlow Layout', 'designstudio-flow' ),
			'edit_item'          => __( 'Edit DSFlow Layout', 'designstudio-flow' ),
			'view_item'          => __( 'View DSFlow Layout', 'designstudio-flow' ),
			'all_items'          => __( 'All DesignStudio Flow Layouts', 'designstudio-flow' ),
			'search_items'       => __( 'Search DesignStudio Flow Layouts', 'designstudio-flow' ),
			'not_found'          => __( 'No DesignStudio Flow layouts found.', 'designstudio-flow' ),
			'not_found_in_trash' => __( 'No DesignStudio Flow layouts found in Trash.', 'designstudio-flow' ),
		);

		$layout_args = array(
			'labels'             => $layout_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'author', 'revisions' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'dsf_layout', $layout_args );

		$form_labels = array(
			'name'               => _x( 'DesignStudio Flow Forms', 'Post type general name', 'designstudio-flow' ),
			'singular_name'      => _x( 'DesignStudio Flow Form', 'Post type singular name', 'designstudio-flow' ),
			'menu_name'          => _x( 'DesignStudio Flow Forms', 'Admin Menu text', 'designstudio-flow' ),
			'name_admin_bar'     => _x( 'DSFlow Form', 'Add New on Toolbar', 'designstudio-flow' ),
			'add_new'            => __( 'Add New DSFlow Form', 'designstudio-flow' ),
			'add_new_item'       => __( 'Add New DSFlow Form', 'designstudio-flow' ),
			'new_item'           => __( 'New DSFlow Form', 'designstudio-flow' ),
			'edit_item'          => __( 'Edit DSFlow Form', 'designstudio-flow' ),
			'view_item'          => __( 'View DSFlow Form', 'designstudio-flow' ),
			'all_items'          => __( 'All DesignStudio Flow Forms', 'designstudio-flow' ),
			'search_items'       => __( 'Search DesignStudio Flow Forms', 'designstudio-flow' ),
			'not_found'          => __( 'No DesignStudio Flow forms found.', 'designstudio-flow' ),
			'not_found_in_trash' => __( 'No DesignStudio Flow forms found in Trash.', 'designstudio-flow' ),
		);

		$form_args = array(
			'labels'             => $form_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'author', 'revisions' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'dsf_form', $form_args );

		// Entries — private records of form submissions. No public UI, no admin
		// list view (admin pages handle this themselves via WP_List_Table).
		register_post_type(
			'dsf_entry',
			array(
				'labels'              => array(
					'name'          => _x( 'Form Entries', 'Post type general name', 'designstudio-flow' ),
					'singular_name' => _x( 'Form Entry', 'Post type singular name', 'designstudio-flow' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'page',
				'has_archive'         => false,
				'supports'            => array( 'title' ),
				'show_in_rest'        => false,
			)
		);
	}

	/**
	 * Register post meta for storing block data
	 */
	public function register_meta() {
		register_post_meta(
			'dsf_layout',
			'_dsf_blocks',
			array(
				'type'          => 'array',
				'description'   => 'Blocks data',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_post_meta(
			'dsf_form',
			'_dsf_form_rows',
			array(
				'type'          => 'array',
				'description'   => 'Form builder rows',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_post_meta(
			'dsf_form',
			'_dsf_form_settings',
			array(
				'type'          => 'array',
				'description'   => 'Form settings',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_post_meta(
			'dsf_layout',
			'_dsf_settings',
			array(
				'type'          => 'array',
				'description'   => 'Template settings',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_post_meta(
			'dsf_layout',
			'_dsf_layout_type',
			array(
				'type'          => 'string',
				'description'   => 'Template type (header/footer)',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 'header',
				'auth_callback' => function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_post_meta(
			'page',
			'_dsf_blocks',
			array(
				'type'          => 'array',
				'description'   => 'Blocks data',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'page',
			'_dsf_settings',
			array(
				'type'          => 'array',
				'description'   => 'Page settings',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'page',
			'_dsf_theme_colors',
			array(
				'type'          => 'string',
				'description'   => 'JSON encoded theme colors',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'page',
			'_dsf_enabled',
			array(
				'type'          => 'boolean',
				'description'   => 'Whether DSF is enabled for this page',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
