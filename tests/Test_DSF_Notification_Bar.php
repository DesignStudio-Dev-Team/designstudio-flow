<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-notification-bar.php';

class Test_DSF_Notification_Bar extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction(
			'sanitize_text_field',
			array(
				'return_arg' => 0,
			)
		);
		WP_Mock::userFunction(
			'wp_kses',
			array(
				'return_in_order' => array( 'Safe message' ),
			)
		);
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array(
				'return_in_order' => array( '#112233', null, '#445566' ),
			)
		);
		WP_Mock::userFunction(
			'esc_url_raw',
			array(
				'return_in_order' => array( '' ),
			)
		);
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_notification_datetime_accepts_valid_local_datetime() {
		$this->assertSame( '2026-08-15T14:30', $this->sanitize_datetime( '2026-08-15T14:30' ) );
	}

	public function test_notification_datetime_rejects_invalid_values() {
		$this->assertSame( '', $this->sanitize_datetime( '2026-02-30T14:30' ) );
		$this->assertSame( '', $this->sanitize_datetime( '2026-08-15T25:00' ) );
		$this->assertSame( '', $this->sanitize_datetime( 'javascript:alert(1)' ) );
	}

	public function test_notification_settings_are_allowlisted_and_bounded() {
		$clean = DSF_Notification_Bar::sanitize_settings(
			array(
				'enabled'         => true,
				'message'         => '<script>alert(1)</script>Safe message',
				'linkText'        => 'Shop now',
				'linkUrl'         => 'javascript:alert(1)',
				'cookieHours'     => 99999,
				'alignment'       => 'sideways',
				'backgroundColor' => '#112233',
				'textColor'       => 'bad-color',
				'linkColor'       => '#445566',
			)
		);

		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( 'Safe message', $clean['message'] );
		$this->assertSame( '', $clean['linkUrl'] );
		$this->assertSame( 8760, $clean['cookieHours'] );
		$this->assertSame( 'center', $clean['alignment'] );
		$this->assertSame( '#112233', $clean['backgroundColor'] );
		$this->assertSame( '#FFFFFF', $clean['textColor'] );
		$this->assertSame( '#445566', $clean['linkColor'] );
	}

	private function sanitize_datetime( $value ) {
		$method = new ReflectionMethod( 'DSF_Notification_Bar', 'sanitize_datetime' );
		$method->setAccessible( true );
		return $method->invoke( null, $value );
	}
}
