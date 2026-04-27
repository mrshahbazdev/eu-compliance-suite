<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

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
		SnapshotStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
		Rest::instance();
		add_action( 'eurocomply_dashboard_daily_snapshot', array( '\\EuroComply\\Dashboard\\Aggregator', 'cron_snapshot' ) );
	}

	public static function activate() : void {
		SnapshotStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
		if ( License::is_pro() && ! wp_next_scheduled( 'eurocomply_dashboard_daily_snapshot' ) ) {
			wp_schedule_event( time() + 600, 'daily', 'eurocomply_dashboard_daily_snapshot' );
		}
	}

	public static function deactivate() : void {
		$ts = wp_next_scheduled( 'eurocomply_dashboard_daily_snapshot' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'eurocomply_dashboard_daily_snapshot' );
		}
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-dashboard', false, dirname( EUROCOMPLY_DASH_BASENAME ) . '/languages' );
	}
}
