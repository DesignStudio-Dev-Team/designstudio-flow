<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

/** @coversNothing */
final class Test_DSF_Layout_Spacing_Defaults extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-07-22 12:00:00' ) ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_registered_blocks_default_layout_spacing_to_zero(): void {
		foreach ( DSF_Blocks::get_instance()->get_registered_blocks() as $block ) {
			foreach ( array( 'padding', 'paddingX', 'paddingY', 'marginY' ) as $key ) {
				if ( ! isset( $block['settings'][ $key ] ) ) {
					continue;
				}
				$this->assertSame( 0, $block['settings'][ $key ]['default'], $block['id'] . ':' . $key );
				$this->assertSame( 0, $block['settings'][ $key ]['min'], $block['id'] . ':' . $key . ' minimum' );
			}
		}
	}
}
