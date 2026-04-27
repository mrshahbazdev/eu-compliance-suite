<?php
/**
 * Plugin bootstrap.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	public static function activate() : void {
		Settings::seed_defaults();
		IssueStore::install_table();
		StatementPage::maybe_create();
	}

	public static function deactivate() : void {
		// Intentionally non-destructive. Issue data and statement page survive.
	}

	private function boot() : void {
		load_plugin_textdomain( 'eurocomply-eaa', false, dirname( EUROCOMPLY_EAA_BASENAME ) . '/languages' );

		Frontend::instance();
		Scanner::instance();
		Statement::instance();

		if ( is_admin() ) {
			Admin::instance();
		}
	}
}
