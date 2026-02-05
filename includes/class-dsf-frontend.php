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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'template_include', array( $this, 'load_flow_template' ), 99 );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Check if this is a Flow page or has Flow blocks
		$is_flow = 'dsf_page' === $post->post_type || get_post_meta( $post->ID, '_dsf_enabled', true );

		if ( ! $is_flow ) {
			return;
		}

		// Production or development mode
		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		wp_enqueue_style(
			'dsf-frontend-app',
			DSF_PLUGIN_URL . 'assets/css/FrontendApp.css',
			array(),
			DSF_VERSION
		);

		wp_enqueue_style(
			'dsf-frontend',
			DSF_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'dsf-frontend-app' ),
			DSF_VERSION
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
				DSF_VERSION,
				true
			);
		}

		$blocks_meta = get_post_meta( $post->ID, '_dsf_blocks', true );
		if ( is_array( $blocks_meta ) ) {
			$blocks = $blocks_meta;
		} else {
			$blocks = $blocks_meta ? json_decode( $blocks_meta, true ) : array();
			if ( ! is_array( $blocks ) ) {
				$blocks = array();
			}
		}

		wp_localize_script(
			'dsf-frontend-app',
			'dsfFrontendData',
			array(
				'postId'  => $post->ID,
				'blocks'  => $blocks,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dsf_frontend_nonce' ),
				'categories'  => $this->get_wc_categories(),
				'isWooActive' => class_exists( 'WooCommerce' ),
			)
		);

		wp_add_inline_script(
			'dsf-frontend-app',
			'window.dsfEditorData = window.dsfEditorData || window.dsfFrontendData || {};',
			'before'
		);
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
		$output .= '<div id="dsf-frontend-app" data-post-id="' . intval( $post_id ) . '">';
		if ( ! empty( $snapshot ) ) {
			$output .= $snapshot;
		}
		$output .= '</div>';
		$output .= '</div></div>';

		return $output;
	}

	private function get_default_settings() {
		return array(
			'theme'  => array(
				'primaryColor'    => '#2C5F5D',
				'secondaryColor'  => '#1E40AF',
				'textColor'       => '#1F2937',
				'backgroundColor' => '#FFFFFF',
			),
			'layout' => array(
				'containerWidth' => 1800,
				'contentPadding' => 10,
				'showHeader'     => true,
				'showFooter'     => true,
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
		$container_width = intval( $layout['containerWidth'] ?? $defaults['layout']['containerWidth'] );
		$content_padding = intval( $layout['contentPadding'] ?? $defaults['layout']['contentPadding'] );

		return sprintf(
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
