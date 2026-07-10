<?php
/**
 * DesignStudio Flow Whole-Site Header/Footer Template
 *
 * Opt-in (Settings → Theme → "Apply to the whole site"). Wraps a normal Page or
 * Post — one the theme would otherwise render — with the DSF site header/footer,
 * while still rendering the post's own content via the_content(). This replaces
 * the theme's page template, so theme sidebars / custom page templates do not
 * apply here; it is gated to Pages and Posts and skips WooCommerce utility pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frontend    = DSF_Frontend::get_instance();
$dsf_post_id = get_queried_object_id();

$show_header   = apply_filters( 'dsf_flow_show_header', true, $dsf_post_id );
$show_footer   = apply_filters( 'dsf_flow_show_footer', true, $dsf_post_id );
$custom_header = $show_header ? $frontend->render_assigned_layout_template( $dsf_post_id, 'header' ) : '';
$custom_footer = $show_footer ? $frontend->render_assigned_layout_template( $dsf_post_id, 'footer' ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'dsf-global-hf' ); ?>>
	<?php
	wp_body_open();

	if ( '' !== $custom_header ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout snapshot is sanitized on save.
		echo $custom_header;
	}
	?>

	<main class="dsf-flow-root dsf-flow-root--global">
		<div class="dsf-global-content">
			<?php
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					?>
					<article <?php post_class( 'dsf-global-article' ); ?>>
						<?php if ( ! is_front_page() && ! post_password_required() ) : ?>
							<h1 class="dsf-global-title"><?php the_title(); ?></h1>
						<?php endif; ?>
						<div class="dsf-global-entry entry-content">
							<?php
							the_content();

							wp_link_pages(
								array(
									'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'designstudio-flow' ),
									'after'  => '</div>',
								)
							);
							?>
						</div>
					</article>
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
				}
			}
			?>
		</div>
	</main>

	<?php
	if ( '' !== $custom_footer ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout snapshot is sanitized on save.
		echo $custom_footer;
	}

	wp_footer();
	?>
</body>
</html>
