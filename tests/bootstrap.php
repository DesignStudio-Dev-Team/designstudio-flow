<?php
/**
 * PHPUnit Bootstrap
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WP_Mock
WP_Mock::setUsePatchwork(true);
WP_Mock::bootstrap();
