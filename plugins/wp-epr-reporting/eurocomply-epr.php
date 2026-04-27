<?php
/**
 * Plugin Name:       EuroComply EPR Multi-Country Reporting
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Extended Producer Responsibility (EPR) data capture and reporting for WooCommerce products across EU registries (France Triman / Agec, Germany LUCID / VerpackG, Spain RAEE / SCRAP, Italy CONAI, Netherlands Afvalfonds, Austria ARA, Belgium Fost Plus). Product-level packaging weight + category codes, per-country compliance dashboard, CSV exports formatted for registry uploads. Part of the EuroComply compliance suite.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-epr
 * Domain Path:       /languages
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_EPR_VERSION', '0.1.0' );
define( 'EUROCOMPLY_EPR_FILE', __FILE__ );
define( 'EUROCOMPLY_EPR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_EPR_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_EPR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_EPR_SLUG', 'eurocomply-epr' );
define( 'EUROCOMPLY_EPR_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_EPR_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Epr\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_EPR_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Epr\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Epr\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Epr\Plugin::instance();
	},
	5
);
