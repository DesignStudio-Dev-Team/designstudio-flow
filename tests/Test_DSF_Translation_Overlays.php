<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlays.php';

/**
 * Covers the WooCommerce catalog overlay model.
 *
 * The whole point of an overlay is that a translation never duplicates an
 * operational record, so these tests care most about what an overlay refuses to
 * carry: prices, SKUs, stock, attributes, and identity.
 */
class Test_DSF_Translation_Overlays extends TestCase {

	/** @var array<int,array<string,mixed>> */
	private $post_meta = array();

	/** @var array<int,array<string,mixed>> */
	private $term_meta = array();

	/** @var array<int,object> */
	private $posts = array();

	/** @var bool */
	private $can_edit = true;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->post_meta = array();
		$this->term_meta = array();
		$this->can_edit  = true;
		$this->posts     = array(
			4321 => (object) array(
				'ID'           => 4321,
				'post_type'    => 'product',
				'post_title'   => 'Trail Runner',
				'post_excerpt' => 'Lightweight shoe',
				'post_content' => '<p>Built for long distances.</p>',
			),
		);

		$post_meta = &$this->post_meta;
		$term_meta = &$this->term_meta;
		$posts     = &$this->posts;
		$can_edit  = &$this->can_edit;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return' => static function ( $v ) {
			return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', preg_replace( '/ on[a-z]+="[^"]*"/i', '', (string) $v ) );
		} ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => static function () use ( &$can_edit ) { return $can_edit; } ) );
		WP_Mock::userFunction( 'get_post', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			return $posts[ (int) $id ] ?? null;
		} ) );
		WP_Mock::userFunction( 'get_post_type', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			return isset( $posts[ (int) $id ] ) ? $posts[ (int) $id ]->post_type : false;
		} ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => static function ( $id, $key = '', $single = false ) use ( &$post_meta ) {
			unset( $single );
			return $post_meta[ (int) $id ][ $key ] ?? '';
		} ) );
		WP_Mock::userFunction( 'update_post_meta', array( 'return' => static function ( $id, $key, $value ) use ( &$post_meta ) {
			$post_meta[ (int) $id ][ $key ] = $value;
			return true;
		} ) );
		WP_Mock::userFunction( 'get_term_meta', array( 'return' => static function ( $id, $key = '', $single = false ) use ( &$term_meta ) {
			unset( $single );
			return $term_meta[ (int) $id ][ $key ] ?? '';
		} ) );
		WP_Mock::userFunction( 'update_term_meta', array( 'return' => static function ( $id, $key, $value ) use ( &$term_meta ) {
			$term_meta[ (int) $id ][ $key ] = $value;
			return true;
		} ) );
		WP_Mock::userFunction( 'get_term', array( 'return' => static function ( $id, $taxonomy = '' ) {
			unset( $taxonomy );
			return 55 === (int) $id ? (object) array( 'term_id' => 55, 'name' => 'Running', 'description' => 'Road shoes', 'slug' => 'running' ) : null;
		} ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => static function ( $v ) { return $v instanceof WP_Error; } ) );
		WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
		// Multilingual mode is off in these tests, so display resolution always
		// falls back to the canonical catalog text.
		WP_Mock::userFunction( 'get_option', array( 'return' => static function ( $key, $default = false ) {
			unset( $key );
			return $default;
		} ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_overlay_identity_round_trips_per_language() {
		$spanish = DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' );
		$french  = DSF_Translation_Overlays::overlay_id( 4321, 'fr' );

		$this->assertGreaterThan( 0, $spanish );
		$this->assertNotSame( $spanish, $french, 'Each language needs its own relationship row.' );
		$this->assertSame(
			array( 'canonical_id' => 4321, 'language' => 'es-MX' ),
			DSF_Translation_Overlays::decode( $spanish )
		);
		$this->assertSame(
			array( 'canonical_id' => 4321, 'language' => 'fr' ),
			DSF_Translation_Overlays::decode( $french )
		);
	}

	public function test_invalid_identities_are_refused() {
		$this->assertSame( 0, DSF_Translation_Overlays::overlay_id( 0, 'es-MX' ) );
		$this->assertSame( 0, DSF_Translation_Overlays::overlay_id( 4321, 'de-XX' ) );
		$this->assertSame( 0, DSF_Translation_Overlays::overlay_id( 4321, '<script>' ) );
		$this->assertSame( array( 'canonical_id' => 0, 'language' => '' ), DSF_Translation_Overlays::decode( 0 ) );
		$this->assertSame( array( 'canonical_id' => 0, 'language' => '' ), DSF_Translation_Overlays::decode( 99 ) );
	}

	public function test_an_overlay_can_only_carry_display_text() {
		$stored = DSF_Translation_Overlays::save_fields(
			'product',
			4321,
			'es-MX',
			array(
				'title'          => 'Corredor de Sendero',
				'excerpt'        => '<p>Zapato ligero</p>',
				'content'        => '<p>Hecho para largas distancias.</p>',
				'sku'            => 'CHANGED-SKU',
				'price'          => '1.00',
				'regular_price'  => '1.00',
				'stock_quantity' => 9999,
				'attributes'     => array( 'pa_size' => '42' ),
				'post_status'    => 'draft',
			)
		);

		$this->assertSame( array( 'title', 'excerpt', 'content' ), array_keys( $stored ) );
		foreach ( array( 'sku', 'price', 'regular_price', 'stock_quantity', 'attributes', 'post_status' ) as $operational ) {
			$this->assertArrayNotHasKey( $operational, $stored, $operational . ' is operational commerce data.' );
		}
		$this->assertSame(
			$stored,
			DSF_Translation_Overlays::get_fields( 'product', 4321, 'es-MX' )
		);
	}

	public function test_overlay_text_is_sanitized_on_the_way_in() {
		$stored = DSF_Translation_Overlays::save_fields(
			'product',
			4321,
			'es-MX',
			array(
				'title'   => '<script>alert(1)</script>Zapato',
				'content' => '<p onclick="alert(1)">Texto</p><script>alert(2)</script>',
			)
		);

		$this->assertStringNotContainsString( '<script', $stored['title'] );
		$this->assertStringNotContainsString( '<script', $stored['content'] );
		$this->assertStringNotContainsString( 'onclick', $stored['content'] );
		$this->assertStringContainsString( 'Texto', $stored['content'] );
	}

	public function test_languages_are_stored_side_by_side_without_touching_the_canonical_fields() {
		DSF_Translation_Overlays::save_fields( 'product', 4321, 'es-MX', array( 'title' => 'Corredor' ) );
		DSF_Translation_Overlays::save_fields( 'product', 4321, 'fr', array( 'title' => 'Coureur' ) );

		$this->assertSame( 'Corredor', DSF_Translation_Overlays::get_fields( 'product', 4321, 'es-MX' )['title'] );
		$this->assertSame( 'Coureur', DSF_Translation_Overlays::get_fields( 'product', 4321, 'fr' )['title'] );
		$this->assertSame( 'Trail Runner', $this->posts[4321]->post_title, 'The canonical product is never rewritten.' );
		$this->assertSame(
			array( DSF_Translation_Overlays::META_KEY ),
			array_keys( $this->post_meta[4321] ),
			'Only the overlay meta key is written.'
		);
	}

	public function test_saving_requires_permission_and_a_valid_target() {
		$this->can_edit = false;
		$forbidden      = DSF_Translation_Overlays::save_fields( 'product', 4321, 'es-MX', array( 'title' => 'x' ) );
		$this->assertInstanceOf( 'WP_Error', $forbidden );
		$this->assertSame( 'dsf_overlay_forbidden', $forbidden->get_error_code() );

		$this->can_edit = true;
		foreach ( array( array( 'shop_order', 4321, 'es-MX' ), array( 'product', 0, 'es-MX' ), array( 'product', 4321, 'de-XX' ) ) as $case ) {
			$result = DSF_Translation_Overlays::save_fields( $case[0], $case[1], $case[2], array( 'title' => 'x' ) );
			$this->assertInstanceOf( 'WP_Error', $result );
			$this->assertSame( 'dsf_overlay_identity', $result->get_error_code() );
		}
		$this->assertSame( array(), $this->post_meta );
	}

	public function test_operational_post_types_can_never_become_overlay_subtypes() {
		$subtypes = DSF_Translation_Overlays::subtypes();

		$this->assertContains( 'product', $subtypes );
		$this->assertContains( 'product_cat', $subtypes );
		$this->assertNotContains( 'shop_order', $subtypes );
		$this->assertNotContains( 'shop_order_refund', $subtypes );
		$this->assertNotContains( 'shop_coupon', $subtypes );
	}

	public function test_the_fingerprint_covers_visitor_text_only() {
		$overlays = DSF_Translation_Overlays::get_instance();
		$payload  = $overlays->fingerprint_payload(
			array(),
			array(
				'object_kind'    => DSF_Translation_Overlays::KIND,
				'object_subtype' => 'product',
				'object_id'      => DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' ),
			)
		);

		$this->assertSame( array( 'object_kind', 'object_subtype', 'title', 'excerpt', 'content' ), array_keys( $payload ) );
		$this->assertSame( 'Trail Runner', $payload['title'] );
		// A price or stock edit must not mark a reviewed description stale.
		$this->assertArrayNotHasKey( 'price', $payload );
		$this->assertArrayNotHasKey( 'sku', $payload );
	}

	public function test_term_overlays_translate_labels_but_never_slugs() {
		$stored = DSF_Translation_Overlays::save_fields(
			'product_cat',
			55,
			'es-MX',
			array( 'title' => 'Correr', 'content' => 'Zapatos de carretera', 'slug' => 'correr' )
		);

		$this->assertSame( array( 'title', 'content' ), array_keys( $stored ) );
		$this->assertArrayNotHasKey( 'slug', $stored, 'A slug participates in variation matching and filter URLs.' );

		$overlays = DSF_Translation_Overlays::get_instance();
		$term     = $overlays->filter_term( (object) array( 'term_id' => 55, 'name' => 'Running', 'description' => 'Road shoes', 'slug' => 'running' ), 'product_cat' );
		$this->assertSame( 'running', $term->slug );
	}

	public function test_display_falls_back_to_canonical_text_without_multilingual_context() {
		$overlays = DSF_Translation_Overlays::get_instance();
		$overlays->flush();

		// No language context is active in this test, so nothing is swapped.
		$this->assertSame( array(), $overlays->resolve_display( 'product', 4321 ) );
		$this->assertSame( 'Trail Runner', $overlays->filter_post_title( 'Trail Runner', 4321 ) );
		$this->assertSame( 'Lightweight shoe', $overlays->filter_product_excerpt( 'Lightweight shoe', null ) );
	}

	public function test_unknown_overlay_objects_are_not_reported_as_existing() {
		$overlays = DSF_Translation_Overlays::get_instance();

		$this->assertTrue(
			$overlays->object_exists( false, 'overlay', 'product', DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' ) )
		);
		$this->assertFalse( $overlays->object_exists( false, 'overlay', 'product', DSF_Translation_Overlays::overlay_id( 999, 'es-MX' ) ) );
		$this->assertFalse( $overlays->object_exists( false, 'overlay', 'shop_order', DSF_Translation_Overlays::overlay_id( 4321, 'es-MX' ) ) );
		$this->assertFalse( $overlays->object_exists( false, 'post', 'product', 4321 ) );
	}
}
