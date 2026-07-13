<?php
/**
 * Create or refresh the editable DesignStudio Flow product landing page.
 *
 * Run with: php scripts/seed-product-landing-page.php
 */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

// Yoast's indexable ORM exhausts the default 128M limit when saving from CLI
// (WordPress pins memory_limit to WP_MEMORY_LIMIT, so -d flags cannot raise it).
if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
	define( 'WP_MEMORY_LIMIT', '512M' );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

// In a bare CLI/wp-load context Elementor's usage tracker wp_die()s on any
// wp_insert_post (its callback is registered on a manager-built instance, so a
// plain remove_action() cannot target it). Strip every Elementor callback from
// the status-transition hook for this run only.
global $wp_filter;
if ( isset( $wp_filter['transition_post_status'] ) ) {
	foreach ( $wp_filter['transition_post_status']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $key => $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && 0 === strpos( get_class( $fn[0] ), 'Elementor' ) ) {
				unset( $wp_filter['transition_post_status']->callbacks[ $priority ][ $key ] );
			}
		}
	}
}

$preferred_slug = 'landing-page';
$existing       = get_page_by_path( $preferred_slug, OBJECT, 'page' );

$post_data = array(
	'post_title'   => 'DesignStudio Flow',
	'post_name'    => $preferred_slug,
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_content' => '',
);

if ( $existing ) {
	$post_data['ID'] = $existing->ID;
}

$post_id = wp_insert_post( wp_slash( $post_data ), true );
if ( is_wp_error( $post_id ) ) {
	fwrite( STDERR, $post_id->get_error_message() . PHP_EOL );
	exit( 1 );
}

$make_block = static function ( $id, $type, $settings ) {
	return array(
		'id'       => $id,
		'type'     => $type,
		'settings' => array_merge(
			array(
				'paddingX' => 0,
				'marginY'  => 0,
			),
			$settings
		),
	);
};

