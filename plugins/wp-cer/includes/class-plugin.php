<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

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
		ServiceStore::maybe_upgrade();
		AssetStore::maybe_upgrade();
		RiskStore::maybe_upgrade();
		MeasureStore::maybe_upgrade();
		IncidentStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		ServiceStore::install();
		AssetStore::install();
		RiskStore::install();
		MeasureStore::install();
		IncidentStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-cer', false, dirname( EUROCOMPLY_CER_BASENAME ) . '/languages' );
	}
}
