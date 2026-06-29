<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-import-export.php';

/**
 * Covers the import/export plumbing added for saved blocks and media sideloading:
 * which meta keys travel per post type, and which URLs are treated as media that
 * should be pulled into the destination Media Library on import.
 */
class Test_DSF_Import_Export extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction(
			'wp_parse_url',
			array(
				'return' => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_saved_block_meta_keys() {
		$this->assertSame(
			array( '_dsf_block_type', '_dsf_block_settings' ),
			$this->invoke( 'get_meta_keys_for_type', 'dsf_saved_block' )
		);
	}

	public function test_template_meta_keys() {
		$this->assertSame(
			array( '_dsf_template_blocks', '_dsf_template_theme', '_dsf_template_kind' ),
			$this->invoke( 'get_meta_keys_for_type', 'dsf_template' )
		);
	}

	public function test_page_and_layout_meta_keys_unchanged() {
		$this->assertSame(
			array( '_dsf_blocks', '_dsf_settings', '_dsf_theme_colors' ),
			$this->invoke( 'get_meta_keys_for_type', 'page' )
		);
		$this->assertSame(
			array( '_dsf_blocks', '_dsf_settings', '_dsf_layout_type' ),
			$this->invoke( 'get_meta_keys_for_type', 'dsf_layout' )
		);
		$this->assertSame( array(), $this->invoke( 'get_meta_keys_for_type', 'comment' ) );
	}

	/**
	 * @dataProvider media_url_provider
	 */
	public function test_media_url_detection( $url, $expected ) {
		$this->assertSame( $expected, $this->invoke( 'looks_like_media_url', $url ) );
	}

	public function media_url_provider() {
		return array(
			'image extension'      => array( 'https://example.com/photo.jpg', true ),
			'video extension'      => array( 'https://cdn.example.com/promo.mp4', true ),
			'uploads without ext'  => array( 'https://example.com/wp-content/uploads/2024/01/file', true ),
			'plain page'           => array( 'https://example.com/about', false ),
			'relative path'        => array( '/images/photo.jpg', false ),
			'anchor'               => array( '#section', false ),
			'mailto'               => array( 'mailto:hi@example.com', false ),
		);
	}

	/**
	 * @dataProvider ip_provider
	 */
	public function test_public_ip_classification( $ip, $expected ) {
		$this->assertSame( $expected, $this->invoke( 'is_public_ip', $ip ) );
	}

	public function ip_provider() {
		return array(
			'public v4'        => array( '8.8.8.8', true ),
			'loopback'         => array( '127.0.0.1', false ),
			'private 10'       => array( '10.0.0.5', false ),
			'private 192'      => array( '192.168.1.10', false ),
			'link-local meta'  => array( '169.254.169.254', false ),
			'ipv6 loopback'    => array( '::1', false ),
		);
	}

	private function invoke( $method_name, ...$arguments ) {
		$reflection = new ReflectionClass( 'DSF_Import_Export' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $arguments );
	}
}
