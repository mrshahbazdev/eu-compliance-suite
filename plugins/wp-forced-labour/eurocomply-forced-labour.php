<?php
/**
 * Plugin Name: EuroComply Forced Labour
 * Plugin URI: https://eurocomply.eu/plugins/forced-labour
 * Description: Toolkit for Reg. (EU) 2024/3015 prohibiting products made with forced labour on the EU market — supplier risk register, country/sector risk index, audit certification log, public information submissions (Art. 9), withdrawal procedure log.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: EuroComply
 * Author URI: https://eurocomply.eu
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eurocomply-forced-labour
 * Domain Path: /languages
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_FL_VERSION', '0.1.0' );
define( 'EUROCOMPLY_FL_FILE', __FILE__ );
define( 'EUROCOMPLY_FL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_FL_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_FL_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_FL_SLUG', 'eurocomply-forced-labour' );

spl_autoload_register(
	static function ( string $class ) : void {
		if ( 0 !== strpos( $class, 'EuroComply\\ForcedLabour\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'EuroComply\\ForcedLabour\\' ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = EUROCOMPLY_FL_DIR . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\ForcedLabour\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\ForcedLabour\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\ForcedLabour\Plugin::instance();
		load_plugin_textdomain( 'eurocomply-forced-labour', false, dirname( EUROCOMPLY_FL_BASENAME ) . '/languages' );
	}
);
