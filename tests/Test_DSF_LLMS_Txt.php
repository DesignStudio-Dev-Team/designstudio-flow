<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-llms-txt.php';

class Test_DSF_LLMS_Txt extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return' => static function ( $value ) { return trim( strip_tags( (string) $value ) ); } ) );
		WP_Mock::userFunction( 'esc_url_raw', array( 'return' => static function ( $value ) { return (string) $value; } ) );
		WP_Mock::userFunction( 'get_bloginfo', array( 'return' => static function ( $field ) { return 'name' === $field ? 'Example Site' : 'Example site description'; } ) );
		WP_Mock::userFunction( 'home_url', array( 'return' => 'https://example.test/' ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_default_draft_is_generic_and_includes_site_basics(): void {
		$content = DSF_LLMS_Txt::get_default_content();

		$this->assertStringContainsString( '# Example Site', $content );
		$this->assertStringContainsString( 'https://example.test/', $content );
		$this->assertStringContainsString( 'TODO:', $content );
	}

	public function test_content_sanitizer_removes_markup_and_caps_length(): void {
		$this->assertSame( 'bad()Safe guide', DSF_LLMS_Txt::sanitize_content( '<script>bad()</script>Safe guide' ) );
		$this->assertSame( DSF_LLMS_Txt::MAX_LENGTH, strlen( DSF_LLMS_Txt::sanitize_content( str_repeat( 'a', DSF_LLMS_Txt::MAX_LENGTH + 100 ) ) ) );
	}
}
