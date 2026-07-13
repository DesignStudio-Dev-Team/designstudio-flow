<?php
/**
 * DesignStudio Flow Shop / Product-Archive Template
 *
 * Renders a DSF shop template (its blocks bound to the current product archive)
 * in place of WooCommerce's archive template. Uses the DSF header/footer (the
 * template's assigned one, or the site-wide default) when available, and falls
 * back to the theme's shop header/footer otherwise. WooCommerce's own template
 * files are never modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frontend         = DSF_Frontend::get_instance();
$shop_template_id = $frontend->get_current_shop_template_id();

$show_header   = apply_filters( 'dsf_flow_show_header', true, $shop_template_id );
$show_footer   = apply_filters( 'dsf_flow_show_footer', true, $shop_template_id );
$custom_header = $show_header ? $frontend->render_assigned_layout_template( $shop_template_id, 'header' ) : '';
$custom_footer = $show_footer ? $frontend->render_assigned_layout_template( $shop_template_id, 'footer' ) : '';

$used_theme_header = false;
if ( $show_header && '' === $custom_header ) {
	get_header( 'shop' );
	$used_theme_header = true;
}

if ( ! $used_theme_header ) {
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<?php
}

if ( $show_header && '' !== $custom_header ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout snapshot is sanitized on save.
	echo $custom_header;
}

if ( function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
}

echo '<main class="dsf-flow-root dsf-flow-root--shop">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped/kses'd block markup built in DSF_Frontend.
echo $frontend->render_shop_flow_blocks( $shop_template_id );
echo '</main>';

if ( $show_footer && '' !== $custom_footer ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout snapshot is sanitized on save.
	echo $custom_footer;
}

$used_theme_footer = false;
if ( $show_footer && '' === $custom_footer ) {
	get_footer( 'shop' );
	$used_theme_footer = true;
}

if ( ! $used_theme_footer ) {
	wp_footer();
	echo '</body></html>';
}
