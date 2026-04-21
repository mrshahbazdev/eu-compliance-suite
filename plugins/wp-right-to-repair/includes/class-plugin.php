<?php
/**
 * Bootstrap for EuroComply Right-to-Repair.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

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
		SparePartsStore::maybe_upgrade();
		RepairerStore::maybe_upgrade();
		Admin::instance();
		ProductMeta::instance();
		ProductDisplay::instance();
		Shortcodes::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		SparePartsStore::install();
		RepairerStore::install();
		if ( ! get_option( 'eurocomply_r2r_settings' ) ) {
			add_option( 'eurocomply_r2r_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-r2r', false, dirname( EUROCOMPLY_R2R_BASENAME ) . '/languages' );
	}
}
