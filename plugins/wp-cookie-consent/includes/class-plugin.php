<?php
/**
 * Plugin bootstrap.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap that wires up admin, frontend banner, and REST endpoints.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Return the singleton instance.
	 */
	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Plugin activation: create log table, seed defaults.
	 */
	public static function activate() : void {
		ConsentLog::install();
		Settings::seed_defaults();
	}

	/**
	 * Plugin deactivation: keep data, nothing to do.
	 */
	public static function deactivate() : void {
		// Intentionally non-destructive.
	}

	/**
	 * Wire WordPress hooks.
	 */
	private function boot() : void {
		load_plugin_textdomain( 'eurocomply-cookie-consent', false, dirname( EUROCOMPLY_CC_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			Admin::instance();
		}

		Banner::instance();
		Gcm::instance();
		Rest::instance();
	}
}
