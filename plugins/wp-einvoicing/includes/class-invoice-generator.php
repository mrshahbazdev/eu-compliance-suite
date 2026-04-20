<?php
/**
 * Invoice generator orchestrator.
 *
 * Pulls buyer/line data from a WooCommerce order, builds the Factur-X CII XML,
 * renders a hybrid PDF with the XML attached, writes it to the uploads dir,
 * and records a row in the invoice log.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InvoiceGenerator {

	/**
	 * @return array{ok:bool,message:string,log_id?:int,file_path?:string,file_url?:string}
	 */
	public static function generate_for_order( int $order_id ) : array {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'WooCommerce is not active.', 'eurocomply-einvoicing' ),
			);
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'ok'      => false,
				'message' => __( 'Order not found.', 'eurocomply-einvoicing' ),
			);
		}

		$settings = Settings::get();
		$profile  = License::is_pro() ? (string) $settings['invoice_profile'] : 'minimum';
		$data     = self::build_payload( $order, $settings, $profile );

		$xml = FacturxXml::build( $data );
		$pdf = FacturxPdf::build( $data, $xml );

		$dir_info = self::ensure_storage_dir();
		if ( ! $dir_info['ok'] ) {
			return array(
				'ok'      => false,
				'message' => (string) $dir_info['message'],
			);
		}

		$filename = sanitize_file_name( $data['invoice_number'] . '.pdf' );
		$path     = trailingslashit( $dir_info['path'] ) . $filename;
		$url      = trailingslashit( $dir_info['url'] ) . rawurlencode( $filename );

		$written = file_put_contents( $path, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			return array(
				'ok'      => false,
				'message' => __( 'Failed to write invoice PDF to disk.', 'eurocomply-einvoicing' ),
			);
		}

		$log_id = InvoiceStore::record(
			array(
				'order_id'       => $order_id,
				'invoice_number' => $data['invoice_number'],
				'profile'        => $profile,
				'total'          => (float) $data['totals']['grand_total'],
				'currency'       => $data['currency'],
				'file_path'      => $path,
				'file_url'       => $url,
				'status'         => 'generated',
				'message'        => '',
			)
		);

		$order->update_meta_data( '_eurocomply_einv_invoice_number', $data['invoice_number'] );
		$order->update_meta_data( '_eurocomply_einv_file_path', $path );
		$order->update_meta_data( '_eurocomply_einv_file_url', $url );
		$order->save();

		return array(
			'ok'        => true,
			'message'   => sprintf(
				/* translators: 1: invoice number, 2: profile name */
				__( 'Invoice %1$s generated (Factur-X %2$s).', 'eurocomply-einvoicing' ),
				$data['invoice_number'],
				strtoupper( $profile )
			),
			'log_id'    => $log_id,
			'file_path' => $path,
			'file_url'  => $url,
		);
	}

	/**
	 * @param \WC_Order           $order
	 * @param array<string,mixed> $settings
	 *
	 * @return array<string,mixed>
	 */
	private static function build_payload( $order, array $settings, string $profile ) : array {
		$invoice_number = self::invoice_number( $order, $settings );
		$buyer_name     = trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() );
		if ( '' === $buyer_name ) {
			$buyer_name = (string) $order->get_billing_company();
		}
		$buyer_company = (string) $order->get_billing_company();
		if ( '' !== $buyer_company ) {
			$buyer_name = $buyer_company;
		}

		$total        = (float) $order->get_total();
		$total_tax    = (float) $order->get_total_tax();
		$currency     = strtoupper( (string) ( $order->get_currency() ?: $settings['currency'] ) );
		$tax_basis    = max( 0.0, $total - $total_tax );

		return array(
			'profile'        => $profile,
			'invoice_number' => $invoice_number,
			'issue_date'     => gmdate( 'Y-m-d' ),
			'type_code'      => '380',
			'currency'       => $currency,
			'seller'         => array(
				'name'    => (string) $settings['seller_name'],
				'vat_id'  => (string) $settings['seller_vat_id'],
				'country' => (string) $settings['seller_country'],
			),
			'buyer'          => array(
				'name'      => $buyer_name,
				'reference' => (string) $order->get_order_number(),
			),
			'totals'         => array(
				'line_total'  => $tax_basis,
				'tax_basis'   => $tax_basis,
				'tax_total'   => $total_tax,
				'grand_total' => $total,
				'due_payable' => $total,
			),
		);
	}

	private static function invoice_number( $order, array $settings ) : string {
		$existing = (string) $order->get_meta( '_eurocomply_einv_invoice_number' );
		if ( '' !== $existing ) {
			return $existing;
		}
		$prefix = (string) ( $settings['invoice_prefix'] ?? 'INV-' );
		return $prefix . (string) $order->get_order_number();
	}

	/**
	 * @return array{ok:bool,path:string,url:string,message:string}
	 */
	private static function ensure_storage_dir() : array {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return array(
				'ok'      => false,
				'path'    => '',
				'url'     => '',
				'message' => (string) $uploads['error'],
			);
		}
		$base_path = trailingslashit( $uploads['basedir'] ) . 'eurocomply-einvoicing';
		$base_url  = trailingslashit( $uploads['baseurl'] ) . 'eurocomply-einvoicing';
		if ( ! wp_mkdir_p( $base_path ) ) {
			return array(
				'ok'      => false,
				'path'    => '',
				'url'     => '',
				'message' => __( 'Could not create invoice storage directory.', 'eurocomply-einvoicing' ),
			);
		}
		$htaccess = $base_path . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return array(
			'ok'      => true,
			'path'    => $base_path,
			'url'     => $base_url,
			'message' => '',
		);
	}
}
