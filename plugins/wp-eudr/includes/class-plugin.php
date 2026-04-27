<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

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
		SupplierStore::maybe_upgrade();
		PlotStore::maybe_upgrade();
		ShipmentStore::maybe_upgrade();
		RiskStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		SupplierStore::install();
		PlotStore::install();
		ShipmentStore::install();
		RiskStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-eudr', false, dirname( EUROCOMPLY_EUDR_BASENAME ) . '/languages' );
	}
}
