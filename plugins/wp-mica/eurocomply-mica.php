<?php
/**
 * Plugin Name:       EuroComply MiCA
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Markets in Crypto-Assets Regulation (EU) 2023/1114 toolkit: crypto-asset register (ART / EMT / other), white-paper register with NCA notification + 12-day standstill (Art. 8 / Art. 17 / Art. 51), marketing-communications log (Art. 7), Art. 31 complaint handling with statutory acknowledgement + resolution windows, insider-information / market-abuse disclosure log (Art. 87–88), reserve-composition snapshots for ART / EMT issuers (Art. 36–38), 10-tab admin, CSV export. Pro: live NCA submission, signed PDF, REST API, multisite aggregator.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-mica
 * Domain Path:       /languages
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_MICA_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_MICA_FILE',     __FILE__ );
define( 'EUROCOMPLY_MICA_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_MICA_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_MICA_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_MICA_SLUG',     'eurocomply-mica' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\MiCA\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_MICA_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\MiCA\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\MiCA\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\MiCA\Plugin::instance();
	}
);