$blocks = array(
	$make_block(
		'dsflow-landing-header',
		'landing-dock-header',
		array(
			'brandText' => 'DesignStudio Flow',
			'homeUrl'   => '#top',
			'navLinks'  => array(
				array( 'label' => 'Why DSFlow', 'url' => '#why-dsflow', 'icon' => 'dsflow-why', 'iconImage' => '' ),
				array( 'label' => 'Blocks', 'url' => '#blocks', 'icon' => 'dsflow-blocks', 'iconImage' => '' ),
				array( 'label' => 'Design included', 'url' => '#ready', 'icon' => 'dsflow-ready', 'iconImage' => '' ),
				array( 'label' => 'Visual editor', 'url' => '#editor', 'icon' => 'dsflow-editor', 'iconImage' => '' ),
				array( 'label' => 'Theme controls', 'url' => '#theme', 'icon' => 'dsflow-theme', 'iconImage' => '' ),
				array( 'label' => 'WooCommerce', 'url' => '#woocommerce', 'icon' => 'dsflow-commerce', 'iconImage' => '' ),
				array( 'label' => 'Headers & footers', 'url' => '#layouts', 'icon' => 'dsflow-layouts', 'iconImage' => '' ),
				array( 'label' => 'Campaigns', 'url' => '#campaigns', 'icon' => 'dsflow-campaigns', 'iconImage' => '' ),
				array( 'label' => 'Forms & growth', 'url' => '#engagement', 'icon' => 'dsflow-engagement', 'iconImage' => '' ),
				array( 'label' => 'SEO', 'url' => '#seo', 'icon' => 'dsflow-seo', 'iconImage' => '' ),
				array( 'label' => 'Security', 'url' => '#security', 'icon' => 'dsflow-security', 'iconImage' => '' ),
				array( 'label' => 'For agencies', 'url' => '#audience', 'icon' => 'dsflow-agencies', 'iconImage' => '' ),
				array( 'label' => 'Workflow', 'url' => '#workflow', 'icon' => 'dsflow-workflow', 'iconImage' => '' ),
				array( 'label' => 'Redirects', 'url' => '#redirects', 'icon' => 'dsflow-redirects', 'iconImage' => '' ),
				array( 'label' => 'Email delivery', 'url' => '#mail', 'icon' => 'dsflow-mail', 'iconImage' => '' ),
				array( 'label' => 'Get DSFlow', 'url' => '#get-dsflow', 'icon' => 'dsflow-launch', 'iconImage' => '' ),
			),
		)
	),
	$make_block(
		'dsflow-landing-showcase-hero',
		'landing-showcase-hero',
		array(
			'eyebrow'       => 'THE VISUAL WORDPRESS SYSTEM',
			'title'         => 'Design your',
			'rotatingWords' => 'WordPress site, page visually, online store, next campaign, site securely, client site',
			'tagline'       => 'Design pages, theme styles, WooCommerce stores, layouts, campaigns, and forms in one visual builder.',
			'primaryText'   => 'Experience DSFlow',
			'primaryUrl'    => '#get-dsflow',
			'secondaryText' => 'Browse 40+ blocks',
			'secondaryUrl'  => '#blocks',
			'chip1'         => '40+ designed blocks',
			'chip2'         => 'WooCommerce ready',
			'chip3'         => 'Built with security in mind',
			'tiles'         => array(
				array( 'label' => 'Design included', 'url' => '#ready', 'icon' => 'wand', 'iconImage' => '' ),
				array( 'label' => 'Visual editor', 'url' => '#editor', 'icon' => 'mouse-pointer', 'iconImage' => '' ),
				array( 'label' => 'WooCommerce', 'url' => '#woocommerce', 'icon' => 'store', 'iconImage' => '' ),
				array( 'label' => 'Forms & growth', 'url' => '#engagement', 'icon' => 'mail', 'iconImage' => '' ),
				array( 'label' => 'Security', 'url' => '#security', 'icon' => 'shield-check', 'iconImage' => '' ),
				array( 'label' => 'For agencies', 'url' => '#audience', 'icon' => 'briefcase', 'iconImage' => '' ),
			),
		)
	),
	$make_block(
		'dsflow-block-explorer',
		'landing-block-explorer',
		array(
			'eyebrow'     => 'A LIBRARY WITH A POINT OF VIEW',
			'title'       => 'Start with structure. Finish with something original.',
			'description' => 'Heroes, content, commerce, forms, headers, and footers — every block solves a real page-building problem, then gives your team room to make it original.',
			'footnote'    => 'New blocks inherit the same editing, theme, responsive, and frontend rendering workflow.',
		)
	),
	$make_block(
		'dsflow-block-ready',
		'landing-block-ready',
		array(
			'eyebrow'     => 'DESIGN INCLUDED',
			'title'       => 'Add a block. The design is already done.',
			'description' => 'Every block ships fully designed and responsive. Drop one onto the page, swap in your own words and images, and publish — no layout work, no CSS, nothing to wire up.',
			'step1Title'  => 'Pick a block',
			'step1Text'   => 'Choose from a library of purpose-built sections made for real pages.',
			'step2Title'  => 'It lands fully styled',
			'step2Text'   => 'Spacing, type, color, and responsive behavior are already handled for you.',
			'step3Title'  => 'Change only the content',
			'step3Text'   => 'Edit the copy and images in place, then publish with confidence.',
			'note'        => 'Every new block inherits the same theme, responsive, and publishing workflow.',
			'demoEyebrow' => 'COUNTDOWN TO LAUNCH',
			'demoTitle'   => 'Launch day is almost here',
			'demoText'    => 'Be first in line when the doors open.',
			'demoButton'  => 'Reserve your spot',
		)
	),
	$make_block(
		'dsflow-editor-story',
		'landing-product-story',
		array(
			'variant'       => 'editor',
			'reverseLayout' => false,
			'eyebrow'       => 'EDIT THE EXPERIENCE',
			'title'         => 'A visual workflow that still respects the system.',
			'description'   => 'Work directly with the page, understand every choice, and keep the guardrails that make a site coherent.',
			'featureOne'    => 'Edit the same component visitors receive',
			'featureTwo'    => 'Responsive controls stay close to the work',
			'featureThree'  => 'Page and block settings have clear ownership',
		)
	),
	$make_block(
		'dsflow-theme-story',
		'landing-product-story',
		array(
			'variant'       => 'theme',
			'reverseLayout' => true,
			'eyebrow'       => 'CONSISTENCY WITHOUT THE CAGE',
			'title'         => 'Set the visual language once. Let every block speak it.',
			'description'   => 'Page and site theme controls connect typography and color choices to the blocks that depend on them.',
			'featureOne'    => 'Shared heading and body typography',
			'featureTwo'    => 'Theme-linked primary and secondary colors',
			'featureThree'  => 'One-click undo, so you can explore with confidence',
		)
	),
	$make_block(
		'dsflow-commerce-story',
		'landing-product-story',
		array(
			'variant'       => 'commerce',
			'reverseLayout' => false,
			'eyebrow'       => 'WOOCOMMERCE, COMPOSED',
			'title'         => 'Turn the catalog into a guided buying experience.',
			'description'   => 'Build product-led pages with category-aware filters, search, manual ordering, and actions connected to WooCommerce.',
			'featureOne'    => 'Search and filters respect the selected product source',
			'featureTwo'    => 'Parent categories include products from descendants',
			'featureThree'  => 'Product and category ordering stays intentional',
		)
	),
	$make_block(
		'dsflow-layouts-story',
		'landing-product-story',
		array(
			'variant'       => 'layouts',
			'reverseLayout' => true,
			'eyebrow'       => 'FROM FIRST IMPRESSION TO FINAL LINK',
			'title'         => 'Headers and footers belong to the same design conversation.',
			'description'   => 'Compose site-wide navigation and footer templates with the same visual language used for the page between them.',
			'featureOne'    => 'Purpose-built navigation and mega-menu patterns',
			'featureTwo'    => 'Desktop and mobile behavior in one component',
			'featureThree'  => 'One-header and one-footer template guardrails',
		)
	),
	$make_block(
		'dsflow-campaigns-story',
		'landing-product-story',
		array(
			'variant'       => 'campaigns',
			'reverseLayout' => false,
			'eyebrow'       => 'CAMPAIGNS WITH TIMING',
			'title'         => 'Launch the moment, not just the page.',
			'description'   => 'Create popups, countdowns, notification bars, and promotional sections that respect timing and repeat visits.',
			'featureOne'    => 'Page popups with date, delay, and cookie controls',
			'featureTwo'    => 'Site-wide notification bar scheduling',
			'featureThree'  => 'Countdown expiration messaging and CTA actions',
		)
	),
	$make_block(
		'dsflow-engagement-suite',
		'landing-engagement-suite',
		array(
			'eyebrow'                => 'FROM VISIT TO CONVERSATION',
			'title'                  => 'Build the page. Then help it do something.',
			'description'            => 'Forms, popups, and notification bars bring the next action into the same visual system as the page around them.',
			'formsTitle'              => 'Forms that belong to the design.',
			'formsDescription'        => 'Build native forms or bring Gravity Forms into Flow, then keep fields, labels, buttons, and responsive behavior visually consistent.',
			'popupTitle'              => 'The right message at the right moment.',
			'popupDescription'        => 'Create image or content popups with scheduling, delay, sizing, CTA, and repeat-visit controls.',
			'notificationTitle'       => 'One announcement across the whole site.',
			'notificationDescription' => 'Publish a site-wide message with clear timing and a visual style connected to the rest of the experience.',
		)
	),
	$make_block(
		'dsflow-seo-proof',
		'landing-trust-workflow',
		array(
			'variant'     => 'seo',
			'eyebrow'     => 'VISIBLE TO PEOPLE AND MACHINES',
			'title'       => 'A visual page should still be a real WordPress page.',
			'description' => 'DesignStudio Flow saves an HTML snapshot alongside the interactive frontend, giving every page useful content before JavaScript takes over.',
		)
	),
	$make_block(
		'dsflow-security-proof',
		'landing-trust-workflow',
		array(
			'variant'     => 'security',
			'eyebrow'     => 'SECURITY IS PART OF THE COMPONENT',
			'title'       => 'Creative controls. Deliberate trust boundaries.',
			'description' => 'The block workflow is designed around WordPress permissions, server-side sanitization, safe output, and bounded data contracts.',
		)
	),
	$make_block(
		'dsflow-audience-proof',
		'landing-trust-workflow',
		array(
			'variant'     => 'audience',
			'eyebrow'     => 'ONE BUILDER, DIFFERENT KINDS OF MOMENTUM',
			'title'       => 'Useful to the people who shape, sell, and maintain the site.',
			'description' => 'DesignStudio Flow creates a shared visual language between creative teams, agencies, commerce operators, and site owners.',
		)
	),
	$make_block(
		'dsflow-workflow-proof',
		'landing-trust-workflow',
		array(
			'variant'     => 'workflow',
			'eyebrow'     => 'FROM BLANK PAGE TO PUBLISHED',
			'title'       => 'A straightforward path through ambitious work.',
			'description' => 'The builder keeps every step clear, so teams move quickly without losing track of what WordPress will publish.',
		)
	),
	array(
		'id'       => 'dsflow-landing-faq',
		'type'     => 'faq',
		'settings' => array(
			'title'           => 'Questions before you start flowing',
			'items'           => array(
				array( 'question' => 'What is DesignStudio Flow?', 'answer' => '<p>DesignStudio Flow is a visual, block-based page-building workflow for normal WordPress pages, with reusable headers, footers, commerce tools, campaigns, and theme controls.</p>' ),
				array( 'question' => 'Does it replace the WordPress page system?', 'answer' => '<p>No. Flow pages remain WordPress pages with their own title, slug, status, parent, permalink, and publishing lifecycle.</p>' ),
				array( 'question' => 'Can it use WooCommerce products?', 'answer' => '<p>Yes. Product blocks can use manual products or category-based sources with search, filters, ordering, and category-aware results.</p>' ),
				array( 'question' => 'Can teams control the design system?', 'answer' => '<p>Yes. Theme settings connect page colors and typography to blocks, while individual block controls handle the exceptions that genuinely need to be different.</p>' ),
				array( 'question' => 'What happens before the frontend JavaScript loads?', 'answer' => '<p>DesignStudio Flow can store a sanitized HTML snapshot with the page so meaningful page content is available for first paint and search crawlers.</p>' ),
				array( 'question' => 'How are custom block fields secured?', 'answer' => '<p>New blocks are expected to define bounded settings, sanitize data on the server, escape output for its context, check WordPress capabilities and nonces, and ship with tests.</p>' ),
			),
			'maxWidth'        => 980,
			'backgroundColor' => '#F7F4ED',
			'titleColor'      => '#111827',
			'questionColor'   => '#111827',
			'answerColor'     => '#526171',
			'dividerColor'    => '#DDE3E7',
			'padding'         => 110,
			'paddingX'        => 24,
			'marginY'         => 0,
		),
	),
	$make_block(
		'dsflow-landing-footer',
		'landing-marketing-footer',
		array(
			'eyebrow'        => 'YOUR NEXT PAGE CAN FEEL DIFFERENT',
			'title'          => 'Give WordPress room to flow.',
			'description'    => 'Create the ambitious page your idea deserves, then hand it to your team with the confidence that the system will hold.',
			'primaryText'    => 'Get DesignStudio Flow',
			'primaryUrl'     => '#why-dsflow',
			'secondaryText'  => 'Read the workflow',
			'secondaryUrl'   => '#workflow',
			'homeUrl'        => '#why-dsflow',
			'docsUrl'        => '#workflow',
			'brandStatement' => 'A modern visual page builder for WordPress teams who care about freedom, consistency, and the quality of what gets published.',
			'copyright'       => 'DesignStudio Flow. Built for WordPress.',
			'tagline'         => 'Build freely. Stay beautifully consistent.',
			'backgroundColor' => '#101B26',
			'textColor'       => '#FFFFFF',
			'accentColor'     => '#0091FF',
			'buttonBgColor'   => '#FEFEFE',
			'buttonLabelColor' => '#111827',
			'linksColor'      => '#9FB0BD',
		)
	),
);

