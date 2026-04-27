<?php
/**
 * Plugin Name:       EuroComply Toy Safety
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Toy Safety Regulation (revising Dir. 2009/48/EC) toolkit: toy register with EAN/GTIN + age range + intended-for-under-36-months flag, restricted-substance register (CMR / endocrine disruptor / PFAS / lead / phthalates / nitrosamines / formaldehyde / heavy metals), conformity-assessment register (Module A / Aa / B+C / B+E), Digital Product Passport (DPP) builder, RAPEX/Safety Gate incident register, economic-operator chain (manufacturer / importer / distributor / fulfilment), CE marking + EU declaration of conformity, 10-tab admin, CSV export. Pro: live Safety Gate submission, signed PDF DoC, EPREL/DPP registry sync.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-toy-safety
 * Domain Path:       /languages
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_TOY_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_TOY_FILE',     __FILE__ );
define( 'EUROCOMPLY_TOY_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_TOY_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_TOY_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_TOY_SLUG',     'eurocomply-toy-safety' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\ToySafety\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_TOY_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\ToySafety\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\ToySafety\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\ToySafety\Plugin::instance();
	}
);
