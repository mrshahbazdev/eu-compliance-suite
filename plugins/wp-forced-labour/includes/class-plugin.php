<?php
/**
 * Plugin singleton.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

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
		SupplierStore::maybe_upgrade();
		RiskStore::maybe_upgrade();
		AuditStore::maybe_upgrade();
		SubmissionStore::maybe_upgrade();
		WithdrawalStore::maybe_upgrade();

		Admin::instance();
		Shortcodes::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		SupplierStore::install();
		RiskStore::install();
		AuditStore::install();
		SubmissionStore::install();
		WithdrawalStore::install();
		add_option( 'eurocomply_fl_settings', Settings::defaults() );
	}

	public static function deactivate() : void {
		// retained for evidence horizon.
	}
}
