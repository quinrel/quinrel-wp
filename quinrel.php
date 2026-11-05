<?php
/**
 * Plugin Name: Quinrel
 * Plugin URI: https://quinrel.com
 * Description: Visibility & Discovery Intelligence for WordPress, with technical SEO included.
 * Version: 1.1.2
 * Author: Skoorite Team
 * Author URI: https://quinrel.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quinrel
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package Quinrel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'QUINREL_VERSION' ) ) {
	define( 'QUINREL_VERSION', '1.1.2' );
}
if ( ! defined( 'QUINREL_PLUGIN_FILE' ) ) {
	define( 'QUINREL_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'QUINREL_PLUGIN_DIR' ) ) {
	define( 'QUINREL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'QUINREL_PLUGIN_URL' ) ) {
	define( 'QUINREL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}


// Defensive PHP version guard.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'Quinrel', 'quinrel' ),
				sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
					esc_html__( 'Quinrel requires PHP version %1$s or higher. Your server is running PHP %2$s. Please upgrade your PHP version.', 'quinrel' ),
					'8.2',
					esc_html( PHP_VERSION )
				)
			);
		}
	);
	return;
}

// Require the Composer autoloader.
if ( file_exists( QUINREL_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once QUINREL_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Show an admin notice if composer is not installed.
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'Quinrel', 'quinrel' ),
				wp_kses(
					__( 'Please run <code>composer install</code> in the plugin directory.', 'quinrel' ),
					array( 'code' => array() )
				)
			);
		}
	);
	return;
}

// Initialize the plugin.
function quinrel_init() {
	$plugin = \Quinrel\WordPress\Plugin::getInstance();
	$plugin->boot();
}

add_action( 'plugins_loaded', 'quinrel_init' );

// Global template helper for themes to render breadcrumbs.
if ( ! function_exists( 'quinrel_breadcrumbs' ) ) {
	function quinrel_breadcrumbs(): void {
		$renderer = new \Quinrel\WordPress\Modules\Breadcrumbs\BreadcrumbRenderer();
		echo wp_kses_post( $renderer->render() );
	}
}

// Activation and Deactivation hooks.
register_activation_hook( __FILE__, array( \Quinrel\WordPress\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Quinrel\WordPress\Plugin::class, 'deactivate' ) );
