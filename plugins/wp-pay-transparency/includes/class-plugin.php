<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

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
		CategoryStore::maybe_upgrade();
		EmployeeStore::maybe_upgrade();
		RequestStore::maybe_upgrade();
		ReportStore::maybe_upgrade();
		Admin::instance();
		Shortcodes::instance();
		JobAd::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		CategoryStore::install();
		EmployeeStore::install();
		RequestStore::install();
		ReportStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// Tables retained until uninstall.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-pay-transparency', false, dirname( EUROCOMPLY_PT_BASENAME ) . '/languages' );
	}
}
