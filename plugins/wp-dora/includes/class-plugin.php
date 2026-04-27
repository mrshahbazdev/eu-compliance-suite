<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

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
		IncidentStore::maybe_upgrade();
		ThirdPartyStore::maybe_upgrade();
		TestStore::maybe_upgrade();
		PolicyStore::maybe_upgrade();
		IntelStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		IncidentStore::install();
		ThirdPartyStore::install();
		TestStore::install();
		PolicyStore::install();
		IntelStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-dora', false, dirname( EUROCOMPLY_DORA_BASENAME ) . '/languages' );
	}
}
