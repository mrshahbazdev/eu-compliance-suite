<?php
/**
 * Plugin Name:       EuroComply Cookie Consent
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       GDPR + ePrivacy cookie-consent banner with Google Consent Mode v2 built in. Part of the EuroComply compliance suite.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-cookie-consent
 * Domain Path:       /languages
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_CC_VERSION', '0.1.0' );
define( 'EUROCOMPLY_CC_FILE', __FILE__ );
define( 'EUROCOMPLY_CC_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_CC_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_CC_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_CC_SLUG', 'eurocomply-cookie-consent' );
define( 'EUROCOMPLY_CC_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_CC_MIN_WP', '6.2' );

// Lightweight PSR-4-ish autoloader for the EuroComply\CookieConsent namespace.
spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\CookieConsent\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_CC_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\CookieConsent\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\CookieConsent\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\CookieConsent\Plugin::instance();
	}
);
