<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

/**
 * Runs the translatable contract against the real block catalog.
 *
 * This is the regression guard for the property the whole feature rests on: a
 * structural setting must never become translatable because someone added a
 * `text` control for it.
 */
class Test_DSF_Translation_Block_Coverage extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $v ) { return trim( strip_tags( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'esc_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'get_option', array( 'return' => false ) );
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-07-28 10:00:00' ) ) );
		WP_Mock::userFunction( 'wp_timezone', array( 'return' => new DateTimeZone( 'UTC' ) ) );
		WP_Mock::userFunction( 'wp_get_attachment_image_url', array( 'return' => '' ) );
		WP_Mock::userFunction( 'trailingslashit', array( 'return' => static function ( $v ) { return rtrim( (string) $v, '/' ) . '/'; } ) );
		if ( ! defined( 'DSF_PLUGIN_URL' ) ) {
			define( 'DSF_PLUGIN_URL', 'https://example.test/plugin/' );
		}
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Flatten every descriptor path a block declares.
	 *
	 * @param array  $descriptor Descriptor.
	 * @param string $prefix     Path prefix.
	 * @return string[]
	 */
	private function flatten( $descriptor, $prefix = '' ) {
		if ( ! is_array( $descriptor ) ) {
			return array();
		}
		if ( 'value' === ( $descriptor['kind'] ?? '' ) ) {
			return array( $prefix );
		}
		$paths = array();
		foreach ( (array) ( $descriptor['fields'] ?? array() ) as $field => $child ) {
			$paths = array_merge( $paths, $this->flatten( $child, '' === $prefix ? $field : $prefix . '.' . $field ) );
		}
		return $paths;
	}

	public function test_no_registered_block_exposes_a_structural_value() {
		$blocks    = DSF_Blocks::get_instance()->get_registered_blocks();
		$offenders = array();

		$this->assertNotEmpty( $blocks );

		foreach ( $blocks as $block_id => $block ) {
			foreach ( DSF_Translation_Contract::describe_block( $block_id, $block ) as $setting => $descriptor ) {
				foreach ( $this->flatten( $descriptor, $setting ) as $path ) {
					$leaf = substr( strrchr( '.' . $path, '.' ), 1 );
					if ( preg_match( '/(url|href|shortcode|embedcode|code|ids?|keys?|video|sku|price|slug|anchor)$/i', $leaf ) ) {
						$offenders[] = $block_id . '.' . $path;
					}
				}
			}
		}

		$this->assertSame( array(), $offenders, 'These paths would send structural values to a translator.' );
	}

	public function test_representative_blocks_declare_the_expected_copy() {
		$faq = DSF_Blocks::get_translatable_settings( 'faq' );
		$this->assertSame( array( 'title', 'items' ), array_keys( $faq ) );
		$this->assertSame( array( 'question', 'answer' ), array_keys( $faq['items']['fields'] ) );

		$content = DSF_Blocks::get_translatable_settings( 'content' );
		$this->assertSame( array( 'content' ), array_keys( $content ) );
		$this->assertSame( DSF_Translation_Contract::FORMAT_HTML, $content['content']['format'] );

		$this->assertSame( array(), DSF_Blocks::get_translatable_settings( 'no-such-block' ) );
	}

	public function test_dynamic_commerce_blocks_have_no_authored_copy_to_translate() {
		// Their visible text comes from the live product or archive, which the
		// WooCommerce overlay phase owns — not from block settings.
		foreach ( array( 'shop-products', 'store-cart', 'product-summary', 'blog-header' ) as $block_id ) {
			$this->assertSame( array(), DSF_Blocks::get_translatable_settings( $block_id ), $block_id );
		}
	}
}
