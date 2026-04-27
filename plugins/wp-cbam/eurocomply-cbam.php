<?php
/**
 * Plugin Name:       EuroComply CBAM
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Carbon Border Adjustment Mechanism (Reg. (EU) 2023/956 + implementing Reg. 2023/1773): per-product CN-8 mapping to CBAM goods categories (cement / iron & steel / aluminium / fertilisers / electricity / hydrogen / downstream), embedded-emissions tracking (direct + indirect tCO2e, default vs verified flag, production route, country of origin), import declarations register, quarterly Q-report builder for the transitional period (Oct 2023 – Dec 2025), accredited-verifier directory, declarant register (EORI + authorised CBAM declarant ID from 2026). Pro: TARIC sync, CBAM Registry API submission, signed PDF, supplier portal.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-cbam
 * Domain Path:       /languages
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_CBAM_VERSION', '0.1.0' );
define( 'EUROCOMPLY_CBAM_FILE', __FILE__ );
define( 'EUROCOMPLY_CBAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_CBAM_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_CBAM_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_CBAM_SLUG', 'eurocomply-cbam' );
define( 'EUROCOMPLY_CBAM_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_CBAM_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\CBAM\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_CBAM_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\CBAM\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\CBAM\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\CBAM\Plugin::instance();
	}
);
