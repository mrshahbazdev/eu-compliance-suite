<?php
/**
 * Plugin Name: EuroComply Product Liability
 * Plugin URI: https://eurocomply.eu/plugins/product-liability
 * Description: Toolkit for Dir. (EU) 2024/2853 (revised Product Liability Directive) — product/component register including software & AI, manufacturer/importer/EU-representative disclosure, defect reports, formal liability claims, Art. 7 evidence-disclosure log, software-update obligation tracker.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: EuroComply
 * Author URI: https://eurocomply.eu
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eurocomply-product-liability
 * Domain Path: /languages
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_PL_VERSION', '0.1.0' );
define( 'EUROCOMPLY_PL_FILE', __FILE__ );
define( 'EUROCOMPLY_PL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_PL_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_PL_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_PL_SLUG', 'eurocomply-product-liability' );

spl_autoload_register(
	static function ( string $class ) : void {
		if ( 0 !== strpos( $class, 'EuroComply\\ProductLiability\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'EuroComply\\ProductLiability\\' ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = EUROCOMPLY_PL_DIR . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\ProductLiability\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\ProductLiability\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\ProductLiability\Plugin::instance();
		load_plugin_textdomain( 'eurocomply-product-liability', false, dirname( EUROCOMPLY_PL_BASENAME ) . '/languages' );
	}
);
