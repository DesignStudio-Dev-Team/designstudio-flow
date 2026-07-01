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

		// Saved Blocks admin list: add a Folder column (Author comes from the
		// 'author' support).
		add_filter( 'manage_dsf_saved_block_posts_columns', array( $this, 'saved_block_columns' ) );
		add_action( 'manage_dsf_saved_block_posts_custom_column', array( $this, 'saved_block_column' ), 10, 2 );
	}

	/**
	 * Add a "Folder" column to the Saved Blocks admin list.
	 */
	public function saved_block_columns( $columns ) {
		$reordered = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$reordered['dsf_folder'] = __( 'Folder', 'designstudio-flow' );
			}
			$reordered[ $key ] = $label;
		}
		if ( ! isset( $reordered['dsf_folder'] ) ) {
			$reordered['dsf_folder'] = __( 'Folder', 'designstudio-flow' );
		}
		return $reordered;
	}

	public function saved_block_column( $column, $post_id ) {
		if ( 'dsf_folder' === $column ) {
			$folder = get_post_meta( $post_id, '_dsf_block_category', true );
			echo $folder ? esc_html( $folder ) : '<span aria-hidden="true">—</span>';
		}
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

		$popup_labels = array(
			'name'               => _x( 'DesignStudio Flow Popups', 'Post type general name', 'designstudio-flow' ),
			'singular_name'      => _x( 'DesignStudio Flow Popup', 'Post type singular name', 'designstudio-flow' ),
			'menu_name'          => _x( 'DesignStudio Flow Popups', 'Admin Menu text', 'designstudio-flow' ),
			'name_admin_bar'     => _x( 'DSFlow Popup', 'Add New on Toolbar', 'designstudio-flow' ),
			'add_new'            => __( 'Add New Popup', 'designstudio-flow' ),
			'add_new_item'       => __( 'Add New Popup', 'designstudio-flow' ),
			'new_item'           => __( 'New Popup', 'designstudio-flow' ),
			'edit_item'          => __( 'Edit Popup', 'designstudio-flow' ),
			'view_item'          => __( 'View Popup', 'designstudio-flow' ),
			'all_items'          => __( 'All DesignStudio Flow Popups', 'designstudio-flow' ),
			'search_items'       => __( 'Search DesignStudio Flow Popups', 'designstudio-flow' ),
			'not_found'          => __( 'No DesignStudio Flow popups found.', 'designstudio-flow' ),
			'not_found_in_trash' => __( 'No DesignStudio Flow popups found in Trash.', 'designstudio-flow' ),
		);

		$popup_args = array(
			'labels'             => $popup_labels,
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
			'show_in_rest'       => false,
		);

		register_post_type( 'dsf_popup', $popup_args );

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

		// Saved Blocks — a site-wide library of reusable blocks (a block's type +
		// full settings) editors can drop onto any page. Managed via its own admin
		// list under the DesignStudio Flow menu.
		register_post_type(
			'dsf_saved_block',
			array(
				'labels'             => array(
					'name'               => _x( 'Saved Blocks', 'Post type general name', 'designstudio-flow' ),
					'singular_name'      => _x( 'Saved Block', 'Post type singular name', 'designstudio-flow' ),
					'menu_name'          => __( 'Saved Blocks', 'designstudio-flow' ),
					'all_items'          => __( 'Saved Blocks', 'designstudio-flow' ),
					'edit_item'          => __( 'Edit Saved Block', 'designstudio-flow' ),
					'search_items'       => __( 'Search Saved Blocks', 'designstudio-flow' ),
					'not_found'          => __( 'No saved blocks yet.', 'designstudio-flow' ),
					'not_found_in_trash' => __( 'No saved blocks found in Trash.', 'designstudio-flow' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'designstudio-flow',
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'page',
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'author' ),
				'show_in_rest'       => false,
			)
		);

		// Templates — a saved group of blocks (a section, or a whole page's blocks
		// plus its theme) reusable across pages. Site-wide, managed under the DSF menu.
		register_post_type(
			'dsf_template',
			array(
				'labels'             => array(
					'name'               => _x( 'Templates', 'Post type general name', 'designstudio-flow' ),
					'singular_name'      => _x( 'Template', 'Post type singular name', 'designstudio-flow' ),
					'menu_name'          => __( 'Templates', 'designstudio-flow' ),
					'all_items'          => __( 'Templates', 'designstudio-flow' ),
					'edit_item'          => __( 'Edit Template', 'designstudio-flow' ),
					'search_items'       => __( 'Search Templates', 'designstudio-flow' ),
					'not_found'          => __( 'No templates yet.', 'designstudio-flow' ),
					'not_found_in_trash' => __( 'No templates found in Trash.', 'designstudio-flow' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'designstudio-flow',
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'page',
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'author' ),
				'show_in_rest'       => false,
			)
		);

		// Product Templates — a reusable single-product page design (theme-builder
		// style). One template is assigned to all products or to specific product
		// categories; its blocks bind to whichever product is being viewed. Managed
		// under the DSF menu. WooCommerce templates are never modified.
		register_post_type(
			'dsf_product_template',
			array(
				'labels'             => array(
					'name'               => _x( 'Product Templates', 'Post type general name', 'designstudio-flow' ),
					'singular_name'      => _x( 'Product Template', 'Post type singular name', 'designstudio-flow' ),
					'menu_name'          => __( 'Product Templates', 'designstudio-flow' ),
					'all_items'          => __( 'Product Templates', 'designstudio-flow' ),
					'add_new'            => __( 'Add New Product Template', 'designstudio-flow' ),
					'add_new_item'       => __( 'Add New Product Template', 'designstudio-flow' ),
					'edit_item'          => __( 'Edit Product Template', 'designstudio-flow' ),
					'search_items'       => __( 'Search Product Templates', 'designstudio-flow' ),
					'not_found'          => __( 'No product templates yet.', 'designstudio-flow' ),
					'not_found_in_trash' => __( 'No product templates found in Trash.', 'designstudio-flow' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'designstudio-flow',
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'page',
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'author' ),
				'show_in_rest'       => false,
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
			'dsf_popup',
			'_dsf_popup_settings',
			array(
				'type'          => 'array',
				'description'   => 'Reusable popup settings',
				'single'        => true,
				'show_in_rest'  => false,
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

		$product_template_auth = function () {
			return current_user_can( 'edit_pages' );
		};

		register_post_meta(
			'dsf_product_template',
			'_dsf_blocks',
			array(
				'type'          => 'array',
				'description'   => 'Product template blocks data',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $product_template_auth,
			)
		);

		register_post_meta(
			'dsf_product_template',
			'_dsf_settings',
			array(
				'type'          => 'array',
				'description'   => 'Product template settings',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $product_template_auth,
			)
		);

		register_post_meta(
			'dsf_product_template',
			'_dsf_pt_assignment',
			array(
				'type'          => 'array',
				'description'   => 'Which products this template applies to',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $product_template_auth,
			)
		);

		register_post_meta(
			'dsf_product_template',
			'_dsf_pt_active',
			array(
				'type'          => 'string',
				'description'   => 'Whether this product template is live',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $product_template_auth,
			)
		);

		register_post_meta(
			'dsf_product_template',
			'_dsf_pt_preview_product',
			array(
				'type'          => 'integer',
				'description'   => 'Editor-only sample product used for preview',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $product_template_auth,
			)
		);
	}
}
