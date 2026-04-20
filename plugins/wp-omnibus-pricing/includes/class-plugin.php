<?php
/**
 * Bootstrap for EuroComply Omnibus.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

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
		PriceStore::maybe_upgrade();
		Admin::instance();
		if ( class_exists( 'WooCommerce' ) ) {
			WooCommerce::instance();
		}
	}

	public static function activate() : void {
		PriceStore::install();
		if ( ! get_option( 'eurocomply_omnibus_settings' ) ) {
			add_option( 'eurocomply_omnibus_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Price history is preserved (auditors may request 24 months of records).
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-omnibus', false, dirname( EUROCOMPLY_OMNIBUS_BASENAME ) . '/languages' );
	}
}
