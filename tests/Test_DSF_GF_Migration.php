<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-gf-migration.php';

/**
 * Covers the pure Gravity Forms → DSF mapping layer: field type translation,
 * choices, composite fields (name/address), conditional-logic remapping, and
 * confirmation/notification settings. The mapped output still passes through
 * DSF_Forms sanitizers before storage; these tests pin down the translation.
 */
class Test_DSF_GF_Migration extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'get_option', array( 'return' => 'owner@example.com' ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_simple_fields_map_with_labels_required_and_help_text() {
		$mapped = $this->map(
			array(
				'title'  => 'Contact Us',
				'fields' => array(
					array(
						'id'          => 1,
						'type'        => 'text',
						'label'       => 'Your Name',
						'isRequired'  => true,
						'placeholder' => 'Jane Doe',
						'description' => 'Tell us who you are',
					),
					array(
						'id'    => 2,
						'type'  => 'email',
						'label' => 'Email',
					),
					array(
						'id'    => 3,
						'type'  => 'textarea',
						'label' => 'Message',
					),
				),
			)
		);

		$this->assertSame( 'Contact Us', $mapped['title'] );
		$this->assertCount( 3, $mapped['rows'] );
		$this->assertSame( array(), $mapped['skipped'] );

		$first = $mapped['rows'][0]['fields'][0];
		$this->assertSame( 'gf-1', $first['id'] );
		$this->assertSame( 'single_line_text', $first['type'] );
		$this->assertSame( 'Your Name', $first['label'] );
		$this->assertSame( 'your_name', $first['name'] );
		$this->assertTrue( $first['required'] );
		$this->assertSame( 'Jane Doe', $first['placeholder'] );

		$this->assertSame( 'email', $mapped['rows'][1]['fields'][0]['type'] );
		$this->assertSame( 'paragraph_text', $mapped['rows'][2]['fields'][0]['type'] );
	}

	public function test_choices_map_to_options_with_selection_and_values() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array(
						'id'      => 4,
						'type'    => 'radio',
						'label'   => 'Size',
						'choices' => array(
							array( 'text' => 'Small', 'value' => 'S' ),
							array( 'text' => 'Large', 'value' => 'Large', 'isSelected' => true ),
						),
					),
				),
			)
		);

		$field = $mapped['rows'][0]['fields'][0];
		$this->assertSame( 'radio_buttons', $field['type'] );
		$this->assertSame( 'Small', $field['options'][0]['label'] );
		$this->assertSame( 'S', $field['options'][0]['value'] );
		// A value identical to the label collapses to the label-only shape.
		$this->assertSame( '', $field['options'][1]['value'] );
		$this->assertTrue( $field['options'][1]['selected'] );
	}

	public function test_name_field_expands_into_a_half_width_pair() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array(
						'id'     => 5,
						'type'   => 'name',
						'label'  => 'Name',
						'inputs' => array(
							array( 'id' => '5.3', 'label' => 'Given Name' ),
							array( 'id' => '5.6', 'label' => 'Family Name' ),
						),
					),
				),
			)
		);

		$this->assertCount( 1, $mapped['rows'] );
		$fields = $mapped['rows'][0]['fields'];
		$this->assertCount( 2, $fields );
		$this->assertSame( 'gf-5-first', $fields[0]['id'] );
		$this->assertSame( 'Given Name', $fields[0]['label'] );
		$this->assertSame( 'half', $fields[0]['width'] );
		$this->assertSame( 'gf-5-last', $fields[1]['id'] );
		$this->assertSame( 'Family Name', $fields[1]['label'] );
	}

	public function test_address_field_expands_into_three_rows() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array( 'id' => 6, 'type' => 'address', 'label' => 'Address' ),
				),
			)
		);

		$this->assertCount( 3, $mapped['rows'] );
		$this->assertSame( 'gf-6-street', $mapped['rows'][0]['fields'][0]['id'] );
		$this->assertSame( 'Street Address', $mapped['rows'][0]['fields'][0]['label'] );
		$this->assertCount( 2, $mapped['rows'][1]['fields'] );
		$this->assertCount( 2, $mapped['rows'][2]['fields'] );
	}

	public function test_conditional_logic_remaps_field_ids_and_operators() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array(
						'id'               => 7,
						'type'             => 'text',
						'label'            => 'Details',
						'conditionalLogic' => array(
							'actionType' => 'hide',
							'logicType'  => 'any',
							'rules'      => array(
								// Checkbox-style input id "8.1" resolves to field 8.
								array( 'fieldId' => '8.1', 'operator' => 'isnot', 'value' => 'No' ),
								array( 'fieldId' => 9, 'operator' => '>', 'value' => '5' ),
								array( 'fieldId' => 99, 'operator' => 'is', 'value' => 'orphan rule' ),
							),
						),
					),
					array( 'id' => 8, 'type' => 'checkbox', 'label' => 'Options' ),
					array( 'id' => 9, 'type' => 'number', 'label' => 'Quantity' ),
				),
			)
		);

		$logic = $mapped['rows'][0]['fields'][0]['conditionalLogic'];
		$this->assertTrue( $logic['enabled'] );
		$this->assertSame( 'hide', $logic['action'] );
		$this->assertSame( 'any', $logic['logicType'] );
		// The rule pointing at an unknown field 99 is dropped.
		$this->assertCount( 2, $logic['rules'] );
		$this->assertSame( 'gf-8', $logic['rules'][0]['fieldId'] );
		$this->assertSame( 'not_equals', $logic['rules'][0]['operator'] );
		$this->assertSame( 'gf-9', $logic['rules'][1]['fieldId'] );
		$this->assertSame( 'greater_than', $logic['rules'][1]['operator'] );
	}

	public function test_unsupported_types_are_skipped_and_reported() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array( 'id' => 10, 'type' => 'captcha', 'label' => 'Captcha' ),
					array( 'id' => 11, 'type' => 'list', 'label' => 'List' ),
					array( 'id' => 12, 'type' => 'page' ),
					array( 'id' => 13, 'type' => 'html', 'content' => '<p>Hello</p>' ),
				),
			)
		);

		$this->assertSame( array( 'captcha', 'list' ), $mapped['skipped'] );
		$this->assertSame( 'page_break', $mapped['rows'][0]['fields'][0]['type'] );
		$this->assertSame( '<p>Hello</p>', $mapped['rows'][1]['fields'][0]['html'] );
	}

	public function test_duplicate_labels_get_unique_machine_names() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array( 'id' => 1, 'type' => 'text', 'label' => 'Phone' ),
					array( 'id' => 2, 'type' => 'text', 'label' => 'Phone' ),
				),
			)
		);

		$this->assertSame( 'phone', $mapped['rows'][0]['fields'][0]['name'] );
		$this->assertSame( 'phone_2', $mapped['rows'][1]['fields'][0]['name'] );
	}

	public function test_settings_map_button_confirmation_and_admin_notification() {
		$mapped = $this->map(
			array(
				'button'        => array( 'text' => 'Send Inquiry' ),
				'confirmations' => array(
					'c1' => array(
						'isDefault' => true,
						'type'      => 'message',
						'message'   => 'Thanks {Name (First):1.3}, we got it. {form_title}',
					),
				),
				'notifications' => array(
					'n1' => array(
						'isActive' => true,
						'to'       => '{admin_email}, sales@example.com, {Email:2}',
						'subject'  => 'New entry — {form_title}',
					),
				),
				'fields'        => array(),
			)
		);

		$settings = $mapped['settings'];
		$this->assertSame( 'Send Inquiry', $settings['submitLabel'] );
		$this->assertSame( 'message', $settings['confirmationType'] );
		$this->assertSame( 'Thanks , we got it.', $settings['confirmationMessage'] );
		$this->assertTrue( $settings['sendAdminNotifications'] );
		// {admin_email} resolves to the site admin; merge-tag recipients drop.
		$this->assertSame( array( 'owner@example.com', 'sales@example.com' ), $settings['adminEmails'] );
		$this->assertSame( 'New entry —', $settings['notificationSubject'] );
	}

	public function test_redirect_confirmation_maps_to_redirect_settings() {
		$mapped = $this->map(
			array(
				'confirmations' => array(
					array( 'type' => 'redirect', 'url' => 'https://example.com/thanks', 'isDefault' => true ),
				),
				'fields'        => array(),
			)
		);

		$this->assertSame( 'redirect_url', $mapped['settings']['confirmationType'] );
		$this->assertSame( 'https://example.com/thanks', $mapped['settings']['redirectUrl'] );
	}

	public function test_consent_field_becomes_a_single_checkbox() {
		$mapped = $this->map(
			array(
				'fields' => array(
					array(
						'id'         => 20,
						'type'       => 'consent',
						'label'      => 'Consent',
						'isRequired' => true,
						'choices'    => array( array( 'text' => 'I agree to the privacy policy.', 'value' => '1' ) ),
					),
				),
			)
		);

		$field = $mapped['rows'][0]['fields'][0];
		$this->assertSame( 'checkboxes', $field['type'] );
		$this->assertTrue( $field['required'] );
		$this->assertCount( 1, $field['options'] );
		$this->assertSame( 'I agree to the privacy policy.', $field['options'][0]['label'] );
	}

	private function map( $gf_form ) {
		$reflection = new ReflectionClass( 'DSF_GF_Migration' );
		$instance   = $reflection->newInstanceWithoutConstructor();
		return $instance->map_gf_form( $gf_form );
	}
}
