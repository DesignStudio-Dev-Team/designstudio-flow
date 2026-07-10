<?php
/**
 * Block registration and management for DesignStudio Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Blocks {

	private static $instance = null;
	private $blocks          = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->register_default_blocks();
	}

	/**
	 * Register all default blocks
	 */
	private function register_default_blocks() {
		// CONTENT Category
		$this->register_block(
			array(
				'id'          => 'content',
				'name'        => 'Content',
				'category'    => 'content',
				'icon'        => 'file-text',
				'description' => 'Simple WYSIWYG content block with width control',
				'settings'    => array(
					'content'  => array(
						'type'         => 'wysiwyg',
						'label'        => 'Content',
						'default'      => '<p>Add your content here.</p>',
						'allowRawHtml' => true,
					),
					'maxWidth' => array(
						'type'    => 'slider',
						'label'   => 'Content Width',
						'default' => 900,
						'min'     => 320,
						'max'     => 1400,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'faq',
				'name'        => 'FAQ',
				'category'    => 'content',
				'icon'        => 'list-checks',
				'description' => 'Frequently asked questions accordion',
				'settings'    => array(
					'title'           => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Frequently asked questions',
					),
					'items'           => array(
						'type'    => 'faq_items',
						'label'   => 'Questions & Answers',
						'default' => array(
							array(
								'question' => 'What is DesignStudio Flow?',
								'answer'   => '<p>DesignStudio Flow is a block-based page builder for creating polished WordPress pages with controlled, reusable layouts.</p>',
							),
							array(
								'question' => 'Can I add more questions?',
								'answer'   => '<p>Yes. Add, remove, edit, and reorder FAQ items from the block settings panel.</p>',
							),
							array(
								'question' => 'Does this use my theme typography?',
								'answer'   => '<p>Yes. The FAQ block uses the same heading and body font tokens as the rest of your Flow blocks.</p>',
							),
						),
					),
					'maxWidth'        => array(
						'type'    => 'slider',
						'label'   => 'Content Width',
						'default' => 900,
						'min'     => 600,
						'max'     => 1200,
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'titleColor'      => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#111827',
					),
					'questionColor'   => array(
						'type'    => 'color',
						'label'   => 'Question Color',
						'default' => '#111827',
					),
					'answerColor'     => array(
						'type'    => 'color',
						'label'   => 'Answer Color',
						'default' => '#4B5563',
					),
					'dividerColor'    => array(
						'type'    => 'color',
						'label'   => 'Divider Color',
						'default' => '#E5E7EB',
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 80,
						'min'     => 20,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'hero',
				'name'        => 'Hero',
				'category'    => 'content',
				'icon'        => 'layout-template',
				'description' => 'Full-width hero with centered text and CTA button',
				'settings'    => array(
					'title'                  => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Welcome to Our Store',
					),
					'subtitle'               => array(
						'type'    => 'textarea',
						'label'   => 'Subtitle',
						'default' => 'Discover amazing products',
					),
					'layoutStyle'            => array(
						'type'    => 'select',
						'label'   => 'Layout Style',
						'default' => 'centered',
						'options' => array(
							'Classic'    => 'centered',
							'Two Column' => 'bottom-split',
						),
					),
					'showButton'             => array(
						'type'    => 'toggle',
						'label'   => 'Show Button',
						'default' => true,
					),
					'buttonText'             => array(
						'type'     => 'text',
						'label'    => 'Button Text',
						'default'  => 'Shop Now',
						'showWhen' => array( 'showButton' => true ),
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '/shop',
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'link',
						),
					),
					'buttonAction'           => array(
						'type'     => 'select',
						'label'    => 'Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'showButton' => true ),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					'backgroundImage'        => array(
						'type'    => 'image',
						'label'   => 'Background Image',
						'default' => '',
					),
					'backgroundColor'        => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#3B82F6',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#FFFFFF',
					),
					'contentBackgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Text Background',
						'default' => 'rgba(0,0,0,0)',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '#FFFFFF',
					),
					'buttonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#2563EB',
					),
					'contentPosition'        => array(
						'type'    => 'select',
						'label'   => 'Content Position',
						'default' => 'center-center',
						'options' => array(
							'Top Left'      => 'top-left',
							'Top Center'    => 'top-center',
							'Top Right'     => 'top-right',
							'Center Left'   => 'center-left',
							'Center Center' => 'center-center',
							'Center Right'  => 'center-right',
							'Bottom Left'   => 'bottom-left',
							'Bottom Center' => 'bottom-center',
							'Bottom Right'  => 'bottom-right',
						),
					),
					'contentEdgePadding'     => array(
						'type'    => 'slider',
						'label'   => 'Content Edge Padding',
						'default' => 15,
						'min'     => 15,
						'max'     => 60,
					),
					'gradientType'           => array(
						'type'    => 'select',
						'label'   => 'Gradient Type',
						'default' => 'none',
						'options' => array(
							'None'             => 'none',
							'Bottom Dark Fade' => 'bottom-dark',
						),
					),
					'gradientHeight'         => array(
						'type'     => 'slider',
						'label'    => 'Gradient Height',
						'default'  => 75,
						'min'      => 25,
						'max'      => 100,
						'unit'     => '%',
						'showWhen' => array( 'gradientType' => 'bottom-dark' ),
					),
					'bottomOffset'           => array(
						'type'     => 'slider',
						'label'    => 'Bottom Offset',
						'default'  => 15,
						'min'      => 0,
						'max'      => 120,
						'showWhen' => array( 'layoutStyle' => 'bottom-split' ),
					),
					'titleSubtitleGap'       => array(
						'type'    => 'slider',
						'label'   => 'Title / Subtitle Gap',
						'default' => 12,
						'min'     => 0,
						'max'     => 80,
					),
					'textButtonGap'          => array(
						'type'     => 'slider',
						'label'    => 'Text / Button Gap',
						'default'  => 15,
						'min'      => 0,
						'max'      => 140,
						'showWhen' => array( 'layoutStyle' => 'bottom-split' ),
					),
					'textColumnWidth'        => array(
						'type'     => 'slider',
						'label'    => 'Text Column Width',
						'default'  => 720,
						'min'      => 220,
						'max'      => 1200,
						'showWhen' => array( 'layoutStyle' => 'bottom-split' ),
					),
					'padding'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 80,
						'min'     => 40,
						'max'     => 200,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'countdown',
				'name'        => 'Countdown',
				'category'    => 'marketing',
				'icon'        => 'clock',
				'description' => 'Split countdown campaign block with CTA and media',
				'settings'    => array(
					'eyebrow'         => array(
						'type'    => 'text',
						'label'   => 'Eyebrow',
						'default' => 'Default eyebrow text',
					),
					'title'           => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Default title here',
					),
					'description'     => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'default' => 'Default description text here.',
					),
					'buttonText'      => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Default button text',
					),
					'buttonAction'    => array(
						'type'    => 'select',
						'label'   => 'Button Action',
						'default' => 'link',
						'options' => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
					),
					'buttonUrl'       => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '#',
						'showWhen' => array( 'buttonAction' => 'link' ),
					),
					'buttonModalLayout' => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContent' => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml' => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode' => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					'targetDate'      => array(
						'type'    => 'datetime',
						'label'   => 'Countdown Target Date',
						'default' => current_datetime()->modify( '+30 days' )->format( 'Y-m-d\TH:i' ),
						'step'    => 60,
						'helper'  => 'Choose the date and local time when the countdown ends.',
					),
					'expiredMessage'  => array(
						'type'    => 'text',
						'label'   => 'Expired Message',
						'default' => 'Default expired message.',
					),
					'noticeText'      => array(
						'type'    => 'text',
						'label'   => 'Notice Text',
						'default' => 'Default notice text',
					),
					'mediaType'       => array(
						'type'    => 'select',
						'label'   => 'Media Type',
						'default' => 'image',
						'options' => array(
							'Image' => 'image',
							'Video' => 'video',
						),
					),
					'image'           => array(
						'type'    => 'image',
						'label'   => 'Image',
						'default' => '',
					),
					'video'           => array(
						'type'     => 'text',
						'label'    => 'Video URL (MP4/WebM or YouTube/Vimeo)',
						'default'  => '',
						'showWhen' => array( 'mediaType' => 'video' ),
					),
					'mediaPosition'   => array(
						'type'    => 'select',
						'label'   => 'Media Position',
						'default' => 'right',
						'options' => array(
							'Media Right' => 'right',
							'Media Left'  => 'left',
						),
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'textColor'       => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#111827',
					),
					'accentColor'     => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '#B42318',
					),
					'eyebrowColor'    => array(
						'type'    => 'color',
						'label'   => 'Eyebrow Color',
						'default' => '',
					),
					'buttonColor'     => array(
						'type'    => 'color',
						'label'   => 'Button Color',
						'default' => '#111111',
					),
					'buttonTextColor' => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'noticeColor'     => array(
						'type'    => 'color',
						'label'   => 'Notice Background',
						'default' => '#F8D7DA',
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 64,
						'min'     => 20,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 40,
						'min'     => 0,
						'max'     => 140,
					),
					'gap'             => array(
						'type'    => 'slider',
						'label'   => 'Column Gap',
						'default' => 56,
						'min'     => 16,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'pricing',
				'name'        => 'Pricing',
				'category'    => 'marketing',
				'icon'        => 'badge-dollar-sign',
				'description' => 'Pricing plans with monthly and annual billing options',
				'settings'    => array(
					'eyebrow'         => array(
						'type'    => 'text',
						'label'   => 'Eyebrow',
						'default' => 'Pricing',
					),
					'title'           => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Pricing that grows with you',
					),
					'description'     => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'default' => 'Choose the plan that fits your needs. Update this text with details about your pricing options.',
					),
					'showBillingToggle' => array(
						'type'    => 'toggle',
						'label'   => 'Show Monthly / Annual Toggle',
						'default' => true,
					),
					'monthlyLabel'    => array(
						'type'     => 'text',
						'label'    => 'Monthly Label',
						'default'  => 'Monthly',
						'showWhen' => array( 'showBillingToggle' => true ),
					),
					'annualLabel'     => array(
						'type'     => 'text',
						'label'    => 'Annual Label',
						'default'  => 'Annually',
						'showWhen' => array( 'showBillingToggle' => true ),
					),
					'plans'           => array(
						'type'    => 'pricing_plans',
						'label'   => 'Pricing Plans',
						'default' => array(
							array(
								'name'         => 'Basic Plan',
								'description'  => 'A simple plan for getting started.',
								'monthlyPrice' => '19',
								'annualPrice'  => '15',
								'pricePrefix'  => '$',
								'priceSuffix'  => '/month',
								'buttonText'   => 'Choose plan',
								'buttonUrl'    => '#',
								'popular'      => false,
								'badgeText'    => 'Most popular',
								'features'     => "Feature one\nFeature two\nFeature three",
							),
							array(
								'name'         => 'Standard Plan',
								'description'  => 'A flexible plan for growing teams.',
								'monthlyPrice' => '29',
								'annualPrice'  => '24',
								'pricePrefix'  => '$',
								'priceSuffix'  => '/month',
								'buttonText'   => 'Choose plan',
								'buttonUrl'    => '#',
								'popular'      => true,
								'badgeText'    => 'Most popular',
								'features'     => "Everything in Basic\nAdvanced feature\nPriority support\nAdditional feature",
							),
							array(
								'name'         => 'Premium Plan',
								'description'  => 'A complete plan for established businesses.',
								'monthlyPrice' => '59',
								'annualPrice'  => '49',
								'pricePrefix'  => '$',
								'priceSuffix'  => '/month',
								'buttonText'   => 'Choose plan',
								'buttonUrl'    => '#',
								'popular'      => false,
								'badgeText'    => 'Most popular',
								'features'     => "Everything in Standard\nUnlimited feature\nDedicated support\nCustom reporting",
							),
						),
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'textColor'       => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#111827',
					),
					'mutedColor'      => array(
						'type'    => 'color',
						'label'   => 'Muted Text Color',
						'default' => '#4B5563',
					),
					'accentColor'     => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '#4F36F5',
					),
					'eyebrowColor'    => array(
						'type'    => 'color',
						'label'   => 'Eyebrow Color',
						'default' => '',
					),
					'cardColor'       => array(
						'type'    => 'color',
						'label'   => 'Card Background',
						'default' => '#FFFFFF',
					),
					'buttonColor'     => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '',
					),
					'buttonTextColor' => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'columns'         => array(
						'type'    => 'select',
						'label'   => 'Columns',
						'default' => '3',
						'options' => array( '2', '3', '4' ),
					),
					'maxWidth'        => array(
						'type'    => 'slider',
						'label'   => 'Content Width',
						'default' => 1200,
						'min'     => 760,
						'max'     => 1600,
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 80,
						'min'     => 20,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'expander-hero',
				'name'        => 'Expander Hero',
				'category'    => 'content',
				'icon'        => 'layout-columns',
				'description' => 'Expandable image-card hero with optional center CTA bar',
				'settings'    => array(
					'layoutStyle'     => array(
						'type'    => 'select',
						'label'   => 'Layout Style',
						'default' => 'split-bar',
						'options' => array(
							'Split With Bar' => 'split-bar',
							'Expanding Row'  => 'row',
						),
					),
					'cards'           => array(
						'type'    => 'expander_cards',
						'label'   => 'Cards',
						'default' => array(
							array( 'title' => 'Card 1', 'image' => '', 'url' => '#' ),
							array( 'title' => 'Card 2', 'image' => '', 'url' => '#' ),
							array( 'title' => 'Card 3', 'image' => '', 'url' => '#' ),
							array( 'title' => 'Card 4', 'image' => '', 'url' => '#' ),
							array( 'title' => 'Card 5', 'image' => '', 'url' => '#' ),
							array( 'title' => 'Card 6', 'image' => '', 'url' => '#' ),
						),
					),
					'barPosition'     => array(
						'type'     => 'select',
						'label'    => 'Bar Position',
						'default'  => 'middle',
						'options'  => array(
							'Top'    => 'top',
							'Middle' => 'middle',
							'Bottom' => 'bottom',
						),
						'showWhen' => array( 'layoutStyle' => 'split-bar' ),
					),
					'barTitle'        => array(
						'type'     => 'text',
						'label'    => 'Bar Title',
						'default'  => 'Test Title 1',
						'showWhen' => array( 'layoutStyle' => 'split-bar' ),
					),
					'showButton'      => array(
						'type'     => 'toggle',
						'label'    => 'Show CTA',
						'default'  => true,
						'showWhen' => array( 'layoutStyle' => 'split-bar' ),
					),
					'buttonText'      => array(
						'type'     => 'text',
						'label'    => 'Button Text',
						'default'  => 'test',
						'showWhen' => array(
							'layoutStyle' => 'split-bar',
							'showButton'  => true,
						),
					),
					'buttonUrl'       => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '#',
						'showWhen' => array(
							'layoutStyle' => 'split-bar',
							'showButton'  => true,
						),
					),
					'barColor'        => array(
						'type'    => 'color',
						'label'   => 'Bar Color',
						'default' => '#76A64B',
					),
					'barTextColor'    => array(
						'type'    => 'color',
						'label'   => 'Bar Text Color',
						'default' => '#FFFFFF',
					),
					'buttonColor'     => array(
						'type'    => 'color',
						'label'   => 'Button Color',
						'default' => '#17212B',
					),
					'buttonTextColor' => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'cardTextColor'   => array(
						'type'    => 'color',
						'label'   => 'Card Text Color',
						'default' => '#FFFFFF',
					),
					'cardHeight'      => array(
						'type'    => 'slider',
						'label'   => 'Card Height',
						'default' => 280,
						'min'     => 160,
						'max'     => 520,
					),
					'barHeight'       => array(
						'type'     => 'slider',
						'label'    => 'Bar Height',
						'default'  => 110,
						'min'      => 70,
						'max'      => 220,
						'showWhen' => array( 'layoutStyle' => 'split-bar' ),
					),
					'gap'             => array(
						'type'    => 'slider',
						'label'   => 'Gap',
						'default' => 16,
						'min'     => 0,
						'max'     => 48,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 80,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'features-grid',
				'name'        => 'Features Grid',
				'category'    => 'content',
				'icon'        => 'grid-3x3',
				'description' => 'Display key features in a grid layout',
				'settings'    => array(
					'title'                => array(
						'type'    => 'text',
						'label'   => 'Section Title',
						'default' => 'Our Features',
					),
					'subtitle'             => array(
						'type'    => 'text',
						'label'   => 'Section Subtitle',
						'default' => 'Everything you need',
					),
					'columns'              => array(
						'type'    => 'select',
						'label'   => 'Columns',
						'default' => '3',
						'options' => array( '2', '3', '4' ),
					),
					'features'             => array(
						'type'    => 'repeater',
						'label'   => 'Features',
						'default' => array(
							array(
								'title'                  => 'Easy to Use',
								'description'            => 'Intuitive drag-and-drop interface',
								'image'                  => '',
								'imagePosition'          => 'above',
								'buttonText'             => 'Learn More',
								'buttonUrl'              => '#',
								'buttonAction'           => 'link',
								'buttonModalLayout'      => 'center',
								'buttonModalContentType' => 'wysiwyg',
								'buttonModalContent'     => '',
								'buttonModalHtml'        => '',
								'buttonModalShortcode'   => '',
							),
							array(
								'title'                  => 'Customizable',
								'description'            => 'Full control over styling',
								'image'                  => '',
								'imagePosition'          => 'above',
								'buttonText'             => 'Learn More',
								'buttonUrl'              => '#',
								'buttonAction'           => 'link',
								'buttonModalLayout'      => 'center',
								'buttonModalContentType' => 'wysiwyg',
								'buttonModalContent'     => '',
								'buttonModalHtml'        => '',
								'buttonModalShortcode'   => '',
							),
							array(
								'title'                  => 'Responsive',
								'description'            => 'Works on all devices',
								'image'                  => '',
								'imagePosition'          => 'above',
								'buttonText'             => 'Learn More',
								'buttonUrl'              => '#',
								'buttonAction'           => 'link',
								'buttonModalLayout'      => 'center',
								'buttonModalContentType' => 'wysiwyg',
								'buttonModalContent'     => '',
								'buttonModalHtml'        => '',
								'buttonModalShortcode'   => '',
							),
						),
					),
					'bottomContent'        => array(
						'type'         => 'wysiwyg',
						'label'        => 'Bottom Content',
						'default'      => '',
						'allowRawHtml' => true,
					),
					'bottomButtonText'     => array(
						'type'    => 'text',
						'label'   => 'Bottom Button Text',
						'default' => '',
					),
					'bottomButtonUrl'      => array(
						'type'             => 'text',
						'label'            => 'Bottom Button URL',
						'default'          => '#',
						'showWhen'         => array( 'bottomButtonAction' => 'link' ),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonAction'   => array(
						'type'             => 'select',
						'label'            => 'Bottom Button Action',
						'default'          => 'link',
						'options'          => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonModalLayout' => array(
						'type'             => 'select',
						'label'            => 'Bottom Modal Layout',
						'default'          => 'center',
						'options'          => array(
							'Center Popup'  => 'center',
							'Right Drawer'  => 'drawer-right',
						),
						'showWhen'         => array( 'bottomButtonAction' => 'modal' ),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonModalContentType' => array(
						'type'             => 'select',
						'label'            => 'Bottom Modal Content Type',
						'default'          => 'wysiwyg',
						'options'          => array(
							'Visual Editor' => 'wysiwyg',
							'Raw HTML'      => 'html',
							'Shortcode'     => 'shortcode',
						),
						'showWhen'         => array( 'bottomButtonAction' => 'modal' ),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonModalContent' => array(
						'type'             => 'wysiwyg',
						'label'            => 'Bottom Modal Content',
						'default'          => '',
						'allowRawHtml'     => true,
						'showWhen'         => array(
							'bottomButtonAction'           => 'modal',
							'bottomButtonModalContentType' => 'wysiwyg',
						),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonModalHtml' => array(
						'type'             => 'textarea',
						'label'            => 'Bottom Modal HTML',
						'default'          => '',
						'showWhen'         => array(
							'bottomButtonAction'           => 'modal',
							'bottomButtonModalContentType' => 'html',
						),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'bottomButtonModalShortcode' => array(
						'type'             => 'text',
						'label'            => 'Bottom Modal Shortcode',
						'default'          => '',
						'showWhen'         => array(
							'bottomButtonAction'           => 'modal',
							'bottomButtonModalContentType' => 'shortcode',
						),
						'showWhenNotEmpty' => array( 'bottomButtonText' ),
					),
					'backgroundColor'      => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'titleColor'           => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'subtitleColor'        => array(
						'type'    => 'color',
						'label'   => 'Subtitle Color',
						'default' => '#6B7280',
					),
					'cardColor'            => array(
						'type'    => 'color',
						'label'   => 'Card Background',
						'default' => '#1F2937',
					),
					'cardTitleColor'       => array(
						'type'    => 'color',
						'label'   => 'Card Title Color',
						'default' => '#60A5FA',
					),
					'cardDescriptionColor' => array(
						'type'    => 'color',
						'label'   => 'Card Description Color',
						'default' => '#9CA3AF',
					),
					'buttonColor'          => array(
						'type'    => 'color',
						'label'   => 'Button Color',
						'default' => '#000000',
					),
					'buttonTextColor'      => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'padding'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 20,
						'max'     => 120,
					),
					'paddingX'             => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'card-columns',
				'name'        => 'Card Columns',
				'category'    => 'content',
				'icon'        => 'columns',
				'description' => 'Intro heading with columns of cards: icon or text headers, bottom-aligned images, and optional arrow buttons',
				'settings'    => array(
					// Header
					'headerLayout'         => array(
						'type'    => 'select',
						'label'   => 'Header Layout',
						'section' => 'header',
						'default' => 'centered',
						'options' => array(
							'Centered'                => 'centered',
							'Two Column (Title/Text)' => 'split',
						),
					),
					'title'                => array(
						'type'    => 'text',
						'label'   => 'Title',
						'section' => 'header',
						'default' => 'Why Choose Us?',
					),
					'description'          => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'section' => 'header',
						'default' => 'Add a short introduction that explains the value of what you offer.',
					),
					// Cards
					'cards'                => array(
						'type'    => 'card_column_items',
						'label'   => 'Cards',
						'section' => 'cards',
						'default' => array(
							array(
								'icon'              => 'sparkles',
								'iconType'          => 'preset',
								'customIcon'        => '',
								'title'             => 'First Benefit',
								'description'       => '',
								'image'             => '',
								'backgroundType'    => 'solid',
								'backgroundColor'   => '#F3F4F6',
								'gradientStart'     => '#F3F4F6',
								'gradientEnd'       => '#E5E7EB',
								'gradientDirection' => 'top-bottom',
								'showButton'        => false,
								'buttonText'        => '',
								'buttonUrl'         => '',
							),
							array(
								'icon'              => 'heart',
								'iconType'          => 'preset',
								'customIcon'        => '',
								'title'             => 'Second Benefit',
								'description'       => '',
								'image'             => '',
								'backgroundType'    => 'solid',
								'backgroundColor'   => '#F3F4F6',
								'gradientStart'     => '#F3F4F6',
								'gradientEnd'       => '#E5E7EB',
								'gradientDirection' => 'top-bottom',
								'showButton'        => false,
								'buttonText'        => '',
								'buttonUrl'         => '',
							),
							array(
								'icon'              => 'users',
								'iconType'          => 'preset',
								'customIcon'        => '',
								'title'             => 'Third Benefit',
								'description'       => '',
								'image'             => '',
								'backgroundType'    => 'solid',
								'backgroundColor'   => '#F3F4F6',
								'gradientStart'     => '#F3F4F6',
								'gradientEnd'       => '#E5E7EB',
								'gradientDirection' => 'top-bottom',
								'showButton'        => false,
								'buttonText'        => '',
								'buttonUrl'         => '',
							),
						),
					),
					// Layout
					'columns'              => array(
						'type'    => 'select',
						'label'   => 'Columns',
						'section' => 'layout',
						'default' => '3',
						'options' => array( '2', '3', '4', '5', '6' ),
					),
					'cardLayout'           => array(
						'type'    => 'select',
						'label'   => 'Card Layout',
						'section' => 'layout',
						'default' => 'standard',
						'options' => array(
							'Standard (Bottom Image)' => 'standard',
							'Image Background (Title Overlay)' => 'overlay',
						),
					),
					'contentAlign'         => array(
						'type'    => 'select',
						'label'   => 'Card Content Alignment',
						'section' => 'layout',
						'default' => 'center',
						'options' => array(
							'Center' => 'center',
							'Left'   => 'left',
						),
					),
					'buttonStyle'          => array(
						'type'    => 'select',
						'label'   => 'Card Button Style',
						'section' => 'layout',
						'default' => 'arrow',
						'options' => array(
							'Arrow Circle' => 'arrow',
							'Text'         => 'text',
							'Text + Arrow' => 'text-arrow',
						),
					),
					'imageFit'             => array(
						'type'     => 'select',
						'label'    => 'Card Image Fit',
						'section'  => 'layout',
						'default'  => 'cover',
						'options'  => array(
							'Cover (fill area)'    => 'cover',
							'Contain (fit inside)' => 'contain',
						),
						'showWhen' => array( 'cardLayout' => 'standard' ),
					),
					// Section background
					'backgroundType'       => array(
						'type'    => 'select',
						'label'   => 'Background Type',
						'default' => 'solid',
						'options' => array(
							'Solid Color' => 'solid',
							'Gradient'    => 'gradient',
						),
					),
					'backgroundColor'      => array(
						'type'     => 'color',
						'label'    => 'Background Color',
						'default'  => '#FFFFFF',
						'showWhen' => array( 'backgroundType' => 'solid' ),
					),
					'gradientStart'        => array(
						'type'     => 'color',
						'label'    => 'Gradient Start',
						'default'  => '#FFFFFF',
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					'gradientEnd'          => array(
						'type'     => 'color',
						'label'    => 'Gradient End',
						'default'  => '#EFF6FF',
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					'gradientDirection'    => array(
						'type'     => 'select',
						'label'    => 'Gradient Direction',
						'default'  => 'top-bottom',
						'options'  => array(
							'Left to Right'       => 'left-right',
							'Top to Bottom'       => 'top-bottom',
							'Radial (Center Out)' => 'radial',
						),
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					// Colors
					'titleColor'           => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#111827',
					),
					'descriptionColor'     => array(
						'type'    => 'color',
						'label'   => 'Description Color',
						'default' => '#4B5563',
					),
					'cardTitleColor'       => array(
						'type'    => 'color',
						'label'   => 'Card Title Color',
						'default' => '#111827',
					),
					'cardDescriptionColor' => array(
						'type'    => 'color',
						'label'   => 'Card Description Color',
						'default' => '#4B5563',
					),
					'cardIconColor'        => array(
						'type'    => 'color',
						'label'   => 'Card Icon Color',
						'default' => '#111827',
					),
					'buttonColor'          => array(
						'type'    => 'color',
						'label'   => 'Card Button Color',
						'default' => '#111827',
					),
					'buttonTextColor'      => array(
						'type'    => 'color',
						'label'   => 'Card Button Text Color',
						'default' => '#FFFFFF',
					),
					// Image-background overlay
					'overlayTextColor'     => array(
						'type'     => 'color',
						'label'    => 'Overlay Text Color',
						'default'  => '#FFFFFF',
						'showWhen' => array( 'cardLayout' => 'overlay' ),
					),
					'overlayStrength'      => array(
						'type'     => 'slider',
						'label'    => 'Overlay Gradient Strength',
						'default'  => 60,
						'min'      => 0,
						'max'      => 100,
						'showWhen' => array( 'cardLayout' => 'overlay' ),
					),
					'overlayHeight'        => array(
						'type'     => 'slider',
						'label'    => 'Overlay Gradient Height (%)',
						'default'  => 50,
						'min'      => 20,
						'max'      => 100,
						'showWhen' => array( 'cardLayout' => 'overlay' ),
					),
					// Card dimensions
					'cardMinHeight'        => array(
						'type'    => 'slider',
						'label'   => 'Card Min Height',
						'default' => 380,
						'min'     => 200,
						'max'     => 720,
					),
					'cardPadding'          => array(
						'type'    => 'slider',
						'label'   => 'Card Padding',
						'default' => 24,
						'min'     => 8,
						'max'     => 48,
					),
					'cardRadius'           => array(
						'type'    => 'slider',
						'label'   => 'Card Corner Radius',
						'default' => 16,
						'min'     => 0,
						'max'     => 40,
					),
					'imageHeight'          => array(
						'type'     => 'slider',
						'label'    => 'Card Image Height',
						'default'  => 220,
						'min'      => 80,
						'max'      => 420,
						'showWhen' => array( 'cardLayout' => 'standard' ),
					),
					// Responsive spacing (padding/gap defined so those sliders surface)
					'padding'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'             => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 120,
					),
					'gap'                  => array(
						'type'    => 'slider',
						'label'   => 'Card Gap',
						'default' => 24,
						'min'     => 0,
						'max'     => 64,
					),
					'marginY'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'bento-hero',
				'name'        => 'Bento Hero',
				'category'    => 'content',
				'icon'        => 'layout',
				'description' => 'Mosaic grid with hero section and feature boxes',
				'settings'    => array(
					// Hero Section
					'heroMediaType'             => array(
						'type'    => 'select',
						'label'   => 'Hero Media Type',
						'default' => 'image',
						'options' => array(
							'Image' => 'image',
							'Video' => 'video',
						),
					),
					'heroImage'                  => array(
						'type'    => 'image',
						'label'   => 'Hero Image',
						'default' => '',
					),
					'heroVideo'                  => array(
						'type'     => 'text',
						'label'    => 'Hero Video URL (MP4/WebM or YouTube/Vimeo)',
						'default'  => '',
						'showWhen' => array( 'heroMediaType' => 'video' ),
					),
					'heroTitle'                  => array(
						'type'    => 'text',
						'label'   => 'Hero Title',
						'default' => 'Hero Title',
					),
					'heroType'                   => array(
						'type'    => 'select',
						'label'   => 'Hero Action Type',
						'default' => 'search',
						'options' => array(
							'Search Box' => 'search',
							'Button'     => 'button',
						),
					),
					// Search Settings
					'searchPlaceholder'          => array(
						'type'     => 'text',
						'label'    => 'Search Placeholder',
						'default'  => 'Search by keyword',
						'showWhen' => array( 'heroType' => 'search' ),
					),
					'searchUrl'                  => array(
						'type'     => 'text',
						'label'    => 'Search URL',
						'default'  => '/?s={query}',
						'showWhen' => array( 'heroType' => 'search' ),
					),
					// Button Settings
					'heroButtonText'             => array(
						'type'     => 'text',
						'label'    => 'Button Text',
						'default'  => 'Shop Now',
						'showWhen' => array( 'heroType' => 'button' ),
					),
					'heroButtonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '#',
						'showWhen' => array(
							'heroType'         => 'button',
							'heroButtonAction' => 'link',
						),
					),
					'heroButtonAction'           => array(
						'type'     => 'select',
						'label'    => 'Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'heroType' => 'button' ),
					),
					'heroButtonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'heroType'         => 'button',
							'heroButtonAction' => 'modal',
						),
					),
					'heroButtonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'heroType'         => 'button',
							'heroButtonAction' => 'modal',
						),
					),
					'heroButtonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'heroType'                   => 'button',
							'heroButtonAction'           => 'modal',
							'heroButtonModalContentType' => 'wysiwyg',
						),
					),
					'heroButtonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'heroType'                   => 'button',
							'heroButtonAction'           => 'modal',
							'heroButtonModalContentType' => 'html',
						),
					),
					'heroButtonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'heroType'                   => 'button',
							'heroButtonAction'           => 'modal',
							'heroButtonModalContentType' => 'shortcode',
						),
					),
					// Feature Boxes (5 boxes)
					'box1Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 1 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box1ImageAlt',
						),
					),
					'box1ImageAlt'               => array(
						'type'    => 'text',
						'label'   => 'Box 1 Image Alt',
						'default' => '',
					),
					'box1Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 1 Title',
						'default' => 'Box 1 Title',
					),
					'box1ShowTitle'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Box 1 Title',
						'default' => true,
					),
					'box1Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 1 URL',
						'default' => '#',
					),
					'boxCount'                   => array(
						'type'    => 'select',
						'label'   => 'Box Count',
						'default' => '6',
						'options' => array(
							'4' => '4',
							'6' => '6',
						),
					),
					'box2Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 2 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box2ImageAlt',
						),
					),
					'box2ImageAlt'               => array(
						'type'    => 'text',
						'label'   => 'Box 2 Image Alt',
						'default' => '',
					),
					'box2Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 2 Title',
						'default' => 'Box 2 Title',
					),
					'box2ShowTitle'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Box 2 Title',
						'default' => true,
					),
					'box2Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 2 URL',
						'default' => '#',
					),
					'box3Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 3 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box3ImageAlt',
						),
					),
					'box3ImageAlt'               => array(
						'type'    => 'text',
						'label'   => 'Box 3 Image Alt',
						'default' => '',
					),
					'box3Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 3 Title',
						'default' => 'Box 3 Title',
					),
					'box3ShowTitle'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Box 3 Title',
						'default' => true,
					),
					'box3Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 3 URL',
						'default' => '#',
					),
					'box4Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 4 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box4ImageAlt',
						),
					),
					'box4ImageAlt'               => array(
						'type'    => 'text',
						'label'   => 'Box 4 Image Alt',
						'default' => '',
					),
					'box4Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 4 Title',
						'default' => 'Box 4 Title',
					),
					'box4ShowTitle'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Box 4 Title',
						'default' => true,
					),
					'box4Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 4 URL',
						'default' => '#',
					),
					'box5Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 5 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box5ImageAlt',
						),
						'showWhen'        => array( 'boxCount' => '6' ),
					),
					'box5ImageAlt'               => array(
						'type'     => 'text',
						'label'    => 'Box 5 Image Alt',
						'default'  => '',
						'showWhen' => array( 'boxCount' => '6' ),
					),
					'box5Title'                  => array(
						'type'     => 'text',
						'label'    => 'Box 5 Title',
						'default'  => 'Box 5 Title',
						'showWhen' => array( 'boxCount' => '6' ),
					),
					'box5ShowTitle'              => array(
						'type'     => 'toggle',
						'label'    => 'Show Box 5 Title',
						'default'  => true,
						'showWhen' => array( 'boxCount' => '6' ),
					),
					'box5Url'                    => array(
						'type'     => 'text',
						'label'    => 'Box 5 URL',
						'default'  => '#',
						'showWhen' => array( 'boxCount' => '6' ),
					),
					// Section Bars
					'showTopBar'                 => array(
						'type'    => 'toggle',
						'label'   => 'Show Top Bar',
						'default' => false,
					),
					'topBarText'                 => array(
						'type'     => 'text',
						'label'    => 'Top Bar Text',
						'default'  => 'Shop by Category',
						'showWhen' => array( 'showTopBar' => true ),
					),
					'showBottomBar'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Bottom Bar',
						'default' => false,
					),
					'bottomBarText'              => array(
						'type'     => 'text',
						'label'    => 'Bottom Bar Text',
						'default'  => 'Shop by Brand',
						'showWhen' => array( 'showBottomBar' => true ),
					),
					// Last Box Type
					'ctaType'                    => array(
						'type'     => 'select',
						'label'    => 'Last Box Type',
						'default'  => 'cta',
						'options'  => array(
							'Shop All CTA' => 'cta',
							'Category Box' => 'category',
							'Extra Box'    => 'box',
						),
						'showWhen' => array( 'boxCount' => '6' ),
					),
					// CTA Box
					'ctaText'                    => array(
						'type'     => 'text',
						'label'    => 'CTA Text',
						'default'  => 'Shop All',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'cta',
						),
					),
					'ctaUrl'                     => array(
						'type'     => 'text',
						'label'    => 'CTA URL',
						'default'  => '/shop',
						'showWhen' => array(
							'boxCount'  => '6',
							'ctaType'   => 'cta',
							'ctaAction' => 'link',
						),
					),
					'ctaAction'                  => array(
						'type'     => 'select',
						'label'    => 'CTA Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'cta',
						),
					),
					'ctaModalLayout'             => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'boxCount'  => '6',
							'ctaType'   => 'cta',
							'ctaAction' => 'modal',
						),
					),
					'ctaModalContentType'        => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'boxCount'  => '6',
							'ctaType'   => 'cta',
							'ctaAction' => 'modal',
						),
					),
					'ctaModalContent'            => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'boxCount'            => '6',
							'ctaType'             => 'cta',
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'wysiwyg',
						),
					),
					'ctaModalHtml'               => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'boxCount'            => '6',
							'ctaType'             => 'cta',
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'html',
						),
					),
					'ctaModalShortcode'          => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'boxCount'            => '6',
							'ctaType'             => 'cta',
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'shortcode',
						),
					),
					'ctaColor'                   => array(
						'type'     => 'color',
						'label'    => 'CTA Background',
						'default'  => '#2C5F5D',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'cta',
						),
					),
					'ctaTextColor'               => array(
						'type'     => 'color',
						'label'    => 'CTA Text Color',
						'default'  => '#FFFFFF',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'cta',
						),
					),
					// Extra Box (Box 6)
					'box6CategoryId'             => array(
						'type'     => 'category',
						'label'    => 'Box 6 Category',
						'default'  => 0,
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'category',
						),
					),
					'box6Image'                  => array(
						'type'            => 'image',
						'label'           => 'Box 6 Image',
						'default'         => '',
						'mediaMetaFields' => array(
							'alt' => 'box6ImageAlt',
						),
						'showWhen'        => array(
							'boxCount' => '6',
							'ctaType'  => 'box',
						),
					),
					'box6ImageAlt'               => array(
						'type'     => 'text',
						'label'    => 'Box 6 Image Alt',
						'default'  => '',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'box',
						),
					),
					'box6Title'                  => array(
						'type'     => 'text',
						'label'    => 'Box 6 Title',
						'default'  => 'Box 6 Title',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'box',
						),
					),
					'box6ShowTitle'              => array(
						'type'     => 'toggle',
						'label'    => 'Show Last Box Title',
						'default'  => true,
						'showWhen' => array( 'boxCount' => '6' ),
					),
					'box6Url'                    => array(
						'type'     => 'text',
						'label'    => 'Box 6 URL',
						'default'  => '#',
						'showWhen' => array(
							'boxCount' => '6',
							'ctaType'  => 'box',
						),
					),
					// Style
					'boxBackground'              => array(
						'type'    => 'color',
						'label'   => 'Box Background',
						'default' => '#F5F5F4',
					),
					'boxImageSize'               => array(
						'type'    => 'slider',
						'label'   => 'Box Image Size',
						'default' => 100,
						'min'     => 30,
						'max'     => 100,
						'unit'    => '%',
					),
					'titleColor'                 => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'sectionBarBackground'       => array(
						'type'    => 'color',
						'label'   => 'Section Bar Background',
						'default' => '#1E467B',
					),
					'sectionBarTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Section Bar Text Color',
						'default' => '#FFFFFF',
					),
					'sectionBarHeight'           => array(
						'type'    => 'slider',
						'label'   => 'Section Bar Height',
						'default' => 64,
						'min'     => 40,
						'max'     => 120,
					),
					'gap'                        => array(
						'type'    => 'slider',
						'label'   => 'Gap',
						'default' => 12,
						'min'     => 4,
						'max'     => 24,
					),
					'paddingX'                   => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                    => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'spotlight-hero',
				'name'        => 'Spotlight Hero',
				'category'    => 'content',
				'icon'        => 'layout',
				'description' => 'Large media spotlight with a promo tile and three buttons',
				'settings'    => array(
					// Main Media
					'mediaType'      => array(
						'type'        => 'select',
						'label'       => 'Main Media Type',
						'default'     => 'image',
						'options'     => array(
							'Image' => 'image',
							'Video' => 'video',
						),
						'section'     => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainImage'      => array(
						'type'         => 'image',
						'label'        => 'Main Image',
						'default'      => '',
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainVideo'      => array(
						'type'         => 'text',
						'label'        => 'Main Video URL (MP4/WebM or YouTube/Vimeo)',
						'default'      => '',
						'showWhen'     => array( 'mediaType' => 'video' ),
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainTitle'      => array(
						'type'         => 'text',
						'label'        => 'Main Headline',
						'default'      => 'Your headline goes here.',
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainContentAlign' => array(
						'type'         => 'select',
						'label'        => 'Content Alignment',
						'default'      => 'left',
						'options'      => array(
							'Left'   => 'left',
							'Center' => 'center',
							'Right'  => 'right',
						),
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'showMainButton' => array(
						'type'         => 'toggle',
						'label'        => 'Show Button',
						'default'      => true,
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainButtonText' => array(
						'type'         => 'text',
						'label'        => 'Button Text',
						'default'      => 'Start Here',
						'showWhen'     => array( 'showMainButton' => true ),
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),
					'mainButtonUrl'  => array(
						'type'         => 'text',
						'label'        => 'Button URL',
						'default'      => '#',
						'showWhen'     => array( 'showMainButton' => true ),
						'section'      => 'main',
						'sectionTitle' => 'Main Media',
					),

					// Promo Tile
					'promoImage'     => array(
						'type'         => 'image',
						'label'        => 'Promo Image',
						'default'      => '',
						'section'      => 'promo',
						'sectionTitle' => 'Promo Tile',
					),
					'showPromoCaption' => array(
						'type'         => 'toggle',
						'label'        => 'Show Caption & Gradient',
						'default'      => true,
						'section'      => 'promo',
						'sectionTitle' => 'Promo Tile',
					),
					'promoTitle'     => array(
						'type'         => 'text',
						'label'        => 'Promo Caption',
						'default'      => '',
						'showWhen'     => array( 'showPromoCaption' => true ),
						'section'      => 'promo',
						'sectionTitle' => 'Promo Tile',
					),
					'promoUrl'       => array(
						'type'         => 'text',
						'label'        => 'Promo URL',
						'default'      => '#',
						'section'      => 'promo',
						'sectionTitle' => 'Promo Tile',
					),

					// Buttons
					'showButtons'    => array(
						'type'         => 'toggle',
						'label'        => 'Show Buttons',
						'default'      => true,
						'section'      => 'buttons',
						'sectionTitle' => 'Buttons',
					),
					'sideButtons'    => array(
						'type'         => 'repeater',
						'label'        => 'Buttons',
						'showWhen'     => array( 'showButtons' => true ),
						'section'      => 'buttons',
						'sectionTitle' => 'Buttons',
						'fields'       => array(
							'text'    => array(
								'type'    => 'text',
								'label'   => 'Button Text',
								'default' => 'Button',
							),
							'url'     => array(
								'type'    => 'text',
								'label'   => 'Button URL',
								'default' => '#',
							),
							'enabled' => array(
								'type'    => 'toggle',
								'label'   => 'Visible',
								'default' => true,
							),
						),
						'default'      => array(
							array(
								'text'    => 'Button One',
								'url'     => '#',
								'enabled' => true,
							),
							array(
								'text'    => 'Button Two',
								'url'     => '#',
								'enabled' => true,
							),
							array(
								'text'    => 'Button Three',
								'url'     => '#',
								'enabled' => true,
							),
						),
					),

					// Style
					'splitRatio'     => array(
						'type'         => 'slider',
						'label'        => 'Main Width',
						'default'      => 58,
						'min'          => 40,
						'max'          => 70,
						'unit'         => '%',
						'section'      => 'layout',
						'sectionTitle' => 'Layout',
					),
					'titleColor'     => array(
						'type'         => 'color',
						'label'        => 'Headline Color',
						'default'      => '#FFFFFF',
						'section'      => 'colors',
						'sectionTitle' => 'Colors',
					),
					'promoTextColor' => array(
						'type'         => 'color',
						'label'        => 'Promo Caption Color',
						'default'      => '#FFFFFF',
						'showWhen'     => array( 'showPromoCaption' => true ),
						'section'      => 'colors',
						'sectionTitle' => 'Colors',
					),
					'buttonColor'    => array(
						'type'         => 'color',
						'label'        => 'Button Color',
						'default'      => '#1CA0DC',
						'section'      => 'colors',
						'sectionTitle' => 'Colors',
					),
					'buttonTextColor' => array(
						'type'         => 'color',
						'label'        => 'Button Text Color',
						'default'      => '#FFFFFF',
						'section'      => 'colors',
						'sectionTitle' => 'Colors',
					),
					'height'         => array(
						'type'    => 'slider',
						'label'   => 'Height',
						'default' => 460,
						'min'     => 300,
						'max'     => 800,
					),
					'gap'            => array(
						'type'    => 'slider',
						'label'   => 'Gap',
						'default' => 16,
						'min'     => 0,
						'max'     => 48,
					),
					'paddingX'       => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'        => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'duo-hero',
				'name'        => 'Duo Hero',
				'category'    => 'content',
				'icon'        => 'columns',
				'description' => 'Split hero section with two interactive panels',
				'settings'    => array(
					// Layout
					'splitRatio'                  => array(
						'type'    => 'slider',
						'label'   => 'Split Ratio (Left %)',
						'default' => 50,
						'min'     => 30,
						'max'     => 70,
						'unit'    => '%',
					),
					'height'                      => array(
						'type'    => 'slider',
						'label'   => 'Height',
						'default' => 500,
						'min'     => 300,
						'max'     => 800,
					),
					'gap'                         => array(
						'type'    => 'slider',
						'label'   => 'Gap',
						'default' => 20,
						'min'     => 0,
						'max'     => 80,
					),

					// Left Side
					'leftImage'                   => array(
						'type'    => 'image',
						'label'   => 'Left Image',
						'default' => '',
					),
					'leftTitle'                   => array(
						'type'    => 'text',
						'label'   => 'Left Title',
						'default' => 'Hero Title 1',
					),
					'leftType'                    => array(
						'type'    => 'select',
						'label'   => 'Left Action Type',
						'default' => 'button',
						'options' => array(
							'Button'     => 'button',
							'Search Box' => 'search',
						),
					),
					'leftButtonText'              => array(
						'type'     => 'text',
						'label'    => 'Left Button Text',
						'default'  => 'Get Started',
						'showWhen' => array( 'leftType' => 'button' ),
					),
					'leftButtonUrl'               => array(
						'type'     => 'text',
						'label'    => 'Left Button URL',
						'default'  => '#',
						'showWhen' => array(
							'leftType'         => 'button',
							'leftButtonAction' => 'link',
						),
					),
					'leftButtonAction'            => array(
						'type'     => 'select',
						'label'    => 'Left Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'leftType' => 'button' ),
					),
					'leftButtonModalLayout'       => array(
						'type'     => 'select',
						'label'    => 'Left Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'leftType'         => 'button',
							'leftButtonAction' => 'modal',
						),
					),
					'leftButtonModalContentType'  => array(
						'type'     => 'select',
						'label'    => 'Left Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'leftType'         => 'button',
							'leftButtonAction' => 'modal',
						),
					),
					'leftButtonModalContent'      => array(
						'type'     => 'wysiwyg',
						'label'    => 'Left Modal Content',
						'default'  => '',
						'showWhen' => array(
							'leftType'                   => 'button',
							'leftButtonAction'           => 'modal',
							'leftButtonModalContentType' => 'wysiwyg',
						),
					),
					'leftButtonModalHtml'         => array(
						'type'     => 'textarea',
						'label'    => 'Left Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'leftType'                   => 'button',
							'leftButtonAction'           => 'modal',
							'leftButtonModalContentType' => 'html',
						),
					),
					'leftButtonModalShortcode'    => array(
						'type'     => 'text',
						'label'    => 'Left Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'leftType'                   => 'button',
							'leftButtonAction'           => 'modal',
							'leftButtonModalContentType' => 'shortcode',
						),
					),
					'leftSearchPlaceholder'       => array(
						'type'     => 'text',
						'label'    => 'Left Search Placeholder',
						'default'  => 'Search by keyword',
						'showWhen' => array( 'leftType' => 'search' ),
					),
					'leftSearchUrl'               => array(
						'type'     => 'text',
						'label'    => 'Left Search URL',
						'default'  => '/?s={query}',
						'showWhen' => array( 'leftType' => 'search' ),
					),
					'leftTextColor'               => array(
						'type'    => 'color',
						'label'   => 'Left Text Color',
						'default' => '#FFFFFF',
					),
					'leftButtonColor'             => array(
						'type'    => 'color',
						'label'   => 'Left Button Background',
						'default' => '#FFFFFF',
					),
					'leftButtonTextColor'         => array(
						'type'    => 'color',
						'label'   => 'Left Button Text Color',
						'default' => '#1F2937',
					),

					// Right Side
					'rightImage'                  => array(
						'type'    => 'image',
						'label'   => 'Right Image',
						'default' => '',
					),
					'rightTitle'                  => array(
						'type'    => 'text',
						'label'   => 'Right Title',
						'default' => 'Hero Title 2',
					),
					'rightType'                   => array(
						'type'    => 'select',
						'label'   => 'Right Action Type',
						'default' => 'search',
						'options' => array(
							'Button'     => 'button',
							'Search Box' => 'search',
						),
					),
					'rightButtonText'             => array(
						'type'     => 'text',
						'label'    => 'Right Button Text',
						'default'  => 'Shop Now',
						'showWhen' => array( 'rightType' => 'button' ),
					),
					'rightButtonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Right Button URL',
						'default'  => '#',
						'showWhen' => array(
							'rightType'         => 'button',
							'rightButtonAction' => 'link',
						),
					),
					'rightButtonAction'           => array(
						'type'     => 'select',
						'label'    => 'Right Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'rightType' => 'button' ),
					),
					'rightButtonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Right Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'rightType'         => 'button',
							'rightButtonAction' => 'modal',
						),
					),
					'rightButtonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Right Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'rightType'         => 'button',
							'rightButtonAction' => 'modal',
						),
					),
					'rightButtonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Right Modal Content',
						'default'  => '',
						'showWhen' => array(
							'rightType'                   => 'button',
							'rightButtonAction'           => 'modal',
							'rightButtonModalContentType' => 'wysiwyg',
						),
					),
					'rightButtonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Right Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'rightType'                   => 'button',
							'rightButtonAction'           => 'modal',
							'rightButtonModalContentType' => 'html',
						),
					),
					'rightButtonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Right Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'rightType'                   => 'button',
							'rightButtonAction'           => 'modal',
							'rightButtonModalContentType' => 'shortcode',
						),
					),
					'rightSearchPlaceholder'      => array(
						'type'     => 'text',
						'label'    => 'Right Search Placeholder',
						'default'  => 'Search by keyword',
						'showWhen' => array( 'rightType' => 'search' ),
					),
					'rightSearchUrl'              => array(
						'type'     => 'text',
						'label'    => 'Right Search URL',
						'default'  => '/?s={query}',
						'showWhen' => array( 'rightType' => 'search' ),
					),
					'rightTextColor'              => array(
						'type'    => 'color',
						'label'   => 'Right Text Color',
						'default' => '#FFFFFF',
					),
					'rightButtonColor'            => array(
						'type'    => 'color',
						'label'   => 'Right Button Background',
						'default' => '#FFFFFF',
					),
					'rightButtonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Right Button Text Color',
						'default' => '#1F2937',
					),

					// General Styles
					'padding'                     => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 40,
						'min'     => 0,
						'max'     => 100,
					),
					'paddingX'                    => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                     => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'featured-promo-banner',
				'name'        => 'Featured Promo Banner',
				'category'    => 'marketing',
				'icon'        => 'tag',
				'description' => 'Featured banner with curved divider and badge',
				'settings'    => array(
					// Content
					'headerText'             => array(
						'type'    => 'text',
						'label'   => 'Header Text',
						'default' => 'Default title here',
					),
					'descriptionText'        => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'default' => 'Default text here',
					),
					'image'                  => array(
						'type'    => 'image',
						'label'   => 'Banner Image',
						'default' => '',
					),
					'imagePosition'          => array(
						'type'    => 'select',
						'label'   => 'Image Position',
						'default' => 'right',
						'options' => array(
							'Image Right' => 'right',
							'Image Left'  => 'left',
						),
					),
					'dividerStyle'           => array(
						'type'    => 'select',
						'label'   => 'Divider Style',
						'default' => 'circle',
						'options' => array(
							'Circle Curve'      => 'circle',
							'Arrow Shape'       => 'arrow',
							'Straight Vertical' => 'vertical',
							'Diagonal Forward'  => 'diagonal-forward',
							'Diagonal Backward' => 'diagonal-backward',
						),
					),
					'descriptionSize'        => array(
						'type'    => 'select',
						'label'   => 'Description Size',
						'default' => 'large',
						'options' => array(
							'Large'        => 'large',
							'Normal P Tag' => 'normal',
						),
					),
					'titleLetterSpacing'     => array(
						'type'    => 'slider',
						'label'   => 'Title Letter Spacing',
						'default' => 0,
						'min'     => -2,
						'max'     => 12,
					),
					'showButton'             => array(
						'type'    => 'toggle',
						'label'   => 'Show Button',
						'default' => true,
					),
					'buttonText'             => array(
						'type'     => 'text',
						'label'    => 'Button Text',
						'default'  => 'Get Started',
						'showWhen' => array( 'showButton' => true ),
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Link URL',
						'default'  => '#',
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'link',
						),
					),
					'buttonAction'           => array(
						'type'     => 'select',
						'label'    => 'Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'showButton' => true ),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),

					// Badge Settings
					'showBadge'              => array(
						'type'    => 'toggle',
						'label'   => 'Show Badge',
						'default' => true,
					),
					'badgeType'              => array(
						'type'     => 'select',
						'label'    => 'Badge Type',
						'default'  => 'new',
						'options'  => array(
							'New / In Stock' => 'new',
							'Low / Stock'    => 'low',
							'Custom Text'    => 'custom',
						),
						'showWhen' => array( 'showBadge' => true ),
					),
					'badgePosition'          => array(
						'type'     => 'select',
						'label'    => 'Badge Position',
						'default'  => 'bottom-right',
						'options'  => array(
							'Bottom Right'      => 'bottom-right',
							'Bottom Left'       => 'bottom-left',
							'Overlapping Curve' => 'overlapping',
						),
						'showWhen' => array( 'showBadge' => true ),
					),
					'badgeCustomLine1'       => array(
						'type'     => 'text',
						'label'    => 'Custom Line 1',
						'default'  => 'Special',
						'showWhen' => array(
							'showBadge' => true,
							'badgeType' => 'custom',
						),
					),
					'badgeCustomLine2'       => array(
						'type'     => 'text',
						'label'    => 'Custom Line 2',
						'default'  => 'Offer',
						'showWhen' => array(
							'showBadge' => true,
							'badgeType' => 'custom',
						),
					),

					// Style
					'backgroundColor'        => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#E0F2F1',
					),
					'badgeColor'             => array(
						'type'    => 'color',
						'label'   => 'Badge Color',
						'default' => '#3D736A',
					),
					'titleColor'             => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Description Color',
						'default' => '#1F2937',
					),
					'circleTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Badge & Button Text Color',
						'default' => '#FFFFFF',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '#3D736A',
					),

					// Dimensions
					'height'                 => array(
						'type'    => 'slider',
						'label'   => 'Height',
						'default' => 450,
						'min'     => 100,
						'max'     => 800,
					),
					'contentPadding'         => array(
						'type'    => 'slider',
						'label'   => 'Content Padding',
						'default' => 40,
						'min'     => 12,
						'max'     => 80,
					),
					'contentSpacing'         => array(
						'type'    => 'slider',
						'label'   => 'Content Spacing',
						'default' => 24,
						'min'     => 0,
						'max'     => 48,
					),
					'padding'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'text-image',
				'name'        => 'Text & Image',
				'category'    => 'content',
				'icon'        => 'layout',
				'description' => 'Text content with an accompanying image and optional CTA',
				'settings'    => array(
					'title'                  => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'About Our Story',
					),
					'content'                => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'default' => 'Share your brand story here.',
					),
					'descriptionSize'        => array(
						'type'    => 'select',
						'label'   => 'Description Size',
						'default' => 'large',
						'options' => array(
							'Large Text' => 'large',
							'P Tag'      => 'normal',
						),
					),
					'showButton'             => array(
						'type'    => 'toggle',
						'label'   => 'Show Button',
						'default' => false,
					),
					'buttonText'             => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Learn More',
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '#',
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'link',
						),
					),
					'buttonAction'           => array(
						'type'     => 'select',
						'label'    => 'Button Action',
						'default'  => 'link',
						'options'  => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
						'showWhen' => array( 'showButton' => true ),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array(
							'showButton'   => true,
							'buttonAction' => 'modal',
						),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'showButton'             => true,
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					'image'                  => array(
						'type'    => 'image',
						'label'   => 'Image',
						'default' => '',
					),
					'imagePosition'          => array(
						'type'    => 'select',
						'label'   => 'Image Position',
						'default' => 'right',
						'options' => array( 'left', 'right' ),
					),
					'backgroundColor'        => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'titleColor'             => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Description Color',
						'default' => '#4B5563',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '#2563EB',
					),
					'buttonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'padding'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 0,
						'max'     => 120,
					),
					'height'                 => array(
						'type'    => 'slider',
						'label'   => 'Block Height',
						'default' => 400,
						'min'     => 100,
						'max'     => 800,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 20,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'testimonials',
				'name'        => 'Testimonials',
				'category'    => 'content',
				'icon'        => 'message-circle',
				'description' => 'Customer testimonials carousel',
				'settings'    => array(
					'testimonials'    => array(
						'type'    => 'repeater',
						'label'   => 'Testimonials',
						'fields'  => array(
							'title'    => array(
								'type'    => 'text',
								'label'   => 'Title',
								'default' => 'Testimonial Title',
							),
							'quote'    => array(
								'type'    => 'richtext',
								'label'   => 'Quote',
								'default' => 'Share your testimonial here...',
							),
							'author'   => array(
								'type'    => 'text',
								'label'   => 'Author Name',
								'default' => 'Customer Name',
							),
							'location' => array(
								'type'    => 'text',
								'label'   => 'Location',
								'default' => 'City, State',
							),
							'image'    => array(
								'type'    => 'image',
								'label'   => 'Image',
								'default' => '',
							),
						),
						'default' => array(
							array(
								'title'    => 'Testimonial Title',
								'quote'    => 'Share your testimonial here...',
								'author'   => 'Customer Name',
								'location' => 'City, State',
								'image'    => '',
							),
						),
					),
					'primaryColor'    => array(
						'type'    => 'color',
						'label'   => 'Primary Color (Arrows & Icon)',
						'default' => '#0F6B8C',
					),
					'titleColor'      => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'textColor'       => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#4B5563',
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 20,
						'max'     => 120,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'form-embed',
				'name'        => 'Form',
				'category'    => 'content',
				'icon'        => 'list-checks',
				'description' => 'Embed a DesignStudio Flow form',
				'settings'    => array(
					'formId'    => array(
						'type'    => 'select',
						'label'   => 'Form',
						'default' => '',
						'options' => array(
							'Select a form' => '',
						),
					),
					'showTitle' => array(
						'type'    => 'toggle',
						'label'   => 'Show Block Title',
						'default' => false,
					),
					'title'     => array(
						'type'     => 'text',
						'label'    => 'Block Title',
						'default'  => '',
						'showWhen' => array(
							'showTitle' => true,
						),
					),
					'formMaxWidth' => array(
						'type'    => 'slider',
						'label'   => 'Form Max Width (px)',
						'default' => 600,
						'min'     => 300,
						'max'     => 1200,
					),
					'formAlignment' => array(
						'type'    => 'select',
						'label'   => 'Form Alignment',
						'default' => 'center',
						'options' => array(
							'Left'   => 'left',
							'Center' => 'center',
							'Right'  => 'right',
						),
					),
					'marginY'   => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'form-with-content',
				'name'        => 'Form with Content',
				'category'    => 'content',
				'icon'        => 'columns',
				'description' => 'Two-column block with a form on one side and rich content on the other',
				'settings'    => array(
					// Section header
					'sectionTitle'    => array(
						'type'    => 'text',
						'label'   => 'Section Title',
						'default' => '',
					),
					'showDivider'     => array(
						'type'    => 'toggle',
						'label'   => 'Show Divider Line',
						'default' => false,
					),
					'dividerColor'    => array(
						'type'     => 'color',
						'label'    => 'Divider Color',
						'default'  => '#E5E7EB',
						'showWhen' => array( 'showDivider' => true ),
					),
					// Form settings
					'formSource'      => array(
						'type'    => 'select',
						'label'   => 'Form Source',
						'default' => 'dsf',
						'options' => array(
							'DSF Form'               => 'dsf',
							'Custom Content / Embed'  => 'embed',
						),
					),
					'formId'          => array(
						'type'     => 'select',
						'label'    => 'Form',
						'default'  => '',
						'options' => array(
							'Select a form' => '',
						),
						'showWhen' => array( 'formSource' => 'dsf' ),
					),
					'embedCode'       => array(
						'type'         => 'wysiwyg',
						'label'        => 'Custom Content',
						'default'      => '',
						'allowRawHtml' => true,
						'showWhen'     => array( 'formSource' => 'embed' ),
					),
					'formSide'        => array(
						'type'    => 'select',
						'label'   => 'Form Position',
						'default' => 'right',
						'options' => array(
							'Form on Left'  => 'left',
							'Form on Right' => 'right',
						),
					),
					'columnRatio'     => array(
						'type'    => 'select',
						'label'   => 'Column Ratio',
						'default' => '1-1',
						'options' => array(
							'50% / 50%' => '1-1',
							'60% / 40%' => '3-2',
							'40% / 60%' => '2-3',
						),
					),
					// Content column
					'content'         => array(
						'type'    => 'wysiwyg',
						'label'   => 'Content',
						'default' => '<p><b>Your dream backyard starts here!</b></p><p>Fill out the form and we\'ll be in touch as soon as possible.</p>',
					),
					'mediaType'       => array(
						'type'    => 'select',
						'label'   => 'Media Type',
						'default' => 'video',
						'options' => array(
							'Video' => 'video',
							'Image' => 'image',
						),
					),
					'image'           => array(
						'type'     => 'image',
						'label'    => 'Image',
						'default'  => '',
						'showWhen' => array( 'mediaType' => 'image' ),
					),
					'logo'            => array(
						'type'    => 'image',
						'label'   => 'Logo',
						'default' => '',
					),
					'logoPadding'     => array(
						'type'    => 'toggle',
						'label'   => 'Add Padding to Logo',
						'default' => false,
					),
					'video'           => array(
						'type'     => 'text',
						'label'    => 'Video Embed URL (YouTube / Vimeo)',
						'default'  => '',
						'showWhen' => array( 'mediaType' => 'video' ),
					),
					'videoFile'       => array(
						'type'     => 'video',
						'label'    => 'Video File (MP4 / WebM from Media Library)',
						'default'  => '',
						'showWhen' => array( 'mediaType' => 'video' ),
					),
					// Colors
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'contentBg'       => array(
						'type'    => 'color',
						'label'   => 'Content Background',
						'default' => '',
					),
					'formBg'          => array(
						'type'    => 'color',
						'label'   => 'Form Column Background',
						'default' => '',
					),
					'textColor'       => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#1F2937',
					),
					'titleColor'      => array(
						'type'    => 'color',
						'label'   => 'Section Title Color',
						'default' => '#1F2937',
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 0,
						'max'     => 120,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		// ECOMMERCE Category
		$this->register_block(
			array(
				'id'          => 'product-grid',
				'name'        => 'Product Grid',
				'category'    => 'ecommerce',
				'icon'        => 'shopping-bag',
				'description' => 'Display products from WooCommerce',
				'settings'    => array(
					// ── Section: Settings ────────────────────────────────
					'title'              => array(
						'type'         => 'text',
						'label'        => 'Section Title',
						'default'      => 'Featured Products',
						'section'      => 'settings',
						'sectionTitle' => 'Settings',
					),
					'showPrice'          => array(
						'type'         => 'toggle',
						'label'        => 'Show Price',
						'default'      => true,
						'section'      => 'settings',
						'sectionTitle' => 'Settings',
					),
					'showButton'         => array(
						'type'         => 'toggle',
						'label'        => 'Show Add to Cart',
						'default'      => true,
						'section'      => 'settings',
						'sectionTitle' => 'Settings',
					),
					'buttonText'         => array(
						'type'         => 'text',
						'label'        => 'Button Text',
						'default'      => 'Add to Cart',
						'section'      => 'settings',
						'sectionTitle' => 'Settings',
					),
					// ── Section: Source ──────────────────────────────────
					'source'             => array(
						'type'         => 'source',
						'label'        => 'Product Source',
						'default'      => 'category',
						'options'      => array( 'category', 'manual' ),
						'section'      => 'source',
						'sectionTitle' => 'Source',
					),
					'categoryIds'        => array(
						'type'         => 'categories',
						'label'        => 'Select Categories',
						'default'      => array(),
						'section'      => 'source',
						'sectionTitle' => 'Source',
					),
					'pinnedProductIds'   => array(
						'type'                => 'products',
						'label'               => 'Pin Top Products (Optional)',
						'default'             => array(),
						'searchPlaceholder'   => 'Search category products...',
						'hideSearchCardTitle' => true,
						'categorySettingKey'  => 'categoryIds',
						'legacyCategoryKey'   => 'categoryId',
						'section'             => 'source',
						'sectionTitle'        => 'Source',
					),
					'productIds'         => array(
						'type'         => 'products',
						'label'        => 'Select Products',
						'default'      => array(),
						'section'      => 'source',
						'sectionTitle' => 'Source',
					),
					'perPage'            => array(
						'type'         => 'number',
						'label'        => 'Products Per Page',
						'default'      => 12,
						'min'          => 1,
						'max'          => 100,
						'section'      => 'source',
						'sectionTitle' => 'Source',
					),
					'columns'            => array(
						'type'         => 'select',
						'label'        => 'Columns',
						'default'      => '3',
						'options'      => array( '2', '3', '4' ),
						'section'      => 'source',
						'sectionTitle' => 'Source',
					),
					// ── Section: Filters ─────────────────────────────────
					'enableFilters'      => array(
						'type'         => 'toggle',
						'label'        => 'Enable Filters',
						'default'      => false,
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'enableSearch'       => array(
						'type'         => 'toggle',
						'label'        => 'Enable Search',
						'default'      => false,
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'searchPlaceholder'  => array(
						'type'         => 'text',
						'label'        => 'Search Placeholder',
						'default'      => 'Search products',
						'showWhen'     => array( 'enableSearch' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterPosition'     => array(
						'type'         => 'select',
						'label'        => 'Filter Sidebar Position',
						'default'      => 'left',
						'options'      => array(
							'Left'  => 'left',
							'Right' => 'right',
						),
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterShowPrice'    => array(
						'type'         => 'toggle',
						'label'        => 'Price Filter',
						'default'      => true,
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterShowCategory' => array(
						'type'         => 'toggle',
						'label'        => 'Category Filter',
						'default'      => true,
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterAttributes'   => array(
						'type'         => 'multiselect_tags',
						'label'        => 'Attribute Filters',
						'default'      => array(),
						'helper'       => 'Type an attribute key (e.g. brand, color, material) and press Enter to add it as a filter.',
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterShowTags'     => array(
						'type'         => 'toggle',
						'label'        => 'Tags Filter',
						'default'      => false,
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterTags'         => array(
						'type'         => 'product_tags',
						'label'        => 'Filter Tags',
						'default'      => null,
						'helper'       => 'All product tags are included by default. Remove chips to hide specific tags from the frontend filter, or type to add them back.',
						'showWhen'     => array(
							'enableFilters'  => true,
							'filterShowTags' => true,
						),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					'filterShowRating'   => array(
						'type'         => 'toggle',
						'label'        => 'Rating Filter',
						'default'      => false,
						'showWhen'     => array( 'enableFilters' => true ),
						'section'      => 'filters',
						'sectionTitle' => 'Filters',
					),
					// ── Style fields (no section → go to Style tab) ──────
					'cardStyle'          => array(
						'type'    => 'select',
						'label'   => 'Card Style',
						'default' => 'classic',
						'options' => array(
							'Classic' => 'classic',
							'Minimal' => 'minimal',
							'Modern'  => 'modern',
						),
					),
					'backgroundColor'    => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'titleColor'         => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'padding'            => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 20,
						'max'     => 120,
					),
					'paddingX'           => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'            => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		// PRODUCT PAGE Category — blocks that bind to the current product. Only
		// available inside a Product Template (template_scope => 'product').
		$this->register_block(
			array(
				'id'             => 'product-summary',
				'name'           => 'Product Summary',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'shopping-bag',
				'description'    => 'Title, price, and short description for the current product.',
				'settings'       => array(
					'showTitle'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Title',
						'default' => true,
					),
					'headingTag'           => array(
						'type'    => 'select',
						'label'   => 'Title Heading Level',
						'default' => 'h1',
						'options' => array(
							'H1' => 'h1',
							'H2' => 'h2',
						),
					),
					'showPrice'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Price',
						'default' => true,
					),
					'showShortDescription' => array(
						'type'    => 'toggle',
						'label'   => 'Show Short Description',
						'default' => true,
					),
					'showSku'              => array(
						'type'    => 'toggle',
						'label'   => 'Show SKU',
						'default' => false,
					),
					'showStock'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Stock Status',
						'default' => true,
					),
					'showRating'           => array(
						'type'    => 'toggle',
						'label'   => 'Show Rating',
						'default' => true,
					),
					'alignment'            => array(
						'type'    => 'select',
						'label'   => 'Alignment',
						'default' => 'left',
						'options' => array(
							'Left'   => 'left',
							'Center' => 'center',
						),
					),
					'titleColor'           => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '',
					),
					'priceColor'           => array(
						'type'    => 'color',
						'label'   => 'Price Color',
						'default' => '',
					),
					'textColor'            => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '',
					),
					'maxWidth'             => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 640,
						'min'     => 320,
						'max'     => 1200,
					),
					'padding'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'             => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-gallery',
				'name'           => 'Product Gallery',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'image',
				'description'    => 'The current product gallery, with multiple layouts and an optional lightbox.',
				'settings'       => array(
					'layout'         => array(
						'type'    => 'select',
						'label'   => 'Layout',
						'default' => 'thumbs-bottom',
						'options' => array(
							'Main + Thumbnails (below)' => 'thumbs-bottom',
							'Main + Thumbnails (left)'  => 'thumbs-left',
							'Mosaic Grid'               => 'grid',
							'Carousel'                  => 'carousel',
							'Single Image'              => 'single',
						),
					),
					'aspectRatio'    => array(
						'type'    => 'select',
						'label'   => 'Image Shape',
						'default' => 'square',
						'options' => array(
							'Square'    => 'square',
							'Portrait'  => 'portrait',
							'Landscape' => 'landscape',
							'Natural'   => 'natural',
						),
					),
					'enableLightbox' => array(
						'type'    => 'toggle',
						'label'   => 'Enable Lightbox',
						'default' => true,
					),
					'showThumbs'     => array(
						'type'    => 'toggle',
						'label'   => 'Show Thumbnails',
						'default' => true,
					),
					'thumbColumns'   => array(
						'type'    => 'slider',
						'label'   => 'Thumbnail Columns',
						'default' => 5,
						'min'     => 2,
						'max'     => 8,
					),
					'gap'            => array(
						'type'    => 'slider',
						'label'   => 'Gap',
						'default' => 12,
						'min'     => 0,
						'max'     => 40,
					),
					'maxWidth'       => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 640,
						'min'     => 320,
						'max'     => 1200,
					),
					'padding'        => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'       => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'        => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-description',
				'name'           => 'Product Description',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'file-text',
				'description'    => 'The current product long description.',
				'settings'       => array(
					'showHeading'  => array(
						'type'    => 'toggle',
						'label'   => 'Show Heading',
						'default' => true,
					),
					'headingText'  => array(
						'type'    => 'text',
						'label'   => 'Heading Text',
						'default' => 'Description',
					),
					'headingColor' => array(
						'type'    => 'color',
						'label'   => 'Heading Color',
						'default' => '',
					),
					'textColor'    => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '',
					),
					'maxWidth'     => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 900,
						'min'     => 320,
						'max'     => 1400,
					),
					'padding'      => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'     => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'      => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-specs',
				'name'           => 'Product Specs',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'list-checks',
				'description'    => 'The current product attributes as a specs table, in several styles.',
				'settings'       => array(
					'layout'       => array(
						'type'    => 'select',
						'label'   => 'Style',
						'default' => 'striped',
						'options' => array(
							'Striped Rows' => 'striped',
							'Card Grid'    => 'cards',
							'Inline Pills' => 'inline',
							'Bordered'     => 'bordered',
						),
					),
					'showHeading'  => array(
						'type'    => 'toggle',
						'label'   => 'Show Heading',
						'default' => true,
					),
					'headingText'  => array(
						'type'    => 'text',
						'label'   => 'Heading Text',
						'default' => 'Specifications',
					),
					'columns'      => array(
						'type'    => 'slider',
						'label'   => 'Card Columns',
						'default' => 1,
						'min'     => 1,
						'max'     => 3,
					),
					'headingColor' => array(
						'type'    => 'color',
						'label'   => 'Heading Color',
						'default' => '',
					),
					'labelColor'   => array(
						'type'    => 'color',
						'label'   => 'Label Color',
						'default' => '',
					),
					'valueColor'   => array(
						'type'    => 'color',
						'label'   => 'Value Color',
						'default' => '',
					),
					'accentColor'  => array(
						'type'    => 'color',
						'label'   => 'Accent / Stripe Color',
						'default' => '',
					),
					'maxWidth'     => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 760,
						'min'     => 320,
						'max'     => 1200,
					),
					'padding'      => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'     => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'      => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-tabs',
				'name'           => 'Product Tabs',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'layout-template',
				'description'    => 'A custom tab system. Each tab shows the description, specs, or your own content.',
				'settings'       => array(
					'style'       => array(
						'type'    => 'select',
						'label'   => 'Tab Style',
						'default' => 'underline',
						'options' => array(
							'Underline' => 'underline',
							'Pills'     => 'pills',
							'Boxed'     => 'boxed',
						),
					),
					'tabs'        => array(
						'type'    => 'product_tabs',
						'label'   => 'Tabs',
						'default' => array(
							array(
								'label'   => 'Description',
								'source'  => 'description',
								'content' => '',
							),
							array(
								'label'   => 'Specifications',
								'source'  => 'specs',
								'content' => '',
							),
						),
					),
					'accentColor' => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '',
					),
					'maxWidth'    => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 900,
						'min'     => 320,
						'max'     => 1400,
					),
					'padding'     => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'    => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'     => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-add-to-cart',
				'name'           => 'Add to Cart',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'shopping-cart',
				'description'    => "WooCommerce's native add-to-cart form (quantity, variations, AJAX).",
				'settings'       => array(
					'alignment'       => array(
						'type'    => 'select',
						'label'   => 'Alignment',
						'default' => 'left',
						'options' => array(
							'Left'   => 'left',
							'Center' => 'center',
						),
					),
					'showPrice'       => array(
						'type'    => 'toggle',
						'label'   => 'Show Price Next to Button',
						'default' => true,
					),
					'priceColor'      => array(
						'type'    => 'color',
						'label'   => 'Price Color',
						'default' => '',
					),
					'buttonColor'     => array(
						'type'    => 'color',
						'label'   => 'Button Color',
						'default' => '',
					),
					'buttonTextColor' => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '',
					),
					'maxWidth'        => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 460,
						'min'     => 280,
						'max'     => 900,
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-hero',
				'name'           => 'Product Hero',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'layout',
				'description'    => 'A modern two-column hero: gallery, title, price, rating, and add-to-cart.',
				'settings'       => array(
					'imageSide'            => array(
						'type'    => 'select',
						'label'   => 'Image Side',
						'default' => 'left',
						'options' => array(
							'Left'  => 'left',
							'Right' => 'right',
						),
					),
					'eyebrowText'          => array(
						'type'    => 'text',
						'label'   => 'Eyebrow Text',
						'default' => '',
					),
					'showRating'           => array(
						'type'    => 'toggle',
						'label'   => 'Show Rating',
						'default' => true,
					),
					'showPrice'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Price',
						'default' => true,
					),
					'showShortDescription' => array(
						'type'    => 'toggle',
						'label'   => 'Show Short Description',
						'default' => true,
					),
					'showStock'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Stock Status',
						'default' => true,
					),
					'showSku'              => array(
						'type'    => 'toggle',
						'label'   => 'Show SKU',
						'default' => false,
					),
					'showAddToCart'        => array(
						'type'    => 'toggle',
						'label'   => 'Show Add to Cart',
						'default' => true,
					),
					'showSaleBadge'        => array(
						'type'    => 'toggle',
						'label'   => 'Show Sale Badge',
						'default' => true,
					),
					'saleBadgeText'        => array(
						'type'     => 'text',
						'label'    => 'Sale Badge Text',
						'default'  => 'Sale',
						'showWhen' => array( 'showSaleBadge' => true ),
					),
					'accentColor'          => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '',
					),
					'titleColor'           => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '',
					),
					'priceColor'           => array(
						'type'    => 'color',
						'label'   => 'Price Color',
						'default' => '',
					),
					'buttonColor'          => array(
						'type'    => 'color',
						'label'   => 'Add-to-Cart Button Color',
						'default' => '',
					),
					'buttonTextColor'      => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '',
					),
					'backgroundColor'      => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '',
					),
					'maxWidth'             => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 1200,
						'min'     => 720,
						'max'     => 1600,
					),
					'padding'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 48,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'             => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-highlights',
				'name'           => 'Product Highlights',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'award',
				'description'    => 'Trust badges: shipping, warranty, returns — icons with short text.',
				'settings'       => array(
					'items'           => array(
						'type'    => 'icon_items',
						'label'   => 'Highlights',
						'default' => array(
							array(
								'icon'        => 'rocket',
								'title'       => 'Free shipping',
								'description' => 'On orders over $50',
							),
							array(
								'icon'        => 'shield-check',
								'title'       => '2-year warranty',
								'description' => 'Covered from day one',
							),
							array(
								'icon'        => 'check',
								'title'       => '30-day returns',
								'description' => 'No questions asked',
							),
						),
					),
					'layout'          => array(
						'type'    => 'select',
						'label'   => 'Layout',
						'default' => 'row',
						'options' => array(
							'Row'  => 'row',
							'Grid' => 'grid',
						),
					),
					'columns'         => array(
						'type'     => 'slider',
						'label'    => 'Grid Columns',
						'default'  => 3,
						'min'      => 2,
						'max'      => 4,
						'showWhen' => array( 'layout' => 'grid' ),
					),
					'cardStyle'       => array(
						'type'    => 'toggle',
						'label'   => 'Card Style',
						'default' => true,
					),
					'accentColor'     => array(
						'type'    => 'color',
						'label'   => 'Icon Color',
						'default' => '',
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '',
					),
					'maxWidth'        => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 1100,
						'min'     => 480,
						'max'     => 1400,
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'product-related',
				'name'           => 'Related Products',
				'category'       => 'product',
				'template_scope' => 'product',
				'icon'           => 'grid-3x3',
				'description'    => "WooCommerce's related products as a modern card grid.",
				'settings'       => array(
					'showHeading'     => array(
						'type'    => 'toggle',
						'label'   => 'Show Heading',
						'default' => true,
					),
					'headingText'     => array(
						'type'    => 'text',
						'label'   => 'Heading Text',
						'default' => 'You may also like',
					),
					'count'           => array(
						'type'    => 'slider',
						'label'   => 'Products to Show',
						'default' => 4,
						'min'     => 2,
						'max'     => 8,
					),
					'columns'         => array(
						'type'    => 'slider',
						'label'   => 'Columns',
						'default' => 4,
						'min'     => 2,
						'max'     => 4,
					),
					'showPrice'       => array(
						'type'    => 'toggle',
						'label'   => 'Show Price',
						'default' => true,
					),
					'headingColor'    => array(
						'type'    => 'color',
						'label'   => 'Heading Color',
						'default' => '',
					),
					'accentColor'     => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '',
					),
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '',
					),
					'maxWidth'        => array(
						'type'    => 'slider',
						'label'   => 'Max Width',
						'default' => 1200,
						'min'     => 480,
						'max'     => 1600,
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 40,
						'min'     => 0,
						'max'     => 160,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 120,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'ecommerce-showcase',
				'name'        => 'Ecommerce Showcase',
				'category'    => 'ecommerce',
				'icon'        => 'shopping-bag',
				'description' => 'Display categories or products with optional slider',
				'settings'    => array(
					'displayMode'      => array(
						'type'    => 'source',
						'label'   => 'Display Mode',
						'default' => 'categories',
					),
					'title'            => array(
						'type'    => 'text',
						'label'   => 'Section Title',
						'default' => 'Ecommerce Showcase',
					),
					'shopAllText'      => array(
						'type'    => 'text',
						'label'   => 'Shop All Link Text',
						'default' => 'SHOP ALL',
					),
					'showShopAll'      => array(
						'type'    => 'toggle',
						'label'   => 'Show Shop All Link',
						'default' => true,
					),
					// Categories mode settings (only show when displayMode is 'categories')
					'categoryIds'      => array(
						'type'     => 'categories',
						'label'    => 'Available Categories',
						'default'  => array(),
						'showWhen' => array( 'displayMode' => 'categories' ),
					),
					// Products mode settings (only show when displayMode is 'products')
					'categoryId'       => array(
						'type'     => 'category',
						'label'    => 'Select Category',
						'default'  => 0,
						'showWhen' => array( 'displayMode' => 'products' ),
					),
					'pinnedProductIds' => array(
						'type'                => 'products',
						'label'               => 'Pin Top Products (Optional)',
						'default'             => array(),
						'showWhen'            => array( 'displayMode' => 'products' ),
						'searchPlaceholder'   => 'Search category products...',
						'hideSearchCardTitle' => true,
					),
					'limit'            => array(
						'type'    => 'number',
						'label'   => 'Items to Show',
						'default' => 5,
						'min'     => 1,
						'max'     => 20,
					),
					// Style settings
					'backgroundColor'  => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'titleColor'       => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'priceColor'       => array(
						'type'     => 'color',
						'label'    => 'Price Color',
						'default'  => '#6B7280',
						'showWhen' => array( 'displayMode' => 'products' ),
					),
					'salePriceColor'   => array(
						'type'     => 'color',
						'label'    => 'Sale Price Color',
						'default'  => '#DC2626',
						'showWhen' => array( 'displayMode' => 'products' ),
					),
					'padding'          => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 20,
						'max'     => 120,
					),
					'paddingX'         => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'          => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'brand-carousel',
				'name'        => 'Brand Logos',
				'category'    => 'marketing',
				'icon'        => 'award',
				'description' => 'Display brand logos in a stacked grid layout',
				'settings'    => array(
					// Title
					'title'           => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Shop By Brand',
					),
					'showTitle'       => array(
						'type'    => 'toggle',
						'label'   => 'Show Title',
						'default' => true,
					),
					'titleColor'      => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'titleSize'       => array(
						'type'    => 'slider',
						'label'   => 'Title Size',
						'default' => 32,
						'min'     => 18,
						'max'     => 48,
					),
					// Brands
					'brands'          => array(
						'type'    => 'repeater',
						'label'   => 'Brands',
						'default' => array(),
					),
					// Layout
					'logosPerRow'     => array(
						'type'    => 'slider',
						'label'   => 'Logos Per Row',
						'default' => 4,
						'min'     => 2,
						'max'     => 6,
					),
					'logoMaxHeight'   => array(
						'type'    => 'slider',
						'label'   => 'Logo Max Height',
						'default' => 60,
						'min'     => 30,
						'max'     => 120,
					),
					'logoGap'         => array(
						'type'    => 'slider',
						'label'   => 'Logo Gap',
						'default' => 48,
						'min'     => 16,
						'max'     => 80,
					),
					// Style
					'backgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#FFFFFF',
					),
					'padding'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 40,
						'min'     => 20,
						'max'     => 100,
					),
					'paddingX'        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		// MARKETING Category
		$this->register_block(
			array(
				'id'          => 'promo-banner',
				'name'        => 'Promo Banner',
				'category'    => 'marketing',
				'icon'        => 'percent',
				'description' => 'Large image with promotional offer panel',
				'settings'    => array(
					'image'                  => array(
						'type'    => 'image',
						'label'   => 'Banner Image',
						'default' => '',
					),
					'imagePosition'          => array(
						'type'    => 'select',
						'label'   => 'Image Position',
						'default' => 'center',
						'options' => array(
							'Top'    => 'top',
							'Center' => 'center',
							'Bottom' => 'bottom',
						),
					),
					'contentPosition'        => array(
						'type'    => 'select',
						'label'   => 'Content Position',
						'default' => 'right',
						'options' => array(
							'Left'  => 'left',
							'Right' => 'right',
						),
					),
					'preText'                => array(
						'type'    => 'text',
						'label'   => 'Pre-Text',
						'default' => 'UP TO',
					),
					'discountAmount'         => array(
						'type'    => 'text',
						'label'   => 'Discount Amount',
						'default' => '20',
					),
					'showPercent'            => array(
						'type'    => 'toggle',
						'label'   => 'Show Percent Sign',
						'default' => true,
					),
					'discountSuffix'         => array(
						'type'    => 'text',
						'label'   => 'Discount Suffix',
						'default' => 'OFF',
					),
					'subtitle'               => array(
						'type'    => 'text',
						'label'   => 'Subtitle Line 1',
						'default' => 'Select',
					),
					'subtitle2'              => array(
						'type'    => 'text',
						'label'   => 'Subtitle Line 2',
						'default' => 'Casual Seating',
					),
					'buttonText'             => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Shop Now',
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '/shop',
						'showWhen' => array( 'buttonAction' => 'link' ),
					),
					'buttonAction'           => array(
						'type'    => 'select',
						'label'   => 'Button Action',
						'default' => 'link',
						'options' => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					'panelColor'             => array(
						'type'    => 'color',
						'label'   => 'Panel Color',
						'default' => '#2C5F5D',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#FFFFFF',
					),
					'dividerColor'           => array(
						'type'    => 'color',
						'label'   => 'Divider Color',
						'default' => '#FFFFFF',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '#FFFFFF',
					),
					'buttonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#2C5F5D',
					),
					'bannerHeight'           => array(
						'type'    => 'slider',
						'label'   => 'Banner Height',
						'default' => 280,
						'min'     => 280,
						'max'     => 580,
					),
					'panelWidth'             => array(
						'type'    => 'slider',
						'label'   => 'Panel Width',
						'default' => 580,
						'min'     => 580,
						'max'     => 800,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'featured-product-banner',
				'name'        => 'Featured Product Banner',
				'category'    => 'ecommerce',
				'icon'        => 'star',
				'description' => 'Showcase a featured product with sale banner and promo code',
				'settings'    => array(
					// Background

					// Sale Banner
					'bannerText'             => array(
						'type'    => 'text',
						'label'   => 'Banner Text',
						'default' => '20%',
					),
					'bannerSubtext'          => array(
						'type'    => 'text',
						'label'   => 'Banner Subtext',
						'default' => 'OFF',
					),
					'bannerColor'            => array(
						'type'    => 'color',
						'label'   => 'Banner Color',
						'default' => '#2C5F5D',
					),
					'bannerTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Banner Text Color',
						'default' => '#FFFFFF',
					),
					// Product Circle
					'circleColor'            => array(
						'type'    => 'color',
						'label'   => 'Circle Color',
						'default' => 'rgba(255,255,255,0.5)',
					),
					'productImage'           => array(
						'type'    => 'image',
						'label'   => 'Product Image',
						'default' => '',
					),
					// Content
					'title'                  => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Special Offer',
					),
					'promoCode'              => array(
						'type'    => 'text',
						'label'   => 'Promo Code',
						'default' => 'HAPPY2026',
					),
					'description'            => array(
						'type'    => 'text',
						'label'   => 'Description',
						'default' => 'Enter promo code at checkout!',
					),
					'buttonText'             => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Shop All',
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '/shop',
						'showWhen' => array( 'buttonAction' => 'link' ),
					),
					'buttonAction'           => array(
						'type'    => 'select',
						'label'   => 'Button Action',
						'default' => 'link',
						'options' => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					// Styles
					'titleColor'             => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#1F2937',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Color',
						'default' => '#2C5F5D',
					),
					'buttonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#FFFFFF',
					),
					'backgroundType'         => array(
						'type'    => 'select',
						'label'   => 'Background Type',
						'default' => 'gradient',
						'options' => array(
							'Solid Color' => 'solid',
							'Gradient'    => 'gradient',
							'Image'       => 'image',
						),
					),
					'backgroundColor'        => array(
						'type'     => 'color',
						'label'    => 'Background Color',
						'default'  => '#C8E6C9',
						'showWhen' => array( 'backgroundType' => 'solid' ),
					),
					'gradientStart'          => array(
						'type'     => 'color',
						'label'    => 'Gradient Start',
						'default'  => '#B2DFDB',
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					'gradientEnd'            => array(
						'type'     => 'color',
						'label'    => 'Gradient End',
						'default'  => '#C8E6C9',
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					'gradientDirection'      => array(
						'type'     => 'select',
						'label'    => 'Gradient Direction',
						'default'  => 'left-right',
						'options'  => array(
							'Left to Right'       => 'left-right',
							'Top to Bottom'       => 'top-bottom',
							'Radial (Center Out)' => 'radial',
						),
						'showWhen' => array( 'backgroundType' => 'gradient' ),
					),
					'backgroundImage'        => array(
						'type'     => 'image',
						'label'    => 'Background Image',
						'default'  => '',
						'showWhen' => array( 'backgroundType' => 'image' ),
					),
					// Layout
					'bannerHeight'           => array(
						'type'    => 'slider',
						'label'   => 'Banner Height',
						'default' => 240,
						'min'     => 240,
						'max'     => 350,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'header-mega-menu',
				'name'           => 'Header Mega Menu',
				'category'       => 'content',
				'template_scope' => 'header',
				'icon'           => 'layout-template',
				'description'    => 'Two-row header with utility links, icon actions, and configurable mega menus',
				'settings'       => array(
					'utilityLinks'                    => array(
						'type'    => 'simple_links',
						'label'   => 'Utility Links',
						'default' => array(
							array(
								'label' => 'Test',
								'url'   => '#',
							),
							array(
								'label' => 'About',
								'url'   => '#',
							),
						),
					),
					'logoText'                        => array(
						'type'    => 'text',
						'label'   => 'Logo Text',
						'default' => 'DESIGNSTUDIO',
					),
					'logoImage'                       => array(
						'type'    => 'image',
						'label'   => 'Logo Image',
						'default' => '',
					),
					'logoAlt'                         => array(
						'type'    => 'text',
						'label'   => 'Logo Alt Text',
						'default' => 'Site logo',
					),
					'logoImageSize'                   => array(
						'type'    => 'slider',
						'label'   => 'Logo Image Size',
						'default' => 100,
						'min'     => 30,
						'max'     => 100,
						'unit'    => '%',
					),
					'homeUrl'                         => array(
						'type'    => 'text',
						'label'   => 'Logo URL',
						'default' => '/',
					),
					'showLanguage'                    => array(
						'type'    => 'toggle',
						'label'   => 'Show Language Icon',
						'default' => false,
					),
					'languageUrl'                     => array(
						'type'     => 'text',
						'label'    => 'Language URL',
						'default'  => '#',
						'showWhen' => array( 'showLanguage' => true ),
					),
					'showSearch'                      => array(
						'type'    => 'toggle',
						'label'   => 'Show Search Icon',
						'default' => true,
					),
					'searchUrl'                       => array(
						'type'     => 'text',
						'label'    => 'Search URL',
						'default'  => '/?s=',
						'showWhen' => array( 'showSearch' => true ),
					),
					'showAccount'                     => array(
						'type'    => 'toggle',
						'label'   => 'Show Account Icon',
						'default' => true,
					),
					'accountUrl'                      => array(
						'type'     => 'text',
						'label'    => 'Account URL',
						'default'  => '/my-account/',
						'showWhen' => array( 'showAccount' => true ),
					),
					'showCart'                        => array(
						'type'    => 'toggle',
						'label'   => 'Show Cart Icon',
						'default' => true,
					),
					'cartUrl'                         => array(
						'type'     => 'text',
						'label'    => 'Cart URL',
						'default'  => '/cart/',
						'showWhen' => array( 'showCart' => true ),
					),
					'cartCount'                       => array(
						'type'    => 'number',
						'label'   => 'Cart Count',
						'default' => 0,
						'min'     => 0,
						'max'     => 99,
					),
					'menuItems'                       => array(
						'type'    => 'mega_menu',
						'label'   => 'Primary Menu + Mega Menu Content',
						'default' => array(
							array(
								'label'   => 'PRODUCT LINE 1',
								'url'     => '#',
								'hasMega' => true,
								'columns' => array(
									array(
										'heading'      => 'Sub Heading 1',
										'imageLinks'   => true,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Link 1',
												'url'   => '#',
											),
											array(
												'label' => 'Link 2',
												'url'   => '#',
											),
										),
									),
									array(
										'heading'      => 'Sub Heading 2',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'This is Long Title',
												'url'   => '#',
											),
											array(
												'label' => 'Link 2',
												'url'   => '#',
											),
										),
									),
									array(
										'heading'      => 'Sub Heading 3',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Link 1',
												'url'   => '#',
											),
											array(
												'label' => 'Link 2',
												'url'   => '#',
											),
										),
									),
									array(
										'heading'      => 'Sub Heading 4',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Link 1',
												'url'   => '#',
											),
											array(
												'label' => 'Link 2',
												'url'   => '#',
											),
										),
									),
								),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'PRODUCT LINE 2',
								'url'     => '#',
								'hasMega' => true,
								'columns' => array(
									array(
										'heading'      => 'Sub Heading',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Link A',
												'url'   => '#',
											),
											array(
												'label' => 'Link B',
												'url'   => '#',
											),
										),
									),
								),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'PRODUCT LINE 3',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'PRODUCT LINE 4',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'PROMOTIONS',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'SHOP ONLINE',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
						),
					),
					'mobileMenuItems'                 => array(
						'type'    => 'mega_menu',
						'label'   => 'Mobile Menu Content',
						'default' => array(),
						'helper'  => 'Defaults to your desktop menu until you customize it.',
					),
					'mobileShowFindLocation'          => array(
						'type'    => 'toggle',
						'label'   => 'Show Find Location (Mobile)',
						'default' => true,
					),
					'mobileFindLabel'                 => array(
						'type'    => 'text',
						'label'   => 'Find Location Label',
						'default' => 'Find a Store',
					),
					'mobileFindPopupTitle'            => array(
						'type'    => 'text',
						'label'   => 'Find Location Popup Title',
						'default' => 'Find your closest store',
					),
					'mobileStores'                    => array(
						'type'    => 'mobile_stores',
						'label'   => 'Find Location Stores',
						'default' => array(
							array(
								'title'       => 'New Hampton',
								'address'     => "5008 Route 17M\nNew Hampton, New York 10958",
								'mapsLabel'   => 'Open in Google Maps',
								'mapsUrl'     => '#',
								'buttonLabel' => 'Set as Default',
								'buttonUrl'   => '#',
							),
							array(
								'title'       => 'Newburgh',
								'address'     => "49 Route 17K\nNewburgh, New York 12550",
								'mapsLabel'   => 'Open in Google Maps',
								'mapsUrl'     => '#',
								'buttonLabel' => 'Set as Default',
								'buttonUrl'   => '#',
							),
						),
					),
					'mobilePhoneNumber'               => array(
						'type'    => 'text',
						'label'   => 'Mobile Phone Label',
						'default' => '845-374-3969',
					),
					'mobilePhoneUrl'                  => array(
						'type'    => 'text',
						'label'   => 'Mobile Phone URL',
						'default' => 'tel:+18453743969',
					),
					'mobilePhonePosition'             => array(
						'type'    => 'select',
						'label'   => 'Mobile Phone Position',
						'default' => 'bottom',
						'options' => array(
							'Top'    => 'top',
							'Bottom' => 'bottom',
							'Hidden' => 'hidden',
						),
					),
					'mobileMenuBackground'            => array(
						'type'    => 'color',
						'label'   => 'Mobile Menu Background',
						'default' => '#27357A',
					),
					'mobileMenuTextColor'             => array(
						'type'    => 'color',
						'label'   => 'Mobile Menu Text Color',
						'default' => '#FFFFFF',
					),
					'mobileMenuDividerColor'          => array(
						'type'    => 'color',
						'label'   => 'Mobile Menu Divider Color',
						'default' => '#3C4A93',
					),
					'mobileMenuTopBackground'         => array(
						'type'    => 'color',
						'label'   => 'Mobile Menu Top Background',
						'default' => '#FFFFFF',
					),
					'mobileMenuTopTextColor'          => array(
						'type'    => 'color',
						'label'   => 'Mobile Menu Top Text Color',
						'default' => '#1F2A44',
					),
					'mobileMenuButtonBackground'      => array(
						'type'    => 'color',
						'label'   => 'Mobile Phone Button Background',
						'default' => '#3C6FB2',
					),
					'mobileMenuButtonTextColor'       => array(
						'type'    => 'color',
						'label'   => 'Mobile Phone Button Text Color',
						'default' => '#FFFFFF',
					),
					'mobileFindModalBackground'       => array(
						'type'    => 'color',
						'label'   => 'Find Modal Background',
						'default' => '#FFFFFF',
					),
					'mobileFindModalTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Find Modal Text Color',
						'default' => '#1F2A44',
					),
					'mobileFindModalLinkColor'        => array(
						'type'    => 'color',
						'label'   => 'Find Modal Link Color',
						'default' => '#2C3D87',
					),
					'mobileFindModalMapsLinkColor'    => array(
						'type'    => 'color',
						'label'   => 'Find Modal Maps Link Color',
						'default' => '#2C3D87',
					),
					'mobileFindModalButtonBackground' => array(
						'type'    => 'color',
						'label'   => 'Find Modal Button Background',
						'default' => '#2C3D87',
					),
					'mobileFindModalButtonText'       => array(
						'type'    => 'color',
						'label'   => 'Find Modal Button Text Color',
						'default' => '#FFFFFF',
					),
					'topBarBackground'                => array(
						'type'    => 'color',
						'label'   => 'Top Bar Background',
						'default' => '#EFEFF1',
					),
					'topBarTextColor'                 => array(
						'type'    => 'color',
						'label'   => 'Top Bar Text Color',
						'default' => '#2C6B34',
					),
					'logoColor'                       => array(
						'type'    => 'color',
						'label'   => 'Logo Text Color',
						'default' => '#111827',
					),
					'iconBackground'                  => array(
						'type'    => 'color',
						'label'   => 'Action Icon Background',
						'default' => '#2C6B34',
					),
					'iconColor'                       => array(
						'type'    => 'color',
						'label'   => 'Action Icon Color',
						'default' => '#FFFFFF',
					),
					'mainNavBackground'               => array(
						'type'    => 'color',
						'label'   => 'Main Nav Background',
						'default' => '#2C6B34',
					),
					'mainNavTextColor'                => array(
						'type'    => 'color',
						'label'   => 'Main Nav Text Color',
						'default' => '#FFFFFF',
					),
					'mainNavBorderColor'              => array(
						'type'    => 'color',
						'label'   => 'Main Nav Divider Color',
						'default' => '#5E8A62',
					),
					'activeNavBackground'             => array(
						'type'    => 'color',
						'label'   => 'Active Nav Background',
						'default' => '#FFFFFF',
					),
					'activeNavTextColor'              => array(
						'type'    => 'color',
						'label'   => 'Active Nav Text Color',
						'default' => '#111827',
					),
					'megaBackground'                  => array(
						'type'    => 'color',
						'label'   => 'Mega Menu Background',
						'default' => '#FFFFFF',
					),
					'megaHeadingColor'                => array(
						'type'    => 'color',
						'label'   => 'Mega Menu Heading Color',
						'default' => '#111827',
					),
					'megaLinkColor'                   => array(
						'type'    => 'color',
						'label'   => 'Mega Menu Link Color',
						'default' => '#374151',
					),
					'megaBorderColor'                 => array(
						'type'    => 'color',
						'label'   => 'Mega Menu Border Color',
						'default' => '#E5E7EB',
					),
					'megaBrandImageSize'              => array(
						'type'    => 'slider',
						'label'   => 'Mega Brand Image Size',
						'default' => 100,
						'min'     => 30,
						'max'     => 100,
						'unit'    => '%',
					),
					'topBarHeight'                    => array(
						'type'    => 'slider',
						'label'   => 'Top Bar Height',
						'default' => 72,
						'min'     => 52,
						'max'     => 110,
					),
					'topBarSidePadding'               => array(
						'type'    => 'slider',
						'label'   => 'Top Row Side Padding',
						'default' => 15,
						'min'     => 15,
						'max'     => 60,
					),
					'menuBarHeight'                   => array(
						'type'    => 'slider',
						'label'   => 'Menu Bar Height',
						'default' => 30,
						'min'     => 24,
						'max'     => 86,
					),
					'megaMinHeight'                   => array(
						'type'    => 'slider',
						'label'   => 'Mega Menu Min Height',
						'default' => 160,
						'min'     => 100,
						'max'     => 360,
					),
					'paddingX'                        => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                         => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'header-showcase-mega',
				'name'           => 'Showcase Mega Header',
				'category'       => 'content',
				'template_scope' => 'header',
				'icon'           => 'layout-template',
				'description'    => 'Two-tier retail header with editorial mega menus, locations, calls, and a nested mobile drawer',
				'settings'       => array(
					'promoText'            => array( 'type' => 'text', 'label' => 'Promo Text', 'default' => 'Seasonal Event', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'promoUrl'             => array( 'type' => 'text', 'label' => 'Promo URL', 'default' => '#', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'logoText'             => array( 'type' => 'text', 'label' => 'Logo Text', 'default' => 'YOUR BRAND', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'logoImage'            => array( 'type' => 'image', 'label' => 'Logo Image', 'default' => '', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'logoAlt'              => array( 'type' => 'text', 'label' => 'Logo Alt Text', 'default' => 'Site logo', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'homeUrl'              => array( 'type' => 'text', 'label' => 'Logo URL', 'default' => '/', 'section' => 'brand', 'sectionTitle' => 'Brand & Promo' ),
					'navigation'           => array(
						'type'    => 'showcase_header_navigation',
						'label'   => 'Header Navigation',
						'section' => 'navigation',
						'sectionTitle' => 'Navigation & Panels',
						'default' => array(
							'utility' => array(
								array( 'label' => 'Services', 'url' => '#', 'icon' => 'settings', 'kind' => 'mega', 'links' => array(), 'panel' => $this->showcase_header_panel_defaults( 'Our Services' ) ),
								array( 'label' => 'Resources', 'url' => '#', 'icon' => 'book', 'kind' => 'dropdown', 'links' => array( array( 'label' => 'Blog', 'url' => '#' ), array( 'label' => 'Financing', 'url' => '#' ), array( 'label' => 'Owner Manuals', 'url' => '#' ) ), 'panel' => $this->showcase_header_panel_defaults() ),
								array( 'label' => 'Locations', 'url' => '#', 'icon' => 'map-pin', 'kind' => 'locations', 'links' => array(), 'panel' => $this->showcase_header_panel_defaults() ),
								array( 'label' => 'Call Us', 'url' => 'tel:', 'icon' => 'phone', 'kind' => 'calls', 'links' => array(), 'panel' => $this->showcase_header_panel_defaults() ),
							),
							'menu' => array(
								array( 'label' => 'Hot Tubs', 'url' => '#', 'hasMega' => true, 'panel' => $this->showcase_header_panel_defaults( 'Shop Our Collection' ) ),
								array( 'label' => 'Swim', 'url' => '#', 'hasMega' => true, 'panel' => $this->showcase_header_panel_defaults( 'Explore Swim & Pools' ) ),
								array( 'label' => 'Saunas', 'url' => '#', 'hasMega' => false, 'panel' => $this->showcase_header_panel_defaults() ),
								array( 'label' => 'Cold Plunge', 'url' => '#', 'hasMega' => false, 'panel' => $this->showcase_header_panel_defaults() ),
							),
							'locations' => array( array( 'name' => 'Main Showroom', 'image' => '', 'address' => '123 Main Street', 'hours' => "Mon-Sat: 10:00AM - 6:00PM\nSunday: 11:00AM - 4:00PM", 'phone' => '(555) 555-0100', 'phoneUrl' => 'tel:+15555550100', 'directionsUrl' => '#' ) ),
							'calls' => array( array( 'label' => 'Sales & Service', 'url' => 'tel:+15555550100' ) ),
						),
					),
					'specialButtonText'    => array( 'type' => 'text', 'label' => 'Special Button Text', 'default' => 'Specials', 'section' => 'cta', 'sectionTitle' => 'Call to Action' ),
					'specialButtonUrl'     => array( 'type' => 'text', 'label' => 'Special Button URL', 'default' => '#', 'section' => 'cta', 'sectionTitle' => 'Call to Action' ),
					'mobileLocationsLabel' => array( 'type' => 'text', 'label' => 'Locations Label', 'default' => 'Locations', 'section' => 'mobileControls', 'sectionTitle' => 'Mobile Controls' ),
					'mobileCallLabel'      => array( 'type' => 'text', 'label' => 'Call Label', 'default' => 'Call Us', 'section' => 'mobileControls', 'sectionTitle' => 'Mobile Controls' ),
					'mobileShowSearch'     => array( 'type' => 'toggle', 'label' => 'Show Search', 'default' => true, 'section' => 'mobileControls', 'sectionTitle' => 'Mobile Controls' ),
					'mobileBackground'     => array( 'type' => 'color', 'label' => 'Drawer Background', 'default' => '#2F73B6', 'section' => 'mobileStyle', 'sectionTitle' => 'Mobile Colors' ),
					'mobileTextColor'      => array( 'type' => 'color', 'label' => 'Drawer Text', 'default' => '#FFFFFF', 'section' => 'mobileStyle', 'sectionTitle' => 'Mobile Colors' ),
					'searchUrl'            => array( 'type' => 'text', 'label' => 'Search URL', 'default' => '/?s=', 'section' => 'mobileControls', 'sectionTitle' => 'Mobile Controls' ),
					'utilityBackground'    => array( 'type' => 'color', 'label' => 'Utility Background', 'default' => '#2F73B6', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'utilityTextColor'     => array( 'type' => 'color', 'label' => 'Utility Text', 'default' => '#FFFFFF', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'navBackground'        => array( 'type' => 'color', 'label' => 'Navigation Background', 'default' => '#0D0D0D', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'navTextColor'         => array( 'type' => 'color', 'label' => 'Navigation Text', 'default' => '#FFFFFF', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'accentColor'          => array( 'type' => 'color', 'label' => 'Accent Color', 'default' => '#2F73B6', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'panelBackground'      => array( 'type' => 'color', 'label' => 'Panel Background', 'default' => '#FFFFFF', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'panelTextColor'       => array( 'type' => 'color', 'label' => 'Panel Text', 'default' => '#171717', 'section' => 'colors', 'sectionTitle' => 'Header Colors' ),
					'logoWidth'            => array( 'type' => 'slider', 'label' => 'Logo Width', 'default' => 250, 'min' => 80, 'max' => 380, 'section' => 'dimensions', 'sectionTitle' => 'Dimensions' ),
					'paddingX'             => array( 'type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100 ),
					'marginY'              => array( 'type' => 'slider', 'label' => 'Vertical Margin', 'default' => 0, 'min' => 0, 'max' => 100 ),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'header-cutout-mega',
				'name'           => 'Header Cutout Mega',
				'category'       => 'content',
				'template_scope' => 'header',
				'icon'           => 'layout-template',
				'description'    => 'Top utility strip, cutout image logo overlap, centered nav shell, and mega menu',
				'settings'       => array(
					'utilityLinks'         => array(
						'type'    => 'simple_links',
						'label'   => 'Top Utility Links',
						'default' => array(
							array(
								'label' => 'CONTACT',
								'url'   => '#',
							),
							array(
								'label' => 'TELEFOON 0180 - 421399',
								'url'   => '#',
							),
							array(
								'label' => 'ADRES',
								'url'   => '#',
							),
						),
					),
					'logoImage'            => array(
						'type'    => 'image',
						'label'   => 'Logo Image',
						'default' => '',
					),
					'logoAlt'              => array(
						'type'    => 'text',
						'label'   => 'Logo Alt Text',
						'default' => 'Site logo',
					),
					'homeUrl'              => array(
						'type'    => 'text',
						'label'   => 'Logo URL',
						'default' => '/',
					),
					'showSearch'           => array(
						'type'    => 'toggle',
						'label'   => 'Show Search Icon',
						'default' => true,
					),
					'searchUrl'            => array(
						'type'     => 'text',
						'label'    => 'Search URL',
						'default'  => '/?s=',
						'showWhen' => array( 'showSearch' => true ),
					),
					'menuItems'            => array(
						'type'    => 'mega_menu',
						'label'   => 'Primary Menu + Mega Menu Content',
						'default' => array(
							array(
								'label'   => 'GROND',
								'url'     => '#',
								'hasMega' => true,
								'columns' => array(
									array(
										'heading'      => 'Merken',
										'imageLinks'   => true,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Bekijk alle producten',
												'url'   => '#',
												'image' => '',
											),
											array(
												'label' => 'STIHL',
												'url'   => '#',
												'image' => '',
											),
											array(
												'label' => 'Honda',
												'url'   => '#',
												'image' => '',
											),
											array(
												'label' => 'Orec',
												'url'   => '#',
												'image' => '',
											),
											array(
												'label' => 'Ferrari',
												'url'   => '#',
												'image' => '',
											),
											array(
												'label' => 'Stiga',
												'url'   => '#',
												'image' => '',
											),
										),
									),
									array(
										'heading'      => 'Maaien',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Grasmaaiers',
												'url'   => '#',
											),
											array(
												'label' => 'Robotmaaiers',
												'url'   => '#',
											),
											array(
												'label' => 'Trimmers',
												'url'   => '#',
											),
											array(
												'label' => 'Mulchmaaiers',
												'url'   => '#',
											),
										),
									),
									array(
										'heading'      => 'Grond Bewerken',
										'imageLinks'   => false,
										'imageColumns' => 2,
										'links'        => array(
											array(
												'label' => 'Drukspuiten',
												'url'   => '#',
											),
											array(
												'label' => 'Grondboren',
												'url'   => '#',
											),
											array(
												'label' => 'Tuinfrezen',
												'url'   => '#',
											),
											array(
												'label' => 'Verticuteermachines',
												'url'   => '#',
											),
										),
									),
								),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'ZAGEN',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'OPRUIMEN',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'DRAGERS',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'STROOM',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'GEREEDSCHAP',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'ACCESSOIRES',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
							array(
								'label'   => 'SERVICE',
								'url'     => '#',
								'hasMega' => false,
								'columns' => array(),
								'banner'  => array(
									'title' => '',
									'image' => '',
									'url'   => '#',
								),
							),
						),
					),
					'topStripBackground'   => array(
						'type'    => 'color',
						'label'   => 'Top Strip Background',
						'default' => '#86BF25',
					),
					'topStripTextColor'    => array(
						'type'    => 'color',
						'label'   => 'Top Strip Text Color',
						'default' => '#111111',
					),
					'menuShellBackground'  => array(
						'type'    => 'color',
						'label'   => 'Menu Background',
						'default' => '#EBEBEB',
					),
					'menuTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Menu Text Color',
						'default' => '#111111',
					),
					'menuDividerColor'     => array(
						'type'    => 'color',
						'label'   => 'Menu Divider Color',
						'default' => '#D9D9D9',
					),
					'activeMenuBackground' => array(
						'type'    => 'color',
						'label'   => 'Active Menu Background',
						'default' => '#F5F5F5',
					),
					'activeMenuTextColor'  => array(
						'type'    => 'color',
						'label'   => 'Active Menu Text Color',
						'default' => '#4F8E2F',
					),
					'megaBackground'       => array(
						'type'    => 'color',
						'label'   => 'Mega Menu Background',
						'default' => '#F0F0F0',
					),
					'megaHeadingColor'     => array(
						'type'    => 'color',
						'label'   => 'Mega Heading Color',
						'default' => '#111111',
					),
					'megaLinkColor'        => array(
						'type'    => 'color',
						'label'   => 'Mega Link Color',
						'default' => '#4F8E2F',
					),
					'megaBorderColor'      => array(
						'type'    => 'color',
						'label'   => 'Mega Border Color',
						'default' => '#D8D8D8',
					),
					'topStripHeight'       => array(
						'type'    => 'slider',
						'label'   => 'Top Strip Height',
						'default' => 30,
						'min'     => 28,
						'max'     => 60,
					),
					'logoWidth'            => array(
						'type'    => 'slider',
						'label'   => 'Logo Width',
						'default' => 248,
						'min'     => 180,
						'max'     => 420,
					),
					'logoHeight'           => array(
						'type'    => 'slider',
						'label'   => 'Logo Height',
						'default' => 124,
						'min'     => 90,
						'max'     => 220,
					),
					'menuBarHeight'        => array(
						'type'    => 'slider',
						'label'   => 'Menu Bar Height',
						'default' => 52,
						'min'     => 30,
						'max'     => 90,
					),
					'megaMinHeight'        => array(
						'type'    => 'slider',
						'label'   => 'Mega Menu Min Height',
						'default' => 180,
						'min'     => 100,
						'max'     => 420,
					),
					'paddingX'             => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'              => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'             => 'footer-dealers',
				'name'           => 'Footer Dealers',
				'category'       => 'content',
				'template_scope' => 'footer',
				'icon'           => 'layout-template',
				'description'    => 'Dealer/location footer with contact details, opening hours, and legal bar',
				'settings'       => array(
					'dealers'               => array(
						'type'    => 'footer_dealers',
						'label'   => 'Dealer Cards',
						'default' => array(
							array(
								'name'            => 'Dealer Toonzaal 1 ~ Amsterdam',
								'mapImage'        => '',
								'photoImage'      => '',
								'addressLine1'    => 'Nieuwe Prinsengracht',
								'addressLine2'    => 'Amsterdam, Netherlands 1018ED',
								'phone'           => '0255-555555',
								'directionsLabel' => 'Routebeschrijving',
								'directionsUrl'   => '#',
								'hoursLabel'      => 'Openingstijden:',
								'day1'            => 'ma - do',
								'hours1'          => '08:00 - 17:00',
								'day2'            => 'vr',
								'hours2'          => '08:00 - 16:30',
							),
							array(
								'name'            => 'Dealer Toonzaal 2 ~ Utrecht',
								'mapImage'        => '',
								'photoImage'      => '',
								'addressLine1'    => 'Vleutenseweg, 3532 HP',
								'addressLine2'    => 'Utrecht, Netherlands 1018ED',
								'phone'           => '0255-555555',
								'directionsLabel' => 'Routebeschrijving',
								'directionsUrl'   => '#',
								'hoursLabel'      => 'Openingstijden:',
								'day1'            => 'ma - do',
								'hours1'          => '07:30 - 17:30',
								'day2'            => 'vr',
								'hours2'          => '08:00 - 16:30',
							),
						),
					),
					'legalLinks'            => array(
						'type'    => 'simple_links',
						'label'   => 'Legal Links',
						'default' => array(
							array(
								'label' => 'Privacybeleid',
								'url'   => '#',
							),
							array(
								'label' => 'Juridische disclaimer',
								'url'   => '#',
							),
						),
					),
					'showFacebook'          => array(
						'type'    => 'toggle',
						'label'   => 'Show Facebook Button',
						'default' => true,
					),
					'facebookUrl'           => array(
						'type'    => 'text',
						'label'   => 'Facebook URL',
						'default' => '#',
					),
					'copyrightText'         => array(
						'type'    => 'text',
						'label'   => 'Copyright Text',
						'default' => '© 2026 Naam dealer. Alle rechten voorbehouden.',
					),
					'creditText'            => array(
						'type'    => 'text',
						'label'   => 'Credit Text',
						'default' => 'Site door DesignStudio',
					),
					'backgroundColor'       => array(
						'type'    => 'color',
						'label'   => 'Main Background',
						'default' => '#14171B',
					),
					'bottomBarColor'        => array(
						'type'    => 'color',
						'label'   => 'Bottom Bar Background',
						'default' => '#33363B',
					),
					'headingColor'          => array(
						'type'    => 'color',
						'label'   => 'Heading Color',
						'default' => '#FFFFFF',
					),
					'textColor'             => array(
						'type'    => 'color',
						'label'   => 'Text Color',
						'default' => '#F8F9FB',
					),
					'accentColor'           => array(
						'type'    => 'color',
						'label'   => 'Accent Color',
						'default' => '#8FCE7A',
					),
					'socialBackgroundColor' => array(
						'type'    => 'color',
						'label'   => 'Social Icon Background',
						'default' => '#4267B2',
					),
					'socialIconColor'       => array(
						'type'    => 'color',
						'label'   => 'Social Icon Color',
						'default' => '#FFFFFF',
					),
					'contentMaxWidth'       => array(
						'type'    => 'slider',
						'label'   => 'Content Max Width',
						'default' => 1280,
						'min'     => 900,
						'max'     => 1800,
					),
					'mapHeight'             => array(
						'type'    => 'slider',
						'label'   => 'Map Area Height',
						'default' => 230,
						'min'     => 160,
						'max'     => 360,
					),
					'cardGap'               => array(
						'type'    => 'slider',
						'label'   => 'Card Gap',
						'default' => 110,
						'min'     => 20,
						'max'     => 240,
					),
					'padding'               => array(
						'type'    => 'slider',
						'label'   => 'Top Section Padding',
						'default' => 72,
						'min'     => 24,
						'max'     => 140,
					),
					'bottomPadding'         => array(
						'type'    => 'slider',
						'label'   => 'Bottom Section Padding',
						'default' => 42,
						'min'     => 20,
						'max'     => 100,
					),
					'contentPaddingX'       => array(
						'type'    => 'slider',
						'label'   => 'Content Horizontal Padding',
						'default' => 24,
						'min'     => 0,
						'max'     => 80,
					),
					'paddingX'              => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'               => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'cta-banner',
				'name'        => 'CTA Banner',
				'category'    => 'marketing',
				'icon'        => 'megaphone',
				'description' => 'Call-to-action banner',
				'settings'    => array(
					'title'                  => array(
						'type'    => 'text',
						'label'   => 'Title',
						'default' => 'Get 20% Off Your First Order',
					),
					'subtitle'               => array(
						'type'    => 'text',
						'label'   => 'Subtitle',
						'default' => 'Sign up for our newsletter',
					),
					'buttonText'             => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Shop Now',
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Button URL',
						'default'  => '/shop',
						'showWhen' => array( 'buttonAction' => 'link' ),
					),
					'buttonAction'           => array(
						'type'    => 'select',
						'label'   => 'Button Action',
						'default' => 'link',
						'options' => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
						),
					),
					'buttonModalLayout'      => array(
						'type'     => 'select',
						'label'    => 'Modal Layout',
						'default'  => 'center',
						'options'  => array(
							'Center'       => 'center',
							'Right Drawer' => 'drawer',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContentType' => array(
						'type'     => 'select',
						'label'    => 'Modal Content Type',
						'default'  => 'wysiwyg',
						'options'  => array(
							'WYSIWYG'   => 'wysiwyg',
							'HTML'      => 'html',
							'Shortcode' => 'shortcode',
						),
						'showWhen' => array( 'buttonAction' => 'modal' ),
					),
					'buttonModalContent'     => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'wysiwyg',
						),
					),
					'buttonModalHtml'        => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'html',
						),
					),
					'buttonModalShortcode'   => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'buttonAction'           => 'modal',
							'buttonModalContentType' => 'shortcode',
						),
					),
					'backgroundColor'        => array(
						'type'    => 'color',
						'label'   => 'Background Color',
						'default' => '#1E40AF',
					),
					'textColor'              => array(
						'type'    => 'color',
						'label'   => 'Subtitle Color',
						'default' => '#FFFFFF',
					),
					'titleColor'             => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#FFFFFF',
					),
					'buttonColor'            => array(
						'type'    => 'color',
						'label'   => 'Button Background',
						'default' => '#FFFFFF',
					),
					'buttonTextColor'        => array(
						'type'    => 'color',
						'label'   => 'Button Text Color',
						'default' => '#1E40AF',
					),
					'padding'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Padding',
						'default' => 60,
						'min'     => 20,
						'max'     => 120,
					),
					'paddingX'               => array(
						'type'    => 'slider',
						'label'   => 'Horizontal Padding',
						'default' => 0,
						'min'     => 0,
						'max'     => 100,
					),
					'marginY'                => array(
						'type'    => 'slider',
						'label'   => 'Vertical Margin',
						'default' => 25,
						'min'     => 0,
						'max'     => 100,
					),
				),
			)
		);

		$this->register_landing_page_blocks();
	}

	/**
	 * Standard image/video media controls for a landing block visual area.
	 * When the type is "mockup" (default) the block renders its built-in illustration.
	 *
	 * @param string $prefix Settings key prefix (e.g. "media", "feature1").
	 * @param string $label  Human label for the control group.
	 * @return array
	 */
	private function landing_media_settings( $prefix = 'media', $label = 'Visual' ) {
		return array(
			$prefix . 'Type' => array(
				'type'    => 'select',
				'label'   => $label . ' source',
				'section' => 'media',
				'default' => 'mockup',
				'options' => array(
					'Built-in mockup' => 'mockup',
					'Image'           => 'image',
					'Video'           => 'video',
				),
			),
			$prefix . 'Image'     => array(
				'type'     => 'image',
				'label'    => $label . ' image',
				'section'  => 'media',
				'default'  => '',
				'showWhen' => array( $prefix . 'Type' => 'image' ),
			),
			$prefix . 'Video'     => array(
				'type'     => 'video',
				'label'    => $label . ' video (file or YouTube/Vimeo)',
				'section'  => 'media',
				'default'  => '',
				'showWhen' => array( $prefix . 'Type' => 'video' ),
			),
		);
	}

	/**
	 * Standard background / text / accent color + spacing controls for a landing block.
	 *
	 * @param array $defaults Optional default hex values keyed by background/text/accent.
	 * @return array
	 */
	private function landing_style_settings( $defaults = array() ) {
		return array(
			'backgroundColor' => array( 'type' => 'color', 'label' => 'Background color', 'section' => 'style', 'default' => $defaults['background'] ?? '' ),
			'textColor'       => array( 'type' => 'color', 'label' => 'Text color', 'section' => 'style', 'default' => $defaults['text'] ?? '' ),
			'accentColor'     => array( 'type' => 'color', 'label' => 'Accent color', 'section' => 'style', 'default' => $defaults['accent'] ?? '' ),
			'eyebrowColor'    => array( 'type' => 'color', 'label' => 'Eyebrow text color', 'section' => 'style', 'default' => $defaults['eyebrow'] ?? '' ),
			'paddingX'        => array( 'type' => 'slider', 'label' => 'Horizontal padding', 'section' => 'style', 'default' => 0, 'min' => 0, 'max' => 80 ),
			'marginY'         => array( 'type' => 'slider', 'label' => 'Vertical margin', 'section' => 'style', 'default' => 0, 'min' => 0, 'max' => 80 ),
		);
	}

	/**
	 * Curated icon options shared by landing blocks that expose an editable icon.
	 *
	 * @return array Map of "Label" => "kebab-name".
	 */
	private function landing_icon_options() {
		return array(
			'Sparkles'      => 'sparkles',
			'Shield'        => 'shield-check',
			'Lock'          => 'lock',
			'Fingerprint'   => 'fingerprint',
			'Code'          => 'code',
			'Paintbrush'    => 'paintbrush',
			'Palette'       => 'palette',
			'Layers'        => 'layers',
			'Layout'        => 'layout',
			'Columns'       => 'columns',
			'Grid'          => 'grid',
			'Briefcase'     => 'briefcase',
			'Store'         => 'store',
			'Users'         => 'users',
			'Mail'          => 'mail',
			'Form'          => 'form-input',
			'Bell'          => 'bell',
			'Megaphone'     => 'megaphone',
			'Clock'         => 'clock',
			'Calendar'      => 'calendar',
			'Search'        => 'search',
			'Filter'        => 'filter',
			'Bolt'          => 'zap',
			'Rocket'        => 'rocket',
			'Check'         => 'check',
			'Star'          => 'star',
			'Heart'         => 'heart',
			'Globe'         => 'globe',
			'Monitor'       => 'monitor',
			'Phone'         => 'smartphone',
			'Document'      => 'file-text',
			'Settings'      => 'settings',
			'Click'         => 'mouse-pointer',
			'Panel'         => 'panel-top',
			'Wand'          => 'wand',
			'Gauge'         => 'gauge',
			'Boxes'         => 'boxes',
		);
	}

	/**
	 * Register the page-scoped marketing blocks used by the DSFlow product landing page.
	 */
	private function register_landing_page_blocks() {
		$this->register_block(
			array(
				'id'          => 'landing-progress-header',
				'name'        => 'Site Header',
				'category'    => 'marketing',
				'icon'        => 'layout-template',
				'description' => 'Sticky navigation header with optional reading progress',
				'settings'    => array_merge(
					array(
						'variant'              => array( 'type' => 'select', 'label' => 'Header style', 'section' => 'content', 'default' => 'progress', 'options' => array( 'Progress bar' => 'progress', 'Minimal' => 'minimal', 'Centered nav' => 'centered', 'Transparent' => 'transparent' ) ),
						'buttonColor'          => array( 'type' => 'color', 'label' => 'Button background', 'section' => 'style', 'default' => '' ),
						'buttonTextColor'      => array( 'type' => 'color', 'label' => 'Button text color', 'section' => 'style', 'default' => '' ),
						'brandText'            => array( 'type' => 'text', 'label' => 'Brand text', 'section' => 'content', 'default' => '' ),
						'logoImage'            => array( 'type' => 'image', 'label' => 'Logo image', 'section' => 'content', 'default' => '' ),
						'navLinks'             => array( 'type' => 'simple_links', 'label' => 'Navigation links', 'section' => 'content', 'default' => array(
							array( 'label' => 'Why DSFlow', 'url' => '#why-dsflow' ),
							array( 'label' => 'Blocks', 'url' => '#blocks' ),
							array( 'label' => 'WooCommerce', 'url' => '#woocommerce' ),
							array( 'label' => 'Forms & Growth', 'url' => '#engagement' ),
							array( 'label' => 'Security', 'url' => '#security' ),
							array( 'label' => 'For Agencies', 'url' => '#audience' ),
						) ),
						'showAnnouncement'     => array( 'type' => 'toggle', 'label' => 'Show Announcement', 'section' => 'content', 'default' => false ),
						'announcementText'     => array( 'type' => 'text', 'label' => 'Announcement', 'section' => 'content', 'default' => 'DesignStudio Flow is built for modern WordPress teams.', 'showWhen' => array( 'showAnnouncement' => true ) ),
						'announcementLinkText' => array( 'type' => 'text', 'label' => 'Announcement Link Text', 'section' => 'content', 'default' => 'See what is new', 'showWhen' => array( 'showAnnouncement' => true ) ),
						'announcementUrl'      => array( 'type' => 'text', 'label' => 'Announcement URL', 'section' => 'content', 'default' => '#blocks', 'showWhen' => array( 'showAnnouncement' => true ) ),
						'homeUrl'              => array( 'type' => 'text', 'label' => 'Logo URL', 'section' => 'content', 'default' => '#why-dsflow' ),
						'docsText'             => array( 'type' => 'text', 'label' => 'Documentation Label', 'section' => 'buttons', 'default' => 'Documentation' ),
						'docsUrl'              => array( 'type' => 'text', 'label' => 'Documentation URL', 'section' => 'buttons', 'default' => '#workflow' ),
						'ctaText'              => array( 'type' => 'text', 'label' => 'CTA Label', 'section' => 'buttons', 'default' => 'Get DSFlow' ),
						'ctaUrl'               => array( 'type' => 'text', 'label' => 'CTA URL', 'section' => 'buttons', 'default' => '#get-dsflow' ),
					),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-hero',
				'name'        => 'Landing Page Hero',
				'category'    => 'content',
				'icon'        => 'layout',
				'description' => 'Large split hero with calls to action and optional media',
				'settings'    => array_merge(
					array(
						'eyebrow'         => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'THE VISUAL BUILDER WORDPRESS DESERVES' ),
						'eyebrowLineColor' => array( 'type' => 'color', 'label' => 'Eyebrow line color', 'section' => 'style', 'default' => '' ),
						'buttonColor'      => array( 'type' => 'color', 'label' => 'Primary button background', 'section' => 'style', 'default' => '' ),
						'buttonTextColor'  => array( 'type' => 'color', 'label' => 'Primary button text color', 'section' => 'style', 'default' => '' ),
						'title'         => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Build freely. Stay beautifully consistent.' ),
						'description'   => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'DesignStudio Flow gives teams the freedom to create ambitious WordPress pages without losing the design system, content model, or publishing workflow beneath them.' ),
						'primaryText'   => array( 'type' => 'text', 'label' => 'Primary Button', 'section' => 'buttons', 'default' => 'Explore the block library' ),
						'primaryUrl'    => array( 'type' => 'text', 'label' => 'Primary URL', 'section' => 'buttons', 'default' => '#blocks' ),
						'secondaryText' => array( 'type' => 'text', 'label' => 'Secondary Button', 'section' => 'buttons', 'default' => 'See how it works' ),
						'secondaryUrl'  => array( 'type' => 'text', 'label' => 'Secondary URL', 'section' => 'buttons', 'default' => '#editor' ),
						'note'          => array( 'type' => 'text', 'label' => 'Supporting Note', 'section' => 'content', 'default' => 'Built inside WordPress. Designed around secure, structured blocks.' ),
						'align'         => array( 'type' => 'select', 'label' => 'Text alignment', 'section' => 'content', 'default' => 'left', 'options' => array( 'Left' => 'left', 'Center' => 'center' ) ),
						'mediaPosition' => array( 'type' => 'select', 'label' => 'Visual position', 'section' => 'content', 'default' => 'right', 'options' => array( 'Right' => 'right', 'Left' => 'left' ) ),
					),
					$this->landing_media_settings( 'media', 'Hero visual' ),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-block-explorer',
				'name'        => 'Content Carousel',
				'category'    => 'content',
				'icon'        => 'grid-3x3',
				'description' => 'Filterable horizontal carousel of visual content cards',
				'settings'    => array_merge(
					array(
						'eyebrow'     => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'A LIBRARY WITH A POINT OF VIEW' ),
						'eyebrowLineColor' => array( 'type' => 'color', 'label' => 'Eyebrow line color', 'section' => 'style', 'default' => '' ),
						'title'       => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Start with structure. Finish with something original.' ),
						'description' => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Each block solves a real page-building problem, then gives your team the right amount of creative control.' ),
						'footnote'    => array( 'type' => 'text', 'label' => 'Footnote', 'section' => 'content', 'default' => 'New blocks inherit the same editing, theme, responsive, and frontend rendering workflow.' ),
						'items'       => array( 'type' => 'gallery_items', 'label' => 'Gallery items', 'section' => 'items', 'default' => array() ),
					),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-block-ready',
				'name'        => 'Design-Ready Block',
				'category'    => 'content',
				'icon'        => 'boxes',
				'description' => 'Shows a fully designed block dropping in, with only the content left to edit',
				'settings'    => array_merge(
					array(
						'eyebrow'     => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'DESIGN INCLUDED' ),
						'eyebrowLineColor' => array( 'type' => 'color', 'label' => 'Eyebrow line color', 'section' => 'style', 'default' => '' ),
						'title'       => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Add a block. The design is already done.' ),
						'description' => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Every block ships fully designed and responsive. Drop one onto the page, swap in your own words and images, and publish — no layout work, no CSS, nothing to wire up.' ),
						'step1Title'  => array( 'type' => 'text', 'label' => 'Step 1 title', 'section' => 'steps', 'default' => 'Pick a block' ),
						'step1Text'   => array( 'type' => 'textarea', 'label' => 'Step 1 text', 'section' => 'steps', 'default' => 'Choose from a library of purpose-built sections made for real pages.' ),
						'step2Title'  => array( 'type' => 'text', 'label' => 'Step 2 title', 'section' => 'steps', 'default' => 'It lands fully styled' ),
						'step2Text'   => array( 'type' => 'textarea', 'label' => 'Step 2 text', 'section' => 'steps', 'default' => 'Spacing, type, color, and responsive behavior are already handled for you.' ),
						'step3Title'  => array( 'type' => 'text', 'label' => 'Step 3 title', 'section' => 'steps', 'default' => 'Change only the content' ),
						'step3Text'   => array( 'type' => 'textarea', 'label' => 'Step 3 text', 'section' => 'steps', 'default' => 'Edit the copy and images in place, then publish with confidence.' ),
						'note'        => array( 'type' => 'text', 'label' => 'Supporting note', 'section' => 'content', 'default' => 'Every new block inherits the same theme, responsive, and publishing workflow.' ),
						'demoEyebrow' => array( 'type' => 'text', 'label' => 'Demo eyebrow', 'section' => 'demo', 'default' => 'COUNTDOWN TO LAUNCH' ),
						'demoTitle'   => array( 'type' => 'text', 'label' => 'Demo headline', 'section' => 'demo', 'default' => 'Launch day is almost here' ),
						'demoText'    => array( 'type' => 'textarea', 'label' => 'Demo text', 'section' => 'demo', 'default' => 'Be first in line when the doors open.' ),
						'demoButton'  => array( 'type' => 'text', 'label' => 'Demo button', 'section' => 'demo', 'default' => 'Reserve your spot' ),
					),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-product-story',
				'name'        => 'Split Content',
				'category'    => 'content',
				'icon'        => 'columns',
				'description' => 'Reusable text and media section with optional reversed layout',
				'settings'    => array_merge(
					array(
						'variant'       => array( 'type' => 'select', 'label' => 'Fallback mockup', 'section' => 'content', 'default' => 'editor', 'options' => array( 'Editor Experience' => 'editor', 'Theme System' => 'theme', 'WooCommerce' => 'commerce', 'Headers and Footers' => 'layouts', 'Campaign Tools' => 'campaigns' ) ),
						'reverseLayout' => array( 'type' => 'toggle', 'label' => 'Visual on Left', 'section' => 'content', 'default' => false ),
						'eyebrow'       => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'EDIT THE EXPERIENCE' ),
						'eyebrowLineColor' => array( 'type' => 'color', 'label' => 'Eyebrow line color', 'section' => 'style', 'default' => '' ),
						'title'         => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'A visual workflow that still respects the system.' ),
						'description'   => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Work directly with the page, understand every choice, and keep the guardrails that make a site coherent.' ),
						'featureOne'    => array( 'type' => 'text', 'label' => 'Feature One', 'section' => 'content', 'default' => 'Edit the same component visitors receive' ),
						'featureTwo'    => array( 'type' => 'text', 'label' => 'Feature Two', 'section' => 'content', 'default' => 'Responsive controls stay close to the work' ),
						'featureThree'  => array( 'type' => 'text', 'label' => 'Feature Three', 'section' => 'content', 'default' => 'Page and block settings have clear ownership' ),
					),
					$this->landing_media_settings( 'media', 'Story visual' ),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-trust-workflow',
				'name'        => 'Content Showcase',
				'category'    => 'content',
				'icon'        => 'list-checks',
				'description' => 'Flexible pipeline, card grid, or numbered content section',
				'settings'    => array_merge(
					array(
						'variant'     => array( 'type' => 'select', 'label' => 'Section anchor', 'section' => 'content', 'default' => 'seo', 'options' => array( 'SEO Rendering' => 'seo', 'Security' => 'security', 'Audience' => 'audience', 'Workflow' => 'workflow' ) ),
						'layout'      => array( 'type' => 'select', 'label' => 'Layout', 'section' => 'content', 'default' => '', 'options' => array( 'Auto (from anchor)' => '', 'Pipeline' => 'pipeline', 'Grid (dark)' => 'grid-dark', 'Grid (light)' => 'grid-light', 'Numbered steps' => 'numbered' ) ),
						'eyebrow'     => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'VISIBLE TO PEOPLE AND MACHINES' ),
						'title'       => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'A visual page should still be a real WordPress page.' ),
						'description' => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'DesignStudio Flow saves an HTML snapshot alongside the interactive frontend, giving every page useful content before JavaScript takes over.' ),
						'caption'     => array( 'type' => 'text', 'label' => 'Pipeline caption', 'section' => 'content', 'default' => 'Every saved page carries a readable HTML snapshot, so content exists before the interactive layer loads.' ),
						'items'       => array( 'type' => 'icon_items', 'label' => 'Steps / items', 'section' => 'items', 'default' => array() ),
					),
					// Variant-themed block (SEO/Security/Audience/Workflow each set their
					// own scheme, including a dark Security variant), so its colors stay
					// theme/variant-driven rather than pre-filled to fixed values.
					$this->landing_style_settings()
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-engagement-suite',
				'name'        => 'Content Grid',
				'category'    => 'content',
				'icon'        => 'mail',
				'description' => 'Three-part content grid with icons and optional media',
				'settings'    => array_merge(
					array(
						'eyebrow'                 => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'FROM VISIT TO CONVERSATION' ),
						'title'                   => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Build the page. Then help it do something.' ),
						'description'             => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Forms, popups, and notification bars bring the next action into the same visual system as the page around them.' ),
						'formsIcon'               => array( 'type' => 'select', 'label' => 'Feature 1 icon', 'section' => 'feature-1', 'default' => 'form-input', 'options' => $this->landing_icon_options() ),
						'formsLabel'              => array( 'type' => 'text', 'label' => 'Feature 1 label', 'section' => 'feature-1', 'default' => 'FORMS' ),
						'formsTitle'              => array( 'type' => 'text', 'label' => 'Feature 1 title', 'section' => 'feature-1', 'default' => 'Forms that belong to the design.' ),
						'formsDescription'        => array( 'type' => 'textarea', 'label' => 'Feature 1 description', 'section' => 'feature-1', 'default' => 'Build native forms or bring Gravity Forms into Flow, then keep fields, labels, buttons, and responsive behavior visually consistent.' ),
						'formsBullets'            => array( 'type' => 'textarea', 'label' => 'Feature 1 bullets (one per line)', 'section' => 'feature-1', 'default' => "Visual field builder\nWordPress and Gravity Forms\nResponsive, theme-aware styling" ),
						'popupIcon'               => array( 'type' => 'select', 'label' => 'Feature 2 icon', 'section' => 'feature-2', 'default' => 'panel-top', 'options' => $this->landing_icon_options() ),
						'popupLabel'              => array( 'type' => 'text', 'label' => 'Feature 2 label', 'section' => 'feature-2', 'default' => 'POPUPS' ),
						'popupTitle'              => array( 'type' => 'text', 'label' => 'Feature 2 title', 'section' => 'feature-2', 'default' => 'The right message at the right moment.' ),
						'popupDescription'        => array( 'type' => 'textarea', 'label' => 'Feature 2 description', 'section' => 'feature-2', 'default' => 'Create image or content popups with scheduling, delay, sizing, CTA, and repeat-visit controls.' ),
						'notificationIcon'        => array( 'type' => 'select', 'label' => 'Feature 3 icon', 'section' => 'feature-3', 'default' => 'bell', 'options' => $this->landing_icon_options() ),
						'notificationLabel'       => array( 'type' => 'text', 'label' => 'Feature 3 label', 'section' => 'feature-3', 'default' => 'NOTIFICATION BAR' ),
						'notificationTitle'       => array( 'type' => 'text', 'label' => 'Feature 3 title', 'section' => 'feature-3', 'default' => 'One announcement across the whole site.' ),
						'notificationDescription' => array( 'type' => 'textarea', 'label' => 'Feature 3 description', 'section' => 'feature-3', 'default' => 'Publish a site-wide message with clear timing and a visual style connected to the rest of the experience.' ),
					),
					$this->landing_media_settings( 'forms', 'Feature 1 visual' ),
					$this->landing_media_settings( 'popup', 'Feature 2 visual' ),
					$this->landing_media_settings( 'notification', 'Feature 3 visual' ),
					$this->landing_style_settings( array( 'background' => '#0091FF', 'text' => '#FFFFFF', 'accent' => '#0091FF', 'eyebrow' => '#FFFFFF' ) ),
					array(
						'accentColor' => array( 'type' => 'color', 'label' => 'Icon background color', 'section' => 'style', 'default' => '' ),
					)
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-redirect-tool',
				'preset_only' => true,
				'name'        => 'Redirect Tool',
				'category'    => 'content',
				'icon'        => 'milestone',
				'description' => 'Showcase the built-in redirect manager with a live redirects table mockup',
				'settings'    => array_merge(
					array(
						'reverseLayout' => array( 'type' => 'toggle', 'label' => 'Visual on Left', 'section' => 'content', 'default' => false ),
						'eyebrow'       => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'TRAFFIC CONTROL' ),
						'title'         => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Send every old URL exactly where it belongs.' ),
						'description'   => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'A built-in redirect manager handles 301 and 302 rules, CSV import and export, and live hit tracking — no extra plugin required.' ),
						'featureOne'    => array( 'type' => 'text', 'label' => 'Feature One', 'section' => 'content', 'default' => '301 and 302 redirects with one click' ),
						'featureTwo'    => array( 'type' => 'text', 'label' => 'Feature Two', 'section' => 'content', 'default' => 'Bulk import and export by CSV' ),
						'featureThree'  => array( 'type' => 'text', 'label' => 'Feature Three', 'section' => 'content', 'default' => 'Live hit counts for every rule' ),
					),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-mail-tool',
				'preset_only' => true,
				'name'        => 'Mail / SMTP',
				'category'    => 'content',
				'icon'        => 'mail-check',
				'description' => 'Showcase SMTP delivery, one-click OAuth, and the email log with a settings mockup',
				'settings'    => array_merge(
					array(
						'reverseLayout' => array( 'type' => 'toggle', 'label' => 'Visual on Left', 'section' => 'content', 'default' => true ),
						'eyebrow'       => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'DELIVERABILITY' ),
						'title'         => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Email that actually reaches the inbox.' ),
						'description'   => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Route WordPress mail through SendGrid or one-click Gmail and Outlook OAuth, send a test message, and keep a 30-day delivery log — with credentials encrypted at rest.' ),
						'featureOne'    => array( 'type' => 'text', 'label' => 'Feature One', 'section' => 'content', 'default' => 'SendGrid, Gmail, and Outlook transports' ),
						'featureTwo'    => array( 'type' => 'text', 'label' => 'Feature Two', 'section' => 'content', 'default' => 'One-click OAuth and a built-in test email' ),
						'featureThree'  => array( 'type' => 'text', 'label' => 'Feature Three', 'section' => 'content', 'default' => 'Email log with 30-day retention' ),
					),
					$this->landing_style_settings( array( 'background' => '#F7F4ED', 'text' => '#111827', 'accent' => '#0091FF', 'eyebrow' => '#0091FF' ) )
				),
			)
		);

		$this->register_block(
			array(
				'id'          => 'landing-marketing-footer',
				'name'        => 'CTA Footer',
				'category'    => 'footers',
				'icon'        => 'layout-template',
				'description' => 'Call to action with optional brand and navigation columns',
				'settings'    => array_merge(
					array(
						'variant'        => array( 'type' => 'select', 'label' => 'Footer style', 'section' => 'content', 'default' => 'bigcta', 'options' => array( 'Big CTA + columns' => 'bigcta', 'Centered' => 'centered', 'Simple (CTA only)' => 'simple', 'Columns' => 'columns' ) ),
						'eyebrow'        => array( 'type' => 'text', 'label' => 'Eyebrow', 'section' => 'content', 'default' => 'YOUR NEXT PAGE CAN FEEL DIFFERENT' ),
						'title'          => array( 'type' => 'text', 'label' => 'Title', 'section' => 'content', 'default' => 'Give WordPress room to flow.' ),
						'description'    => array( 'type' => 'textarea', 'label' => 'Description', 'section' => 'content', 'default' => 'Create the ambitious page your idea deserves, then hand it to your team with the confidence that the system will hold.' ),
						'primaryText'    => array( 'type' => 'text', 'label' => 'Primary Button', 'section' => 'buttons', 'default' => 'Get DesignStudio Flow' ),
						'primaryUrl'     => array( 'type' => 'text', 'label' => 'Primary URL', 'section' => 'buttons', 'default' => '#' ),
						'secondaryText'  => array( 'type' => 'text', 'label' => 'Secondary Button', 'section' => 'buttons', 'default' => 'Read the documentation' ),
						'secondaryUrl'   => array( 'type' => 'text', 'label' => 'Secondary URL', 'section' => 'buttons', 'default' => '#workflow' ),
						'brandText'      => array( 'type' => 'text', 'label' => 'Brand text', 'section' => 'content', 'default' => '' ),
						'logoImage'      => array( 'type' => 'image', 'label' => 'Logo image', 'section' => 'content', 'default' => '' ),
						'homeUrl'        => array( 'type' => 'text', 'label' => 'Logo URL', 'section' => 'content', 'default' => '#why-dsflow' ),
						'brandStatement' => array( 'type' => 'textarea', 'label' => 'Brand Statement', 'section' => 'content', 'default' => 'A modern visual page builder for WordPress teams who care about freedom, consistency, and the quality of what gets published.' ),
						'copyright'      => array( 'type' => 'text', 'label' => 'Copyright line', 'section' => 'content', 'default' => 'DesignStudio Flow. Built for WordPress.' ),
						'tagline'        => array( 'type' => 'text', 'label' => 'Bottom tagline', 'section' => 'content', 'default' => 'Build freely. Stay beautifully consistent.' ),
						'col1Title'      => array( 'type' => 'text', 'label' => 'Column 1 title', 'section' => 'columns', 'default' => 'Product' ),
						'col1Links'      => array( 'type' => 'simple_links', 'label' => 'Column 1 links', 'section' => 'columns', 'default' => array( array( 'label' => 'Why DSFlow', 'url' => '#why-dsflow' ), array( 'label' => 'Block library', 'url' => '#blocks' ), array( 'label' => 'WooCommerce', 'url' => '#woocommerce' ), array( 'label' => 'Forms & growth', 'url' => '#engagement' ) ) ),
						'col2Title'      => array( 'type' => 'text', 'label' => 'Column 2 title', 'section' => 'columns', 'default' => 'Build' ),
						'col2Links'      => array( 'type' => 'simple_links', 'label' => 'Column 2 links', 'section' => 'columns', 'default' => array( array( 'label' => 'Editor experience', 'url' => '#editor' ), array( 'label' => 'Theme system', 'url' => '#theme' ), array( 'label' => 'Layouts', 'url' => '#layouts' ), array( 'label' => 'Workflow', 'url' => '#workflow' ) ) ),
						'col3Title'      => array( 'type' => 'text', 'label' => 'Column 3 title', 'section' => 'columns', 'default' => 'Trust' ),
						'col3Links'      => array( 'type' => 'simple_links', 'label' => 'Column 3 links', 'section' => 'columns', 'default' => array( array( 'label' => 'SEO rendering', 'url' => '#seo' ), array( 'label' => 'Security', 'url' => '#security' ), array( 'label' => 'For agencies', 'url' => '#audience' ), array( 'label' => 'Documentation', 'url' => '#workflow' ) ) ),
					),
					// Pre-fill the base palette with the footer's real (non-theme-alias)
					// colors so the pickers show real values and stay stable on load.
					$this->landing_style_settings( array( 'background' => '#101B26', 'text' => '#FFFFFF', 'accent' => '#0091FF' ) ),
					array(
						// Footer-specific colors. Keys are intentionally named so the theme
						// auto-linker (resolveThemeKey) leaves them alone — these are
						// explicit controls, not theme-followers.
						'buttonBgColor'    => array( 'type' => 'color', 'label' => 'Main CTA background', 'section' => 'style', 'default' => '#FFFFFF' ),
						'buttonLabelColor' => array( 'type' => 'color', 'label' => 'Main CTA text', 'section' => 'style', 'default' => '#101B26' ),
						'linksColor'       => array( 'type' => 'color', 'label' => 'Column link color', 'section' => 'style', 'default' => '#0091FF' ),
					)
				),
			)
		);
	}

	/**
	 * Shared starter content for the editorial mega-menu panels.
	 *
	 * @param string $title Introductory panel title.
	 * @return array
	 */
	private function showcase_header_panel_defaults( $title = 'Explore Our Collection' ) {
		return array(
			'introTitle'    => $title,
			'introText'     => 'Discover products and services selected to make your space more enjoyable.',
			'buttonText'    => 'View Collection',
			'buttonUrl'     => '#',
			'accentText'    => 'Shop Accessories',
			'accentUrl'     => '#',
			'promoImage'    => '',
			'promoTitle'    => 'Featured Special',
			'promoSubtitle' => 'Limited time only',
			'promoUrl'      => '#',
			'cards'         => array(
				array( 'eyebrow' => 'Collection', 'title' => 'Premium Series', 'url' => '#', 'image' => '' ),
				array( 'eyebrow' => 'Comfort & Design', 'title' => 'Signature Series', 'url' => '#', 'image' => '' ),
				array( 'eyebrow' => 'Plug & Play', 'title' => 'Essential Series', 'url' => '#', 'image' => '' ),
				array( 'eyebrow' => 'Performance', 'title' => 'Reserve Series', 'url' => '#', 'image' => '' ),
			),
		);
	}

	/**
	 * Register a block
	 */
	public function register_block( $block ) {
		$this->blocks[ $block['id'] ] = $block;
	}

	/**
	 * Get all registered blocks
	 */
	public function get_registered_blocks() {
		return $this->blocks;
	}

	/**
	 * Get blocks grouped by category
	 */
	public function get_blocks_by_category() {
		$categories = array(
			'heroes'    => array(
				'label'  => 'Heroes',
				'icon'   => 'layout',
				'blocks' => array(),
			),
			'content'   => array(
				'label'  => 'Content',
				'icon'   => 'file-text',
				'blocks' => array(),
			),
			'ecommerce' => array(
				'label'  => 'Ecommerce',
				'icon'   => 'shopping-cart',
				'blocks' => array(),
			),
			'marketing' => array(
				'label'  => 'Marketing',
				'icon'   => 'target',
				'blocks' => array(),
			),
			'footers'   => array(
				'label'  => 'Footers',
				'icon'   => 'layout-template',
				'blocks' => array(),
			),
		);
		$hero_block_ids = array( 'hero', 'landing-hero', 'bento-hero', 'spotlight-hero', 'expander-hero', 'duo-hero', 'featured-promo-banner' );

		foreach ( $this->blocks as $block ) {
			// preset_only blocks stay registered for rendering existing pages and
			// resolving presets, but are not offered in the block library.
			if ( ! empty( $block['preset_only'] ) ) {
				continue;
			}
			$cat = $block['category'];
			if ( in_array( $block['id'], $hero_block_ids, true ) ) {
				$categories['heroes']['blocks'][] = $block;
			} elseif ( isset( $categories[ $cat ] ) ) {
				$categories[ $cat ]['blocks'][] = $block;
			}
		}

		return $categories;
	}

	/**
	 * Get a single block
	 */
	public function get_block( $block_id ) {
		return $this->blocks[ $block_id ] ?? null;
	}
}
