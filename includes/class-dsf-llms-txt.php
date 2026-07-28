<?php
/**
 * Admin tool for drafting and writing a public llms.txt file at the site root.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_LLMS_Txt {

	const ACTION     = 'dsf_generate_llms_txt';
	const MAX_LENGTH = 20000;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_generate' ) );
	}

	/**
	 * Produce a deliberately generic draft. The administrator must review it
	 * before publishing it, so no private pages, credentials, or inferred claims
	 * are ever included.
	 *
	 * @return string
	 */
	public static function get_default_content() {
		$name        = sanitize_text_field( get_bloginfo( 'name' ) );
		$description = sanitize_text_field( get_bloginfo( 'description' ) );
		$site_url    = esc_url_raw( home_url( '/' ), array( 'http', 'https' ) );

		if ( '' === $name ) {
			$name = __( 'Your Site Name', 'designstudio-flow' );
		}

		$lines = array(
			'# ' . $name,
			'',
			$description ? '> ' . $description : '> TODO: Add a short description of this site.',
			'',
			'## Website',
			'',
			'- [Home](' . $site_url . ')',
			'',
			'## Important pages',
			'',
			'- TODO: Add the key public pages an AI assistant should use, such as your About, Services, Products, Help, and Contact pages.',
			'',
			'## Notes for AI systems',
			'',
			'- Use only publicly available information from this website.',
			'- Treat this file as a starting point and verify details on the linked pages.',
			'- TODO: Replace these notes with guidance specific to this site.',
		);

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Restrict the file to plain text and a modest size. llms.txt is public, so
	 * no HTML or executable markup belongs in the generated root file.
	 *
	 * @param mixed $content Submitted content.
	 * @return string
	 */
	public static function sanitize_content( $content ) {
		$content = is_scalar( $content ) ? (string) $content : '';
		$content = sanitize_textarea_field( $content );
		return substr( $content, 0, self::MAX_LENGTH );
	}

	/**
	 * Write the administrator-reviewed draft to exactly one fixed destination.
	 *
	 * @param string $content Plain-text content.
	 * @return true|WP_Error
	 */
	private function write_file( $content ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'filesystem_unavailable', __( 'WordPress could not access the site filesystem.', 'designstudio-flow' ) );
		}

		$permissions = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		if ( ! $wp_filesystem->put_contents( ABSPATH . 'llms.txt', $content . "\n", $permissions ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write llms.txt in the WordPress root directory.', 'designstudio-flow' ) );
		}

		return true;
	}

	/**
	 * Save handler for the Tools screen.
	 */
	public function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to write files in the site root.', 'designstudio-flow' ) );
		}

		check_admin_referer( self::ACTION );
		$content = self::sanitize_content( wp_unslash( $_POST['dsf_llms_content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified directly above.
		$result  = '' === $content ? new WP_Error( 'empty', __( 'Add content before generating llms.txt.', 'designstudio-flow' ) ) : $this->write_file( $content );
		$status  = is_wp_error( $result ) ? $result->get_error_code() : 'done';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'dsf-tools',
					'tab'      => 'llms-txt',
					'dsf_llms' => $status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the llms.txt tools tab.
	 */
	public function render_admin_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = isset( $_GET['dsf_llms'] ) ? sanitize_key( wp_unslash( $_GET['dsf_llms'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
		?>
		<div class="dsf-tools-grid" style="display:grid;gap:20px;max-width:820px;margin-top:16px;">
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Generate llms.txt', 'designstudio-flow' ); ?></h2>
				<p><?php esc_html_e( 'Create a simple, public AI guidance file at your WordPress root. Review and tailor the draft for your site before generating it.', 'designstudio-flow' ); ?></p>
				<?php if ( 'done' === $status ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'llms.txt was generated. Review it at your site root and update it whenever your content changes.', 'designstudio-flow' ); ?></p></div>
				<?php elseif ( '' !== $status ) : ?>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'llms.txt could not be generated. Confirm WordPress has permission to write to the site root, then try again.', 'designstudio-flow' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'This will replace any existing llms.txt file in the WordPress root. Continue?', 'designstudio-flow' ) ); ?>');">
					<?php wp_nonce_field( self::ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<p><label for="dsf-llms-content"><strong><?php esc_html_e( 'Draft content', 'designstudio-flow' ); ?></strong></label></p>
					<textarea id="dsf-llms-content" name="dsf_llms_content" rows="20" class="large-text code" maxlength="<?php echo esc_attr( self::MAX_LENGTH ); ?>"><?php echo esc_textarea( self::get_default_content() ); ?></textarea>
					<p class="description"><?php esc_html_e( 'This is intentionally generic. Replace every TODO with accurate information for this site. Generating replaces an existing root llms.txt file.', 'designstudio-flow' ); ?></p>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate / Update llms.txt', 'designstudio-flow' ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
	}
}
