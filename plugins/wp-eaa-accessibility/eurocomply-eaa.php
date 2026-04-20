<?php
/**
 * Plugin Name:       EuroComply EAA Accessibility
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       European Accessibility Act (Directive 2019/882) readiness for WordPress and WooCommerce. Site-wide WCAG 2.1 AA scanner (alt text, heading order, form labels, link text, landmarks, basic contrast), issue store, accessibility statement generator (EAA Art. 7), skip-to-content link injector, and CSV export of findings. Part of the EuroComply compliance suite.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-eaa
 * Domain Path:       /languages
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_EAA_VERSION', '0.1.0' );
define( 'EUROCOMPLY_EAA_FILE', __FILE__ );
define( 'EUROCOMPLY_EAA_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_EAA_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_EAA_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_EAA_SLUG', 'eurocomply-eaa' );
define( 'EUROCOMPLY_EAA_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_EAA_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Eaa\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_EAA_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Eaa\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Eaa\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Eaa\Plugin::instance();
	},
	5
);
