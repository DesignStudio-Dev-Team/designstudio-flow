<?php
/**
 * DesignStudio Flow Blog / Post-Archive Template
 *
 * Renders a DSF blog template (its blocks bound to the current post archive)
 * in place of the theme archive template. Uses the DSF header/footer (the
 * template's assigned one, or the site-wide default) when available, and falls
 * back to the theme's header/footer otherwise. Theme template files are never
 * modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frontend         = DSF_Frontend::get_instance();
$blog_template_id = $frontend->get_current_blog_template_id();

$show_header   = apply_filters( 'dsf_flow_show_header', true, $blog_template_id );
$show_footer   = apply_filters( 'dsf_flow_show_footer', true, $blog_template_id );
$custom_header = $show_header ? $frontend->render_assigned_layout_template( $blog_template_id, 'header' ) : '';
$custom_footer = $show_footer ? $frontend->render_assigned_layout_template( $blog_template_id, 'footer' ) : '';

$used_theme_header = false;
if ( $show_header && '' === $custom_header ) {
	get_header();
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

echo '<main class="dsf-flow-root dsf-flow-root--blog">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped/kses'd block markup built in DSF_Frontend.
echo $frontend->render_blog_flow_blocks( $blog_template_id );
echo '</main>';

if ( $show_footer && '' !== $custom_footer ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout snapshot is sanitized on save.
	echo $custom_footer;
}

$used_theme_footer = false;
if ( $show_footer && '' === $custom_footer ) {
	get_footer();
	$used_theme_footer = true;
}

if ( ! $used_theme_footer ) {
	wp_footer();
	echo '</body></html>';
}
