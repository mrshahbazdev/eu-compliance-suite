<?php
/**
 * Plugin Name:       EuroComply NIS2 & CRA
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       NIS2 Directive (EU 2022/2555) and Cyber Resilience Act compliance toolkit for WordPress. Local security event log, incident register with Art. 23 deadlines (24h / 72h / 30d / final), EU CSIRT contact directory, notification templates, vulnerability-report shortcode. Pro: SIEM forwarding, MISP threat-intel, signed PDFs, REST API.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-nis2
 * Domain Path:       /languages
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_NIS2_VERSION', '0.1.0' );
define( 'EUROCOMPLY_NIS2_FILE', __FILE__ );
define( 'EUROCOMPLY_NIS2_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_NIS2_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_NIS2_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_NIS2_SLUG', 'eurocomply-nis2' );
define( 'EUROCOMPLY_NIS2_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_NIS2_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\NIS2\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_NIS2_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\NIS2\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\NIS2\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\NIS2\Plugin::instance();
	}
);
