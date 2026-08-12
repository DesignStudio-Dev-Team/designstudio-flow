<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlays.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-overlay-admin.php';

/** Relationship double for overlay admin tests. */
class DSF_Overlay_Admin_Test_Relationships {
	public $members = array();
	public $created = array();
	public $added   = array();

	public function find_by_object( $kind, $subtype, $id ) {
		foreach ( $this->members as $member ) {
			if ( $member['object_kind'] === $kind && $member['object_subtype'] === $subtype && (int) $member['object_id'] === (int) $id ) {
				return $member;
			}
		}
		return null;
	}

	public function find_member( $group, $language ) {
		foreach ( $this->members as $member ) {
			if ( $member['group_uuid'] === $group && $member['language'] === $language ) {
				return $member;
			}
		}
		return null;
	}

	public function create_group( $kind, $subtype, $id, $language ) {
		$member          = array(
			'group_uuid'     => 'group-' . count( $this->members ),
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'object_id'      => (int) $id,
			'language'       => $language,
		);
		$this->created[] = $member;
		$this->members[] = $member;
		return $member;
	}

	public function add_member( $group, $kind, $subtype, $id, $language ) {
		$member          = array(
			'group_uuid'     => $group,
			'object_kind'    => $kind,
			'object_subtype' => $subtype,
			'object_id'      => (int) $id,
			'language'       => $language,
		);
		$this->added[]   = $member;
		$this->members[] = $member;
		return $member;
	}
}

/** Routing double recording catalog route syncs. */
class DSF_Overlay_Admin_Test_Routing {
	public $synced = array();

	public function sync_overlay_route( $subtype, $canonical_id, $language ) {
		$this->synced[] = array( $subtype, (int) $canonical_id, $language );
		return array( 'path' => 'product/trail-runner' );
	}
}

/** Workflow double recording review clears. */
class DSF_Overlay_Admin_Test_Workflow {
	public $cleared = array();
	public $facts   = array();

	public function get_facts( $group, $language ) {
		unset( $group, $language );
		return $this->facts;
	}

	public function clear_review( $group, $language, $auth ) {
		unset( $auth );
		$this->cleared[] = array( $group, $language );
		return true;
	}
}

/**
 * Covers the catalog translation authoring form.
 *
 * The form is the one place a human types catalog translations, so it is also
 * the place where an unexpected POST field could reach commerce data. It must
 * not: everything goes through the overlay sanitizer, and every save returns
 * the overlay to unreviewed.
 */
class Test_DSF_Translation_Overlay_Admin extends TestCase {

	/** @var DSF_Overlay_Admin_Test_Relationships */
	private $relationships;

	/** @var DSF_Overlay_Admin_Test_Workflow */
	private $workflow;

	/** @var array<int,array<string,mixed>> */
	private $post_meta = array();

	/** @var bool */
	private $can_edit = true;

	/** @var DSF_Overlay_Admin_Test_Routing */
	private $routing;

	/** @var DSF_Translation_Overlay_Admin */
	private $admin;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->relationships = new DSF_Overlay_Admin_Test_Relationships();
		$this->workflow      = new DSF_Overlay_Admin_Test_Workflow();
		$this->routing       = new DSF_Overlay_Admin_Test_Routing();
		$this->admin         = new DSF_Translation_Overlay_Admin(
			array(
				'relationships' => $this->relationships,
				'workflow'      => $this->workflow,
				'routing'       => $this->routing,
			)
		);

		$this->post_meta = array();
		$this->can_edit  = true;

