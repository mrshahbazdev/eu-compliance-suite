<?php
/**
 * Bootstrap for EuroComply CSDDD.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

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
		RiskStore::maybe_upgrade();
		ActionStore::maybe_upgrade();
		ComplaintStore::maybe_upgrade();
		Admin::instance();
		Shortcodes::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		SupplierStore::install();
		RiskStore::install();
		ActionStore::install();
		ComplaintStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Due-diligence records preserved (Art. 16 5-year audit horizon).
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-csddd', false, dirname( EUROCOMPLY_CSDDD_BASENAME ) . '/languages' );
	}
}
