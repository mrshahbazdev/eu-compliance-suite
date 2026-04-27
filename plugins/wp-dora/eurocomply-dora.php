<?php
/**
 * Plugin Name:       EuroComply DORA
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Digital Operational Resilience Act (Reg. (EU) 2022/2554) toolkit: ICT-related incident register with Art. 19 classification (major / significant) + 4h / 72h / 1-month deadline tracker, ICT third-party Register of Information (Art. 28(3)) with criticality tiers, resilience-testing log (vuln scan / pen test / TLPT / scenario / BCP), policy register, info-sharing log (Art. 45). Pro: live competent-authority submission, signed PDF reports, REST API, multi-site aggregator.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-dora
 * Domain Path:       /languages
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_DORA_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_DORA_FILE',     __FILE__ );
define( 'EUROCOMPLY_DORA_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_DORA_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_DORA_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_DORA_SLUG',     'eurocomply-dora' );
define( 'EUROCOMPLY_DORA_MIN_PHP',  '7.4' );
define( 'EUROCOMPLY_DORA_MIN_WP',   '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\DORA\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_DORA_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\DORA\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\DORA\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\DORA\Plugin::instance();
	}
);
