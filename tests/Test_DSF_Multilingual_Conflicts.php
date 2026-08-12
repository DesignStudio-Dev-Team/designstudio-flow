<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-multilingual-conflicts.php';

/**
 * Covers read-only multilingual-plugin conflict detection.
 */
class Test_DSF_Multilingual_Conflicts extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_detects_active_network_must_use_and_runtime_signals() {
		$conflicts = DSF_Multilingual_Conflicts::detect_conflicts(
			array(
				'active'         => array( 'polylang/polylang.php', 'wordpress-seo/wp-seo.php' ),
				'network_active' => array( 'sitepress-multilingual-cms/sitepress.php' => 1710000000 ),
				'must_use'       => array( '/srv/wp-content/mu-plugins/weglot/weglot.php' => array( 'Name' => 'Weglot loader' ) ),
			),
			array(
				'translatepress' => array( 'constant:TRP_PLUGIN_VERSION' ),
				'not-registered' => array( 'constant:UNKNOWN' ),
			)
		);

		$this->assertSame( array( 'polylang', 'translatepress', 'weglot', 'wpml' ), array_keys( $conflicts ) );
		$this->assertSame( array( 'active' ), $conflicts['polylang']['sources'] );
		$this->assertSame( array( 'network_active' ), $conflicts['wpml']['sources'] );
		$this->assertSame( array( 'must_use' ), $conflicts['weglot']['sources'] );
		$this->assertSame( array( 'runtime' ), $conflicts['translatepress']['sources'] );
		$this->assertSame( array( 'constant:TRP_PLUGIN_VERSION' ), $conflicts['translatepress']['runtime_signals'] );
	}

	public function test_inactive_installed_inventory_is_not_a_conflict_signal() {
		$conflicts = DSF_Multilingual_Conflicts::detect_conflicts(
			array(
				'active'    => array( 'wordpress-seo/wp-seo.php' ),
				'installed' => array( 'gtranslate/gtranslate.php', 'polylang/polylang.php' ),
			),
			array()
		);

		$this->assertSame( array(), $conflicts );
		$this->assertFalse(
			DSF_Multilingual_Conflicts::has_conflicts(
				array( 'active' => array( 'my-polylang-tools/my-polylang-tools.php' ) ),
				array()
			)
		);
	}

	public function test_plugin_candidates_use_exact_directory_and_file_slugs() {
		$this->assertSame(
			array( 'sitepress', 'sitepress-multilingual-cms' ),
			DSF_Multilingual_Conflicts::plugin_file_candidates( '/srv/wp-content/plugins/sitepress-multilingual-cms/sitepress.php' )
		);
		$this->assertSame( array( 'gtranslate' ), DSF_Multilingual_Conflicts::plugin_file_candidates( 'gtranslate.php' ) );
		$this->assertSame( array(), DSF_Multilingual_Conflicts::plugin_file_candidates( '../bad/<script>.php' ) );
	}

	public function test_collects_mu_plugins_without_admin_plugin_api() {
		WP_Mock::userFunction( 'get_option', array( 'return' => array() ) );
		WP_Mock::userFunction( 'get_site_option', array( 'return' => array() ) );
		WP_Mock::userFunction(
			'wp_get_mu_plugins',
			array(
				'return' => array( '/srv/wp-content/mu-plugins/multilingualpress-pro.php' ),
			)
		);

		$sources   = DSF_Multilingual_Conflicts::collect_active_sources();
		$conflicts = DSF_Multilingual_Conflicts::detect_conflicts( $sources, array() );

		$this->assertContains( '/srv/wp-content/mu-plugins/multilingualpress-pro.php', $sources['must_use'] );
		$this->assertArrayHasKey( 'multilingualpress', $conflicts );
		$this->assertSame( array( 'must_use' ), $conflicts['multilingualpress']['sources'] );
	}

	public function test_conflict_map_filter_is_revalidated() {
		$base                      = DSF_Multilingual_Conflicts::get_conflict_map();
		$filtered                  = $base;
		$filtered['custom-router'] = array(
			'name'    => '<b>Custom Router</b>',
			'slugs'   => array( 'custom-router' ),
			'runtime' => array(),
			'unknown' => 'discarded',
		);
		WP_Mock::onFilter( 'dsf_multilingual_conflict_map' )
			->with( $base )
			->reply( $filtered );

		$map = DSF_Multilingual_Conflicts::get_conflict_map();

		$this->assertArrayHasKey( 'custom-router', $map );
		$this->assertSame( 'Custom Router', $map['custom-router']['name'] );
		$this->assertSame( array( 'custom-router' ), $map['custom-router']['slugs'] );
		$this->assertSame( array( 'constants' => array(), 'classes' => array(), 'functions' => array() ), $map['custom-router']['runtime'] );
		$this->assertArrayNotHasKey( 'unknown', $map['custom-router'] );

		$this->assertTrue(
			DSF_Multilingual_Conflicts::has_conflicts(
				array( 'active' => array( 'custom-router/plugin.php' ) ),
				array()
			)
		);
	}

	public function test_malformed_runtime_reports_are_discarded() {
		$conflicts = DSF_Multilingual_Conflicts::detect_conflicts(
			array(),
			array(
				'polylang' => array(
					'constant:POLYLANG_VERSION',
					'../../secret',
					array( 'not' => 'scalar' ),
				),
			)
		);

		$this->assertSame( array( 'constant:POLYLANG_VERSION' ), $conflicts['polylang']['runtime_signals'] );
	}
}
