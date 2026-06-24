<?php
/**
 * AJAX handlers for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Ajax {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Save page
		add_action( 'wp_ajax_dsf_save_page', array( $this, 'save_page' ) );

		// Get products
		add_action( 'wp_ajax_dsf_get_products', array( $this, 'get_products' ) );
		add_action( 'wp_ajax_nopriv_dsf_get_products', array( $this, 'get_products' ) );

		// Search products
		add_action( 'wp_ajax_dsf_search_products', array( $this, 'search_products' ) );

		// Get categories
		add_action( 'wp_ajax_dsf_get_categories', array( $this, 'get_categories' ) );
		add_action( 'wp_ajax_nopriv_dsf_get_categories', array( $this, 'get_categories' ) );

		// Upload image
		add_action( 'wp_ajax_dsf_upload_image', array( $this, 'upload_image' ) );

		// Update page title
		add_action( 'wp_ajax_dsf_update_title', array( $this, 'update_title' ) );

		// Publish page
		add_action( 'wp_ajax_dsf_publish_page', array( $this, 'publish_page' ) );

		// Render shortcode for modal content
		add_action( 'wp_ajax_dsf_render_shortcode', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_nopriv_dsf_render_shortcode', array( $this, 'render_shortcode' ) );

		// List reusable popups for the page-settings picker.
		add_action( 'wp_ajax_dsf_list_popups', array( $this, 'list_popups' ) );
	}

	/**
	 * Return the list of reusable popups for the page-settings picker.
	 */
	public function list_popups() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		wp_send_json_success( array( 'popups' => DSF_Popup::get_popup_list() ) );
	}

	/**
	 * Verify permissions for editor actions.
	 */
	private function verify_permissions() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
	}

	/**
	 * Normalize request payloads that may arrive as scalars, arrays, or JSON strings.
	 *
	 * @param mixed $value Raw request value.
	 * @return int[]
	 */
	private function normalize_numeric_id_list( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				$value = array( $value );
			}
		}

		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		$ids = array();
		foreach ( $value as $item ) {
			$id = intval( $item );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Save page blocks and settings
	 */
	public function save_page() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id       = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$blocks        = isset( $_POST['blocks'] ) ? $_POST['blocks'] : '[]';
		$settings      = isset( $_POST['settings'] ) ? $_POST['settings'] : '{}';
		$html_snapshot = isset( $_POST['html_snapshot'] ) ? wp_unslash( $_POST['html_snapshot'] ) : '';
		$status        = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug          = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$parent_id     = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
		$layout_type   = isset( $_POST['layout_type'] ) ? sanitize_key( wp_unslash( $_POST['layout_type'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
		}

		// Validate JSON
		$blocks_raw  = wp_unslash( $blocks );
		$blocks_data = json_decode( $blocks_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid blocks JSON data: ' . json_last_error_msg() ) );
		}
		$blocks_data = $this->sanitize_known_block_settings( $blocks_data );

		$settings_raw  = wp_unslash( $settings );
		$settings_data = json_decode( $settings_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid settings JSON data: ' . json_last_error_msg() ) );
		}
		$settings_data = is_array( $settings_data ) ? $settings_data : array();
		$settings_data['theme']  = $this->sanitize_page_theme_settings( $settings_data['theme'] ?? array() );
		$settings_data['layout'] = $this->sanitize_page_layout_settings( $settings_data['layout'] ?? array() );
		if ( isset( $settings_data['popup'] ) && is_array( $settings_data['popup'] ) ) {
			$settings_data['popup'] = $this->sanitize_popup_settings( $settings_data['popup'] );
		}
		$settings_data['popupId'] = isset( $settings_data['popupId'] ) ? absint( $settings_data['popupId'] ) : 0;

		$post_type = get_post_type( $post_id );
		if ( 'dsf_layout' === $post_type ) {
			$current_layout_type = in_array( $layout_type, array( 'header', 'footer' ), true )
				? $layout_type
				: get_post_meta( $post_id, '_dsf_layout_type', true );
			if ( 'header' === $current_layout_type && count( $blocks_data ) > 1 ) {
				$blocks_data = array_slice( $blocks_data, 0, 1 );
			}
		}

		// Save meta as arrays (avoids JSON escaping issues)
		update_post_meta( $post_id, '_dsf_blocks', $blocks_data );
		update_post_meta( $post_id, '_dsf_settings', $settings_data );
		if ( '' !== $html_snapshot ) {
			update_post_meta( $post_id, '_dsf_html_snapshot', $this->sanitize_snapshot_html( $html_snapshot ) );
		}

		if ( 'page' === $post_type ) {
			update_post_meta( $post_id, '_dsf_enabled', true );
		}

		if ( 'dsf_layout' === $post_type ) {
			if ( ! in_array( $layout_type, array( 'header', 'footer' ), true ) ) {
				$layout_type = get_post_meta( $post_id, '_dsf_layout_type', true );
			}
			$layout_type = 'footer' === $layout_type ? 'footer' : 'header';
			update_post_meta( $post_id, '_dsf_layout_type', $layout_type );
		}

		// Update modified time and status (if requested)
		$post_update = array(
			'ID'                => $post_id,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		if ( '' !== $title ) {
			$post_update['post_title'] = $title;
		}

		if ( 'page' === $post_type ) {
			$post_update['post_name']   = '' !== $slug ? $slug : sanitize_title( $title );
			$post_update['post_parent'] = $this->get_valid_page_parent_id( $parent_id, $post_id );
		}

		if ( 'draft' === $status ) {
			$post_update['post_status'] = 'draft';
		} elseif ( 'publish' === $status ) {
			$post_update['post_status'] = 'publish';
		}

		wp_update_post( $post_update );

		$post_status = get_post_status( $post_id );
		$post        = get_post( $post_id );
		$post_title  = get_the_title( $post_id );
		$post_type   = $post ? $post->post_type : get_post_type( $post_id );
		$permalink   = 'dsf_layout' !== $post_type ? get_permalink( $post_id ) : '';
		$preview_url = 'dsf_layout' !== $post_type ? get_preview_post_link( $post_id ) : '';

		wp_send_json_success(
			array(
				'message'     => 'Page saved successfully',
				'post_id'     => $post_id,
				'post_status' => $post_status,
				'post_title'  => $post_title,
				'post_name'   => $post ? $post->post_name : '',
				'post_parent' => $post ? (int) $post->post_parent : 0,
				'permalink'   => $permalink,
				'preview_url' => $preview_url,
			)
		);
	}

	/**
	 * Sanitize settings for blocks with dedicated save-time contracts.
	 *
	 * @param array $blocks Saved page blocks.
	 * @return array
	 */
	private function sanitize_known_block_settings( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		foreach ( $blocks as &$block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( in_array( $block['type'] ?? '', array( 'landing-progress-header', 'landing-hero', 'landing-block-explorer', 'landing-product-story', 'landing-trust-workflow', 'landing-engagement-suite', 'landing-marketing-footer' ), true ) ) {
				$block['settings'] = $this->sanitize_landing_block_settings( $block['type'], $block['settings'] ?? array() );
				continue;
			}
			if ( 'faq' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_faq_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'text-image' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_text_image_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'countdown' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_countdown_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'expander-hero' === ( $block['type'] ?? '' ) ) {
				$block['settings'] = $this->sanitize_expander_hero_settings( $block['settings'] ?? array() );
				continue;
			}
			if ( 'header-showcase-mega' !== ( $block['type'] ?? '' ) ) {
				continue;
			}
			$settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$text_keys = array( 'promoText', 'logoText', 'logoAlt', 'specialButtonText', 'mobileLocationsLabel', 'mobileCallLabel' );
			$url_keys  = array( 'promoUrl', 'homeUrl', 'specialButtonUrl', 'searchUrl' );
			foreach ( $text_keys as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$settings[ $key ] = sanitize_text_field( $settings[ $key ] );
				}
			}
			foreach ( $url_keys as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$settings[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] );
				}
			}
			foreach ( array( 'utilityBackground', 'utilityTextColor', 'navBackground', 'navTextColor', 'accentColor', 'panelBackground', 'panelTextColor', 'mobileBackground', 'mobileTextColor' ) as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$color             = sanitize_hex_color( $settings[ $key ] );
					$settings[ $key ] = $color ? $color : '';
				}
			}
			if ( isset( $settings['logoWidth'] ) ) {
				$settings['logoWidth'] = max( 80, min( 380, absint( $settings['logoWidth'] ) ) );
			}
			if ( isset( $settings['mobileShowSearch'] ) ) {
				$settings['mobileShowSearch'] = (bool) $settings['mobileShowSearch'];
			}
			if ( isset( $settings['logoImage'] ) ) {
				$settings['logoImage'] = esc_url_raw( $settings['logoImage'], array( 'http', 'https' ) );
			}
			if ( isset( $settings['navigation'] ) ) {
				$settings['navigation'] = $this->sanitize_showcase_navigation( $settings['navigation'] );
			}
			$block['settings'] = $settings;
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Sanitize the bounded text, URL, enum, and layout contract for landing blocks.
	 *
	 * @param string $type Block type.
	 * @param array  $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_landing_block_settings( $type, $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'paddingX' => max( 0, min( 80, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'  => max( 0, min( 80, absint( $settings['marginY'] ?? 0 ) ) ),
		);
		foreach ( array( 'backgroundColor', 'textColor', 'accentColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		if ( 'landing-progress-header' === $type ) {
			$clean['variant']          = in_array( $settings['variant'] ?? '', array( 'progress', 'minimal', 'centered', 'transparent' ), true ) ? $settings['variant'] : 'progress';
			$clean['showAnnouncement'] = ! empty( $settings['showAnnouncement'] );
			foreach ( array( 'brandText', 'announcementText', 'announcementLinkText', 'docsText', 'ctaText' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'announcementUrl', 'homeUrl', 'docsUrl', 'ctaUrl' ) as $key ) {
				$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
			}
			$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
			$clean['navLinks']  = $this->sanitize_landing_links( $settings['navLinks'] ?? array(), 10 );
			return $clean;
		}

		if ( 'landing-hero' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'primaryText', 'secondaryText', 'note' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description']  = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['primaryUrl']   = $this->sanitize_showcase_url( $settings['primaryUrl'] ?? '' );
			$clean['secondaryUrl'] = $this->sanitize_showcase_url( $settings['secondaryUrl'] ?? '' );
			$clean['align']         = in_array( $settings['align'] ?? '', array( 'left', 'center' ), true ) ? $settings['align'] : 'left';
			$clean['mediaPosition'] = in_array( $settings['mediaPosition'] ?? '', array( 'right', 'left' ), true ) ? $settings['mediaPosition'] : 'right';

			$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, 'media' ) );
			return $clean;
		}

		if ( 'landing-block-explorer' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'footnote' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['items']       = $this->sanitize_landing_gallery_items( $settings['items'] ?? array() );
			return $clean;
		}

		if ( 'landing-product-story' === $type ) {
			$clean['variant']       = in_array( $settings['variant'] ?? '', array( 'editor', 'theme', 'commerce', 'layouts', 'campaigns' ), true ) ? $settings['variant'] : 'editor';
			$clean['reverseLayout'] = ! empty( $settings['reverseLayout'] );
			foreach ( array( 'eyebrow', 'title', 'featureOne', 'featureTwo', 'featureThree' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );

			$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, 'media' ) );
			return $clean;
		}

		if ( 'landing-trust-workflow' === $type ) {
			$clean['variant'] = in_array( $settings['variant'] ?? '', array( 'seo', 'security', 'audience', 'workflow' ), true ) ? $settings['variant'] : 'seo';
			$clean['layout']  = in_array( $settings['layout'] ?? '', array( '', 'pipeline', 'grid-dark', 'grid-light', 'numbered' ), true ) ? $settings['layout'] : '';
			foreach ( array( 'eyebrow', 'title', 'caption' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			$clean['description'] = sanitize_textarea_field( $settings['description'] ?? '' );
			$clean['items']       = $this->sanitize_landing_icon_items( $settings['items'] ?? array() );
			return $clean;
		}

		if ( 'landing-engagement-suite' === $type ) {
			foreach ( array( 'eyebrow', 'title', 'formsLabel', 'formsTitle', 'popupLabel', 'popupTitle', 'notificationLabel', 'notificationTitle' ) as $key ) {
				$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'description', 'formsDescription', 'formsBullets', 'popupDescription', 'notificationDescription' ) as $key ) {
				$clean[ $key ] = sanitize_textarea_field( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'formsIcon', 'popupIcon', 'notificationIcon' ) as $key ) {
				$clean[ $key ] = $this->sanitize_landing_icon( $settings[ $key ] ?? '' );
			}
			foreach ( array( 'forms', 'popup', 'notification' ) as $prefix ) {
				$clean = array_merge( $clean, $this->sanitize_landing_media( $settings, $prefix ) );
			}
			return $clean;
		}

		$clean['variant'] = in_array( $settings['variant'] ?? '', array( 'bigcta', 'centered', 'simple', 'columns' ), true ) ? $settings['variant'] : 'bigcta';
		foreach ( array( 'eyebrow', 'title', 'primaryText', 'secondaryText', 'brandText', 'col1Title', 'col2Title', 'col3Title', 'copyright', 'tagline' ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $settings[ $key ] ?? '' );
		}
		foreach ( array( 'description', 'brandStatement' ) as $key ) {
			$clean[ $key ] = sanitize_textarea_field( $settings[ $key ] ?? '' );
		}
		foreach ( array( 'primaryUrl', 'secondaryUrl', 'homeUrl', 'docsUrl' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $settings[ $key ] ?? '' );
		}
		$clean['logoImage'] = esc_url_raw( $settings['logoImage'] ?? '', array( 'http', 'https' ) );
		foreach ( array( 1, 2, 3 ) as $column ) {
			$clean[ 'col' . $column . 'Links' ] = $this->sanitize_landing_links( $settings[ 'col' . $column . 'Links' ] ?? array(), 10 );
		}

		return $clean;
	}

	/**
	 * Sanitize one landing block media control group.
	 *
	 * @param array  $settings Submitted settings.
	 * @param string $prefix Setting key prefix.
	 * @return array
	 */
	private function sanitize_landing_media( $settings, $prefix ) {
		$type_key  = $prefix . 'Type';
		$image_key = $prefix . 'Image';
		$video_key = $prefix . 'Video';
		$type      = in_array( $settings[ $type_key ] ?? '', array( 'mockup', 'image', 'video' ), true ) ? $settings[ $type_key ] : 'mockup';

		return array(
			$type_key  => $type,
			$image_key => esc_url_raw( $settings[ $image_key ] ?? '', array( 'http', 'https' ) ),
			$video_key => esc_url_raw( $settings[ $video_key ] ?? '', array( 'http', 'https' ) ),
		);
	}

	/**
	 * Sanitize a bounded list of label and URL pairs.
	 *
	 * @param mixed $links Submitted links.
	 * @param int   $limit Maximum links.
	 * @return array
	 */
	private function sanitize_landing_links( $links, $limit ) {
		$clean = array();
		foreach ( array_slice( is_array( $links ) ? $links : array(), 0, $limit ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$clean[] = array(
				'label' => sanitize_text_field( $link['label'] ?? '' ),
				'url'   => $this->sanitize_showcase_url( $link['url'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Sanitize editable carousel cards.
	 *
	 * @param mixed $items Submitted cards.
	 * @return array
	 */
	private function sanitize_landing_gallery_items( $items ) {
		$kinds = array( 'hero', 'bento', 'spotlight', 'duo', 'expander', 'content', 'faq', 'text-image', 'features', 'testimonials', 'countdown', 'pricing', 'product-grid', 'featured-promo', 'cta-banner', 'form', 'mega-menu', 'footer', 'generic' );
		$clean = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 24 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean[] = array(
				'category'    => sanitize_text_field( $item['category'] ?? '' ),
				'title'       => sanitize_text_field( $item['title'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'image'       => esc_url_raw( $item['image'] ?? '', array( 'http', 'https' ) ),
				'kind'        => in_array( $item['kind'] ?? '', $kinds, true ) ? $item['kind'] : 'generic',
				'url'         => $this->sanitize_showcase_url( $item['url'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Sanitize editable icon cards.
	 *
	 * @param mixed $items Submitted cards.
	 * @return array
	 */
	private function sanitize_landing_icon_items( $items ) {
		$clean = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 8 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean[] = array(
				'icon'        => $this->sanitize_landing_icon( $item['icon'] ?? '' ),
				'title'       => sanitize_text_field( $item['title'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'note'        => sanitize_text_field( $item['note'] ?? '' ),
			);
		}
		return $clean;
	}

	/**
	 * Allow only icons supported by the landing icon renderer.
	 *
	 * @param mixed $icon Submitted icon name.
	 * @return string
	 */
	private function sanitize_landing_icon( $icon ) {
		$allowed = array( 'sparkles', 'shield-check', 'lock', 'fingerprint', 'code', 'paintbrush', 'palette', 'layers', 'layout', 'columns', 'grid', 'briefcase', 'store', 'users', 'mail', 'form-input', 'bell', 'megaphone', 'clock', 'calendar', 'search', 'filter', 'zap', 'rocket', 'check', 'star', 'heart', 'globe', 'monitor', 'smartphone', 'file-text', 'settings', 'mouse-pointer', 'panel-top', 'wand', 'gauge', 'boxes' );
		return in_array( $icon, $allowed, true ) ? $icon : 'sparkles';
	}

	/**
	 * Sanitize FAQ rich answers, colors, and bounded layout settings.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	private function sanitize_faq_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'title'    => sanitize_text_field( $settings['title'] ?? '' ),
			'items'    => array(),
			'maxWidth' => max( 600, min( 1200, absint( $settings['maxWidth'] ?? 900 ) ) ),
			'padding'  => max( 20, min( 160, absint( $settings['padding'] ?? 80 ) ) ),
			'paddingX' => max( 0, min( 120, absint( $settings['paddingX'] ?? 24 ) ) ),
			'marginY'  => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array_slice( is_array( $settings['items'] ?? null ) ? $settings['items'] : array(), 0, 12 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$clean['items'][] = array(
				'question' => sanitize_text_field( $item['question'] ?? '' ),
				'answer'   => wp_kses_post( $item['answer'] ?? '' ),
			);
		}

		foreach ( array( 'backgroundColor', 'titleColor', 'questionColor', 'answerColor', 'dividerColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Sanitize the Text & Image settings, including responsive dimensions.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_text_image_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'title'                  => sanitize_text_field( $settings['title'] ?? '' ),
			'content'                => sanitize_textarea_field( $settings['content'] ?? '' ),
			'descriptionSize'        => 'normal' === ( $settings['descriptionSize'] ?? '' ) ? 'normal' : 'large',
			'showButton'             => ! empty( $settings['showButton'] ),
			'buttonText'             => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonUrl'              => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'buttonAction'           => 'modal' === ( $settings['buttonAction'] ?? '' ) ? 'modal' : 'link',
			'buttonModalLayout'      => 'drawer' === ( $settings['buttonModalLayout'] ?? '' ) ? 'drawer' : 'center',
			'buttonModalContentType' => in_array( $settings['buttonModalContentType'] ?? '', array( 'wysiwyg', 'html', 'shortcode' ), true ) ? $settings['buttonModalContentType'] : 'wysiwyg',
			'buttonModalContent'     => wp_kses_post( $settings['buttonModalContent'] ?? '' ),
			'buttonModalHtml'        => wp_kses_post( $settings['buttonModalHtml'] ?? '' ),
			'buttonModalShortcode'   => sanitize_text_field( $settings['buttonModalShortcode'] ?? '' ),
			'image'                  => esc_url_raw( $settings['image'] ?? '', array( 'http', 'https' ) ),
			'imagePosition'          => 'left' === ( $settings['imagePosition'] ?? '' ) ? 'left' : 'right',
			'height'                 => max( 100, min( 800, absint( $settings['height'] ?? 400 ) ) ),
			'padding'                => max( 0, min( 120, absint( $settings['padding'] ?? 60 ) ) ),
			'paddingX'               => max( 0, min( 100, absint( $settings['paddingX'] ?? 20 ) ) ),
			'marginY'                => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'backgroundColor', 'titleColor', 'textColor', 'buttonColor', 'buttonTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array(
				'height'   => max( 100, min( 800, absint( $values['height'] ?? $clean['height'] ) ) ),
				'padding'  => max( 0, min( 120, absint( $values['padding'] ?? $clean['padding'] ) ) ),
				'paddingX' => max( 0, min( 100, absint( $values['paddingX'] ?? $clean['paddingX'] ) ) ),
				'marginY'  => max( 0, min( 100, absint( $values['marginY'] ?? $clean['marginY'] ) ) ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize Countdown content, media, deadline, and CTA settings.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_countdown_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'eyebrow'               => sanitize_text_field( $settings['eyebrow'] ?? '' ),
			'title'                 => sanitize_text_field( $settings['title'] ?? '' ),
			'description'           => sanitize_textarea_field( $settings['description'] ?? '' ),
			'buttonText'            => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonAction'          => 'modal' === ( $settings['buttonAction'] ?? '' ) ? 'modal' : 'link',
			'buttonUrl'             => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'buttonModalLayout'     => 'drawer' === ( $settings['buttonModalLayout'] ?? '' ) ? 'drawer' : 'center',
			'buttonModalContentType' => in_array( $settings['buttonModalContentType'] ?? '', array( 'wysiwyg', 'html', 'shortcode' ), true ) ? $settings['buttonModalContentType'] : 'wysiwyg',
			'buttonModalContent'    => wp_kses_post( $settings['buttonModalContent'] ?? '' ),
			'buttonModalHtml'       => wp_kses_post( $settings['buttonModalHtml'] ?? '' ),
			'buttonModalShortcode'  => sanitize_text_field( $settings['buttonModalShortcode'] ?? '' ),
			'targetDate'            => $this->sanitize_countdown_datetime( $settings['targetDate'] ?? '' ),
			'expiredMessage'        => sanitize_text_field( $settings['expiredMessage'] ?? '' ),
			'noticeText'            => sanitize_text_field( $settings['noticeText'] ?? '' ),
			'mediaType'             => 'video' === ( $settings['mediaType'] ?? '' ) ? 'video' : 'image',
			'image'                 => esc_url_raw( $settings['image'] ?? '', array( 'http', 'https' ) ),
			'video'                 => esc_url_raw( $settings['video'] ?? '', array( 'http', 'https' ) ),
			'mediaPosition'         => 'left' === ( $settings['mediaPosition'] ?? '' ) ? 'left' : 'right',
			'padding'               => max( 20, min( 160, absint( $settings['padding'] ?? 64 ) ) ),
			'paddingX'              => max( 0, min( 140, absint( $settings['paddingX'] ?? 40 ) ) ),
			'gap'                   => max( 16, min( 120, absint( $settings['gap'] ?? 56 ) ) ),
			'marginY'               => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array( 'backgroundColor', 'textColor', 'accentColor', 'buttonColor', 'buttonTextColor', 'noticeColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array(
				'padding'  => max( 20, min( 160, absint( $values['padding'] ?? $clean['padding'] ) ) ),
				'paddingX' => max( 0, min( 140, absint( $values['paddingX'] ?? $clean['paddingX'] ) ) ),
				'gap'      => max( 16, min( 120, absint( $values['gap'] ?? $clean['gap'] ) ) ),
				'marginY'  => max( 0, min( 100, absint( $values['marginY'] ?? $clean['marginY'] ) ) ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize Expander Hero content, links, layout options, and dimensions.
	 *
	 * @param array $settings Submitted block settings.
	 * @return array
	 */
	private function sanitize_expander_hero_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array(
			'layoutStyle' => 'row' === ( $settings['layoutStyle'] ?? '' ) ? 'row' : 'split-bar',
			'barPosition' => in_array( $settings['barPosition'] ?? '', array( 'top', 'bottom' ), true ) ? $settings['barPosition'] : 'middle',
			'cards'       => array(),
			'barTitle'    => sanitize_text_field( $settings['barTitle'] ?? '' ),
			'showButton'  => ! empty( $settings['showButton'] ),
			'buttonText'  => sanitize_text_field( $settings['buttonText'] ?? '' ),
			'buttonUrl'   => $this->sanitize_showcase_url( $settings['buttonUrl'] ?? '' ),
			'cardHeight'  => max( 160, min( 520, absint( $settings['cardHeight'] ?? 280 ) ) ),
			'barHeight'   => max( 70, min( 220, absint( $settings['barHeight'] ?? 110 ) ) ),
			'gap'         => max( 0, min( 48, absint( $settings['gap'] ?? 16 ) ) ),
			'paddingX'    => max( 0, min( 80, absint( $settings['paddingX'] ?? 0 ) ) ),
			'marginY'     => max( 0, min( 100, absint( $settings['marginY'] ?? 25 ) ) ),
		);

		foreach ( array_slice( is_array( $settings['cards'] ?? null ) ? $settings['cards'] : array(), 0, 6 ) as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$clean['cards'][] = array(
				'title' => sanitize_text_field( $card['title'] ?? '' ),
				'image' => esc_url_raw( $card['image'] ?? '', array( 'http', 'https' ) ),
				'url'   => $this->sanitize_showcase_url( $card['url'] ?? '' ),
			);
		}

		foreach ( array( 'barColor', 'barTextColor', 'buttonColor', 'buttonTextColor', 'cardTextColor' ) as $key ) {
			$color         = sanitize_hex_color( $settings[ $key ] ?? '' );
			$clean[ $key ] = $color ? $color : '';
		}

		if ( isset( $settings['height'] ) ) {
			$clean['height'] = max( 200, min( 1000, absint( $settings['height'] ) ) );
		}

		$clean['responsive'] = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $breakpoint ) {
			$values = is_array( $settings['responsive'][ $breakpoint ] ?? null ) ? $settings['responsive'][ $breakpoint ] : array();
			$clean['responsive'][ $breakpoint ] = array();
			if ( isset( $values['height'] ) ) {
				$clean['responsive'][ $breakpoint ]['height'] = max( 200, min( 1000, absint( $values['height'] ) ) );
			}
			if ( isset( $values['gap'] ) ) {
				$clean['responsive'][ $breakpoint ]['gap'] = max( 0, min( 48, absint( $values['gap'] ) ) );
			}
			if ( isset( $values['paddingX'] ) ) {
				$clean['responsive'][ $breakpoint ]['paddingX'] = max( 0, min( 80, absint( $values['paddingX'] ) ) );
			}
			if ( isset( $values['marginY'] ) ) {
				$clean['responsive'][ $breakpoint ]['marginY'] = max( 0, min( 100, absint( $values['marginY'] ) ) );
			}
		}

		return $clean;
	}

	/**
	 * Validate a local calendar date and time from datetime-local.
	 *
	 * @param mixed $value Submitted datetime value.
	 * @return string
	 */
	private function sanitize_countdown_datetime( $value ) {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches ) ) {
			return '';
		}
		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return '';
		}
		if ( (int) $matches[4] > 23 || (int) $matches[5] > 59 || ( isset( $matches[6] ) && (int) $matches[6] > 59 ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Sanitize and cap nested showcase navigation collections.
	 *
	 * @param array $navigation Navigation configuration.
	 * @return array
	 */
	private function sanitize_showcase_navigation( $navigation ) {
		$navigation = is_array( $navigation ) ? $navigation : array();
		$clean      = array( 'utility' => array(), 'menu' => array(), 'locations' => array(), 'calls' => array() );
		$kinds      = array( 'link', 'dropdown', 'mega', 'locations', 'calls' );
		$icons      = array( 'settings', 'book', 'map-pin', 'phone' );

		foreach ( array_slice( is_array( $navigation['utility'] ?? null ) ? $navigation['utility'] : array(), 0, 4 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$kind  = in_array( $item['kind'] ?? '', $kinds, true ) ? $item['kind'] : 'link';
			$icon  = in_array( $item['icon'] ?? '', $icons, true ) ? $item['icon'] : 'settings';
			$links = array();
			foreach ( array_slice( is_array( $item['links'] ?? null ) ? $item['links'] : array(), 0, 6 ) as $link ) {
				if ( is_array( $link ) ) {
					$links[] = array( 'label' => sanitize_text_field( $link['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $link['url'] ?? '' ) );
				}
			}
			$clean['utility'][] = array( 'label' => sanitize_text_field( $item['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $item['url'] ?? '' ), 'icon' => $icon, 'kind' => $kind, 'links' => $links, 'panel' => $this->sanitize_showcase_panel( $item['panel'] ?? array() ) );
		}

		foreach ( array_slice( is_array( $navigation['menu'] ?? null ) ? $navigation['menu'] : array(), 0, 8 ) as $item ) {
			if ( is_array( $item ) ) {
				$clean['menu'][] = array( 'label' => sanitize_text_field( $item['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $item['url'] ?? '' ), 'hasMega' => ! empty( $item['hasMega'] ), 'panel' => $this->sanitize_showcase_panel( $item['panel'] ?? array() ) );
			}
		}

		foreach ( array_slice( is_array( $navigation['locations'] ?? null ) ? $navigation['locations'] : array(), 0, 6 ) as $location ) {
			if ( is_array( $location ) ) {
				$clean['locations'][] = array( 'name' => sanitize_text_field( $location['name'] ?? '' ), 'image' => esc_url_raw( $location['image'] ?? '', array( 'http', 'https' ) ), 'address' => sanitize_textarea_field( $location['address'] ?? '' ), 'hours' => sanitize_textarea_field( $location['hours'] ?? '' ), 'phone' => sanitize_text_field( $location['phone'] ?? '' ), 'phoneUrl' => $this->sanitize_showcase_url( $location['phoneUrl'] ?? '' ), 'directionsUrl' => $this->sanitize_showcase_url( $location['directionsUrl'] ?? '' ) );
			}
		}

		foreach ( array_slice( is_array( $navigation['calls'] ?? null ) ? $navigation['calls'] : array(), 0, 8 ) as $call ) {
			if ( is_array( $call ) ) {
				$clean['calls'][] = array( 'label' => sanitize_text_field( $call['label'] ?? '' ), 'url' => $this->sanitize_showcase_url( $call['url'] ?? '' ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize one editorial mega panel.
	 *
	 * @param array $panel Panel configuration.
	 * @return array
	 */
	private function sanitize_showcase_panel( $panel ) {
		$panel = is_array( $panel ) ? $panel : array();
		$clean = array();
		foreach ( array( 'introTitle', 'buttonText', 'accentText', 'promoTitle', 'promoSubtitle' ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $panel[ $key ] ?? '' );
		}
		$clean['introText']  = sanitize_textarea_field( $panel['introText'] ?? '' );
		$clean['promoImage'] = esc_url_raw( $panel['promoImage'] ?? '', array( 'http', 'https' ) );
		foreach ( array( 'buttonUrl', 'accentUrl', 'promoUrl' ) as $key ) {
			$clean[ $key ] = $this->sanitize_showcase_url( $panel[ $key ] ?? '' );
		}
		$clean['cards'] = array();
		foreach ( array_slice( is_array( $panel['cards'] ?? null ) ? $panel['cards'] : array(), 0, 6 ) as $card ) {
			if ( is_array( $card ) ) {
				$clean['cards'][] = array( 'eyebrow' => sanitize_text_field( $card['eyebrow'] ?? '' ), 'title' => sanitize_text_field( $card['title'] ?? '' ), 'url' => $this->sanitize_showcase_url( $card['url'] ?? '' ), 'image' => esc_url_raw( $card['image'] ?? '', array( 'http', 'https' ) ) );
			}
		}
		return $clean;
	}

	/**
	 * Keep only public web and contact URL protocols.
	 *
	 * @param mixed $value Candidate URL.
	 * @return string
	 */
	private function sanitize_showcase_url( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '#' === $value || preg_match( '/^#[A-Za-z][A-Za-z0-9_:.-]*$/', $value ) ) {
			return $value;
		}
		if ( 0 === strpos( $value, '//' ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	private function sanitize_popup_settings( $popup ) {
		return DSF_Popup::sanitize_settings( $popup );
	}

	private function sanitize_page_theme_settings( $theme ) {
		$theme    = is_array( $theme ) ? $theme : array();
		$defaults = DSF_Frontend::get_default_theme_settings();
		$clean    = array();

		foreach ( array( 'primaryColor', 'secondaryColor', 'textColor', 'backgroundColor' ) as $key ) {
			$clean[ $key ] = sanitize_hex_color( $theme[ $key ] ?? '' ) ?: $defaults[ $key ];
		}
		$clean['headingFont'] = DSF_Frontend::sanitize_font_family( $theme['headingFont'] ?? $defaults['headingFont'] );
		$clean['bodyFont']    = DSF_Frontend::sanitize_font_family( $theme['bodyFont'] ?? $defaults['bodyFont'] );

		return $clean;
	}

	private function sanitize_page_layout_settings( $layout ) {
		$layout = is_array( $layout ) ? $layout : array();

		return array(
			'containerWidth'   => min( 1800, max( 1000, absint( $layout['containerWidth'] ?? 1800 ) ) ),
			'contentPadding'   => min( 64, absint( $layout['contentPadding'] ?? 10 ) ),
			'showHeader'       => ! isset( $layout['showHeader'] ) || (bool) $layout['showHeader'],
			'showFooter'       => ! isset( $layout['showFooter'] ) || (bool) $layout['showFooter'],
			'headerTemplateId' => absint( $layout['headerTemplateId'] ?? 0 ),
			'footerTemplateId' => absint( $layout['footerTemplateId'] ?? 0 ),
			'template'         => 'fullwidth' === ( $layout['template'] ?? '' ) ? 'fullwidth' : 'default',
		);
	}

	private function get_valid_page_parent_id( $parent_id, $post_id ) {
		$parent_id = (int) $parent_id;
		$post_id   = (int) $post_id;

		if ( $parent_id <= 0 || $parent_id === $post_id ) {
			return 0;
		}

		if ( 'page' !== get_post_type( $parent_id ) ) {
			return 0;
		}

		$ancestor_ids = get_post_ancestors( $parent_id );
		if ( in_array( $post_id, array_map( 'intval', $ancestor_ids ), true ) ) {
			return 0;
		}

		return $parent_id;
	}

	private function sanitize_snapshot_html( $html ) {
		// Strip every HTML comment before sanitization. The snapshot is a
		// first-paint placeholder that Vue replaces on mount, so comments serve
		// no purpose — and a stray "-->" inside user WYSIWYG content can
		// prematurely close a Vue placeholder comment, leaking unclosed tags
		// that corrupt the DOM tree and produce phantom unstyled duplicates
		// of the last blocks after #dsf-frontend-app is closed early.
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', (string) $html );

		$allowed = wp_kses_allowed_html( 'post' );
		$extra   = array(
			'svg'      => array(
				'class'           => true,
				'viewBox'         => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'role'            => true,
				'data-*'          => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'data-*'          => true,
			),
			'circle'   => array(
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'rect'     => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'line'     => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
				'data-*' => true,
			),
			'polyline' => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'polygon'  => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'g'        => array(
				'class'  => true,
				'fill'   => true,
				'stroke' => true,
				'data-*' => true,
			),
			'div'      => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'section'  => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'span'     => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'p'        => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h1'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h2'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h3'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h4'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h5'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'h6'       => array(
				'class'  => true,
				'style'  => true,
				'id'     => true,
				'data-*' => true,
			),
			'a'        => array(
				'class'      => true,
				'style'      => true,
				'href'       => true,
				'target'     => true,
				'rel'        => true,
				'aria-label' => true,
				'data-*'     => true,
			),
			'img'      => array(
				'class'   => true,
				'style'   => true,
				'src'     => true,
				'alt'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
				'data-*'  => true,
			),
			'button'   => array(
				'class'      => true,
				'style'      => true,
				'type'       => true,
				'aria-label' => true,
				'data-*'     => true,
			),
			'input'    => array(
				'class'       => true,
				'style'       => true,
				'type'        => true,
				'value'       => true,
				'placeholder' => true,
				'name'        => true,
				'data-*'      => true,
			),
		);

		foreach ( $extra as $tag => $attrs ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ] = array_merge( $allowed[ $tag ], $attrs );
		}

		return wp_kses( $html, $allowed );
	}

	/**
	 * Render a shortcode for modal content (frontend)
	 */
	public function render_shortcode() {
		if ( ! check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$shortcode = isset( $_POST['shortcode'] ) ? wp_unslash( $_POST['shortcode'] ) : '';

		if ( ! $shortcode ) {
			wp_send_json_error( array( 'message' => 'Missing shortcode' ) );
		}

		$html = do_shortcode( $shortcode );
		$html = wp_kses_post( $html );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Get products by category or IDs (Hybrid Logic: Pinned First)
	 */
	public function get_products() {
		$editor_nonce_ok   = check_ajax_referer( 'dsf_editor_nonce', 'nonce', false );
		$frontend_nonce_ok = check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false );

		if ( ! $editor_nonce_ok && ! $frontend_nonce_ok ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( $editor_nonce_ok ) {
			$this->verify_permissions();
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$category_ids = isset( $_POST['category_ids'] ) ? $this->normalize_numeric_id_list( $_POST['category_ids'] ) : array();
		$source       = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : 'category';

		if ( empty( $category_ids ) && isset( $_POST['category_id'] ) ) {
			$category_ids = $this->normalize_numeric_id_list( $_POST['category_id'] );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? $this->normalize_numeric_id_list( $_POST['product_ids'] ) : array();

		$products = array();

		// If Manual Source OR we have Pinned products to show first
		if ( ! empty( $product_ids ) ) {
			$pinned_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'post__in'       => $product_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1, // Get all pinned
			);

			if ( 'manual' !== $source && ! empty( $category_ids ) ) {
				$pinned_args['tax_query'] = array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $category_ids,
						'include_children' => true,
					),
				);
			}

			$pinned_posts = get_posts( $pinned_args );

			// If Manual Source, we ONLY show pinned products (filtered by what exists)
			if ( 'manual' === $source ) {
				$products = $pinned_posts;
			} else {
				// If Category Source, Pinned products come first, then fill with category
				$products = $pinned_posts;
			}
		}

		// If Category Source fetch all remaining products
		if ( 'manual' !== $source ) {
			$seen_product_ids = array_map( 'intval', wp_list_pluck( $products, 'ID' ) );

			if ( empty( $category_ids ) ) {
				$cat_posts = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'post__not_in'   => $product_ids, // Exclude pinned to avoid duplicates
					)
				);

				$products = array_merge( $products, $cat_posts );
			} else {
				foreach ( $category_ids as $category_id ) {
					$cat_posts = get_posts(
						array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'post__not_in'   => array_values( array_unique( array_merge( $product_ids, $seen_product_ids ) ) ),
							'tax_query'      => array(
								array(
									'taxonomy'         => 'product_cat',
									'field'            => 'term_id',
									'terms'            => $category_id,
									'include_children' => true,
								),
							),
						)
					);

					if ( empty( $cat_posts ) ) {
						continue;
					}

					$products         = array_merge( $products, $cat_posts );
					$seen_product_ids = array_values(
						array_unique(
							array_merge(
								$seen_product_ids,
								array_map( 'intval', wp_list_pluck( $cat_posts, 'ID' ) )
							)
						)
					);
				}
			}
		}

		// Format Result
		$result = array();
		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			// Ensure Image URL
			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
			$price     = $product->get_price();
			$regular   = $product->get_regular_price();
			$sale      = $product->get_sale_price();

			$price_display   = '' !== $price ? html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) ) : '';
			$regular_display = '' !== $regular ? html_entity_decode( wp_strip_all_tags( wc_price( $regular ) ) ) : '';
			$sale_display    = '' !== $sale ? html_entity_decode( wp_strip_all_tags( wc_price( $sale ) ) ) : '';

			$cat_terms    = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			$cat_term_ids = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			$tag_terms    = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );

			$result[] = array(
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'price'           => $price_display,
				'regularPrice'    => $regular_display,
				'salePrice'       => $sale_display,
				'price_html'      => $product->get_price_html(),
				'regular_price'   => $regular_display,
				'sale_price'      => $sale_display,
				'image'           => $image_url,
				'permalink'       => $product->get_permalink(),
				'add_to_cart_url' => $product->add_to_cart_url(),
				'product_type'    => $product->get_type(),
				'stock_status'    => $product->get_stock_status(),
				'price_num'       => (float) $product->get_price(),
				'rating'          => round( (float) $product->get_average_rating(), 1 ),
				'categories'      => is_wp_error( $cat_terms ) ? array() : $cat_terms,
				'category_ids'    => is_wp_error( $cat_term_ids ) ? array() : array_map( 'intval', (array) $cat_term_ids ),
				'tags'            => is_wp_error( $tag_terms ) ? array() : $tag_terms,
				'attributes'      => $this->get_product_filter_attributes( $product ),
			);
		}

		wp_send_json_success( array( 'products' => $result ) );
	}

	/**
	 * Build a normalized attribute map for client-side filtering.
	 * Keys are lowercase attribute labels (e.g. "brand", "material", "color").
	 *
	 * @param WC_Product $product
	 * @return array<string, string[]>
	 */
	private function get_product_filter_attributes( $product ) {
		$attrs = array();

		// Standard WooCommerce product attributes (pa_* taxonomies + custom)
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$label  = wc_attribute_label( $attribute->get_name() );
				$terms  = wp_get_post_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
				$values = is_wp_error( $terms ) ? array() : (array) $terms;
			} else {
				$label  = $attribute->get_name();
				$values = array_map( 'trim', $attribute->get_options() );
			}

			if ( ! empty( $values ) ) {
				$key           = strtolower( str_replace( ' ', '_', $label ) );
				$attrs[ $key ] = array_values( array_filter( $values ) );
			}
		}

		// WooCommerce Brands plugin (product_brand taxonomy).
		// Merge into the 'brand' key so the Vue filter picks them up automatically.
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brand_terms = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
				$existing       = isset( $attrs['brand'] ) ? $attrs['brand'] : array();
				$attrs['brand'] = array_values( array_unique( array_merge( $existing, (array) $brand_terms ) ) );
			}
		}

		return $attrs;
	}

	/**
	 * Search products
	 */
	public function search_products() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$search       = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category_ids = isset( $_POST['category_ids'] ) ? $this->normalize_numeric_id_list( $_POST['category_ids'] ) : array();

		if ( empty( $category_ids ) && isset( $_POST['category_id'] ) ) {
			$category_ids = $this->normalize_numeric_id_list( $_POST['category_id'] );
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $search,
		);

		if ( ! empty( $category_ids ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $category_ids,
					'include_children' => true,
				),
			);
		}

		$products = get_posts( $args );
		$result   = array();

		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			$result[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => $product->get_price_html(),
				'image' => wp_get_attachment_url( $product->get_image_id() ),
			);
		}

		wp_send_json_success( array( 'products' => $result ) );
	}

	/**
	 * Get WooCommerce categories
	 */
	public function get_categories() {
		$editor_nonce_ok   = check_ajax_referer( 'dsf_editor_nonce', 'nonce', false );
		$frontend_nonce_ok = check_ajax_referer( 'dsf_frontend_nonce', 'nonce', false );

		if ( ! $editor_nonce_ok && ! $frontend_nonce_ok ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		if ( $editor_nonce_ok ) {
			$this->verify_permissions();
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $categories ) ) {
			wp_send_json_error( array( 'message' => $categories->get_error_message() ) );
		}

		$result = array_map(
			function ( $cat ) {
				$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );

				return array(
					'id'       => $cat->term_id,
					'name'     => $cat->name,
					'slug'     => $cat->slug,
					'url'      => get_term_link( $cat ),
					'count'    => $cat->count,
					'image'    => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '',
					'imageAlt' => $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
				);
			},
			$categories
		);

		wp_send_json_success( array( 'categories' => $result ) );
	}

	/**
	 * Handle image upload
	 */
	public function upload_image() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * Update page title
	 */
	public function update_title() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';

		if ( ! $post_id || ! $title ) {
			wp_send_json_error( array( 'message' => 'Invalid data' ) );
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);

		wp_send_json_success( array( 'message' => 'Title updated' ) );
	}

	/**
	 * Publish page
	 */
	public function publish_page() {
		if ( ! check_ajax_referer( 'dsf_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		$this->verify_permissions();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		wp_send_json_success(
			array(
				'message'   => 'Page published',
				'permalink' => get_permalink( $post_id ),
			)
		);
	}
}
