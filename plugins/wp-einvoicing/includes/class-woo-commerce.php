<?php
/**
 * WooCommerce hooks for EuroComply E-Invoicing.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce {

	private static ?WooCommerce $instance = null;

	public static function instance() : WooCommerce {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_status_completed' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_status_processing' ), 20, 1 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_download_action' ), 10, 2 );
	}

	public function on_status_completed( int $order_id ) : void {
		$this->maybe_generate( $order_id, 'completed' );
	}

	public function on_status_processing( int $order_id ) : void {
		$this->maybe_generate( $order_id, 'processing' );
	}

	private function maybe_generate( int $order_id, string $current_status ) : void {
		$settings = Settings::get();
		if ( empty( $settings['auto_generate'] ) ) {
			return;
		}
		if ( (string) $settings['trigger_status'] !== $current_status ) {
			return;
		}
		if ( null !== InvoiceStore::latest_for_order( $order_id ) ) {
			return; // Already generated; avoid duplicates on status churn.
		}
		InvoiceGenerator::generate_for_order( $order_id );
	}

	/**
	 * @param array<string,array<string,string>> $actions
	 * @param \WC_Order                          $order
	 *
	 * @return array<string,array<string,string>>
	 */
	public function add_download_action( array $actions, $order ) : array {
		$row = InvoiceStore::latest_for_order( (int) $order->get_id() );
		if ( $row && ! empty( $row['file_url'] ) ) {
			$actions['eurocomply_einv'] = array(
				'url'  => (string) $row['file_url'],
				'name' => __( 'Download invoice', 'eurocomply-einvoicing' ),
			);
		}
		return $actions;
	}
}
