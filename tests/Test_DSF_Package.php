<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-package.php';

/**
 * Unit coverage for the whole-site packager's load-bearing, DB-free logic:
 * the Zip-Slip guard, the precise ID-reference remap, the never-export-secrets
 * rule (including a hostile filter), and the template-assignment slug fallback.
 */
class Test_DSF_Package extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__', array( 'return' => static fn( $t, $d = null ) => $t ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/* ---- Zip-Slip guard ------------------------------------------------ */

	/**
	 * @dataProvider zip_entry_provider
	 */
	public function test_zip_entry_guard( $name, $expected ) {
		$this->assertSame( $expected, $this->invoke( 'is_safe_zip_entry', $name ) );
	}

	public function zip_entry_provider() {
		return array(
			'manifest'          => array( 'manifest.json', true ),
			'media file'        => array( 'media/abc123.jpg', true ),
			'parent traversal'  => array( '../evil.php', false ),
			'nested traversal'  => array( 'media/../../evil.php', false ),
			'absolute unix'     => array( '/etc/passwd', false ),
			'windows drive'     => array( 'C:\\windows\\system32', false ),
			'backslash escape'  => array( 'media\\..\\..\\evil', false ),
			'null byte'         => array( "media/x.jpg\0.php", false ),
			'empty'             => array( '', false ),
		);
	}

	/* ---- ID reference remap ------------------------------------------- */

	public function test_remap_rewrites_only_known_reference_keys() {
		$ref_maps = array(
			'headerTemplateId' => array( 11 => 111 ),
			'footerTemplateId' => array( 12 => 122 ),
			'formId'           => array( 5 => 55 ),
			'popupId'          => array( 7 => 77 ),
		);

		$value = array(
			'layout' => array(
				'headerTemplateId' => 11,
				'footerTemplateId' => 12,
			),
			'blocks' => array(
				array( 'type' => 'form', 'formId' => 5, 'spacing' => 11 ),   // spacing 11 must NOT be remapped
				array( 'type' => 'button', 'popupId' => '7', 'radius' => 12 ), // numeric-string id remaps; radius stays
				array( 'type' => 'text', 'headerTemplateId' => 999 ),         // 999 not in map → unchanged
			),
		);

		$result = $this->remap( $value, $ref_maps );

		$this->assertSame( 111, $result['layout']['headerTemplateId'] );
		$this->assertSame( 122, $result['layout']['footerTemplateId'] );
		$this->assertSame( 55, $result['blocks'][0]['formId'] );
		$this->assertSame( 11, $result['blocks'][0]['spacing'], 'unrelated numeric must be untouched' );
		$this->assertSame( 77, $result['blocks'][1]['popupId'], 'numeric-string id remaps' );
		$this->assertSame( 12, $result['blocks'][1]['radius'], 'unrelated numeric must be untouched' );
		$this->assertSame( 999, $result['blocks'][2]['headerTemplateId'], 'id not in map stays as-is' );
	}

	public function test_remap_reports_changed_flag() {
		$ref_maps = array( 'formId' => array( 5 => 55 ) );

		$changed = false;
		$this->remap( array( 'formId' => 5 ), $ref_maps, $changed );
		$this->assertTrue( $changed );

		$changed = false;
		$this->remap( array( 'formId' => 999, 'spacing' => 5 ), $ref_maps, $changed );
		$this->assertFalse( $changed, 'nothing matched → no change' );
	}

	/* ---- Secrets never leave the site --------------------------------- */

	public function test_settings_keys_exclude_secrets_even_when_filter_injects_them() {
		// A hostile/careless filter tries to add secrets to the allow-list.
		WP_Mock::userFunction(
			'apply_filters',
			array(
				'return' => static function ( $tag, $value ) {
					return array_merge( (array) $value, array( 'dsf_github_token', 'dsf_recaptcha_secret_key', 'dsf_mail_smtp' ) );
				},
			)
		);

		$keys = $this->invoke( 'settings_option_keys' );

		$this->assertContains( 'dsf_typography', $keys );
		$this->assertContains( 'dsf_default_header_id', $keys );
		foreach ( array( 'dsf_github_token', 'dsf_recaptcha_secret_key', 'dsf_recaptcha_site_key', 'dsf_mail_smtp' ) as $secret ) {
			$this->assertNotContains( $secret, $keys, $secret . ' must never be exportable' );
		}
	}

	/* ---- Template assignment: slug resolution + fallback -------------- */

	public function test_assignment_falls_back_to_all_when_no_terms_resolve() {
		WP_Mock::userFunction( 'taxonomy_exists', array( 'return' => true ) );
		WP_Mock::userFunction( 'sanitize_title', array( 'return' => static fn( $s ) => $s ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
		// Destination site has none of the exported product categories.
		WP_Mock::userFunction( 'get_term_by', array( 'return' => false ) );

		$saved = array();
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'return' => static function ( $id, $key, $val ) use ( &$saved ) {
					$saved = array( $id, $key, $val );
					return true;
				},
			)
		);

		$conf = array( 'assignment_meta' => '_dsf_pt_assignment', 'taxonomy' => 'product_cat' );
		$this->invoke_on(
			$this->instance(),
			'apply_assignment',
			99,
			array( 'mode' => 'categories', 'categorySlugs' => array( 'hoodies', 'shoes' ) ),
			$conf
		);

		$this->assertSame( '_dsf_pt_assignment', $saved[1] );
		$this->assertSame( 'all', $saved[2]['mode'], 'unresolved slugs collapse to all' );
		$this->assertSame( array(), $saved[2]['categoryIds'] );
	}

	public function test_assignment_resolves_slugs_to_local_term_ids() {
		WP_Mock::userFunction( 'taxonomy_exists', array( 'return' => true ) );
		WP_Mock::userFunction( 'sanitize_title', array( 'return' => static fn( $s ) => $s ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
		WP_Mock::userFunction(
			'get_term_by',
			array(
				'return' => static function ( $field, $slug, $tax ) {
					$ids = array( 'hoodies' => 42, 'shoes' => 43 );
					return isset( $ids[ $slug ] ) ? (object) array( 'term_id' => $ids[ $slug ] ) : false;
				},
			)
		);

		$saved = array();
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'return' => static function ( $id, $key, $val ) use ( &$saved ) {
					$saved = array( $id, $key, $val );
					return true;
				},
			)
		);

		$conf = array( 'assignment_meta' => '_dsf_bt_assignment', 'taxonomy' => 'category' );
		$this->invoke_on(
			$this->instance(),
			'apply_assignment',
			7,
			array( 'mode' => 'categories', 'categorySlugs' => array( 'hoodies', 'shoes' ) ),
			$conf
		);

		$this->assertSame( 'categories', $saved[2]['mode'] );
		$this->assertSame( array( 42, 43 ), $saved[2]['categoryIds'] );
	}

	/* ---- selective export --------------------------------------------- */

	public function test_parse_selection_casts_ids_and_reads_toggles() {
		$post = array(
			'dsf_items'    => array(
				'pages' => array( '12', '15', '0', 'abc', '15' ), // dup + non-int + zero pruned
				'posts' => array( '7' ),
			),
			'dsf_settings' => '1',
			// dsf_redirects intentionally absent → false
		);

		$selection = $this->invoke( 'parse_selection', $post );

		$this->assertSame( array( 12, 15 ), $selection['items']['pages'] );
		$this->assertSame( array( 7 ), $selection['items']['posts'] );
		$this->assertSame( array(), $selection['items']['forms'], 'unlisted domains default to empty' );
		$this->assertTrue( $selection['settings'] );
		$this->assertFalse( $selection['redirects'] );
	}

	public function test_selected_ids_null_selection_means_everything() {
		$this->assertNull( $this->invoke( 'selected_ids', 'pages', null ) );

		$selection = array( 'items' => array( 'pages' => array( 3, 4 ) ), 'settings' => false, 'redirects' => false );
		$this->assertSame( array( 3, 4 ), $this->invoke( 'selected_ids', 'pages', $selection ) );
		$this->assertSame( array(), $this->invoke( 'selected_ids', 'posts', $selection ), 'a domain absent from the selection exports nothing' );
	}

	public function test_selection_is_empty() {
		$empty = array( 'items' => array( 'pages' => array(), 'posts' => array() ), 'settings' => false, 'redirects' => false );
		$this->assertTrue( $this->invoke( 'selection_is_empty', $empty ) );

		$has_item = array( 'items' => array( 'pages' => array( 9 ) ), 'settings' => false, 'redirects' => false );
		$this->assertFalse( $this->invoke( 'selection_is_empty', $has_item ) );

		$only_redirects = array( 'items' => array( 'pages' => array() ), 'settings' => false, 'redirects' => true );
		$this->assertFalse( $this->invoke( 'selection_is_empty', $only_redirects ) );
	}

	/* ---- helpers ------------------------------------------------------ */

	private function instance() {
		return ( new ReflectionClass( 'DSF_Package' ) )->newInstanceWithoutConstructor();
	}

	private function invoke( $method_name, ...$arguments ) {
		return $this->invoke_on( $this->instance(), $method_name, ...$arguments );
	}

	private function invoke_on( $instance, $method_name, ...$arguments ) {
		$method = ( new ReflectionClass( 'DSF_Package' ) )->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $arguments );
	}

	private function remap( $value, $ref_maps, &$changed = false ) {
		$method = ( new ReflectionClass( 'DSF_Package' ) )->getMethod( 'remap_ids_in_value' );
		$method->setAccessible( true );
		$args = array( $value, $ref_maps, &$changed );
		return $method->invokeArgs( $this->instance(), $args );
	}
}
