<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-blocks.php';

class Test_DSF_Ajax_Landing_Blocks extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'sanitize_textarea_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_kses_post', array( 'return' => 'safe-answer' ) );
		WP_Mock::userFunction( 'sanitize_hex_color', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'current_datetime', array( 'return' => new DateTimeImmutable( '2026-06-22 12:00:00' ) ) );
		WP_Mock::userFunction(
			'esc_url_raw',
			array(
				'return' => static function ( $value ) {
					return 0 === strpos( $value, 'javascript:' ) ? '' : $value;
				},
			)
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_story_settings_use_bounded_variants_and_strip_unknown_fields() {
		$clean = $this->sanitize_settings(
			'landing-product-story',
			array(
				'variant'       => 'not-a-real-variant',
				'reverseLayout' => 'yes',
				'title'         => '<script>Title</script>',
				'description'   => 'Description',
				'featureOne'    => 'One',
				'featureTwo'    => 'Two',
				'featureThree'  => 'Three',
				'unknownHtml'   => '<iframe src="bad"></iframe>',
			)
		);

		$this->assertSame( 'editor', $clean['variant'] );
		$this->assertTrue( $clean['reverseLayout'] );
		$this->assertArrayNotHasKey( 'unknownHtml', $clean );
	}

	public function test_header_settings_reject_unsafe_protocols_and_keep_page_anchors() {
		$clean = $this->sanitize_settings(
			'landing-progress-header',
			array(
				'announcementUrl' => '#blocks',
				'homeUrl'         => '#why-dsflow',
				'docsUrl'         => 'javascript:alert(1)',
				'ctaUrl'          => 'https://example.com/get',
			)
		);

		$this->assertSame( '#blocks', $clean['announcementUrl'] );
		$this->assertSame( '', $clean['docsUrl'] );
		$this->assertSame( 'https://example.com/get', $clean['ctaUrl'] );
	}

	public function test_engagement_settings_are_bounded_to_known_plain_text_fields() {
		$clean = $this->sanitize_settings(
			'landing-engagement-suite',
			array(
				'eyebrow'                => 'Growth tools',
				'title'                  => 'Forms and campaigns',
				'description'            => 'A connected suite.',
				'formsTitle'              => 'Forms',
				'formsDescription'        => 'Build forms.',
				'popupTitle'              => 'Popups',
				'popupDescription'        => 'Schedule popups.',
				'notificationTitle'       => 'Notification bar',
				'notificationDescription' => 'Publish site wide.',
				'unknownHtml'             => '<script>alert(1)</script>',
			)
		);

		$this->assertSame( 'Growth tools', $clean['eyebrow'] );
		$this->assertSame( 'Publish site wide.', $clean['notificationDescription'] );
		$this->assertArrayNotHasKey( 'unknownHtml', $clean );
	}

	public function test_landing_styles_and_media_survive_the_secure_save_contract() {
		$clean = $this->sanitize_settings(
			'landing-hero',
			array(
				'backgroundColor' => '#112233',
				'textColor'       => '#fefefe',
				'accentColor'     => '#ff6600',
				'paddingX'        => 999,
				'marginY'         => 32,
				'mediaType'       => 'image',
				'mediaImage'      => 'https://example.com/hero.jpg',
				'mediaVideo'      => 'javascript:alert(1)',
			)
		);

		$this->assertSame( '#112233', $clean['backgroundColor'] );
		$this->assertSame( '#fefefe', $clean['textColor'] );
		$this->assertSame( '#ff6600', $clean['accentColor'] );
		$this->assertSame( 80, $clean['paddingX'] );
		$this->assertSame( 32, $clean['marginY'] );
		$this->assertSame( 'image', $clean['mediaType'] );
		$this->assertSame( 'https://example.com/hero.jpg', $clean['mediaImage'] );
		$this->assertSame( '', $clean['mediaVideo'] );
	}

	public function test_editable_landing_collections_are_capped_and_sanitized() {
		$gallery_items = array_fill(
			0,
			30,
			array(
				'category'    => 'Cards',
				'title'       => 'Card',
				'description' => 'Description',
				'image'       => 'https://example.com/card.jpg',
				'kind'        => 'not-real',
				'url'         => 'javascript:alert(1)',
				'unknown'     => '<script>bad</script>',
			)
		);
		$gallery = $this->sanitize_settings( 'landing-block-explorer', array( 'items' => $gallery_items ) );

		$this->assertCount( 24, $gallery['items'] );
		$this->assertSame( 'generic', $gallery['items'][0]['kind'] );
		$this->assertSame( '', $gallery['items'][0]['url'] );
		$this->assertArrayNotHasKey( 'unknown', $gallery['items'][0] );

		$showcase = $this->sanitize_settings(
			'landing-trust-workflow',
			array(
				'layout' => 'numbered',
				'items'  => array_fill( 0, 12, array( 'icon' => 'not-real', 'title' => 'Step', 'description' => 'Do it', 'note' => '01' ) ),
			)
		);
		$this->assertSame( 'numbered', $showcase['layout'] );
		$this->assertCount( 8, $showcase['items'] );
		$this->assertSame( 'sparkles', $showcase['items'][0]['icon'] );
	}

	public function test_generic_content_grid_and_footer_fields_persist() {
		$grid = $this->sanitize_settings(
			'landing-engagement-suite',
			array(
				'formsLabel'       => 'First card',
				'formsIcon'        => 'palette',
				'formsBullets'     => "One\nTwo",
				'formsType'        => 'video',
				'formsVideo'       => 'https://example.com/video.mp4',
				'popupIcon'        => 'bad-icon',
				'notificationIcon' => 'bell',
			)
		);
		$this->assertSame( 'First card', $grid['formsLabel'] );
		$this->assertSame( 'palette', $grid['formsIcon'] );
		$this->assertSame( "One\nTwo", $grid['formsBullets'] );
		$this->assertSame( 'video', $grid['formsType'] );
		$this->assertSame( 'https://example.com/video.mp4', $grid['formsVideo'] );
		$this->assertSame( 'sparkles', $grid['popupIcon'] );

		$footer = $this->sanitize_settings(
			'landing-marketing-footer',
			array(
				'variant'   => 'columns',
				'col1Title' => 'Explore',
				'col1Links' => array( array( 'label' => 'Safe', 'url' => '#safe', 'unknown' => 'drop' ) ),
			)
		);
		$this->assertSame( 'columns', $footer['variant'] );
		$this->assertSame( 'Explore', $footer['col1Title'] );
		$this->assertSame( array( 'label' => 'Safe', 'url' => '#safe' ), $footer['col1Links'][0] );
	}

	public function test_cta_footer_has_its_own_library_group() {
		$categories = DSF_Blocks::get_instance()->get_blocks_by_category();
		$footer_ids = array_column( $categories['footers']['blocks'], 'id' );

		$this->assertSame( 'Footers', $categories['footers']['label'] );
		$this->assertContains( 'landing-marketing-footer', $footer_ids );
		$this->assertNotContains( 'landing-marketing-footer', array_column( $categories['marketing']['blocks'], 'id' ) );
		$this->assertSame( 'Icon background color', DSF_Blocks::get_instance()->get_block( 'landing-engagement-suite' )['settings']['accentColor']['label'] );
	}

	public function test_faq_settings_cap_items_and_sanitize_rich_answers() {
		$items = array_fill( 0, 15, array( 'question' => 'Question', 'answer' => '<script>alert(1)</script><p>Answer</p>' ) );
		$clean = $this->invoke_private( 'sanitize_faq_settings', array( 'items' => $items ) );

		$this->assertCount( 12, $clean['items'] );
		$this->assertSame( 'safe-answer', $clean['items'][0]['answer'] );
	}

	private function sanitize_settings( $type, $settings ) {
		return $this->invoke_private( 'sanitize_landing_block_settings', $type, $settings );
	}

	private function invoke_private( $method_name, ...$arguments ) {
		$reflection = new ReflectionClass( 'DSF_Ajax' );
		$ajax       = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $ajax, $arguments );
	}
}
