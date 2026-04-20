<?php
/**
 * Plugin Name:       EuroComply EU VAT Validator & OSS Rates
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU VAT number validation (VIES), EU-27 VAT rates, B2B reverse-charge and OSS destination tax for WooCommerce. Part of the EuroComply compliance suite.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-vat-oss
 * Domain Path:       /languages
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_VAT_VERSION', '0.1.0' );
define( 'EUROCOMPLY_VAT_FILE', __FILE__ );
define( 'EUROCOMPLY_VAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_VAT_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_VAT_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_VAT_SLUG', 'eurocomply-vat-oss' );
define( 'EUROCOMPLY_VAT_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_VAT_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\VatOss\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_VAT_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\VatOss\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\VatOss\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\VatOss\Plugin::instance();
	}
);
