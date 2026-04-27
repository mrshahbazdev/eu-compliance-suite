<?php
/**
 * CSV export for the invoice log.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const NONCE_ACTION = 'eurocomply_einv_export';
	public const ACTION       = 'eurocomply_einv_export';

	public static function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-einvoicing' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$rows = InvoiceStore::recent( 500 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-einvoicing-log.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'order_id', 'invoice_number', 'profile', 'total', 'currency', 'status', 'generated_at', 'file_path' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					(int) $row['id'],
					(int) $row['order_id'],
					(string) $row['invoice_number'],
					(string) $row['profile'],
					(string) $row['total'],
					(string) $row['currency'],
					(string) $row['status'],
					(string) $row['generated_at'],
					(string) $row['file_path'],
				)
			);
		}
		fclose( $out );
		exit;
	}
}
