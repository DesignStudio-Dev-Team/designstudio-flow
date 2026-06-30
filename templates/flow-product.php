<?php
/**
 * DesignStudio Flow Single-Product Template
 *
 * Renders a DSF product template (its blocks bound to the current product) in
 * place of WooCommerce's single-product template. WooCommerce's own template
 * files are never modified — this whole template is only used for products that
 * resolve to an active DSF product template; all other products keep the native
 * WooCommerce template. The standard theme header/footer are used so WooCommerce
 * notices, cart fragments, and structured data continue to work.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frontend            = DSF_Frontend::get_instance();
$product_id          = get_queried_object_id();
$product_template_id = $frontend->get_current_product_template_id();

get_header( 'shop' );

/**
 * Keep WooCommerce's before/after single-product hooks so other plugins and Woo
 * itself (notices, structured data, sidebars) still run around our content.
 */
if ( function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
}

do_action( 'woocommerce_before_single_product' );

echo '<main class="dsf-flow-root dsf-flow-root--product">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped/kses'd block + fallback markup built in DSF_Frontend.
echo $frontend->render_product_flow_blocks( $product_template_id, $product_id );
echo '</main>';

do_action( 'woocommerce_after_single_product' );

get_footer( 'shop' );
