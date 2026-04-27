<?php
/**
 * Plugin Name:       EuroComply CSRD / ESRS
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Corporate Sustainability Reporting Directive (Directive (EU) 2022/2464) + European Sustainability Reporting Standards (ESRS 1/2 cross-cutting, E1–E5 environment, S1–S4 social, G1 governance): double-materiality assessment workflow, datapoint catalogue + collection, assurance log (limited→reasonable), phase-in eligibility (large 2024 → SMEs 2026 → 3rd-country 2028), XBRL-style report builder, CSV export. Pro: full CSRD-XBRL taxonomy export, signed PDF, ESEF inline, REST API, supplier portal.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-csrd-esrs
 * Domain Path:       /languages
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_CSRD_VERSION', '0.1.0' );
define( 'EUROCOMPLY_CSRD_FILE', __FILE__ );
define( 'EUROCOMPLY_CSRD_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_CSRD_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_CSRD_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_CSRD_SLUG', 'eurocomply-csrd-esrs' );
define( 'EUROCOMPLY_CSRD_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_CSRD_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\CSRD\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_CSRD_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\CSRD\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\CSRD\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\CSRD\Plugin::instance();
	}
);
