<?php
/**
 * Plugin Name: AI Visibility Scanner
 * Plugin URI:  https://nexvistech.com
 * Description: Audit, score, and optimize your WordPress site for AI search engines, answer engines, and LLM web crawlers. Developed by NexVis Technologies & Mudassar Ijaz.
 * Version:     1.0.0
 * Author:      NexVis Technologies & Mudassar Ijaz
 * Author URI:  https://nexvistech.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-visibility-scanner
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Tested up to:      7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'AVS_VERSION', '1.0.0' );
define( 'AVS_FILE', __FILE__ );
define( 'AVS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AVS_URL', plugin_dir_url( __FILE__ ) );

// Check minimum PHP requirement
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'AI Visibility Scanner requires PHP 7.4 or higher.', 'ai-visibility-scanner' ) . '</p></div>';
	} );
	return;
}

/**
 * Autoloader for AIVisibilityScanner namespace (PSR-4 compliant).
 * Ensures compatibility across basic shared hosting without requiring CLI/Composer at runtime.
 */
spl_autoload_register( function ( $class ) {
	$prefix   = 'AIVisibilityScanner\\';
	$base_dir = AVS_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Convert namespace separators to directory separators
	$file_path = str_replace( '\\', '/', $relative_class );

	// Split directory path and class name to follow WordPress class naming conventions
	$parts      = explode( '/', $file_path );
	$class_name = array_pop( $parts );

	// Convert ClassName to class-classname.php or interface-interfacename.php
	$slug       = strtolower( str_replace( '_', '-', $class_name ) );
	$is_interface = strpos( $class_name, 'Interface' ) !== false;
	$file_name  = ( $is_interface ? 'interface-' : 'class-' ) . str_replace( '-interface', '', $slug ) . '.php';

	$dir_path = ! empty( $parts ) ? implode( '/', $parts ) . '/' : '';
	$file     = $base_dir . $dir_path . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Load Composer autoloader if present (for bundled packages like Action Scheduler)
if ( file_exists( AVS_PATH . 'vendor/autoload.php' ) ) {
	require_once AVS_PATH . 'vendor/autoload.php';
}

/**
 * Main plugin activation handler.
 */
function activate_ai_visibility_scanner() {
	require_once AVS_PATH . 'includes/class-activator.php';
	\AIVisibilityScanner\Activator::activate();
}

/**
 * Main plugin deactivation handler.
 */
function deactivate_ai_visibility_scanner() {
	require_once AVS_PATH . 'includes/class-deactivator.php';
	\AIVisibilityScanner\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ai_visibility_scanner' );
register_deactivation_hook( __FILE__, 'deactivate_ai_visibility_scanner' );

/**
 * Initialize and bootstrap the plugin.
 */
function run_ai_visibility_scanner() {
	$plugin = \AIVisibilityScanner\Plugin::get_instance();
	$plugin->run();
}

add_action( 'plugins_loaded', 'run_ai_visibility_scanner' );
