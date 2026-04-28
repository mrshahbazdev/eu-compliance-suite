<?php
/**
 * Bootstrap for EuroComply GDPR DSAR.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

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
		RequestStore::maybe_upgrade();
		Admin::instance();
		RequestForm::instance();
		CsvExport::instance();
		ErasureManager::instance();
		Nis2Bridge::register();
	}

	public static function activate() : void {
		RequestStore::install();
		if ( ! get_option( 'eurocomply_dsar_settings' ) ) {
			add_option( 'eurocomply_dsar_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Request log is preserved — DPAs may audit up to 3 years.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-dsar', false, dirname( EUROCOMPLY_DSAR_BASENAME ) . '/languages' );
	}
}
