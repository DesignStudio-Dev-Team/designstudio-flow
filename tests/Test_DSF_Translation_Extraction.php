<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-html.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-extractor.php';
require_once dirname( __DIR__ ) . '/includes/class-dsf-translation-reassembler.php';

/**
 * Stands in for the block registry so extraction can be exercised without
 * booting the full block catalog.
 */
class DSF_Extraction_Test_Blocks {
	public static $registry = array();

	public static function get_translatable_settings( $block_id ) {
		$block = self::$registry[ $block_id ] ?? null;
		return is_array( $block ) ? DSF_Translation_Contract::describe_block( $block_id, $block ) : array();
	}
}

/**
 * Covers the bounded extractor and the reassembler: what leaves the site, what
 * can be written back, and what is preserved untouched in both directions.
 */
class Test_DSF_Translation_Extraction extends TestCase {

	/** @var DSF_Translation_Extractor */
	private $extractor;

	/** @var DSF_Translation_Reassembler */
	private $reassembler;

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
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		DSF_Extraction_Test_Blocks::$registry = array(
			'faq'      => array(
				'settings' => array(
					'title'           => array( 'type' => 'text' ),
					'items'           => array( 'type' => 'faq_items' ),
					'backgroundColor' => array( 'type' => 'color' ),
					'maxWidth'        => array( 'type' => 'slider' ),
				),
			),
			'cta'      => array(
				'settings' => array(
					'heading'    => array( 'type' => 'text' ),
					'body'       => array( 'type' => 'wysiwyg' ),
					'buttonText' => array( 'type' => 'text' ),
					'buttonUrl'  => array( 'type' => 'text' ),
				),
			),
			'mystery'  => array( 'settings' => array( 'label' => array( 'type' => 'text' ) ) ),
		);

