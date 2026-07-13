<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-seo.php';

/**
 * JSON-LD @graph assembly and FAQ extraction are pure (no WP-context reads), so
 * their node shapes are asserted directly.
 */
class Test_DSF_SEO_StructuredData extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_strip_all_tags', array( 'return' => static function ( $v ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $v ) ); } ) );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function graph_types( $graph ) {
		return array_map(
			static function ( $node ) {
				return $node['@type'];
			},
			$graph['@graph']
		);
	}

	public function test_page_graph_has_org_website_and_webpage() {
		$graph = DSF_SEO::build_json_ld_graph(
			array(
				'type'        => 'page',
				'site_name'   => 'Acme',
				'site_url'    => 'https://acme.test/',
				'locale'      => 'en_US',
				'title'       => 'Pricing',
				'description' => 'Our plans',
				'url'         => 'https://acme.test/pricing/',
				'image'       => array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' ),
				'logo'        => 'https://acme.test/logo.png',
				'search_url'  => 'https://acme.test/?s={search_term_string}',
				'breadcrumbs' => array(),
				'faq'         => array(),
				'product'     => array(),
			)
		);

		$this->assertSame( 'https://schema.org', $graph['@context'] );
		$types = $this->graph_types( $graph );
		$this->assertContains( 'Organization', $types );
		$this->assertContains( 'WebSite', $types );
		$this->assertContains( 'WebPage', $types );

		// Organization logo + WebSite SearchAction are wired.
		$org = $graph['@graph'][0];
		$this->assertSame( 'https://acme.test/logo.png', $org['logo']['url'] );
		$this->assertSame( 'SearchAction', $graph['@graph'][1]['potentialAction']['@type'] );
	}

	public function test_organization_uses_org_name_logo_and_sameas() {
		$graph = DSF_SEO::build_json_ld_graph(
			array(
				'type'        => 'page',
				'site_name'   => 'Acme Site',
				'org_name'    => 'Acme Inc',
				'site_url'    => 'https://acme.test/',
				'url'         => 'https://acme.test/x/',
				'title'       => 'X',
				'logo'        => 'https://acme.test/logo.png',
				'sameas'      => array( 'https://facebook.com/acme', 'https://x.com/acme' ),
				'image'       => array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' ),
				'breadcrumbs' => array(),
				'faq'         => array(),
				'product'     => array(),
			)
		);

		$org = $graph['@graph'][0];
		$this->assertSame( 'Organization', $org['@type'] );
		$this->assertSame( 'Acme Inc', $org['name'] );
		$this->assertSame( 'https://acme.test/logo.png', $org['logo']['url'] );
		$this->assertSame( array( 'https://facebook.com/acme', 'https://x.com/acme' ), $org['sameAs'] );

		// WebSite still carries the site (not org) name.
		$this->assertSame( 'Acme Site', $graph['@graph'][1]['name'] );
	}

	public function test_image_node_and_primary_image_reference() {
		$graph = DSF_SEO::build_json_ld_graph(
			array(
				'type'        => 'page',
				'site_url'    => 'https://acme.test/',
				'url'         => 'https://acme.test/x/',
				'title'       => 'X',
				'image'       => array( 'url' => 'https://acme.test/x.jpg', 'width' => 1200, 'height' => 630, 'alt' => 'Hero' ),
				'breadcrumbs' => array(),
				'faq'         => array(),
				'product'     => array(),
			)
		);

		$image = null;
		$page  = null;
		foreach ( $graph['@graph'] as $node ) {
			if ( 'ImageObject' === $node['@type'] ) {
				$image = $node;
			}
			if ( 'WebPage' === $node['@type'] ) {
				$page = $node;
			}
		}
		$this->assertSame( 1200, $image['width'] );
		$this->assertSame( 'Hero', $image['caption'] );
		$this->assertSame( $image['@id'], $page['primaryImageOfPage']['@id'] );
	}

	public function test_product_graph_includes_offer_and_breadcrumb() {
		$graph = DSF_SEO::build_json_ld_graph(
			array(
				'type'        => 'product',
				'site_url'    => 'https://shop.test/',
				'url'         => 'https://shop.test/product/widget/',
				'title'       => 'Widget',
				'description' => 'A widget',
				'image'       => array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' ),
				'breadcrumbs' => array(
					array( 'name' => 'Home', 'url' => 'https://shop.test/' ),
					array( 'name' => 'Shop', 'url' => 'https://shop.test/shop/' ),
					array( 'name' => 'Widget', 'url' => 'https://shop.test/product/widget/' ),
				),
				'faq'         => array(),
				'product'     => array(
					'name'         => 'Widget',
					'sku'          => 'W-1',
					'price'        => '19.99',
					'currency'     => 'USD',
					'availability' => 'instock',
				),
			)
		);

		$types = $this->graph_types( $graph );
		$this->assertContains( 'Product', $types );
		$this->assertContains( 'BreadcrumbList', $types );

		foreach ( $graph['@graph'] as $node ) {
			if ( 'Product' === $node['@type'] ) {
				$this->assertSame( '19.99', $node['offers']['price'] );
				$this->assertSame( 'USD', $node['offers']['priceCurrency'] );
				$this->assertSame( 'https://schema.org/InStock', $node['offers']['availability'] );
			}
			if ( 'BreadcrumbList' === $node['@type'] ) {
				$this->assertCount( 3, $node['itemListElement'] );
				$this->assertSame( 1, $node['itemListElement'][0]['position'] );
			}
		}
	}

	public function test_faq_extraction_and_faqpage_node() {
		$blocks = array(
			array(
				'type'     => 'faq',
				'settings' => array(
					'items' => array(
						array( 'question' => 'What is it?', 'answer' => '<p>A tool.</p>' ),
						array( 'question' => '', 'answer' => 'orphan' ),
					),
				),
			),
			array( 'type' => 'content', 'settings' => array() ),
		);

		$faq = DSF_SEO::extract_faq_entities( $blocks );
		$this->assertCount( 1, $faq );
		$this->assertSame( 'What is it?', $faq[0]['question'] );
		$this->assertSame( 'A tool.', $faq[0]['answer'] );

		$graph = DSF_SEO::build_json_ld_graph(
			array(
				'type'        => 'page',
				'site_url'    => 'https://acme.test/',
				'url'         => 'https://acme.test/faq/',
				'title'       => 'FAQ',
				'image'       => array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' ),
				'breadcrumbs' => array(),
				'faq'         => $faq,
				'product'     => array(),
			)
		);

		$faq_node = null;
		foreach ( $graph['@graph'] as $node ) {
			if ( 'FAQPage' === $node['@type'] ) {
				$faq_node = $node;
			}
		}
		$this->assertNotNull( $faq_node );
		$this->assertSame( 'Question', $faq_node['mainEntity'][0]['@type'] );
		$this->assertSame( 'A tool.', $faq_node['mainEntity'][0]['acceptedAnswer']['text'] );
	}
}
