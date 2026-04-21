<?php
/**
 * Plugin Name:       EuroComply Age Verification
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU / EEA age-verification gate for WordPress and WooCommerce with per-country minimum-age rules (DE JMStV, FR ARCOM, IT/ES/NL alcohol laws). Site-wide modal, WooCommerce product-category gating, DOB-based verification, hashed-IP audit log. Pro: AusweisIdent / eID, SCHUFA age-check, Veriff biometric, SMS OTP, ID-document upload, parental consent workflow.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-age-verification
 * Domain Path:       /languages
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_AV_VERSION', '0.1.0' );
define( 'EUROCOMPLY_AV_FILE', __FILE__ );
define( 'EUROCOMPLY_AV_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_AV_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_AV_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_AV_SLUG', 'eurocomply-age-verification' );
define( 'EUROCOMPLY_AV_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_AV_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\AgeVerification\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_AV_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\AgeVerification\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\AgeVerification\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\AgeVerification\Plugin::instance();
	}
);
