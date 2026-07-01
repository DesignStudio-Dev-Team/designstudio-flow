<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-forms.php';

/**
 * Security-focused coverage for DSF_Forms.
 *
 * The form builder lets admins define fields (incl. the new Parameter Name and
 * per-option label/value), and the public submit endpoint accepts arbitrary
 * visitor input. These tests pin down the sanitization layers so a hostile
 * payload can't smuggle scripts, event handlers, or reserved fields through.
 *
 * WordPress escaping/sanitizing functions are mocked to mirror real behavior
 * (sanitize_text_field strips tags; esc_attr/esc_html run htmlspecialchars),
 * so the assertions reflect what actually reaches storage / the page.
 */
class Test_DSF_Forms extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction(
			'sanitize_text_field',
			array(
				'return' => static function ( $value ) {
					$value = preg_replace( '/<[^>]*>/', '', (string) $value ); // strip tags
					$value = preg_replace( '/[\r\n\t]+/', ' ', $value );
					return trim( preg_replace( '/\s{2,}/', ' ', $value ) );
				},
			)
		);
		WP_Mock::userFunction(
			'wp_unslash',
			array(
				'return' => static function ( $value ) {
					return is_string( $value ) ? stripslashes( $value ) : $value;
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_title',
			array(
				'return' => static function ( $value ) {
					return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
				},
			)
		);
		WP_Mock::userFunction(
			'esc_attr',
			array(
				'return' => static function ( $value ) {
					return htmlspecialchars( (string) $value, ENT_QUOTES );
				},
			)
		);
		WP_Mock::userFunction(
			'esc_html',
			array(
				'return' => static function ( $value ) {
					return htmlspecialchars( (string) $value, ENT_QUOTES );
				},
			)
		);
		WP_Mock::userFunction(
			'wp_kses_post',
			array(
				'return' => static function ( $html ) {
					$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
					return preg_replace( '/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html );
				},
			)
		);
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_attr__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( '_x', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'wp_json_encode', array( 'return' => static function ( $v ) { return json_encode( $v ); } ) );
		WP_Mock::userFunction( 'wp_generate_uuid4', array( 'return' => 'uuid-test-0000' ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/* ---- Submission payload (public, untrusted input) ---- */

	public function test_submission_strips_scripts_from_values() {
		$clean = $this->invoke(
			'sanitize_submission_payload',
			array(
				'message' => '<script>alert(document.cookie)</script>Hello',
				'name'    => 'Jane<img src=x onerror=alert(1)>',
			)
		);

		$this->assertArrayHasKey( 'message', $clean );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $clean['message'] );
		$this->assertStringNotContainsStringIgnoringCase( 'onerror', $clean['name'] );
		$this->assertStringContainsString( 'Hello', $clean['message'] );
	}

	public function test_submission_skips_reserved_keys() {
		$clean = $this->invoke(
			'sanitize_submission_payload',
			array(
				'nonce'          => 'forged',
				'action'         => 'evil',
				'dsf_form_id'    => '999',
				'dsf_form_nonce' => 'x',
				'recaptcha_token' => 'x',
				'email'          => 'a@b.com',
			)
		);

		$this->assertSame( array( 'email' => 'a@b.com' ), $clean );
	}

	public function test_submission_sanitizes_array_values_and_keys() {
		$clean = $this->invoke(
			'sanitize_submission_payload',
			array(
				'interests[]' => array( '<b>One</b>', '<i>Two</i>' ),
				'BadKey!!'    => 'value',
			)
		);

		// Key is run through sanitize_key (brackets/punctuation/uppercase removed).
		$this->assertArrayHasKey( 'interests', $clean );
		$this->assertArrayHasKey( 'badkey', $clean );
		$this->assertSame( array( 'One', 'Two' ), $clean['interests'] );
	}

	public function test_submission_rejects_non_array_payload() {
		$this->assertSame( array(), $this->invoke( 'sanitize_submission_payload', 'not-an-array' ) );
	}

	/* ---- Field config (Parameter Name + options) ---- */

	public function test_param_name_strips_unsafe_characters() {
		$field = $this->invoke(
			'sanitize_form_field',
			array(
				'type'      => 'single_line_text',
				'label'     => 'Email',
				'name'      => 'email',
				'paramName' => 'utm_source"><script>alert(1)</script> drop',
			)
		);

		// Only the safe param-name charset survives — no <, >, quotes, spaces, parens.
		$this->assertSame( '', preg_replace( '/[A-Za-z0-9_.\-\[\]]/', '', $field['paramName'] ) );
		$this->assertStringStartsWith( 'utm_source', $field['paramName'] );
	}

	public function test_options_normalized_with_value_and_single_select_for_radio() {
		$field = $this->invoke(
			'sanitize_form_field',
			array(
				'type'    => 'radio_buttons',
				'label'   => 'Plan',
				'name'    => 'plan',
				'options' => array(
					array( 'label' => '<b>Basic</b>', 'value' => 'basic', 'selected' => true ),
					array( 'label' => 'Pro', 'value' => 'pro', 'selected' => true ),
					'Legacy',
				),
			)
		);

		$this->assertCount( 3, $field['options'] );
		$this->assertSame( 'Basic', $field['options'][0]['label'] ); // tags stripped
		$this->assertSame( 'basic', $field['options'][0]['value'] );
		$this->assertTrue( $field['options'][0]['selected'] );
		$this->assertFalse( $field['options'][1]['selected'] ); // only one preselect on radio
		$this->assertSame( 'Legacy', $field['options'][2]['label'] ); // legacy string accepted
	}

	public function test_unknown_field_type_is_rejected() {
		$this->assertNull(
			$this->invoke( 'sanitize_form_field', array( 'type' => 'arbitrary_code', 'label' => 'x' ) )
		);
	}

	/* ---- Rendered output is escaped (no attribute breakout) ---- */

	public function test_rendered_option_value_is_attribute_escaped() {
		$field = $this->invoke(
			'sanitize_form_field',
			array(
				'type'    => 'radio_buttons',
				'label'   => 'Pick',
				'name'    => 'pick',
				// Quotes survive sanitize_text_field; esc_attr must neutralize them.
				'options' => array( array( 'label' => 'X', 'value' => 'a" onfocus="alert(1)' ) ),
			)
		);

		$html = $this->invoke( 'render_field_markup', $field, 1, 0, 0, array() );

		// The handler must be escaped — no live, unescaped onfocus attribute.
		$this->assertStringNotContainsString( ' onfocus="alert(1)"', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	public function test_rendered_param_attribute_present_and_safe() {
		$field = $this->invoke(
			'sanitize_form_field',
			array(
				'type'      => 'single_line_text',
				'label'     => 'Email',
				'name'      => 'email',
				'paramName' => 'utm_source',
			)
		);

		$html = $this->invoke( 'render_field_markup', $field, 1, 0, 0, array() );
		$this->assertStringContainsString( 'data-dsf-param="utm_source"', $html );
	}

	private function invoke( $method_name, ...$arguments ) {
		$reflection = new ReflectionClass( 'DSF_Forms' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $arguments );
	}
}
