<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

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
		MaterialityStore::maybe_upgrade();
		DatapointStore::maybe_upgrade();
		AssuranceStore::maybe_upgrade();
		ReportStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		MaterialityStore::install();
		DatapointStore::install();
		AssuranceStore::install();
		ReportStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-csrd-esrs', false, dirname( EUROCOMPLY_CSRD_BASENAME ) . '/languages' );
	}
}
