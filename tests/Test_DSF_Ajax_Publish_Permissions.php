<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';

/** Exact object capability checks for Flow editor live-state changes. */
class Test_DSF_Ajax_Publish_Permissions extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_page_auto_publish_requires_publish_post_capability() {
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'return' => static function ( $capability, $post_id ) {
					return 'publish_post' !== $capability || 42 !== (int) $post_id;
				},
			)
		);
		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'return' => static function ( $data, $status ) {
					throw new RuntimeException( (string) ( $data['message'] ?? '' ), (int) $status );
				},
			)
		);

		try {
			$this->invoke_publish_check( 42, 'page', 'draft', array() );
			$this->fail( 'The page publish capability check did not stop the save.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 403, $error->getCode() );
			$this->assertSame( 'You are not allowed to publish this item.', $error->getMessage() );
		}
	}

	public function test_active_template_requires_publish_post_capability() {
		WP_Mock::userFunction( 'current_user_can', array( 'return' => false ) );
		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'return' => static function ( $data, $status ) {
					throw new RuntimeException( (string) ( $data['message'] ?? '' ), (int) $status );
				},
			)
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionCode( 403 );
		$this->invoke_publish_check(
			51,
			'dsf_product_template',
			'draft',
			array( 'productTemplate' => array( 'active' => true ) )
		);
	}

	/** Invoke the private guard without constructing the AJAX singleton. */
	private function invoke_publish_check( $post_id, $post_type, $status, $settings ) {
		$reflection = new ReflectionClass( DSF_Ajax::class );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'verify_editor_publish_permission' );
		$method->setAccessible( true );
		$method->invoke( $ajax, $post_id, $post_type, $status, $settings );
	}
}
