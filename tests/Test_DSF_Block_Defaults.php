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
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
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

				// A control may deliberately opt out of placeholder hydration to keep
				// a genuinely empty default (e.g. an optional, transparent-by-default
				// background color).
				if ( ! empty( $setting['skip_placeholder'] ) ) {
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

	public function test_every_image_setting_and_nested_image_value_has_a_placeholder() {
		$blocks = DSF_Blocks::get_instance()->get_registered_blocks();

		foreach ( $blocks as $block ) {
			foreach ( $block['settings'] as $key => $setting ) {
				if ( 'image' === ( $setting['type'] ?? '' ) && empty( $setting['skip_placeholder'] ) ) {
					$this->assertStringContainsString(
						'assets/images/dsf-placeholder.svg',
						(string) $setting['default'],
						sprintf( '%s:%s should start with the packaged image placeholder.', $block['id'], $key )
					);
				}

				$this->assertNestedImagesHavePlaceholders( $block['id'], $key, $setting['default'] ?? null );
			}
		}
	}

	private function assertNestedImagesHavePlaceholders( $block_id, $setting_key, $value ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$this->assertNestedImagesHavePlaceholders( $block_id, $setting_key, $item );
				continue;
			}

			$key_string = (string) $key;
			if ( preg_match( '/(?:image|logo)$/i', $key_string ) && ! preg_match( '/iconimage$/i', $key_string ) ) {
				$this->assertStringContainsString(
					'assets/images/dsf-placeholder.svg',
					(string) $item,
					sprintf( '%s:%s[%s] should start with the packaged image placeholder.', $block_id, $setting_key, $key )
				);
				continue;
			}

			if ( preg_match( '/(?:title|heading|description|content|text|label|name|eyebrow|subtitle|caption|quote|author|feature\w*)$/i', $key_string ) ) {
				$this->assertNotSame(
					'',
					(string) $item,
					sprintf( '%s:%s[%s] should start with editable placeholder copy.', $block_id, $setting_key, $key )
				);
			}
		}
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

	public function test_ecommerce_showcase_exposes_product_purchase_and_count_controls() {
		$blocks   = DSF_Blocks::get_instance()->get_registered_blocks();
		$settings = $blocks['ecommerce-showcase']['settings'];

		foreach ( array( 'imageFit', 'priceColor', 'salePriceColor', 'showAddToCart', 'buttonText', 'buttonColor', 'buttonTextColor', 'showProductCount', 'countText', 'countColor' ) as $key ) {
			$this->assertArrayHasKey( $key, $settings );
			$this->assertSame( 'products', $settings[ $key ]['showWhen']['displayMode'] );
		}
		$this->assertSame( '{count} products total', $settings['countText']['default'] );
		$this->assertSame( 'contain', $settings['imageFit']['default'] );
	}
}
