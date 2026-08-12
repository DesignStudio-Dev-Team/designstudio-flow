<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-contract.php';

/**
 * Covers the explicit translatable-path contract: only declared schema fields
 * are translatable, and structural values can never become translatable by
 * accident.
 */
class Test_DSF_Translation_Contract extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Describe a synthetic block registration.
	 *
	 * @param array  $settings Settings schema.
	 * @param string $id       Block id.
	 * @return array
	 */
	private function describe( $settings, $id = 'acme-block' ) {
		return DSF_Translation_Contract::describe_block( $id, array( 'settings' => $settings ) );
	}

	public function test_copy_types_are_translatable_and_control_types_are_not() {
		$described = $this->describe(
			array(
				'title'           => array( 'type' => 'text' ),
				'subtitle'        => array( 'type' => 'textarea' ),
				'content'         => array( 'type' => 'wysiwyg' ),
				'body'            => array( 'type' => 'richtext' ),
				'backgroundColor' => array( 'type' => 'color' ),
				'padding'         => array( 'type' => 'slider' ),
				'showRating'      => array( 'type' => 'toggle' ),
				'imageSide'       => array( 'type' => 'select' ),
				'heroImage'       => array( 'type' => 'image' ),
				'clipVideo'       => array( 'type' => 'video' ),
				'launchAt'        => array( 'type' => 'datetime' ),
				'productIds'      => array( 'type' => 'products' ),
				'columns'         => array( 'type' => 'number' ),
			)
		);

		$this->assertSame( array( 'title', 'subtitle', 'content', 'body' ), array_keys( $described ) );
		$this->assertSame( DSF_Translation_Contract::FORMAT_TEXT, $described['title']['format'] );
		$this->assertSame( DSF_Translation_Contract::FORMAT_MULTILINE, $described['subtitle']['format'] );
		$this->assertSame( DSF_Translation_Contract::FORMAT_HTML, $described['content']['format'] );
		$this->assertSame( DSF_Translation_Contract::FORMAT_HTML, $described['body']['format'] );
	}

	public function test_structural_keys_are_excluded_even_with_a_copy_type() {
		$described = $this->describe(
			array(
				'buttonText'          => array( 'type' => 'text' ),
				'buttonUrl'           => array( 'type' => 'text' ),
				'newsletterAction'    => array( 'type' => 'text' ),
				'customFieldKeys'     => array( 'type' => 'text' ),
				'promoCode'           => array( 'type' => 'text' ),
				'currencyText'        => array( 'type' => 'text' ),
				'discountAmount'      => array( 'type' => 'text' ),
				'mobilePhoneNumber'   => array( 'type' => 'text' ),
				'buttonModalShortcode' => array( 'type' => 'text' ),
				'buttonModalHtml'     => array( 'type' => 'textarea' ),
				'newsletterEmbedCode' => array( 'type' => 'textarea' ),
				'heroVideo'           => array( 'type' => 'text' ),
				'url'                 => array( 'type' => 'text' ),
				'logoAlt'             => array( 'type' => 'text' ),
				'box1ImageAlt'        => array( 'type' => 'text' ),
			)
		);

		$this->assertSame(
			array( 'buttonText', 'logoAlt', 'box1ImageAlt' ),
			array_keys( $described ),
			'Only visitor copy survives; routing, codes, keys, amounts, media and raw markup never do.'
		);
	}

	public function test_repeater_types_expose_only_their_declared_copy_fields() {
		$described = $this->describe(
			array(
				'items' => array( 'type' => 'faq_items' ),
				'plans' => array( 'type' => 'pricing_plans' ),
				'links' => array( 'type' => 'simple_links' ),
				'logos' => array( 'type' => 'image_logo_grid_items' ),
			)
		);

		$this->assertSame( 'list', $described['items']['kind'] );
		$this->assertSame( array( 'question', 'answer' ), array_keys( $described['items']['fields'] ) );
		$this->assertSame( DSF_Translation_Contract::FORMAT_HTML, $described['items']['fields']['answer']['format'] );

		$plan_fields = array_keys( $described['plans']['fields'] );
		$this->assertContains( 'name', $plan_fields );
		$this->assertContains( 'priceSuffix', $plan_fields );
		$this->assertNotContains( 'monthlyPrice', $plan_fields, 'Amounts are canonical commerce data.' );
		$this->assertNotContains( 'annualPrice', $plan_fields );
		$this->assertNotContains( 'buttonUrl', $plan_fields );
		$this->assertNotContains( 'popular', $plan_fields );

		$this->assertSame( array( 'label' ), array_keys( $described['links']['fields'] ) );
		$this->assertSame( array(), $described['logos']['fields'], 'A logo grid holds only images and links.' );
	}

	public function test_nested_menu_structures_declare_copy_at_every_level() {
		$described = $this->describe( array( 'menuItems' => array( 'type' => 'mega_menu' ) ) );
		$fields    = $described['menuItems']['fields'];

		$this->assertSame( array( 'label', 'columns', 'banner' ), array_keys( $fields ) );
		$this->assertSame( array( 'heading', 'links' ), array_keys( $fields['columns']['fields'] ) );
		$this->assertSame( array( 'label' ), array_keys( $fields['columns']['fields']['links']['fields'] ) );
		$this->assertSame( 'map', $fields['banner']['kind'] );
		$this->assertSame( array( 'title' ), array_keys( $fields['banner']['fields'] ) );
	}

	public function test_a_block_may_declare_or_refuse_translation_at_registration() {
		$described = $this->describe(
			array(
				'trackingLabel' => array(
					'type'         => 'text',
					'translatable' => false,
				),
				'quotes'        => array(
					'type'         => 'acme_quotes',
					'translatable' => DSF_Translation_Contract::items(
						array(
							'quote' => DSF_Translation_Contract::text(),
							'body'  => DSF_Translation_Contract::html(),
						)
					),
				),
				'mystery'       => array( 'type' => 'acme_unknown' ),
			)
		);

		$this->assertSame( array( 'quotes' ), array_keys( $described ) );
		$this->assertSame( array( 'quote', 'body' ), array_keys( $described['quotes']['fields'] ) );
	}

	public function test_block_level_overrides_win_over_the_type_default() {
		$described = DSF_Translation_Contract::describe_block(
			'form-embed',
			array( 'settings' => array( 'embedCode' => array( 'type' => 'wysiwyg' ) ) )
		);

		$this->assertSame( array(), $described );
	}

	public function test_filtered_descriptors_are_rebuilt_and_cannot_invent_settings() {
		$settings = array(
			'title'  => array( 'type' => 'text' ),
			'nested' => array( 'type' => 'text' ),
		);
		WP_Mock::onFilter( 'dsf_block_translatable_settings' )
			->with(
				array(
					'title'  => DSF_Translation_Contract::text(),
					'nested' => DSF_Translation_Contract::text(),
				),
				'acme-block',
				array( 'settings' => $settings )
			)
			->reply(
				array(
					'title'    => array( 'kind' => 'value', 'format' => 'html' ),
					'ghost'    => DSF_Translation_Contract::text(),
					'password' => 'not-a-descriptor',
					'nested'   => array( 'kind' => 'wormhole' ),
				)
			);

		$described = $this->describe( $settings );

		$this->assertSame( array( 'title' ), array_keys( $described ) );
		$this->assertSame( DSF_Translation_Contract::FORMAT_HTML, $described['title']['format'] );
	}

	public function test_malformed_descriptors_are_discarded() {
		$this->assertNull( DSF_Translation_Contract::normalize_descriptor( 'text' ) );
		$this->assertNull( DSF_Translation_Contract::normalize_descriptor( array( 'kind' => 'exec' ) ) );
		$this->assertNull( DSF_Translation_Contract::normalize_descriptor( array( 'kind' => 'value' ), DSF_Translation_Contract::MAX_DEPTH + 1 ) );
		$this->assertSame(
			DSF_Translation_Contract::FORMAT_TEXT,
			DSF_Translation_Contract::normalize_descriptor( array( 'kind' => 'value', 'format' => 'javascript' ) )['format'],
			'An unknown format degrades to plain text rather than rich text.'
		);
		$this->assertSame(
			array(),
			DSF_Translation_Contract::normalize_descriptor(
				array(
					'kind'   => 'list',
					'fields' => array( 'url' => DSF_Translation_Contract::text(), 12 => DSF_Translation_Contract::text() ),
				)
			)['fields']
		);
	}
}
