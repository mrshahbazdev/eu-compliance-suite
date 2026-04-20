<?php
/**
 * Plugin bootstrap.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

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
	}

	public static function deactivate() : void {
		// Intentionally non-destructive. Product meta and reports survive.
	}

	private function boot() : void {
		load_plugin_textdomain( 'eurocomply-epr', false, dirname( EUROCOMPLY_EPR_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			Admin::instance();
		}

		add_action(
			'plugins_loaded',
			static function () : void {
				if ( class_exists( '\\WooCommerce' ) || function_exists( 'WC' ) ) {
					ProductFields::instance();
				}
			},
			20
		);
	}
}
