<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-update-checker.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-admin.php';

class Test_DSF_Plugin extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_get_page_settings_merges_defaults_with_array_meta() {
		$settings = array(
			'theme'  => array(
				'primaryColor' => '#111111',
			),
			'layout' => array(
				'containerWidth' => 1400,
			),
		);

		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 123, '_dsf_settings', true ),
				'return' => $settings,
			)
		);

		$ref      = new ReflectionClass( 'DSF_Frontend' );
		$frontend = $ref->newInstanceWithoutConstructor();

		$result = $frontend->get_page_settings( 123 );

		$this->assertSame( '#111111', $result['theme']['primaryColor'] );
		$this->assertSame( '#FFFFFF', $result['theme']['backgroundColor'] );
		$this->assertSame( 1400, $result['layout']['containerWidth'] );
		$this->assertTrue( $result['layout']['showHeader'] );
	}

	public function test_get_page_settings_handles_json_string() {
		$settings = array(
			'theme'  => array(
				'primaryColor' => '#222222',
			),
			'layout' => array(
				'contentPadding' => 22,
			),
		);

		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 456, '_dsf_settings', true ),
				'return' => json_encode( $settings ),
			)
		);

		$ref      = new ReflectionClass( 'DSF_Frontend' );
		$frontend = $ref->newInstanceWithoutConstructor();

		$result = $frontend->get_page_settings( 456 );

		$this->assertSame( '#222222', $result['theme']['primaryColor'] );
		$this->assertSame( 22, $result['layout']['contentPadding'] );
		$this->assertSame( '#1E40AF', $result['theme']['secondaryColor'] );
	}

	public function test_update_checker_filter_download_ignores_other_packages() {
		$ref     = new ReflectionClass( 'DSF_Update_Checker' );
		$checker = $ref->newInstanceWithoutConstructor();

		$result = $checker->filter_download( 'keep', 'https://example.com/other.zip', null );

		$this->assertSame( 'keep', $result );
	}

	public function test_flow_logo_allowed_html_has_svg_elements() {
		$ref   = new ReflectionClass( 'DSF_Admin' );
		$admin = $ref->newInstanceWithoutConstructor();
		$method = $ref->getMethod( 'get_flow_logo_allowed_html' );
		$method->setAccessible( true );

		$allowed = $method->invoke( $admin );

		$this->assertArrayHasKey( 'svg', $allowed );
		$this->assertArrayHasKey( 'rect', $allowed );
		$this->assertArrayHasKey( 'viewBox', $allowed['svg'] );
		$this->assertArrayHasKey( 'opacity', $allowed['rect'] );
	}
}
