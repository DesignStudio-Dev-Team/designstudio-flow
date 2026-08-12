<?php

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual.php';

class Test_DSF_Multilingual_Adapters extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction(
			'sanitize_key',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
			)
		);
		WP_Mock::userFunction(
			'absint',
			array(
				'return' => static function ( $value ) {
					return abs( (int) $value );
				},
			)
		);
		WP_Mock::userFunction(
			'sanitize_text_field',
			array(
				'return' => static function ( $value ) {
					return trim( strip_tags( (string) $value ) );
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
			'wp_kses_post',
			array(
				'return' => static function ( $value ) {
					return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $value );
				},
			)
		);
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_operational_post_types_are_never_in_the_default_inventory() {
		$types = DSF_Multilingual_Adapters::post_types();

		$this->assertContains( 'page', $types );
		$this->assertContains( 'product', $types );
		$this->assertContains( 'product_variation', $types );
		$this->assertNotContains( 'dsf_entry', $types );
		$this->assertNotContains( 'shop_order', $types );
		$this->assertNotContains( 'shop_order_refund', $types );
		$this->assertNotContains( 'shop_coupon', $types );
		$this->assertNotContains( 'attachment', $types );
	}

	public function test_a_secondary_object_goes_public_only_with_a_resolvable_route() {
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
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
		WP_Mock::userFunction(
			'get_post_type_object',
			array(
				'return' => static function ( $post_type ) {
					$is_public = in_array( $post_type, array( 'page', 'post' ), true );
					return (object) array(
						'public'             => $is_public,
						'publicly_queryable' => $is_public,
					);
				},
			)
		);

		$coordinator = ( new ReflectionClass( DSF_Multilingual::class ) )->newInstanceWithoutConstructor();
		$routes      = new class() {
			public $routed = array( 'post:page:12' => true );

			public function get_route( $kind, $subtype, $id ) {
				$key = $kind . ':' . $subtype . ':' . (int) $id;
				return isset( $this->routed[ $key ] ) ? array( 'path' => 'acerca-de' ) : null;
			}
		};
		$routing     = new class() {
			public $synced = array();

			public function sync_post_route( $post_id ) {
				$this->synced[] = (int) $post_id;
				return null;
			}
		};

		foreach ( array( 'routes', 'language_routing' ) as $name ) {
			$property = ( new ReflectionClass( DSF_Multilingual::class ) )->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $coordinator, 'routes' === $name ? $routes : $routing );
		}

		$secondary_page = array(
			'object_kind'    => 'post',
			'object_subtype' => 'page',
			'language'       => 'es-MX',
		);

		$this->assertTrue(
			$coordinator->foundation_route_is_valid( true, array_merge( $secondary_page, array( 'object_id' => 12 ) ) ),
			'A translated page with a stored route resolves to exactly one URL.'
		);
		$this->assertFalse(
			$coordinator->foundation_route_is_valid( true, array_merge( $secondary_page, array( 'object_id' => 99 ) ) ),
			'A translated page without a route has no public URL and must stay blocked.'
		);
		$this->assertTrue(
			$coordinator->foundation_route_is_valid(
				true,
				array(
					'object_kind'    => 'post',
					'object_subtype' => 'page',
					'object_id'      => 99,
					'language'       => 'en-US',
				)
			),
			'The main language keeps its native unprefixed URL.'
		);
		$this->assertTrue(
			$coordinator->foundation_route_is_valid(
				true,
				array(
					'object_kind'    => 'post',
					'object_subtype' => 'dsf_layout',
					'object_id'      => 40,
					'language'       => 'es-MX',
				)
			),
			'Objects without their own public URL are reached through the page that embeds them.'
		);
		$this->assertSame(
			array( 99 ),
			$routing->synced,
			'A missing route is rebuilt once before the publish attempt is refused.'
		);
		$this->assertFalse( $coordinator->foundation_route_is_valid( false, array( 'object_kind' => 'term' ) ) );
		$this->assertTrue( $coordinator->foundation_route_is_valid( true, array( 'object_kind' => 'synthetic' ) ) );
	}

	public function test_notification_slots_are_distinct_and_require_secondary_storage() {
		$translations = array();
		WP_Mock::userFunction( 'get_locale', array( 'return' => 'en_US' ) );
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) use ( &$translations ) {
					if ( DSF_Multilingual_Settings::OPTION_NAME === $key ) {
						return array(
							'enabled'       => true,
							'main_language' => 'en-US',
							'languages'     => array(
								array(
									'code'   => 'en-US',
									'prefix' => '',
								),
								array(
									'code'   => 'es-MX',
									'prefix' => 'es-mx',
								),
							),
						);
					}
					if ( DSF_Multilingual_Adapters::NOTIFICATION_TRANSLATIONS_OPTION === $key ) {
						return $translations;
					}
					if ( 'dsf_notification_bar' === $key ) {
						return array( 'enabled' => true );
					}
					return $default;
				},
			)
		);

		$main_id    = DSF_Multilingual_Adapters::synthetic_notification_id( 'en-US' );
		$spanish_id = DSF_Multilingual_Adapters::synthetic_notification_id( 'es-MX' );
		$this->assertSame( 1002, $main_id );
		$this->assertSame( 1008, $spanish_id );
		$this->assertGreaterThan( 0, $main_id );
		$this->assertNotSame( $main_id, $spanish_id );
		$this->assertTrue( DSF_Multilingual_Adapters::object_exists( 'synthetic', 'notification_bar', $main_id, 'en-US' ) );
		$this->assertFalse( DSF_Multilingual_Adapters::object_exists( 'synthetic', 'notification_bar', $spanish_id, 'es-MX' ) );

		$translations['es-MX'] = array( 'message' => 'Aviso' );
		$this->assertTrue( DSF_Multilingual_Adapters::object_exists( 'synthetic', 'notification_bar', $spanish_id, 'es-MX' ) );
		$this->assertFalse( DSF_Multilingual_Adapters::object_exists( 'synthetic', 'notification_bar', $spanish_id, 'fr-FR' ) );

		$post            = new stdClass();
		$post->ID        = 44;
		$post->post_type = 'page';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => array() ) );
		$dependencies = DSF_Multilingual_Adapters::post_dependencies( 44, 'es-MX' );
		$this->assertContains( $spanish_id, array_column( $dependencies, 'object_id' ) );
		$this->assertContains( 'globals.notification_bar', array_column( $dependencies, 'path' ) );
	}

	public function test_form_fingerprint_excludes_recipients_connections_secrets_and_redirects() {
		$post               = new stdClass();
		$post->ID           = 41;
		$post->post_type    = 'dsf_form';
		$post->post_title   = 'Contact';
		$post->post_name    = 'contact';
		$post->post_excerpt = '';
		$post->post_content = '';

		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) {
					if ( '_dsf_form_rows' === $key ) {
						return array(
							array(
								'label' => 'Your name',
								'name'  => 'name',
							),
						);
					}
					return array(
						'submitLabel'         => 'Send',
						'notificationSubject' => 'New request',
						'adminEmails'         => array( 'private@example.com' ),
						'redirectUrl'         => 'https://private.example/target',
						'connections'         => array(
							array(
								'endpointUrl' => 'https://private.example/hook',
								'secret'      => 'do-not-hash',
							),
						),
					);
				},
			)
		);

		$payload = DSF_Multilingual_Adapters::fingerprint_payload(
			array(
				'object_kind'    => 'post',
				'object_subtype' => 'dsf_form',
				'object_id'      => 41,
			)
		);
		$json    = json_encode( $payload );

		$this->assertStringContainsString( 'New request', $json );
		$this->assertStringNotContainsString( 'private@example.com', $json );
		$this->assertStringNotContainsString( 'private.example', $json );
		$this->assertStringNotContainsString( 'do-not-hash', $json );
	}

	public function test_dependencies_use_only_explicit_existing_reference_fields() {
		$post            = new stdClass();
		$post->ID        = 22;
		$post->post_type = 'page';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) {
					if ( '_dsf_settings' === $key ) {
						return array(
							'layout'  => array(
								'headerTemplateId' => 4,
								'footerTemplateId' => 5,
							),
							'popupId' => 6,
						);
					}
					return array(
						array(
							'savedBlockId' => 7,
							'type'         => 'form-with-content',
							'settings'     => array(
								'formSource'       => 'dsf',
								'formId'           => '8',
								'newsletterSource' => 'dsf',
								'newsletterFormId' => 9,
								'unrelatedId'      => 999,
							),
						),
					);
				},
			)
		);

		$dependencies = DSF_Multilingual_Adapters::post_dependencies( 22 );
		$ids          = array_column( $dependencies, 'object_id' );
		$paths        = array_column( $dependencies, 'path' );

		$this->assertSame( array( 4, 5, 6, 7, 8, 9 ), $ids );
		$this->assertNotContains( 999, $ids );
		$this->assertContains( 'settings.layout.headerTemplateId', $paths );
		$this->assertContains( 'blocks.0.settings.formId', $paths );
		$this->assertContains( 'blocks.0.settings.newsletterFormId', $paths );
		$this->assertNotContains( 'blocks.0.settings.unrelatedId', $paths );
	}

	public function test_embedded_form_with_content_ignores_stale_dsf_form_id() {
		$post            = new stdClass();
		$post->ID        = 23;
		$post->post_type = 'page';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) {
					unset( $post_id );
					if ( '_dsf_blocks' !== $key ) {
						return array();
					}
					return array(
						array(
							'type'     => 'form-with-content',
							'settings' => array(
								'formSource' => 'embed',
								'formId'     => '88',
								'embedCode'  => '<iframe></iframe>',
							),
						),
					);
				},
			)
		);
		WP_Mock::userFunction( 'get_option', array( 'return' => 0 ) );

		$dependencies = DSF_Multilingual_Adapters::post_dependencies( 23 );

		$this->assertNotContains( 88, array_column( $dependencies, 'object_id' ) );
		$this->assertNotContains( 'blocks.0.settings.formId', array_column( $dependencies, 'path' ) );
	}

	public function test_product_fingerprint_does_not_read_operational_product_meta() {
		$post               = new stdClass();
		$post->ID           = 12;
		$post->post_type    = 'product';
		$post->post_title   = 'Trail boot';
		$post->post_name    = 'trail-boot';
		$post->post_excerpt = 'Lightweight';
		$post->post_content = 'Visitor description';

		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'times' => 0 ) );

		$payload = DSF_Multilingual_Adapters::fingerprint_payload(
			array(
				'object_kind'    => 'post',
				'object_subtype' => 'product',
				'object_id'      => 12,
			)
		);

		$this->assertSame( 'Trail boot', $payload['post_title'] );
		$this->assertSame( array(), $payload['meta'] );
		$this->assertArrayNotHasKey( 'sku', $payload );
		$this->assertArrayNotHasKey( 'stock', $payload );
		$this->assertArrayNotHasKey( 'price', $payload );
	}

	public function test_fingerprint_fails_closed_instead_of_truncating_deep_or_wide_meta() {
		$post               = new stdClass();
		$post->ID           = 70;
		$post->post_type    = 'page';
		$post->post_title   = 'Bounded';
		$post->post_name    = 'bounded';
		$post->post_excerpt = '';
		$post->post_content = '';
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );

		$deep = 'visitor-tail';
		for ( $depth = 0; $depth < 22; $depth++ ) {
			$deep = array( 'level' => $deep );
		}
		$fingerprint_meta = $deep;
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) use ( &$fingerprint_meta ) {
					unset( $post_id );
					return '_dsf_blocks' === $key ? $fingerprint_meta : array();
				},
			)
		);
		$deep_result = DSF_Multilingual_Adapters::fingerprint_payload(
			array(
				'object_kind'    => 'post',
				'object_subtype' => 'page',
				'object_id'      => 70,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $deep_result );
		$this->assertSame( 'dsf_multilingual_fingerprint_depth', $deep_result->get_error_code() );

		$fingerprint_meta = array_fill( 0, 5001, 'visitor-copy' );
		$wide_result      = DSF_Multilingual_Adapters::fingerprint_payload(
			array(
				'object_kind'    => 'post',
				'object_subtype' => 'page',
				'object_id'      => 70,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $wide_result );
		$this->assertSame( 'dsf_multilingual_fingerprint_nodes', $wide_result->get_error_code() );
	}

	public function test_a_layout_never_depends_on_itself_through_the_site_default() {
		$layout              = new stdClass();
		$layout->ID          = 1229;
		$layout->post_type   = 'dsf_layout';
		$layout->post_parent = 0;

		WP_Mock::userFunction( 'get_post', array( 'return' => $layout ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'return' => array() ) );
		// This layout is the site-wide default header, so the fallback resolves
		// to the very object being described.
		WP_Mock::userFunction(
			'get_option',
			array(
				'return' => static function ( $key, $default = false ) {
					unset( $default );
					if ( 'dsf_default_header_id' === $key ) {
						return 1229;
					}
					if ( 'dsf_default_footer_id' === $key ) {
						return 1161;
					}
					return array();
				},
			)
		);

		$dependencies = DSF_Multilingual_Adapters::post_dependencies( 1229 );

		$this->assertIsArray( $dependencies );
		$this->assertSame(
			array(),
			$dependencies,
			'A header or footer is not wrapped in another header or footer, and can never require itself.'
		);
	}

	public function test_only_whole_page_objects_require_a_header_and_footer() {
		$this->assertTrue( DSF_Multilingual_Adapters::renders_with_layout( 'page' ) );
		$this->assertTrue( DSF_Multilingual_Adapters::renders_with_layout( 'post' ) );
		$this->assertTrue( DSF_Multilingual_Adapters::renders_with_layout( 'dsf_shop_template' ) );

		// These render inside a page that already has its own header and footer.
		$this->assertFalse( DSF_Multilingual_Adapters::renders_with_layout( 'dsf_layout' ) );
		$this->assertFalse( DSF_Multilingual_Adapters::renders_with_layout( 'dsf_saved_block' ) );
		$this->assertFalse( DSF_Multilingual_Adapters::renders_with_layout( 'dsf_form' ) );
		$this->assertFalse( DSF_Multilingual_Adapters::renders_with_layout( 'dsf_popup' ) );
	}

	public function test_a_page_that_points_at_itself_drops_the_self_reference() {
		$page              = new stdClass();
		$page->ID          = 55;
		$page->post_type   = 'page';
		$page->post_parent = 0;

		WP_Mock::userFunction( 'get_post', array( 'return' => $page ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) {
					unset( $post_id );
					if ( '_dsf_blocks' === $key ) {
						return array( array( 'savedBlockId' => 55 ) );
					}
					return array( 'popupId' => 55 );
				},
			)
		);
		WP_Mock::userFunction( 'get_option', array( 'return' => 0 ) );

		$this->assertSame( array(), DSF_Multilingual_Adapters::post_dependencies( 55 ) );
	}

	public function test_dependency_after_capacity_limit_blocks_instead_of_disappearing() {
		$post            = new stdClass();
		$post->ID        = 71;
		$post->post_type = 'page';
		$blocks          = array();
		for ( $index = 1; $index <= DSF_Multilingual_Adapters::MAX_DEPENDENCIES + 1; $index++ ) {
			// Offset past the owner's own ID: an object is never its own dependency,
			// so a colliding ID would be skipped and never reach the limit.
			$blocks[] = array( 'savedBlockId' => 1000 + $index );
		}
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $post_id, $key ) use ( $blocks ) {
					unset( $post_id );
					return '_dsf_blocks' === $key ? $blocks : array();
				},
			)
		);
		WP_Mock::userFunction( 'get_option', array( 'return' => 0 ) );

		$result = DSF_Multilingual_Adapters::post_dependencies( 71 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dsf_multilingual_dependency_limit', $result->get_error_code() );
	}
}
