<?php
/**
 * Admin Dashboard Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$render_recent_items = static function ( $title, $items, $all_url, $edit_url_callback, $view_url_callback, $empty_text ) {
	?>
	<div style="border: 1px solid #d9dee8; border-radius: 8px; background: #fff; padding: 14px;">
		<h3 style="margin: 0 0 10px; font-size: 16px;"><?php echo esc_html( $title ); ?></h3>

		<?php if ( empty( $items ) ) : ?>
			<p style="margin: 0; color: #646970;"><?php echo esc_html( $empty_text ); ?></p>
		<?php else : ?>
			<ul style="margin: 0; padding: 0; list-style: none;">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$item_title = $item->post_title ? $item->post_title : __( '(no title)', 'designstudio-flow' );
					$edit_url   = call_user_func( $edit_url_callback, $item );
					$view_url   = call_user_func( $view_url_callback, $item );
					$status     = 'publish' === $item->post_status ? __( 'Published', 'designstudio-flow' ) : ucfirst( $item->post_status );
					$modified   = get_post_modified_time( 'U', true, $item );
					?>
					<li style="padding: 10px 0; border-top: 1px solid #eef2f7;">
						<div style="display: flex; justify-content: space-between; gap: 8px; align-items: baseline;">
							<a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight: 600; text-decoration: none;">
								<?php echo esc_html( $item_title ); ?>
							</a>
							<span style="color: #6b7280; font-size: 12px;">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Human-readable time difference. */
										__( '%s ago', 'designstudio-flow' ),
										human_time_diff( $modified, time() )
									)
								);
								?>
							</span>
						</div>
						<div style="margin-top: 4px; color: #6b7280; font-size: 12px; display: flex; gap: 8px; align-items: center;">
							<span><?php echo esc_html( $status ); ?></span>
							<span>•</span>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'designstudio-flow' ); ?></a>
							<?php if ( $view_url ) : ?>
								<span>•</span>
								<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'designstudio-flow' ); ?></a>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<p style="margin: 10px 0 0;">
				<a href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'View all →', 'designstudio-flow' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="wrap dsf-admin-dashboard">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-layout" style="margin-right: 8px;"></span>
		DesignStudio Flow
	</h1>
	
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor' ) ); ?>" class="page-title-action">
		Add New Page
	</a>
	
	<hr class="wp-header-end">
	
	<div class="dsf-dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
		<!-- Recent Content -->
		<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;">Recent DesignStudio Flow Content</h2>

			<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px;">
				<?php
				$render_recent_items(
					__( 'Pages', 'designstudio-flow' ),
					$pages,
					admin_url( 'edit.php?post_type=dsf_page' ),
					static function ( $item ) {
						return admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $item->ID ) );
					},
					static function ( $item ) {
						return get_permalink( $item->ID );
					},
					__( 'No pages yet.', 'designstudio-flow' )
				);
				$render_recent_items(
					__( 'Headers', 'designstudio-flow' ),
					$headers,
					admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=header' ),
					static function ( $item ) {
						return admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $item->ID ) );
					},
					static function ( $item ) {
						unset( $item );
						return '';
					},
					__( 'No headers yet.', 'designstudio-flow' )
				);
				$render_recent_items(
					__( 'Footers', 'designstudio-flow' ),
					$footers,
					admin_url( 'edit.php?post_type=dsf_layout&dsf_layout_type=footer' ),
					static function ( $item ) {
						return admin_url( 'admin.php?page=dsf-editor&post_id=' . intval( $item->ID ) );
					},
					static function ( $item ) {
						unset( $item );
						return '';
					},
					__( 'No footers yet.', 'designstudio-flow' )
				);
				$render_recent_items(
					__( 'Forms', 'designstudio-flow' ),
					$forms,
					admin_url( 'edit.php?post_type=dsf_form' ),
					static function ( $item ) {
						return admin_url( 'admin.php?page=dsf-form-builder&form_id=' . intval( $item->ID ) );
					},
					static function ( $item ) {
						unset( $item );
						return '';
					},
					__( 'No forms yet.', 'designstudio-flow' )
				);
				?>
			</div>
		</div>
		
		<!-- Quick Links -->
		<div>
			<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
				<h2 style="margin-top: 0;">Quick Start</h2>
				<ul style="margin: 0;">
					<li style="margin-bottom: 12px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor' ) ); ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-plus-alt2"></span>
							Create New Page
						</a>
					</li>
					<li style="margin-bottom: 12px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=header' ) ); ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-editor-kitchensink"></span>
							Create New Header
						</a>
					</li>
					<li style="margin-bottom: 12px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor&post_type=dsf_layout&dsf_layout_type=footer' ) ); ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-editor-insertmore"></span>
							Create New Footer
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-form-builder' ) ); ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-feedback"></span>
							Create New Form
						</a>
					</li>
				</ul>
			</div>
			
			<div class="dsf-card" style="background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
				<h2 style="margin-top: 0; color: #2271b1;">
					<span class="dashicons dashicons-megaphone"></span>
					Pro Features
				</h2>
				<p style="color: #50575e;">Unlock more blocks, templates, and advanced customization options with DesignStudio Flow Pro.</p>
				<a href="<?php echo esc_url( 'https://designstudio.com/flow/pro' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					Learn More
				</a>
			</div>
		</div>
	</div>
</div>
