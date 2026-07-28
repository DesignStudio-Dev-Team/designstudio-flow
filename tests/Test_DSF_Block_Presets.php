<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-block-presets.php';

class Test_DSF_Block_Presets extends TestCase {

	// Specialized landing designs stay renderable but live in Presets instead of
	// crowding the reusable Blocks tab.
	private const PRESET_ONLY_LANDING_TYPES = array(
		'landing-product-story',
		'landing-redirect-tool',
		'landing-mail-tool',
		'landing-block-ready',
	);

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
			)
		);
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-07-23 12:00:00' ) ) );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_every_preset_resolves_to_a_registered_block_with_settings() {
		$registered = DSF_Blocks::get_instance()->get_registered_blocks();
		$presets    = DSF_Block_Presets::get_instance()->get_presets();

		$this->assertNotEmpty( $presets );
		foreach ( $presets as $preset ) {
			$this->assertArrayHasKey( $preset['type'], $registered, "Preset {$preset['key']} references unregistered type {$preset['type']}" );
			$this->assertIsArray( $preset['settings'] );
			$this->assertNotEmpty( $preset['settings'], "Preset {$preset['key']} resolved no settings" );
		}
	}

	public function test_prefilled_landing_sections_are_offered_as_presets() {
		$presets = DSF_Block_Presets::get_instance()->get_presets();
		$types   = array_column( $presets, 'type' );

		foreach ( self::PRESET_ONLY_LANDING_TYPES as $type ) {
			$this->assertContains( $type, $types, "No preset exists for {$type}" );
		}
	}

	public function test_preset_settings_carry_the_prefilled_block_defaults() {
		$presets = DSF_Block_Presets::get_instance()->get_presets();
		$by_key  = array_column( $presets, null, 'key' );
		$mail     = $by_key['split-content-mail'] ?? null;
		$carousel = $by_key['content-carousel-block-library'] ?? null;

		$this->assertNotNull( $mail );
		$this->assertSame( 'landing-mail-tool', $mail['type'] );
		$this->assertSame( 'Email that actually reaches the inbox.', $mail['settings']['title'] );
		$this->assertNotNull( $carousel );
		$this->assertSame( 'block-library', $carousel['settings']['source'] );
	}

	public function test_preset_only_blocks_stay_registered_but_leave_the_block_library() {
		$blocks     = DSF_Blocks::get_instance();
		$registered = $blocks->get_registered_blocks();
		$categories = $blocks->get_blocks_by_category();

		$listed_ids = array();
		foreach ( $categories as $category ) {
			foreach ( $category['blocks'] as $block ) {
				$listed_ids[] = $block['id'];
			}
		}

		foreach ( self::PRESET_ONLY_LANDING_TYPES as $type ) {
			$this->assertArrayHasKey( $type, $registered, "{$type} must stay registered so existing pages keep rendering" );
			$this->assertNotContains( $type, $listed_ids, "{$type} must not be listed in the block library" );
		}

		// Reusable blocks and unique landing sections remain listed normally.
		$this->assertContains( 'card-columns', $listed_ids );
		$this->assertContains( 'landing-marketing-footer', $listed_ids );
		$this->assertNotContains( 'landing-product-story', $listed_ids );
		$this->assertContains( 'landing-hero', $listed_ids );
		$this->assertContains( 'landing-block-explorer', $listed_ids );
		$this->assertContains( 'steps-image', $listed_ids );
		$this->assertNotContains( 'landing-block-ready', $listed_ids );
		$this->assertContains( 'landing-trust-workflow', $listed_ids );
		$this->assertContains( 'landing-engagement-suite', $listed_ids );
	}
}
