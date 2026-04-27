<?php
/**
 * CSV export for the Omnibus price history log.
 *
 * Free tier caps the export at 500 rows. Pro uses a streaming cursor (see
 * the Pro features tab) and supports per-product / per-date filters.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const NONCE_ACTION = 'eurocomply_omnibus_export';
	public const ACTION       = 'eurocomply_omnibus_export';

	public static function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-omnibus' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$limit = License::is_pro() ? 5000 : 500;
		$rows  = PriceStore::recent( $limit );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-omnibus-history.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv(
			$out,
			array(
				'id',
				'product_id',
				'parent_id',
				'regular_price',
				'sale_price',
				'effective_price',
				'currency',
				'trigger_source',
				'recorded_at',
			)
		);
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					(int) $row['id'],
					(int) $row['product_id'],
					(int) $row['parent_id'],
					(string) $row['regular_price'],
					null === $row['sale_price'] ? '' : (string) $row['sale_price'],
					(string) $row['effective_price'],
					(string) $row['currency'],
					(string) $row['trigger_source'],
					(string) $row['recorded_at'],
				)
			);
		}
		fclose( $out );
		exit;
	}
}
