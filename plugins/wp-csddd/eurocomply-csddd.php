<?php
/**
 * Plugin Name:       EuroComply CSDDD
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Corporate Sustainability Due Diligence Directive (Dir. (EU) 2024/1760) toolkit: chain-of-activities supplier register (Tier 1/2/3+) + adverse-impact register (12 human-rights + 6 environmental categories) + Art. 10 preventive / Art. 11 corrective action plans + Art. 14 stakeholder complaints mechanism + Art. 22 climate transition plan + Art. 16 annual due-diligence statement (CSRD-linked) + auto-generated due-diligence policy + 9-tab admin + CSV. Pro: ESG data-API ingestion, supplier survey portal, signed PDF audit, REST API.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-csddd
 * Domain Path:       /languages
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_CSDDD_VERSION',  '0.1.0' );
define( 'EUROCOMPLY_CSDDD_FILE',     __FILE__ );
define( 'EUROCOMPLY_CSDDD_DIR',      plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_CSDDD_URL',      plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_CSDDD_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_CSDDD_SLUG',     'eurocomply-csddd' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\CSDDD\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_CSDDD_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\CSDDD\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\CSDDD\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\CSDDD\Plugin::instance();
	}
);
