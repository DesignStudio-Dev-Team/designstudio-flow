<?php
/**
 * Frontend rendering for DesignStudio Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSF_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('the_content', [$this, 'render_flow_content'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if this is a Flow page or has Flow blocks
        $is_flow = $post->post_type === 'dsf_page' || get_post_meta($post->ID, '_dsf_enabled', true);
        
        if (!$is_flow) {
            return;
        }
        
        wp_enqueue_style(
            'dsf-frontend',
            DSF_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            DSF_VERSION
        );
        
        wp_enqueue_script(
            'dsf-frontend',
            DSF_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            DSF_VERSION,
            true
        );
    }
    
    /**
     * Render Flow page content
     */
    public function render_flow_content($content) {
        global $post;
        
        if (!$post || !is_singular()) {
            return $content;
        }
        
        // Only for Flow pages
        if ($post->post_type !== 'dsf_page') {
            return $content;
        }
        
        $blocks_json = get_post_meta($post->ID, '_dsf_blocks', true);
        
        if (!$blocks_json) {
            return $content;
        }
        
        $blocks = json_decode($blocks_json, true);
        
        if (!$blocks || !is_array($blocks)) {
            return $content;
        }
        
        $output = '<div class="dsf-page-content">';
        
        foreach ($blocks as $block) {
            $output .= $this->render_block($block);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render a single block
     */
    public function render_block($block) {
        $block_type = $block['type'] ?? '';
        $settings = $block['settings'] ?? [];
        
        // Get block template
        $template_file = DSF_PLUGIN_DIR . "blocks/{$block_type}/template.php";
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback to built-in rendering
        return $this->render_block_fallback($block_type, $settings);
    }
    
    /**
     * Fallback block rendering
     */
    private function render_block_fallback($block_type, $settings) {
        $padding = $settings['padding'] ?? 60;
        $bg_color = $settings['backgroundColor'] ?? '#FFFFFF';
        $text_color = $settings['textColor'] ?? '#1F2937';
        
        $style = sprintf(
            'padding: %dpx 0; background-color: %s; color: %s;',
            $padding,
            esc_attr($bg_color),
            esc_attr($text_color)
        );
        
        switch ($block_type) {
            case 'hero-centered':
                return $this->render_hero_centered($settings, $style);
            
            case 'hero-split':
                return $this->render_hero_split($settings, $style);
            
            case 'product-grid':
                return $this->render_product_grid($settings, $style);
            
            case 'category-grid':
                return $this->render_category_grid($settings, $style);
            
            case 'features-grid':
                return $this->render_features_grid($settings, $style);
            
            case 'cta-banner':
                return $this->render_cta_banner($settings, $style);
            
            case 'testimonials':
                return $this->render_testimonials($settings, $style);
            
            case 'newsletter':
                return $this->render_newsletter($settings, $style);
            
            case 'brand-carousel':
                return $this->render_brand_carousel($settings, $style);
            
            case 'text-image':
                return $this->render_text_image($settings, $style);
            
            default:
                return '';
        }
    }
    
    /**
     * Render Hero Centered
     */
    private function render_hero_centered($s, $style) {
        $bg_image = !empty($s['backgroundImage']) ? 'background-image: url(' . esc_url($s['backgroundImage']) . ');' : '';
        
        return sprintf(
            '<section class="dsf-hero dsf-hero--centered" style="%s %s">
                <div class="dsf-hero__overlay"></div>
                <div class="dsf-hero__content">
                    <h1 class="dsf-hero__title">%s</h1>
                    <p class="dsf-hero__subtitle">%s</p>
                    <a href="%s" class="dsf-hero__btn">%s</a>
                </div>
            </section>',
            $style,
            $bg_image,
            esc_html($s['title'] ?? ''),
            esc_html($s['subtitle'] ?? ''),
            esc_url($s['buttonUrl'] ?? '#'),
            esc_html($s['buttonText'] ?? 'Learn More')
        );
    }
    
    /**
     * Render Hero Split
     */
    private function render_hero_split($s, $style) {
        $img_pos = ($s['imagePosition'] ?? 'right') === 'left' ? 'dsf-hero--img-left' : '';
        
        return sprintf(
            '<section class="dsf-hero dsf-hero--split %s" style="%s">
                <div class="dsf-hero__grid">
                    <div class="dsf-hero__text">
                        <h1 class="dsf-hero__title">%s</h1>
                        <p class="dsf-hero__subtitle">%s</p>
                        <a href="%s" class="dsf-hero__btn">%s</a>
                    </div>
                    <div class="dsf-hero__image">
                        <img src="%s" alt="">
                    </div>
                </div>
            </section>',
            $img_pos,
            $style,
            esc_html($s['title'] ?? ''),
            esc_html($s['subtitle'] ?? ''),
            esc_url($s['buttonUrl'] ?? '#'),
            esc_html($s['buttonText'] ?? 'Explore'),
            esc_url($s['image'] ?? '')
        );
    }
    
    /**
     * Render Product Grid
     */
    private function render_product_grid($s, $style) {
        if (!class_exists('WooCommerce')) {
            return '<section class="dsf-product-grid" style="' . $style . '"><p>WooCommerce is required.</p></section>';
        }
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => intval($s['limit'] ?? 6),
            'post_status' => 'publish',
        ];
        
        if (($s['source'] ?? 'category') === 'manual' && !empty($s['productIds'])) {
            $args['post__in'] = array_map('intval', $s['productIds']);
            $args['orderby'] = 'post__in';
        } elseif (!empty($s['categoryId'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => intval($s['categoryId']),
            ]];
        }
        
        $products = new WP_Query($args);
        $columns = intval($s['columns'] ?? 3);
        
        ob_start();
        ?>
        <section class="dsf-product-grid" style="<?php echo $style; ?>">
            <div class="dsf-container">
                <?php if (!empty($s['title'])): ?>
                    <h2 class="dsf-product-grid__title"><?php echo esc_html($s['title']); ?></h2>
                <?php endif; ?>
                
                <div class="dsf-product-grid__items" style="--columns: <?php echo $columns; ?>">
                    <?php while ($products->have_posts()): $products->the_post();
                        $product = wc_get_product(get_the_ID());
                        if (!$product) continue;
                    ?>
                        <div class="dsf-product-card">
                            <a href="<?php echo $product->get_permalink(); ?>">
                                <img src="<?php echo wp_get_attachment_url($product->get_image_id()); ?>" 
                                     alt="<?php echo esc_attr($product->get_name()); ?>"
                                     class="dsf-product-card__image">
                            </a>
                            <div class="dsf-product-card__body">
                                <h3 class="dsf-product-card__title"><?php echo $product->get_name(); ?></h3>
                                <?php if ($s['showPrice'] ?? true): ?>
                                    <div class="dsf-product-card__price"><?php echo $product->get_price_html(); ?></div>
                                <?php endif; ?>
                                <?php if ($s['showButton'] ?? true): ?>
                                    <a href="<?php echo $product->add_to_cart_url(); ?>" class="dsf-product-card__btn">
                                        <?php echo esc_html($s['buttonText'] ?? 'Add to Cart'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Category Grid
     */
    private function render_category_grid($s, $style) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        
        $args = [
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => intval($s['limit'] ?? 5),
        ];
        
        if (!empty($s['categoryIds'])) {
            $args['include'] = array_map('intval', $s['categoryIds']);
        }
        
        $categories = get_terms($args);
        
        ob_start();
        ?>
        <section class="dsf-category-grid" style="<?php echo $style; ?>">
            <div class="dsf-container">
                <?php if (!empty($s['title'])): ?>
                    <h2 class="dsf-category-grid__title"><?php echo esc_html($s['title']); ?></h2>
                <?php endif; ?>
                
                <div class="dsf-category-grid__items">
                    <?php foreach ($categories as $cat):
                        $thumb_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
                        $image = $thumb_id ? wp_get_attachment_url($thumb_id) : wc_placeholder_img_src();
                    ?>
                        <a href="<?php echo get_term_link($cat); ?>" class="dsf-category-item">
                            <img src="<?php echo $image; ?>" alt="<?php echo esc_attr($cat->name); ?>" class="dsf-category-item__image">
                            <span class="dsf-category-item__name"><?php echo esc_html($cat->name); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Features Grid
     */
    private function render_features_grid($s, $style) {
        $features = $s['features'] ?? [];
        $columns = intval($s['columns'] ?? 3);
        $card_color = $s['cardColor'] ?? '#1F2937';
        
        ob_start();
        ?>
        <section class="dsf-features-grid" style="<?php echo $style; ?>">
            <div class="dsf-container">
                <div class="dsf-features-grid__header">
                    <h2 class="dsf-features-grid__title"><?php echo esc_html($s['title'] ?? ''); ?></h2>
                    <p class="dsf-features-grid__subtitle"><?php echo esc_html($s['subtitle'] ?? ''); ?></p>
                </div>
                <div class="dsf-features-grid__items" style="--columns: <?php echo $columns; ?>">
                    <?php foreach ($features as $feature): ?>
                        <div class="dsf-feature-card" style="background-color: <?php echo esc_attr($card_color); ?>">
                            <h3 class="dsf-feature-card__title"><?php echo esc_html($feature['title'] ?? ''); ?></h3>
                            <p class="dsf-feature-card__desc"><?php echo esc_html($feature['description'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render CTA Banner
     */
    private function render_cta_banner($s, $style) {
        return sprintf(
            '<section class="dsf-cta-banner" style="%s">
                <div class="dsf-container dsf-cta-banner__inner">
                    <div class="dsf-cta-banner__text">
                        <h2 class="dsf-cta-banner__title">%s</h2>
                        <p class="dsf-cta-banner__subtitle">%s</p>
                    </div>
                    <a href="%s" class="dsf-cta-banner__btn">%s</a>
                </div>
            </section>',
            $style,
            esc_html($s['title'] ?? ''),
            esc_html($s['subtitle'] ?? ''),
            esc_url($s['buttonUrl'] ?? '#'),
            esc_html($s['buttonText'] ?? 'Shop Now')
        );
    }
    
    /**
     * Render Testimonials
     */
    private function render_testimonials($s, $style) {
        $testimonials = $s['testimonials'] ?? [];
        
        ob_start();
        ?>
        <section class="dsf-testimonials" style="<?php echo $style; ?>">
            <div class="dsf-container">
                <h2 class="dsf-testimonials__title"><?php echo esc_html($s['title'] ?? ''); ?></h2>
                <div class="dsf-testimonials__items">
                    <?php foreach ($testimonials as $t): ?>
                        <div class="dsf-testimonial-card">
                            <p class="dsf-testimonial-card__quote">"<?php echo esc_html($t['quote'] ?? ''); ?>"</p>
                            <div class="dsf-testimonial-card__author">
                                <span class="dsf-testimonial-card__name"><?php echo esc_html($t['author'] ?? ''); ?></span>
                                <span class="dsf-testimonial-card__role"><?php echo esc_html($t['role'] ?? ''); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Newsletter
     */
    private function render_newsletter($s, $style) {
        return sprintf(
            '<section class="dsf-newsletter" style="%s">
                <div class="dsf-container">
                    <h2 class="dsf-newsletter__title">%s</h2>
                    <p class="dsf-newsletter__subtitle">%s</p>
                    <form class="dsf-newsletter__form">
                        <input type="email" placeholder="%s" class="dsf-newsletter__input">
                        <button type="submit" class="dsf-newsletter__btn">%s</button>
                    </form>
                </div>
            </section>',
            $style,
            esc_html($s['title'] ?? ''),
            esc_html($s['subtitle'] ?? ''),
            esc_attr($s['placeholder'] ?? 'Enter your email'),
            esc_html($s['buttonText'] ?? 'Subscribe')
        );
    }
    
    /**
     * Render Brand Carousel
     */
    private function render_brand_carousel($s, $style) {
        $brands = $s['brands'] ?? [];
        
        ob_start();
        ?>
        <section class="dsf-brand-carousel" style="<?php echo $style; ?>">
            <div class="dsf-brand-carousel__items">
                <?php foreach ($brands as $brand): ?>
                    <a href="<?php echo esc_url($brand['url'] ?? '#'); ?>" class="dsf-brand-item">
                        <img src="<?php echo esc_url($brand['logo'] ?? ''); ?>" 
                             alt="<?php echo esc_attr($brand['name'] ?? ''); ?>"
                             class="dsf-brand-item__logo">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Text & Image
     */
    private function render_text_image($s, $style) {
        $img_pos = ($s['imagePosition'] ?? 'right') === 'left' ? 'dsf-text-image--img-left' : '';
        
        return sprintf(
            '<section class="dsf-text-image %s" style="%s">
                <div class="dsf-container dsf-text-image__grid">
                    <div class="dsf-text-image__content">
                        <h2 class="dsf-text-image__title">%s</h2>
                        <div class="dsf-text-image__text">%s</div>
                    </div>
                    <div class="dsf-text-image__image">
                        <img src="%s" alt="">
                    </div>
                </div>
            </section>',
            $img_pos,
            $style,
            esc_html($s['title'] ?? ''),
            wp_kses_post($s['content'] ?? ''),
            esc_url($s['image'] ?? '')
        );
    }
}