$settings = array(
	'theme'  => array(
		'primaryColor'    => '#0091FF',
		'secondaryColor'  => '#FF7100',
		'textColor'       => '#111827',
		'backgroundColor' => '#F7F4ED',
		'headingFont'     => "'Manrope', sans-serif",
		'bodyFont'        => "'Source Sans 3', sans-serif",
	),
	'layout' => array(
		'containerWidth'   => 1800,
		'contentPadding'   => 0,
		'showHeader'       => false,
		'showFooter'       => false,
		'headerTemplateId' => 0,
		'footerTemplateId' => 0,
		'template'         => 'fullwidth',
	),
	'popup'  => array( 'enabled' => false ),
);

$snapshot = '<main class="dsf-snapshot-landing">'
	. '<header><a href="#why-dsflow">DesignStudio Flow</a><nav><a href="#blocks">Blocks</a> <a href="#woocommerce">WooCommerce</a> <a href="#engagement">Forms and Growth</a> <a href="#security">Security</a> <a href="#audience">For Agencies</a></nav></header>'
	. '<section id="why-dsflow"><p>The visual WordPress system</p><h1>Design your WordPress site.</h1><p>Design pages, theme styles, WooCommerce stores, layouts, campaigns, and forms in one visual builder.</p><ul><li><a href="#ready">Design included</a></li><li><a href="#editor">Visual editor</a></li><li><a href="#woocommerce">WooCommerce</a></li><li><a href="#engagement">Forms and growth</a></li><li><a href="#security">Security</a></li><li><a href="#audience">For agencies</a></li></ul></section>'
	. '<section id="blocks"><h2>Start with structure. Finish with something original.</h2><p>Build with purpose-built heroes, content, commerce, campaign, header, and footer blocks.</p></section>'
	. '<section id="ready"><h2>Add a block. The design is already done.</h2><p>Every block ships fully designed and responsive. Swap in your own words and images, and publish.</p></section>'
	. '<section id="editor"><h2>A visual workflow that still respects the system.</h2><p>Edit responsive pages directly while keeping design guardrails clear.</p></section>'
	. '<section id="theme"><h2>Set the visual language once. Let every block speak it.</h2></section>'
	. '<section id="woocommerce"><h2>Turn the catalog into a guided buying experience.</h2></section>'
	. '<section id="layouts"><h2>Headers and footers belong to the same design conversation.</h2></section>'
	. '<section id="campaigns"><h2>Launch the moment, not just the page.</h2></section>'
	. '<section id="engagement"><h2>Build the page. Then help it do something.</h2><p>Forms, popups, and notification bars bring the next action into the same visual system.</p></section>'
	. '<section id="seo"><h2>A visual page should still be a real WordPress page.</h2></section>'
	. '<section id="security"><h2>Creative controls. Deliberate trust boundaries.</h2></section>'
	. '<section id="audience"><h2>Useful to the people who shape, sell, and maintain the site.</h2></section>'
	. '<section id="workflow"><h2>A straightforward path through ambitious work.</h2></section>'
	. '<footer id="get-dsflow"><h2>Give WordPress room to flow.</h2></footer>'
	. '</main>';

update_post_meta( $post_id, '_dsf_enabled', true );
update_post_meta( $post_id, '_dsf_blocks', $blocks );
update_post_meta( $post_id, '_dsf_settings', $settings );
update_post_meta( $post_id, '_dsf_html_snapshot', wp_kses_post( $snapshot ) );
update_post_meta( $post_id, '_dsf_product_landing', '1' );

clean_post_cache( $post_id );

fwrite( STDOUT, wp_json_encode( array( 'post_id' => $post_id, 'url' => get_permalink( $post_id ), 'blocks' => count( $blocks ) ), JSON_PRETTY_PRINT ) . PHP_EOL );
