<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		ReportStore::maybe_upgrade();
		AccessLog::maybe_upgrade();
		Recipient::register();
		Shortcodes::instance();
		Admin::instance();
		CsvExport::instance();
		DsarBridge::register();
	}

	public static function activate() : void {
		ReportStore::install();
		AccessLog::install();
		Recipient::ensure_role();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// Role + tables are kept on deactivation; uninstall.php removes them.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-whistleblower', false, dirname( EUROCOMPLY_WB_BASENAME ) . '/languages' );
	}
}
