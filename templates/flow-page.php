<?php
/**
 * DesignStudio Flow Page Template
 * Renders only Flow blocks with the active theme header/footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flow_post_id = get_queried_object_id();
$frontend     = DSF_Frontend::get_instance();
$show_header  = apply_filters( 'dsf_flow_show_header', true, $flow_post_id );
$show_footer  = apply_filters( 'dsf_flow_show_footer', true, $flow_post_id );
$custom_header_markup = $show_header ? $frontend->render_assigned_layout_template( $flow_post_id, 'header' ) : '';
$custom_footer_markup = $show_footer ? $frontend->render_assigned_layout_template( $flow_post_id, 'footer' ) : '';

$theme              = wp_get_theme();
$theme_slug         = $theme ? $theme->get_stylesheet() : '';
$parent_slug        = $theme ? $theme->get_template() : '';
$syndified_themes   = array(
	'syndified-theme-main',
	'syndified-theme-child',
	'dsnshowcase-syndified',
);
$is_syndified_theme = in_array( $theme_slug, $syndified_themes, true ) || in_array( $parent_slug, $syndified_themes, true );

$render_theme_header = function() use ( $is_syndified_theme ) {
	if ( $is_syndified_theme ) {
		$header_template = locate_template( array( 'header.php' ), false, false );
		$child_header    = trailingslashit( get_stylesheet_directory() ) . 'header.php';
		$parent_header   = trailingslashit( get_template_directory() ) . 'header.php';
		$header_found    = null;

		if ( $header_template && file_exists( $header_template ) ) {
			$header_found = $header_template;
		} elseif ( file_exists( $child_header ) ) {
			$header_found = $child_header;
		} elseif ( file_exists( $parent_header ) ) {
			$header_found = $parent_header;
		}

		if ( $header_found ) {
			do_action( 'get_header', null, array() );
			include $header_found;
			return;
		}
	}

	get_header();
};

$render_theme_footer = function() use ( $is_syndified_theme ) {
	if ( $is_syndified_theme ) {
		$footer_template = locate_template( array( 'footer.php' ), false, false );
		$child_footer    = trailingslashit( get_stylesheet_directory() ) . 'footer.php';
		$parent_footer   = trailingslashit( get_template_directory() ) . 'footer.php';

		if ( $footer_template && file_exists( $footer_template ) ) {
			do_action( 'get_footer', null, array() );
			include $footer_template;
			return;
		}
		if ( file_exists( $child_footer ) ) {
			do_action( 'get_footer', null, array() );
			include $child_footer;
			return;
		}
		if ( file_exists( $parent_footer ) ) {
			do_action( 'get_footer', null, array() );
			include $parent_footer;
			return;
		}
	}

	get_footer();
};

$used_theme_header = false;
$used_theme_footer = false;

if ( $show_header && empty( $custom_header_markup ) ) {
	$render_theme_header();
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

if ( $show_header && ! empty( $custom_header_markup ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template snapshot is sanitized on save.
	echo $custom_header_markup;
}

echo '<main class="dsf-flow-root">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output contains vetted block markup.
echo $frontend->render_flow_blocks( $flow_post_id );
echo '</main>';

if ( $show_footer && ! empty( $custom_footer_markup ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template snapshot is sanitized on save.
	echo $custom_footer_markup;
}

if ( $show_footer && empty( $custom_footer_markup ) ) {
	$render_theme_footer();
	$used_theme_footer = true;
}

if ( ! $used_theme_footer ) {
	wp_footer();
	echo '</body></html>';
}
