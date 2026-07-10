<?php
/**
 * Editor functionality for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Editor {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_editor_redirect' ) );
		add_action( 'admin_menu', array( $this, 'add_editor_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'filter_editor_assets' ), 999 );
		add_filter( 'admin_body_class', array( $this, 'add_editor_body_class' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
	}

	/**
	 * Add type="module" to Vite scripts
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ) {
		unset( $src );
		// Add type="module" for Vite dev scripts
		if ( in_array( $handle, array( 'dsf-editor-vite', 'dsf-editor' ), true ) ) {
			// Check if it's already a module
			if ( false !== strpos( $tag, 'type="module"' ) ) {
				return $tag;
			}
			// Replace the script tag to add type="module"
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Handle redirect to the Flow editor for supported content.
	 */
	public function handle_editor_redirect() {
		global $pagenow;

		$post_id   = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$action    = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action    = $action ? sanitize_key( $action ) : '';
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';

		if ( 'post.php' === $pagenow && $post_id && 'edit' === $action ) {
			$post_id   = intval( $post_id );
			$post_type = get_post_type( $post_id );

			// Flow-only post types open in the Flow editor instead of the WP editor.
			// Pages with _dsf_enabled keep their normal WP editor; the admin
			// bar already provides a separate "DS Flow" shortcut.
			if ( in_array( $post_type, array( 'dsf_layout', 'dsf_saved_block', 'dsf_template', 'dsf_product_template' ), true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dsf-editor&post_id=' . $post_id ) );
				exit;
			}
		}

		if ( 'post-new.php' === $pagenow && 'dsf_layout' === $post_type ) {
			$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$layout_type = $layout_type ? sanitize_key( $layout_type ) : 'header';
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = 'header';
			}

			$redirect_url = add_query_arg(
				array(
					'page'            => 'dsf-editor',
					'post_type'       => $post_type,
					'dsf_layout_type' => $layout_type,
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'post-new.php' === $pagenow && 'dsf_product_template' === $post_type ) {
			$redirect_url = add_query_arg(
				array(
					'page'      => 'dsf-editor',
					'post_type' => 'dsf_product_template',
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Add hidden editor page
	 */
	public function add_editor_page() {
		add_submenu_page(
			null, // Hidden from menu
			__( 'Edit with DSFlow', 'designstudio-flow' ),
			__( 'Edit with DSFlow', 'designstudio-flow' ),
			'edit_pages',
			'dsf-editor',
			array( $this, 'render_editor_page' )
		);
	}

	/**
	 * Enqueue editor scripts and styles
	 */
	public function enqueue_editor_scripts( $hook ) {
		if ( 'admin_page_dsf-editor' !== $hook ) {
			return;
		}

		// Enqueue media library
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Production or development mode
		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		if ( $is_dev ) {
			// Development - load from Vite dev server
			wp_enqueue_script(
				'dsf-editor-vite',
				'http://localhost:5173/@vite/client',
				array(),
				DSF_VERSION,
				true
			);
			wp_enqueue_script(
				'dsf-editor',
				'http://localhost:5173/src/main.js',
				array( 'dsf-editor-vite' ),
				DSF_VERSION,
				true
			);
		} else {
			$editor_css_version = $this->get_asset_version( 'assets/css/editor.css' );
			$main_css_version   = $this->get_asset_version( 'assets/css/main.css' );
			$editor_js_version  = $this->get_asset_version( 'assets/js/editor.js' );

			// Production - load built assets
			wp_enqueue_style(
				'dsf-main',
				DSF_PLUGIN_URL . 'assets/css/main.css',
				array(),
				$main_css_version
			);

			wp_enqueue_style(
				'dsf-editor',
				DSF_PLUGIN_URL . 'assets/css/editor.css',
				array( 'dsf-main' ),
				$editor_css_version
			);

			wp_enqueue_script(
				'dsf-editor',
				DSF_PLUGIN_URL . 'assets/js/editor.js',
				array(),
				$editor_js_version,
				true
			);
		}

		// Pass data to JavaScript
		$post_id   = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id   = $post_id ? intval( $post_id ) : 0;
		$post      = $post_id ? get_post( $post_id ) : null;
		$post_type = $post ? $post->post_type : '';
		if ( ! $post_type ) {
			$query_post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$post_type       = $query_post_type ? sanitize_key( $query_post_type ) : 'page';
		}
		if ( ! in_array( $post_type, array( 'page', 'dsf_layout', 'dsf_saved_block', 'dsf_template', 'dsf_product_template' ), true ) ) {
			$post_type = 'page';
		}

		$layout_type = $this->get_layout_type( $post_id );
		$preview_url = ( $post_id && 'dsf_layout' !== $post_type ) ? get_preview_post_link( $post_id ) : '';
		$view_url    = ( $post_id && 'dsf_layout' !== $post_type ) ? get_permalink( $post_id ) : '';
		$post_status = $post ? $post->post_status : 'draft';

		// A product template has no page of its own — "View" opens the preview
		// product's real frontend URL (which uses this template once it is live).
		if ( 'dsf_product_template' === $post_type ) {
			$preview_product_id = $post_id ? absint( get_post_meta( $post_id, '_dsf_pt_preview_product', true ) ) : 0;
			$product_url        = ( $preview_product_id && 'product' === get_post_type( $preview_product_id ) )
				? get_permalink( $preview_product_id )
				: '';
			$view_url           = $product_url ? $product_url : '';
			$preview_url        = $view_url;
		}

		wp_localize_script(
			'dsf-editor',
			'dsfEditorData',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'restUrl'           => rest_url( 'dsf/v1/' ),
				'nonce'             => wp_create_nonce( 'dsf_editor_nonce' ),
				'restNonce'         => wp_create_nonce( 'wp_rest' ),
				'postId'            => $post_id,
				'postTitle'         => $post ? $post->post_title : '',
				'postSlug'          => $post ? $post->post_name : '',
				'postParent'        => $post ? (int) $post->post_parent : 0,
				'postType'          => $post_type,
				'layoutType'        => $layout_type,
				'pageData'          => $this->get_page_data( $post_id ),
				'parentPages'       => 'page' === $post_type ? $this->get_parent_page_options( $post_id ) : array(),
				'layoutTemplates'   => $this->get_layout_templates(),
				'defaultLayoutIds'  => array(
					'header' => absint( get_option( 'dsf_default_header_id', 0 ) ),
					'footer' => absint( get_option( 'dsf_default_footer_id', 0 ) ),
				),
				'layoutCreateUrls'  => array(
					'header' => admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=header' ),
					'footer' => admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=footer' ),
				),
				'blocks'            => DSF_Blocks::get_instance()->get_registered_blocks(),
				'blockPresets'      => DSF_Block_Presets::get_instance()->get_presets(),
				'forms'             => $this->get_available_forms(),
				'gravityForms'      => $this->get_available_gravity_forms(),
				'popups'            => DSF_Popup::get_popup_list(),
				'popupCreateUrl'    => admin_url( 'post-new.php?post_type=dsf_popup' ),
				'popupEditUrlBase'  => admin_url( 'post.php?action=edit&post=' ),
				'categories'        => $this->get_wc_categories(),
				'productTags'       => $this->get_wc_product_tags(),
				'themeFonts'        => $this->get_theme_fonts(),
				'themeTypography'   => $this->get_theme_typography_payload(),
				'defaultTheme'      => DSF_Frontend::get_default_theme_settings(),
				'pluginUrl'         => DSF_PLUGIN_URL,
				'homeUrl'           => home_url(),
				'adminUrl'          => admin_url(),
				'previewUrl'        => $preview_url,
				'viewUrl'           => $view_url,
				'postStatus'        => $post_status,
				'isWooActive'       => class_exists( 'WooCommerce' ),
				'wcAjaxUrl'         => class_exists( 'WooCommerce' ) ? \WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
				'isProductTemplate' => 'dsf_product_template' === $post_type,
				'productTemplate'   => 'dsf_product_template' === $post_type ? $this->get_product_template_config( $post_id ) : null,
				'currentProduct'    => 'dsf_product_template' === $post_type ? $this->get_product_template_preview_context( $post_id ) : null,
			)
		);
	}

	/**
	 * Read a product template's editor configuration (assignment, live toggle, and
	 * the editor-only preview product).
	 *
	 * @param int $post_id Product template post ID.
	 * @return array
	 */
	private function get_product_template_config( $post_id ) {
		$post_id = intval( $post_id );

		return array(
			'assignment'     => $post_id ? DSF_Product_Templates::get_assignment( $post_id ) : array(
				'mode'        => 'all',
				'categoryIds' => array(),
			),
			'active'         => $post_id ? ( '1' === (string) get_post_meta( $post_id, '_dsf_pt_active', true ) ) : false,
			'previewProduct' => $post_id ? absint( get_post_meta( $post_id, '_dsf_pt_preview_product', true ) ) : 0,
		);
	}

	/**
	 * Build the live product payload for the editor preview, using the template's
	 * saved sample product (falling back to the most recent product).
	 *
	 * @param int $post_id Product template post ID.
	 * @return array|null
	 */
	private function get_product_template_preview_context( $post_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		$product_id = $post_id ? absint( get_post_meta( $post_id, '_dsf_pt_preview_product', true ) ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			$recent     = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$product_id = ! empty( $recent ) ? absint( $recent[0] ) : 0;
		}

		if ( ! $product_id ) {
			return null;
		}

		$context = DSF_Product_Templates::build_product_context( $product_id, array( 'related' => true ) );
		return empty( $context ) ? null : $context;
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

	/**
	 * Filter admin scripts/styles for the editor page only.
	 */
	public function filter_editor_assets( $hook ) {
		if ( 'admin_page_dsf-editor' !== $hook ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		if ( $wp_scripts && is_array( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $handle ) {
				if ( empty( $wp_scripts->registered[ $handle ]->src ) ) {
					continue;
				}
				$src = $wp_scripts->registered[ $handle ]->src;
				if ( false !== strpos( $src, '/themes/' ) || false !== strpos( $src, 'dsShowcase' ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}

		if ( $wp_styles && is_array( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $handle ) {
				if ( empty( $wp_styles->registered[ $handle ]->src ) ) {
					continue;
				}
				$src = $wp_styles->registered[ $handle ]->src;
				if ( false !== strpos( $src, '/themes/' ) || false !== strpos( $src, 'dsShowcase' ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}
	}

	/**
	 * Add body class for editor page
	 */
	public function add_editor_body_class( $classes ) {
		$screen = get_current_screen();

		if ( $screen && 'admin_page_dsf-editor' === $screen->id ) {
			$classes .= ' dsf-editor-page';
		}

		return $classes;
	}

	/**
	 * Render editor page
	 */
	public function render_editor_page() {
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;

		// Create new page if no ID
		if ( ! $post_id ) {
			$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$post_type = $post_type ? sanitize_key( $post_type ) : 'page';
			if ( ! in_array( $post_type, array( 'page', 'dsf_layout', 'dsf_product_template' ), true ) ) {
				$post_type = 'page';
			}

			$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$layout_type = $layout_type ? sanitize_key( $layout_type ) : 'header';
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = 'header';
			}

			if ( 'dsf_layout' === $post_type ) {
				$default_title = 'footer' === $layout_type ? __( 'Untitled DSFlow Footer', 'designstudio-flow' ) : __( 'Untitled DSFlow Header', 'designstudio-flow' );
			} elseif ( 'dsf_product_template' === $post_type ) {
				$default_title = __( 'Untitled Product Template', 'designstudio-flow' );
			} else {
				$default_title = __( 'Untitled Page', 'designstudio-flow' );
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'draft',
					'post_title'  => $default_title,
				)
			);

			if ( 'dsf_layout' === $post_type && $post_id ) {
				update_post_meta( $post_id, '_dsf_layout_type', $layout_type );
			}

			if ( 'page' === $post_type && $post_id ) {
				update_post_meta( $post_id, '_dsf_enabled', true );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=dsf-editor&post_id=' . $post_id ) );
			exit;
		}

		include DSF_PLUGIN_DIR . 'templates/editor-page.php';
	}

	/**
	 * Get page data (blocks and settings)
	 */
	private function get_page_data( $post_id ) {
		if ( ! $post_id ) {
			return array(
				'blocks'   => array(),
				'settings' => $this->get_default_settings(),
			);
		}

		// Saved blocks + templates store their blocks in their own meta; map them
		// onto the editor's blocks model (each needs a client id).
		$library_type = get_post_type( $post_id );
		if ( 'dsf_template' === $library_type ) {
			$tpl_blocks = get_post_meta( $post_id, '_dsf_template_blocks', true );
			$tpl_theme  = get_post_meta( $post_id, '_dsf_template_theme', true );
			$blocks     = array();
			foreach ( (array) $tpl_blocks as $index => $block ) {
				if ( ! is_array( $block ) || empty( $block['type'] ) ) {
					continue;
				}
				$blocks[] = array(
					'id'       => 'block_tpl_' . $index . '_' . wp_generate_password( 6, false, false ),
					'type'     => $block['type'],
					'settings' => isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array(),
				);
			}
			$settings = $this->get_default_settings();
			if ( is_array( $tpl_theme ) && ! empty( $tpl_theme ) ) {
				$settings['theme'] = $tpl_theme;
			}
			return array(
				'blocks'   => $blocks,
				'settings' => $settings,
			);
		}
		if ( 'dsf_saved_block' === $library_type ) {
			$type     = get_post_meta( $post_id, '_dsf_block_type', true );
			$settings = get_post_meta( $post_id, '_dsf_block_settings', true );
			$blocks   = array();
			if ( $type ) {
				$blocks[] = array(
					'id'       => 'block_saved_' . $post_id,
					'type'     => $type,
					'settings' => is_array( $settings ) ? $settings : array(),
				);
			}
			return array(
				'blocks'   => $blocks,
				'settings' => $this->get_default_settings(),
			);
		}

		$blocks_meta   = get_post_meta( $post_id, '_dsf_blocks', true );
		$settings_meta = get_post_meta( $post_id, '_dsf_settings', true );

		if ( is_array( $blocks_meta ) ) {
			$blocks_data  = $blocks_meta;
			$blocks_error = JSON_ERROR_NONE;
		} else {
			$blocks_data  = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks_error = json_last_error();
			if ( JSON_ERROR_NONE !== $blocks_error && $blocks_meta ) {
				$blocks_data  = json_decode( wp_unslash( $blocks_meta ), true );
				$blocks_error = json_last_error();
			}
		}

		if ( is_array( $settings_meta ) ) {
			$settings_data  = $settings_meta;
			$settings_error = JSON_ERROR_NONE;
		} else {
			$settings_data  = $settings_meta ? json_decode( $settings_meta, true ) : $this->get_default_settings();
			$settings_error = json_last_error();
			if ( JSON_ERROR_NONE !== $settings_error && $settings_meta ) {
				$settings_data  = json_decode( wp_unslash( $settings_meta ), true );
				$settings_error = json_last_error();
			}
		}

		if ( JSON_ERROR_NONE !== $blocks_error ) {
			$blocks_data = array();
		}
		if ( JSON_ERROR_NONE !== $settings_error || ! is_array( $settings_data ) ) {
			$settings_data = $this->get_default_settings();
		}

		return array(
			'blocks'   => $blocks_data,
			'settings' => $settings_data,
		);
	}

	/**
	 * Get default page settings
	 */
	private function get_default_settings() {
		return array(
			'theme'  => DSF_Frontend::get_default_theme_settings(),
			'layout' => array(
				'containerWidth'   => DSF_Frontend::get_typography_option()['container_width'],
				'contentPadding'   => 10,
				'showHeader'       => true,
				'showFooter'       => true,
				'headerTemplateId' => 0,
				'footerTemplateId' => 0,
				'template'         => 'default',
			),
			'popup'  => array(
				'enabled'        => false,
				'type'           => 'content',
				'headline'       => 'Limited time offer',
				'body'           => '<p>Add your popup message here.</p>',
				'image'          => '',
				'imageAlt'       => '',
				'imagePosition'  => 'top',
				'buttonText'     => 'Learn more',
				'buttonUrl'      => '#',
				'openNewTab'     => false,
				'width'          => 'medium',
				'position'       => 'center',
				'delaySeconds'   => 3,
				'startDate'      => '',
				'endDate'        => '',
				'cookieDuration' => 24,
				'cookieUnit'     => 'hours',
				'showOverlay'    => true,
				'closeOnOverlay' => true,
				'showClose'      => true,
				'backgroundColor' => '#FFFFFF',
				'textColor'      => '#1F2937',
				'accentColor'    => '#2C5F5D',
			),
		);
	}

	/**
	 * Get current layout type for template posts.
	 */
	private function get_layout_type( $post_id ) {
		if ( ! $post_id ) {
			$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$layout_type = $layout_type ? sanitize_key( $layout_type ) : '';
		} else {
			$layout_type = get_post_meta( $post_id, '_dsf_layout_type', true );
			$layout_type = $layout_type ? sanitize_key( $layout_type ) : '';
		}

		return 'footer' === $layout_type ? 'footer' : 'header';
	}

	/**
	 * Get available header/footer templates for page assignment UI.
	 */
	private function get_layout_templates() {
		$templates = get_posts(
			array(
				'post_type'      => 'dsf_layout',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$result = array(
			'headers' => array(),
			'footers' => array(),
		);

		foreach ( $templates as $template ) {
			$type = get_post_meta( $template->ID, '_dsf_layout_type', true );
			$type = 'footer' === $type ? 'footer' : 'header';
			$key  = 'footer' === $type ? 'footers' : 'headers';

			$result[ $key ][] = array(
				'id'       => intval( $template->ID ),
				'title'    => $template->post_title ? $template->post_title : __( '(no title)', 'designstudio-flow' ),
				'status'   => $template->post_status,
				'editUrl'  => admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $template->ID ) ),
				'modified' => get_post_modified_time( 'U', true, $template ),
			);
		}

		return $result;
	}

	/**
	 * Get available DesignStudio Flow forms for block settings.
	 */
	private function get_available_forms() {
		$forms = get_posts(
			array(
				'post_type'      => 'dsf_form',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $forms ) ) {
			return array();
		}

		return array_map(
			function ( $form ) {
				return array(
					'id'        => intval( $form->ID ),
					'title'     => $form->post_title ? $form->post_title : __( '(no title)', 'designstudio-flow' ),
					'status'    => $form->post_status,
					'shortcode' => "[dsform id='" . intval( $form->ID ) . "']",
				);
			},
			$forms
		);
	}

	/**
	 * Get available Gravity Forms for shortcode insertion when the plugin is active.
	 */
	private function get_available_gravity_forms() {
		if ( ! class_exists( 'GFAPI' ) || ! is_callable( array( 'GFAPI', 'get_forms' ) ) ) {
			return array();
		}

		$forms = GFAPI::get_forms();
		if ( empty( $forms ) || ! is_array( $forms ) ) {
			return array();
		}

		$result = array();
		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$form_id = absint( $form['id'] ?? 0 );
			if ( ! $form_id ) {
				continue;
			}

			$title = sanitize_text_field( $form['title'] ?? '' );
			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %d: Gravity Forms form id. */
					__( 'Gravity Form #%d', 'designstudio-flow' ),
					$form_id
				);
			}

			$result[] = array(
				'id'        => $form_id,
				'title'     => $title,
				'shortcode' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="true"]', $form_id ),
			);
		}

		return $result;
	}

	/**
	 * Get WooCommerce categories with recursive product counts and parent info.
	 */
	private function get_wc_categories() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		// Build an id→direct_count map and a parent→children map.
		$direct_counts = array();
		$children_map  = array();
		foreach ( $categories as $cat ) {
			$direct_counts[ $cat->term_id ] = (int) $cat->count;
			if ( ! isset( $children_map[ $cat->parent ] ) ) {
				$children_map[ $cat->parent ] = array();
			}
			$children_map[ $cat->parent ][] = $cat->term_id;
		}

		// Recursively sum a category's count plus all descendants.
		$get_total = null;
		$get_total = function ( $id ) use ( &$get_total, &$direct_counts, &$children_map ) {
			$total = isset( $direct_counts[ $id ] ) ? $direct_counts[ $id ] : 0;
			if ( ! empty( $children_map[ $id ] ) ) {
				foreach ( $children_map[ $id ] as $child_id ) {
					$total += $get_total( $child_id );
				}
			}
			return $total;
		};

		$result = array();
		foreach ( $categories as $cat ) {
			$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
			$term_link    = get_term_link( $cat );

			$result[] = array(
				'id'          => $cat->term_id,
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'parent'      => (int) $cat->parent,
				'count'       => (int) $cat->count,
				'total_count' => $get_total( $cat->term_id ),
				'url'         => is_wp_error( $term_link ) ? '' : $term_link,
				'image'       => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
				'imageAlt'    => $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			);
		}

		return $result;
	}

	/**
	 * Get page options for assigning a WordPress parent page.
	 */
	private function get_parent_page_options( $current_post_id ) {
		$pages = get_pages(
			array(
				'post_type'   => 'page',
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'exclude'     => array_filter( array( (int) $current_post_id ) ),
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
			)
		);

		if ( empty( $pages ) ) {
			return array();
		}

		$children = array();
		foreach ( $pages as $page ) {
			$parent_id = (int) $page->post_parent;
			if ( (int) $current_post_id === $parent_id ) {
				$parent_id = 0;
			}
			if ( ! isset( $children[ $parent_id ] ) ) {
				$children[ $parent_id ] = array();
			}
			$children[ $parent_id ][] = $page;
		}

		$result = array();
		$walk   = function ( $parent_id, $depth ) use ( &$walk, &$children, &$result ) {
			if ( empty( $children[ $parent_id ] ) ) {
				return;
			}

			foreach ( $children[ $parent_id ] as $page ) {
				$result[] = array(
					'id'         => (int) $page->ID,
					'title'      => html_entity_decode( get_the_title( $page ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'slug'       => $page->post_name,
					'status'     => $page->post_status,
					'parent'     => (int) $page->post_parent,
					'depth'      => $depth,
					'depthLabel' => str_repeat( '— ', $depth ),
				);
				$walk( (int) $page->ID, $depth + 1 );
			}
		};

		$walk( 0, 0 );

		return $result;
	}

	/**
	 * Get WooCommerce product tags for tag filter configuration.
	 */
	private function get_wc_product_tags() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $tags ) ) {
			return array();
		}

		return array_map(
			function ( $tag ) {
				return array(
					'id'    => $tag->term_id,
					'name'  => $tag->name,
					'slug'  => $tag->slug,
					'count' => (int) $tag->count,
				);
			},
			$tags
		);
	}

	/**
	 * Build the typography payload exposed to the editor:
	 *   { base, scale, tokens, mode, headingFont, bodyFont }
	 * The editor uses tokens for the canvas CSS vars, and the font names as
	 * fallbacks when per-page settings don't specify a font.
	 */
	private function get_theme_typography_payload() {
		$defaults = DSF_Frontend::get_default_typography();
		$option   = DSF_Frontend::get_typography_option();
		$maps     = DSF_Frontend::get_responsive_typography_tokens();
		return array(
			'base'         => $defaults['base'],
			'scale'        => $defaults['scale'],
			'tokens'       => $maps['desktop'],
			'tokensLaptop' => $maps['laptop'],
			'tokensMobile' => $maps['mobile'],
			'mode'         => $option['mode'],
			'headingFont'  => $option['heading_font'],
			'bodyFont'     => $option['body_font'],
		);
	}

	/**
	 * Get fonts from the active WordPress theme (theme.json)
	 */
	private function get_theme_fonts() {
		$fonts = array();

		// Try to get fonts from theme.json (block themes)
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings     = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
			$font_sources = array( 'theme', 'custom', 'default' );

			foreach ( $font_sources as $source ) {
				if ( ! empty( $settings[ $source ] ) && is_array( $settings[ $source ] ) ) {
					foreach ( $settings[ $source ] as $font ) {
						if ( ! empty( $font['fontFamily'] ) && ! empty( $font['name'] ) ) {
							$fonts[] = array(
								'label'  => $font['name'],
								'value'  => $font['fontFamily'],
								'source' => $source,
							);
						}
					}
				}
			}
		}

		// Fallback: try to get fonts from theme customizer settings
		if ( empty( $fonts ) ) {
			$theme_mods = get_theme_mods();
			
			$font_settings = array(
				'heading_font_family',
				'body_font_family',
				'primary_font',
				'secondary_font',
				'base_font_family',
			);

			foreach ( $font_settings as $setting ) {
				if ( ! empty( $theme_mods[ $setting ] ) ) {
					$font_value = $theme_mods[ $setting ];
					$font_name  = ucwords( str_replace( array( '-', '_', "'", '"' ), ' ', $font_value ) );
					$font_name  = trim( explode( ',', $font_name )[0] );

					$fonts[] = array(
						'label'  => $font_name,
						'value'  => "'{$font_value}', sans-serif",
						'source' => 'customizer',
					);
				}
			}
		}

		// If still no fonts found, add common system font stacks
		if ( empty( $fonts ) ) {
			$fonts = array(
				array(
					'label'  => 'System UI',
					'value'  => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif",
					'source' => 'system',
				),
				array(
					'label'  => 'Serif',
					'value'  => "Georgia, 'Times New Roman', Times, serif",
					'source' => 'system',
				),
				array(
					'label'  => 'Sans Serif',
					'value'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
					'source' => 'system',
				),
				array(
					'label'  => 'Monospace',
					'value'  => "ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace",
					'source' => 'system',
				),
			);
		}

		// Remove duplicates based on value
		$unique_fonts = array();
		$seen_values  = array();

		foreach ( $fonts as $font ) {
			$normalized = strtolower( preg_replace( '/\s+/', '', $font['value'] ) );
			if ( ! in_array( $normalized, $seen_values, true ) ) {
				$seen_values[]  = $normalized;
				$unique_fonts[] = $font;
			}
		}

		return $unique_fonts;
	}
}
