<?php
/**
 * Bootstrap for EuroComply DSA Transparency.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

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
		NoticeStore::maybe_upgrade();
		StatementStore::maybe_upgrade();
		TraderStore::maybe_upgrade();
		Admin::instance();
		NoticeForm::instance();
		TraderForm::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		NoticeStore::install();
		StatementStore::install();
		TraderStore::install();
		if ( ! get_option( 'eurocomply_dsa_settings' ) ) {
			add_option( 'eurocomply_dsa_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Notice / statement / trader records are preserved — DSA audit horizon is 5 years.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-dsa', false, dirname( EUROCOMPLY_DSA_BASENAME ) . '/languages' );
	}
}
