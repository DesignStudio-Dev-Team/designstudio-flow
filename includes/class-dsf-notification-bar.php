<?php
/**
 * Site-wide notification bar for DesignStudio Flow.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage global notification settings, assets, scheduling, and markup.
 */
class DSF_Notification_Bar {

	/**
	 * Singleton instance.
	 *
	 * @var DSF_Notification_Bar|null
	 */
	private static $instance = null;

	/**
	 * Whether markup has already rendered during this request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return DSF_Notification_Bar
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register frontend hooks. */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_body_open', array( $this, 'render_at_body_open' ), 1 );
		add_action( 'wp_footer', array( $this, 'render_footer_fallback' ), 1 );
	}

	/**
	 * Get safe defaults for a new notification bar.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'         => false,
			'message'         => 'Add your site-wide announcement here.',
			'linkText'        => '',
			'linkUrl'         => '#',
			'openNewTab'      => false,
			'dismissible'     => true,
			'cookieHours'     => 24,
			'sticky'          => false,
			'alignment'       => 'center',
			'startDate'       => '',
			'endDate'         => '',
			'backgroundColor' => '#2C5F5D',
			'textColor'       => '#FFFFFF',
			'linkColor'       => '#FFFFFF',
		);
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( 'dsf_notification_bar', array() );
		return array_merge( self::get_defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Sanitize the complete notification settings contract.
	 *
	 * @param array $settings Submitted settings.
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		$settings             = is_array( $settings ) ? $settings : array();
		$defaults             = self::get_defaults();
		$allowed_message_html = array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'br'     => array(),
			'em'     => array(),
			'span'   => array(),
			'strong' => array(),
		);

		$background = sanitize_hex_color( $settings['backgroundColor'] ?? '' );
		$text_color = sanitize_hex_color( $settings['textColor'] ?? '' );
		$link_color = sanitize_hex_color( $settings['linkColor'] ?? '' );

		return array(
			'enabled'         => ! empty( $settings['enabled'] ),
			'message'         => wp_kses( $settings['message'] ?? '', $allowed_message_html ),
			'linkText'        => sanitize_text_field( $settings['linkText'] ?? '' ),
			'linkUrl'         => self::sanitize_public_url( $settings['linkUrl'] ?? '' ),
			'openNewTab'      => ! empty( $settings['openNewTab'] ),
			'dismissible'     => ! empty( $settings['dismissible'] ),
			'cookieHours'     => max( 0, min( 8760, absint( $settings['cookieHours'] ?? 24 ) ) ),
			'sticky'          => ! empty( $settings['sticky'] ),
			'alignment'       => 'left' === ( $settings['alignment'] ?? '' ) ? 'left' : 'center',
			'startDate'       => self::sanitize_datetime( $settings['startDate'] ?? '' ),
			'endDate'         => self::sanitize_datetime( $settings['endDate'] ?? '' ),
			'backgroundColor' => $background ? $background : $defaults['backgroundColor'],
			'textColor'       => $text_color ? $text_color : $defaults['textColor'],
			'linkColor'       => $link_color ? $link_color : $defaults['linkColor'],
		);
	}

	/**
	 * Allow only public link and contact protocols.
	 *
	 * @param mixed $value Submitted URL.
	 * @return string
	 */
	private static function sanitize_public_url( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '#' === $value ) {
			return '#';
		}
		return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Validate a WordPress-local datetime input.
	 *
	 * @param mixed $value Submitted datetime.
	 * @return string
	 */
	private static function sanitize_datetime( $value ) {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/', $value, $matches ) ) {
			return '';
		}
		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return '';
		}
		if ( (int) $matches[4] > 23 || (int) $matches[5] > 59 ) {
			return '';
		}
		return $value;
	}

	/** Enqueue the minimal site-wide frontend assets when active. */
	public function enqueue_assets() {
		if ( ! $this->should_display() ) {
			return;
		}

		$is_dev = defined( 'DSF_DEV_MODE' ) && DSF_DEV_MODE;
		if ( $is_dev ) {
			wp_enqueue_script( 'dsf-frontend-vite', 'http://localhost:5173/@vite/client', array(), DSF_VERSION, true );
			wp_enqueue_script( 'dsf-notification-bar', 'http://localhost:5173/src/frontend/notificationBar.js', array( 'dsf-frontend-vite' ), DSF_VERSION, true );
			return;
		}

		wp_enqueue_style(
			'dsf-notification-bar',
			DSF_PLUGIN_URL . 'assets/css/notification-bar.css',
			array(),
			$this->asset_version( 'assets/css/notification-bar.css' )
		);
		wp_enqueue_script(
			'dsf-notification-bar',
			DSF_PLUGIN_URL . 'assets/js/notification-bar.js',
			array(),
			$this->asset_version( 'assets/js/notification-bar.js' ),
			true
		);
	}

	/** Render immediately after the opening body tag. */
	public function render_at_body_open() {
		$this->render( false );
	}

	/** Render a fallback for themes that omit wp_body_open(). */
	public function render_footer_fallback() {
		if ( ! $this->rendered ) {
			$this->render( true );
		}
	}

	/**
	 * Render sanitized notification markup once.
	 *
	 * @param bool $is_fallback Whether the markup is using the footer fallback.
	 */
	private function render( $is_fallback ) {
		if ( $this->rendered || ! $this->should_display() ) {
			return;
		}

		$settings    = self::get_settings();
		$cookie_name = 'dsf_notification_' . substr(
			md5(
				wp_json_encode(
					array(
						$settings['message'],
						$settings['linkText'],
						$settings['linkUrl'],
						$settings['startDate'],
						$settings['endDate'],
					)
				)
			),
			0,
			12
		);
		$classes     = array(
			'dsf-notification-bar',
			'dsf-notification-bar--' . $settings['alignment'],
		);
		if ( $settings['sticky'] ) {
			$classes[] = 'dsf-notification-bar--sticky';
		}

		$style = sprintf(
			'--dsf-notification-bg:%s;--dsf-notification-text:%s;--dsf-notification-link:%s;',
			esc_attr( $settings['backgroundColor'] ),
			esc_attr( $settings['textColor'] ),
			esc_attr( $settings['linkColor'] )
		);

		echo '<aside class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '" data-dsf-notification-bar data-cookie-name="' . esc_attr( $cookie_name ) . '" data-cookie-hours="' . absint( $settings['cookieHours'] ) . '"' . ( $is_fallback ? ' data-footer-fallback="true"' : '' ) . ' aria-label="' . esc_attr__( 'Site announcement', 'designstudio-flow' ) . '">';
		echo '<div class="dsf-notification-bar__inner">';
		echo '<div class="dsf-notification-bar__message">' . wp_kses_post( $settings['message'] ) . '</div>';
		if ( $settings['linkText'] && $settings['linkUrl'] ) {
			echo '<a class="dsf-notification-bar__link" href="' . esc_url( $settings['linkUrl'] ) . '"';
			if ( $settings['openNewTab'] ) {
				echo ' target="_blank" rel="noopener noreferrer"';
			}
			echo '>' . esc_html( $settings['linkText'] ) . '</a>';
		}
		if ( $settings['dismissible'] ) {
			echo '<button class="dsf-notification-bar__close" type="button" data-dsf-notification-close aria-label="' . esc_attr__( 'Dismiss announcement', 'designstudio-flow' ) . '"><span aria-hidden="true">&times;</span></button>';
		}
		echo '</div></aside>';

		$this->rendered = true;
	}

	/**
	 * Check enabled state and the active WordPress-local schedule.
	 *
	 * @return bool
	 */
	private function should_display() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return false;
		}

		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) || '' === trim( wp_strip_all_tags( $settings['message'] ) ) ) {
			return false;
		}

		$now   = current_datetime()->getTimestamp();
		$start = $this->datetime_timestamp( $settings['startDate'] );
		$end   = $this->datetime_timestamp( $settings['endDate'] );
		if ( $start && $now < $start ) {
			return false;
		}
		if ( $end && $now > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert a sanitized local datetime to a timestamp.
	 *
	 * @param string $value Local datetime.
	 * @return int
	 */
	private function datetime_timestamp( $value ) {
		if ( ! $value ) {
			return 0;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return $date instanceof DateTimeImmutable ? $date->getTimestamp() : 0;
	}

	/**
	 * Get a cache-busting asset version.
	 *
	 * @param string $relative_path Plugin-relative asset path.
	 * @return string
	 */
	private function asset_version( $relative_path ) {
		$path = DSF_PLUGIN_DIR . ltrim( $relative_path, '/' );
		return file_exists( $path ) ? (string) filemtime( $path ) : DSF_VERSION;
	}
}
