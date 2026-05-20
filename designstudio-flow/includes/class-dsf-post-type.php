<?php
/**
 * Custom Post Type for DesignStudio Flow Pages
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
	 * Register the DSF Page post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'DesignStudio Flow Pages', 'Post type general name', 'designstudio-flow' ),
			'singular_name'      => _x( 'DesignStudio Flow Page', 'Post type singular name', 'designstudio-flow' ),
			'menu_name'          => _x( 'DesignStudio Flow Pages', 'Admin Menu text', 'designstudio-flow' ),
			'name_admin_bar'     => _x( 'DesignStudio Flow Page', 'Add New on Toolbar', 'designstudio-flow' ),
			'add_new'            => __( 'Add New', 'designstudio-flow' ),
			'add_new_item'       => __( 'Add New DesignStudio Flow Page', 'designstudio-flow' ),
			'new_item'           => __( 'New DesignStudio Flow Page', 'designstudio-flow' ),
			'edit_item'          => __( 'Edit DesignStudio Flow Page', 'designstudio-flow' ),
			'view_item'          => __( 'View DesignStudio Flow Page', 'designstudio-flow' ),
			'all_items'          => __( 'All DesignStudio Flow Pages', 'designstudio-flow' ),
			'search_items'       => __( 'Search DesignStudio Flow Pages', 'designstudio-flow' ),
			'parent_item_colon'  => __( 'Parent DesignStudio Flow Pages:', 'designstudio-flow' ),
			'not_found'          => __( 'No DesignStudio Flow pages found.', 'designstudio-flow' ),
			'not_found_in_trash' => __( 'No DesignStudio Flow pages found in Trash.', 'designstudio-flow' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // We'll add it to our custom menu
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'flow' ),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => true,
			'menu_position'      => null,
			'supports'           => array( 'title', 'author', 'thumbnail', 'excerpt', 'revisions' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'dsf_page', $args );

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
	}

	/**
	 * Register post meta for storing block data
	 */
	public function register_meta() {
		register_post_meta(
			'dsf_page',
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
			'dsf_page',
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
			'dsf_page',
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

		// Also register for regular pages if enabled
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
