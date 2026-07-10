<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-product-templates.php';

/**
 * The site-wide default header/footer must resolve even when the layout is a
 * draft (the caller applies the publish/preview gate, matching explicitly-
 * assigned templates). Also covers the "enable products" master toggle.
 */
class Test_DSF_Default_Layout extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function mock_layout( $id, $type, $status ) {
		WP_Mock::userFunction( 'get_option', array( 'return' => $id ) );
		$post               = new stdClass();
		$post->post_type    = 'dsf_layout';
		$post->post_status  = $status;
		WP_Mock::userFunction( 'get_post', array( 'args' => array( $id ), 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $id, '_dsf_layout_type', true ), 'return' => $type ) );
	}

	public function test_published_default_resolves() {
		$this->mock_layout( 12, 'header', 'publish' );
		$this->assertSame( 12, DSF_Frontend::get_default_layout_id( 'header' ) );
	}

	public function test_draft_default_still_resolves() {
		// The publish gate lives in the caller, so a draft default is returned here.
		$this->mock_layout( 8, 'footer', 'draft' );
		$this->assertSame( 8, DSF_Frontend::get_default_layout_id( 'footer' ) );
	}

	public function test_trashed_default_does_not_resolve() {
		$this->mock_layout( 5, 'header', 'trash' );
		$this->assertSame( 0, DSF_Frontend::get_default_layout_id( 'header' ) );
	}

	public function test_type_mismatch_does_not_resolve() {
		$this->mock_layout( 9, 'footer', 'publish' );
		$this->assertSame( 0, DSF_Frontend::get_default_layout_id( 'header' ) );
	}

	public function test_zero_option_returns_zero() {
		WP_Mock::userFunction( 'get_option', array( 'return' => 0 ) );
		$this->assertSame( 0, DSF_Frontend::get_default_layout_id( 'header' ) );
	}

	public function test_products_enabled_defaults_true() {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( 'dsf_products_enabled', true ),
				'return' => true,
			)
		);
		$this->assertTrue( DSF_Product_Templates::is_enabled() );
	}

	public function test_products_can_be_disabled() {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( 'dsf_products_enabled', true ),
				'return' => 0,
			)
		);
		$this->assertFalse( DSF_Product_Templates::is_enabled() );
	}

	public function test_apply_layout_to_all_is_noop_for_zero() {
		$this->assertSame( 0, DSF_Frontend::apply_layout_to_all_flow_content( 'header', 0 ) );
	}

	public function test_apply_layout_writes_header_to_pages_that_differ() {
		// Two get_posts calls: DSF pages, then product templates.
		WP_Mock::userFunction( 'get_posts' )->andReturn( array( 10, 11 ), array() );

		// Page 10 already has the target header; page 11 has a different one.
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 10, '_dsf_settings', true ), 'return' => array( 'layout' => array( 'headerTemplateId' => 7 ) ) ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 11, '_dsf_settings', true ), 'return' => array( 'layout' => array( 'headerTemplateId' => 3 ) ) ) );

		// Only the differing page is rewritten, with the new header id.
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'times'  => 1,
				'args'   => array( 11, '_dsf_settings', array( 'layout' => array( 'headerTemplateId' => 7 ) ) ),
				'return' => true,
			)
		);

		$updated = DSF_Frontend::apply_layout_to_all_flow_content( 'header', 7 );
		$this->assertSame( 1, $updated );
	}

	public function test_apply_layout_creates_layout_key_when_missing() {
		WP_Mock::userFunction( 'get_posts' )->andReturn( array( 20 ), array() );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 20, '_dsf_settings', true ), 'return' => array( 'theme' => array( 'primaryColor' => '#000' ) ) ) );
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'times'  => 1,
				'args'   => array( 20, '_dsf_settings', array( 'theme' => array( 'primaryColor' => '#000' ), 'layout' => array( 'footerTemplateId' => 9 ) ) ),
				'return' => true,
			)
		);

		$this->assertSame( 1, DSF_Frontend::apply_layout_to_all_flow_content( 'footer', 9 ) );
	}
}
