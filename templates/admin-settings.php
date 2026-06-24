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
	update_option( 'dsf_recaptcha_secret_key', $recaptcha_secret_key );
	update_option( 'dsf_recaptcha_threshold', $recaptcha_threshold );

	// Typography defaults.
	$typography_mode  = ( isset( $_POST['dsf_typography_mode'] ) && 'override' === $_POST['dsf_typography_mode'] ) ? 'override' : 'theme';
	$typography_base  = isset( $_POST['dsf_typography_base'] ) ? floatval( wp_unslash( $_POST['dsf_typography_base'] ) ) : 16.0;
	$typography_scale = isset( $_POST['dsf_typography_scale'] ) ? floatval( wp_unslash( $_POST['dsf_typography_scale'] ) ) : 1.25;
	$typography_base  = max( 12.0, min( 22.0, $typography_base ) );
	$typography_scale = max( 1.05, min( 1.6, $typography_scale ) );

	$typography = array(
		'mode'         => $typography_mode,
		'heading_font' => isset( $_POST['dsf_typography_heading_font'] ) ? DSF_Frontend::sanitize_font_family( wp_unslash( $_POST['dsf_typography_heading_font'] ) ) : '',
		'body_font'    => isset( $_POST['dsf_typography_body_font'] ) ? DSF_Frontend::sanitize_font_family( wp_unslash( $_POST['dsf_typography_body_font'] ) ) : '',
		'base'         => $typography_base,
		'scale'        => $typography_scale,
	);
	update_option( 'dsf_typography', $typography );

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
$recaptcha_secret_key = get_option( 'dsf_recaptcha_secret_key', '' );
$recaptcha_threshold  = floatval( get_option( 'dsf_recaptcha_threshold', 0.5 ) );
$notification_bar     = DSF_Notification_Bar::get_settings();
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
			<!-- General Settings -->
			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
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
							<p class="description">DesignStudio Flow now works directly on normal WordPress pages.</p>
						</td>
					</tr>
				</table>
			</div>
			
			<!-- Default Colors -->
			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
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

			<!-- Typography -->
			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
				<h2 style="margin-top: 0;">Typography</h2>
				<p class="description">Controls the base body size, modular scale, and font families used by every DesignStudio Flow block.</p>

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
							<th scope="row"><label for="dsf_typography_base">Base Body Size</label></th>
							<td>
								<input type="number" id="dsf_typography_base" name="dsf_typography_base"
									min="12" max="22" step="0.5"
									value="<?php echo esc_attr( $typography_base ); ?>">
								<span class="description" style="margin-left: 8px;">px</span>
								<p class="description">The size of normal body text. All other sizes derive from this × the scale.</p>
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
								<p class="description">Each step up multiplies by this ratio. Larger ratios create more dramatic heading hierarchies.</p>
							</td>
						</tr>
					</table>
				</div>

				<script>
				(function () {
					var radios = document.querySelectorAll('input[name="dsf_typography_mode"]');
					var panel  = document.getElementById('dsf-typography-overrides');
					if (!panel || !radios.length) return;
					radios.forEach(function (r) {
						r.addEventListener('change', function () {
							panel.style.display = (this.value === 'override' && this.checked) ? '' : 'none';
						});
					});
				})();
				</script>
			</div>

			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
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

			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
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
			
			<p class="submit">
				<button type="submit" name="dsf_save_settings" class="button button-primary">
					Save Settings
				</button>
			</p>
		</div>
	</form>
</div>
