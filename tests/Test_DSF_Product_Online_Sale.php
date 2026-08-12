<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-product-templates.php';

/**
 * Minimal stand-in for WC_Product covering only what the online-sale gate reads.
 */
class DSF_Fake_Product {
	private $id;
	private $price;

	public function __construct( $id, $price ) {
		$this->id    = $id;
		$this->price = $price;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_price() {
		return $this->price;
	}
}

/**
 * Coverage for the "may this product be sold online?" gate that every product
 * block funnels through.
 *
 * Two independent things suppress the price and the add-to-cart control: a
 * product with no price at all, and the Syndified plugin flagging a product as
 * not sellable by this dealer (`dswaves_can_purchase` !== 'Yes'). The critical
 * negative case is a *missing* flag: most products on a live site have never
 * been synced with it, and reading "absent" as "no" would strip prices from the
 * entire catalog.
 */
class Test_DSF_Product_Online_Sale extends TestCase {

	/** Meta returned by the mocked get_post_meta(), keyed post_id => key => value. */
	private $meta = array();

	/** Whether the mocked environment reports Syndified as active. */
	private $syndified_active = true;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);

		$meta = &$this->meta;
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key, $single = false ) use ( &$meta ) {
					return isset( $meta[ $post_id ][ $key ] ) ? $meta[ $post_id ][ $key ] : '';
				},
			)
		);

		// The Syndified plugin is not loaded in the test suite, so class-based
		// detection reports false; the detection filter is the seam that lets each
		// test choose. WP_Mock passes every other filter's value through untouched.
		$this->set_syndified_active( true );
	}

	/** Point the Syndified-detection seam at $active for the current test. */
	private function set_syndified_active( $active ) {
		$this->syndified_active = $active;
		WP_Mock::onFilter( 'dsf_syndified_active' )->with( false )->reply( (bool) $active );
	}

	public function tearDown(): void {
		$this->meta             = array();
		$this->syndified_active = true;
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_priced_product_without_syndified_flag_is_sold_online() {
		$product = new DSF_Fake_Product( 10, '49.00' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_product_with_no_price_is_not_sold_online() {
		$product = new DSF_Fake_Product( 11, '' );
		$this->assertFalse( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_zero_price_product_is_still_sold_online() {
		// A genuine free product has a price of "0", which is not the same as
		// having no price at all.
		$product = new DSF_Fake_Product( 12, '0' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_syndified_no_flag_suppresses_a_priced_product() {
		$this->meta[13] = array( 'dswaves_can_purchase' => 'No' );
		$product        = new DSF_Fake_Product( 13, '49.00' );
		$this->assertFalse( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_syndified_yes_flag_keeps_a_priced_product_sellable() {
		$this->meta[14] = array( 'dswaves_can_purchase' => 'Yes' );
		$product        = new DSF_Fake_Product( 14, '49.00' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_syndified_flag_is_matched_case_insensitively() {
		$this->meta[15] = array( 'dswaves_can_purchase' => 'yes' );
		$product        = new DSF_Fake_Product( 15, '49.00' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_missing_syndified_flag_does_not_suppress_the_product() {
		// Products the syndication plugin does not manage carry no flag at all;
		// treating that as "No" would blank prices across the whole catalog.
		$this->meta[16] = array( 'some_other_meta' => 'x' );
		$product        = new DSF_Fake_Product( 16, '49.00' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_syndified_flag_is_ignored_when_the_plugin_is_inactive() {
		// Stale meta left behind by a deactivated plugin must not keep suppressing.
		$this->set_syndified_active( false );
		$this->meta[17] = array( 'dswaves_can_purchase' => 'No' );
		$product        = new DSF_Fake_Product( 17, '49.00' );
		$this->assertTrue( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_no_price_still_wins_when_syndified_allows_selling() {
		$this->meta[18] = array( 'dswaves_can_purchase' => 'Yes' );
		$product        = new DSF_Fake_Product( 18, '' );
		$this->assertFalse( DSF_Product_Templates::is_sold_online( $product ) );
	}

	public function test_non_product_input_is_not_sold_online() {
		$this->assertFalse( DSF_Product_Templates::is_sold_online( null ) );
		$this->assertFalse( DSF_Product_Templates::is_sold_online( 'product' ) );
	}

	public function test_cta_buttons_return_only_visible_entries() {
		$this->meta[20] = array(
			'cta_buttons' => json_encode(
				array(
					array( 'label' => 'Find a Dealer', 'show' => 'Show' ),
					array( 'label' => 'Hidden CTA', 'show' => 'Hide' ),
					array( 'label' => 'Request a Quote', 'show' => 'Show' ),
				)
			),
		);

		$buttons = DSF_Product_Templates::get_syndified_cta_buttons( 20 );

		$this->assertSame(
			array( 'Find a Dealer', 'Request a Quote' ),
			array_column( $buttons, 'label' )
		);
	}

	public function test_cta_buttons_are_empty_when_syndified_is_inactive() {
		$this->set_syndified_active( false );
		$this->meta[21] = array(
			'cta_buttons' => json_encode( array( array( 'label' => 'Find a Dealer', 'show' => 'Show' ) ) ),
		);

		$this->assertSame( array(), DSF_Product_Templates::get_syndified_cta_buttons( 21 ) );
	}

	public function test_malformed_cta_buttons_meta_yields_no_buttons() {
		$this->meta[22] = array( 'cta_buttons' => 'not-json' );
		$this->assertSame( array(), DSF_Product_Templates::get_syndified_cta_buttons( 22 ) );

		$this->meta[23] = array( 'cta_buttons' => '' );
		$this->assertSame( array(), DSF_Product_Templates::get_syndified_cta_buttons( 23 ) );
	}

	public function test_cta_buttons_are_capped() {
		$raw = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$raw[] = array( 'label' => 'CTA ' . $i, 'show' => 'Show' );
		}
		$this->meta[24] = array( 'cta_buttons' => json_encode( $raw ) );

		$this->assertCount( 4, DSF_Product_Templates::get_syndified_cta_buttons( 24 ) );
	}
}
