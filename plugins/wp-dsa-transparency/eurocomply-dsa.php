<?php
/**
 * Plugin Name:       EuroComply DSA Transparency
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Digital Services Act (Regulation (EU) 2022/2065) compliance helper for WordPress / WooCommerce marketplaces. Implements Article 16 notice-and-action, Article 17 statements of reasons, Article 30 trader traceability (KYBP), and Article 15/24 transparency reporting. Pro: DSA Transparency Database submission (XML), out-of-court dispute workflow, strike/reputation system, marketplace plugin integrations (WC Vendors / Dokan / WCFM), scheduled annual cron, multi-language T&Cs.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-dsa
 * Domain Path:       /languages
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_DSA_VERSION', '0.1.0' );
define( 'EUROCOMPLY_DSA_FILE', __FILE__ );
define( 'EUROCOMPLY_DSA_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_DSA_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_DSA_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_DSA_SLUG', 'eurocomply-dsa' );
define( 'EUROCOMPLY_DSA_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_DSA_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\DSA\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_DSA_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\DSA\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\DSA\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\DSA\Plugin::instance();
	}
);
