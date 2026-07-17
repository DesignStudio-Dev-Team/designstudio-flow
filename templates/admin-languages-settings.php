<?php
/**
 * Languages settings card.
 *
 * Expected variables are prepared by admin-settings.php. All language identity
 * fields are rendered from the server-owned locale registry.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$multilingual_state_labels = array(
	'disabled'    => __( 'Disabled', 'designstudio-flow' ),
	'not_started' => __( 'Not started', 'designstudio-flow' ),
	'pending'     => __( 'Pending', 'designstudio-flow' ),
	'running'     => __( 'Running', 'designstudio-flow' ),
	'complete'    => __( 'Complete', 'designstudio-flow' ),
	'failed'      => __( 'Paused safely', 'designstudio-flow' ),
);
$multilingual_state_label  = $multilingual_state_labels[ $multilingual_settings['migration_state'] ] ?? __( 'Unknown', 'designstudio-flow' );
?>

<div class="dsf-card" data-dsf-tab="languages" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0;">
	<h2 style="margin-top: 0;"><?php esc_html_e( 'Languages', 'designstudio-flow' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the reviewed multilingual foundation. Existing URLs remain unchanged in this phase; language routing, cloning, switchers, and machine translation are added in later reviewed phases.', 'designstudio-flow' ); ?>
	</p>

	<?php if ( ! empty( $multilingual_conflicts ) ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'Multilingual mode cannot be enabled while another multilingual system is active.', 'designstudio-flow' ); ?></strong>
				<?php
				$multilingual_conflict_names = array_filter(
					array_map(
						static function ( $conflict ) {
							return sanitize_text_field( $conflict['name'] ?? '' );
						},
						$multilingual_conflicts
					)
				);
				echo ' ' . esc_html( implode( ', ', $multilingual_conflict_names ) ) . '.';
				?>
			</p>
			<p><?php esc_html_e( 'DesignStudio Flow will never deactivate another plugin automatically.', 'designstudio-flow' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( in_array( $multilingual_settings['migration_state'], array( 'pending', 'running', 'failed', 'complete' ), true ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: migration state label, 2: number of processed objects. */
						__( 'Existing-content assignment: %1$s. Objects processed: %2$d. Existing public URLs are unchanged.', 'designstudio-flow' ),
						$multilingual_state_label,
						absint( $multilingual_progress['processed'] ?? 0 )
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Multilingual mode', 'designstudio-flow' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="dsf_multilingual_enabled" value="1" <?php checked( ! empty( $multilingual_settings['enabled'] ) ); ?>>
					<?php esc_html_e( 'Enable the multilingual foundation', 'designstudio-flow' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Requires a main language plus at least one secondary language. Disabling preserves every content object and relationship.', 'designstudio-flow' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsf-multilingual-main-language"><?php esc_html_e( 'Main language', 'designstudio-flow' ); ?></label></th>
			<td>
				<select id="dsf-multilingual-main-language" name="dsf_multilingual_main_language">
					<?php foreach ( $multilingual_registry as $multilingual_code => $multilingual_locale ) : ?>
						<option value="<?php echo esc_attr( $multilingual_code ); ?>" <?php selected( $multilingual_settings['main_language'], $multilingual_code ); ?>>
							<?php echo esc_html( $multilingual_locale['native_label'] . ' — ' . $multilingual_code ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The main language keeps the site’s existing unprefixed URLs. Once content assignment begins, changing it requires the later migration-preview workflow.', 'designstudio-flow' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'URL policy', 'designstudio-flow' ); ?></th>
			<td>
				<strong><?php esc_html_e( 'Main language unprefixed; secondary languages use stable prefixes', 'designstudio-flow' ); ?></strong>
				<p class="description"><?php esc_html_e( 'This approved policy is fixed. Prefixes are validated now and become active only when routing is implemented.', 'designstudio-flow' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsf-multilingual-missing-policy"><?php esc_html_e( 'Missing translation', 'designstudio-flow' ); ?></label></th>
			<td>
				<select id="dsf-multilingual-missing-policy" name="dsf_multilingual_missing_policy">
					<option value="not_found" <?php selected( $multilingual_settings['missing_translation_policy'], 'not_found' ); ?>><?php esc_html_e( 'Return a language-specific 404 (recommended)', 'designstudio-flow' ); ?></option>
					<option value="visible_redirect" <?php selected( $multilingual_settings['missing_translation_policy'], 'visible_redirect' ); ?>><?php esc_html_e( 'Show an explicit redirect', 'designstudio-flow' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Main-language content is never rendered silently under a secondary-language URL.', 'designstudio-flow' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsf-multilingual-source-policy"><?php esc_html_e( 'Source changes', 'designstudio-flow' ); ?></label></th>
			<td>
				<select id="dsf-multilingual-source-policy" name="dsf_multilingual_source_change_policy">
					<option value="keep_minor" <?php selected( $multilingual_settings['source_change_policy'], 'keep_minor' ); ?>><?php esc_html_e( 'Keep minor stale translations public (recommended)', 'designstudio-flow' ); ?></option>
					<option value="hide_until_reviewed" <?php selected( $multilingual_settings['source_change_policy'], 'hide_until_reviewed' ); ?>><?php esc_html_e( 'Hide every stale translation until reviewed', 'designstudio-flow' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Critical source changes and invalid required dependencies always hide the affected translation until it is reviewed again.', 'designstudio-flow' ); ?></p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Enabled languages and stable prefixes', 'designstudio-flow' ); ?></h3>
	<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: maximum enabled language count. */
				__( 'Choose up to %d curated languages and set their order. The selected main language is always included and ignores its prefix.', 'designstudio-flow' ),
				DSF_Multilingual_Settings::MAX_LANGUAGES
			)
		);
		?>
	</p>

	<div style="overflow-x: auto; max-height: 560px; overflow-y: auto; margin-top: 12px; border: 1px solid #dcdcde;">
		<table class="widefat striped" style="border: 0; min-width: 760px;">
			<thead>
				<tr>
					<th scope="col" style="width: 70px;"><?php esc_html_e( 'Use', 'designstudio-flow' ); ?></th>
					<th scope="col" style="width: 75px;"><?php esc_html_e( 'Order', 'designstudio-flow' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Language', 'designstudio-flow' ); ?></th>
					<th scope="col" style="width: 90px;"><?php esc_html_e( 'HTML lang', 'designstudio-flow' ); ?></th>
					<th scope="col" style="width: 115px;"><?php esc_html_e( 'WP locale', 'designstudio-flow' ); ?></th>
					<th scope="col" style="width: 75px;"><?php esc_html_e( 'Direction', 'designstudio-flow' ); ?></th>
					<th scope="col" style="width: 155px;"><?php esc_html_e( 'URL prefix', 'designstudio-flow' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $multilingual_registry as $multilingual_code => $multilingual_locale ) : ?>
					<?php
					$multilingual_selected       = isset( $multilingual_languages[ $multilingual_code ] );
					$multilingual_saved_prefix   = $multilingual_selected ? $multilingual_languages[ $multilingual_code ]['prefix'] : '';
					$multilingual_default_prefix = strtolower( str_replace( '_', '-', $multilingual_code ) );
					$multilingual_prefix_value   = '' !== $multilingual_saved_prefix ? $multilingual_saved_prefix : $multilingual_default_prefix;
					$multilingual_order_value    = $multilingual_selected ? $multilingual_languages[ $multilingual_code ]['order'] : 999;
					$field_key                   = sanitize_key( $multilingual_code );
					?>
					<tr>
						<td>
							<input type="hidden" name="dsf_multilingual_languages[<?php echo esc_attr( $field_key ); ?>][code]" value="<?php echo esc_attr( $multilingual_code ); ?>">
							<label class="screen-reader-text" for="dsf-language-enabled-<?php echo esc_attr( $field_key ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: native language label. */
										__( 'Enable %s', 'designstudio-flow' ),
										$multilingual_locale['native_label']
									)
								);
								?>
							</label>
							<input id="dsf-language-enabled-<?php echo esc_attr( $field_key ); ?>" type="checkbox" name="dsf_multilingual_languages[<?php echo esc_attr( $field_key ); ?>][enabled]" value="1" <?php checked( $multilingual_selected ); ?>>
						</td>
						<td>
							<input type="number" class="small-text" min="1" max="999" name="dsf_multilingual_languages[<?php echo esc_attr( $field_key ); ?>][order]" value="<?php echo esc_attr( (string) $multilingual_order_value ); ?>">
						</td>
						<td><strong><?php echo esc_html( $multilingual_locale['native_label'] ); ?></strong><br><code><?php echo esc_html( $multilingual_code ); ?></code></td>
						<td><code><?php echo esc_html( $multilingual_locale['html_lang'] ); ?></code></td>
						<td><code><?php echo esc_html( $multilingual_locale['wp_locale'] ); ?></code></td>
						<td><?php echo esc_html( strtoupper( $multilingual_locale['direction'] ) ); ?></td>
						<td>
							<label class="screen-reader-text" for="dsf-language-prefix-<?php echo esc_attr( $field_key ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: native language label. */
										__( 'URL prefix for %s', 'designstudio-flow' ),
										$multilingual_locale['native_label']
									)
								);
								?>
							</label>
							<input id="dsf-language-prefix-<?php echo esc_attr( $field_key ); ?>" type="text" maxlength="<?php echo esc_attr( (string) DSF_Multilingual_Settings::MAX_PREFIX_LENGTH ); ?>" pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" name="dsf_multilingual_languages[<?php echo esc_attr( $field_key ); ?>][prefix]" value="<?php echo esc_attr( $multilingual_prefix_value ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="description"><?php esc_html_e( 'Prefixes may contain lowercase letters, numbers, and internal hyphens only. WordPress, REST, feed, sitemap, and WooCommerce routes are reserved.', 'designstudio-flow' ); ?></p>
</div>
