<?php
/**
 * Central translation review dashboard.
 *
 * Every state shown here is derived at render time from the publishing gate, so
 * the screen can never report an approval that the server would refuse.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'edit_posts' ) ) {
	return;
}

$dsf_review_service = DSF_Translation_Review::get_instance();
$dsf_review_filters = array(
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters; every state-changing action carries its own nonce.
	'post_type' => isset( $_GET['dsf_type'] ) ? sanitize_key( wp_unslash( $_GET['dsf_type'] ) ) : '',
	'language'  => isset( $_GET['dsf_language'] ) ? sanitize_text_field( wp_unslash( $_GET['dsf_language'] ) ) : '',
	'status'    => isset( $_GET['dsf_status'] ) ? sanitize_key( wp_unslash( $_GET['dsf_status'] ) ) : '',
	'paged'     => isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1,
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
);

$dsf_review        = $dsf_review_service->query( $dsf_review_filters );
$dsf_status_labels = DSF_Translation_Review::get_status_labels();
$dsf_conflicts     = DSF_Multilingual_Conflicts::detect_conflicts();
$dsf_action_nonce  = wp_create_nonce( 'dsf_translation_actions' );
?>

<div class="wrap dsf-translations">
	<h1><?php esc_html_e( 'Translations', 'designstudio-flow' ); ?></h1>

	<?php if ( ! empty( $dsf_conflicts ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Another multilingual plugin is active. Translation actions stay blocked until an administrator disables it.', 'designstudio-flow' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $dsf_review['languages'] ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Enable at least one secondary language in Settings to start translating.', 'designstudio-flow' ); ?></p>
		</div>
	<?php else : ?>

	<form method="get" style="margin: 16px 0;">
		<input type="hidden" name="page" value="dsf-translations">
		<label class="screen-reader-text" for="dsf-filter-type"><?php esc_html_e( 'Content type', 'designstudio-flow' ); ?></label>
		<select id="dsf-filter-type" name="dsf_type">
			<option value=""><?php esc_html_e( 'All content types', 'designstudio-flow' ); ?></option>
			<?php foreach ( $dsf_review['post_types'] as $dsf_type ) : ?>
				<?php $dsf_type_object = get_post_type_object( $dsf_type ); ?>
				<option value="<?php echo esc_attr( $dsf_type ); ?>" <?php selected( $dsf_type, $dsf_review_filters['post_type'] ); ?>>
					<?php echo esc_html( is_object( $dsf_type_object ) ? $dsf_type_object->labels->name : $dsf_type ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="dsf-filter-language"><?php esc_html_e( 'Language', 'designstudio-flow' ); ?></label>
		<select id="dsf-filter-language" name="dsf_language">
			<option value=""><?php esc_html_e( 'All languages', 'designstudio-flow' ); ?></option>
			<?php foreach ( $dsf_review['languages'] as $dsf_language ) : ?>
				<?php $dsf_locale = DSF_Language_Context::describe( $dsf_language ); ?>
				<option value="<?php echo esc_attr( $dsf_language ); ?>" <?php selected( $dsf_language, $dsf_review_filters['language'] ); ?>>
					<?php echo esc_html( ! empty( $dsf_locale['native_label'] ) ? $dsf_locale['native_label'] : $dsf_language ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="dsf-filter-status"><?php esc_html_e( 'Status', 'designstudio-flow' ); ?></label>
		<select id="dsf-filter-status" name="dsf_status">
			<option value=""><?php esc_html_e( 'All statuses', 'designstudio-flow' ); ?></option>
			<?php foreach ( $dsf_status_labels as $dsf_status_key => $dsf_status_label ) : ?>
				<option value="<?php echo esc_attr( $dsf_status_key ); ?>" <?php selected( $dsf_status_key, $dsf_review_filters['status'] ); ?>>
					<?php echo esc_html( $dsf_status_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php submit_button( __( 'Filter', 'designstudio-flow' ), 'secondary', '', false ); ?>
	</form>

	<table class="wp-list-table widefat fixed striped" id="dsf-translation-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Source', 'designstudio-flow' ); ?></th>
				<th scope="col" style="width:120px;"><?php esc_html_e( 'Language', 'designstudio-flow' ); ?></th>
				<th scope="col" style="width:150px;"><?php esc_html_e( 'Status', 'designstudio-flow' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Blocking', 'designstudio-flow' ); ?></th>
				<th scope="col" style="width:150px;"><?php esc_html_e( 'Reviewed', 'designstudio-flow' ); ?></th>
				<th scope="col" style="width:220px;"><?php esc_html_e( 'Actions', 'designstudio-flow' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $dsf_review['rows'] ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nothing matches these filters.', 'designstudio-flow' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $dsf_review['rows'] as $dsf_row ) : ?>
				<?php $dsf_locale = DSF_Language_Context::describe( $dsf_row['language'] ); ?>
				<tr>
					<td>
						<?php if ( $dsf_row['source_edit'] ) : ?>
							<a href="<?php echo esc_url( $dsf_row['source_edit'] ); ?>"><strong><?php echo esc_html( $dsf_row['source_title'] ); ?></strong></a>
						<?php else : ?>
							<strong><?php echo esc_html( $dsf_row['source_title'] ); ?></strong>
						<?php endif; ?>
						<div class="row-actions"><span><?php echo esc_html( $dsf_row['source_type'] ); ?></span></div>
					</td>
					<td><?php echo esc_html( ! empty( $dsf_locale['native_label'] ) ? $dsf_locale['native_label'] : $dsf_row['language'] ); ?></td>
					<td>
						<span class="dsf-status dsf-status-<?php echo esc_attr( $dsf_row['status'] ); ?>">
							<?php echo esc_html( $dsf_status_labels[ $dsf_row['status'] ] ?? $dsf_row['status'] ); ?>
						</span>
					</td>
					<td>
						<?php if ( empty( $dsf_row['blockers'] ) ) : ?>
							<span aria-hidden="true">—</span><span class="screen-reader-text"><?php esc_html_e( 'Nothing blocking', 'designstudio-flow' ); ?></span>
						<?php else : ?>
							<ul style="margin:0;">
								<?php foreach ( $dsf_row['blockers'] as $dsf_blocker ) : ?>
									<li><?php echo esc_html( $dsf_blocker['message'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( '' !== $dsf_row['reviewed_at'] ) : ?>
							<?php echo esc_html( $dsf_row['reviewed_at'] ); ?><br>
							<span class="description"><?php echo esc_html( $dsf_row['reviewer'] ); ?></span>
						<?php else : ?>
							<span aria-hidden="true">—</span><span class="screen-reader-text"><?php esc_html_e( 'Not reviewed', 'designstudio-flow' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( ! $dsf_row['target_id'] && $dsf_row['can_clone'] ) : ?>
							<button type="button" class="button button-primary dsf-translation-action" data-action="clone" data-source="<?php echo esc_attr( (string) $dsf_row['source_id'] ); ?>" data-language="<?php echo esc_attr( $dsf_row['language'] ); ?>">
								<?php esc_html_e( 'Create draft', 'designstudio-flow' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( $dsf_row['target_id'] && $dsf_row['target_edit'] ) : ?>
							<a class="button" href="<?php echo esc_url( $dsf_row['target_edit'] ); ?>"><?php esc_html_e( 'Edit', 'designstudio-flow' ); ?></a>
						<?php endif; ?>
						<?php if ( $dsf_row['target_id'] ) : ?>
							<?php if ( $dsf_row['can_review'] ) : ?>
								<button type="button" class="button dsf-translation-action" data-action="review"
									data-target="<?php echo esc_attr( (string) $dsf_row['target_id'] ); ?>"
									data-kind="<?php echo esc_attr( (string) ( $dsf_row['object_kind'] ?? 'post' ) ); ?>"
									data-subtype="<?php echo esc_attr( (string) ( $dsf_row['object_subtype'] ?? '' ) ); ?>">
									<?php esc_html_e( 'Approve', 'designstudio-flow' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( $dsf_row['can_publish'] && 'published' !== $dsf_row['status'] ) : ?>
								<button type="button" class="button button-primary dsf-translation-action" data-action="publish" data-target="<?php echo esc_attr( (string) $dsf_row['target_id'] ); ?>">
									<?php esc_html_e( 'Publish', 'designstudio-flow' ); ?>
								</button>
							<?php endif; ?>
						<?php endif; ?>
						<span class="dsf-action-feedback" aria-live="polite"></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description" style="margin-top:12px;">
		<?php esc_html_e( 'Approving records the exact source version you reviewed. If the main language changes afterwards, the translation returns to "Source changed" without overwriting your work.', 'designstudio-flow' ); ?>
	</p>

	<?php endif; ?>
</div>

<script>
( function () {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce = <?php echo wp_json_encode( $dsf_action_nonce ); ?>;
	var strings = {
		working: <?php echo wp_json_encode( __( 'Working…', 'designstudio-flow' ) ); ?>,
		failed: <?php echo wp_json_encode( __( 'That action could not be completed.', 'designstudio-flow' ) ); ?>
	};
	var actions = {
		clone: 'dsf_clone_translation',
		review: 'dsf_review_translation',
		publish: 'dsf_publish_translation'
	};

	document.getElementById( 'dsf-translation-table' ).addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.dsf-translation-action' );
		if ( ! button ) {
			return;
		}

		var action = actions[ button.getAttribute( 'data-action' ) ];
		if ( ! action ) {
			return;
		}

		var feedback = button.parentNode.querySelector( '.dsf-action-feedback' );
		button.disabled = true;
		feedback.textContent = ' ' + strings.working;

		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		if ( button.getAttribute( 'data-source' ) ) {
			body.append( 'source_id', button.getAttribute( 'data-source' ) );
			body.append( 'language', button.getAttribute( 'data-language' ) );
		}
		if ( button.getAttribute( 'data-target' ) ) {
			body.append( 'target_id', button.getAttribute( 'data-target' ) );
			body.append( 'object_kind', button.getAttribute( 'data-kind' ) || 'post' );
			body.append( 'object_subtype', button.getAttribute( 'data-subtype' ) || '' );
		}

		window.fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( payload ) {
				if ( payload && payload.success ) {
					window.location.reload();
					return;
				}
				feedback.textContent = ' ' + ( ( payload && payload.data && payload.data.message ) || strings.failed );
				button.disabled = false;
			} )
			.catch( function () {
				feedback.textContent = ' ' + strings.failed;
				button.disabled = false;
			} );
	} );
}() );
</script>
