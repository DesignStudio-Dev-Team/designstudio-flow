<?php
/**
 * Plugin Name: DesignStudio Flow
 * Plugin URI: https://designstudio.com/flow
 * Description: Build your WordPress Page with Artisanal Content Blocks. A lightweight alternative to Elementor and Divi.
 * Version: 1.0.8
 * Author: DesignStudio
 * Author URI: https://designstudio.com
 * Text Domain: designstudio-flow
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'DSF_VERSION', '1.0.7' );
define( 'DSF_PLUGIN_FILE', __FILE__ );
define( 'DSF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DSF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DSF_PLUGIN_DIR . 'includes/class-designstudio-flow.php';

DesignStudio_Flow::get_instance();
