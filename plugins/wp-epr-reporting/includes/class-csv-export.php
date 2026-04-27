<?php
/**
 * CSV export of the EPR scan results. One file per-country or combined.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public static function handle() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-epr' ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eurocomply_epr_csv' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'eurocomply-epr' ) );
		}

		$country = isset( $_GET['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['country'] ) ) ) : '';
		if ( '' !== $country && ! Countries::is_supported( $country ) ) {
			$country = '';
		}

		$rows = Reporting::scan();

		$filename = 'eurocomply-epr-' . ( '' === $country ? 'all' : strtolower( $country ) ) . '-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}

		$materials = array_keys( Countries::materials() );
		$header    = array( 'product_id', 'title', 'country', 'status', 'registration', 'missing', 'total_weight_g' );
		foreach ( $materials as $mat ) {
			$header[] = 'weight_' . $mat . '_g';
		}
		fputcsv( $out, $header );

		foreach ( $rows as $row ) {
			foreach ( (array) $row['countries'] as $code => $c ) {
				if ( '' !== $country && $code !== $country ) {
					continue;
				}
				$line = array(
					(int) $row['id'],
					(string) $row['title'],
					(string) $code,
					(string) ( $c['status'] ?? '' ),
					(string) ( $c['registration'] ?? '' ),
					implode( '|', (array) ( $c['missing'] ?? array() ) ),
					(float) $row['total_weight_g'],
				);
				foreach ( $materials as $mat ) {
					$line[] = (float) ( $row['materials'][ $mat ] ?? 0 );
				}
				fputcsv( $out, $line );
			}
		}

		fclose( $out );
		exit;
	}
}
