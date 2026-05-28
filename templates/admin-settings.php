<?php
/**
 * Admin Settings Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['dsf_save_settings'] ) && isset( $_POST['dsf_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsf_settings_nonce'] ) ), 'dsf_save_settings' ) ) {
	// Save settings
	$enabled_post_types = array( 'page' );
	update_option( 'dsf_enabled_post_types', $enabled_post_types );

	$default_colors = array(
		'primary'    => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['dsf_primary_color'] ?? '#3B82F6' ) ) ),
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

	// Typography defaults
	$typography_mode  = ( isset( $_POST['dsf_typography_mode'] ) && 'override' === $_POST['dsf_typography_mode'] ) ? 'override' : 'theme';
	$typography_base  = isset( $_POST['dsf_typography_base'] ) ? floatval( wp_unslash( $_POST['dsf_typography_base'] ) ) : 16.0;
	$typography_scale = isset( $_POST['dsf_typography_scale'] ) ? floatval( wp_unslash( $_POST['dsf_typography_scale'] ) ) : 1.25;
	$typography_base  = max( 12.0, min( 22.0, $typography_base ) );
	$typography_scale = max( 1.05, min( 1.6, $typography_scale ) );

	$typography = array(
		'mode'         => $typography_mode,
		'heading_font' => isset( $_POST['dsf_typography_heading_font'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_typography_heading_font'] ) ) : '',
		'body_font'    => isset( $_POST['dsf_typography_body_font'] ) ? sanitize_text_field( wp_unslash( $_POST['dsf_typography_body_font'] ) ) : '',
		'base'         => $typography_base,
		'scale'        => $typography_scale,
	);
	update_option( 'dsf_typography', $typography );

	echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

// Get current settings
$enabled_post_types   = get_option( 'dsf_enabled_post_types', array( 'page' ) );
$default_colors       = get_option(
	'dsf_default_colors',
	array(
		'primary'    => '#3B82F6',
		'secondary'  => '#1E40AF',
		'text'       => '#1F2937',
		'background' => '#FFFFFF',
	)
);
$recaptcha_enabled    = (bool) get_option( 'dsf_recaptcha_enabled', false );
$recaptcha_site_key   = get_option( 'dsf_recaptcha_site_key', '' );
$recaptcha_secret_key = get_option( 'dsf_recaptcha_secret_key', '' );
$recaptcha_threshold  = floatval( get_option( 'dsf_recaptcha_threshold', 0.5 ) );

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
$typography_mode  = $typography_option['mode'] ?? 'theme';
$typography_hfont = $typography_option['heading_font'] ?? '';
$typography_bfont = $typography_option['body_font'] ?? '';
$typography_base  = floatval( $typography_option['base'] ?? 16 );
$typography_scale = floatval( $typography_option['scale'] ?? 1.25 );

$scale_options = array(
	'1.125' => 'Minor Second (1.125)',
	'1.2'   => 'Minor Third (1.2)',
	'1.25'  => 'Major Third (1.25)',
	'1.333' => 'Perfect Fourth (1.333)',
	'1.414' => 'Augmented Fourth (1.414)',
	'1.5'   => 'Perfect Fifth (1.5)',
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
				<p class="description">These colors will be used as defaults for new pages.</p>
				
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
