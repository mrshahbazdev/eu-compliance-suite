<?php
/**
 * Plugin bootstrap.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap. Wires admin, WooCommerce product fields, frontend block, compliance dashboard.
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
		Settings::seed_defaults();
	}

	public static function deactivate() : void {
		// Intentionally non-destructive.
	}

	private function boot() : void {
		load_plugin_textdomain( 'eurocomply-gpsr', false, dirname( EUROCOMPLY_GPSR_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			Admin::instance();
		}

		add_action(
			'plugins_loaded',
			static function () : void {
				if ( class_exists( '\\WooCommerce' ) || function_exists( 'WC' ) ) {
					ProductFields::instance();
					Frontend::instance();
				}
			},
			20
		);
	}
}
