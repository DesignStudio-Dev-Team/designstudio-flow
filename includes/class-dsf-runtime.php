<?php
/**
 * Request-context helpers for DesignStudio Flow.
 *
 * Several subsystems keep their own database tables and self-heal their schema
 * when the plugin is updated. Doing that check on every request — including
 * ordinary frontend page views — costs one uncached option read per version key
 * per visitor, because schema-version options are deliberately not autoloaded.
 *
 * Maintenance work belongs to requests that can actually act on it: wp-admin,
 * cron, WP-CLI, and plugin activation. This class is the single place that
 * decides that, so every subsystem answers the question the same way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Runtime {

	/**
	 * Register the class autoloader for the plugin's `includes/` directory.
	 *
	 * Every class file maps to its name: `DSF_Translation_Routes` lives in
	 * `class-dsf-translation-routes.php`, the one interface in
	 * `interface-dsf-translation-provider.php`. Loading them on demand keeps
	 * requests that never touch a subsystem from parsing it — the editor, import
	 * /export, packaging, and the translation stack together are the bulk of the
	 * plugin's source and are irrelevant to an ordinary page view.
	 *
	 * This also makes the `class_exists( 'DSF_Ajax' )` guards elsewhere behave the
	 * way they read: they now load the class and use it, instead of silently
	 * skipping work whenever the file happened not to be included.
	 */
	public static function register_autoloader() {
		spl_autoload_register(
			static function ( $class ) {
				if ( 0 !== strpos( $class, 'DSF_' ) && 'DesignStudio_Flow' !== $class ) {
					return;
				}

				$slug = strtolower( str_replace( '_', '-', $class ) );
				foreach ( array( 'class-', 'interface-' ) as $prefix ) {
					$path = DSF_PLUGIN_DIR . 'includes/' . $prefix . $slug . '.php';
					if ( is_readable( $path ) ) {
						require_once $path;
						return;
					}
				}
			}
		);
	}

	/**
	 * Whether deferred maintenance (schema upgrades, rewrite flushes, one-time
	 * migrations) should run on this request.
	 *
	 * Frontend page views return false: the tables are created at activation, and
	 * any pending upgrade is applied the next time an administrator loads wp-admin
	 * or cron runs — the same deferral WordPress core uses for its own upgrades.
	 *
	 * @return bool
	 */
	public static function is_maintenance_request() {
		$is_maintenance = is_admin()
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'WP_INSTALLING' ) && WP_INSTALLING );

		/**
		 * Filter whether DesignStudio Flow runs deferred maintenance this request.
		 *
		 * Returning true on frontend requests restores the previous behavior of
		 * self-healing the schema on every page view.
		 *
		 * The earliest check happens while the plugin file is still loading, before
		 * `plugins_loaded`, so a callback added from another regular plugin is too
		 * late to affect it. Add it from a mu-plugin to influence every check.
		 *
		 * @param bool $is_maintenance Whether maintenance work should run.
		 */
		return (bool) apply_filters( 'dsf_is_maintenance_request', $is_maintenance );
	}
}
