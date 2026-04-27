<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

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
		AssetStore::maybe_upgrade();
		WhitepaperStore::maybe_upgrade();
		CommunicationStore::maybe_upgrade();
		ComplaintStore::maybe_upgrade();
		DisclosureStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		AssetStore::install();
		WhitepaperStore::install();
		CommunicationStore::install();
		ComplaintStore::install();
		DisclosureStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-mica', false, dirname( EUROCOMPLY_MICA_BASENAME ) . '/languages' );
	}
}
