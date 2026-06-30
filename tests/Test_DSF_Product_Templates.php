<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-product-templates.php';

/**
 * Coverage for the product-template assignment logic.
 *
 * Assignment rules decide which products a reusable single-product design
 * applies to, so a malformed or hostile rule must normalize to safe values
 * (positive category IDs only, a bounded count, and "all" when a category rule
 * names no categories so it can never silently match everything by accident).
 */
class Test_DSF_Product_Templates extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_non_array_assignment_defaults_to_all() {
		$result = DSF_Product_Templates::sanitize_assignment( 'nope' );
		$this->assertSame( 'all', $result['mode'] );
		$this->assertSame( array(), $result['categoryIds'] );
	}

	public function test_categories_mode_filters_dedupes_and_casts_ids() {
		$result = DSF_Product_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array( 5, '5', 0, -3, '7', 'abc' ),
			)
		);

		$this->assertSame( 'categories', $result['mode'] );
		$this->assertSame( array( 5, 3, 7 ), $result['categoryIds'] );
	}

	public function test_categories_mode_with_no_valid_ids_falls_back_to_all() {
		$result = DSF_Product_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array( 0, 'x', '' ),
			)
		);

		$this->assertSame( 'all', $result['mode'] );
		$this->assertSame( array(), $result['categoryIds'] );
	}

	public function test_category_id_count_is_capped() {
		$ids    = range( 1, 150 );
		$result = DSF_Product_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => $ids,
			)
		);

		$this->assertCount( 100, $result['categoryIds'] );
	}

	public function test_unknown_mode_normalizes_to_all() {
		$result = DSF_Product_Templates::sanitize_assignment( array( 'mode' => 'everything' ) );
		$this->assertSame( 'all', $result['mode'] );
	}

	public function test_get_assignment_reads_and_normalizes_stored_meta() {
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 42, '_dsf_pt_assignment', true ),
				'return' => array(
					'mode'        => 'categories',
					'categoryIds' => array( '9', 9, 0 ),
				),
			)
		);

		$result = DSF_Product_Templates::get_assignment( 42 );
		$this->assertSame( 'categories', $result['mode'] );
		$this->assertSame( array( 9 ), $result['categoryIds'] );
	}

	public function test_get_assignment_defaults_when_meta_missing() {
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( 7, '_dsf_pt_assignment', true ),
				'return' => '',
			)
		);

		$result = DSF_Product_Templates::get_assignment( 7 );
		$this->assertSame( 'all', $result['mode'] );
		$this->assertSame( array(), $result['categoryIds'] );
	}
}
