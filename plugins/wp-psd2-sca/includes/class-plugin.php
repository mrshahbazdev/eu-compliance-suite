<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

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
		TransactionStore::maybe_upgrade();
		ConsentStore::maybe_upgrade();
		TppStore::maybe_upgrade();
		FraudStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		TransactionStore::install();
		ConsentStore::install();
		TppStore::install();
		FraudStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-psd2-sca', false, dirname( EUROCOMPLY_PSD2_BASENAME ) . '/languages' );
	}
}
