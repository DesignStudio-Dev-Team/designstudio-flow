<?php
/**
 * Front-end hydration loading cover.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-dsf-frontend.php';

class Test_DSF_Frontend_Loader extends TestCase {
	public function test_critical_loader_covers_hydration_without_hiding_snapshot_markup() {
		$frontend = ( new ReflectionClass( 'DSF_Frontend' ) )->newInstanceWithoutConstructor();
		$method   = new ReflectionMethod( 'DSF_Frontend', 'get_loader_critical_css' );
		$method->setAccessible( true );
		$css = $method->invoke( $frontend );

		$this->assertStringContainsString( 'position:fixed', $css );
		$this->assertStringContainsString( 'dsf-loader-failsafe', $css );
		$this->assertStringNotContainsString( '#dsf-frontend-app>*{visibility:hidden}', $css );
	}
}
