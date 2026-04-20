<?php
/**
 * Plugin Name:       EuroComply Omnibus
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Omnibus Directive price-transparency helper for WooCommerce. Records every product price change and, when a product is on sale, displays the lowest price from the last 30 days next to the sale price (Article 6a Price Indication Directive). Pro: per-country reference, 90/180-day windows, PDF auditor reports, WPML / multi-currency, daily snapshot cron.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-omnibus
 * Domain Path:       /languages
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_OMNIBUS_VERSION', '0.1.0' );
define( 'EUROCOMPLY_OMNIBUS_FILE', __FILE__ );
define( 'EUROCOMPLY_OMNIBUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_OMNIBUS_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_OMNIBUS_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_OMNIBUS_SLUG', 'eurocomply-omnibus' );
define( 'EUROCOMPLY_OMNIBUS_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_OMNIBUS_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Omnibus\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_OMNIBUS_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Omnibus\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Omnibus\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Omnibus\Plugin::instance();
	}
);
