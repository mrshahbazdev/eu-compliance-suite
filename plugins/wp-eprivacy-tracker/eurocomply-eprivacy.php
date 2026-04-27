<?php
/**
 * Plugin Name:       EuroComply ePrivacy & Tracker Registry
 * Plugin URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * Description:       Static HTML + live cookie scanner for ePrivacy 2002/58 + GDPR Art. 7 compliance. Detects 80+ known third-party trackers (GA4, GTM, Meta Pixel, Hotjar, Clarity, LinkedIn Insight, TikTok, Pinterest, Klaviyo, Intercom, HubSpot, Segment, Mixpanel, Stripe, PayPal, etc.) per URL, observes browser cookies via JS sniffer, classifies by category (analytics / advertising / functional / social / preferences) and surfaces a compliance gap report against EuroComply Cookie Consent. Pro: hourly cron, headless Chrome deep scan, IAB TCF v2.2, signed PDF.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://github.com/mrshahbazdev/eu-compliance-suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-eprivacy
 * Domain Path:       /languages
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUROCOMPLY_EPR_VERSION', '0.1.0' );
define( 'EUROCOMPLY_EPR_FILE', __FILE__ );
define( 'EUROCOMPLY_EPR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_EPR_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_EPR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EUROCOMPLY_EPR_SLUG', 'eurocomply-eprivacy' );
define( 'EUROCOMPLY_EPR_MIN_PHP', '7.4' );
define( 'EUROCOMPLY_EPR_MIN_WP', '6.2' );

spl_autoload_register(
	static function ( string $class_name ) : void {
		$prefix = 'EuroComply\\EPrivacy\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$dir      = EUROCOMPLY_EPR_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$path = $dir . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $file ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\EuroComply\\EPrivacy\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\EPrivacy\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () : void {
		\EuroComply\EPrivacy\Plugin::instance();
	}
);
