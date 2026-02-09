<?php
/**
 * Admin Dashboard Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		<!-- Recent Pages -->
		<div class="dsf-card" style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;">Recent Flow Pages</h2>
			
			<?php if ( empty( $pages ) ) : ?>
				<div style="text-align: center; padding: 40px; color: #646970;">
					<span class="dashicons dashicons-layout" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 16px;"></span>
					<p>No Flow Pages yet.</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor' ) ); ?>" class="button button-primary">
						Create Your First Page
					</a>
				</div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Title</th>
							<th>Status</th>
							<th>Modified</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pages as $flow_page ) : ?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor&post_id=' . $flow_page->ID ) ); ?>">
											<?php echo esc_html( $flow_page->post_title ); ?>
										</a>
									</strong>
								</td>
								<td>
									<?php
									$status_label = 'publish' === $flow_page->post_status ? 'Published' : ucfirst( $flow_page->post_status );
									$status_color = 'publish' === $flow_page->post_status ? '#00a32a' : '#d63638';
									?>
									<span style="color: <?php echo esc_attr( $status_color ); ?>;">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
								<td>
									<?php
									$modified_timestamp = get_post_modified_time( 'U', true, $flow_page );
									echo esc_html(
										sprintf(
											/* translators: %s: Human-readable time difference. */
											__( '%s ago', 'designstudio-flow' ),
											human_time_diff( $modified_timestamp, time() )
										)
									);
									?>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-editor&post_id=' . $flow_page->ID ) ); ?>">
										Edit
									</a>
									|
									<a href="<?php echo esc_url( get_permalink( $flow_page->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
										View
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				
				<p style="margin-top: 16px;">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dsf_page' ) ); ?>">
						View all pages →
					</a>
				</p>
			<?php endif; ?>
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
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsf-settings' ) ); ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-admin-settings"></span>
							Plugin Settings
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( 'https://designstudio.com/flow/docs' ); ?>" target="_blank" rel="noopener noreferrer" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
							<span class="dashicons dashicons-book"></span>
							Documentation
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
