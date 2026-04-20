<?php
/**
 * Plugin Name:       EuroComply — Legal Pages (Impressum / AGB / Datenschutz / Widerruf)
 * Plugin URI:        https://eurocomply.eu/plugins/legal-pages
 * Description:       Generate EU-compliant legal pages (Impressum, Datenschutzerklärung, AGB, Widerrufsbelehrung) for WordPress and WooCommerce. Country-specific templates for Germany, Austria, Switzerland, and all EU-27 (Pro).
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            EuroComply
 * Author URI:        https://eurocomply.eu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eurocomply-legal
 * Domain Path:       /languages
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

define( 'EUROCOMPLY_LEGAL_VERSION', '0.1.0' );
define( 'EUROCOMPLY_LEGAL_FILE', __FILE__ );
define( 'EUROCOMPLY_LEGAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EUROCOMPLY_LEGAL_URL', plugin_dir_url( __FILE__ ) );
define( 'EUROCOMPLY_LEGAL_BASENAME', plugin_basename( __FILE__ ) );

require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-plugin.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-admin.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-settings.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-templates.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-generator.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-publisher.php';
require_once EUROCOMPLY_LEGAL_DIR . 'includes/class-license.php';

register_activation_hook( __FILE__, array( '\\EuroComply\\LegalPages\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EuroComply\\LegalPages\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	\EuroComply\LegalPages\Plugin::instance()->boot();
} );
