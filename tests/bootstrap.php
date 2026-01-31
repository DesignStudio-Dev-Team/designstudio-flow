<?php
/**
 * PHPUnit Bootstrap
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WP_Mock
WP_Mock::setUsePatchwork(true);
WP_Mock::bootstrap();
