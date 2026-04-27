<?php
/**
 * Plugin Name:       EuroComply Compliance Dashboard
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Site-wide aggregator for the EuroComply EU compliance suite. Surfaces a unified compliance score, plugin status grid, alerts feed, deadline calendar and CSV export across all installed EuroComply plugins (Legal Pages, Cookie Consent, VAT OSS, GPSR, EPR, EAA, Omnibus, DSA, Age Verification, GDPR DSAR, NIS2 & CRA, Right-to-Repair, AI Act and any future suite plugins).
 * Version:           0.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-dashboard
 * Domain Path:       /languages
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_DASH_VERSION', '0.2.0' );
define( 'EUROCOMPLY_DASH_FILE', __FILE__ );
define( 'EUROCOMPLY_DASH_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_DASH_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_DASH_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_DASH_SLUG', 'eurocomply-dashboard' );
define( 'EUROCOMPLY_DASH_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_DASH_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Dashboard\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_DASH_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Dashboard\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Dashboard\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Dashboard\Plugin::instance();
	}
);
