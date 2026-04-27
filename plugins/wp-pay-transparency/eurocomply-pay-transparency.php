<?php
/**
 * Plugin Name:       EuroComply Pay Transparency
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Pay Transparency Directive (Directive (EU) 2023/970, transposition deadline 7 June 2026): Art. 5 pay-range disclosure on job ads, Art. 6 pay-setting & progression criteria, Art. 7 worker right-to-information request workflow with 2-month response tracker, Art. 9 annual gender pay-gap report (CSV upload + per-category calculator + JSON/CSV export), Art. 10 joint-pay-assessment trigger when any category gap exceeds 5%, Art. 11 pay-categories taxonomy. Pro: payroll integrations (DATEV/SAP/BambooHR), Eurostat NACE classifier, signed PDF report, REST API.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-pay-transparency
 * Domain Path:       /languages
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_PT_VERSION', '0.1.0' );
define( 'EUROCOMPLY_PT_FILE', __FILE__ );
define( 'EUROCOMPLY_PT_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_PT_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_PT_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_PT_SLUG', 'eurocomply-pay-transparency' );
define( 'EUROCOMPLY_PT_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_PT_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\PayTransparency\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_PT_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\PayTransparency\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\PayTransparency\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\PayTransparency\Plugin::instance();
	}
);
