<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

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
		ClaimStore::maybe_upgrade();
		LabelStore::maybe_upgrade();
		ProductMeta::instance();
		Admin::instance();
		Shortcodes::instance();
		Scanner::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		ClaimStore::install();
		LabelStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op; substantiation files retained.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-green-claims', false, dirname( EUROCOMPLY_GC_BASENAME ) . '/languages' );
	}
}
