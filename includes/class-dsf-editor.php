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
	 * Handle redirect to editor when editing a DesignStudio Flow Page
	 */
	public function handle_editor_redirect() {
		global $pagenow;

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$action  = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action  = $action ? sanitize_key( $action ) : '';
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type = $post_type ? sanitize_key( $post_type ) : '';

		if ( 'post.php' === $pagenow && $post_id && 'edit' === $action ) {
			$post_id   = intval( $post_id );
			$post_type = get_post_type( $post_id );

			if ( in_array( $post_type, array( 'dsf_page', 'dsf_layout' ), true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dsf-editor&post_id=' . $post_id ) );
				exit;
			}
		}

		if ( 'post-new.php' === $pagenow && in_array( $post_type, array( 'dsf_page', 'dsf_layout' ), true ) ) {
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
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? intval( $post_id ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		$post_type = $post ? $post->post_type : '';
		if ( ! $post_type ) {
			$query_post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$post_type       = $query_post_type ? sanitize_key( $query_post_type ) : 'dsf_page';
		}
		if ( ! in_array( $post_type, array( 'dsf_page', 'dsf_layout' ), true ) ) {
			$post_type = 'dsf_page';
		}

		$layout_type = $this->get_layout_type( $post_id );
		$preview_url = ( $post_id && 'dsf_page' === $post_type ) ? get_preview_post_link( $post_id ) : '';
		$view_url    = ( $post_id && 'dsf_page' === $post_type ) ? get_permalink( $post_id ) : '';
		$post_status = $post ? $post->post_status : 'draft';

		wp_localize_script(
			'dsf-editor',
			'dsfEditorData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'dsf/v1/' ),
				'nonce'       => wp_create_nonce( 'dsf_editor_nonce' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'postId'      => $post_id,
				'postTitle'   => $post ? $post->post_title : '',
				'postType'    => $post_type,
				'layoutType'  => $layout_type,
				'pageData'    => $this->get_page_data( $post_id ),
				'layoutTemplates' => $this->get_layout_templates(),
				'layoutCreateUrls' => array(
					'header' => admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=header' ),
					'footer' => admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=footer' ),
				),
				'blocks'      => DSF_Blocks::get_instance()->get_registered_blocks(),
				'forms'       => $this->get_available_forms(),
				'categories'  => $this->get_wc_categories(),
				'themeFonts'  => $this->get_theme_fonts(),
				'pluginUrl'   => DSF_PLUGIN_URL,
				'homeUrl'     => home_url(),
				'adminUrl'    => admin_url(),
				'previewUrl'  => $preview_url,
				'viewUrl'     => $view_url,
				'postStatus'  => $post_status,
				'isWooActive' => class_exists( 'WooCommerce' ),
			)
		);
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
			$post_type = $post_type ? sanitize_key( $post_type ) : 'dsf_page';
			if ( ! in_array( $post_type, array( 'dsf_page', 'dsf_layout' ), true ) ) {
				$post_type = 'dsf_page';
			}

			$layout_type = filter_input( INPUT_GET, 'dsf_layout_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$layout_type = $layout_type ? sanitize_key( $layout_type ) : 'header';
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = 'header';
			}

			$default_title = 'dsf_layout' === $post_type
				? ( 'footer' === $layout_type ? __( 'Untitled DSFlow Footer', 'designstudio-flow' ) : __( 'Untitled DSFlow Header', 'designstudio-flow' ) )
				: __( 'Untitled Page', 'designstudio-flow' );

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
			'theme'  => array(
				'primaryColor'    => '#2C5F5D',
				'secondaryColor'  => '#1E40AF',
				'textColor'       => '#1F2937',
				'backgroundColor' => '#FFFFFF',
				'headingFont'     => '',
				'bodyFont'        => '',
			),
			'layout' => array(
				'containerWidth' => 1800,
				'contentPadding' => 10,
				'showHeader'     => true,
				'showFooter'     => true,
				'headerTemplateId' => 0,
				'footerTemplateId' => 0,
				'template'       => 'default',
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
	 * Get WooCommerce categories
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

		return array_map(
			function ( $cat ) {
				$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
				$term_link    = get_term_link( $cat );

				return array(
					'id'    => $cat->term_id,
					'name'  => $cat->name,
					'slug'  => $cat->slug,
					'count' => $cat->count,
					'url'   => is_wp_error( $term_link ) ? '' : $term_link,
					'image' => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
				);
			},
			$categories
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
