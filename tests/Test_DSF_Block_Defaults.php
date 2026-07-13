<?php
/**
 * First-insert schema defaults.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

class Test_DSF_Block_Defaults extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-06-22 12:00:00' ) ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_visual_scalar_controls_have_editable_first_insert_defaults() {
		$visual_types = array( 'text', 'textarea', 'wysiwyg', 'richtext', 'image', 'color' );
		$blocks       = DSF_Blocks::get_instance()->get_registered_blocks();

		foreach ( $blocks as $block ) {
			foreach ( $block['settings'] as $key => $setting ) {
				if ( ! in_array( $setting['type'] ?? '', $visual_types, true ) ) {
					continue;
				}

				$this->assertNotSame(
					'',
					$setting['default'],
					sprintf( '%s:%s should be populated for a new block.', $block['id'], $key )
				);
			}
		}
	}

	public function test_blank_image_controls_use_the_packaged_placeholder_asset() {
		$blocks = DSF_Blocks::get_instance()->get_registered_blocks();

		$this->assertStringContainsString(
			'assets/images/dsf-placeholder.svg',
			$blocks['bento-hero']['settings']['heroImage']['default']
		);
		$this->assertStringContainsString(
			'assets/images/dsf-placeholder.svg',
			$blocks['expander-hero']['settings']['cards']['default'][0]['image']
		);
	}

	public function test_showcase_hero_defaults_define_six_synchronized_destinations() {
		$blocks = DSF_Blocks::get_instance()->get_registered_blocks();
		$hero   = $blocks['landing-showcase-hero']['settings'];
		$words  = array_map( 'trim', explode( ',', $hero['rotatingWords']['default'] ) );

		$this->assertCount( 6, $words );
		$this->assertCount( 6, array_unique( array_map( 'strtolower', $words ) ) );
		foreach ( $words as $word ) {
			$this->assertCount( 2, preg_split( '/\s+/', $word ) );
		}
		$this->assertCount( 6, $hero['tiles']['default'] );
		$this->assertSame( 6, $hero['tiles']['maxItems'] );
		$this->assertSame( 390, $hero['rotatingWords']['maxLength'] );
	}
}
