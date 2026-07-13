<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-blog-templates.php';

/**
 * Blog templates: the assignment-rule contract, the WordPress-inactive
 * fallbacks, and the save-time sanitizers for the blog-header / post-loop
 * blocks. Archive data (posts, pagination) is always built server-side and
 * never trusted from the client.
 */
class Test_DSF_Blog_Templates extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'absint', array( 'return' => static function ( $v ) { return abs( (int) $v ); } ) );
		WP_Mock::userFunction(
			'sanitize_text_field',
			array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } )
		);
		WP_Mock::userFunction( 'sanitize_key', array( 'return' => static function ( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); } ) );
		WP_Mock::userFunction(
			'sanitize_hex_color',
			array( 'return' => static function ( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? $v : null; } )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function sanitize( $method, $settings ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$m          = $reflection->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $ajax, array( $settings ) );
	}

	// ---- Assignment rule ----

	public function test_assignment_defaults_and_category_mode() {
		$clean = DSF_Blog_Templates::sanitize_assignment( 'garbage' );
		$this->assertSame( 'all', $clean['mode'] );
		$this->assertSame( array(), $clean['categoryIds'] );

		$clean = DSF_Blog_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array( 4, '4', 0, 'x', 7 ),
			)
		);
		$this->assertSame( 'categories', $clean['mode'] );
		$this->assertSame( array( 4, 7 ), $clean['categoryIds'] );
	}

	public function test_assignment_empty_categories_falls_back_to_all_and_caps() {
		$clean = DSF_Blog_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => array(),
			)
		);
		$this->assertSame( 'all', $clean['mode'] );

		$many  = range( 1, 150 );
		$clean = DSF_Blog_Templates::sanitize_assignment(
			array(
				'mode'        => 'categories',
				'categoryIds' => $many,
			)
		);
		$this->assertCount( 100, $clean['categoryIds'] );
	}

	// ---- Environment fallbacks ----

	public function test_is_blog_archive_without_wordpress_conditionals_is_false() {
		$this->assertFalse( DSF_Blog_Templates::is_blog_archive() );
	}

	public function test_preview_context_without_wp_query_is_null() {
		$this->assertNull( DSF_Blog_Templates::build_preview_context( 3 ) );
	}

	// ---- blog-header sanitizer ----

	public function test_blog_header_defaults_and_rejects_client_archive_data() {
		$clean = $this->sanitize( 'sanitize_blog_header_settings', array() );
		$this->assertTrue( $clean['showTitle'] );
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertSame( 1100, $clean['maxWidth'] );
		$this->assertSame( 40, $clean['padding'] );

		$clean = $this->sanitize(
			'sanitize_blog_header_settings',
			array(
				'alignment'       => 'right',
				'title'           => '<script>x</script>',
				'descriptionHtml' => '<iframe>',
				'titleColor'      => '#112233',
			)
		);
		$this->assertSame( 'left', $clean['alignment'] );
		$this->assertArrayNotHasKey( 'title', $clean );
		$this->assertArrayNotHasKey( 'descriptionHtml', $clean );
		$this->assertSame( '#112233', $clean['titleColor'] );
	}

	// ---- post-loop sanitizer ----

	public function test_post_loop_defaults_and_enums() {
		$clean = $this->sanitize( 'sanitize_post_loop_settings', array() );
		$this->assertSame( 'grid', $clean['layout'] );
		$this->assertSame( 3, $clean['columns'] );
		$this->assertTrue( $clean['featuredFirst'] );
		$this->assertTrue( $clean['showPagination'] );
		$this->assertSame( 'Read article', $clean['readMoreText'] );

		$clean = $this->sanitize(
			'sanitize_post_loop_settings',
			array(
				'layout'  => 'masonry',
				'columns' => 99,
			)
		);
		$this->assertSame( 'grid', $clean['layout'] );
		$this->assertSame( 4, $clean['columns'] );

		$clean = $this->sanitize( 'sanitize_post_loop_settings', array( 'layout' => 'list' ) );
		$this->assertSame( 'list', $clean['layout'] );
	}

	public function test_post_loop_never_accepts_client_posts_and_strips_text() {
		$clean = $this->sanitize(
			'sanitize_post_loop_settings',
			array(
				'posts'        => array( array( 'title' => '<script>x</script>' ) ),
				'pagination'   => array( array( 'url' => 'javascript:x' ) ),
				'readMoreText' => '<b>Go</b>',
				'accentColor'  => 'red',
			)
		);
		$this->assertArrayNotHasKey( 'posts', $clean );
		$this->assertArrayNotHasKey( 'pagination', $clean );
		$this->assertSame( 'Go', $clean['readMoreText'] );
		$this->assertSame( '', $clean['accentColor'] );
	}

	public function test_post_loop_non_array_input_returns_safe_defaults() {
		$clean = $this->sanitize( 'sanitize_post_loop_settings', 'not-an-array' );
		$this->assertSame( 'grid', $clean['layout'] );
		$this->assertTrue( $clean['showImage'] );
	}
}
