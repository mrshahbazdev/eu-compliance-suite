<?php
/**
 * Plugin bootstrap.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap. Wires admin, WooCommerce checkout integration, REST, and tax log.
 */
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
		TaxLog::install();
		Settings::seed_defaults();
	}

	public static function deactivate() : void {
		// Intentionally non-destructive.
	}

	private function boot() : void {
		load_plugin_textdomain( 'eurocomply-vat-oss', false, dirname( EUROCOMPLY_VAT_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			Admin::instance();
		}

		Rest::instance();

		// WooCommerce integration is only activated if WC is present at plugins_loaded+1.
		add_action(
			'plugins_loaded',
			static function () : void {
				if ( class_exists( '\\WooCommerce' ) || function_exists( 'WC' ) ) {
					WooCommerce::instance();
				}
			},
			20
		);
	}
}
