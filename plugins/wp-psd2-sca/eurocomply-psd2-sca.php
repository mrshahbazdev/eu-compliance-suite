<?php
/**
 * Plugin Name:       EuroComply PSD2 / SCA
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       PSD2 (Directive (EU) 2015/2366) + SCA RTS (Reg. (EU) 2018/389) toolkit for WordPress / WooCommerce: SCA-applicability decision engine, exemption library (low-value, recurring, MIT, TRA, trusted-beneficiary), 3-DS2 challenge log, PSU consent register (Art. 10 RTS 90-day re-auth), TPP / AISP / PISP directory, fraud-event log + Art. 96(6) quarterly report builder, refund-deadline tracker (Art. 73), CSV export. Pro: live EBA TPP-register sync, signed PDF, REST/webhooks, WPML.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-psd2-sca
 * Domain Path:       /languages
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_PSD2_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_PSD2_FILE',     __FILE__ );
define( 'EUROCOMPLY_PSD2_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_PSD2_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_PSD2_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_PSD2_SLUG',     'eurocomply-psd2-sca' );
define( 'EUROCOMPLY_PSD2_MIN_PHP',  '7.4' );
define( 'EUROCOMPLY_PSD2_MIN_WP',   '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\PSD2\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_PSD2_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\PSD2\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\PSD2\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\PSD2\Plugin::instance();
	}
);
