<?php
/**
 * Bootstrap for EuroComply Age Verification.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

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
		VerificationStore::maybe_upgrade();
		Admin::instance();
		AgeGate::instance();
		CsvExport::instance();
		if ( class_exists( 'WooCommerce' ) ) {
			WooCommerce::instance();
		}
	}

	public static function activate() : void {
		VerificationStore::install();
		if ( ! get_option( 'eurocomply_av_settings' ) ) {
			add_option( 'eurocomply_av_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Verification log is preserved — regulators (DE KJM, FR ARCOM) may audit 12+ months.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-age-verification', false, dirname( EUROCOMPLY_AV_BASENAME ) . '/languages' );
	}
}
