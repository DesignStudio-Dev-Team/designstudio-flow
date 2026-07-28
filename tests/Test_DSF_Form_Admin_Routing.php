<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-forms.php';

/**
 * Coverage for form-builder admin destinations.
 */
class Test_DSF_Form_Admin_Routing extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction(
			'admin_url',
			array(
				'return' => static function ( $path ) {
					return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
				},
			)
		);
		WP_Mock::userFunction(
			'add_query_arg',
			array(
				'return' => static function ( $args, $url ) {
					return $url . '?' . http_build_query( $args );
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_entries_url_filters_to_the_current_form() {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=dsf-entries&form_id=42',
			$this->invoke( 'get_form_entries_url', 42 )
		);
	}

	public function test_entries_url_omits_an_invalid_form_filter() {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=dsf-entries',
			$this->invoke( 'get_form_entries_url', 0 )
		);
	}

	private function invoke( $method_name, ...$arguments ) {
		$reflection = new ReflectionClass( 'DSF_Forms' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $instance, $arguments );
	}
}
