<?php
/**
 * Plugin Name:       EuroComply GDPR DSAR
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       GDPR Data Subject Access Request handler for WordPress and WooCommerce. Public request form (access / erase / portability / rectification / objection), email-token identity verification, 30-day deadline tracking, one-click JSON + CSV ZIP export, pseudonymisation + WC anonymiser, request log. Pro: CRM erasers (HubSpot / Mailchimp / Stripe / ActiveCampaign), SFTP delivery, signed audit PDF, MFA verification, multi-site aggregator.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-dsar
 * Domain Path:       /languages
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_DSAR_VERSION', '0.1.0' );
define( 'EUROCOMPLY_DSAR_FILE', __FILE__ );
define( 'EUROCOMPLY_DSAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_DSAR_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_DSAR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_DSAR_SLUG', 'eurocomply-dsar' );
define( 'EUROCOMPLY_DSAR_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_DSAR_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\DSAR\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_DSAR_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\DSAR\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\DSAR\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\DSAR\Plugin::instance();
	}
);
