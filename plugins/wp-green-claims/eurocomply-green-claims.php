<?php
/**
 * Plugin Name:       EuroComply Green Claims
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Empowering Consumers for the Green Transition (Dir. (EU) 2024/825) toolkit: substantiation register for environmental claims, sustainability label registry (Annex I), banned-claim scanner ("eco-friendly" / "climate neutral" / "biodegradable" / "natural" without proof), per-product durability + software-update + repairability disclosures (CRD Art. 5a), Schema.org `Product` augmentation, auto-generated consumer-info page, 9-tab admin, CSV. Pro: third-party verification API, EPREL bridge, signed PDF substantiation file, REST API.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-green-claims
 * Domain Path:       /languages
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_GC_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_GC_FILE',     __FILE__ );
define( 'EUROCOMPLY_GC_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_GC_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_GC_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_GC_SLUG',     'eurocomply-green-claims' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\GreenClaims\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_GC_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\GreenClaims\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\GreenClaims\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\GreenClaims\Plugin::instance();
	}
);
