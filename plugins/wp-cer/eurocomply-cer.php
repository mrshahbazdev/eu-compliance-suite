<?php
/**
 * Plugin Name:       EuroComply CER
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Critical Entities Resilience Directive (EU) 2022/2557 toolkit: 11-sector taxonomy (energy · transport · banking · FMI · health · drinking water · waste water · digital infrastructure · public administration · space · food), essential-services register, asset / site / dependency map, Art. 12 four-yearly risk assessment, Art. 13 resilience-measures register, Art. 15 significant-disruption incident register with 24-hour early-warning + 1-month follow-up deadline tracker, cross-border dependency log. Pro: live competent-authority submission, background checks (Art. 14), signed PDF reports.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-cer
 * Domain Path:       /languages
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_CER_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_CER_FILE',     __FILE__ );
define( 'EUROCOMPLY_CER_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_CER_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_CER_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_CER_SLUG',     'eurocomply-cer' );
define( 'EUROCOMPLY_CER_MIN_PHP',  '7.4' );
define( 'EUROCOMPLY_CER_MIN_WP',   '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\CER\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_CER_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\CER\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\CER\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\CER\Plugin::instance();
	}
);
