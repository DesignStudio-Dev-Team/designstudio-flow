<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-runtime.php';

/**
 * Coverage for the request-context gate that keeps deferred maintenance —
 * schema upgrades, rewrite flushes, one-time migrations — off frontend page
 * views.
 *
 * Each of those checks reads a deliberately non-autoloaded option, so running
 * them for every visitor cost one uncached query per key per request. The
 * contract that matters: admin, cron and WP-CLI still run them, and a plain
 * frontend request does not.
 */
class Test_DSF_Runtime extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/** Point the environment at a given admin/cron state. */
	private function environment( $is_admin, $is_cron ) {
		WP_Mock::userFunction( 'is_admin', array( 'return' => (bool) $is_admin ) );
		WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => (bool) $is_cron ) );
	}

	public function test_admin_requests_run_maintenance() {
		$this->environment( true, false );
		$this->assertTrue( DSF_Runtime::is_maintenance_request() );
	}

	public function test_cron_requests_run_maintenance() {
		// An unattended site still applies a pending schema upgrade through cron.
		$this->environment( false, true );
		$this->assertTrue( DSF_Runtime::is_maintenance_request() );
	}

	public function test_frontend_requests_do_not_run_maintenance() {
		$this->environment( false, false );
		$this->assertFalse( DSF_Runtime::is_maintenance_request() );
	}

	public function test_filter_can_restore_maintenance_on_the_frontend() {
		$this->environment( false, false );
		WP_Mock::onFilter( 'dsf_is_maintenance_request' )->with( false )->reply( true );
		$this->assertTrue( DSF_Runtime::is_maintenance_request() );
	}

	public function test_filter_can_suppress_maintenance_in_admin() {
		$this->environment( true, false );
		WP_Mock::onFilter( 'dsf_is_maintenance_request' )->with( true )->reply( false );
		$this->assertFalse( DSF_Runtime::is_maintenance_request() );
	}

	/**
	 * The autoloader is what makes the on-demand loading safe: the class_exists()
	 * guards scattered through the plugin must still resolve their target rather
	 * than silently skipping the work they guard.
	 */
	public function test_autoloader_resolves_every_plugin_class() {
		if ( ! defined( 'DSF_PLUGIN_DIR' ) ) {
			define( 'DSF_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
		}

		$missing = array();
		foreach ( glob( DSF_PLUGIN_DIR . 'includes/*.php' ) as $file ) {
			if ( ! preg_match_all( '/^(?:final |abstract )?(?:class|interface) ([A-Za-z0-9_]+)/m', (string) file_get_contents( $file ), $matches ) ) {
				continue;
			}
			foreach ( $matches[1] as $class ) {
				$slug  = strtolower( str_replace( '_', '-', $class ) );
				$found = false;
				foreach ( array( 'class-', 'interface-' ) as $prefix ) {
					if ( is_readable( DSF_PLUGIN_DIR . 'includes/' . $prefix . $slug . '.php' ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$missing[] = $class;
				}
			}
		}

		$this->assertSame(
			array(),
			$missing,
			'Every class must live in the file its name maps to, or the autoloader cannot find it.'
		);
	}
}
