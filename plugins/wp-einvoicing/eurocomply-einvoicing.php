<?php
/**
 * Plugin Name:       EuroComply E-Invoicing
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU e-invoicing for WooCommerce. Generates Factur-X MINIMUM profile hybrid invoices (PDF with embedded EN 16931 CII XML) on order completion. Admin log + per-order download. Pro: BASIC/EN 16931/EXTENDED profiles, Peppol BIS Billing 3.0 UBL, XRechnung, Chorus Pro, SDI, KSeF, Peppol Access Point sending.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-einvoicing
 * Domain Path:       /languages
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_EINV_VERSION', '0.1.0' );
define( 'EUROCOMPLY_EINV_FILE', __FILE__ );
define( 'EUROCOMPLY_EINV_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_EINV_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_EINV_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_EINV_SLUG', 'eurocomply-einvoicing' );
define( 'EUROCOMPLY_EINV_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_EINV_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\EInvoicing\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_EINV_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\EInvoicing\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\EInvoicing\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\EInvoicing\Plugin::instance();
	}
);
