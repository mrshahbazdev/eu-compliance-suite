<?php
/**
 * Plugin Name:       EuroComply Right-to-Repair & Energy Label
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Right-to-Repair Directive (EU 2024/1799), Ecodesign ESPR, and Energy Labelling Regulation (EU 2017/1369) compliance for WooCommerce. Per-product reparability score, energy class, spare-parts availability years, repair manual URL, spare-parts supplier directory, authorised-repairer directory (FR L.111-4). Pro: EPREL sync, German ReparaturIndex draft, FR Indice de réparabilité auto-calc, digital product passport.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-r2r
 * Domain Path:       /languages
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_R2R_VERSION', '0.1.0' );
define( 'EUROCOMPLY_R2R_FILE', __FILE__ );
define( 'EUROCOMPLY_R2R_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_R2R_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_R2R_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_R2R_SLUG', 'eurocomply-r2r' );
define( 'EUROCOMPLY_R2R_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_R2R_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\R2R\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_R2R_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\R2R\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\R2R\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\R2R\Plugin::instance();
	}
);
