<?php
/**
 * Plugin Name:       EuroComply EUDR
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Deforestation-free Products Regulation (Reg. (EU) 2023/1115) toolkit: 7-commodity registry (cattle · cocoa · coffee · oil palm · rubber · soya · wood + derived), supplier directory, plot / geolocation register (GeoJSON polygons & points), country-risk classification (low / standard / high), shipment & Due Diligence Statement (DDS) builder, risk-assessment + mitigation log, XML + JSON export for TRACES NT pre-filing. Pro: live TRACES NT submission, satellite-imagery deforestation check, PDF DDS, REST/webhooks, WPML.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-eudr
 * Domain Path:       /languages
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_EUDR_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_EUDR_FILE',     __FILE__ );
define( 'EUROCOMPLY_EUDR_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_EUDR_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_EUDR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_EUDR_SLUG',     'eurocomply-eudr' );
define( 'EUROCOMPLY_EUDR_MIN_PHP',  '7.4' );
define( 'EUROCOMPLY_EUDR_MIN_WP',   '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\EUDR\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_EUDR_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\EUDR\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\EUDR\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\EUDR\Plugin::instance();
	}
);
