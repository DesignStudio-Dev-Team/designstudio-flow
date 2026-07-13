<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * The block-level "HTML anchor" (links like #pricing scroll to a block) is
 * normalized to a safe id and preserved through the block settings sanitizer for
 * every block type.
 */
class Test_DSF_Ajax_Block_Anchor extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		// A block type with no bespoke sanitizer falls through to this filter; the
		// default is a pass-through, so return the settings unchanged.
		WP_Mock::userFunction( 'apply_filters', array( 'return_arg' => 1 ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_anchor_is_slugified() {
		$this->assertSame( 'our-pricing', DSF_Ajax::sanitize_anchor_id( 'Our Pricing!' ) );
		$this->assertSame( 'contact-us', DSF_Ajax::sanitize_anchor_id( '  Contact_Us  ' ) );
		$this->assertSame( 'a-b', DSF_Ajax::sanitize_anchor_id( 'a---b' ) );
	}

	public function test_leading_digit_is_prefixed_for_a_valid_selector() {
		$this->assertSame( 's-2024-plans', DSF_Ajax::sanitize_anchor_id( '2024 plans' ) );
	}

	public function test_empty_or_garbage_returns_empty() {
		$this->assertSame( '', DSF_Ajax::sanitize_anchor_id( '!!!' ) );
		$this->assertSame( '', DSF_Ajax::sanitize_anchor_id( '' ) );
	}

	public function test_anchor_survives_block_sanitizer_and_is_normalized() {
		$blocks = $this->sanitize_blocks(
			array(
				array(
					'type'     => 'content',
					'settings' => array(),
					'anchorId' => 'My Section',
				),
			)
		);

		$this->assertSame( 'my-section', $blocks[0]['anchorId'] );
	}

	public function test_blank_anchor_is_dropped() {
		$blocks = $this->sanitize_blocks(
			array(
				array(
					'type'     => 'hero',
					'settings' => array(),
					'anchorId' => '   ',
				),
			)
		);

		$this->assertArrayNotHasKey( 'anchorId', $blocks[0] );
	}

	private function sanitize_blocks( $blocks ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_known_block_settings' );
		$method->setAccessible( true );
		return $method->invoke( $ajax, $blocks );
	}
}
