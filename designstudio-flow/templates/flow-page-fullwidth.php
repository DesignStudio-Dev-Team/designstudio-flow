<?php
/**
 * Flow Page Full Width Template
 * Attempts to render Flow blocks edge-to-edge while still using the active theme header/footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flow_post_id = get_queried_object_id();
$frontend     = DSF_Frontend::get_instance();
$show_header  = apply_filters( 'dsf_flow_show_header', true, $flow_post_id );
$show_footer  = apply_filters( 'dsf_flow_show_footer', true, $flow_post_id );

$theme              = wp_get_theme();
$theme_slug         = $theme ? $theme->get_stylesheet() : '';
$parent_slug        = $theme ? $theme->get_template() : '';
$syndified_themes   = array(
	'syndified-theme-main',
	'syndified-theme-child',
	'dsnshowcase-syndified',
);
$is_syndified_theme = in_array( $theme_slug, $syndified_themes, true ) || in_array( $parent_slug, $syndified_themes, true );

if ( $show_header ) {
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
		} elseif ( $header_template && file_exists( $header_template ) ) {
			do_action( 'get_header', null, array() );
			include $header_template;
		} elseif ( file_exists( $child_header ) ) {
			do_action( 'get_header', null, array() );
			include $child_header;
		} elseif ( file_exists( $parent_header ) ) {
			do_action( 'get_header', null, array() );
			include $parent_header;
		} else {
			get_header();
		}
	} else {
		get_header();
	}
}

echo '<main class="dsf-flow-root dsf-flow-root--fullwidth">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output contains vetted block markup.
echo $frontend->render_flow_blocks( $flow_post_id );
echo '</main>';

if ( $show_footer ) {
	if ( $is_syndified_theme ) {
		$footer_template = locate_template( array( 'footer.php' ), false, false );
		$child_footer    = trailingslashit( get_stylesheet_directory() ) . 'footer.php';
		$parent_footer   = trailingslashit( get_template_directory() ) . 'footer.php';

		if ( $footer_template && file_exists( $footer_template ) ) {
			do_action( 'get_footer', null, array() );
			include $footer_template;
		} elseif ( file_exists( $child_footer ) ) {
			do_action( 'get_footer', null, array() );
			include $child_footer;
		} elseif ( file_exists( $parent_footer ) ) {
			do_action( 'get_footer', null, array() );
			include $parent_footer;
		} else {
			get_footer();
		}
	} else {
		get_footer();
	}
}
