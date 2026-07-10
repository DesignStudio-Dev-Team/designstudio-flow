<?php
/**
 * Block Presets — a curated, read-only starter library of pre-configured blocks
 * that editors can drop onto any page from the block picker. Each preset is built
 * from a real registered block's defaults, then a few curated overrides are merged
 * on top (unknown keys are ignored, so a preset is always valid for its type).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Block_Presets {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Curated presets, each resolved against the live block schema.
	 *
	 * @return array[] List of { key, name, type, category, icon, settings }.
	 */
	public function get_presets() {
		if ( ! class_exists( 'DSF_Blocks' ) ) {
			return array();
		}

		$registered = DSF_Blocks::get_instance()->get_registered_blocks();
		$presets    = array();

		foreach ( $this->specs() as $spec ) {
			$def = isset( $registered[ $spec['type'] ] ) ? $registered[ $spec['type'] ] : null;
			if ( ! $def ) {
				continue;
			}

			$settings = $this->defaults_for( $def );
			foreach ( $spec['overrides'] as $key => $value ) {
				// Only override keys the block actually defines — keeps presets valid
				// even if a block's schema changes over time.
				if ( array_key_exists( $key, $settings ) ) {
					$settings[ $key ] = $value;
				}
			}

			$presets[] = array(
				'key'      => $spec['key'],
				'name'     => $spec['name'],
				'type'     => $spec['type'],
				'category' => isset( $def['category'] ) ? $def['category'] : '',
				'icon'     => isset( $def['icon'] ) ? $def['icon'] : 'layout-template',
				'settings' => $settings,
			);
		}

		return $presets;
	}

	private function defaults_for( $def ) {
		$settings = array();
		if ( isset( $def['settings'] ) && is_array( $def['settings'] ) ) {
			foreach ( $def['settings'] as $key => $config ) {
				$settings[ $key ] = ( is_array( $config ) && array_key_exists( 'default', $config ) ) ? $config['default'] : null;
			}
		}
		return $settings;
	}

	private function specs() {
		return array(
			array(
				'key'       => 'hero-bold-dark',
				'name'      => 'Hero — Bold Dark',
				'type'      => 'hero',
				'overrides' => array(
					'title'           => 'Build something remarkable',
					'subtitle'        => 'A modern foundation for your next launch.',
					'buttonText'      => 'Get started',
					'buttonUrl'       => '#',
					'layoutStyle'     => 'centered',
					'backgroundColor' => '#0F172A',
					'textColor'       => '#FFFFFF',
					'buttonColor'     => '#38BDF8',
					'buttonTextColor' => '#0F172A',
				),
			),
			array(
				'key'       => 'hero-clean-light',
				'name'      => 'Hero — Clean Light',
				'type'      => 'hero',
				'overrides' => array(
					'title'           => 'Everything you need, in one place',
					'subtitle'        => 'Tell your story with a calm, focused first impression.',
					'buttonText'      => 'Learn more',
					'buttonUrl'       => '#',
					'layoutStyle'     => 'centered',
					'backgroundColor' => '#F8FAFC',
					'textColor'       => '#0F172A',
					'buttonColor'     => '#0091FF',
					'buttonTextColor' => '#FFFFFF',
				),
			),
			array(
				'key'       => 'features-three-up',
				'name'      => 'Features — Three Up',
				'type'      => 'features-grid',
				'overrides' => array(
					'title'    => 'Why teams choose us',
					'subtitle' => 'Everything you need to ship with confidence.',
					'columns'  => 3,
				),
			),
			array(
				'key'       => 'pricing-three-tier',
				'name'      => 'Pricing — Three Tier',
				'type'      => 'pricing',
				'overrides' => array(
					'eyebrow'     => 'Plans',
					'title'       => 'Simple, transparent pricing',
					'description' => 'Pick the plan that fits today — change it whenever you need to.',
					'accentColor' => '#0091FF',
				),
			),
			array(
				'key'       => 'cta-newsletter',
				'name'      => 'CTA — Newsletter',
				'type'      => 'cta-banner',
				'overrides' => array(
					'title'           => 'Ready to get started?',
					'subtitle'        => 'Join thousands of teams already building with us.',
					'buttonText'      => 'Sign up free',
					'buttonUrl'       => '#',
					'backgroundColor' => '#111827',
					'textColor'       => '#FFFFFF',
					'titleColor'      => '#FFFFFF',
					'buttonColor'     => '#38BDF8',
					'buttonTextColor' => '#0F172A',
				),
			),
			array(
				'key'       => 'faq-support',
				'name'      => 'FAQ — Support',
				'type'      => 'faq',
				'overrides' => array(
					'title' => 'Frequently asked questions',
				),
			),
			array(
				'key'       => 'testimonials-social-proof',
				'name'      => 'Testimonials — Social Proof',
				'type'      => 'testimonials',
				'overrides' => array(
					'backgroundColor' => '#F8FAFC',
				),
			),
			array(
				'key'       => 'footer-marketing',
				'name'      => 'Footer — Marketing',
				'type'      => 'landing-marketing-footer',
				'overrides' => array(),
			),
			// The Redirect Tool and Mail/SMTP sections are the Split Content block
			// prefilled with different content, so they live here as presets while
			// Split Content (landing-product-story) stays in the Blocks tab. Their
			// types are preset_only: still registered for rendering and settings.
			array(
				'key'       => 'split-content-redirects',
				'name'      => 'Split Content — Redirect Manager',
				'type'      => 'landing-redirect-tool',
				'overrides' => array(),
			),
			array(
				'key'       => 'split-content-mail',
				'name'      => 'Split Content — Mail & SMTP',
				'type'      => 'landing-mail-tool',
				'overrides' => array(),
			),
		);
	}
}
