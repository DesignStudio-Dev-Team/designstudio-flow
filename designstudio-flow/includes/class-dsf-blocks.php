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
					'showButton'             => array(
						'type'    => 'toggle',
						'label'   => 'Show Button',
						'default' => true,
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
					'features'             => array(
						'type'    => 'repeater',
						'label'   => 'Features',
						'default' => array(
							array(
								'title'                  => 'Easy to Use',
								'description'            => 'Intuitive drag-and-drop interface',
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
					'columns'              => array(
						'type'    => 'select',
						'label'   => 'Columns',
						'default' => '3',
						'options' => array( '2', '3', '4' ),
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
				'id'          => 'bento-hero',
				'name'        => 'Bento Hero',
				'category'    => 'content',
				'icon'        => 'layout',
				'description' => 'Mosaic grid with hero section and feature boxes',
				'settings'    => array(
					// Hero Section
					'heroImage'                  => array(
						'type'    => 'image',
						'label'   => 'Hero Image',
						'default' => '',
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
						'type'    => 'image',
						'label'   => 'Box 1 Image',
						'default' => '',
					),
					'box1Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 1 Title',
						'default' => 'Box 1 Title',
					),
					'box1Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 1 URL',
						'default' => '#',
					),
					'box2Image'                  => array(
						'type'    => 'image',
						'label'   => 'Box 2 Image',
						'default' => '',
					),
					'box2Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 2 Title',
						'default' => 'Box 2 Title',
					),
					'box2Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 2 URL',
						'default' => '#',
					),
					'box3Image'                  => array(
						'type'    => 'image',
						'label'   => 'Box 3 Image',
						'default' => '',
					),
					'box3Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 3 Title',
						'default' => 'Box 3 Title',
					),
					'box3Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 3 URL',
						'default' => '#',
					),
					'box4Image'                  => array(
						'type'    => 'image',
						'label'   => 'Box 4 Image',
						'default' => '',
					),
					'box4Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 4 Title',
						'default' => 'Box 4 Title',
					),
					'box4Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 4 URL',
						'default' => '#',
					),
					'box5Image'                  => array(
						'type'    => 'image',
						'label'   => 'Box 5 Image',
						'default' => '',
					),
					'box5Title'                  => array(
						'type'    => 'text',
						'label'   => 'Box 5 Title',
						'default' => 'Box 5 Title',
					),
					'box5Url'                    => array(
						'type'    => 'text',
						'label'   => 'Box 5 URL',
						'default' => '#',
					),
					// CTA Box
					'ctaText'                    => array(
						'type'    => 'text',
						'label'   => 'CTA Text',
						'default' => 'Shop All',
					),
					'ctaUrl'                     => array(
						'type'     => 'text',
						'label'    => 'CTA URL',
						'default'  => '/shop',
						'showWhen' => array( 'ctaAction' => 'link' ),
					),
					'ctaAction'                  => array(
						'type'    => 'select',
						'label'   => 'CTA Action',
						'default' => 'link',
						'options' => array(
							'Link'       => 'link',
							'Open Modal' => 'modal',
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
						'showWhen' => array( 'ctaAction' => 'modal' ),
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
						'showWhen' => array( 'ctaAction' => 'modal' ),
					),
					'ctaModalContent'            => array(
						'type'     => 'wysiwyg',
						'label'    => 'Modal Content',
						'default'  => '',
						'showWhen' => array(
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'wysiwyg',
						),
					),
					'ctaModalHtml'               => array(
						'type'     => 'textarea',
						'label'    => 'Modal HTML',
						'default'  => '',
						'showWhen' => array(
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'html',
						),
					),
					'ctaModalShortcode'          => array(
						'type'     => 'text',
						'label'    => 'Modal Shortcode',
						'default'  => '',
						'showWhen' => array(
							'ctaAction'           => 'modal',
							'ctaModalContentType' => 'shortcode',
						),
					),
					'ctaColor'                   => array(
						'type'    => 'color',
						'label'   => 'CTA Background',
						'default' => '#2C5F5D',
					),
					'ctaTextColor'               => array(
						'type'    => 'color',
						'label'   => 'CTA Text Color',
						'default' => '#FFFFFF',
					),
					// Style
					'boxBackground'              => array(
						'type'    => 'color',
						'label'   => 'Box Background',
						'default' => '#F5F5F4',
					),
					'titleColor'                 => array(
						'type'    => 'color',
						'label'   => 'Title Color',
						'default' => '#1F2937',
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
						'default' => 'New At Backyard Leisure',
					),
					'descriptionText'        => array(
						'type'    => 'textarea',
						'label'   => 'Description',
						'default' => 'Our new patio furniture has arrived—designed for comfort, built for outdoor living.',
					),
					'image'                  => array(
						'type'    => 'image',
						'label'   => 'Banner Image',
						'default' => '',
					),
					'buttonText'             => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Get Started',
					),
					'buttonUrl'              => array(
						'type'     => 'text',
						'label'    => 'Link URL',
						'default'  => '#',
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

					// Badge Settings
					'badgeType'              => array(
						'type'    => 'select',
						'label'   => 'Badge Type',
						'default' => 'new',
						'options' => array(
							'New / In Stock' => 'new',
							'Low / Stock'    => 'low',
							'Custom Text'    => 'custom',
						),
					),
					'badgePosition'          => array(
						'type'    => 'select',
						'label'   => 'Badge Position',
						'default' => 'bottom-right',
						'options' => array(
							'Bottom Right'      => 'bottom-right',
							'Overlapping Curve' => 'overlapping',
						),
					),
					'badgeCustomLine1'       => array(
						'type'     => 'text',
						'label'    => 'Custom Line 1',
						'default'  => 'Special',
						'showWhen' => array( 'badgeType' => 'custom' ),
					),
					'badgeCustomLine2'       => array(
						'type'     => 'text',
						'label'    => 'Custom Line 2',
						'default'  => 'Offer',
						'showWhen' => array( 'badgeType' => 'custom' ),
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

					// Dimensions
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
						'type'    => 'richtext',
						'label'   => 'Description',
						'default' => 'Share your brand story here.',
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
						'min'     => 20,
						'max'     => 120,
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

		// ECOMMERCE Category
		$this->register_block(
			array(
				'id'          => 'product-grid',
				'name'        => 'Product Grid',
				'category'    => 'ecommerce',
				'icon'        => 'shopping-bag',
				'description' => 'Display products from WooCommerce',
				'settings'    => array(
					'title'            => array(
						'type'    => 'text',
						'label'   => 'Section Title',
						'default' => 'Featured Products',
					),
					'source'           => array(
						'type'    => 'source',
						'label'   => 'Product Source',
						'default' => 'category',
						'options' => array( 'category', 'manual' ),
					),
					'categoryId'       => array(
						'type'    => 'category',
						'label'   => 'Select Category',
						'default' => 0,
					),
					'pinnedProductIds' => array(
						'type'                => 'products',
						'label'               => 'Pin Top Products (Optional)',
						'default'             => array(),
						'searchPlaceholder'   => 'Search category products...',
						'hideSearchCardTitle' => true,
					),
					'productIds'       => array(
						'type'    => 'products',
						'label'   => 'Select Products',
						'default' => array(),
					),
					'limit'            => array(
						'type'    => 'number',
						'label'   => 'Products to Show',
						'default' => 6,
						'min'     => 1,
						'max'     => 100,
					),
					'columns'          => array(
						'type'    => 'select',
						'label'   => 'Columns',
						'default' => '3',
						'options' => array( '2', '3', '4' ),
					),
					'showPrice'        => array(
						'type'    => 'toggle',
						'label'   => 'Show Price',
						'default' => true,
					),
					'showButton'       => array(
						'type'    => 'toggle',
						'label'   => 'Show Add to Cart',
						'default' => true,
					),
					'buttonText'       => array(
						'type'    => 'text',
						'label'   => 'Button Text',
						'default' => 'Add to Cart',
					),
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
		);

		foreach ( $this->blocks as $block ) {
			$cat = $block['category'];
			if ( isset( $categories[ $cat ] ) ) {
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
