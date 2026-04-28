<?php
/**
 * Bootstrap for EuroComply NIS2 & CRA.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

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
		EventStore::maybe_upgrade();
		IncidentStore::maybe_upgrade();
		Admin::instance();
		EventLogger::instance();
		VulnReportForm::instance();
		CsvExport::instance();
		DsarBridge::register();
	}

	public static function activate() : void {
		EventStore::install();
		IncidentStore::install();
		if ( ! get_option( 'eurocomply_nis2_settings' ) ) {
			add_option( 'eurocomply_nis2_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Event log and incident register are preserved for NIS2 audit horizon.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-nis2', false, dirname( EUROCOMPLY_NIS2_BASENAME ) . '/languages' );
	}
}
