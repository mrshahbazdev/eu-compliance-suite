<?php
/**
 * Plugin Name:       EuroComply Whistleblower
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       EU Whistleblower Directive (Directive (EU) 2019/1937) internal reporting channel: anonymous + identified report submission, follow-up token for reporters, Designated Recipient role with tamper-evident access log, Art. 9 deadline tracker (7-day acknowledgement + 3-month feedback), EU external-authority directory, auto-generated whistleblower policy page. Pro: PGP-encrypted-at-rest bodies, off-site storage, 2FA, Slack/Teams alerts, REST API, signed PDF case bundle.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-whistleblower
 * Domain Path:       /languages
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_WB_VERSION', '0.1.0' );
define( 'EUROCOMPLY_WB_FILE', __FILE__ );
define( 'EUROCOMPLY_WB_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_WB_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_WB_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_WB_SLUG', 'eurocomply-whistleblower' );
define( 'EUROCOMPLY_WB_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_WB_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\Whistleblower\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_WB_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\Whistleblower\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\Whistleblower\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\Whistleblower\Plugin::instance();
	}
);
