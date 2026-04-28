<?php
/**
 * Bootstrap for EuroComply AI Act Transparency.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

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
		ProviderStore::maybe_upgrade();
		DisclosureLog::maybe_upgrade();
		Admin::instance();
		PostMeta::instance();
		ContentDisplay::instance();
		Shortcodes::instance();
		CsvExport::instance();
		DsaBridge::register();
	}

	public static function activate() : void {
		ProviderStore::install();
		DisclosureLog::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-ai-act', false, dirname( EUROCOMPLY_AIACT_BASENAME ) . '/languages' );
	}
}
