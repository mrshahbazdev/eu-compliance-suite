<?php
/**
 * Plugin Name:       EuroComply GPSR Compliance Manager
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU General Product Safety Regulation (GPSR) compliance fields for WooCommerce products: manufacturer, importer / EU Responsible Person, warnings, batch / lot, frontend safety block, admin compliance dashboard. Part of the EuroComply compliance suite.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-gpsr
 * Domain Path:       /languages
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_GPSR_VERSION', '0.1.0' );
define( 'EUROCOMPLY_GPSR_FILE', __FILE__ );
define( 'EUROCOMPLY_GPSR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_GPSR_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_GPSR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_GPSR_SLUG', 'eurocomply-gpsr' );
define( 'EUROCOMPLY_GPSR_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_GPSR_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Gpsr\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_GPSR_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Gpsr\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Gpsr\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Gpsr\Plugin::instance();
	}
);
