<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-cloner.php';

/** In-memory relationship reader/writer for clone tests. */
class DSF_Cloner_Test_Relationships {
	public $members = array();
	public $added   = array();
	public $fail    = false;

	public function add( $group, $kind, $subtype, $id, $language ) {
		$this->members[] = compact( 'group', 'kind', 'subtype', 'id', 'language' );
	}

	public function find_by_object( $kind, $subtype, $id ) {
		foreach ( $this->members as $member ) {
			if ( $member['kind'] === $kind && $member['subtype'] === $subtype && (int) $member['id'] === (int) $id ) {
				return $this->format( $member );
			}
		}
		return null;
	}

	public function find_member( $group, $language ) {
		foreach ( $this->members as $member ) {
			if ( $member['group'] === $group && $member['language'] === $language ) {
				return $this->format( $member );
			}
		}
		return null;
	}

	public function add_member( $group, $kind, $subtype, $id, $language ) {
		if ( $this->fail ) {
			return new WP_Error( 'dsf_translation_duplicate', 'duplicate' );
		}
		$this->add( $group, $kind, $subtype, $id, $language );
		$this->added[] = array( $group, $kind, $subtype, $id, $language );
		return $this->format( end( $this->members ) );
	}

	private function format( $member ) {
		return array(
			'group_uuid'     => $member['group'],
			'object_kind'    => $member['kind'],
			'object_subtype' => $member['subtype'],
			'object_id'      => (int) $member['id'],
			'language'       => $member['language'],
		);
	}
}

/** Routing double recording route syncs. */
class DSF_Cloner_Test_Routing {
	public $synced = array();

	public function sync_post_route( $post_id, $post = null ) {
		unset( $post );
		$this->synced[] = (int) $post_id;
		return null;
	}
}

/**
 * Covers main-language-only clone-to-draft behavior: what is copied, what is
 * mapped, what is deliberately detached, and what is refused outright.
 */
class Test_DSF_Translation_Cloner extends TestCase {

	private const GROUP = '11111111-2222-4333-8444-555555555555';

	/** @var DSF_Cloner_Test_Relationships */
	private $relationships;

	/** @var DSF_Cloner_Test_Routing */
	private $routing;

	/** @var DSF_Translation_Cloner */
	private $cloner;

	/** @var array<int,object> */
	private $posts = array();

	/** @var array<int,array<string,mixed>> */
	private $meta = array();

	/** @var array<string,mixed> */
	private $options = array();

	/** @var array<string,mixed> */
	private $transients = array();

	/** @var array<int,array<string,mixed>> */
	private $inserted = array();

	/** @var bool */
	private $can_edit = true;

	/** @var int */
	private $next_id = 500;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->relationships = new DSF_Cloner_Test_Relationships();
		$this->routing       = new DSF_Cloner_Test_Routing();
		$this->transients    = array();
		$this->inserted      = array();
		$this->can_edit      = true;

