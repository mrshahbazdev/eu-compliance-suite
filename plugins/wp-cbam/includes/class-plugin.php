<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

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
		ImportStore::maybe_upgrade();
		ReportStore::maybe_upgrade();
		VerifierStore::maybe_upgrade();
		ProductMeta::instance();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		ImportStore::install();
		ReportStore::install();
		VerifierStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// Tables retained until uninstall.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-cbam', false, dirname( EUROCOMPLY_CBAM_BASENAME ) . '/languages' );
	}
}
