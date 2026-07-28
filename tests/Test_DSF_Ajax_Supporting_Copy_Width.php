<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

class Test_DSF_Ajax_Supporting_Copy_Width extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_width_is_bounded_without_discarding_existing_settings() {
		$clean = $this->sanitize( array( 'title' => 'Hero', 'descriptionMaxWidth' => 9999 ), 800 );

		$this->assertSame( 'Hero', $clean['title'] );
		$this->assertSame( 1200, $clean['descriptionMaxWidth'] );
		$this->assertSame( 'auto', $clean['textAlign'] );
		$this->assertSame( 240, $this->sanitize( array( 'descriptionMaxWidth' => 1 ), 800 )['descriptionMaxWidth'] );
	}

	private function sanitize( $settings, $default_width ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_supporting_copy_width' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $settings, $default_width );
	}
}