		$post_meta = &$this->post_meta;
		$can_edit  = &$this->can_edit;

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $v ) {
					return abs( (int) $v );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $v ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_text_field',
			array(
				'return' => static function ( $v ) {
					return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) );
				},
			)
		);
		WP_Mock::userFunction(
			'wp_kses_post',
			array(
				'return' => static function ( $v ) {
					return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $v );
				},
			)
		);
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_verify_nonce',
			array(
				'return' => static function ( $nonce ) {
					return 'valid-nonce' === $nonce;
				},
			)
		);
		WP_Mock::userFunction(
			'current_user_can',
			array(
				'return' => static function () use ( &$can_edit ) {
					return $can_edit;
				},
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $id, $key = '', $single = false ) use ( &$post_meta ) {
					unset( $single );
					return $post_meta[ (int) $id ][ $key ] ?? '';
				},
			)
		);
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'return' => static function ( $id, $key, $value ) use ( &$post_meta ) {
					$post_meta[ (int) $id ][ $key ] = $value;
					return true;
				},
			)
		);
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) {
					if ( DSF_Multilingual_Settings::OPTION_NAME === $key ) {
						return array(
							'enabled'           => true,
							'main_language'     => 'en-US',
							'migration_state'   => 'complete',
							'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
							'languages'         => array(
								array(
									'code'   => 'en-US',
									'prefix' => '',
								),
								array(
									'code'   => 'es-MX',
									'prefix' => 'es',
								),
							),
						);
					}
					return $default;
				},
			)
		);
	}

	public function tearDown(): void {
		unset( $_POST );
		$_POST = array();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Submit the product form.
	 *
	 * @param array  $fields Posted overlay fields.
	 * @param string $nonce  Posted nonce.
	 */
	private function submit( $fields, $nonce = 'valid-nonce' ) {
		$_POST = array(
			DSF_Translation_Overlay_Admin::NONCE_FIELD => $nonce,
			DSF_Translation_Overlay_Admin::FIELD_NAME  => $fields,
		);
		$this->admin->save_product( 4321 );
	}

	public function test_a_submitted_translation_is_stored_and_grouped() {
		$this->submit(
			array(
				'es-MX' => array(
					'title'   => 'Corredor de Sendero',
					'excerpt' => 'Zapato ligero',
					'content' => '<p>Para largas distancias.</p>',
				),
			)
		);

		$stored = DSF_Translation_Overlays::get_fields( 'product', 4321, 'es-MX' );
		$this->assertSame( 'Corredor de Sendero', $stored['title'] );
		$this->assertSame( 'Zapato ligero', $stored['excerpt'] );

		// The canonical object becomes the main-language member of its own group.
		$this->assertCount( 1, $this->relationships->created );
		$this->assertSame( 'en-US', $this->relationships->created[0]['language'] );
		$this->assertCount( 1, $this->relationships->added );
		$this->assertSame( 'es-MX', $this->relationships->added[0]['language'] );

		// The prefixed catalog URL is created alongside the translation.
		$this->assertSame( array( array( 'product', 4321, 'es-MX' ) ), $this->routing->synced );
	}

	public function test_editing_a_translation_returns_it_to_unreviewed() {
		$this->submit( array( 'es-MX' => array( 'title' => 'Corredor' ) ) );
		$this->assertCount( 1, $this->workflow->cleared, 'New text has not been reviewed by anybody.' );

		// Re-submitting the same text is not an edit and must not clear a review.
		$this->workflow->cleared = array();
		$this->submit( array( 'es-MX' => array( 'title' => 'Corredor' ) ) );
		$this->assertSame( array(), $this->workflow->cleared );
	}

	public function test_commerce_fields_posted_alongside_the_form_are_discarded() {
		$this->submit(
			array(
				'es-MX' => array(
					'title'          => 'Corredor',
					'sku'            => 'HACKED',
					'price'          => '0.01',
					'stock_quantity' => 0,
					'post_status'    => 'draft',
				),
			)
		);

		$stored = DSF_Translation_Overlays::get_fields( 'product', 4321, 'es-MX' );
		$this->assertSame( array( 'title' ), array_keys( $stored ) );
		foreach ( array( 'sku', 'price', 'stock_quantity', 'post_status' ) as $operational ) {
			$this->assertArrayNotHasKey( $operational, $stored );
		}
	}

	public function test_submitted_markup_is_sanitized() {
		$this->submit(
			array(
				'es-MX' => array(
					'title'   => '<script>alert(1)</script>Corredor',
					'content' => '<p>Bien</p><script>alert(2)</script>',
				),
			)
		);

		$stored = DSF_Translation_Overlays::get_fields( 'product', 4321, 'es-MX' );
		$this->assertStringNotContainsString( '<script', $stored['title'] );
		$this->assertStringNotContainsString( '<script', $stored['content'] );
	}

	public function test_a_missing_or_invalid_nonce_writes_nothing() {
		$this->submit( array( 'es-MX' => array( 'title' => 'Corredor' ) ), 'wrong-nonce' );
		$this->assertSame( array(), $this->post_meta );

		$_POST = array();
		$this->admin->save_product( 4321 );
		$this->assertSame( array(), $this->post_meta );
	}

	public function test_a_user_without_permission_writes_nothing() {
		$this->can_edit = false;
		$this->submit( array( 'es-MX' => array( 'title' => 'Corredor' ) ) );

		$this->assertSame( array(), $this->post_meta );
		$this->assertSame( array(), $this->relationships->created );
	}

	public function test_unknown_and_main_languages_are_ignored() {
		$this->submit(
			array(
				'en-US'    => array( 'title' => 'Overwrite the source' ),
				'de-DE'    => array( 'title' => 'Not enabled' ),
				'<script>' => array( 'title' => 'Malformed' ),
				'es-MX'    => array( 'title' => 'Corredor' ),
			)
		);

		$stored = $this->post_meta[4321][ DSF_Translation_Overlays::META_KEY ];
		$this->assertSame( array( 'es-MX' ), array_keys( $stored ), 'Only enabled secondary languages are writable here.' );
	}
}
