<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/**
 * @coversNothing
 */
final class Test_DSF_Ajax_Pricing extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $value ) { return abs( (int) $value ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return' => static function ( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? $value : null; } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $value ) {
			$value = (string) $value;
			return preg_match( '#^(javascript|data|vbscript):#i', $value ) ? '' : $value;
		} ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_unified_pricing_settings_allowlist_layout_and_plan_values(): void {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_pricing_settings' );
		$method->setAccessible( true );
		$clean = $method->invoke( $ajax, array(
			'layout' => 'modern',
			'title' => '<script>Pricing</script>',
			'plans' => array(
				array( 'name' => '<b>Growth</b>', 'description' => '<script>Good plan</script>', 'buttonUrl' => 'javascript:alert(1)', 'features' => "One\nTwo" ),
			),
			'columns' => '9',
		) );

		$this->assertSame( 'modern', $clean['layout'] );
		$this->assertSame( 'Pricing', $clean['title'] );
		$this->assertSame( '', $clean['plans'][0]['buttonUrl'] );
		$this->assertSame( array( 'One', 'Two' ), $clean['plans'][0]['features'] );
		$this->assertSame( '3', $clean['columns'] );
		$this->assertCount( 3, $clean['plans'] );
	}
}
