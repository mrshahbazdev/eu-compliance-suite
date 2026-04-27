<?php
/**
 * Bootstrap for EuroComply E-Invoicing.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

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
		InvoiceStore::maybe_upgrade();
		Admin::instance();
		if ( class_exists( 'WooCommerce' ) ) {
			WooCommerce::instance();
		}
	}

	public static function activate() : void {
		InvoiceStore::install();
		if ( ! get_option( 'eurocomply_einv_settings' ) ) {
			add_option( 'eurocomply_einv_settings', Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {
		// No-op. Invoice log and generated PDFs are preserved.
	}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-einvoicing', false, dirname( EUROCOMPLY_EINV_BASENAME ) . '/languages' );
	}
}
