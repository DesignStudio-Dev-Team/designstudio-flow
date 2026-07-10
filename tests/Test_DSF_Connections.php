<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-connections.php';

/**
 * Covers the connection payload contract: the flat `fields` map plus the
 * Gravity-Forms-style `fields_by_label` map that Zapier zaps rely on.
 */
class Test_DSF_Connections extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'home_url', array( 'return' => 'https://example.com' ) );
		WP_Mock::userFunction(
			'get_post',
			array(
				'return' => static function ( $post_id ) {
					$post             = new stdClass();
					$post->post_title = 'Contact Form';
					return $post_id ? $post : null;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) {
					if ( '_dsf_form_rows' !== $key ) {
						return '';
					}
					return array(
						array(
							'fields' => array(
								array( 'name' => 'first_name', 'label' => 'First Name' ),
								array( 'name' => 'last_name', 'label' => 'Last Name' ),
							),
						),
						array(
							'fields' => array(
								array( 'name' => 'email', 'label' => 'Email' ),
								array( 'name' => 'email_2', 'label' => 'Email' ),
							),
						),
					);
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_payload_includes_label_keyed_fields_for_zapier() {
		$payload = $this->build_payload(
			42,
			7,
			array(
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
				'email'      => 'jane@example.com',
				'email_2'    => 'work@example.com',
				'unmapped'   => 'kept as-is',
			),
			false
		);

		$this->assertSame( 42, $payload['form_id'] );
		$this->assertSame( 'Contact Form', $payload['form_title'] );
		$this->assertSame( 7, $payload['entry_id'] );
		$this->assertFalse( $payload['test'] );

		// Machine-name map stays intact.
		$this->assertSame( 'Jane', $payload['fields']['first_name'] );

		// Label-keyed map mirrors Gravity Forms' Zapier presentation.
		$this->assertSame( 'Jane', $payload['fields_by_label']['First Name'] );
		$this->assertSame( 'Doe', $payload['fields_by_label']['Last Name'] );
		$this->assertSame( 'jane@example.com', $payload['fields_by_label']['Email'] );
		// A duplicate label is disambiguated instead of overwritten.
		$this->assertSame( 'work@example.com', $payload['fields_by_label']['Email (email_2)'] );
		// Fields without a schema label fall back to their machine name.
		$this->assertSame( 'kept as-is', $payload['fields_by_label']['unmapped'] );

		$this->assertSame( 'First Name', $payload['field_labels']['first_name'] );
	}

	public function test_payload_for_unknown_form_keeps_flat_fields() {
		$payload = $this->build_payload( 0, 0, array( 'message' => 'Hi' ), true );

		$this->assertTrue( $payload['test'] );
		$this->assertSame( array( 'message' => 'Hi' ), $payload['fields'] );
		$this->assertSame( array( 'message' => 'Hi' ), $payload['fields_by_label'] );
		$this->assertSame( array(), $payload['field_labels'] );
	}

	private function build_payload( $form_id, $entry_id, $submission, $is_test ) {
		$reflection = new ReflectionClass( 'DSF_Connections' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'build_payload' );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, array( $form_id, $entry_id, $submission, $is_test ) );
	}
}