		$this->posts = array(
			10 => (object) array(
				'ID'             => 10,
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => 'About us',
				'post_name'      => 'about',
				'post_content'   => '<p>Body</p>',
				'post_excerpt'   => 'Summary',
				'post_parent'    => 0,
				'menu_order'     => 3,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			11 => (object) array(
				'ID'             => 11,
				'post_type'      => 'page',
				'post_status'    => 'draft',
				'post_title'     => 'Acerca de',
				'post_name'      => 'acerca-de',
				'post_content'   => '',
				'post_excerpt'   => '',
				'post_parent'    => 0,
				'menu_order'     => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
		);

		$this->meta = array(
			10 => array(
				'_dsf_blocks'        => array(
					array(
						'type'         => 'content',
						'savedBlockId' => 70,
						'settings'     => array( 'formId' => 80, 'content' => '<p>Hi</p>' ),
					),
				),
				'_dsf_settings'      => array(
					'layout' => array( 'headerTemplateId' => 40, 'footerTemplateId' => 41 ),
					'popupId' => 50,
					'seo'     => array( 'title' => 'About', 'canonical' => 'https://example.test/about/' ),
				),
				'_dsf_html_snapshot' => '<html>source language</html>',
				'_thumbnail_id'      => 900,
			),
		);

		$this->relationships->add( self::GROUP, 'post', 'page', 10, 'en-US' );

		$this->mock_environment();

		$this->cloner = new DSF_Translation_Cloner(
			array(
				'relationships'   => $this->relationships,
				'routing'         => $this->routing,
				'dependency_sync' => static function () {
					return null;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/** Register every WordPress function the cloner touches. */
	private function mock_environment() {
		$posts      = &$this->posts;
		$meta       = &$this->meta;
		$options    = &$this->options;
		$transients = &$this->transients;
		$inserted   = &$this->inserted;
		$can_edit   = &$this->can_edit;
		$next_id    = &$this->next_id;

		$this->options = array(
			DSF_Multilingual_Settings::OPTION_NAME => array(
				'enabled'           => true,
				'main_language'     => 'en-US',
				'migration_state'   => 'complete',
				'migration_version' => DSF_Multilingual_Settings::MIGRATION_VERSION,
				'languages'         => array(
					array( 'code' => 'en-US', 'prefix' => '' ),
					array( 'code' => 'es-MX', 'prefix' => 'es' ),
				),
			),
		);

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => static function ( $v ) { return trim( wp_strip_all_tags_local( (string) $v ) ); } ) );
		WP_Mock::userFunction( 'sanitize_title', array( 'return' => static function ( $v ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ), '-' ); } ) );
		WP_Mock::userFunction( 'is_wp_error', array( 'return' => static function ( $v ) { return $v instanceof WP_Error; } ) );
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 7 ) );
		WP_Mock::userFunction( 'get_edit_post_link', array( 'return' => 'https://example.test/wp-admin/post.php?post=1&action=edit' ) );
		WP_Mock::userFunction( 'wp_delete_post', array( 'return' => true ) );
		WP_Mock::userFunction( 'get_object_taxonomies', array( 'return' => array() ) );
		WP_Mock::userFunction( 'wp_get_object_terms', array( 'return' => array() ) );
		WP_Mock::userFunction( 'wp_set_object_terms', array( 'return' => true ) );
		WP_Mock::userFunction( 'get_site_option', array( 'return' => array() ) );
		WP_Mock::userFunction( 'remove_filter', array( 'return' => true ) );
		WP_Mock::userFunction( 'add_filter', array( 'return' => true ) );
		WP_Mock::userFunction( 'wp_get_mu_plugins', array( 'return' => array() ) );
		WP_Mock::userFunction( 'get_mu_plugins', array( 'return' => array() ) );

