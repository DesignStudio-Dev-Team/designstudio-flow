<?php
/**
 * Forms Builder Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_title = $form ? $form->post_title : __( 'Untitled Form', 'designstudio-flow' );
?>
<div class="wrap dsf-forms-admin-wrap">
	<div id="dsf-forms-builder" class="dsf-forms-builder" data-form-id="<?php echo esc_attr( intval( $form_id ) ); ?>">
		<h1 class="dsf-forms-builder__page-title"><?php esc_html_e( 'DesignStudio Flow Form', 'designstudio-flow' ); ?></h1>
		<div class="dsf-forms-builder__header">
			<div class="dsf-forms-builder__title-group">
				<label for="dsf-form-title" class="screen-reader-text"><?php esc_html_e( 'Form title', 'designstudio-flow' ); ?></label>
				<input
					id="dsf-form-title"
					class="dsf-forms-builder__title-input"
					type="text"
					value="<?php echo esc_attr( $form_title ); ?>"
					placeholder="<?php esc_attr_e( 'Untitled Form', 'designstudio-flow' ); ?>"
				>
			</div>

				<div class="dsf-forms-builder__nav">
					<nav class="dsf-forms-builder__tabs" aria-label="<?php esc_attr_e( 'Form builder tabs', 'designstudio-flow' ); ?>">
					<button class="dsf-top-tab is-active" type="button" data-tab="build"><?php esc_html_e( 'Build', 'designstudio-flow' ); ?></button>
					<button class="dsf-top-tab" type="button" data-tab="settings"><?php esc_html_e( 'Settings', 'designstudio-flow' ); ?></button>
					<button class="dsf-top-tab" type="button" data-tab="entries"><?php esc_html_e( 'Entries', 'designstudio-flow' ); ?></button>
					<button class="dsf-top-tab" type="button" data-tab="notifications"><?php esc_html_e( 'Notifications', 'designstudio-flow' ); ?></button>
					<button class="dsf-top-tab" type="button" data-tab="confirmation"><?php esc_html_e( 'Confirmation', 'designstudio-flow' ); ?></button>
					<button class="dsf-top-tab" type="button" data-tab="connections"><?php esc_html_e( 'Connections', 'designstudio-flow' ); ?></button>
				</nav>

					<div class="dsf-forms-builder__actions">
						<div class="dsf-forms-builder__shortcode">
							<span class="dsf-forms-builder__shortcode-label"><?php esc_html_e( 'Shortcode', 'designstudio-flow' ); ?></span>
							<code id="dsf-form-shortcode">[dsform id='<?php echo esc_html( intval( $form_id ) ); ?>']</code>
						</div>
						<button id="dsf-form-back" class="button"><?php esc_html_e( 'Back', 'designstudio-flow' ); ?></button>
						<button id="dsf-form-save" class="button button-primary button-hero"><?php esc_html_e( 'Save Form', 'designstudio-flow' ); ?></button>
					</div>
				</div>
		</div>

		<div class="dsf-forms-builder__panes">
			<section class="dsf-pane is-active" data-pane="build">
				<div class="dsf-build-layout">
					<div class="dsf-build-layout__canvas">
						<div class="dsf-panel-card dsf-panel-card--canvas">
							<div class="dsf-canvas-head">
								<h2><?php esc_html_e( 'Form Canvas', 'designstudio-flow' ); ?></h2>
								<p><?php esc_html_e( 'This is the live visual preview. Click any field to edit it.', 'designstudio-flow' ); ?></p>
							</div>
							<p class="dsf-canvas-empty-state">
								<?php esc_html_e( 'Drag a field from the library to start building the form', 'designstudio-flow' ); ?>
							</p>
							<div id="dsf-form-canvas" class="dsf-form-canvas" aria-live="polite"></div>
						</div>
					</div>

					<aside class="dsf-build-layout__sidebar">
						<div class="dsf-sidebar-stack">
							<div id="dsf-form-library" class="dsf-panel-card dsf-form-library">
								<div class="dsf-form-library__head">
									<h2><?php esc_html_e( 'Form Fields Library', 'designstudio-flow' ); ?></h2>
									<p><?php esc_html_e( 'Drag a field into the canvas or double-click to add it.', 'designstudio-flow' ); ?></p>
								</div>
								<div id="dsf-field-library-list" class="dsf-field-library-list"></div>
							</div>

								<div id="dsf-edit-drawer" class="dsf-panel-card dsf-edit-drawer" aria-hidden="true">
									<p class="dsf-edit-drawer__eyebrow"><?php esc_html_e( 'Field Settings', 'designstudio-flow' ); ?></p>
									<div class="dsf-edit-drawer__head">
										<h2 id="dsf-edit-drawer-title"><?php esc_html_e( 'Edit Field', 'designstudio-flow' ); ?></h2>
										<button id="dsf-close-drawer" class="dsf-link-btn" type="button"><?php esc_html_e( 'Close', 'designstudio-flow' ); ?></button>
									</div>
									<div id="dsf-edit-drawer-body" class="dsf-edit-drawer__body"></div>
								</div>
						</div>
					</aside>
				</div>
			</section>

			<section class="dsf-pane" data-pane="settings">
				<div class="dsf-panel-card dsf-simple-card">
					<h2><?php esc_html_e( 'Form Settings', 'designstudio-flow' ); ?></h2>
					<p><?php esc_html_e( 'Configure global labels and response messaging used by this form.', 'designstudio-flow' ); ?></p>
					<div class="dsf-settings-grid">
						<label>
							<span><?php esc_html_e( 'Submit Button Label', 'designstudio-flow' ); ?></span>
							<input id="dsf-setting-submit-label" type="text">
						</label>
						<label>
							<span><?php esc_html_e( 'Next Button Label', 'designstudio-flow' ); ?></span>
							<input id="dsf-setting-next-label" type="text">
						</label>
						<label>
							<span><?php esc_html_e( 'Previous Button Label', 'designstudio-flow' ); ?></span>
							<input id="dsf-setting-previous-label" type="text">
						</label>
						<label class="dsf-settings-grid__full">
							<span><?php esc_html_e( 'Success Message', 'designstudio-flow' ); ?></span>
							<input id="dsf-setting-success-message" type="text">
						</label>
					</div>
				</div>
			</section>

			<section class="dsf-pane" data-pane="entries">
				<div class="dsf-panel-card dsf-tab-card">
					<h2><?php esc_html_e( 'Entries', 'designstudio-flow' ); ?></h2>
					<p class="dsf-tab-card__subtle"><?php esc_html_e( 'Total entries:', 'designstudio-flow' ); ?> <strong id="dsf-entries-total">0</strong></p>
					<button id="dsf-view-entries" class="button button-primary"><?php esc_html_e( 'View Entries', 'designstudio-flow' ); ?></button>
				</div>
			</section>

			<section class="dsf-pane" data-pane="notifications">
				<div class="dsf-panel-card dsf-tab-card">
					<h2><?php esc_html_e( 'Notifications', 'designstudio-flow' ); ?></h2>
					<label class="dsf-checkline">
						<input id="dsf-setting-send-admin-notifications" type="checkbox">
						<span><?php esc_html_e( 'Send admin notifications when form is submitted', 'designstudio-flow' ); ?></span>
					</label>

					<div class="dsf-inline-heading">
						<strong><?php esc_html_e( 'Admin Emails', 'designstudio-flow' ); ?></strong>
						<button id="dsf-add-admin-email" type="button" class="dsf-link-btn"><?php esc_html_e( '+ Add Email', 'designstudio-flow' ); ?></button>
					</div>
					<p id="dsf-admin-emails-empty" class="dsf-tab-card__hint"><?php esc_html_e( 'No admin emails configured. System default email will be used.', 'designstudio-flow' ); ?></p>
					<div id="dsf-admin-emails-list" class="dsf-repeat-list"></div>

					<label class="dsf-field-block">
						<span><?php esc_html_e( 'Notification Subject', 'designstudio-flow' ); ?></span>
						<input id="dsf-setting-notification-subject" type="text">
					</label>
					<p class="dsf-tab-card__hint"><?php esc_html_e( 'You can use {form_title} in the subject.', 'designstudio-flow' ); ?></p>

					<label class="dsf-field-block">
						<span><?php esc_html_e( 'Intro Message (optional)', 'designstudio-flow' ); ?></span>
						<textarea id="dsf-setting-notification-intro"></textarea>
					</label>

					<label class="dsf-checkline">
						<input id="dsf-setting-send-submitter-copy" type="checkbox">
						<span><?php esc_html_e( 'Send a copy to the submitter (requires an Email field)', 'designstudio-flow' ); ?></span>
					</label>
				</div>
			</section>

			<section class="dsf-pane" data-pane="confirmation">
				<div class="dsf-panel-card dsf-tab-card">
					<h2><?php esc_html_e( 'Confirmation', 'designstudio-flow' ); ?></h2>
					<label class="dsf-field-block">
						<span><?php esc_html_e( 'Confirmation Type', 'designstudio-flow' ); ?></span>
						<select id="dsf-setting-confirmation-type">
							<option value="message"><?php esc_html_e( 'Show Message', 'designstudio-flow' ); ?></option>
							<option value="redirect_url"><?php esc_html_e( 'Redirect to URL', 'designstudio-flow' ); ?></option>
						</select>
					</label>

					<label class="dsf-field-block" id="dsf-confirmation-message-wrap">
						<span><?php esc_html_e( 'Confirmation Message', 'designstudio-flow' ); ?></span>
						<textarea id="dsf-setting-confirmation-message"></textarea>
					</label>

					<label class="dsf-field-block" id="dsf-confirmation-url-wrap">
						<span><?php esc_html_e( 'Redirect URL', 'designstudio-flow' ); ?></span>
						<input id="dsf-setting-redirect-url" type="url" placeholder="https://example.com/thank-you">
					</label>
				</div>
			</section>

			<section class="dsf-pane" data-pane="connections">
				<div class="dsf-panel-card dsf-tab-card">
					<h2><?php esc_html_e( 'Connections', 'designstudio-flow' ); ?></h2>
					<div class="dsf-inline-heading">
						<strong><?php esc_html_e( 'Destination Connections', 'designstudio-flow' ); ?></strong>
						<button id="dsf-add-connection" type="button" class="dsf-link-btn"><?php esc_html_e( '+ Add Connection', 'designstudio-flow' ); ?></button>
					</div>
					<div id="dsf-connections-list" class="dsf-repeat-list"></div>
					<p class="dsf-tab-card__hint"><?php esc_html_e( 'Use Zapier webhook URLs directly. Salesforce can be connected with a Flow/Apex endpoint or middleware webhook.', 'designstudio-flow' ); ?></p>
					<p class="dsf-tab-card__hint"><?php esc_html_e( 'If a Secret is set, requests include X-DSForm-Signature.', 'designstudio-flow' ); ?></p>
				</div>
			</section>
		</div>
	</div>
</div>
