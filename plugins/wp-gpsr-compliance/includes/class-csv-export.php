<?php
/**
 * CSV export of product GPSR compliance status.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	/**
	 * Stream a CSV compliance report and exit.
	 */
	public static function stream() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-gpsr' ) );
		}

		$scan     = Compliance::scan( 1000 );
		$filename = 'eurocomply-gpsr-compliance-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$handle = fopen( 'php://output', 'w' );
		if ( false === $handle ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-gpsr' ) );
		}

		fputcsv(
			$handle,
			array(
				'product_id',
				'title',
				'status',
				'missing_required',
				'missing_recommended',
			)
		);

		foreach ( $scan['rows'] as $row ) {
			fputcsv(
				$handle,
				array(
					(int) $row['id'],
					(string) $row['title'],
					(string) $row['status'],
					implode( '|', (array) $row['missing_required'] ),
					implode( '|', (array) $row['missing_recommended'] ),
				)
			);
		}

		fclose( $handle );
		exit;
	}
}
