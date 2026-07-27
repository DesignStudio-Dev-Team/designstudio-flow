<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

class Test_DSF_Ajax_Theme_Layout extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static fn( $value ) => abs( (int) $value ) ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $layout ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_page_layout_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $layout );
	}

	public function test_zero_content_padding_is_preserved_and_is_the_default() {
		$this->assertSame( 0, $this->sanitize( array( 'contentPadding' => 0 ) )['contentPadding'] );
		$this->assertSame( 0, $this->sanitize( array() )['contentPadding'] );
	}

	public function test_layout_numbers_are_clamped() {
		$clean = $this->sanitize(
			array(
				'containerWidth' => 99999,
				'contentPadding' => 999,
			)
		);

		$this->assertSame( 1800, $clean['containerWidth'] );
		$this->assertSame( 64, $clean['contentPadding'] );
	}
}
