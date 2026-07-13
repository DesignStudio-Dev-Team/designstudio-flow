<?php
/**
 * SEO output for DesignStudio Flow pages and templates.
 *
 * Every DSF-rendered URL — Flow pages, product templates, shop/archive
 * templates, and blog templates — gets a Yoast-style SEO panel: custom title,
 * meta description, social (Open Graph / Twitter) tags, canonical override,
 * and a noindex switch. Template SEO fields support variables, so one product
 * template produces a unique title/description for every product:
 *
 *   {title} {site_name} {tagline} {sep} {excerpt} {price} {category}
 *
 * If a dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) is active,
 * DSF outputs nothing and defers entirely to it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_SEO {

	private static $instance = null;

	/** Per-request cache of the resolved flow context (null until first use). */
	private $context = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// wp-sitemap.xml is a front-end request (not is_admin), so the sitemap
		// exclusion is registered before the admin bail-out.
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_noindex_from_sitemap' ), 10, 2 );

		if ( is_admin() ) {
			return;
		}

		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );
		// Priority 4: after wp_enqueue_scripts (1) so the LCP/hero image is known,
		// before most theme output.
		add_action( 'wp_head', array( $this, 'output_head_tags' ), 4 );
		// JSON-LD after the meta tags so the graph sits with the rest of our SEO.
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 5 );
	}

	/**
	 * Whether a dedicated SEO plugin is active (DSF then defers entirely).
	 *
	 * @return bool
	 */
	public static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' )
			|| class_exists( 'RankMath' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' );
	}

	/**
	 * The site-wide SEO defaults (fallback social image, {sep} separator, and the
	 * Organization identity that feeds structured data), normalized with sane
	 * fallbacks. Read from the `dsf_seo_defaults` option.
	 *
	 * @return array{defaultSocialImage:string,titleSeparator:string,orgName:string,orgLogo:string,twitterSite:string,socialProfiles:array}
	 */
	public static function get_defaults() {
		$opt = get_option( 'dsf_seo_defaults', array() );
		$opt = is_array( $opt ) ? $opt : array();

		$separator = isset( $opt['titleSeparator'] ) ? (string) $opt['titleSeparator'] : '';

		return array(
			'defaultSocialImage' => isset( $opt['defaultSocialImage'] ) ? (string) $opt['defaultSocialImage'] : '',
			'titleSeparator'     => '' !== $separator ? $separator : '–',
			'orgName'            => isset( $opt['orgName'] ) ? (string) $opt['orgName'] : '',
			'orgLogo'            => isset( $opt['orgLogo'] ) ? (string) $opt['orgLogo'] : '',
			'twitterSite'        => isset( $opt['twitterSite'] ) ? (string) $opt['twitterSite'] : '',
			'socialProfiles'     => isset( $opt['socialProfiles'] ) && is_array( $opt['socialProfiles'] )
				? array_values( array_filter( array_map( 'strval', $opt['socialProfiles'] ) ) )
				: array(),
		);
	}

	/**
	 * Replace SEO variables in a template string with their values.
	 *
	 * Pure string work (values are gathered separately) so the contract is unit
	 * testable. Unknown {tokens} are removed, whitespace is collapsed, and stray
	 * separators left dangling by empty values are trimmed.
	 *
	 * @param string $template Template string with {variables}.
	 * @param array  $values   Variable => replacement map (plain text).
	 * @return string
	 */
	public static function apply_template( $template, $values ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}

		$replacements = array();
		foreach ( (array) $values as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}

		$out = strtr( $template, $replacements );
		// Drop unknown {tokens} rather than leaking them into the page.
		$out = preg_replace( '/\{[a-z0-9_]+\}/i', '', $out );
		// Collapse whitespace and tidy separators orphaned by empty values.
		$out = preg_replace( '/\s{2,}/', ' ', (string) $out );
		$out = trim( (string) $out, " \t\n\r\0\x0B-–—|·:" );

		return trim( $out );
	}

	/**
	 * Resolve which DSF surface (if any) is rendering this request and where its
	 * SEO settings live.
	 *
	 * @return array{type:string,source_id:int,post_id:int}
	 */
	private function resolve_context() {
		if ( null !== $this->context ) {
			return $this->context;
		}

		$context = array(
			'type'      => '',
			'source_id' => 0,
			'post_id'   => 0,
		);

		if ( class_exists( 'DSF_Shop_Templates' ) && DSF_Shop_Templates::is_product_archive() ) {
			$template_id = DSF_Shop_Templates::get_instance()->resolve_template_for_current_archive();
			if ( $template_id ) {
				$context = array(
					'type'      => 'shop',
					'source_id' => $template_id,
					'post_id'   => 0,
				);
			}
		} elseif ( class_exists( 'DSF_Blog_Templates' ) && DSF_Blog_Templates::is_blog_archive() ) {
			$template_id = DSF_Blog_Templates::get_instance()->resolve_template_for_current_archive();
			if ( $template_id ) {
				$context = array(
					'type'      => 'blog',
					'source_id' => $template_id,
					'post_id'   => 0,
				);
			}
		} elseif ( is_singular( 'product' ) && class_exists( 'DSF_Product_Templates' ) ) {
			$product_id  = get_queried_object_id();
			$template_id = $product_id ? DSF_Product_Templates::get_instance()->resolve_template_for_product( $product_id ) : 0;
			if ( $template_id ) {
				$context = array(
					'type'      => 'product',
					'source_id' => $template_id,
					'post_id'   => $product_id,
				);
			}
		} elseif ( is_singular( 'page' ) ) {
			$page_id = get_queried_object_id();
			if ( $page_id && get_post_meta( $page_id, '_dsf_enabled', true ) ) {
				$context = array(
					'type'      => 'page',
					'source_id' => $page_id,
					'post_id'   => $page_id,
				);
			}
		}

		$this->context = $context;
		return $context;
	}

	/**
	 * Read the sanitized SEO settings saved with a page/template.
	 *
	 * @param int $source_id Settings source post ID.
	 * @return array
	 */
	private function get_seo_settings( $source_id ) {
		$settings = get_post_meta( $source_id, '_dsf_settings', true );
		if ( ! is_array( $settings ) ) {
			$decoded  = $settings ? json_decode( (string) $settings, true ) : array();
			$settings = is_array( $decoded ) ? $decoded : array();
		}

		$seo = isset( $settings['seo'] ) && is_array( $settings['seo'] ) ? $settings['seo'] : array();

		return array(
			'title'       => isset( $seo['title'] ) ? (string) $seo['title'] : '',
			'description' => isset( $seo['description'] ) ? (string) $seo['description'] : '',
			'socialImage' => isset( $seo['socialImage'] ) ? (string) $seo['socialImage'] : '',
			'canonical'   => isset( $seo['canonical'] ) ? (string) $seo['canonical'] : '',
			'noindex'     => ! empty( $seo['noindex'] ),
			'nofollow'    => ! empty( $seo['nofollow'] ),
		);
	}

	/**
	 * Gather the variable values for the current request.
	 *
	 * @param array $context Resolved flow context.
	 * @return array
	 */
	private function gather_values( $context ) {
		$defaults = self::get_defaults();
		$values   = array(
			'site_name' => get_bloginfo( 'name' ),
			'tagline'   => get_bloginfo( 'description' ),
			'sep'       => $defaults['titleSeparator'],
			'title'     => '',
			'excerpt'   => '',
			'price'     => '',
			'category'  => '',
		);

		switch ( $context['type'] ) {
			case 'page':
				$values['title']   = get_the_title( $context['post_id'] );
				$values['excerpt'] = get_the_excerpt( $context['post_id'] );
				break;

			case 'product':
				$values['title'] = get_the_title( $context['post_id'] );
				if ( function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $context['post_id'] );
					if ( $product ) {
						$values['excerpt'] = wp_strip_all_tags( (string) $product->get_short_description() );
						$values['price']   = function_exists( 'wc_price' )
							? html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, get_bloginfo( 'charset' ) )
							: '';
					}
				}
				$terms = get_the_terms( $context['post_id'], 'product_cat' );
				if ( is_array( $terms ) && isset( $terms[0]->name ) ) {
					$values['category'] = $terms[0]->name;
				}
				break;

			case 'shop':
				$values['title'] = function_exists( 'woocommerce_page_title' )
					? woocommerce_page_title( false )
					: get_the_archive_title();
				if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
					$values['excerpt']  = wp_strip_all_tags( (string) term_description() );
					$term               = get_queried_object();
					$values['category'] = ( $term && isset( $term->name ) ) ? $term->name : '';
				}
				break;

			case 'blog':
				if ( is_home() ) {
					$posts_page      = (int) get_option( 'page_for_posts' );
					$values['title'] = $posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'designstudio-flow' );
				} else {
					$values['title']   = wp_strip_all_tags( get_the_archive_title() );
					$values['excerpt'] = wp_strip_all_tags( get_the_archive_description() );
				}
				if ( is_category() ) {
					$term               = get_queried_object();
					$values['category'] = ( $term && isset( $term->name ) ) ? $term->name : '';
				}
				break;
		}

		foreach ( $values as $key => $value ) {
			$values[ $key ] = sanitize_text_field( wp_strip_all_tags( (string) $value ) );
		}
		// Descriptions built from long excerpts stay a sane length.
		$values['excerpt'] = wp_html_excerpt( $values['excerpt'], 200 );

		return $values;
	}

	/**
	 * The resolved custom title for this request, or '' to leave core alone.
	 *
	 * @return string
	 */
	private function resolved_title() {
		if ( self::has_seo_plugin() ) {
			return '';
		}

		$context = $this->resolve_context();
		if ( ! $context['type'] ) {
			return '';
		}

		$seo = $this->get_seo_settings( $context['source_id'] );
		if ( '' === trim( $seo['title'] ) ) {
			return '';
		}

		return self::apply_template( $seo['title'], $this->gather_values( $context ) );
	}

	/**
	 * Replace the document title when a custom SEO title is set.
	 *
	 * @param string $title Existing pre-filter value.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		$custom = $this->resolved_title();
		return '' !== $custom ? $custom : $title;
	}

	/**
	 * Apply the noindex switch through core's robots API.
	 *
	 * @param array $robots Core robots directives.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		if ( self::has_seo_plugin() ) {
			return $robots;
		}

		$context = $this->resolve_context();
		if ( ! $context['type'] ) {
			return $robots;
		}

		$seo = $this->get_seo_settings( $context['source_id'] );

		if ( $seo['noindex'] ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
			// A noindex page can still pass link equity unless the author also
			// asked for nofollow.
			if ( $seo['nofollow'] ) {
				$robots['nofollow'] = true;
				unset( $robots['follow'] );
			} else {
				$robots['follow'] = true;
			}
			return $robots;
		}

		if ( $seo['nofollow'] ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}

		// Indexable: give search engines the widest preview allowances so rich
		// snippets, large image thumbnails, and video previews are permitted.
		$robots['max-snippet']       = -1;
		$robots['max-image-preview'] = 'large';
		$robots['max-video-preview'] = -1;

		return $robots;
	}

	/**
	 * Output description, canonical, Open Graph, and Twitter tags.
	 */
	public function output_head_tags() {
		if ( self::has_seo_plugin() ) {
			return;
		}

		$context = $this->resolve_context();
		if ( ! $context['type'] ) {
			return;
		}

		$seo    = $this->get_seo_settings( $context['source_id'] );
		$values = $this->gather_values( $context );

		$title       = '' !== trim( $seo['title'] ) ? self::apply_template( $seo['title'], $values ) : '';
		$description = $this->resolve_description( $seo, $values, $context );

		$url = $this->current_url();

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		if ( '' !== $seo['canonical'] ) {
			// A custom canonical replaces core's (core only prints one on singular).
			remove_action( 'wp_head', 'rel_canonical' );
			echo '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '" />' . "\n";
		}

		// Open Graph + Twitter. Title falls back to the document title so shares
		// look right even when only a description was customized.
		$og_title = '' !== $title ? $title : wp_get_document_title();
		$og_image = $this->resolve_social_image_meta( $seo );
		$og_type  = $this->og_type_for_context( $context['type'] );

		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
		if ( '' !== $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( $this->og_locale() ) . '" />' . "\n";

		if ( '' !== $og_image['url'] ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image['url'] ) . '" />' . "\n";
			if ( $og_image['width'] > 0 && $og_image['height'] > 0 ) {
				echo '<meta property="og:image:width" content="' . esc_attr( (string) $og_image['width'] ) . '" />' . "\n";
				echo '<meta property="og:image:height" content="' . esc_attr( (string) $og_image['height'] ) . '" />' . "\n";
			}
			if ( '' !== $og_image['alt'] ) {
				echo '<meta property="og:image:alt" content="' . esc_attr( $og_image['alt'] ) . '" />' . "\n";
			}
		}

		// og:updated_time / article timestamps let crawlers see content freshness.
		if ( 'page' === $context['type'] && $context['post_id'] ) {
			$published = get_post_time( 'c', true, $context['post_id'] );
			$modified  = get_post_modified_time( 'c', true, $context['post_id'] );
			if ( $published ) {
				echo '<meta property="article:published_time" content="' . esc_attr( $published ) . '" />' . "\n";
			}
			if ( $modified ) {
				echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";
				echo '<meta property="og:updated_time" content="' . esc_attr( $modified ) . '" />' . "\n";
			}
		}

		// Open Graph product tags (price / currency / availability) for shares and
		// shopping surfaces. WooCommerce is the source of truth.
		if ( 'product' === $context['type'] && $context['post_id'] && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $context['post_id'] );
			if ( $product ) {
				$price = $product->get_price();
				if ( '' !== (string) $price ) {
					echo '<meta property="product:price:amount" content="' . esc_attr( (string) $price ) . '" />' . "\n";
					if ( function_exists( 'get_woocommerce_currency' ) ) {
						echo '<meta property="product:price:currency" content="' . esc_attr( get_woocommerce_currency() ) . '" />' . "\n";
					}
				}
				echo '<meta property="product:availability" content="' . esc_attr( $product->is_in_stock() ? 'instock' : 'outofstock' ) . '" />' . "\n";
			}
		}

		// Twitter card.
		echo '<meta name="twitter:card" content="' . ( '' !== $og_image['url'] ? 'summary_large_image' : 'summary' ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static enum.
		$twitter_site = self::get_defaults()['twitterSite'];
		if ( '' !== $twitter_site ) {
			echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '" />' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
		if ( '' !== $description ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
		if ( '' !== $og_image['url'] && '' !== $og_image['alt'] ) {
			echo '<meta name="twitter:image:alt" content="' . esc_attr( $og_image['alt'] ) . '" />' . "\n";
		}
	}

	/**
	 * og:type for a DSF surface. Content pages read as "article" (freshness
	 * signals apply), products as "product", archives as "website".
	 *
	 * @param string $type Context type.
	 * @return string
	 */
	private function og_type_for_context( $type ) {
		if ( 'product' === $type ) {
			return 'product';
		}
		if ( 'page' === $type ) {
			return 'article';
		}
		return 'website';
	}

	/**
	 * The og:locale value (e.g. en_US) from the site locale.
	 *
	 * @return string
	 */
	private function og_locale() {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		return $locale ? $locale : 'en_US';
	}

	/**
	 * The final meta description: the authored (templated) value, else the
	 * surface's excerpt/short description, else text derived from the first
	 * content block — so every DSF surface ships a description.
	 *
	 * @param array $seo     SEO settings.
	 * @param array $values  Gathered template values.
	 * @param array $context Resolved context.
	 * @return string
	 */
	private function resolve_description( $seo, $values, $context ) {
		if ( '' !== trim( $seo['description'] ) ) {
			return self::apply_template( $seo['description'], $values );
		}

		if ( '' !== trim( (string) $values['excerpt'] ) ) {
			return trim( (string) $values['excerpt'] );
		}

		$derived = $this->derive_description_from_blocks( $context['source_id'] );
		return '' !== $derived ? $derived : '';
	}

	/**
	 * Best-effort description from a surface's block content: the first text-ish
	 * block's copy, stripped and length-capped. Returns '' when nothing usable.
	 *
	 * @param int $source_id Settings/content source post ID.
	 * @return string
	 */
	private function derive_description_from_blocks( $source_id ) {
		$blocks = $this->read_blocks( $source_id );
		if ( empty( $blocks ) ) {
			return '';
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			foreach ( array( 'content', 'text', 'subtitle', 'description', 'body', 'intro' ) as $key ) {
				if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
					$text = trim( wp_strip_all_tags( $settings[ $key ] ) );
					if ( '' !== $text ) {
						return wp_html_excerpt( $text, 200, '…' );
					}
				}
			}
		}

		return '';
	}

	/**
	 * Decode a surface's saved blocks (_dsf_blocks meta) into an array.
	 *
	 * @param int $source_id Post ID.
	 * @return array
	 */
	private function read_blocks( $source_id ) {
		if ( ! $source_id ) {
			return array();
		}
		$meta = get_post_meta( $source_id, '_dsf_blocks', true );
		if ( is_array( $meta ) ) {
			return $meta;
		}
		$decoded = $meta ? json_decode( (string) $meta, true ) : array();
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * The social-share image plus its dimensions and alt text.
	 *
	 * Uses the explicit SEO field, else the surface's hero image; when the URL
	 * resolves to a local attachment, its real width/height/alt are attached so
	 * cards render crisply.
	 *
	 * @param array $seo SEO settings.
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	private function resolve_social_image_meta( $seo ) {
		$url = '';
		if ( '' !== $seo['socialImage'] ) {
			$url = $seo['socialImage'];
		} elseif ( class_exists( 'DSF_Frontend' ) && DSF_Frontend::get_instance()->get_hero_image_url() ) {
			$url = DSF_Frontend::get_instance()->get_hero_image_url();
		} else {
			// Site-wide fallback so every share still gets an image.
			$defaults = self::get_defaults();
			if ( '' !== $defaults['defaultSocialImage'] ) {
				$url = $defaults['defaultSocialImage'];
			}
		}

		$meta = array(
			'url'    => $url,
			'width'  => 0,
			'height' => 0,
			'alt'    => '',
		);
		if ( '' === $url ) {
			return $meta;
		}

		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( is_array( $src ) ) {
				$meta['width']  = isset( $src[1] ) ? (int) $src[1] : 0;
				$meta['height'] = isset( $src[2] ) ? (int) $src[2] : 0;
			}
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( is_string( $alt ) && '' !== $alt ) {
				$meta['alt'] = sanitize_text_field( $alt );
			}
		}

		return $meta;
	}

	/**
	 * The canonical-ish URL of the current request for og:url.
	 *
	 * @return string
	 */
	private function current_url() {
		if ( is_singular() ) {
			$permalink = get_permalink( get_queried_object_id() );
			return $permalink ? $permalink : '';
		}

		global $wp;
		if ( $wp instanceof WP && isset( $wp->request ) ) {
			return home_url( add_query_arg( array(), $wp->request ) );
		}

		return '';
	}

	/**
	 * Output the JSON-LD @graph (Organization, WebSite, WebPage, and where they
	 * apply Product, FAQPage, and BreadcrumbList) for this DSF surface.
	 */
	public function output_json_ld() {
		if ( self::has_seo_plugin() ) {
			return;
		}

		$context = $this->resolve_context();
		if ( ! $context['type'] ) {
			return;
		}

		$seo    = $this->get_seo_settings( $context['source_id'] );
		$values = $this->gather_values( $context );

		$title    = '' !== trim( $seo['title'] ) ? self::apply_template( $seo['title'], $values ) : wp_get_document_title();
		$defaults = self::get_defaults();
		$logo     = '' !== $defaults['orgLogo']
			? $defaults['orgLogo']
			: ( function_exists( 'get_site_icon_url' ) ? (string) get_site_icon_url( 512 ) : '' );

		$graph = self::build_json_ld_graph(
			array(
				'type'        => $context['type'],
				'site_name'   => get_bloginfo( 'name' ),
				'org_name'    => '' !== $defaults['orgName'] ? $defaults['orgName'] : get_bloginfo( 'name' ),
				'site_url'    => home_url( '/' ),
				'locale'      => $this->og_locale(),
				'title'       => $title,
				'description' => $this->resolve_description( $seo, $values, $context ),
				'url'         => $this->current_url(),
				'image'       => $this->resolve_social_image_meta( $seo ),
				'logo'        => $logo,
				'sameas'      => $defaults['socialProfiles'],
				'search_url'  => home_url( '/?s={search_term_string}' ),
				'breadcrumbs' => $this->build_breadcrumb_items( $context ),
				'faq'         => self::extract_faq_entities( $this->read_blocks( $context['source_id'] ) ),
				'product'     => $this->gather_product_data( $context ),
			)
		);

		if ( empty( $graph['@graph'] ) ) {
			return;
		}

		// JSON_HEX_TAG escapes < and > so the payload can never break out of the
		// <script> element; values are otherwise plain data assembled server-side.
		$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( ! $json ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG-encoded JSON-LD.
	}

	/**
	 * Build the JSON-LD @graph from normalized inputs. Pure (no WP calls) so the
	 * node shape is unit-testable.
	 *
	 * @param array $args Normalized SEO/context data.
	 * @return array{"@context":string,"@graph":array}
	 */
	public static function build_json_ld_graph( $args ) {
		$site_url = (string) ( $args['site_url'] ?? '' );
		$url      = (string) ( $args['url'] ?? '' );
		$org_id   = $site_url . '#organization';
		$site_id  = $site_url . '#website';
		$page_id  = ( '' !== $url ? $url : $site_url ) . '#webpage';
		$graph    = array();

		// Organization (publisher).
		$org = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => (string) ( $args['org_name'] ?? $args['site_name'] ?? '' ),
			'url'   => $site_url,
		);
		if ( ! empty( $args['logo'] ) ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => (string) $args['logo'],
			);
			// image shorthand aids consumers that read Organization.image.
			$org['image'] = array( '@id' => $org_id . '#logo' );
			$org['logo']['@id'] = $org_id . '#logo';
		}
		$sameas = is_array( $args['sameas'] ?? null ) ? array_values( array_filter( array_map( 'strval', $args['sameas'] ) ) ) : array();
		if ( ! empty( $sameas ) ) {
			$org['sameAs'] = $sameas;
		}
		$graph[] = $org;

		// WebSite (with sitewide SearchAction).
		$website = array(
			'@type'     => 'WebSite',
			'@id'       => $site_id,
			'url'       => $site_url,
			'name'      => (string) ( $args['site_name'] ?? '' ),
			'publisher' => array( '@id' => $org_id ),
		);
		if ( ! empty( $args['search_url'] ) ) {
			$website['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => (string) $args['search_url'],
				),
				'query-input' => 'required name=search_term_string',
			);
		}
		$graph[] = $website;

		// Primary image node.
		$image      = is_array( $args['image'] ?? null ) ? $args['image'] : array();
		$image_node = null;
		if ( ! empty( $image['url'] ) ) {
			$image_node = array(
				'@type' => 'ImageObject',
				'@id'   => $page_id . '#primaryimage',
				'url'   => (string) $image['url'],
			);
			if ( ! empty( $image['width'] ) ) {
				$image_node['width'] = (int) $image['width'];
			}
			if ( ! empty( $image['height'] ) ) {
				$image_node['height'] = (int) $image['height'];
			}
			if ( ! empty( $image['alt'] ) ) {
				$image_node['caption'] = (string) $image['alt'];
			}
			$graph[] = $image_node;
		}

		// WebPage / CollectionPage / ItemPage.
		$page_types = array(
			'page'    => 'WebPage',
			'product' => 'ItemPage',
			'shop'    => 'CollectionPage',
			'blog'    => 'CollectionPage',
		);
		$type    = (string) ( $args['type'] ?? 'page' );
		$webpage = array(
			'@type'    => $page_types[ $type ] ?? 'WebPage',
			'@id'      => $page_id,
			'url'      => '' !== $url ? $url : $site_url,
			'name'     => (string) ( $args['title'] ?? '' ),
			'isPartOf' => array( '@id' => $site_id ),
		);
		if ( ! empty( $args['description'] ) ) {
			$webpage['description'] = (string) $args['description'];
		}
		if ( ! empty( $args['locale'] ) ) {
			$webpage['inLanguage'] = str_replace( '_', '-', (string) $args['locale'] );
		}
		if ( $image_node ) {
			$webpage['primaryImageOfPage'] = array( '@id' => $image_node['@id'] );
		}

		$breadcrumbs = is_array( $args['breadcrumbs'] ?? null ) ? $args['breadcrumbs'] : array();
		if ( count( $breadcrumbs ) > 1 ) {
			$webpage['breadcrumb'] = array( '@id' => $page_id . '#breadcrumb' );
		}
		$graph[] = $webpage;

		// BreadcrumbList.
		if ( count( $breadcrumbs ) > 1 ) {
			$items = array();
			$pos   = 1;
			foreach ( $breadcrumbs as $crumb ) {
				if ( empty( $crumb['name'] ) ) {
					continue;
				}
				$element = array(
					'@type'    => 'ListItem',
					'position' => $pos,
					'name'     => (string) $crumb['name'],
				);
				if ( ! empty( $crumb['url'] ) ) {
					$element['item'] = (string) $crumb['url'];
				}
				$items[] = $element;
				$pos++;
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $page_id . '#breadcrumb',
				'itemListElement' => $items,
			);
		}

		// Product.
		$product = is_array( $args['product'] ?? null ) ? $args['product'] : array();
		if ( ! empty( $product ) && ! empty( $product['name'] ) ) {
			$node = array(
				'@type'       => 'Product',
				'@id'         => $page_id . '#product',
				'name'        => (string) $product['name'],
			);
			if ( ! empty( $args['description'] ) ) {
				$node['description'] = (string) $args['description'];
			}
			if ( $image_node ) {
				$node['image'] = array( '@id' => $image_node['@id'] );
			}
			if ( ! empty( $product['sku'] ) ) {
				$node['sku'] = (string) $product['sku'];
			}
			if ( ! empty( $product['brand'] ) ) {
				$node['brand'] = array(
					'@type' => 'Brand',
					'name'  => (string) $product['brand'],
				);
			}
			if ( '' !== (string) ( $product['price'] ?? '' ) ) {
				$node['offers'] = array(
					'@type'         => 'Offer',
					'price'         => (string) $product['price'],
					'priceCurrency' => (string) ( $product['currency'] ?? '' ),
					'availability'  => 'instock' === ( $product['availability'] ?? '' )
						? 'https://schema.org/InStock'
						: 'https://schema.org/OutOfStock',
					'url'           => '' !== $url ? $url : $site_url,
				);
			}
			if ( ! empty( $product['rating'] ) && ! empty( $product['review_count'] ) ) {
				$node['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => (string) $product['rating'],
					'reviewCount' => (int) $product['review_count'],
				);
			}
			$graph[] = $node;
		}

		// FAQPage (from FAQ blocks).
		$faq = is_array( $args['faq'] ?? null ) ? $args['faq'] : array();
		if ( ! empty( $faq ) ) {
			$entities = array();
			foreach ( $faq as $item ) {
				if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
					continue;
				}
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => (string) $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => (string) $item['answer'],
					),
				);
			}
			if ( ! empty( $entities ) ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => $page_id . '#faq',
					'mainEntity' => $entities,
				);
			}
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Extract FAQ question/answer pairs from a surface's blocks for FAQPage
	 * schema. Answers are stripped to plain text. Pure (no WP-context reads).
	 *
	 * @param array $blocks Decoded blocks.
	 * @return array<int,array{question:string,answer:string}>
	 */
	public static function extract_faq_entities( $blocks ) {
		$out = array();
		if ( ! is_array( $blocks ) ) {
			return $out;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ( $block['type'] ?? '' ) !== 'faq' ) {
				continue;
			}
			$settings = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$items    = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$question = trim( wp_strip_all_tags( (string) ( $item['question'] ?? '' ) ) );
				$answer   = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );
				if ( '' !== $question && '' !== $answer ) {
					$out[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Gather WooCommerce product data for Product schema, or array() when not a
	 * product surface.
	 *
	 * @param array $context Resolved context.
	 * @return array
	 */
	private function gather_product_data( $context ) {
		if ( 'product' !== $context['type'] || ! $context['post_id'] || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( $context['post_id'] );
		if ( ! $product ) {
			return array();
		}

		$brand = '';
		$terms = get_the_terms( $context['post_id'], 'product_brand' );
		if ( is_array( $terms ) && isset( $terms[0]->name ) ) {
			$brand = $terms[0]->name;
		}

		return array(
			'name'         => get_the_title( $context['post_id'] ),
			'sku'          => (string) $product->get_sku(),
			'price'        => (string) $product->get_price(),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'availability' => $product->is_in_stock() ? 'instock' : 'outofstock',
			'brand'        => $brand,
			'rating'       => (string) $product->get_average_rating(),
			'review_count' => (int) $product->get_review_count(),
		);
	}

	/**
	 * The breadcrumb trail for the current request, for the Breadcrumbs block to
	 * render. Independent of the SEO-plugin gate (the visual block should work
	 * even when a dedicated SEO plugin owns the meta tags).
	 *
	 * @return array<int,array{name:string,url:string}>
	 */
	public function get_current_breadcrumb_trail() {
		$context = $this->resolve_context();
		if ( ! $context['type'] ) {
			return array();
		}
		return $this->build_breadcrumb_items( $context );
	}

	/**
	 * Build breadcrumb trail items ({name,url}) for the current surface.
	 *
	 * @param array $context Resolved context.
	 * @return array<int,array{name:string,url:string}>
	 */
	private function build_breadcrumb_items( $context ) {
		$home  = array(
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		);
		$items = array( $home );

		switch ( $context['type'] ) {
			case 'page':
				$ancestors = array_reverse( get_post_ancestors( $context['post_id'] ) );
				foreach ( $ancestors as $ancestor_id ) {
					$items[] = array(
						'name' => get_the_title( $ancestor_id ),
						'url'  => (string) get_permalink( $ancestor_id ),
					);
				}
				$items[] = array(
					'name' => get_the_title( $context['post_id'] ),
					'url'  => (string) get_permalink( $context['post_id'] ),
				);
				break;

			case 'product':
				if ( function_exists( 'wc_get_page_permalink' ) ) {
					$shop = wc_get_page_permalink( 'shop' );
					if ( $shop ) {
						$items[] = array(
							'name' => __( 'Shop', 'designstudio-flow' ),
							'url'  => $shop,
						);
					}
				}
				$terms = get_the_terms( $context['post_id'], 'product_cat' );
				if ( is_array( $terms ) && isset( $terms[0] ) ) {
					$link    = get_term_link( $terms[0] );
					$items[] = array(
						'name' => $terms[0]->name,
						'url'  => is_wp_error( $link ) ? '' : (string) $link,
					);
				}
				$items[] = array(
					'name' => get_the_title( $context['post_id'] ),
					'url'  => (string) get_permalink( $context['post_id'] ),
				);
				break;

			case 'shop':
			case 'blog':
				$items[] = array(
					'name' => wp_strip_all_tags( (string) get_the_archive_title() ),
					'url'  => $this->current_url(),
				);
				break;
		}

		return $items;
	}

	/**
	 * Keep DSF pages flagged noindex out of the core WordPress sitemap, so the
	 * plugin never advertises a URL it is asking search engines not to index.
	 *
	 * @param array  $args      Query args for the sitemap provider.
	 * @param string $post_type Post type being listed.
	 * @return array
	 */
	public function exclude_noindex_from_sitemap( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}

		$meta_query   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => '_dsf_noindex',
			'compare' => 'NOT EXISTS',
		);
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		return $args;
	}
}