		WP_Mock::userFunction( 'get_post', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			$id = is_object( $id ) ? $id->ID : (int) $id;
			return $posts[ $id ] ?? null;
		} ) );
		WP_Mock::userFunction( 'get_post_type', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			$id = (int) $id;
			return isset( $posts[ $id ] ) ? $posts[ $id ]->post_type : false;
		} ) );
		WP_Mock::userFunction( 'get_post_status', array( 'return' => static function ( $id = 0 ) use ( &$posts ) {
			$id = (int) $id;
			return isset( $posts[ $id ] ) ? $posts[ $id ]->post_status : false;
		} ) );
		WP_Mock::userFunction( 'get_post_type_object', array( 'return' => (object) array( 'cap' => (object) array( 'create_posts' => 'edit_pages' ) ) ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => static function () use ( &$can_edit ) { return $can_edit; } ) );

		WP_Mock::userFunction( 'get_post_meta', array( 'return' => static function ( $id, $key = '', $single = false ) use ( &$meta ) {
			unset( $single );
			return $meta[ (int) $id ][ $key ] ?? '';
		} ) );
		WP_Mock::userFunction( 'update_post_meta', array( 'return' => static function ( $id, $key, $value ) use ( &$meta ) {
			$meta[ (int) $id ][ $key ] = $value;
			return true;
		} ) );
		WP_Mock::userFunction( 'delete_post_meta', array( 'return' => static function ( $id, $key ) use ( &$meta ) {
			unset( $meta[ (int) $id ][ $key ] );
			return true;
		} ) );

		WP_Mock::userFunction( 'get_option', array( 'return' => static function ( $key, $default = false ) use ( &$options ) {
			return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
		} ) );
		WP_Mock::userFunction( 'get_transient', array( 'return' => static function ( $key ) use ( &$transients ) {
			return array_key_exists( $key, $transients ) ? $transients[ $key ] : false;
		} ) );
		WP_Mock::userFunction( 'set_transient', array( 'return' => static function ( $key, $value ) use ( &$transients ) {
			$transients[ $key ] = $value;
			return true;
		} ) );
		WP_Mock::userFunction( 'delete_transient', array( 'return' => static function ( $key ) use ( &$transients ) {
			unset( $transients[ $key ] );
			return true;
		} ) );

		WP_Mock::userFunction( 'wp_insert_post', array( 'return' => static function ( $data ) use ( &$inserted, &$posts, &$next_id ) {
			$id            = $next_id++;
			$inserted[]    = $data;
			$posts[ $id ]  = (object) array_merge( array( 'ID' => $id ), $data );
			return $id;
		} ) );
	}

	public function test_a_clone_is_a_draft_that_maps_every_translated_dependency() {
		$this->relationships->add( self::GROUP, 'post', 'page', 11, 'es-MX' );
		$this->relationships->members = array_filter(
			$this->relationships->members,
			static function ( $member ) {
				return 'es-MX' !== $member['language'];
			}
		);
		// Translated siblings the clone should map onto.
		$this->relationships->add( 'group-header', 'post', 'dsf_layout', 40, 'en-US' );
		$this->relationships->add( 'group-header', 'post', 'dsf_layout', 140, 'es-MX' );
		$this->relationships->add( 'group-popup', 'post', 'dsf_popup', 50, 'en-US' );
		$this->relationships->add( 'group-popup', 'post', 'dsf_popup', 150, 'es-MX' );
		$this->relationships->add( 'group-saved', 'post', 'dsf_saved_block', 70, 'en-US' );
		$this->relationships->add( 'group-saved', 'post', 'dsf_saved_block', 170, 'es-MX' );

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertIsArray( $result );
		$this->assertSame( 'draft', $this->inserted[0]['post_status'], 'A translation never publishes itself.' );
		$this->assertSame( 'About us', $this->inserted[0]['post_title'] );
		$this->assertSame( 3, $this->inserted[0]['menu_order'] );

		$new_id   = $result['post_id'];
		$settings = $this->meta[ $new_id ]['_dsf_settings'];
		$blocks   = $this->meta[ $new_id ]['_dsf_blocks'];

		$this->assertSame( 140, $settings['layout']['headerTemplateId'], 'The header maps to its Spanish sibling.' );
		$this->assertArrayNotHasKey( 'footerTemplateId', $settings['layout'], 'An untranslated footer is left unassigned.' );
		$this->assertSame( 150, $settings['popupId'] );
		$this->assertSame( 170, $blocks[0]['savedBlockId'] );
		$this->assertSame( 900, $this->meta[ $new_id ]['_thumbnail_id'], 'Media is shared, not duplicated.' );
		$this->assertArrayNotHasKey( '_dsf_html_snapshot', $this->meta[ $new_id ], 'A snapshot renders the source language.' );
		$this->assertSame( array( $new_id ), $this->routing->synced );
		$this->assertSame( array( self::GROUP, 'post', 'page', $new_id, 'es-MX' ), $this->relationships->added[0] );
	}

	public function test_an_untranslated_saved_block_is_detached_rather_than_carried() {
		$this->relationships->add( 'group-saved', 'post', 'dsf_saved_block', 70, 'en-US' );

		$result = $this->cloner->clone_post( 10, 'es-MX' );
		$blocks = $this->meta[ $result['post_id'] ]['_dsf_blocks'];

		$this->assertArrayNotHasKey(
			'savedBlockId',
			$blocks[0],
			'Keeping the source saved block would let a later sync overwrite this language.'
		);
		$this->assertNotEmpty( $result['notices'] );
	}

	public function test_a_copied_title_and_slug_stay_unconfirmed() {
		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertArrayNotHasKey( '_dsf_translation_title_confirmed', $this->meta[ $result['post_id'] ] );
		$this->assertArrayNotHasKey( '_dsf_translation_slug_confirmed', $this->meta[ $result['post_id'] ] );
	}

	public function test_explicit_translated_identity_is_marked_confirmed() {
		$result = $this->cloner->clone_post( 10, 'es-MX', array( 'title' => 'Acerca de', 'slug' => 'acerca-de' ) );

		$this->assertSame( 'Acerca de', $this->inserted[0]['post_title'] );
		$this->assertSame( 'acerca-de', $this->inserted[0]['post_name'] );
		$this->assertSame( 1, $this->meta[ $result['post_id'] ]['_dsf_translation_title_confirmed'] );
		$this->assertSame( 1, $this->meta[ $result['post_id'] ]['_dsf_translation_slug_confirmed'] );
	}

	public function test_only_the_main_language_may_be_a_clone_source() {
		$this->relationships->add( self::GROUP, 'post', 'page', 11, 'es-MX' );

		$result = $this->cloner->clone_post( 11, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_clone_source_language', $result->get_error_code() );
		$this->assertEmpty( $this->inserted );
	}

	public function test_an_existing_translation_is_never_replaced() {
		$this->relationships->add( self::GROUP, 'post', 'page', 11, 'es-MX' );

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_clone_exists', $result->get_error_code() );
		$this->assertEmpty( $this->inserted );
	}

	public function test_a_concurrent_clone_is_refused_by_the_lock() {
		$this->transients[ 'dsf_clone_' . md5( self::GROUP . '|es-MX' ) ] = 1;

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_clone_in_progress', $result->get_error_code() );
		$this->assertEmpty( $this->inserted );
	}

	public function test_a_lost_race_at_the_database_removes_the_orphan_draft() {
		$this->relationships->fail = true;

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertCount( 1, $this->inserted );
		$this->assertSame( array(), $this->routing->synced, 'An orphan draft never gets a public route.' );
	}

	public function test_unknown_disabled_and_main_target_languages_are_refused() {
		foreach ( array( 'de-DE', 'xx-ZZ', 'en-US', '', '<script>' ) as $language ) {
			$result = $this->cloner->clone_post( 10, $language );
			$this->assertInstanceOf( 'WP_Error', $result, (string) $language );
			$this->assertSame( 'dsf_clone_language', $result->get_error_code(), (string) $language );
		}
		$this->assertEmpty( $this->inserted );
	}

	public function test_a_user_without_edit_permission_cannot_clone() {
		$this->can_edit = false;

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_clone_forbidden', $result->get_error_code() );
		$this->assertEmpty( $this->inserted );
	}

	public function test_cloning_is_blocked_while_multilingual_mode_is_off() {
		$this->options[ DSF_Multilingual_Settings::OPTION_NAME ]['enabled'] = false;

		$result = $this->cloner->clone_post( 10, 'es-MX' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'dsf_clone_disabled', $result->get_error_code() );
	}

	public function test_a_submitted_title_is_sanitized_before_storage() {
		$result = $this->cloner->clone_post( 10, 'es-MX', array( 'title' => '<script>alert(1)</script>Acerca', 'slug' => 'Acerca De!' ) );

		$this->assertIsArray( $result );
		$this->assertStringNotContainsString( '<script', $this->inserted[0]['post_title'] );
		$this->assertSame( 'acerca-de', $this->inserted[0]['post_name'] );
	}
}

/**
 * Local tag stripper for the sanitize_text_field() double.
 *
 * @param string $value Raw value.
 * @return string
 */
function wp_strip_all_tags_local( $value ) {
	return preg_replace( '/<[^>]*>/', '', preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $value ) );
}
