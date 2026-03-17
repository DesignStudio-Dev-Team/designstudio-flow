<?php
/**
 * Frontend rendering for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Frontend {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'the_content', array( $this, 'render_flow_content' ), 20 );
		// Use priority 20 to ensure the main query is fully set up before we check for Flow pages.
		// This fixes asset loading for non-logged-in users where get_queried_object_id() may return 0
		// at the default priority (10) due to the query not being initialized yet.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
		add_filter( 'template_include', array( $this, 'load_flow_template' ), 99 );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
		add_filter( 'dsf_flow_show_header', array( $this, 'filter_show_header' ), 10, 2 );
		add_filter( 'dsf_flow_show_footer', array( $this, 'filter_show_footer' ), 10, 2 );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Try get_queried_object_id() first, then fall back to global $post.
		// This ensures we load assets correctly for all users, including when
		// caching plugins or certain themes don't initialize the query fully.
		$post_id = get_queried_object_id();

		// Fallback to global $post if get_queried_object_id() returns 0
		if ( ! $post_id ) {
			global $post;
			if ( $post instanceof WP_Post ) {
				$post_id = $post->ID;
			}
		}

		if ( ! $post_id ) {
			return;
		}

		$current_post = get_post( $post_id );
		if ( ! $current_post ) {
			return;
		}

		// Check if this is a Flow page or has Flow blocks
		$is_flow = 'dsf_page' === $current_post->post_type || get_post_meta( $post_id, '_dsf_enabled', true );

		if ( ! $is_flow ) {
			return;
		}

		// Production or development mode
		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		$main_css_version       = $this->get_asset_version( 'assets/css/main.css' );
		$frontend_theme_version = $this->get_asset_version( 'assets/css/frontend.css' );
		$frontend_js_version    = $this->get_asset_version( 'assets/js/frontend.js' );

		wp_enqueue_style(
			'dsf-main',
			DSF_PLUGIN_URL . 'assets/css/main.css',
			array(),
			$main_css_version
		);

		wp_enqueue_style(
			'dsf-frontend',
			DSF_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'dsf-main' ),
			$frontend_theme_version
		);

		if ( $is_dev ) {
			wp_enqueue_script(
				'dsf-frontend-vite',
				'http://localhost:5173/@vite/client',
				array(),
				DSF_VERSION,
				true
			);
			wp_enqueue_script(
				'dsf-frontend-app',
				'http://localhost:5173/src/frontend/main.js',
				array( 'dsf-frontend-vite' ),
				DSF_VERSION,
				true
			);
		} else {
			wp_enqueue_script(
				'dsf-frontend-app',
				DSF_PLUGIN_URL . 'assets/js/frontend.js',
				array(),
				$frontend_js_version,
				true
			);
		}

		$blocks_meta = get_post_meta( $post_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$blocks = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			if ( ! is_array( $blocks ) ) {
				$blocks = array();
			}
		}
		$blocks = $this->prepare_blocks_for_frontend( $blocks );

		$layout_templates = $this->get_assigned_layout_templates_data( $post_id );
		$layout_templates = $this->prepare_layout_templates_for_frontend( $layout_templates );

		wp_localize_script(
			'dsf-frontend-app',
			'dsfFrontendData',
			array(
				'postId'      => $post_id,
				'blocks'      => $blocks,
				'layoutTemplates' => $layout_templates,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'dsf_frontend_nonce' ),
				'categories'  => $this->get_wc_categories(),
				'isWooActive' => class_exists( 'WooCommerce' ),
			)
		);

		wp_add_inline_script(
			'dsf-frontend-app',
			'window.dsfEditorData = window.dsfEditorData || window.dsfFrontendData || {};',
			'before'
		);

		// Enqueue Google Fonts if custom fonts are set
		$this->enqueue_google_fonts( $post_id );
	}

	/**
	 * Prepare registered blocks for frontend runtime.
	 */
	private function prepare_blocks_for_frontend( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		return array_map(
			array( $this, 'prepare_single_block_for_frontend' ),
			$blocks
		);
	}

	/**
	 * Prepare layout templates payload for frontend runtime.
	 */
	private function prepare_layout_templates_for_frontend( $layout_templates ) {
		if ( ! is_array( $layout_templates ) ) {
			return array();
		}

		foreach ( array( 'header', 'footer' ) as $type ) {
			if ( empty( $layout_templates[ $type ]['blocks'] ) || ! is_array( $layout_templates[ $type ]['blocks'] ) ) {
				continue;
			}
			$layout_templates[ $type ]['blocks'] = $this->prepare_blocks_for_frontend( $layout_templates[ $type ]['blocks'] );
		}

		return $layout_templates;
	}

	/**
	 * Prepare one block payload for frontend rendering.
	 */
	private function prepare_single_block_for_frontend( $block ) {
		if ( ! is_array( $block ) ) {
			return array();
		}

		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( 'form-embed' !== $type ) {
			return $block;
		}

		$settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$form_id  = isset( $settings['formId'] ) ? absint( $settings['formId'] ) : 0;
		$form     = $form_id ? get_post( $form_id ) : null;

		if ( ! $form || 'dsf_form' !== $form->post_type ) {
			$form_id = 0;
		}

		$settings['formId']          = $form_id ? (string) $form_id : '';
		$settings['formTitle']       = ( $form_id && $form && $form->post_title ) ? $form->post_title : '';
		$settings['renderedFormHtml'] = $form_id ? DSF_Forms::get_instance()->render_form_shortcode( array( 'id' => $form_id ) ) : '';
		$block['settings']            = $settings;

		return $block;
	}

	/**
	 * Enqueue Google Fonts for custom theme fonts
	 */
	private function enqueue_google_fonts( $post_id ) {
		$settings = $this->get_page_settings( $post_id );
		$theme    = $settings['theme'] ?? array();

		$fonts_to_load = array();

		// Extract font names from heading and body font settings
		foreach ( array( 'headingFont', 'bodyFont' ) as $font_key ) {
			if ( ! empty( $theme[ $font_key ] ) ) {
				$font_family = $theme[ $font_key ];
				// Extract font name from font-family string like "'Inter', sans-serif"
				if ( preg_match( "/'([^']+)'/", $font_family, $matches ) ) {
					$font_name = $matches[1];
					// Skip system fonts
					if ( ! in_array( strtolower( $font_name ), array( 'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy', 'system-ui' ), true ) ) {
						$fonts_to_load[ $font_name ] = true;
					}
				}
			}
		}

		if ( empty( $fonts_to_load ) ) {
			return;
		}

		// Build Google Fonts URL
		$font_families = array();
		foreach ( array_keys( $fonts_to_load ) as $font_name ) {
			$font_families[] = str_replace( ' ', '+', $font_name ) . ':wght@400;500;600;700';
		}

		$google_fonts_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_families ) . '&display=swap';

		wp_enqueue_style(
			'dsf-google-fonts',
			$google_fonts_url,
			array(),
			null // No version for external resource
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
	 * Add type="module" to frontend Vite scripts
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ) {
		unset( $src );
		if ( in_array( $handle, array( 'dsf-frontend-vite', 'dsf-frontend-app' ), true ) ) {
			if ( false === strpos( $tag, 'type="module"' ) ) {
				$tag = str_replace( '<script ', '<script type="module" ', $tag );
			}
		}
		return $tag;
	}

	/**
	 * Render Flow page content
	 */
	public function render_flow_content( $content ) {
		global $post;

		if ( ! $post || ! is_singular() ) {
			return $content;
		}

		// Only for Flow pages or Flow-enabled pages
		$is_flow = 'dsf_page' === $post->post_type || get_post_meta( $post->ID, '_dsf_enabled', true );
		if ( ! $is_flow ) {
			return $content;
		}

		$rendered = $this->render_flow_blocks( $post->ID );
		return $rendered ? $rendered : $content;
	}

	/**
	 * Use a clean template for Flow pages
	 */
	public function load_flow_template( $template ) {
		if ( is_admin() || ! is_singular() ) {
			return $template;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $template;
		}

		$post_type = get_post_type( $post_id );
		$is_flow   = 'dsf_page' === $post_type || get_post_meta( $post_id, '_dsf_enabled', true );
		if ( ! $is_flow ) {
			return $template;
		}

		$settings        = $this->get_page_settings( $post_id );
		$layout          = $settings['layout'] ?? array();
		$template_choice = $layout['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $post_id );
		$template_file   = 'fullwidth' === $template_choice ? 'flow-page-fullwidth.php' : 'flow-page.php';
		$custom_template = DSF_PLUGIN_DIR . 'templates/' . $template_file;
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}

		return $template;
	}

	/**
	 * Render blocks for a given post
	 */
	public function render_flow_blocks( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		$blocks_json = get_post_meta( $post_id, '_dsf_blocks', true );
		if ( ! $blocks_json ) {
			return '';
		}

		$page_settings = $this->get_page_settings( $post_id );
		$theme_style   = $this->build_theme_style( $page_settings );

		$outer_class     = 'dsf-page-content';
		$inner_class     = 'dsf-page-content__inner';
		$template_choice = $page_settings['layout']['template'] ?? 'default';
		$template_choice = apply_filters( 'dsf_flow_template', $template_choice, $post_id );
		if ( 'fullwidth' === $template_choice ) {
			$outer_class .= ' dsf-page-content--fullwidth';
			$inner_class .= ' dsf-page-content__inner--fullwidth';
		}

		$snapshot = get_post_meta( $post_id, '_dsf_html_snapshot', true );

		$output  = '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div class="' . esc_attr( $inner_class ) . '">';
		$output .= '<div id="dsf-frontend-app" class="dsf-wrapper" data-post-id="' . intval( $post_id ) . '">';
		if ( ! empty( $snapshot ) ) {
			$output .= $snapshot;
		}
		$output .= '</div>';
		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Resolve page setting for showing the active theme header.
	 */
	public function filter_show_header( $show, $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id ) {
			return $show;
		}

		$settings = $this->get_page_settings( $post_id );
		if ( isset( $settings['layout']['showHeader'] ) && false === $settings['layout']['showHeader'] ) {
			return false;
		}

		return $show;
	}

	/**
	 * Resolve page setting for showing the active theme footer.
	 */
	public function filter_show_footer( $show, $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id ) {
			return $show;
		}

		$settings = $this->get_page_settings( $post_id );
		if ( isset( $settings['layout']['showFooter'] ) && false === $settings['layout']['showFooter'] ) {
			return false;
		}

		return $show;
	}

	/**
	 * Render the assigned custom header/footer template for a Flow page.
	 */
	public function render_assigned_layout_template( $post_id, $type ) {
		$layout_data = $this->get_assigned_layout_template_data( $post_id, $type );
		if ( empty( $layout_data ) || empty( $layout_data['id'] ) ) {
			return '';
		}

		$settings    = $this->get_page_settings( $post_id );
		$type        = 'footer' === sanitize_key( $type ) ? 'footer' : 'header';
		$template_id = intval( $layout_data['id'] );
		$snapshot    = $layout_data['snapshot'] ?? '';

		$theme_style = $this->build_theme_style( $settings );
		$app_id      = 'dsf-layout-' . $type . '-app';

		$output  = '<div class="dsf-layout-template dsf-layout-template--' . esc_attr( $type ) . '" style="' . esc_attr( $theme_style ) . '">';
		$output .= '<div id="' . esc_attr( $app_id ) . '" class="dsf-wrapper" data-dsf-layout-id="' . intval( $template_id ) . '" data-dsf-layout-type="' . esc_attr( $type ) . '">';
		if ( ! empty( $snapshot ) ) {
			$output .= $snapshot;
		}
		$output .= '</div>';
		$output .= '</div>';
		return $output;
	}

	/**
	 * Collect assigned header/footer template data for frontend bootstrapping.
	 */
	private function get_assigned_layout_templates_data( $post_id ) {
		$data = array(
			'header' => $this->get_assigned_layout_template_data( $post_id, 'header' ),
			'footer' => $this->get_assigned_layout_template_data( $post_id, 'footer' ),
		);

		return $data;
	}

	/**
	 * Resolve one assigned template and return sanitized data for rendering/mounting.
	 */
	private function get_assigned_layout_template_data( $post_id, $type ) {
		$post_id = intval( $post_id );
		$type    = sanitize_key( $type );
		if ( ! $post_id || ! in_array( $type, array( 'header', 'footer' ), true ) ) {
			return array();
		}

		$settings    = $this->get_page_settings( $post_id );
		$layout_key  = 'header' === $type ? 'headerTemplateId' : 'footerTemplateId';
		$template_id = absint( $settings['layout'][ $layout_key ] ?? 0 );
		if ( ! $template_id ) {
			return array();
		}

		$template_post = get_post( $template_id );
		if ( ! $template_post || 'dsf_layout' !== $template_post->post_type ) {
			return array();
		}

		$template_type = get_post_meta( $template_id, '_dsf_layout_type', true );
		$template_type = 'footer' === $template_type ? 'footer' : 'header';
		if ( $type !== $template_type ) {
			return array();
		}

		if ( 'publish' !== $template_post->post_status ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $template_id ) ) {
				return array();
			}
		}

		$blocks_meta = get_post_meta( $template_id, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$decoded = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}

		$snapshot = get_post_meta( $template_id, '_dsf_html_snapshot', true );

		return array(
			'id'       => $template_id,
			'status'   => $template_post->post_status,
			'blocks'   => $blocks,
			'snapshot' => is_string( $snapshot ) ? $snapshot : '',
		);
	}

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

	public function get_page_settings( $post_id ) {
		$raw_settings = get_post_meta( $post_id, '_dsf_settings', true );
		if ( is_array( $raw_settings ) ) {
			$settings = $raw_settings;
		} else {
			$decoded  = $raw_settings ? json_decode( $raw_settings, true ) : array();
			$settings = is_array( $decoded ) ? $decoded : array();
		}

		$defaults           = $this->get_default_settings();
		$settings['theme']  = array_merge( $defaults['theme'], $settings['theme'] ?? array() );
		$settings['layout'] = array_merge( $defaults['layout'], $settings['layout'] ?? array() );

		return $settings;
	}

	private function build_theme_style( $page_settings ) {
		$defaults = $this->get_default_settings();
		$theme    = array_merge( $defaults['theme'], $page_settings['theme'] ?? array() );
		$layout   = array_merge( $defaults['layout'], $page_settings['layout'] ?? array() );

		$primary         = $theme['primaryColor'] ?? $defaults['theme']['primaryColor'];
		$secondary       = $theme['secondaryColor'] ?? $defaults['theme']['secondaryColor'];
		$text            = $theme['textColor'] ?? $defaults['theme']['textColor'];
		$background      = $theme['backgroundColor'] ?? $defaults['theme']['backgroundColor'];
		$heading_font    = $theme['headingFont'] ?? '';
		$body_font       = $theme['bodyFont'] ?? '';
		$container_width = intval( $layout['containerWidth'] ?? $defaults['layout']['containerWidth'] );
		$content_padding = intval( $layout['contentPadding'] ?? $defaults['layout']['contentPadding'] );

		$style = sprintf(
			'--dsf-theme-primary:%s; --dsf-theme-secondary:%s; --dsf-theme-text:%s; --dsf-theme-background:%s; --dsf-theme-container-width:%dpx; --dsf-theme-content-padding:%dpx; --dsf-primary-500:%s; --dsf-primary-600:%s; --dsf-primary-700:%s;',
			esc_attr( $primary ),
			esc_attr( $secondary ),
			esc_attr( $text ),
			esc_attr( $background ),
			$container_width,
			$content_padding,
			esc_attr( $primary ),
			esc_attr( $primary ),
			esc_attr( $primary )
		);

		// Add font CSS variables if set
		if ( ! empty( $heading_font ) ) {
			$style .= sprintf( ' --dsf-theme-heading-font:%s;', esc_attr( $heading_font ) );
		}
		if ( ! empty( $body_font ) ) {
			$style .= sprintf( ' --dsf-theme-body-font:%s;', esc_attr( $body_font ) );
		}

		return $style;
	}

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

				return array(
					'id'    => $cat->term_id,
					'name'  => $cat->name,
					'slug'  => $cat->slug,
					'count' => $cat->count,
					'url'   => get_term_link( $cat ),
					'image' => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
				);
			},
			$categories
		);
	}
}
