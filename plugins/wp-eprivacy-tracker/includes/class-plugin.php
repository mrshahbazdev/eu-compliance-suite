<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

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
		ScanStore::maybe_upgrade();
		FindingStore::maybe_upgrade();
		CookieStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
		CookieObserver::instance();
	}

	public static function activate() : void {
		ScanStore::install();
		FindingStore::install();
		CookieStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// Tables are kept until uninstall.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-eprivacy', false, dirname( EUROCOMPLY_EPR_BASENAME ) . '/languages' );
	}
}
