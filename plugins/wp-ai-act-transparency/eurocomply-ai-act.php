<?php
/**
 * Plugin Name:       EuroComply AI Act Transparency
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU AI Act (Regulation 2024/1689) Article 50 transparency obligations: per-post AI-generated markers, deepfake labels, generative-AI provider registry, chatbot disclosure shortcode, auto-generated AI policy page, disclosure audit log. Pro: C2PA manifest signing, watermark detection, GPAI provider scorecard, REST API.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-ai-act
 * Domain Path:       /languages
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_AIACT_VERSION', '0.1.0' );
define( 'EUROCOMPLY_AIACT_FILE', __FILE__ );
define( 'EUROCOMPLY_AIACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_AIACT_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_AIACT_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_AIACT_SLUG', 'eurocomply-ai-act' );
define( 'EUROCOMPLY_AIACT_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_AIACT_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\AIAct\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_AIACT_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\AIAct\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\AIAct\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\AIAct\Plugin::instance();
	}
);
