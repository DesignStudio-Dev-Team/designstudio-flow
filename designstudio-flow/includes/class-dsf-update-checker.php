<?php
/**
 * Plugin Update Checker
 *
 * Handles checking for updates from GitHub releases
 * Supports both public and private repositories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Update_Checker {

	/**
	 * GitHub repository info
	 */
	private $github_username = 'DesignStudio-Dev-Team';
	private $github_repo     = 'designstudio-flow';

	/**
	 * GitHub access token for private repos
	 * Set via: update_option('dsf_github_token', 'your_token_here');
	 */
	private $github_token = null;

	/**
	 * Plugin info
	 */
	private $plugin_slug;
	private $plugin_basename;
	private $current_version;

	/**
	 * Cache key and duration
	 */
	private $cache_key      = 'dsf_update_check';
	private $cache_duration = 12 * HOUR_IN_SECONDS;

	/**
	 * Single instance
	 */
	private static $instance = null;

	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->plugin_slug     = 'designstudio-flow';
		$this->plugin_basename = DSF_PLUGIN_BASENAME;
		$this->current_version = DSF_VERSION;

		// Load GitHub token: check wp-config constant first, then fallback to option
		if ( defined( 'DSF_GITHUB_TOKEN' ) && DSF_GITHUB_TOKEN ) {
			$this->github_token = DSF_GITHUB_TOKEN;
		} else {
			$this->github_token = get_option( 'dsf_github_token', '' );
		}

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Check for updates
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		// Plugin info popup
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// After update, clear cache
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );

		// Filter for authenticated downloads (private repos)
		add_filter( 'upgrader_pre_download', array( $this, 'filter_download' ), 10, 3 );
	}

	/**
	 * Check GitHub for updates
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_version = $this->get_remote_version();

		if ( $remote_version && version_compare( $this->current_version, $remote_version->version, '<' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'         => $this->plugin_slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $remote_version->version,
				'url'          => $remote_version->url,
				'package'      => $remote_version->download_url,
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => $remote_version->tested ?? '',
				'requires_php' => $remote_version->requires_php ?? '7.4',
			);
		}

		return $transient;
	}

	/**
	 * Plugin info for the popup
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$remote_version = $this->get_remote_version();

		if ( ! $remote_version ) {
			return $result;
		}

		return (object) array(
			'name'           => 'DesignStudio Flow',
			'slug'           => $this->plugin_slug,
			'version'        => $remote_version->version,
			'author'         => '<a href="https://designstudio.com">DesignStudio Network, Inc.</a>',
			'author_profile' => 'https://designstudio.com',
			'homepage'       => 'https://designstudio.com/flow',
			'requires'       => '5.8',
			'tested'         => $remote_version->tested ?? '',
			'requires_php'   => $remote_version->requires_php ?? '7.4',
			'downloaded'     => 0,
			'last_updated'   => $remote_version->last_updated ?? '',
			'sections'       => array(
				'description'  => 'Build your WordPress Page with Artisanal Content Blocks.',
				'changelog'    => $remote_version->changelog ?? '',
				'installation' => 'Upload the plugin zip file via WordPress admin and activate.',
			),
			'download_link'  => $remote_version->download_url,
		);
	}

	/**
	 * Get remote version info from GitHub
	 */
	private function get_remote_version() {
		$cached = get_transient( $this->cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Build request headers
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
		);

		// Add authorization header for private repos
		if ( ! empty( $this->github_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->github_token;
		}

		$response = wp_remote_get(
			"https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest",
			array(
				'timeout' => 10,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $release->tag_name ) ) {
			return false;
		}

		// Find the zip asset
		$download_url = '';
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( false !== strpos( $asset->name, '.zip' ) ) {
					// For private repos, use the API URL (requires auth)
					// For public repos, use the browser download URL
					if ( ! empty( $this->github_token ) ) {
						$download_url = $asset->url; // API URL for authenticated download
					} else {
						$download_url = $asset->browser_download_url;
					}
					break;
				}
			}
		}

		// Fallback to zipball (works for both public and private)
		if ( empty( $download_url ) ) {
			$download_url = $release->zipball_url;
		}

		$version_data = (object) array(
			'version'      => ltrim( $release->tag_name, 'v' ),
			'download_url' => $download_url,
			'url'          => $release->html_url,
			'changelog'    => $release->body ?? '',
			'last_updated' => $release->published_at ?? '',
			'tested'       => '', // Can be parsed from release body if needed
			'requires_php' => '7.4',
		);

		set_transient( $this->cache_key, $version_data, $this->cache_duration );

		return $version_data;
	}

	/**
	 * Filter download to add authentication for private repos
	 */
	public function filter_download( $reply, $package, $upgrader ) {
		unset( $upgrader );
		// Only handle our plugin's downloads
		if ( false === strpos( $package, $this->github_repo ) ) {
			return $reply;
		}

		// Only needed for private repos with a token
		if ( empty( $this->github_token ) ) {
			return $reply;
		}

		// Download with authentication
		$response = wp_remote_get(
			$package,
			array(
				'timeout' => 300,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->github_token,
					'Accept'        => 'application/octet-stream',
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'download_failed', $response->get_error_message() );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'download_failed', 'Failed to download plugin update.' );
		}

		// Save to temp file
		$temp_file = wp_tempnam( $package );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'download_failed', 'File system unavailable.' );
		}

		$written = $wp_filesystem->put_contents( $temp_file, wp_remote_retrieve_body( $response ), FS_CHMOD_FILE );
		if ( ! $written ) {
			return new WP_Error( 'download_failed', 'Failed to write update package.' );
		}

		return $temp_file;
	}

	/**
	 * Clear cache after update
	 */
	public function clear_cache( $upgrader, $options ) {
		unset( $upgrader );
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key );
		}
	}
}

// Initialize (only run if main plugin constants are defined)
if ( defined( 'DSF_VERSION' ) && defined( 'DSF_PLUGIN_BASENAME' ) ) {
	DSF_Update_Checker::get_instance();
}
