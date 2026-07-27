<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the history payload transport normalizer.
 *
 * Block and form schemas use camelCase keys. History must preserve them so the
 * current restore sanitizers recognize the saved custom values instead of
 * replacing them with schema defaults.
 *
 * @runInSeparateProcess
 */
class Test_DSF_History extends TestCase {
	public function test_history_payload_preserves_camel_case_block_and_form_keys() {
		$normalized = $this->invoke(
			'normalize_value',
			array(
				'_dsf_blocks' => array(
					array(
						'settings' => array(
							'backgroundColor' => '#123456',
							'buttonText'      => 'Request a demo',
						),
					),
				),
				'_dsf_form_rows' => array(
					array(
						'fields' => array(
							array(
								'defaultValue'     => 'Campaign',
								'conditionalLogic' => array( 'fieldId' => 'gf-1' ),
							),
						),
					),
				),
			)
		);

		$settings = $normalized['_dsf_blocks'][0]['settings'];
		$field    = $normalized['_dsf_form_rows'][0]['fields'][0];
		$this->assertSame( '#123456', $settings['backgroundColor'] );
		$this->assertSame( 'Request a demo', $settings['buttonText'] );
		$this->assertSame( 'Campaign', $field['defaultValue'] );
		$this->assertSame( 'gf-1', $field['conditionalLogic']['fieldId'] );
		$this->assertArrayNotHasKey( 'backgroundcolor', $settings );
	}

	public function test_history_payload_removes_unsafe_array_key_characters() {
		$normalized = $this->invoke( 'normalize_value', array( 'safe<script>' => 'value', '###' => 'discard' ) );

		$this->assertSame( 'value', $normalized['safescript'] );
		$this->assertArrayNotHasKey( '', $normalized );
	}

	private function invoke( $method_name, ...$arguments ) {
		require_once dirname( __DIR__ ) . '/includes/class-dsf-history.php';
		$reflection = new ReflectionClass( 'DSF_History' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $arguments );
	}
}
