<?php
/**
 * Admin Settings Template.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission.
$is_settings_submission = isset( $_POST['dsf_save_settings'] ) || isset( $_POST['dsf_undo_theme_defaults'] );
$has_valid_nonce        = isset( $_POST['dsf_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsf_settings_nonce'] ) ), 'dsf_save_settings' );

if ( $is_settings_submission && ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You are not allowed to change DesignStudio Flow settings.', 'designstudio-flow' ) );
}

if ( isset( $_POST['dsf_undo_theme_defaults'] ) && $has_valid_nonce ) {
	$previous_theme_defaults = get_option( 'dsf_previous_theme_defaults', array() );
	if ( is_array( $previous_theme_defaults ) && ! empty( $previous_theme_defaults['colors'] ) && ! empty( $previous_theme_defaults['typography'] ) ) {
		$current_theme_defaults = array(
			'colors'     => get_option( 'dsf_default_colors', array() ),
			'typography' => get_option( 'dsf_typography', array() ),
		);
		update_option( 'dsf_default_colors', $previous_theme_defaults['colors'] );
		update_option( 'dsf_typography', $previous_theme_defaults['typography'] );
		update_option( 'dsf_previous_theme_defaults', $current_theme_defaults );
		echo '<div class="notice notice-success is-dismissible"><p>Theme defaults restored.</p></div>';
	}
} elseif ( isset( $_POST['dsf_save_settings'] ) && $has_valid_nonce ) {
	// Save settings.
	update_option(
		'dsf_previous_theme_defaults',
		array(
			'colors'     => get_option( 'dsf_default_colors', array( 'primary' => '#2C5F5D', 'secondary' => '#1E40AF', 'text' => '#1F2937', 'background' => '#FFFFFF' ) ),
			'typography' => get_option( 'dsf_typography', array( 'mode' => 'theme', 'heading_font' => '', 'body_font' => '', 'base' => 16, 'scale' => 1.25 ) ),
		)
	);
	$enabled_post_types = array( 'page' );
	update_option( 'dsf_enabled_post_types', $enabled_post_types );

	// Whether the DSF editor / product templates apply to WooCommerce products.
	update_option( 'dsf_products_enabled', isset( $_POST['dsf_products_enabled'] ) ? 1 : 0 );

	$default_colors = array(
		'primary'    => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['dsf_primary_color'] ?? '#2C5F5D' ) ) ),
		'secondary'  => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['dsf_secondary_color'] ?? '#1E40AF' ) ) ),
		'text'       => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['dsf_text_color'] ?? '#1F2937' ) ) ),
		'background' => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['dsf_background_color'] ?? '#FFFFFF' ) ) ),
	);
	update_option( 'dsf_default_colors', $default_colors );

	$recaptcha_enabled         = isset( $_POST['dsf_recaptcha_enabled'] ) ? 1 : 0;
	$recaptcha_site_key        = isset( $_POST['dsf_recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_recaptcha_site_key'] ) ) : '';
	$recaptcha_secret_key      = isset( $_POST['dsf_recaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_recaptcha_secret_key'] ) ) : '';
	$recaptcha_threshold_input = isset( $_POST['dsf_recaptcha_threshold'] ) ? floatval( wp_unslash( $_POST['dsf_recaptcha_threshold'] ) ) : 0.5;
	$recaptcha_threshold       = min( 1, max( 0, $recaptcha_threshold_input ) );

	update_option( 'dsf_recaptcha_enabled', $recaptcha_enabled );
	update_option( 'dsf_recaptcha_site_key', $recaptcha_site_key );
	update_option( 'dsf_recaptcha_secret_key', DSF_Crypto::encrypt( $recaptcha_secret_key ) );
	update_option( 'dsf_recaptcha_threshold', $recaptcha_threshold );

	// Global SEO defaults: the fallback og:image, the {sep} title separator, and
	// the site's Organization identity + social profiles that feed JSON-LD.
	$dsf_allowed_separators = array( '–', '—', '-', '|', '·', '•', ':', '/' );
	$dsf_title_separator    = isset( $_POST['dsf_seo_title_separator'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_seo_title_separator'] ) ) : '–';
	if ( ! in_array( $dsf_title_separator, $dsf_allowed_separators, true ) ) {
		$dsf_title_separator = '–';
	}

	$dsf_twitter_site = isset( $_POST['dsf_seo_twitter_site'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_seo_twitter_site'] ) ) : '';
	$dsf_twitter_site = preg_replace( '/[^A-Za-z0-9_]/', '', ltrim( trim( $dsf_twitter_site ), '@' ) );
	$dsf_twitter_site = '' !== $dsf_twitter_site ? '@' . substr( $dsf_twitter_site, 0, 15 ) : '';

	$dsf_social_profiles = array();
	foreach ( array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok' ) as $dsf_network ) {
		$dsf_profile_url = isset( $_POST[ 'dsf_seo_social_' . $dsf_network ] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST[ 'dsf_seo_social_' . $dsf_network ] ) ) ) : '';
		if ( '' !== $dsf_profile_url && preg_match( '#^https?://#i', $dsf_profile_url ) ) {
			$dsf_social_profiles[] = $dsf_profile_url;
		}
	}

	update_option(
		'dsf_seo_defaults',
		array(
			'defaultSocialImage' => isset( $_POST['dsf_seo_default_image'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['dsf_seo_default_image'] ) ) ) : '',
			'titleSeparator'     => $dsf_title_separator,
			'orgName'            => isset( $_POST['dsf_seo_org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_seo_org_name'] ) ) : '',
			'orgLogo'            => isset( $_POST['dsf_seo_org_logo'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['dsf_seo_org_logo'] ) ) ) : '',
			'twitterSite'        => $dsf_twitter_site,
			'socialProfiles'     => $dsf_social_profiles,
		)
	);

	// Typography defaults.
	$typography_mode  = ( isset( $_POST['dsf_typography_mode'] ) && 'override' === $_POST['dsf_typography_mode'] ) ? 'override' : 'theme';
	$typography_base  = isset( $_POST['dsf_typography_base'] ) ? floatval( wp_unslash( $_POST['dsf_typography_base'] ) ) : 16.0;
	$typography_scale = isset( $_POST['dsf_typography_scale'] ) ? floatval( wp_unslash( $_POST['dsf_typography_scale'] ) ) : 1.25;
	$typography_base  = max( 12.0, min( 22.0, $typography_base ) );
	$typography_scale = max( 1.05, min( 1.6, $typography_scale ) );

	// Explicit per-element sizes (px). Blank / 0 means "automatic".
	$dsf_clamp_size = static function ( $key ) {
		$value = isset( $_POST[ $key ] ) ? floatval( wp_unslash( $_POST[ $key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		if ( $value <= 0 ) {
			return 0.0;
		}
		return max( 8.0, min( 200.0, $value ) );
	};
	// Per-breakpoint base body size. Blank / 0 means "inherit desktop".
	$dsf_clamp_base_bp = static function ( $key ) {
		$value = isset( $_POST[ $key ] ) ? floatval( wp_unslash( $_POST[ $key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		return $value > 0 ? max( 12.0, min( 22.0, $value ) ) : 0.0;
	};

	$container_width = isset( $_POST['dsf_container_width'] ) ? intval( wp_unslash( $_POST['dsf_container_width'] ) ) : 1800;
	$container_width = $container_width > 0 ? max( 320, min( 3000, $container_width ) ) : 1800;

	$typography = array(
		'mode'            => $typography_mode,
		'heading_font'    => isset( $_POST['dsf_typography_heading_font'] ) ? DSF_Frontend::sanitize_font_family( wp_unslash( $_POST['dsf_typography_heading_font'] ) ) : '',
		'body_font'       => isset( $_POST['dsf_typography_body_font'] ) ? DSF_Frontend::sanitize_font_family( wp_unslash( $_POST['dsf_typography_body_font'] ) ) : '',
		'base'            => $typography_base,
		'scale'           => $typography_scale,
		'size_p'          => $dsf_clamp_size( 'dsf_size_p' ),
		'size_h1'         => $dsf_clamp_size( 'dsf_size_h1' ),
		'size_h2'         => $dsf_clamp_size( 'dsf_size_h2' ),
		'size_h3'         => $dsf_clamp_size( 'dsf_size_h3' ),
		'size_h4'         => $dsf_clamp_size( 'dsf_size_h4' ),
		'container_width' => $container_width,
	);

	// Laptop + mobile responsive sizes (blank inherits the desktop value).
	foreach ( array( 'laptop', 'mobile' ) as $dsf_bp ) {
		$typography[ 'base_' . $dsf_bp ]    = $dsf_clamp_base_bp( 'dsf_typography_base_' . $dsf_bp );
		$typography[ 'size_p_' . $dsf_bp ]  = $dsf_clamp_size( 'dsf_size_p_' . $dsf_bp );
		$typography[ 'size_h1_' . $dsf_bp ] = $dsf_clamp_size( 'dsf_size_h1_' . $dsf_bp );
		$typography[ 'size_h2_' . $dsf_bp ] = $dsf_clamp_size( 'dsf_size_h2_' . $dsf_bp );
		$typography[ 'size_h3_' . $dsf_bp ] = $dsf_clamp_size( 'dsf_size_h3_' . $dsf_bp );
		$typography[ 'size_h4_' . $dsf_bp ] = $dsf_clamp_size( 'dsf_size_h4_' . $dsf_bp );
	}

	update_option( 'dsf_typography', $typography );

	// Default header / footer (applied to pages that don't pick their own).
	$dsf_validate_layout = static function ( $raw_id, $type ) {
		$id = absint( wp_unslash( $raw_id ) );
		if ( ! $id ) {
			return 0;
		}
		$layout_post = get_post( $id );
		if ( ! $layout_post || 'dsf_layout' !== $layout_post->post_type ) {
			return 0;
		}
		$stored = get_post_meta( $id, '_dsf_layout_type', true );
		$stored = 'footer' === $stored ? 'footer' : 'header';
		return $stored === $type ? $id : 0;
	};

	$dsf_default_header = $dsf_validate_layout( $_POST['dsf_default_header_id'] ?? 0, 'header' );
	$dsf_default_footer = $dsf_validate_layout( $_POST['dsf_default_footer_id'] ?? 0, 'footer' );
	update_option( 'dsf_default_header_id', $dsf_default_header );
	update_option( 'dsf_default_footer_id', $dsf_default_footer );

	// Push the chosen header/footer onto every existing DSF page + product
	// template so they all switch to it now. Future pages inherit it via the
	// default fallback. (Only when a real layout is chosen — "None" leaves pages
	// untouched.)
	$dsf_layout_updates = 0;
	if ( $dsf_default_header ) {
		$dsf_layout_updates += DSF_Frontend::apply_layout_to_all_flow_content( 'header', $dsf_default_header );
	}
	if ( $dsf_default_footer ) {
		$dsf_layout_updates += DSF_Frontend::apply_layout_to_all_flow_content( 'footer', $dsf_default_footer );
	}

	// Whole-site mode: apply the header/footer to normal (non-DSF) pages/posts too.
	update_option( 'dsf_global_header_footer', isset( $_POST['dsf_global_header_footer'] ) ? 1 : 0 );

	$notification_bar = DSF_Notification_Bar::sanitize_settings(
		array(
			'enabled'         => isset( $_POST['dsf_notification_enabled'] ),
			'message'         => isset( $_POST['dsf_notification_message'] ) ? wp_kses_post( wp_unslash( $_POST['dsf_notification_message'] ) ) : '',
			'linkText'        => isset( $_POST['dsf_notification_link_text'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_notification_link_text'] ) ) : '',
			'linkUrl'         => isset( $_POST['dsf_notification_link_url'] ) ? esc_url_raw( wp_unslash( $_POST['dsf_notification_link_url'] ) ) : '',
			'openNewTab'      => isset( $_POST['dsf_notification_new_tab'] ),
			'dismissible'     => isset( $_POST['dsf_notification_dismissible'] ),
			'cookieHours'     => isset( $_POST['dsf_notification_cookie_hours'] ) ? absint( wp_unslash( $_POST['dsf_notification_cookie_hours'] ) ) : 24,
			'sticky'          => isset( $_POST['dsf_notification_sticky'] ),
			'alignment'       => isset( $_POST['dsf_notification_alignment'] ) ? sanitize_key( wp_unslash( $_POST['dsf_notification_alignment'] ) ) : 'center',
			'startDate'       => isset( $_POST['dsf_notification_start'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_notification_start'] ) ) : '',
			'endDate'         => isset( $_POST['dsf_notification_end'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_notification_end'] ) ) : '',
			'backgroundColor' => isset( $_POST['dsf_notification_background'] ) ? sanitize_hex_color( wp_unslash( $_POST['dsf_notification_background'] ) ) : '#2C5F5D',
			'textColor'       => isset( $_POST['dsf_notification_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['dsf_notification_text_color'] ) ) : '#FFFFFF',
			'linkColor'       => isset( $_POST['dsf_notification_link_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['dsf_notification_link_color'] ) ) : '#FFFFFF',
		)
	);
	update_option( 'dsf_notification_bar', $notification_bar );

	echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

// Get current settings.
$enabled_post_types   = get_option( 'dsf_enabled_post_types', array( 'page' ) );
$products_enabled     = (bool) get_option( 'dsf_products_enabled', true );
$default_colors       = get_option(
	'dsf_default_colors',
	array(
		'primary'    => '#2C5F5D',
		'secondary'  => '#1E40AF',
		'text'       => '#1F2937',
		'background' => '#FFFFFF',
	)
);
$recaptcha_enabled    = (bool) get_option( 'dsf_recaptcha_enabled', false );
$recaptcha_site_key   = get_option( 'dsf_recaptcha_site_key', '' );
$recaptcha_secret_key = DSF_Crypto::decrypt( get_option( 'dsf_recaptcha_secret_key', '' ) );
$recaptcha_threshold  = floatval( get_option( 'dsf_recaptcha_threshold', 0.5 ) );
$notification_bar     = DSF_Notification_Bar::get_settings();
$seo_defaults         = class_exists( 'DSF_SEO' ) ? DSF_SEO::get_defaults() : array(
	'defaultSocialImage' => '',
	'titleSeparator'     => '–',
	'orgName'            => '',
	'orgLogo'            => '',
	'twitterSite'        => '',
	'socialProfiles'     => array(),
);
$seo_social_by_network = array();
foreach ( (array) ( $seo_defaults['socialProfiles'] ?? array() ) as $seo_profile_url ) {
	$seo_host = wp_parse_url( (string) $seo_profile_url, PHP_URL_HOST );
	$seo_host = $seo_host ? strtolower( $seo_host ) : '';
	if ( false !== strpos( $seo_host, 'facebook' ) ) {
		$seo_social_by_network['facebook'] = $seo_profile_url;
	} elseif ( false !== strpos( $seo_host, 'twitter' ) || false !== strpos( $seo_host, 'x.com' ) ) {
		$seo_social_by_network['twitter'] = $seo_profile_url;
	} elseif ( false !== strpos( $seo_host, 'instagram' ) ) {
		$seo_social_by_network['instagram'] = $seo_profile_url;
	} elseif ( false !== strpos( $seo_host, 'linkedin' ) ) {
		$seo_social_by_network['linkedin'] = $seo_profile_url;
	} elseif ( false !== strpos( $seo_host, 'youtube' ) ) {
		$seo_social_by_network['youtube'] = $seo_profile_url;
	} elseif ( false !== strpos( $seo_host, 'tiktok' ) ) {
		$seo_social_by_network['tiktok'] = $seo_profile_url;
	}
}
$previous_theme_value = get_option( 'dsf_previous_theme_defaults', array() );
$has_previous_theme   = is_array( $previous_theme_value ) && ! empty( $previous_theme_value['colors'] ) && ! empty( $previous_theme_value['typography'] );

$typography_option = get_option(
	'dsf_typography',
	array(
		'mode'         => 'theme',
		'heading_font' => '',
		'body_font'    => '',
		'base'         => 16,
		'scale'        => 1.25,
	)
);
$typography_mode   = $typography_option['mode'] ?? 'theme';
$typography_hfont  = $typography_option['heading_font'] ?? '';
$typography_bfont  = $typography_option['body_font'] ?? '';
$typography_base   = floatval( $typography_option['base'] ?? 16 );
$typography_scale  = floatval( $typography_option['scale'] ?? 1.25 );

// Available headers/footers for the site-default picker.
$dsf_layout_posts   = get_posts(
	array(
		'post_type'      => 'dsf_layout',
		'post_status'    => array( 'publish', 'draft' ),
		'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded admin picker list.
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
$dsf_header_options = array();
$dsf_footer_options = array();
foreach ( $dsf_layout_posts as $dsf_layout_post ) {
	$dsf_lt    = get_post_meta( $dsf_layout_post->ID, '_dsf_layout_type', true );
	$dsf_lt    = 'footer' === $dsf_lt ? 'footer' : 'header';
	$dsf_entry = array(
		'id'     => (int) $dsf_layout_post->ID,
		'title'  => $dsf_layout_post->post_title ? $dsf_layout_post->post_title : __( '(no title)', 'designstudio-flow' ),
		'status' => $dsf_layout_post->post_status,
	);
	if ( 'footer' === $dsf_lt ) {
		$dsf_footer_options[] = $dsf_entry;
	} else {
		$dsf_header_options[] = $dsf_entry;
	}
}
$dsf_default_header_id = absint( get_option( 'dsf_default_header_id', 0 ) );
$dsf_default_footer_id = absint( get_option( 'dsf_default_footer_id', 0 ) );
$dsf_global_hf         = (bool) get_option( 'dsf_global_header_footer', false );

// Normalized values (per-element sizes + container width) for the form fields.
$typography_norm     = DSF_Frontend::get_typography_option();
$size_field_value    = static function ( $value ) {
	$value = floatval( $value );
	return $value > 0 ? (string) ( 0 === fmod( $value, 1.0 ) ? (int) $value : $value ) : '';
};
$container_width_val = (int) $typography_norm['container_width'];

// Content-sizing field values per breakpoint. Desktop base uses the 12–22 base;
// laptop/mobile base + all element sizes are blank when 0 (inherit desktop).
$dsf_bp_field = static function ( $key ) use ( $typography_norm, $size_field_value ) {
	return $size_field_value( $typography_norm[ $key ] ?? 0 );
};

$typography_sizes = array(
	'desktop' => array(
		// Desktop base is the modular-scale driver (always has a value).
		'base' => (string) ( 0 === fmod( (float) $typography_base, 1.0 ) ? (int) $typography_base : $typography_base ),
		'p'    => $dsf_bp_field( 'size_p' ),
		'h1'   => $dsf_bp_field( 'size_h1' ),
		'h2'   => $dsf_bp_field( 'size_h2' ),
		'h3'   => $dsf_bp_field( 'size_h3' ),
		'h4'   => $dsf_bp_field( 'size_h4' ),
	),
	'laptop'  => array(
		'base' => $dsf_bp_field( 'base_laptop' ),
		'p'    => $dsf_bp_field( 'size_p_laptop' ),
		'h1'   => $dsf_bp_field( 'size_h1_laptop' ),
		'h2'   => $dsf_bp_field( 'size_h2_laptop' ),
		'h3'   => $dsf_bp_field( 'size_h3_laptop' ),
		'h4'   => $dsf_bp_field( 'size_h4_laptop' ),
	),
	'mobile'  => array(
		'base' => $dsf_bp_field( 'base_mobile' ),
		'p'    => $dsf_bp_field( 'size_p_mobile' ),
		'h1'   => $dsf_bp_field( 'size_h1_mobile' ),
		'h2'   => $dsf_bp_field( 'size_h2_mobile' ),
		'h3'   => $dsf_bp_field( 'size_h3_mobile' ),
		'h4'   => $dsf_bp_field( 'size_h4_mobile' ),
	),
);
$dsf_breakpoints  = array(
	'desktop' => 'Desktop',
	'laptop'  => 'Laptop',
	'mobile'  => 'Mobile',
);

$scale_options = array(
	'1.125' => 'Minor Second (1.125)',
	'1.2'   => 'Minor Third (1.2)',
	'1.25'  => 'Major Third (1.25)',
	'1.333' => 'Perfect Fourth (1.333)',
	'1.414' => 'Augmented Fourth (1.414)',
	'1.5'   => 'Perfect Fifth (1.5)',
);

$font_options = array(
	"'Inter', sans-serif",
	"'Roboto', sans-serif",
	"'Open Sans', sans-serif",
	"'Lato', sans-serif",
	"'Montserrat', sans-serif",
	"'Poppins', sans-serif",
	"'Outfit', sans-serif",
	"'Source Sans 3', sans-serif",
	"'Nunito', sans-serif",
	"'Raleway', sans-serif",
	"'Playfair Display', serif",
	"'Merriweather', serif",
	"'Lora', serif",
	"'DM Sans', sans-serif",
	"'Work Sans', sans-serif",
	"'Oswald', sans-serif",
	"'Ubuntu', sans-serif",
	"'Rubik', sans-serif",
	"'Manrope', sans-serif",
	"'Space Grotesk', sans-serif",
);
?>

<div class="wrap dsf-admin-settings">
	<h1>
		<span class="dashicons dashicons-admin-settings" style="margin-right: 8px;"></span>
		DesignStudio Flow Settings
	</h1>
	
	<hr class="wp-header-end">
	
	<form method="post" action="">
		<?php wp_nonce_field( 'dsf_save_settings', 'dsf_settings_nonce' ); ?>
		
		<div style="max-width: 800px;">
			<datalist id="dsf-font-family-options">
				<?php foreach ( $font_options as $font_option ) : ?>
					<option value="<?php echo esc_attr( $font_option ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<h2 class="nav-tab-wrapper dsf-settings-tabs" style="margin-bottom: 0;">
				<a href="#general" class="nav-tab nav-tab-active" data-dsf-tab-link="general">General</a>
				<a href="#theme" class="nav-tab" data-dsf-tab-link="theme">Theme</a>
				<a href="#seo" class="nav-tab" data-dsf-tab-link="seo">SEO</a>
				<a href="#notification" class="nav-tab" data-dsf-tab-link="notification">Notification Bar</a>
				<a href="#recaptcha" class="nav-tab" data-dsf-tab-link="recaptcha">reCAPTCHA</a>
			</h2>
			<style>
				/* Show only the first tab until the switcher script runs (no FOUC). */
				.dsf-card[data-dsf-tab]:not([data-dsf-tab="general"]) { display: none; }
			</style>

			<!-- General Settings -->
			<div class="dsf-card" data-dsf-tab="general" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">General Settings</h2>
				
				<table class="form-table">
					<tr>
						<th scope="row">Enable DesignStudio Flow Editor for</th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_enabled_post_types[]" value="page"
									checked disabled>
								Pages
							</label>
							<input type="hidden" name="dsf_enabled_post_types[]" value="page">
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_products_enabled" value="1" <?php checked( $products_enabled ); ?>>
								WooCommerce Products
							</label>
							<p class="description">Pages always use DesignStudio Flow. Enable Products to design single-product pages with Product Templates (requires WooCommerce). When off, products render with the default WooCommerce template.</p>
						</td>
					</tr>
				</table>
			</div>
			
			<!-- Default Colors -->
			<div class="dsf-card" data-dsf-tab="theme" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Default Colors</h2>
				<p class="description">These are the same site defaults shown in each page's Theme window. New blocks inherit them automatically.</p>
				<p>
					<button type="submit" class="button" name="dsf_undo_theme_defaults" value="1" <?php disabled( ! $has_previous_theme ); ?>>Undo Last Saved Theme Change</button>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">Primary Color</th>
						<td>
							<input type="color" name="dsf_primary_color" 
								value="<?php echo esc_attr( $default_colors['primary'] ); ?>">
							<span class="description" style="margin-left: 8px;">
								<?php echo esc_html( $default_colors['primary'] ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row">Secondary Color</th>
						<td>
							<input type="color" name="dsf_secondary_color" 
								value="<?php echo esc_attr( $default_colors['secondary'] ); ?>">
							<span class="description" style="margin-left: 8px;">
								<?php echo esc_html( $default_colors['secondary'] ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row">Text Color</th>
						<td>
							<input type="color" name="dsf_text_color" 
								value="<?php echo esc_attr( $default_colors['text'] ); ?>">
							<span class="description" style="margin-left: 8px;">
								<?php echo esc_html( $default_colors['text'] ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row">Background Color</th>
						<td>
							<input type="color" name="dsf_background_color" 
								value="<?php echo esc_attr( $default_colors['background'] ); ?>">
							<span class="description" style="margin-left: 8px;">
								<?php echo esc_html( $default_colors['background'] ); ?>
							</span>
						</td>
					</tr>
				</table>
			</div>

			<!-- Default Header & Footer -->
			<div class="dsf-card" data-dsf-tab="theme" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Default Header &amp; Footer</h2>
				<p class="description">Pick the header and footer used across the site. On <strong>Save</strong>, the chosen header/footer is applied to every existing DesignStudio Flow page and product template, and future pages inherit them automatically. (This overrides any per-page header/footer chosen in a page's Theme panel.)</p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="dsf_default_header_id">Default Header</label></th>
						<td>
							<select id="dsf_default_header_id" name="dsf_default_header_id">
								<option value="0"><?php esc_html_e( 'None', 'designstudio-flow' ); ?></option>
								<?php foreach ( $dsf_header_options as $dsf_opt ) : ?>
									<option value="<?php echo esc_attr( (string) $dsf_opt['id'] ); ?>" <?php selected( $dsf_opt['id'], $dsf_default_header_id ); ?>>
										<?php
										echo esc_html( $dsf_opt['title'] );
										echo 'publish' !== $dsf_opt['status'] ? ' ' . esc_html__( '(draft)', 'designstudio-flow' ) : '';
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( empty( $dsf_header_options ) ) : ?>
								<p class="description"><?php esc_html_e( 'No headers yet. Create one under DesignStudio Flow → Headers.', 'designstudio-flow' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_default_footer_id">Default Footer</label></th>
						<td>
							<select id="dsf_default_footer_id" name="dsf_default_footer_id">
								<option value="0"><?php esc_html_e( 'None', 'designstudio-flow' ); ?></option>
								<?php foreach ( $dsf_footer_options as $dsf_opt ) : ?>
									<option value="<?php echo esc_attr( (string) $dsf_opt['id'] ); ?>" <?php selected( $dsf_opt['id'], $dsf_default_footer_id ); ?>>
										<?php
										echo esc_html( $dsf_opt['title'] );
										echo 'publish' !== $dsf_opt['status'] ? ' ' . esc_html__( '(draft)', 'designstudio-flow' ) : '';
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( empty( $dsf_footer_options ) ) : ?>
								<p class="description"><?php esc_html_e( 'No footers yet. Create one under DesignStudio Flow → Footers.', 'designstudio-flow' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Apply to the whole site</th>
						<td>
							<label>
								<input type="checkbox" name="dsf_global_header_footer" value="1" <?php checked( $dsf_global_hf ); ?>>
								Also show this header/footer on normal (non-DSF) Pages and Posts
							</label>
							<p class="description">
								When on, regular WordPress Pages and Posts are wrapped with the header/footer above (their content still shows). <strong>This replaces the theme's page layout</strong> for those pages, so theme sidebars and custom page templates won't apply. Archives, search, 404, and WooCommerce cart/checkout/account/shop pages are left to the theme.
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Typography -->
			<div class="dsf-card" data-dsf-tab="theme" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Typography</h2>
				<p class="description">Controls the modular scale, font families, and per-device content sizing used by every DesignStudio Flow block.</p>

				<table class="form-table">
					<tr>
						<th scope="row">Source</th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="dsf_typography_mode" value="theme"
									<?php checked( 'theme', $typography_mode ); ?>>
								Use active WordPress theme typography
							</label>
							<label style="display: block;">
								<input type="radio" name="dsf_typography_mode" value="override"
									<?php checked( 'override', $typography_mode ); ?>>
								Override with plugin settings below
							</label>
							<p class="description">When the active theme is a block theme, body size auto-reads from <code>theme.json</code>. Override gives you direct control.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_container_width">Content max width</label></th>
						<td>
							<input type="number" id="dsf_container_width" name="dsf_container_width" min="320" max="3000" step="10" value="<?php echo esc_attr( (string) $container_width_val ); ?>">
							<span class="description" style="margin-left: 8px;">px</span>
							<p class="description">Maximum width of the main page container. Individual pages can override this in the editor's Theme panel.</p>
						</td>
					</tr>
				</table>

				<div id="dsf-typography-overrides" style="<?php echo 'override' === $typography_mode ? '' : 'display:none;'; ?>">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsf_typography_heading_font">Heading Font</label></th>
							<td>
								<input type="text" class="regular-text" id="dsf_typography_heading_font"
									name="dsf_typography_heading_font"
									list="dsf-font-family-options"
									value="<?php echo esc_attr( $typography_hfont ); ?>"
									placeholder="e.g. 'Inter', sans-serif">
								<p class="description">Any CSS <code>font-family</code> value. Leave empty to inherit from the active theme.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf_typography_body_font">Body Font</label></th>
							<td>
								<input type="text" class="regular-text" id="dsf_typography_body_font"
									name="dsf_typography_body_font"
									list="dsf-font-family-options"
									value="<?php echo esc_attr( $typography_bfont ); ?>"
									placeholder="e.g. 'Inter', sans-serif">
								<p class="description">Used for paragraphs, lists, and body copy.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsf_typography_scale">Scale Ratio</label></th>
							<td>
								<select id="dsf_typography_scale" name="dsf_typography_scale">
									<?php foreach ( $scale_options as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"
											<?php selected( (float) $value, $typography_scale ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="button" id="dsf-typo-recalc" class="button" style="margin-left: 8px;">Recalculate sizes</button>
								<p class="description">Each step up multiplies by this ratio. Changing the ratio (or the Desktop Body Size) recalculates the Desktop content sizes below. Save to apply.</p>
							</td>
						</tr>
					</table>

					<h3 style="margin: 22px 0 4px;">Content Sizing</h3>
					<p class="description" style="margin-top: 0;">Set the body and heading sizes per device. Leave a value blank on Laptop or Mobile to inherit the Desktop size.</p>

					<div class="dsf-typo-tabs" role="tablist" aria-label="Content sizing device">
						<?php foreach ( $dsf_breakpoints as $dsf_bp_key => $dsf_bp_label ) : ?>
							<button type="button" class="dsf-typo-tab<?php echo 'desktop' === $dsf_bp_key ? ' is-active' : ''; ?>"
								role="tab" data-bp="<?php echo esc_attr( $dsf_bp_key ); ?>"
								aria-selected="<?php echo 'desktop' === $dsf_bp_key ? 'true' : 'false'; ?>">
								<?php echo esc_html( $dsf_bp_label ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<?php foreach ( $dsf_breakpoints as $dsf_bp_key => $dsf_bp_label ) : ?>
						<?php
						$dsf_is_desktop = 'desktop' === $dsf_bp_key;
						$dsf_suffix     = $dsf_is_desktop ? '' : '_' . $dsf_bp_key;
						$dsf_vals       = $typography_sizes[ $dsf_bp_key ];
						$dsf_size_ph    = $dsf_is_desktop ? 'auto' : 'inherit';
						// Field rows: element key => [label, min, max, step, php-name].
						$dsf_rows = array(
							array(
								'label' => 'Body Size',
								'name'  => 'dsf_typography_base' . $dsf_suffix,
								'min'   => 12,
								'max'   => 22,
								'step'  => 0.5,
								'val'   => $dsf_vals['base'],
								'ph'    => $dsf_is_desktop ? '16' : 'inherit',
							),
						);

						$dsf_size_elements = array(
							'p'  => 'Paragraph (p)',
							'h1' => 'Heading 1 (h1)',
							'h2' => 'Heading 2 (h2)',
							'h3' => 'Heading 3 (h3)',
							'h4' => 'Heading 4 (h4)',
						);
						foreach ( $dsf_size_elements as $dsf_el => $dsf_el_label ) {
							$dsf_rows[] = array(
								'label' => $dsf_el_label,
								'name'  => 'dsf_size_' . $dsf_el . $dsf_suffix,
								'min'   => 8,
								'max'   => 200,
								'step'  => 1,
								'val'   => $dsf_vals[ $dsf_el ],
								'ph'    => $dsf_size_ph,
							);
						}
						?>
						<div class="dsf-typo-panel" data-bp="<?php echo esc_attr( $dsf_bp_key ); ?>" style="<?php echo $dsf_is_desktop ? '' : 'display:none;'; ?>">
							<table class="form-table">
								<?php foreach ( $dsf_rows as $dsf_row ) : ?>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr( $dsf_row['name'] ); ?>"><?php echo esc_html( $dsf_row['label'] ); ?></label></th>
										<td>
											<input type="number" id="<?php echo esc_attr( $dsf_row['name'] ); ?>" name="<?php echo esc_attr( $dsf_row['name'] ); ?>"
												min="<?php echo esc_attr( (string) $dsf_row['min'] ); ?>" max="<?php echo esc_attr( (string) $dsf_row['max'] ); ?>" step="<?php echo esc_attr( (string) $dsf_row['step'] ); ?>"
												value="<?php echo esc_attr( (string) $dsf_row['val'] ); ?>" placeholder="<?php echo esc_attr( $dsf_row['ph'] ); ?>">
											<span class="description" style="margin-left: 8px;">px</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					<?php endforeach; ?>
				</div>

				<style>
				.dsf-typo-tabs { display: flex; gap: 4px; margin: 8px 0 4px; border-bottom: 1px solid #dcdcde; }
				.dsf-typo-tab { padding: 8px 16px; border: 0; border-bottom: 2px solid transparent; background: transparent; cursor: pointer; font-weight: 600; color: #646970; }
				.dsf-typo-tab.is-active { color: #2271b1; border-bottom-color: #2271b1; }
				</style>

				<script>
				(function () {
					var radios = document.querySelectorAll('input[name="dsf_typography_mode"]');
					var panel  = document.getElementById('dsf-typography-overrides');
					if (panel && radios.length) {
						radios.forEach(function (r) {
							r.addEventListener('change', function () {
								panel.style.display = (this.value === 'override' && this.checked) ? '' : 'none';
							});
						});
					}

					var tabs   = document.querySelectorAll('.dsf-typo-tab');
					var panels = document.querySelectorAll('.dsf-typo-panel');
					tabs.forEach(function (tab) {
						tab.addEventListener('click', function () {
							var bp = this.getAttribute('data-bp');
							tabs.forEach(function (t) {
								var active = t === tab;
								t.classList.toggle('is-active', active);
								t.setAttribute('aria-selected', active ? 'true' : 'false');
							});
							panels.forEach(function (p) {
								p.style.display = (p.getAttribute('data-bp') === bp) ? '' : 'none';
							});
						});
					});

					// Recalculate the Desktop content sizes from the body size × scale
					// ratio. Runs when the Scale Ratio or Desktop Body Size changes, or
					// via the "Recalculate" button. Laptop/Mobile inherit Desktop.
					function setField(id, value) {
						var el = document.getElementById(id);
						if (el) el.value = String(Math.round(value));
					}
					function recomputeFromScale() {
						var baseEl  = document.getElementById('dsf_typography_base');
						var scaleEl = document.getElementById('dsf_typography_scale');
						if (!baseEl || !scaleEl) return;
						var base  = parseFloat(baseEl.value) || 16;
						var scale = parseFloat(scaleEl.value) || 1.25;
						setField('dsf_size_p', base);
						setField('dsf_size_h4', base * scale);
						setField('dsf_size_h3', base * Math.pow(scale, 2));
						setField('dsf_size_h2', base * Math.pow(scale, 3));
						setField('dsf_size_h1', base * Math.pow(scale, 4));
					}
					var scaleEl = document.getElementById('dsf_typography_scale');
					var baseEl  = document.getElementById('dsf_typography_base');
					if (scaleEl) scaleEl.addEventListener('change', recomputeFromScale);
					if (baseEl)  baseEl.addEventListener('change', recomputeFromScale);
					var recalcBtn = document.getElementById('dsf-typo-recalc');
					if (recalcBtn) recalcBtn.addEventListener('click', recomputeFromScale);
				})();
				</script>
			</div>

			<!-- SEO Defaults -->
			<div class="dsf-card" data-dsf-tab="seo" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">SEO Defaults</h2>
				<p class="description">Site-wide fallbacks used when a page has no SEO settings of its own, plus the business identity that powers search &amp; social rich results (JSON-LD). If a dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) is active, DesignStudio Flow defers to it and outputs nothing.</p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="dsf_seo_default_image">Default social image</label></th>
						<td>
							<input type="url" id="dsf_seo_default_image" name="dsf_seo_default_image" class="regular-text" value="<?php echo esc_attr( $seo_defaults['defaultSocialImage'] ); ?>" placeholder="https://…">
							<p class="description">Fallback <code>og:image</code> for shares when a page has no social image and no hero image. Use at least 1200×630px.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_seo_title_separator">Title separator</label></th>
						<td>
							<select id="dsf_seo_title_separator" name="dsf_seo_title_separator">
								<?php foreach ( array( '–', '—', '-', '|', '·', '•', ':', '/' ) as $dsf_sep_option ) : ?>
									<option value="<?php echo esc_attr( $dsf_sep_option ); ?>" <?php selected( $seo_defaults['titleSeparator'], $dsf_sep_option ); ?>><?php echo esc_html( $dsf_sep_option ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Used for the <code>{sep}</code> variable in SEO titles, e.g. <em>Page Title <?php echo esc_html( $seo_defaults['titleSeparator'] ); ?> Site Name</em>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_seo_org_name">Organization / brand name</label></th>
						<td>
							<input type="text" id="dsf_seo_org_name" name="dsf_seo_org_name" class="regular-text" value="<?php echo esc_attr( $seo_defaults['orgName'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description">Publisher name in structured data. Defaults to the site name when blank.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_seo_org_logo">Organization logo</label></th>
						<td>
							<input type="url" id="dsf_seo_org_logo" name="dsf_seo_org_logo" class="regular-text" value="<?php echo esc_attr( $seo_defaults['orgLogo'] ); ?>" placeholder="https://…">
							<p class="description">Square logo used in the Organization schema. Defaults to the Site Icon when blank.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf_seo_twitter_site">X (Twitter) handle</label></th>
						<td>
							<input type="text" id="dsf_seo_twitter_site" name="dsf_seo_twitter_site" class="regular-text" value="<?php echo esc_attr( $seo_defaults['twitterSite'] ); ?>" placeholder="@yourbrand">
							<p class="description">Output as <code>twitter:site</code> on shared cards.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Social profiles</th>
						<td>
							<p class="description" style="margin-top: 0;">Official profile URLs. Added to structured data as <code>sameAs</code> so search engines can connect your brand's accounts.</p>
							<?php
							$dsf_social_labels = array(
								'facebook'  => 'Facebook',
								'twitter'   => 'X (Twitter)',
								'instagram' => 'Instagram',
								'linkedin'  => 'LinkedIn',
								'youtube'   => 'YouTube',
								'tiktok'    => 'TikTok',
							);
							foreach ( $dsf_social_labels as $dsf_net_key => $dsf_net_label ) :
								?>
								<p style="margin: 6px 0;">
									<label style="display:inline-block; width:110px;"><?php echo esc_html( $dsf_net_label ); ?></label>
									<input type="url" name="dsf_seo_social_<?php echo esc_attr( $dsf_net_key ); ?>" class="regular-text" value="<?php echo esc_attr( $seo_social_by_network[ $dsf_net_key ] ?? '' ); ?>" placeholder="https://…">
								</p>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="dsf-card" data-dsf-tab="notification" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Site-wide Notification Bar</h2>
				<p class="description">Display one announcement across the public site, including normal WordPress pages and DSFlow pages.</p>

				<table class="form-table">
					<tr>
						<th scope="row">Enable Notification Bar</th>
						<td>
							<label>
								<input type="checkbox" name="dsf_notification_enabled" value="1" <?php checked( ! empty( $notification_bar['enabled'] ) ); ?>>
								Show this notification across the site
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf-notification-message">Message</label></th>
						<td>
							<textarea id="dsf-notification-message" class="large-text" rows="3" name="dsf_notification_message"><?php echo esc_textarea( $notification_bar['message'] ); ?></textarea>
							<p class="description">Simple formatting and inline links are allowed. Scripts, embeds, and unsafe HTML are removed.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf-notification-link-text">CTA Link</label></th>
						<td>
							<input type="text" id="dsf-notification-link-text" class="regular-text" name="dsf_notification_link_text" value="<?php echo esc_attr( $notification_bar['linkText'] ); ?>" placeholder="Shop now">
							<input type="url" class="regular-text" name="dsf_notification_link_url" value="<?php echo esc_attr( $notification_bar['linkUrl'] ); ?>" placeholder="https://example.com/offer">
							<p>
								<label>
									<input type="checkbox" name="dsf_notification_new_tab" value="1" <?php checked( ! empty( $notification_bar['openNewTab'] ) ); ?>>
									Open CTA in a new tab
								</label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Display</th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_notification_sticky" value="1" <?php checked( ! empty( $notification_bar['sticky'] ) ); ?>>
								Keep the bar visible at the top while scrolling
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_notification_dismissible" value="1" <?php checked( ! empty( $notification_bar['dismissible'] ) ); ?>>
								Allow visitors to close the bar
							</label>
							<label for="dsf-notification-alignment">Content alignment</label>
							<select id="dsf-notification-alignment" name="dsf_notification_alignment">
								<option value="center" <?php selected( 'center', $notification_bar['alignment'] ); ?>>Center</option>
								<option value="left" <?php selected( 'left', $notification_bar['alignment'] ); ?>>Left</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsf-notification-cookie-hours">Dismissal Duration</label></th>
						<td>
							<input type="number" id="dsf-notification-cookie-hours" name="dsf_notification_cookie_hours" min="0" max="8760" step="1" value="<?php echo esc_attr( $notification_bar['cookieHours'] ); ?>">
							<span class="description">hours</span>
							<p class="description">After a visitor closes the bar, hide it for this long. Use 0 for the current browser session only.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Schedule</th>
						<td>
							<label for="dsf-notification-start">Starts</label>
							<input type="datetime-local" id="dsf-notification-start" name="dsf_notification_start" value="<?php echo esc_attr( $notification_bar['startDate'] ); ?>">
							<label for="dsf-notification-end" style="margin-left: 16px;">Ends</label>
							<input type="datetime-local" id="dsf-notification-end" name="dsf_notification_end" value="<?php echo esc_attr( $notification_bar['endDate'] ); ?>">
							<p class="description">Leave either field empty when no start or end limit is needed. Times use the WordPress site timezone.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Colors</th>
						<td>
							<label style="display: inline-block; margin-right: 18px;">Background <input type="color" name="dsf_notification_background" value="<?php echo esc_attr( $notification_bar['backgroundColor'] ); ?>"></label>
							<label style="display: inline-block; margin-right: 18px;">Text <input type="color" name="dsf_notification_text_color" value="<?php echo esc_attr( $notification_bar['textColor'] ); ?>"></label>
							<label style="display: inline-block;">Links <input type="color" name="dsf_notification_link_color" value="<?php echo esc_attr( $notification_bar['linkColor'] ); ?>"></label>
						</td>
					</tr>
				</table>
			</div>

			<div class="dsf-card" data-dsf-tab="recaptcha" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Google reCAPTCHA v3</h2>
				<p class="description">Enable score-based bot protection for all DSF forms rendered with <code>[dsform]</code>.</p>

				<table class="form-table">
					<tr>
						<th scope="row">Enable reCAPTCHA</th>
						<td>
							<label>
								<input type="checkbox" name="dsf_recaptcha_enabled" value="1" <?php checked( $recaptcha_enabled ); ?>>
								Use Google reCAPTCHA v3 on DSF Forms
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Site Key</th>
						<td>
							<input type="text" class="regular-text" name="dsf_recaptcha_site_key" value="<?php echo esc_attr( $recaptcha_site_key ); ?>" autocomplete="off">
							<p class="description">Public key used on the frontend.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Secret Key</th>
						<td>
							<input type="password" class="regular-text" name="dsf_recaptcha_secret_key" value="<?php echo esc_attr( $recaptcha_secret_key ); ?>" autocomplete="off">
							<p class="description">Private key used for server-side verification.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Score Threshold</th>
						<td>
							<input type="number" min="0" max="1" step="0.1" name="dsf_recaptcha_threshold" value="<?php echo esc_attr( $recaptcha_threshold ); ?>">
							<p class="description">Recommended default: 0.5. Lower is less strict, higher is more strict.</p>
						</td>
					</tr>
				</table>
			</div>
			
			<script>
			(function () {
				var VALID = ['general', 'theme', 'notification', 'recaptcha'];
				var links  = document.querySelectorAll('.dsf-settings-tabs [data-dsf-tab-link]');
				var cards  = document.querySelectorAll('.dsf-card[data-dsf-tab]');
				if (!links.length || !cards.length) return;

				function activate(tab) {
					if (VALID.indexOf(tab) === -1) tab = 'general';
					links.forEach(function (link) {
						link.classList.toggle('nav-tab-active', link.getAttribute('data-dsf-tab-link') === tab);
					});
					cards.forEach(function (card) {
						// Use an explicit 'block' (not '') so it beats the FOUC
						// stylesheet rule that hides non-general cards by default.
						card.style.display = (card.getAttribute('data-dsf-tab') === tab) ? 'block' : 'none';
					});
				}

				links.forEach(function (link) {
					link.addEventListener('click', function (event) {
						event.preventDefault();
						var tab = this.getAttribute('data-dsf-tab-link');
						if (window.history && window.history.replaceState) {
							window.history.replaceState(null, '', '#' + tab);
						} else {
							window.location.hash = tab;
						}
						activate(tab);
					});
				});

				// Restore the tab from the URL hash (survives a Save reload).
				activate((window.location.hash || '').replace('#', ''));
			})();
			</script>

			<p class="submit">
				<button type="submit" name="dsf_save_settings" class="button button-primary">
					Save Settings
				</button>
			</p>
		</div>
	</form>
</div>
