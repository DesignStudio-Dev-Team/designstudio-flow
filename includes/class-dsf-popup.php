<?php
/**
 * Reusable popups for DesignStudio Flow.
 *
 * Popups are stored as a dedicated `dsf_popup` post type and referenced by id
 * from a page's `_dsf_settings['popupId']`. The popup is designed with the same
 * config form used previously for inline page popups (PopupSettingsFields.vue),
 * rendered here through a meta box that mounts the `popup-editor` Vue bundle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Popup {

	const POST_TYPE = 'dsf_popup';
	const META_KEY  = '_dsf_popup_settings';
	const NONCE     = 'dsf_popup_save';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 2 );
	}

	/**
	 * The popup editor bundle is an ES module (it imports a shared Vue chunk), so
	 * the script tag must declare type="module".
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function add_module_type_to_scripts( $tag, $handle ) {
		if ( in_array( $handle, array( 'dsf-popup-editor', 'dsf-popup-editor-vite' ), true ) && false === strpos( $tag, 'type="module"' ) ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Default popup configuration (without the per-page `enabled` flag).
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'         => false,
			'type'            => 'content',
			'headline'        => 'Limited time offer',
			'body'            => '<p>Add your popup message here.</p>',
			'image'           => '',
			'imageAlt'        => '',
			'imagePosition'   => 'top',
			'buttonText'      => 'Learn more',
			'buttonUrl'       => '#',
			'openNewTab'      => false,
			'width'           => 'medium',
			'position'        => 'center',
			'delaySeconds'    => 3,
			'startDate'       => '',
			'endDate'         => '',
			'cookieDuration'  => 24,
			'cookieUnit'      => 'hours',
			'showOverlay'     => true,
			'closeOnOverlay'  => true,
			'showClose'       => true,
			'backgroundColor' => '#FFFFFF',
			'textColor'       => '#1F2937',
			'accentColor'     => '#2C5F5D',
		);
	}

	/**
	 * Sanitize a popup settings array. Shared by the page-settings save path and
	 * the popup post type save handler so validation stays identical.
	 *
	 * @param array $popup Raw popup settings.
	 * @return array
	 */
	public static function sanitize_settings( $popup ) {
		$popup          = is_array( $popup ) ? $popup : array();
		$type           = isset( $popup['type'] ) ? sanitize_key( $popup['type'] ) : 'content';
		$image_position = isset( $popup['imagePosition'] ) ? sanitize_key( $popup['imagePosition'] ) : 'top';
		$width          = isset( $popup['width'] ) ? sanitize_key( $popup['width'] ) : 'medium';
		$position       = isset( $popup['position'] ) ? sanitize_key( $popup['position'] ) : 'center';
		$cookie_unit    = isset( $popup['cookieUnit'] ) ? sanitize_key( $popup['cookieUnit'] ) : 'hours';

		return array(
			'enabled'         => ! empty( $popup['enabled'] ),
			'type'            => in_array( $type, array( 'content', 'image' ), true ) ? $type : 'content',
			'headline'        => sanitize_text_field( $popup['headline'] ?? '' ),
			'body'            => wp_kses_post( $popup['body'] ?? '' ),
			'image'           => esc_url_raw( $popup['image'] ?? '' ),
			'imageAlt'        => sanitize_text_field( $popup['imageAlt'] ?? '' ),
			'imagePosition'   => in_array( $image_position, array( 'top', 'left', 'right' ), true ) ? $image_position : 'top',
			'buttonText'      => sanitize_text_field( $popup['buttonText'] ?? '' ),
			'buttonUrl'       => esc_url_raw( $popup['buttonUrl'] ?? '' ),
			'openNewTab'      => ! empty( $popup['openNewTab'] ),
			'width'           => in_array( $width, array( 'small', 'medium', 'large', 'wide' ), true ) ? $width : 'medium',
			'position'        => in_array( $position, array( 'center', 'bottom-right', 'bottom-left' ), true ) ? $position : 'center',
			'delaySeconds'    => min( 3600, max( 0, intval( $popup['delaySeconds'] ?? 0 ) ) ),
			'startDate'       => sanitize_text_field( $popup['startDate'] ?? '' ),
			'endDate'         => sanitize_text_field( $popup['endDate'] ?? '' ),
			'cookieDuration'  => min( 365, max( 0, intval( $popup['cookieDuration'] ?? 24 ) ) ),
			'cookieUnit'      => in_array( $cookie_unit, array( 'hours', 'days' ), true ) ? $cookie_unit : 'hours',
			'showOverlay'     => ! empty( $popup['showOverlay'] ),
			'closeOnOverlay'  => ! empty( $popup['closeOnOverlay'] ),
			'showClose'       => ! empty( $popup['showClose'] ),
			'backgroundColor' => sanitize_hex_color( $popup['backgroundColor'] ?? '' ) ?: '#FFFFFF',
			'textColor'       => sanitize_hex_color( $popup['textColor'] ?? '' ) ?: '#1F2937',
			'accentColor'     => sanitize_hex_color( $popup['accentColor'] ?? '' ) ?: '#2C5F5D',
		);
	}

	/**
	 * List reusable popups for the page-settings picker.
	 *
	 * @return array Array of { id, title, status }.
	 */
	public static function get_popup_list() {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$list = array();
		foreach ( $posts as $post ) {
			$list[] = array(
				'id'     => (int) $post->ID,
				/* translators: %d: popup id. */
				'title'  => $post->post_title ? $post->post_title : sprintf( __( 'Popup #%d', 'designstudio-flow' ), $post->ID ),
				'status' => $post->post_status,
			);
		}

		return $list;
	}

	/**
	 * Resolve the popup to render for a page: a referenced popup wins, otherwise
	 * fall back to the legacy inline popup for full backward compatibility.
	 *
	 * @param array $page_settings The page `_dsf_settings`.
	 * @return array Popup settings (possibly empty).
	 */
	public static function resolve_page_popup( $page_settings ) {
		$page_settings = is_array( $page_settings ) ? $page_settings : array();
		$popup_id      = isset( $page_settings['popupId'] ) ? absint( $page_settings['popupId'] ) : 0;

		if ( $popup_id ) {
			$post = get_post( $popup_id );
			if ( $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status ) {
				$settings = get_post_meta( $popup_id, self::META_KEY, true );
				if ( is_array( $settings ) ) {
					$settings            = self::sanitize_settings( $settings );
					$settings['enabled'] = true;
					$settings['popupId'] = $popup_id;
					return $settings;
				}
			}
		}

		return isset( $page_settings['popup'] ) && is_array( $page_settings['popup'] ) ? $page_settings['popup'] : array();
	}

	/**
	 * Register the popup design meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'dsf-popup-design',
			__( 'Popup Design', 'designstudio-flow' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box: a mount node plus a hidden field the Vue app writes to.
	 *
	 * @param WP_Post $post Current popup post.
	 */
	public function render_meta_box( $post ) {
		$settings = get_post_meta( $post->ID, self::META_KEY, true );
		$settings = is_array( $settings ) ? array_merge( self::get_defaults(), $settings ) : self::get_defaults();

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<input type="hidden" id="dsf-popup-settings-input" name="dsf_popup_settings" value="' . esc_attr( wp_json_encode( $settings ) ) . '" />';
		echo '<div id="dsf-popup-editor-app"></div>';
		echo '<noscript><p>' . esc_html__( 'JavaScript is required to design popups.', 'designstudio-flow' ) . '</p></noscript>';
	}

	/**
	 * Persist popup settings from the meta box.
	 *
	 * @param int     $post_id Popup post id.
	 * @param WP_Post $post    Popup post.
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = filter_input( INPUT_POST, self::NONCE . '_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_pages', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['dsf_popup_settings'] ) ) {
			return;
		}

		$raw     = wp_unslash( $_POST['dsf_popup_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded + sanitized below.
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_KEY, self::sanitize_settings( $decoded ) );
	}

	/**
	 * Enqueue the popup editor bundle on the popup edit screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;

		if ( $is_dev ) {
			wp_enqueue_script( 'dsf-popup-editor-vite', 'http://localhost:5173/@vite/client', array(), DSF_VERSION, true );
			wp_enqueue_script( 'dsf-popup-editor', 'http://localhost:5173/src/admin/popupEditor.js', array( 'dsf-popup-editor-vite' ), DSF_VERSION, true );
		} else {
			$main_css_version  = $this->asset_version( 'assets/css/main.css' );
			$popup_css_version = $this->asset_version( 'assets/css/popup-editor.css' );
			$js_version        = $this->asset_version( 'assets/js/popup-editor.js' );

			wp_enqueue_style( 'dsf-main', DSF_PLUGIN_URL . 'assets/css/main.css', array(), $main_css_version );
			if ( file_exists( DSF_PLUGIN_DIR . 'assets/css/popup-editor.css' ) ) {
				wp_enqueue_style( 'dsf-popup-editor', DSF_PLUGIN_URL . 'assets/css/popup-editor.css', array( 'dsf-main' ), $popup_css_version );
			}
			wp_enqueue_script( 'dsf-popup-editor', DSF_PLUGIN_URL . 'assets/js/popup-editor.js', array(), $js_version, true );
		}

		$post_id  = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$post_id  = $post_id ? intval( $post_id ) : 0;
		$settings = $post_id ? get_post_meta( $post_id, self::META_KEY, true ) : array();
		$settings = is_array( $settings ) ? array_merge( self::get_defaults(), $settings ) : self::get_defaults();

		wp_localize_script(
			'dsf-popup-editor',
			'dsfPopupEditorData',
			array(
				'settings'  => $settings,
				'pluginUrl' => DSF_PLUGIN_URL,
			)
		);
	}

	/**
	 * Cache-busting version string from file mtime.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string
	 */
	private function asset_version( $relative_path ) {
		$file = DSF_PLUGIN_DIR . $relative_path;
		return file_exists( $file ) ? (string) filemtime( $file ) : DSF_VERSION;
	}
}
