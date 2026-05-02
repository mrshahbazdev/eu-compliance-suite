<?php
/**
 * Plugin singleton.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

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
		ProductStore::maybe_upgrade();
		DefectStore::maybe_upgrade();
		ClaimStore::maybe_upgrade();
		DisclosureStore::maybe_upgrade();
		Admin::instance();
		Shortcodes::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		ProductStore::install();
		DefectStore::install();
		ClaimStore::install();
		DisclosureStore::install();
		add_option( 'eurocomply_pl_settings', Settings::defaults() );
	}

	public static function deactivate() : void {
		// retained for the 10-year (general) / 25-year (latent injury) PLD limitation horizon.
	}
}