		$this->extractor   = new DSF_Translation_Extractor(
			array( 'block_resolver' => array( 'DSF_Extraction_Test_Blocks', 'get_translatable_settings' ) )
		);
		$this->reassembler = new DSF_Translation_Reassembler( array( 'extractor' => $this->extractor ) );
	}

	public function tearDown(): void {
		DSF_Extraction_Test_Blocks::$registry = array();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * A representative page's blocks.
	 *
	 * @return array
	 */
	private function blocks() {
		return array(
			array(
				'type'     => 'faq',
				'settings' => array(
					'title'           => 'Frequently asked questions',
					'items'           => array(
						array(
							'question' => 'What is Flow?',
							'answer'   => '<p>A <strong>page builder</strong>.</p>',
						),
						array(
							'question' => 'Is it fast?',
							'answer'   => '<p>Yes.</p>',
						),
					),
					'backgroundColor' => '#ffffff',
					'maxWidth'        => 900,
				),
			),
			array(
				'type'     => 'cta',
				'settings' => array(
					'heading'    => 'Ready to start?',
					'body'       => '<p>Join today.</p>',
					'buttonText' => 'Sign up',
					'buttonUrl'  => 'https://example.test/signup?ref=hero',
				),
			),
		);
	}

	/**
	 * Extract paths as a flat list for readable assertions.
	 *
	 * @param array $result Extractor result.
	 * @return string[]
	 */
	private function paths( $result ) {
		return array_map(
			static function ( $segment ) {
				return null === $segment['node'] ? $segment['path'] : $segment['path'] . '#' . $segment['node'];
			},
			$result['segments']
		);
	}

	public function test_only_declared_block_copy_is_extracted() {
		$result = $this->extractor->extract_blocks( $this->blocks() );

		$this->assertSame(
			array(
				'blocks.0.settings.title',
				'blocks.0.settings.items.0.question',
				'blocks.0.settings.items.0.answer#0',
				'blocks.0.settings.items.0.answer#1',
				'blocks.0.settings.items.1.question',
				'blocks.0.settings.items.1.answer#0',
				'blocks.1.settings.heading',
				'blocks.1.settings.body#0',
				'blocks.1.settings.buttonText',
			),
			$this->paths( $result )
		);
		$this->assertFalse( $result['truncated'] );

		$texts = wp_list_pluck_local( $result['segments'], 'text' );
		$this->assertContains( 'A', $texts );
		$this->assertContains( 'page builder', $texts );
		$this->assertNotContains( 'https://example.test/signup?ref=hero', $texts, 'A URL is never sent to a translator.' );
		$this->assertNotContains( '#ffffff', $texts );
		$this->assertNotContains( '<p>Yes.</p>', $texts, 'Rich text travels as visible nodes, never as markup.' );
	}

	public function test_unregistered_block_types_contribute_nothing() {
		$result = $this->extractor->extract_blocks(
			array(
				array(
					'type'     => 'not-installed',
					'settings' => array( 'title' => 'Invisible' ),
				),
				array( 'type' => 'mystery' ),
				'not-a-block',
			)
		);

		$this->assertSame( array(), $result['segments'] );
	}

	public function test_oversized_and_empty_values_are_skipped() {
		$blocks = array(
			array(
				'type'     => 'cta',
				'settings' => array(
					'heading'    => str_repeat( 'a', DSF_Translation_Extractor::MAX_SEGMENT_CHARS + 1 ),
					'body'       => '<p>2026 — 40%</p>',
					'buttonText' => '   ',
				),
			),
		);

		$this->assertSame( array(), $this->extractor->extract_blocks( $blocks )['segments'] );
	}

	public function test_extraction_stops_at_the_block_ceiling() {
		$block  = array(
			'type'     => 'cta',
			'settings' => array( 'heading' => 'Repeated heading' ),
		);
		$result = $this->extractor->extract_blocks( array_fill( 0, DSF_Translation_Extractor::MAX_BLOCKS + 25, $block ) );

		$this->assertCount( DSF_Translation_Extractor::MAX_BLOCKS, $result['segments'] );
	}

	public function test_round_trip_translates_declared_copy_and_preserves_everything_else() {
		$blocks       = $this->blocks();
		$translations = array(
			array( 'path' => 'blocks.0.settings.title', 'node' => null, 'text' => 'Preguntas frecuentes' ),
			array( 'path' => 'blocks.0.settings.items.0.question', 'node' => null, 'text' => '¿Qué es Flow?' ),
			array( 'path' => 'blocks.0.settings.items.0.answer', 'node' => 0, 'text' => 'Un' ),
			array( 'path' => 'blocks.0.settings.items.0.answer', 'node' => 1, 'text' => 'constructor de páginas' ),
			array( 'path' => 'blocks.1.settings.buttonText', 'node' => null, 'text' => 'Registrarse' ),
		);

		$translated = $this->reassembler->apply_to_document(
			$blocks,
			array( 'kind' => 'blocks' ),
			'blocks',
			$translations
		);

		$this->assertSame( 'Preguntas frecuentes', $translated[0]['settings']['title'] );
		$this->assertSame( '¿Qué es Flow?', $translated[0]['settings']['items'][0]['question'] );
		$this->assertSame( '<p>Un <strong>constructor de páginas</strong>.</p>', $translated[0]['settings']['items'][0]['answer'] );
		$this->assertSame( 'Registrarse', $translated[1]['settings']['buttonText'] );

		// Untranslated and non-translatable values are byte-identical.
		$this->assertSame( '#ffffff', $translated[0]['settings']['backgroundColor'] );
		$this->assertSame( 900, $translated[0]['settings']['maxWidth'] );
		$this->assertSame( 'https://example.test/signup?ref=hero', $translated[1]['settings']['buttonUrl'] );
		$this->assertSame( 'Ready to start?', $translated[1]['settings']['heading'] );
		$this->assertSame( '<p>Is it fast?</p>', '<p>' . $translated[0]['settings']['items'][1]['question'] . '</p>' );
		$this->assertSame( 5, $this->reassembler->get_applied_count() );
	}

	public function test_undeclared_and_malformed_paths_are_refused() {
		$blocks     = $this->blocks();
		$translated = $this->reassembler->apply_to_document(
			$blocks,
			array( 'kind' => 'blocks' ),
			'blocks',
			array(
				array( 'path' => 'blocks.1.settings.buttonUrl', 'text' => 'https://evil.test' ),
				array( 'path' => 'blocks.0.settings.backgroundColor', 'text' => '#000000' ),
				array( 'path' => 'blocks.0.settings.__proto__', 'text' => 'x' ),
				array( 'path' => '../../wp-config', 'text' => 'x' ),
				array( 'path' => 'blocks.0.settings.title; DROP TABLE', 'text' => 'x' ),
				array( 'path' => array( 'blocks.0.settings.title' ), 'text' => 'x' ),
				array( 'path' => 'blocks.0.settings.title', 'text' => array( 'nested' ) ),
				'not-a-segment',
			)
		);

		$this->assertSame( $blocks, $translated, 'Nothing outside the declared contract may be written.' );
		$this->assertSame( 0, $this->reassembler->get_applied_count() );
	}

	public function test_translated_markup_cannot_smuggle_scripts_into_rich_text() {
		$blocks     = $this->blocks();
		$translated = $this->reassembler->apply_to_document(
			$blocks,
			array( 'kind' => 'blocks' ),
			'blocks',
			array(
				array(
					'path' => 'blocks.1.settings.body',
					'node' => 0,
					'text' => '<script>alert(1)</script><img src=x onerror=alert(1)>Hola',
				),
			)
		);

		// A translated value is written as text, so submitted markup becomes inert
		// characters instead of new elements or attributes.
		$body = $translated[1]['settings']['body'];
		$this->assertStringNotContainsString( '<script', $body );
		$this->assertStringNotContainsString( '<img', $body );
		$this->assertStringContainsString( '&lt;script&gt;', $body );
		$this->assertStringContainsString( 'Hola', $body );
		$this->assertStringStartsWith( '<p>', $body, 'The source markup structure is preserved.' );
	}

	public function test_a_translation_never_reaches_a_field_the_source_does_not_have() {
		$blocks = array(
			array(
				'type'     => 'cta',
				'settings' => array( 'heading' => 'Only heading' ),
			),
		);

		$translated = $this->reassembler->apply_to_document(
			$blocks,
			array( 'kind' => 'blocks' ),
			'blocks',
			array(
				array( 'path' => 'blocks.0.settings.buttonText', 'text' => 'Injected' ),
				array( 'path' => 'blocks.0.settings.body', 'node' => 0, 'text' => 'Injected' ),
			)
		);

		$this->assertSame( array( 'heading' => 'Only heading' ), $translated[0]['settings'] );
	}

	public function test_empty_translations_leave_the_source_untouched() {
		$blocks = $this->blocks();

		$this->assertSame(
			$blocks,
			$this->reassembler->apply_to_document( $blocks, array( 'kind' => 'blocks' ), 'blocks', array() )
		);
		$this->assertSame(
			$blocks,
			$this->reassembler->apply_to_document( $blocks, array( 'kind' => 'blocks' ), 'blocks', 'garbage' )
		);
	}

	public function test_rich_text_walker_keeps_markup_urls_and_code_intact() {
		$html  = '<p class="lead">Hello <a href="/x?a=1&amp;b=2" title="Go">world</a></p><script>var a="Keep";</script><img src="/a.png" alt="A cat">';
		$nodes = DSF_Translation_Html::extract( $html );

		$values = wp_list_pluck_local( $nodes, 'value' );
		$this->assertSame( array( 'Hello', 'A cat', 'Go', 'world' ), $values );
		$this->assertNotContains( 'var a="Keep";', $values, 'Script contents are never translatable.' );

		$replaced = DSF_Translation_Html::replace( $html, array( 0 => 'Hola', 3 => 'mundo' ) );
		$this->assertStringContainsString( 'class="lead"', $replaced );
		$this->assertStringContainsString( 'href="/x?a=1&amp;b=2"', $replaced );
		$this->assertStringContainsString( 'var a="Keep";', $replaced );
		$this->assertStringContainsString( '>mundo<', $replaced );
	}

	public function test_rich_text_walker_fails_closed_on_unusable_input() {
		$this->assertSame( array(), DSF_Translation_Html::extract( '' ) );
		$this->assertSame( array(), DSF_Translation_Html::extract( str_repeat( 'a', DSF_Translation_Html::MAX_HTML_BYTES + 1 ) ) );
		$this->assertSame( array(), DSF_Translation_Html::extract( array( 'not', 'html' ) ) );

		$oversized = str_repeat( 'a', DSF_Translation_Html::MAX_HTML_BYTES + 1 );
		$this->assertSame( $oversized, DSF_Translation_Html::replace( $oversized, array( 0 => 'x' ) ) );
	}
}

/**
 * Local stand-in for wp_list_pluck() so these assertions do not depend on a
 * WordPress runtime.
 *
 * @param array  $list  Rows.
 * @param string $field Field name.
 * @return array
 */
function wp_list_pluck_local( $list, $field ) {
	return array_map(
		static function ( $row ) use ( $field ) {
			return $row[ $field ];
		},
		$list
	);
}
