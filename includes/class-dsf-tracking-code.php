<?php
/**
 * Site-wide tracking snippet output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DSF_Tracking_Code {
	const OPTION_KEY = 'dsf_tracking_code';
	const MAX_CODE_BYTES = 50000;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'output_head_code' ), 1 );
		add_action( 'wp_body_open', array( $this, 'output_body_code' ), 1 );
	}

	public static function get_settings() {
		$value = get_option( self::OPTION_KEY, array() );
		return self::sanitize_settings( $value, false );
	}

	/** Sanitize a trusted administrator's snippets without permitting PHP tags. */
	public static function sanitize_settings( $value, $require_capability = true ) {
		if ( $require_capability && ! current_user_can( 'unfiltered_html' ) ) {
			return array( 'head' => '', 'body' => '' );
		}

		$value = is_array( $value ) ? $value : array();
		return array(
			'head' => self::sanitize_code( $value['head'] ?? '' ),
			'body' => self::sanitize_code( $value['body'] ?? '' ),
		);
	}

	private static function sanitize_code( $code ) {
		$code = is_string( $code ) ? $code : '';
		if ( strlen( $code ) > self::MAX_CODE_BYTES ) {
			return '';
		}
		return preg_replace( '/<\?(?:php|=)?|\?>/i', '', $code );
	}

	public function output_head_code() {
		$this->output( 'head' );
	}

	public function output_body_code() {
		$this->output( 'body' );
	}

	private function output( $location ) {
		if ( is_admin() ) {
			return;
		}
		$settings = self::get_settings();
		$code     = $settings[ $location ] ?? '';
		if ( '' === $code ) {
			return;
		}
		// Intentionally unescaped: snippets are accepted only from users permitted
		// to publish unfiltered HTML, so GTM/pixel script tags can execute.
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
