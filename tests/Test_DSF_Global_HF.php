<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-frontend.php';

/**
 * Whole-site header/footer mode wraps normal Pages/Posts with the DSF header and
 * footer. These tests pin down the gate: it is opt-in, needs a default configured,
 * and skips DSF pages, products, and non-page/post content.
 */
class Test_DSF_Global_HF extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		WP_Mock::userFunction( 'is_singular', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'apply_filters', array( 'return' => static function ( $tag, $value = null ) { return $value; } ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function frontend() {
		return ( new ReflectionClass( 'DSF_Frontend' ) )->newInstanceWithoutConstructor();
	}

	/** Mock get_option so the global flag + default header id are controllable. */
	private function mock_options( $global_on, $header_id = 0, $footer_id = 0 ) {
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) use ( $global_on, $header_id, $footer_id ) {
					switch ( $key ) {
						case 'dsf_global_header_footer':
							return $global_on;
						case 'dsf_default_header_id':
							return $header_id;
						case 'dsf_default_footer_id':
							return $footer_id;
					}
					return $default;
				},
			)
		);
	}

	/** Make get_default_layout_id( 'header' ) resolve to a valid published header. */
	private function mock_valid_header( $id ) {
		$post              = new stdClass();
		$post->post_type   = 'dsf_layout';
		$post->post_status = 'publish';
		WP_Mock::userFunction( 'get_post', array( 'args' => array( $id ), 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $id, '_dsf_layout_type', true ), 'return' => 'header' ) );
	}

	public function test_off_by_default() {
		$this->mock_options( false );
		$this->assertFalse( $this->frontend()->should_apply_global_hf( 10 ) );
	}

	public function test_on_but_no_default_configured() {
		$this->mock_options( true, 0, 0 );
		$this->assertFalse( $this->frontend()->should_apply_global_hf( 10 ) );
	}

	public function test_applies_to_normal_page() {
		$this->mock_options( true, 5, 0 );
		$this->mock_valid_header( 5 );
		WP_Mock::userFunction( 'get_post_type', array( 'args' => array( 10 ), 'return' => 'page' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 10, '_dsf_enabled', true ), 'return' => '' ) );

		$this->assertTrue( $this->frontend()->should_apply_global_hf( 10 ) );
	}

	public function test_applies_to_normal_post() {
		$this->mock_options( true, 5, 0 );
		$this->mock_valid_header( 5 );
		WP_Mock::userFunction( 'get_post_type', array( 'args' => array( 12 ), 'return' => 'post' ) );

		$this->assertTrue( $this->frontend()->should_apply_global_hf( 12 ) );
	}

	public function test_skips_dsf_enabled_page() {
		$this->mock_options( true, 5, 0 );
		$this->mock_valid_header( 5 );
		WP_Mock::userFunction( 'get_post_type', array( 'args' => array( 10 ), 'return' => 'page' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 10, '_dsf_enabled', true ), 'return' => '1' ) );

		$this->assertFalse( $this->frontend()->should_apply_global_hf( 10 ) );
	}

	public function test_skips_products_and_other_types() {
		$this->mock_options( true, 5, 0 );
		$this->mock_valid_header( 5 );
		WP_Mock::userFunction( 'get_post_type', array( 'args' => array( 99 ), 'return' => 'product' ) );

		$this->assertFalse( $this->frontend()->should_apply_global_hf( 99 ) );
	}
}
