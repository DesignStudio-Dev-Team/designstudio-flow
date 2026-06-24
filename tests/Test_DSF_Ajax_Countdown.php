<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

class Test_DSF_Ajax_Countdown extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction(
			'sanitize_text_field',
			array(
				'return_arg' => 0,
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_countdown_datetime_accepts_calendar_date_and_time() {
		$this->assertSame( '2026-08-15T14:30', $this->sanitize_datetime( '2026-08-15T14:30' ) );
		$this->assertSame( '2026-08-15T14:30:45', $this->sanitize_datetime( '2026-08-15T14:30:45' ) );
	}

	public function test_countdown_datetime_rejects_impossible_or_malformed_values() {
		$this->assertSame( '', $this->sanitize_datetime( '2026-02-30T14:30' ) );
		$this->assertSame( '', $this->sanitize_datetime( '2026-08-15T25:00' ) );
		$this->assertSame( '', $this->sanitize_datetime( 'javascript:alert(1)' ) );
	}

	private function sanitize_datetime( $value ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_countdown_datetime' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $value );
	}
}
