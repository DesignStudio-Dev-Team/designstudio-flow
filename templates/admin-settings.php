<?php
/**
 * Admin Settings Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['dsf_save_settings'] ) && wp_verify_nonce( $_POST['dsf_settings_nonce'], 'dsf_save_settings' ) ) {
	// Save settings
	$enabled_post_types = isset( $_POST['dsf_enabled_post_types'] ) ? array_map( 'sanitize_text_field', $_POST['dsf_enabled_post_types'] ) : array();
	update_option( 'dsf_enabled_post_types', $enabled_post_types );

	$default_colors = array(
		'primary'    => sanitize_hex_color( $_POST['dsf_primary_color'] ?? '#3B82F6' ),
		'secondary'  => sanitize_hex_color( $_POST['dsf_secondary_color'] ?? '#1E40AF' ),
		'text'       => sanitize_hex_color( $_POST['dsf_text_color'] ?? '#1F2937' ),
		'background' => sanitize_hex_color( $_POST['dsf_background_color'] ?? '#FFFFFF' ),
	);
	update_option( 'dsf_default_colors', $default_colors );

	echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

// Get current settings
$enabled_post_types = get_option( 'dsf_enabled_post_types', array( 'page', 'dsf_page' ) );
$default_colors     = get_option(
	'dsf_default_colors',
	array(
		'primary'    => '#3B82F6',
		'secondary'  => '#1E40AF',
		'text'       => '#1F2937',
		'background' => '#FFFFFF',
	)
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
						<th scope="row">Enable Flow Editor for</th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_enabled_post_types[]" value="page" 
									<?php checked( in_array( 'page', $enabled_post_types, true ) ); ?>>
								Pages
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="dsf_enabled_post_types[]" value="dsf_page" 
									<?php checked( in_array( 'dsf_page', $enabled_post_types, true ) ); ?>>
								Flow Pages (Custom Post Type)
							</label>
							<p class="description">Select which post types can use the Flow editor.</p>
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
			
			<p class="submit">
				<button type="submit" name="dsf_save_settings" class="button button-primary">
					Save Settings
				</button>
			</p>
		</div>
	</form>
</div>
