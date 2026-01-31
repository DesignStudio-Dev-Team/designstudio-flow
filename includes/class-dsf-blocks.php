<?php
/**
 * Block registration and management for DesignStudio Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Blocks {
    
    private static $instance = null;
    private $blocks = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
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
        $this->register_block([
            'id' => 'hero',
            'name' => 'Hero',
            'category' => 'content',
            'icon' => 'layout-template',
            'description' => 'Full-width hero with centered text and CTA button',
            'settings' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Welcome to Our Store'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Subtitle', 'default' => 'Discover amazing products'],
                'showButton' => ['type' => 'toggle', 'label' => 'Show Button', 'default' => true],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Shop Now'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'default' => '/shop'],
                'backgroundImage' => ['type' => 'image', 'label' => 'Background Image', 'default' => ''],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#3B82F6'],
                'textColor' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#FFFFFF'],
                'contentBackgroundColor' => ['type' => 'color', 'label' => 'Text Background', 'default' => 'rgba(0,0,0,0)'],
                'contentPosition' => [
                    'type' => 'select', 
                    'label' => 'Content Position', 
                    'default' => 'center-center',
                    'options' => [
                        'Top Left' => 'top-left', 'Top Center' => 'top-center', 'Top Right' => 'top-right',
                        'Center Left' => 'center-left', 'Center Center' => 'center-center', 'Center Right' => 'center-right',
                        'Bottom Left' => 'bottom-left', 'Bottom Center' => 'bottom-center', 'Bottom Right' => 'bottom-right'
                    ]
                ],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 80, 'min' => 40, 'max' => 200],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'features-grid',
            'name' => 'Features Grid',
            'category' => 'content',
            'icon' => 'grid-3x3',
            'description' => 'Display key features in a grid layout',
            'settings' => [
                'title' => ['type' => 'text', 'label' => 'Section Title', 'default' => 'Our Features'],
                'subtitle' => ['type' => 'text', 'label' => 'Section Subtitle', 'default' => 'Everything you need'],
                'features' => ['type' => 'repeater', 'label' => 'Features', 'default' => [
                    ['title' => 'Easy to Use', 'description' => 'Intuitive drag-and-drop interface', 'buttonText' => 'Learn More', 'buttonUrl' => '#'],
                    ['title' => 'Customizable', 'description' => 'Full control over styling', 'buttonText' => 'Learn More', 'buttonUrl' => '#'],
                    ['title' => 'Responsive', 'description' => 'Works on all devices', 'buttonText' => 'Learn More', 'buttonUrl' => '#'],
                ]],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'default' => '3', 'options' => ['2', '3', '4']],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'subtitleColor' => ['type' => 'color', 'label' => 'Subtitle Color', 'default' => '#6B7280'],
                'cardColor' => ['type' => 'color', 'label' => 'Card Background', 'default' => '#1F2937'],
                'cardTitleColor' => ['type' => 'color', 'label' => 'Card Title Color', 'default' => '#60A5FA'],
                'cardDescriptionColor' => ['type' => 'color', 'label' => 'Card Description Color', 'default' => '#9CA3AF'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'bento-hero',
            'name' => 'Bento Hero',
            'category' => 'content',
            'icon' => 'layout',
            'description' => 'Mosaic grid with hero section and feature boxes',
            'settings' => [
                // Hero Section
                'heroImage' => ['type' => 'image', 'label' => 'Hero Image', 'default' => ''],
                'heroTitle' => ['type' => 'text', 'label' => 'Hero Title', 'default' => 'Hero Title'],
                'heroType' => [
                    'type' => 'select', 
                    'label' => 'Hero Action Type', 
                    'default' => 'search', 
                    'options' => ['Search Box' => 'search', 'Button' => 'button']
                ],
                // Search Settings
                'searchPlaceholder' => [
                    'type' => 'text', 
                    'label' => 'Search Placeholder', 
                    'default' => 'Search by keyword',
                    'showWhen' => ['heroType' => 'search']
                ],
                // Button Settings
                'heroButtonText' => [
                    'type' => 'text', 
                    'label' => 'Button Text', 
                    'default' => 'Shop Now',
                    'showWhen' => ['heroType' => 'button']
                ],
                'heroButtonUrl' => [
                    'type' => 'text', 
                    'label' => 'Button URL', 
                    'default' => '#',
                    'showWhen' => ['heroType' => 'button']
                ],
                // Feature Boxes (5 boxes)
                'box1Image' => ['type' => 'image', 'label' => 'Box 1 Image', 'default' => ''],
                'box1Title' => ['type' => 'text', 'label' => 'Box 1 Title', 'default' => 'Box 1 Title'],
                'box1Url' => ['type' => 'text', 'label' => 'Box 1 URL', 'default' => '#'],
                'box2Image' => ['type' => 'image', 'label' => 'Box 2 Image', 'default' => ''],
                'box2Title' => ['type' => 'text', 'label' => 'Box 2 Title', 'default' => 'Box 2 Title'],
                'box2Url' => ['type' => 'text', 'label' => 'Box 2 URL', 'default' => '#'],
                'box3Image' => ['type' => 'image', 'label' => 'Box 3 Image', 'default' => ''],
                'box3Title' => ['type' => 'text', 'label' => 'Box 3 Title', 'default' => 'Box 3 Title'],
                'box3Url' => ['type' => 'text', 'label' => 'Box 3 URL', 'default' => '#'],
                'box4Image' => ['type' => 'image', 'label' => 'Box 4 Image', 'default' => ''],
                'box4Title' => ['type' => 'text', 'label' => 'Box 4 Title', 'default' => 'Box 4 Title'],
                'box4Url' => ['type' => 'text', 'label' => 'Box 4 URL', 'default' => '#'],
                'box5Image' => ['type' => 'image', 'label' => 'Box 5 Image', 'default' => ''],
                'box5Title' => ['type' => 'text', 'label' => 'Box 5 Title', 'default' => 'Box 5 Title'],
                'box5Url' => ['type' => 'text', 'label' => 'Box 5 URL', 'default' => '#'],
                // CTA Box
                'ctaText' => ['type' => 'text', 'label' => 'CTA Text', 'default' => 'Shop All'],
                'ctaUrl' => ['type' => 'text', 'label' => 'CTA URL', 'default' => '/shop'],
                'ctaColor' => ['type' => 'color', 'label' => 'CTA Background', 'default' => '#2C5F5D'],
                'ctaTextColor' => ['type' => 'color', 'label' => 'CTA Text Color', 'default' => '#FFFFFF'],
                // Style
                'boxBackground' => ['type' => 'color', 'label' => 'Box Background', 'default' => '#F5F5F4'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'gap' => ['type' => 'slider', 'label' => 'Gap', 'default' => 12, 'min' => 4, 'max' => 24],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'duo-hero',
            'name' => 'Duo Hero',
            'category' => 'content',
            'icon' => 'columns',
            'description' => 'Split hero section with two interactive panels',
            'settings' => [
                // Layout
                'splitRatio' => ['type' => 'slider', 'label' => 'Split Ratio (Left %)', 'default' => 50, 'min' => 30, 'max' => 70, 'unit' => '%'],
                'height' => ['type' => 'slider', 'label' => 'Height', 'default' => 500, 'min' => 300, 'max' => 800],
                'gap' => ['type' => 'slider', 'label' => 'Gap', 'default' => 20, 'min' => 0, 'max' => 80],
                
                // Left Side
                'leftImage' => ['type' => 'image', 'label' => 'Left Image', 'default' => ''],
                'leftTitle' => ['type' => 'text', 'label' => 'Left Title', 'default' => 'Hero Title 1'],
                'leftType' => [
                    'type' => 'select', 
                    'label' => 'Left Action Type', 
                    'default' => 'button', 
                    'options' => ['Button' => 'button', 'Search Box' => 'search']
                ],
                'leftButtonText' => ['type' => 'text', 'label' => 'Left Button Text', 'default' => 'Get Started', 'showWhen' => ['leftType' => 'button']],
                'leftButtonUrl' => ['type' => 'text', 'label' => 'Left Button URL', 'default' => '#', 'showWhen' => ['leftType' => 'button']],
                'leftSearchPlaceholder' => ['type' => 'text', 'label' => 'Left Search Placeholder', 'default' => 'Search by keyword', 'showWhen' => ['leftType' => 'search']],
                'leftTextColor' => ['type' => 'color', 'label' => 'Left Text Color', 'default' => '#FFFFFF'],

                // Right Side
                'rightImage' => ['type' => 'image', 'label' => 'Right Image', 'default' => ''],
                'rightTitle' => ['type' => 'text', 'label' => 'Right Title', 'default' => 'Hero Title 2'],
                'rightType' => [
                    'type' => 'select', 
                    'label' => 'Right Action Type', 
                    'default' => 'search', 
                    'options' => ['Button' => 'button', 'Search Box' => 'search']
                ],
                'rightButtonText' => ['type' => 'text', 'label' => 'Right Button Text', 'default' => 'Shop Now', 'showWhen' => ['rightType' => 'button']],
                'rightButtonUrl' => ['type' => 'text', 'label' => 'Right Button URL', 'default' => '#', 'showWhen' => ['rightType' => 'button']],
                'rightSearchPlaceholder' => ['type' => 'text', 'label' => 'Right Search Placeholder', 'default' => 'Search by keyword', 'showWhen' => ['rightType' => 'search']],
                'rightTextColor' => ['type' => 'color', 'label' => 'Right Text Color', 'default' => '#FFFFFF'],

                // General Styles
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 40, 'min' => 0, 'max' => 100],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'featured-promo-banner',
            'name' => 'Featured Promo Banner',
            'category' => 'marketing',
            'icon' => 'tag',
            'description' => 'Featured banner with curved divider and badge',
            'settings' => [
                // Content
                'headerText' => ['type' => 'text', 'label' => 'Header Text', 'default' => 'New At Backyard Leisure'],
                'descriptionText' => ['type' => 'textarea', 'label' => 'Description', 'default' => 'Our new patio furniture has arrived—designed for comfort, built for outdoor living.'],
                'image' => ['type' => 'image', 'label' => 'Banner Image', 'default' => ''],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Get Started'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Link URL', 'default' => '#'],
                
                // Badge Settings
                'badgeType' => ['type' => 'select', 'label' => 'Badge Type', 'default' => 'new', 'options' => [
                    'New / In Stock' => 'new', 
                    'Low / Stock' => 'low', 
                    'Custom Text' => 'custom'
                ]],
                'badgePosition' => ['type' => 'select', 'label' => 'Badge Position', 'default' => 'bottom-right', 'options' => [
                   'Bottom Right' => 'bottom-right', 
                   'Overlapping Curve' => 'overlapping'
                ]],
                'badgeCustomLine1' => ['type' => 'text', 'label' => 'Custom Line 1', 'default' => 'Special', 'showWhen' => ['badgeType' => 'custom']],
                'badgeCustomLine2' => ['type' => 'text', 'label' => 'Custom Line 2', 'default' => 'Offer', 'showWhen' => ['badgeType' => 'custom']],
                
                // Style
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#E0F2F1'],
                'badgeColor' => ['type' => 'color', 'label' => 'Badge Color', 'default' => '#3D736A'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'textColor' => ['type' => 'color', 'label' => 'Description Color', 'default' => '#1F2937'],
                'circleTextColor' => ['type' => 'color', 'label' => 'Badge & Button Text Color', 'default' => '#FFFFFF'],
                
                // Dimensions
                 'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                 'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                 'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'text-image',
            'name' => 'Text & Image',
            'category' => 'content',
            'icon' => 'layout',
            'description' => 'Text content with an accompanying image and optional CTA',
            'settings' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'About Our Story'],
                'content' => ['type' => 'richtext', 'label' => 'Description', 'default' => 'Share your brand story here.'],
                'showButton' => ['type' => 'toggle', 'label' => 'Show Button', 'default' => false],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Learn More'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'default' => '#'],
                'image' => ['type' => 'image', 'label' => 'Image', 'default' => ''],
                'imagePosition' => ['type' => 'select', 'label' => 'Image Position', 'default' => 'right', 'options' => ['left', 'right']],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'textColor' => ['type' => 'color', 'label' => 'Description Color', 'default' => '#4B5563'],
                'buttonColor' => ['type' => 'color', 'label' => 'Button Background', 'default' => '#2563EB'],
                'buttonTextColor' => ['type' => 'color', 'label' => 'Button Text Color', 'default' => '#FFFFFF'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 20, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'testimonials',
            'name' => 'Testimonials',
            'category' => 'content',
            'icon' => 'message-circle',
            'description' => 'Customer testimonials carousel',
            'settings' => [
                'testimonials' => ['type' => 'repeater', 'label' => 'Testimonials', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Testimonial Title'],
                    'quote' => ['type' => 'richtext', 'label' => 'Quote', 'default' => 'Share your testimonial here...'],
                    'author' => ['type' => 'text', 'label' => 'Author Name', 'default' => 'Customer Name'],
                    'location' => ['type' => 'text', 'label' => 'Location', 'default' => 'City, State'],
                    'image' => ['type' => 'image', 'label' => 'Image', 'default' => ''],
                ], 'default' => [
                    ['title' => 'Testimonial Title', 'quote' => 'Share your testimonial here...', 'author' => 'Customer Name', 'location' => 'City, State', 'image' => ''],
                ]],
                'primaryColor' => ['type' => 'color', 'label' => 'Primary Color (Arrows & Icon)', 'default' => '#0F6B8C'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'textColor' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#4B5563'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        // ECOMMERCE Category
        $this->register_block([
            'id' => 'product-grid',
            'name' => 'Product Grid',
            'category' => 'ecommerce',
            'icon' => 'shopping-bag',
            'description' => 'Display products from WooCommerce',
            'settings' => [
                'title' => ['type' => 'text', 'label' => 'Section Title', 'default' => 'Featured Products'],
                'source' => ['type' => 'source', 'label' => 'Product Source', 'default' => 'category', 'options' => ['category', 'manual']],
                'categoryId' => ['type' => 'category', 'label' => 'Select Category', 'default' => 0],
                'pinnedProductIds' => [
                    'type' => 'products', 
                    'label' => 'Pin Top Products (Optional)', 
                    'default' => [],
                    'searchPlaceholder' => 'Search category products...',
                    'hideSearchCardTitle' => true
                ],
                'productIds' => ['type' => 'products', 'label' => 'Select Products', 'default' => []],
                'limit' => ['type' => 'number', 'label' => 'Products to Show', 'default' => 6, 'min' => 1, 'max' => 100],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'default' => '3', 'options' => ['2', '3', '4']],
                'showPrice' => ['type' => 'toggle', 'label' => 'Show Price', 'default' => true],
                'showButton' => ['type' => 'toggle', 'label' => 'Show Add to Cart', 'default' => true],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Add to Cart'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'ecommerce-showcase',
            'name' => 'Ecommerce Showcase',
            'category' => 'ecommerce',
            'icon' => 'shopping-bag',
            'description' => 'Display categories or products with optional slider',
            'settings' => [
                'displayMode' => ['type' => 'source', 'label' => 'Display Mode', 'default' => 'categories'],
                'title' => ['type' => 'text', 'label' => 'Section Title', 'default' => 'Ecommerce Showcase'],
                'shopAllText' => ['type' => 'text', 'label' => 'Shop All Link Text', 'default' => 'SHOP ALL'],
                'showShopAll' => ['type' => 'toggle', 'label' => 'Show Shop All Link', 'default' => true],
                // Categories mode settings (only show when displayMode is 'categories')
                'categoryIds' => ['type' => 'categories', 'label' => 'Available Categories', 'default' => [], 'showWhen' => ['displayMode' => 'categories']],
                // Products mode settings (only show when displayMode is 'products')
                'categoryId' => ['type' => 'category', 'label' => 'Select Category', 'default' => 0, 'showWhen' => ['displayMode' => 'products']],
                'pinnedProductIds' => [
                    'type' => 'products', 
                    'label' => 'Pin Top Products (Optional)', 
                    'default' => [],
                    'showWhen' => ['displayMode' => 'products'],
                    'searchPlaceholder' => 'Search category products...',
                    'hideSearchCardTitle' => true
                ],
                'limit' => ['type' => 'number', 'label' => 'Items to Show', 'default' => 5, 'min' => 1, 'max' => 20],
                // Style settings
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'priceColor' => ['type' => 'color', 'label' => 'Price Color', 'default' => '#6B7280', 'showWhen' => ['displayMode' => 'products']],
                'salePriceColor' => ['type' => 'color', 'label' => 'Sale Price Color', 'default' => '#DC2626', 'showWhen' => ['displayMode' => 'products']],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'brand-carousel',
            'name' => 'Brand Logos',
            'category' => 'marketing',
            'icon' => 'award',
            'description' => 'Display brand logos in a stacked grid layout',
            'settings' => [
                // Title
                'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Shop By Brand'],
                'showTitle' => ['type' => 'toggle', 'label' => 'Show Title', 'default' => true],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'titleSize' => ['type' => 'slider', 'label' => 'Title Size', 'default' => 32, 'min' => 18, 'max' => 48],
                // Brands
                'brands' => ['type' => 'repeater', 'label' => 'Brands', 'default' => []],
                // Layout
                'logosPerRow' => ['type' => 'slider', 'label' => 'Logos Per Row', 'default' => 4, 'min' => 2, 'max' => 6],
                'logoMaxHeight' => ['type' => 'slider', 'label' => 'Logo Max Height', 'default' => 60, 'min' => 30, 'max' => 120],
                'logoGap' => ['type' => 'slider', 'label' => 'Logo Gap', 'default' => 48, 'min' => 16, 'max' => 80],
                // Style
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 40, 'min' => 20, 'max' => 100],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        // MARKETING Category
        $this->register_block([
            'id' => 'promo-banner',
            'name' => 'Promo Banner',
            'category' => 'marketing',
            'icon' => 'percent',
            'description' => 'Large image with promotional offer panel',
            'settings' => [
                'image' => ['type' => 'image', 'label' => 'Banner Image', 'default' => ''],
                'imagePosition' => ['type' => 'select', 'label' => 'Image Position', 'default' => 'center', 'options' => ['Top' => 'top', 'Center' => 'center', 'Bottom' => 'bottom']],
                'contentPosition' => ['type' => 'select', 'label' => 'Content Position', 'default' => 'right', 'options' => ['Left' => 'left', 'Right' => 'right']],
                'preText' => ['type' => 'text', 'label' => 'Pre-Text', 'default' => 'UP TO'],
                'discountAmount' => ['type' => 'text', 'label' => 'Discount Amount', 'default' => '20'],
                'showPercent' => ['type' => 'toggle', 'label' => 'Show Percent Sign', 'default' => true],
                'discountSuffix' => ['type' => 'text', 'label' => 'Discount Suffix', 'default' => 'OFF'],
                'subtitle' => ['type' => 'text', 'label' => 'Subtitle Line 1', 'default' => 'Select'],
                'subtitle2' => ['type' => 'text', 'label' => 'Subtitle Line 2', 'default' => 'Casual Seating'],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Shop Now'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'default' => '/shop'],
                'panelColor' => ['type' => 'color', 'label' => 'Panel Color', 'default' => '#2C5F5D'],
                'textColor' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#FFFFFF'],
                'dividerColor' => ['type' => 'color', 'label' => 'Divider Color', 'default' => '#FFFFFF'],
                'bannerHeight' => ['type' => 'slider', 'label' => 'Banner Height', 'default' => 280, 'min' => 280, 'max' => 580],
                'panelWidth' => ['type' => 'slider', 'label' => 'Panel Width', 'default' => 580, 'min' => 580, 'max' => 800],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'featured-product-banner',
            'name' => 'Featured Product Banner',
            'category' => 'ecommerce',
            'icon' => 'star',
            'description' => 'Showcase a featured product with sale banner and promo code',
            'settings' => [
                // Background

                // Sale Banner
                'bannerText' => ['type' => 'text', 'label' => 'Banner Text', 'default' => '20%'],
                'bannerSubtext' => ['type' => 'text', 'label' => 'Banner Subtext', 'default' => 'OFF'],
                'bannerColor' => ['type' => 'color', 'label' => 'Banner Color', 'default' => '#2C5F5D'],
                'bannerTextColor' => ['type' => 'color', 'label' => 'Banner Text Color', 'default' => '#FFFFFF'],
                // Product Circle
                'circleColor' => ['type' => 'color', 'label' => 'Circle Color', 'default' => 'rgba(255,255,255,0.5)'],
                'productImage' => ['type' => 'image', 'label' => 'Product Image', 'default' => ''],
                // Content
                'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Special Offer'],
                'promoCode' => ['type' => 'text', 'label' => 'Promo Code', 'default' => 'HAPPY2026'],
                'description' => ['type' => 'text', 'label' => 'Description', 'default' => 'Enter promo code at checkout!'],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Shop All'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'default' => '/shop'],
                // Styles
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#1F2937'],
                'textColor' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#1F2937'],
                'buttonColor' => ['type' => 'color', 'label' => 'Button Color', 'default' => '#2C5F5D'],
                'buttonTextColor' => ['type' => 'color', 'label' => 'Button Text Color', 'default' => '#FFFFFF'],
                'backgroundType' => [
                    'type' => 'select', 
                    'label' => 'Background Type', 
                    'default' => 'gradient', 
                    'options' => ['Solid Color' => 'solid', 'Gradient' => 'gradient', 'Image' => 'image']
                ],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#C8E6C9', 'showWhen' => ['backgroundType' => 'solid']],
                'gradientStart' => ['type' => 'color', 'label' => 'Gradient Start', 'default' => '#B2DFDB', 'showWhen' => ['backgroundType' => 'gradient']],
                'gradientEnd' => ['type' => 'color', 'label' => 'Gradient End', 'default' => '#C8E6C9', 'showWhen' => ['backgroundType' => 'gradient']],
                'gradientDirection' => [
                    'type' => 'select',
                    'label' => 'Gradient Direction',
                    'default' => 'left-right',
                    'options' => ['Left to Right' => 'left-right', 'Top to Bottom' => 'top-bottom', 'Radial (Center Out)' => 'radial'],
                    'showWhen' => ['backgroundType' => 'gradient']
                ],
                'backgroundImage' => ['type' => 'image', 'label' => 'Background Image', 'default' => '', 'showWhen' => ['backgroundType' => 'image']],
                // Layout
                'bannerHeight' => ['type' => 'slider', 'label' => 'Banner Height', 'default' => 240, 'min' => 240, 'max' => 350],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        
        $this->register_block([
            'id' => 'cta-banner',
            'name' => 'CTA Banner',
            'category' => 'marketing',
            'icon' => 'megaphone',
            'description' => 'Call-to-action banner',
            'settings' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Get 20% Off Your First Order'],
                'subtitle' => ['type' => 'text', 'label' => 'Subtitle', 'default' => 'Sign up for our newsletter'],
                'buttonText' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Shop Now'],
                'buttonUrl' => ['type' => 'text', 'label' => 'Button URL', 'default' => '/shop'],
                'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#1E40AF'],
                'textColor' => ['type' => 'color', 'label' => 'Subtitle Color', 'default' => '#FFFFFF'],
                'titleColor' => ['type' => 'color', 'label' => 'Title Color', 'default' => '#FFFFFF'],
                'buttonColor' => ['type' => 'color', 'label' => 'Button Background', 'default' => '#FFFFFF'],
                'buttonTextColor' => ['type' => 'color', 'label' => 'Button Text Color', 'default' => '#1E40AF'],
                'padding' => ['type' => 'slider', 'label' => 'Vertical Padding', 'default' => 60, 'min' => 20, 'max' => 120],
                'paddingX' => ['type' => 'slider', 'label' => 'Horizontal Padding', 'default' => 0, 'min' => 0, 'max' => 100],
                'marginY' => ['type' => 'slider', 'label' => 'Vertical Margin', 'default' => 25, 'min' => 0, 'max' => 100],
            ],
        ]);
        

    }
    
    /**
     * Register a block
     */
    public function register_block($block) {
        $this->blocks[$block['id']] = $block;
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
        $categories = [
            'content' => ['label' => 'Content', 'icon' => 'file-text', 'blocks' => []],
            'ecommerce' => ['label' => 'Ecommerce', 'icon' => 'shopping-cart', 'blocks' => []],
            'marketing' => ['label' => 'Marketing', 'icon' => 'target', 'blocks' => []],
        ];
        
        foreach ($this->blocks as $block) {
            $cat = $block['category'];
            if (isset($categories[$cat])) {
                $categories[$cat]['blocks'][] = $block;
            }
        }
        
        return $categories;
    }
    
    /**
     * Get a single block
     */
    public function get_block($block_id) {
        return $this->blocks[$block_id] ?? null;
    }
}
